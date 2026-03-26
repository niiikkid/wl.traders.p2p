<?php

namespace App\Http\Controllers;

use App\Http\Requests\News\StoreRequest;
use App\Http\Resources\NewsPostResource;
use App\Models\NewsPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Tiptap\Editor;

class NewsController extends Controller
{
    private const SELECTABLE_VISIBLE_ROLE_NAMES = ['Trader', 'Support', 'Team Leader'];

    public function index(): Response
    {
        $user = auth()->user();
        $userRoleNames = $user->roles()->pluck('name')->values()->all();

        $newsQuery = NewsPost::query()
            ->with('author:id,email')
            ->latest('id');

        if (! $user->hasRole('Super Admin')) {
            $newsQuery->visibleForRoles($userRoleNames);
        }

        $news = NewsPostResource::collection(
            $newsQuery
                ->paginate(request()->integer('per_page', 10))
                ->withQueryString()
        );

        return Inertia::render('News/Index', [
            'news' => $news,
            'canManageNews' => request()->routeIs('admin.news.*'),
            'newsRoleOptions' => collect(self::SELECTABLE_VISIBLE_ROLE_NAMES)
                ->map(fn (string $roleName) => ['value' => $roleName, 'label' => $roleName])
                ->values()
                ->toArray(),
        ]);
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

        $contentHtml = (new Editor)
            ->setContent($contentJson)
            ->getHTML();

        $coverImagePath = null;
        if ($request->hasFile('cover_image')) {
            $coverImagePath = $request->file('cover_image')->store('news-covers', 'public');
        }

        DB::transaction(function () use ($request, $title, $contentJson, $contentHtml, $coverImagePath, $isVisibleForAll, $visibleRoleNames) {
            NewsPost::query()->create([
                'author_id' => $request->user()->id,
                'cover_image_path' => $coverImagePath,
                'title' => $title,
                'is_visible_for_all' => $isVisibleForAll,
                'visible_role_names' => $visibleRoleNames,
                'content_json' => $contentJson,
                'content_html' => $contentHtml,
            ]);
        });

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

        $newsPost->delete();

        return redirect()
            ->back()
            ->with('message', 'Новость удалена');
    }
}
