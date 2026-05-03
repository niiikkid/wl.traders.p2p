<?php

namespace App\Http\Middleware;

use App\Enums\CascadeDealStatus;
use App\Enums\DisputeStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\MarketEnum;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Http\Resources\UserResource;
use App\Http\Resources\WalletResource;
use App\Models\CascadeDeal;
use App\Models\Dispute;
use App\Models\Invoice;
use App\Models\NewsPost;
use App\Models\Order;
use App\Models\PaymentDetail;
use App\Models\Payout\Payout;
use App\Models\User;
use App\Models\UserMeta;
use App\Services\Money\Currency;
use App\Services\PaymentDetail\PaymentDetailEnabledPeriodService;
use App\Services\UserOnline\UserOnlinePeriodRecorder;
use App\Services\Wallet\Values\WalletStatsValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $authUser */
        $authUser = $request->user();

        // Save latest frontend ping time for authenticated user (Inertia request)
        if ($authUser instanceof User) {
            $user = $authUser;
            $userId = $user->id;
            $now = now();
            cache()->put("user-online-at-{$userId}", $now->toISOString());
            app(UserOnlinePeriodRecorder::class)->touch($userId, $now);

            if ($request->routeIs([
                'news.index',
                'admin.news.index',
                'support.news.index',
                'analyst.news.index',
                'leader.news.index',
            ])) {
                $user->meta()->updateOrCreate(
                    ['user_id' => $userId],
                    ['news_last_read_at' => $now]
                );

                cache()->forget("news_unread_{$userId}");
            }

            if ($user->hasRole('Trader')) {
                app(PaymentDetailEnabledPeriodService::class)->syncForUser($user, $now);
            }
        }

        $rates = cache()->remember('currency-rates', 60, function () {
            return Currency::getAll()
                ->transform(function (Currency $currency) {
                    $isUah = $currency->getCode() === 'uah';
                    if ($isUah) {
                        $buy = services()->market()->getBuyPrice($currency, MarketEnum::MANUAL, false);
                        $sell = services()->market()->getSellPrice($currency, MarketEnum::MANUAL, false);
                    } else {
                        $buy = services()->market()->getBuyPrice($currency);
                        $sell = services()->market()->getSellPrice($currency);
                    }

                    return [
                        'code' => $currency->getCode(),
                        'buy_price' => $buy->toBeauty(),
                        'sell_price' => $sell->toBeauty(),
                    ];
                })
                ->sortByDesc(function ($currency) {
                    // Desired order: uah, rub, kzt, usd, eur
                    $order = ['uah', 'rub', 'kzt', 'usd', 'eur'];
                    $idx = array_search($currency['code'], $order);

                    return $idx !== false ? $idx : count($order);
                })

                ->reverse()
                ->values()
                ->toArray();
        });

        $orderQuery = Order::query()
            ->where('status', OrderStatus::PENDING);
        $disputeQuery = Dispute::query()
            ->where('status', DisputeStatus::PENDING);

        $userId = Auth::id();
        $userRole = isRouteFor('Merchant') ? 'merchant' : (isRouteFor('Trader') ? 'trader' : (isRouteFor('Super Admin') ? 'admin' : 'guest'));

        $pendingOrdersCount = cache()->remember("pending_orders_{$userRole}_{$userId}", 15, function () use ($orderQuery, $userRole, $userId) {
            if ($userRole === 'merchant') {
                return 0;
            } elseif ($userRole === 'trader') {
                return $orderQuery->clone()->whereRelation('paymentDetail', 'user_id', $userId)->count();
            } elseif ($userRole === 'admin') {
                return $orderQuery->clone()->count();
            } else {
                return 0;
            }
        });

        $pendingDisputesCount = cache()->remember("pending_disputes_{$userRole}_{$userId}", 15, function () use ($disputeQuery, $userRole, $userId) {
            if ($userRole === 'merchant') {
                return 0;
            } elseif ($userRole === 'trader') {
                return $disputeQuery->clone()->whereRelation('order.paymentDetail', 'user_id', $userId)->count();
            } elseif ($userRole === 'admin') {
                return $disputeQuery->clone()->count();
            } else {
                return 0;
            }
        });

        $payoutsActiveCount = cache()->remember("payouts_active_{$userRole}_{$userId}", 15, function () use ($userRole, $userId) {
            if ($userRole === 'trader') {
                return Payout::query()
                    ->where(function ($query) use ($userId) {
                        $query->where('status', PayoutStatus::OPEN->value)
                            ->orWhere(function ($query) use ($userId) {
                                $query->where('trader_id', $userId)
                                    ->whereIn('status', [PayoutStatus::TAKEN->value, PayoutStatus::SENT->value]);
                            });
                    })
                    ->count();
            }

            if ($userRole === 'admin') {
                return Payout::query()
                    ->whereIn('status', [
                        PayoutStatus::OPEN->value,
                        PayoutStatus::TAKEN->value,
                        PayoutStatus::SENT->value,
                    ])
                    ->count();
            }

            return 0;
        });

        $cascadeActiveCount = 0;
        if ($userRole === 'admin') {
            $cascadeActiveCount = (int) cache()->remember('cascade_active_admin', 15, function () {
                return CascadeDeal::query()
                    ->whereIn('status', [
                        CascadeDealStatus::PROVISIONING->value,
                        CascadeDealStatus::PENDING->value,
                    ])
                    ->count();
            });
        }

        $onlineUsers = 0;
        $activeDetails = 0;
        $pendingWithdrawals = 0;
        $newsUnreadCount = 0;

        if ($authUser instanceof User) {
            $userId = $authUser->id;

            if (isRouteFor('Super Admin')) {
                $onlineUsers = cache()->remember('online_users', 15, function () {
                    return User::query()
                        ->where('is_online', true)
                        ->count();
                });

                $pendingWithdrawals = cache()->remember('pending_withdrawals', 15, function () {
                    return Invoice::query()
                        ->where('status', InvoiceStatus::PENDING)
                        ->where('type', InvoiceType::WITHDRAWAL)
                        ->count();
                });
            } elseif (isRouteFor('Support') || isRouteFor('Analyst')) {
                $onlineUsers = cache()->remember('online_users_support', 15, function () {
                    return User::query()
                        ->where('is_online', true)
                        ->count();
                });

                $pendingOrdersCount = cache()->remember('pending_orders_support', 15, function () use ($orderQuery) {
                    return $orderQuery->clone()->count();
                });

                $pendingDisputesCount = cache()->remember('pending_disputes_support', 15, function () use ($disputeQuery) {
                    return $disputeQuery->clone()->count();
                });

                $payoutsActiveCount = cache()->remember('payouts_active_support', 15, function () {
                    return Payout::query()
                        ->whereIn('status', [
                            PayoutStatus::OPEN->value,
                            PayoutStatus::TAKEN->value,
                            PayoutStatus::SENT->value,
                        ])
                        ->count();
                });
            }

            if (isRouteFor('Trader')) {
                $activeDetails = cache()->remember("active_details_trader_{$userId}", 15, function () use ($userId) {
                    return PaymentDetail::query()
                        ->whereNull('archived_at')
                        ->where('is_active', true)
                        ->whereRelation('user', 'is_online', true)
                        ->whereRelation('user', 'id', $userId)
                        ->whereRelation('user', 'stop_traffic', false)
                        ->count();
                });
            } elseif (isRouteFor('Super Admin')) {
                $activeDetails = cache()->remember('active_details_admin', 15, function () {
                    return PaymentDetail::query()
                        ->whereNull('archived_at')
                        ->where('is_active', true)
                        ->whereRelation('user', 'is_online', true)
                        ->whereRelation('user', 'stop_traffic', false)
                        ->count();
                });
            }

            $newsUnreadCount = cache()->remember("news_unread_{$userId}", 15, function () use ($userId) {
                $lastReadAt = UserMeta::query()
                    ->where('user_id', $userId)
                    ->value('news_last_read_at');
                $user = User::query()->find($userId);
                if (! $user) {
                    return 0;
                }
                $roleNames = $user->roles()->pluck('name')->values()->all();

                $query = NewsPost::query()
                    ->when($lastReadAt, function ($query) use ($lastReadAt) {
                        $query->where('created_at', '>', $lastReadAt);
                    });

                if (! $user->hasRole('Super Admin')) {
                    $query->visibleForRoles($roleNames);
                }

                return $query->count();
            });
        }

        $menu = [
            'pendingOrdersCount' => (int) $pendingOrdersCount,
            'pendingDisputesCount' => (int) $pendingDisputesCount,
            'onlineUsers' => (int) $onlineUsers,
            'activeDetails' => (int) $activeDetails,
            'pendingWithdrawals' => (int) $pendingWithdrawals,
            'newsUnreadCount' => (int) $newsUnreadCount,
            'payoutsActiveCount' => (int) $payoutsActiveCount,
            'cascadeActiveCount' => (int) $cascadeActiveCount,
        ];

        $sharedWalletStats = null;
        if ($authUser instanceof User && (isRouteFor('Trader') || isRouteFor('Merchant'))) {
            /** @var WalletStatsValue $walletStatsValue */
            $walletStatsValue = services()->wallet()->getWalletStats($authUser->wallet);
            $sharedWalletStats = $walletStatsValue->toArray();
        }

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'slogan' => services()->settings()->getAppSlogan(),
            ],
            'auth' => [
                'user' => fn () => $request->user()
                    ? UserResource::make($request->user()->loadMissing('roles', 'wallet'))->resolve()
                    : null,
                'role' => $request->user()?->roles()?->first(),
                'is_admin' => $request->user()?->hasRole('Super Admin'),
                'is_trader' => $request->user()?->hasRole('Trader'),
                'is_impersonated' => $request->user()?->isImpersonated(),
            ],
            'ziggy' => fn () => [
                // ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'data' => [
                'rates' => fn () => $rates,
                'wallet' => fn () => $request->user() ? WalletResource::make($request->user()->wallet)->resolve() : null,
                'wallet_stats' => fn () => $sharedWalletStats,
                'hasPendingDisputes' => fn () => $request->user()?->hasRole('Trader') ? $menu['pendingDisputesCount'] > 0 : 0,
            ],
            'menu' => $menu,
            'notificationsSound' => $authUser instanceof User && $authUser->hasRole('Trader') ? [
                'order_assigned' => [
                    'enabled' => $authUser->meta?->notification_sound_order_enabled ?? true,
                    'track' => $authUser->meta?->notification_sound_order_track ?? 'radwimps.mp3',
                ],
                'dispute_opened' => [
                    'enabled' => $authUser->meta?->notification_sound_dispute_enabled ?? true,
                    'track' => $authUser->meta?->notification_sound_dispute_track ?? 'radwimps.mp3',
                ],
                'message_received' => [
                    'enabled' => $authUser->meta?->notification_sound_message_enabled ?? true,
                    'track' => $authUser->meta?->notification_sound_message_track ?? 'radwimps.mp3',
                ],
            ] : null,
        ];
    }
}
