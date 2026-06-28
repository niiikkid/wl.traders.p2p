<?php

namespace App\Http\Controllers;

use App\Http\Requests\News\FormatRequest;
use App\Http\Requests\News\ReactRequest;
use App\Http\Requests\News\StoreRequest;
use App\Http\Requests\News\TrackViewsRequest;
use App\Http\Resources\NewsPostResource;
use App\Models\NewsPost;
use App\Models\NewsPostReaction;
use App\Services\News\Features\NewsAiFormatter;
use App\Support\NewsTiptapEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class NewsController extends Controller
{
    private const SELECTABLE_VISIBLE_ROLE_NAMES = ['Trader', 'Support', 'Team Leader'];

    private const VIEW_TRACK_COOLDOWN_MINUTES = 30;

    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        $userRoleNames = $user->roles()->pluck('name')->values()->all();

        $newsQuery = NewsPost::query()
            ->with([
                'author:id,email',
                'reactions' => fn ($query) => $query
                    ->select('id', 'news_post_id', 'user_id', 'reaction')
                    ->where('user_id', $user->id),
            ])
            ->latest('id');

        if (! $user->hasRole('Super Admin')) {
            $newsQuery->visibleForRoles($userRoleNames);
        }

        $news = $newsQuery
            ->paginate($request->integer('per_page', 10));

        return response()->success([
            'data' => NewsPostResource::collection($news->items())->resolve(),
            'meta' => [
                'current_page' => $news->currentPage(),
                'last_page' => $news->lastPage(),
                'per_page' => $news->perPage(),
                'total' => $news->total(),
            ],
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;
        $now = now();

        $user->meta()->updateOrCreate(
            ['user_id' => $userId],
            ['news_last_read_at' => $now]
        );

        cache()->forget("news_unread_{$userId}");

        return response()->success([
            'news_last_read_at' => $now->toISOString(),
        ]);
    }

    public function format(FormatRequest $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('Super Admin'), 403);

        try {
            $result = (new NewsAiFormatter)->format(
                $request->validated('text'),
                $request->validated('title'),
            );
        } catch (RuntimeException $exception) {
            return response()->failWithMessage($exception->getMessage(), 422);
        }

        return response()->success($result);
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Super Admin'), 403);

        $payload = $request->validated();
        $title = $payload['title'];
        $contentJson = $payload['content_json'];
        $visibilityType = $payload['visibility_type'];
        $isVisibleForAll = $visibilityType === 'all';
        $visibleRoleNames = $isVisibleForAll ? null : array_values($payload['visible_roles']);

        $contentHtml = NewsTiptapEditor::jsonToHtml($contentJson);

        $coverImagePath = null;
        if ($request->hasFile('cover_image')) {
            $coverImagePath = $request->file('cover_image')->store('news-covers', 'public');
        }

        $newsPost = DB::transaction(function () use ($request, $title, $contentJson, $contentHtml, $coverImagePath, $isVisibleForAll, $visibleRoleNames) {
            return NewsPost::query()->create([
                'author_id' => $request->user()->id,
                'cover_image_path' => $coverImagePath,
                'title' => $title,
                'is_visible_for_all' => $isVisibleForAll,
                'visible_role_names' => $visibleRoleNames,
                'content_json' => $contentJson,
                'content_html' => $contentHtml,
            ]);
        });

        $newsPost->load('author:id,email');

        if ($request->expectsJson()) {
            return response()->successWithMessage(
                'Новость успешно опубликована',
                NewsPostResource::make($newsPost)->resolve(),
            );
        }

        return redirect()
            ->back()
            ->with('message', 'Новость успешно опубликована');
    }

    public function destroy(NewsPost $newsPost): RedirectResponse
    {
        abort_unless(request()->user()?->hasRole('Super Admin'), 403);

        if ($newsPost->cover_image_path) {
            Storage::disk('public')->delete($newsPost->cover_image_path);
        }

        $newsPostId = $newsPost->id;
        $newsPost->delete();

        if (request()->expectsJson()) {
            return response()->successWithMessage('Новость удалена', [
                'id' => $newsPostId,
            ]);
        }

        return redirect()
            ->back()
            ->with('message', 'Новость удалена');
    }

    public function trackViews(TrackViewsRequest $request): JsonResponse
    {
        $user = $request->user();
        $userRoleNames = $user->roles()->pluck('name')->values()->all();
        $userId = $user->id;
        $now = now();
        $cooldownBorder = $now->copy()->subMinutes(self::VIEW_TRACK_COOLDOWN_MINUTES);
        $postIds = collect($request->validated('post_ids'))
            ->map(fn (mixed $id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($postIds)) {
            return response()->json(['tracked' => 0]);
        }

        $newsQuery = NewsPost::query()
            ->whereIn('id', $postIds);

        if (! $user->hasRole('Super Admin')) {
            $newsQuery->visibleForRoles($userRoleNames);
        }

        $allowedPostIds = $newsQuery
            ->pluck('id')
            ->map(fn (mixed $id) => (int) $id)
            ->all();

        if (empty($allowedPostIds)) {
            return response()->json(['tracked' => 0]);
        }

        $trackedCount = DB::transaction(function () use ($userId, $allowedPostIds, $now, $cooldownBorder): int {
            $existingViews = DB::table('news_post_views')
                ->where('user_id', $userId)
                ->whereIn('news_post_id', $allowedPostIds)
                ->lockForUpdate()
                ->get(['news_post_id', 'last_viewed_at'])
                ->keyBy('news_post_id');

            $postIdsToTrack = collect($allowedPostIds)
                ->filter(function (int $postId) use ($existingViews, $cooldownBorder): bool {
                    $existingView = $existingViews->get($postId);
                    if (! $existingView) {
                        return true;
                    }

                    return Carbon::parse($existingView->last_viewed_at)
                        ->lte($cooldownBorder);
                })
                ->values()
                ->all();

            if (empty($postIdsToTrack)) {
                return 0;
            }

            $existingPostIdsToUpdate = collect($postIdsToTrack)
                ->filter(fn (int $postId) => $existingViews->has($postId))
                ->values()
                ->all();

            if (! empty($existingPostIdsToUpdate)) {
                DB::table('news_post_views')
                    ->where('user_id', $userId)
                    ->whereIn('news_post_id', $existingPostIdsToUpdate)
                    ->update([
                        'last_viewed_at' => $now,
                        'updated_at' => $now,
                    ]);
            }

            $newPostIdsToInsert = collect($postIdsToTrack)
                ->filter(fn (int $postId) => ! $existingViews->has($postId))
                ->values()
                ->all();

            if (! empty($newPostIdsToInsert)) {
                DB::table('news_post_views')->insert(
                    collect($newPostIdsToInsert)
                        ->map(fn (int $postId) => [
                            'news_post_id' => $postId,
                            'user_id' => $userId,
                            'last_viewed_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])
                        ->all()
                );
            }

            NewsPost::query()
                ->whereIn('id', $postIdsToTrack)
                ->increment('views_count');

            return count($postIdsToTrack);
        });

        return response()->json([
            'tracked' => $trackedCount,
        ]);
    }

    public function react(ReactRequest $request): JsonResponse
    {
        $user = $request->user();
        $userRoleNames = $user->roles()->pluck('name')->values()->all();
        $postId = (int) $request->validated('post_id');
        $reaction = $request->validated('reaction');

        $newsPost = DB::transaction(function () use ($user, $userRoleNames, $postId, $reaction) {
            $newsQuery = NewsPost::query()
                ->whereKey($postId)
                ->lockForUpdate();

            if (! $user->hasRole('Super Admin')) {
                $newsQuery->visibleForRoles($userRoleNames);
            }

            /** @var NewsPost $newsPost */
            $newsPost = $newsQuery->firstOrFail();

            $existingReaction = NewsPostReaction::query()
                ->where('news_post_id', $newsPost->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $existingReaction) {
                NewsPostReaction::query()->create([
                    'news_post_id' => $newsPost->id,
                    'user_id' => $user->id,
                    'reaction' => $reaction,
                ]);

                $this->incrementReactionCounter($newsPost, $reaction);

                return $newsPost->refresh();
            }

            if ($existingReaction->reaction !== $reaction) {
                $oldReaction = $existingReaction->reaction;
                $existingReaction->update(['reaction' => $reaction]);

                $this->decrementReactionCounter($newsPost, $oldReaction);
                $this->incrementReactionCounter($newsPost, $reaction);
            }

            return $newsPost->refresh();
        });

        return response()->json([
            'post' => [
                'id' => $newsPost->id,
                'likes_count' => (int) $newsPost->likes_count,
                'dislikes_count' => (int) $newsPost->dislikes_count,
                'user_reaction' => $reaction,
            ],
        ]);
    }

    private function incrementReactionCounter(NewsPost $newsPost, string $reaction): void
    {
        if ($reaction === 'up') {
            $newsPost->increment('likes_count');

            return;
        }

        $newsPost->increment('dislikes_count');
    }

    private function decrementReactionCounter(NewsPost $newsPost, string $reaction): void
    {
        if ($reaction === 'up') {
            NewsPost::query()
                ->whereKey($newsPost->id)
                ->update(['likes_count' => DB::raw('GREATEST(likes_count - 1, 0)')]);

            return;
        }

        NewsPost::query()
            ->whereKey($newsPost->id)
            ->update(['dislikes_count' => DB::raw('GREATEST(dislikes_count - 1, 0)')]);
    }
}
