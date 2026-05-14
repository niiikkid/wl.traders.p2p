<?php

namespace App\Http\Controllers;

use App\Enums\NotificationEvent;
use App\Enums\NotificationMessageScope;
use App\Http\Requests\NotificationRuleRequest;
use App\Models\NotificationRule;
use App\Services\Money\Currency;
use Illuminate\Support\Facades\Gate;

class NotificationRuleController extends Controller
{
    public function store(NotificationRuleRequest $request)
    {
        $user = $request->user();
        $event = NotificationEvent::from($request->validated('event'));

        if (! $event->isAllowedForUser($user)) {
            return back()->with('error', 'Этот тип уведомления недоступен для вашей роли.');
        }

        $usesAmountFilters = ! in_array($event, [
            NotificationEvent::WITHDRAWAL_REQUESTED,
            NotificationEvent::MESSAGE_RECEIVED,
        ], true);
        $isTrustBalanceLow = $event === NotificationEvent::TRUST_BALANCE_LOW;

        NotificationRule::create([
            'user_id' => $user->id,
            'event' => $event,
            'message_scope' => $event === NotificationEvent::MESSAGE_RECEIVED
                ? NotificationMessageScope::ALL
                : null,
            'currency' => ! $usesAmountFilters
                ? null
                : ($isTrustBalanceLow ? Currency::USDT()->getCode() : $request->validated('currency')),
            'statuses' => $request->validated('statuses'),
            'min_amount_minor' => $usesAmountFilters ? $request->minAmountMinor() : null,
            'enabled' => $request->validated('enabled', true),
        ]);

        return back();
    }

    public function update(NotificationRuleRequest $request, NotificationRule $notificationRule)
    {
        Gate::authorize('access-to-self', $notificationRule->user);

        $eventValue = $request->validated('event', $notificationRule->event->value);
        $event = NotificationEvent::from($eventValue);

        if (! $event->isAllowedForUser($notificationRule->user)) {
            return back()->with('error', 'Этот тип уведомления недоступен для вашей роли.');
        }

        $usesAmountFilters = ! in_array($event, [
            NotificationEvent::WITHDRAWAL_REQUESTED,
            NotificationEvent::MESSAGE_RECEIVED,
        ], true);
        $isTrustBalanceLow = $event === NotificationEvent::TRUST_BALANCE_LOW;

        $notificationRule->update([
            'event' => $event,
            'message_scope' => $event === NotificationEvent::MESSAGE_RECEIVED
                ? NotificationMessageScope::ALL
                : null,
            'currency' => ! $usesAmountFilters
                ? null
                : ($isTrustBalanceLow
                    ? Currency::USDT()->getCode()
                    : $request->validated('currency', $notificationRule->currency?->getCode())),
            'statuses' => $request->validated('statuses', $notificationRule->statuses),
            'min_amount_minor' => $usesAmountFilters
                ? ($request->has('min_amount') ? $request->minAmountMinor() : $notificationRule->min_amount_minor)
                : null,
            'enabled' => $request->validated('enabled', $notificationRule->enabled),
        ]);

        return back();
    }

    public function destroy(NotificationRule $notificationRule)
    {
        Gate::authorize('access-to-self', $notificationRule->user);

        NotificationRule::query()
            ->whereKey($notificationRule->id)
            ->delete();

        return back();
    }
}
