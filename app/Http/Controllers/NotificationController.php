<?php

namespace App\Http\Controllers;

use App\Enums\NotificationEvent;
use App\Http\Resources\NotificationRuleResource;
use App\Http\Resources\TelegramAccountResource;
use App\Models\NotificationRule;
use App\Services\Money\Currency;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    protected function buildIndexProps(Request $request): array
    {
        $user = $request->user();

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
            'filtersVariants' => [
                'event' => $events,
                'currency' => $currencies,
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
}
