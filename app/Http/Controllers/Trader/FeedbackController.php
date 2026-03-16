<?php

namespace App\Http\Controllers\Trader;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trader\Feedback\StoreRequest;
use App\Http\Requests\Trader\Feedback\ToggleRequest;
use App\Http\Resources\FeedbackResource;
use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canModerate = $request->routeIs('admin.*');
        $showHidden = $canModerate ? $request->boolean('show_hidden', false) : false;
        $feedbackCooldownEndsAt = null;

        $feedPage = max((int) $request->integer('feed_page', 1), 1);
        $myPage = max((int) $request->integer('my_page', 1), 1);

        $feedQuery = Feedback::query()
            ->with('author:id,name,email')
            ->orderByDesc('created_at');

        if ($canModerate) {
            $feedQuery
                ->withExists([
                    'favoritedBy as is_starred' => fn ($query) => $query->where('users.id', $user->id),
                    'hiddenBy as is_hidden' => fn ($query) => $query->where('users.id', $user->id),
                ])
                ->when(
                    ! $showHidden,
                    fn ($query) => $query->whereDoesntHave('hiddenBy', fn ($hiddenQuery) => $hiddenQuery->where('users.id', $user->id))
                );
        } else {
            $feedQuery->where('user_id', $user->id);
        }

        $feed = $feedQuery
            ->paginate(perPage: 15, pageName: 'feed_page', page: $feedPage)
            ->appends(['show_hidden' => $showHidden ? '1' : '0', 'my_page' => $myPage]);

        $myFeedbacks = Feedback::query()
            ->with('author:id,name,email')
            ->where('user_id', $user->id)
            ->when($canModerate, function ($query) use ($user) {
                $query->withExists([
                    'favoritedBy as is_starred' => fn ($favoriteQuery) => $favoriteQuery->where('users.id', $user->id),
                    'hiddenBy as is_hidden' => fn ($hiddenQuery) => $hiddenQuery->where('users.id', $user->id),
                ]);
            })
            ->orderByDesc('created_at')
            ->paginate(perPage: 10, pageName: 'my_page', page: $myPage)
            ->appends([
                'show_hidden' => $showHidden ? '1' : '0',
                'feed_page' => $feedPage,
            ]);

        if (! $canModerate) {
            $latestFeedback = Feedback::query()
                ->where('user_id', $user->id)
                ->latest('created_at')
                ->first(['created_at']);

            if ($latestFeedback !== null && $latestFeedback->created_at !== null) {
                $cooldownEndsAt = $latestFeedback->created_at->copy()->addSeconds(StoreRequest::COOLDOWN_SECONDS);

                if ($cooldownEndsAt->isFuture()) {
                    $feedbackCooldownEndsAt = $cooldownEndsAt->toISOString();
                }
            }
        }

        return Inertia::render('Feedback/Trader/Index', [
            'canModerate' => $canModerate,
            'showHidden' => $showHidden,
            'hiddenCount' => $canModerate ? $user->hiddenFeedbacks()->count() : 0,
            'feedbackCooldownEndsAt' => $feedbackCooldownEndsAt,
            'feed' => FeedbackResource::collection($feed),
            'myFeedbacks' => FeedbackResource::collection($myFeedbacks),
        ]);
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        Feedback::query()->create([
            'user_id' => $request->user()->id,
            'content' => trim((string) $request->input('content')),
        ]);

        return redirect()->back()->with('message', 'Фидбек отправлен.');
    }

    public function toggleFavorite(ToggleRequest $request, Feedback $feedback): RedirectResponse
    {
        abort_unless($request->routeIs('admin.*'), 403);

        $enabled = $request->boolean('enabled');
        $userId = $request->user()->id;

        if ($enabled) {
            $feedback->favoritedBy()->syncWithoutDetaching([$userId]);
        } else {
            $feedback->favoritedBy()->detach($userId);
        }

        return redirect()->back();
    }

    public function toggleHidden(ToggleRequest $request, Feedback $feedback): RedirectResponse
    {
        abort_unless($request->routeIs('admin.*'), 403);

        $enabled = $request->boolean('enabled');
        $userId = $request->user()->id;

        if ($enabled) {
            $feedback->hiddenBy()->syncWithoutDetaching([$userId]);
        } else {
            $feedback->hiddenBy()->detach($userId);
        }

        return redirect()->back();
    }
}
