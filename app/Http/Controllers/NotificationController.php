<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\SmsLog;
use App\Services\Notification\NotificationSettingsPresenter;
use App\Services\UserOnline\UserOnlinePeriodRecorder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationSettingsPresenter $notificationSettingsPresenter
    ) {}

    protected function buildIndexProps(Request $request): array
    {
        return $this->notificationSettingsPresenter->present($request->user());
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

        abort_unless($user->hasRole('Trader'), 403);

        $now = now();
        cache()->put("user-online-at-{$user->id}", $now->toISOString());
        app(UserOnlinePeriodRecorder::class)->touch($user->id, $now);

        return response()->json([
            'latest_event_ids' => $this->resolveLatestEventIds($request),
        ]);
    }

    public function updateSoundSettings(Request $request)
    {
        $user = $request->user();

        abort_unless($user->hasRole('Trader'), 403);

        $audioTracks = $this->notificationSettingsPresenter->getAudioTracks();
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
                'notification_sound_order_track' => $this->notificationSettingsPresenter->resolveSoundTrack($settings['order_assigned']['track'] ?? null, $audioTracks),
                'notification_sound_dispute_enabled' => (bool) $settings['dispute_opened']['enabled'],
                'notification_sound_dispute_track' => $this->notificationSettingsPresenter->resolveSoundTrack($settings['dispute_opened']['track'] ?? null, $audioTracks),
                'notification_sound_message_enabled' => (bool) $settings['message_received']['enabled'],
                'notification_sound_message_track' => $this->notificationSettingsPresenter->resolveSoundTrack($settings['message_received']['track'] ?? null, $audioTracks),
            ]
        );

        return back();
    }

    protected function resolveLatestEventIds(Request $request): array
    {
        $user = $request->user();

        return [
            'order_assigned' => (int) (Order::query()->where('trader_id', $user->id)->max('id') ?? 0),
            'dispute_opened' => (int) (Dispute::query()->where('trader_id', $user->id)->max('id') ?? 0),
            'message_received' => (int) (SmsLog::query()
                ->where('user_id', $user->id)
                ->whereNotNull('parsing_result')
                ->whereRaw(
                    "LOWER(JSON_UNQUOTE(JSON_EXTRACT(parsing_result, '$.operation_type'))) IN ('in', 'out')"
                )
                ->max('id') ?? 0),
        ];
    }
}
