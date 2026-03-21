<?php

namespace App\Http\Controllers;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEvent;
use App\Http\Requests\NotificationFilterRequest;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\NotificationRuleResource;
use App\Http\Resources\TelegramAccountResource;
use App\Models\Notification;
use App\Models\NotificationRule;
use App\Services\UserOnline\UserOnlinePeriodRecorder;
use App\Services\Money\Currency;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    protected const DEFAULT_SOUND_TRACK = 'radwimps.mp3';

    protected function buildIndexProps(NotificationFilterRequest $request): array
    {
        $user = $request->user();
        $filters = $request->filters();
        $audioTracks = $this->getAudioTracks();

        $query = Notification::query()
            ->where('user_id', $user->id)
            ->where('channel', NotificationChannel::IN_APP)
            ->when($filters['event'], function ($query) use ($filters) {
                $query->where('event', $filters['event']);
            })
            ->when($filters['delivery_status'], function ($query) use ($filters) {
                $query->where('status', $filters['delivery_status']);
            })
            ->when($filters['only_unread'], function ($query) {
                $query->whereNull('read_at');
            })
            ->latest('id');

        $notifications = NotificationResource::collection(
            $query->paginate(request()->per_page ?? 10)->withQueryString()
        );

        $rules = NotificationRuleResource::collection(
            NotificationRule::query()->where('user_id', $user->id)->get()
        )->resolve();

        $telegramAccount = TelegramAccountResource::make(
            services()->telegram()->getOrCreateForUser($user)
        )->resolve();

        $events = array_map(function (NotificationEvent $event) {
            return [
                'name' => $event->label(),
                'value' => $event->value,
            ];
        }, NotificationEvent::forUser($user));

        $channels = array_map(function (NotificationChannel $channel) {
            return [
                'name' => $channel->label(),
                'value' => $channel->value,
            ];
        }, NotificationChannel::cases());

        $deliveryStatuses = array_map(function (NotificationDeliveryStatus $status) {
            return [
                'name' => $status->label(),
                'value' => $status->value,
            ];
        }, NotificationDeliveryStatus::cases());

        $currencies = Currency::getAll()
            ->map(function (Currency $currency) {
                return [
                    'name' => strtoupper($currency->getCode()),
                    'value' => $currency->getCode(),
                ];
            })
            ->values()
            ->toArray();

        $filtersVariants = [
            'event' => $events,
            'channels' => $channels,
            'delivery_status' => $deliveryStatuses,
            'currency' => $currencies,
        ];

        $selectedTrack = $this->resolveSoundTrack(
            $user->meta?->notification_sound_track,
            $audioTracks
        );

        return [
            'notifications' => $notifications,
            'rules' => $rules,
            'filters' => $filters,
            'filtersVariants' => $filtersVariants,
            'telegramAccount' => $telegramAccount,
            'audioTracks' => $audioTracks,
            'notificationSoundSettings' => [
                'enabled' => $user->meta?->notification_sound_enabled ?? true,
                'track' => $selectedTrack,
            ],
        ];
    }

    protected function renderIndex(NotificationFilterRequest $request, string $view)
    {
        return Inertia::render($view, $this->buildIndexProps($request));
    }

    public function index(NotificationFilterRequest $request)
    {
        return $this->renderIndex($request, 'Notifications/Index');
    }

    public function markAllRead()
    {
        $user = auth()->user();

        Notification::query()
            ->where('user_id', $user->id)
            ->where('channel', NotificationChannel::IN_APP)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    public function markRead(Notification $notification)
    {
        Gate::authorize('access-to-self', $notification->user);

        $notification->update(['read_at' => now()]);

        return back();
    }

    public function markUnread(Notification $notification)
    {
        Gate::authorize('access-to-self', $notification->user);

        $notification->update(['read_at' => null]);

        return back();
    }

    public function ping(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $now = now();

        cache()->put("user-online-at-{$userId}", $now->toISOString());
        app(UserOnlinePeriodRecorder::class)->touch($userId, $now);

        $latestNotificationId = Notification::query()
            ->where('user_id', $userId)
            ->where('channel', NotificationChannel::IN_APP)
            ->latest('id')
            ->value('id');

        $unreadCount = Notification::query()
            ->where('user_id', $userId)
            ->where('channel', NotificationChannel::IN_APP)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'latest_notification_id' => $latestNotificationId ? (int) $latestNotificationId : null,
            'unread_count' => (int) $unreadCount,
        ]);
    }

    public function updateSoundSettings(Request $request)
    {
        $audioTracks = $this->getAudioTracks();
        $allowedTracks = array_column($audioTracks, 'value');

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'track' => ['nullable', 'string', Rule::in($allowedTracks)],
        ]);

        $resolvedTrack = $this->resolveSoundTrack($validated['track'] ?? null, $audioTracks);

        $request->user()->meta()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'notification_sound_enabled' => (bool) $validated['enabled'],
                'notification_sound_track' => $resolvedTrack,
            ]
        );

        return back();
    }

    protected function getAudioTracks(): array
    {
        $audioDirectory = public_path('audio');

        if (! File::isDirectory($audioDirectory)) {
            return [];
        }

        $tracks = collect(File::files($audioDirectory))
            ->filter(function ($file) {
                return $file->getExtension() === 'mp3';
            })
            ->sortBy(function ($file) {
                return $file->getFilename();
            })
            ->values()
            ->map(function ($file) {
                $name = $file->getFilename();

                return [
                    'name' => $name,
                    'value' => $name,
                    'url' => '/audio/' . $name,
                ];
            })
            ->toArray();

        return $tracks;
    }

    protected function resolveSoundTrack(?string $track, array $audioTracks): ?string
    {
        if (empty($audioTracks)) {
            return null;
        }

        $allowedTracks = array_column($audioTracks, 'value');

        if ($track && in_array($track, $allowedTracks, true)) {
            return $track;
        }

        if (in_array(self::DEFAULT_SOUND_TRACK, $allowedTracks, true)) {
            return self::DEFAULT_SOUND_TRACK;
        }

        return $audioTracks[0]['value'];
    }
}
