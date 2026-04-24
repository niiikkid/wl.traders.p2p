<?php

namespace App\Http\Controllers;

use App\Enums\NotificationEvent;
use App\Enums\NotificationMessageScope;
use App\Http\Resources\NotificationRuleResource;
use App\Http\Resources\TelegramAccountResource;
use App\Models\Dispute;
use App\Models\NotificationRule;
use App\Models\Order;
use App\Models\SmsLog;
use App\Models\UserMeta;
use App\Services\Money\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class NotificationController extends Controller
{
    protected const DEFAULT_SOUND_TRACK = 'radwimps.mp3';

    protected function buildIndexProps(Request $request): array
    {
        $user = $request->user();
        $audioTracks = $this->getAudioTracks();

        $rules = NotificationRuleResource::collection(
            NotificationRule::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->get()
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

        $currencies = Currency::getAll()
            ->map(function (Currency $currency) {
                return [
                    'name' => strtoupper($currency->getCode()),
                    'value' => $currency->getCode(),
                ];
            })
            ->values()
            ->toArray();

        return [
            'rules' => $rules,
            'telegramAccount' => $telegramAccount,
            'audioTracks' => $audioTracks,
            'notificationSoundSettings' => $this->buildNotificationSoundSettings($user->meta, $audioTracks),
            'filtersVariants' => [
                'event' => $events,
                'currency' => $currencies,
                'message_scope' => array_map(function (NotificationMessageScope $scope) {
                    return [
                        'name' => $scope->label(),
                        'value' => $scope->value,
                    ];
                }, NotificationMessageScope::cases()),
            ],
        ];
    }

    protected function renderIndex(Request $request, string $view)
    {
        return Inertia::render($view, $this->buildIndexProps($request));
    }

    public function index(Request $request)
    {
        return $this->renderIndex($request, 'Notifications/Index');
    }

    public function ping(Request $request)
    {
        $user = $request->user();

        abort_unless($user->hasAnyRole(['Trader', 'Super Admin']), 403);

        return response()->json([
            'latest_event_ids' => $this->resolveLatestEventIds($request),
        ]);
    }

    public function updateSoundSettings(Request $request)
    {
        $user = $request->user();

        abort_unless($user->hasAnyRole(['Trader', 'Super Admin']), 403);

        $audioTracks = $this->getAudioTracks();
        $allowedTracks = array_column($audioTracks, 'value');

        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.order_assigned.enabled' => ['required', 'boolean'],
            'settings.order_assigned.track' => ['nullable', 'string', Rule::in($allowedTracks)],
            'settings.dispute_opened.enabled' => ['required', 'boolean'],
            'settings.dispute_opened.track' => ['nullable', 'string', Rule::in($allowedTracks)],
            'settings.message_received.enabled' => ['required', 'boolean'],
            'settings.message_received.track' => ['nullable', 'string', Rule::in($allowedTracks)],
        ]);

        $settings = $validated['settings'];

        $user->meta()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'notification_sound_order_enabled' => (bool) $settings['order_assigned']['enabled'],
                'notification_sound_order_track' => $this->resolveSoundTrack($settings['order_assigned']['track'] ?? null, $audioTracks),
                'notification_sound_dispute_enabled' => (bool) $settings['dispute_opened']['enabled'],
                'notification_sound_dispute_track' => $this->resolveSoundTrack($settings['dispute_opened']['track'] ?? null, $audioTracks),
                'notification_sound_message_enabled' => (bool) $settings['message_received']['enabled'],
                'notification_sound_message_track' => $this->resolveSoundTrack($settings['message_received']['track'] ?? null, $audioTracks),
            ]
        );

        return back();
    }

    protected function resolveLatestEventIds(Request $request): array
    {
        $user = $request->user();

        if ($user->hasRole('Super Admin')) {
            return [
                'order_assigned' => (int) (Order::query()->max('id') ?? 0),
                'dispute_opened' => (int) (Dispute::query()->max('id') ?? 0),
                'message_received' => (int) (SmsLog::query()->max('id') ?? 0),
            ];
        }

        return [
            'order_assigned' => (int) (Order::query()->where('trader_id', $user->id)->max('id') ?? 0),
            'dispute_opened' => (int) (Dispute::query()->where('trader_id', $user->id)->max('id') ?? 0),
            'message_received' => (int) (SmsLog::query()->where('user_id', $user->id)->max('id') ?? 0),
        ];
    }

    protected function buildNotificationSoundSettings(?UserMeta $meta, array $audioTracks): array
    {
        return [
            'order_assigned' => [
                'enabled' => $meta?->notification_sound_order_enabled ?? true,
                'track' => $this->resolveSoundTrack($meta?->notification_sound_order_track, $audioTracks),
            ],
            'dispute_opened' => [
                'enabled' => $meta?->notification_sound_dispute_enabled ?? true,
                'track' => $this->resolveSoundTrack($meta?->notification_sound_dispute_track, $audioTracks),
            ],
            'message_received' => [
                'enabled' => $meta?->notification_sound_message_enabled ?? true,
                'track' => $this->resolveSoundTrack($meta?->notification_sound_message_track, $audioTracks),
            ],
        ];
    }

    protected function getAudioTracks(): array
    {
        $audioDirectory = public_path('audio');

        if (! File::isDirectory($audioDirectory)) {
            return [];
        }

        return collect(File::files($audioDirectory))
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
                    'url' => '/audio/'.$name,
                ];
            })
            ->toArray();
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
