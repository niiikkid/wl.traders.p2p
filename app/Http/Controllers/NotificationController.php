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
use App\Services\Money\Currency;
use Inertia\Inertia;
use Illuminate\Support\Facades\Gate;

class NotificationController extends Controller
{
    protected function buildIndexProps(NotificationFilterRequest $request): array
    {
        $user = $request->user();
        $filters = $request->filters();

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

        return [
            'notifications' => $notifications,
            'rules' => $rules,
            'filters' => $filters,
            'filtersVariants' => $filtersVariants,
            'telegramAccount' => $telegramAccount,
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
}
