<?php

namespace App\Http\Controllers;

use App\Enums\DetailType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentDemoController extends Controller
{
    private const DEFAULT_PRESET = 'pending';

    private const STAGE_PRESETS = [
        'select_gateway' => [
            'gateway_selected' => false,
            'order_status' => 'pending',
            'has_dispute' => false,
            'dispute_status' => null,
        ],
        'payment' => [
            'gateway_selected' => true,
            'order_status' => 'pending',
            'has_dispute' => false,
            'dispute_status' => null,
        ],
        'success' => [
            'gateway_selected' => true,
            'order_status' => 'success',
            'has_dispute' => false,
            'dispute_status' => null,
        ],
        'fail' => [
            'gateway_selected' => true,
            'order_status' => 'fail',
            'has_dispute' => false,
            'dispute_status' => null,
        ],
        'dispute_review' => [
            'gateway_selected' => true,
            'order_status' => 'fail',
            'has_dispute' => true,
            'dispute_status' => 'pending',
        ],
        'dispute_canceled' => [
            'gateway_selected' => true,
            'order_status' => 'fail',
            'has_dispute' => true,
            'dispute_status' => 'canceled',
        ],
    ];

    private const DEMO_PRESETS = [
        'manual_selection' => [
            'name' => 'Ручной выбор',
            'description' => 'Экран выбора способа оплаты',
            'group' => 'status',
            'query' => [
                'stage' => 'select_gateway',
                'detail_type' => DetailType::CARD->value,
                'selected_gateway' => 'sber',
                'manually' => 1,
                'expires_in' => 20,
            ],
        ],
        'pending' => [
            'name' => 'Ожидает оплату',
            'description' => 'Стандартный экран оплаты (pending)',
            'group' => 'status',
            'query' => [
                'stage' => 'payment',
                'detail_type' => DetailType::CARD->value,
                'selected_gateway' => 'sber',
                'manually' => 0,
                'expires_in' => 20,
            ],
        ],
        'success' => [
            'name' => 'Успешно',
            'description' => 'Оплата завершена успешно',
            'group' => 'status',
            'query' => [
                'stage' => 'success',
                'detail_type' => DetailType::CARD->value,
                'selected_gateway' => 'sber',
                'manually' => 0,
                'expires_in' => 20,
            ],
        ],
        'fail' => [
            'name' => 'Отменено',
            'description' => 'Оплата завершена с ошибкой',
            'group' => 'status',
            'query' => [
                'stage' => 'fail',
                'detail_type' => DetailType::CARD->value,
                'selected_gateway' => 'sber',
                'manually' => 0,
                'expires_in' => 20,
            ],
        ],
        'dispute_review' => [
            'name' => 'Спор: на рассмотрении',
            'description' => 'Статус dispute pending',
            'group' => 'status',
            'query' => [
                'stage' => 'dispute_review',
                'detail_type' => DetailType::CARD->value,
                'selected_gateway' => 'sber',
                'manually' => 0,
                'expires_in' => 20,
            ],
        ],
        'dispute_canceled' => [
            'name' => 'Спор: отклонен',
            'description' => 'Статус dispute canceled',
            'group' => 'status',
            'query' => [
                'stage' => 'dispute_canceled',
                'detail_type' => DetailType::CARD->value,
                'selected_gateway' => 'sber',
                'manually' => 0,
                'expires_in' => 20,
            ],
        ],
        'details_card' => [
            'name' => 'Карта',
            'description' => 'Вид блока реквизитов card',
            'group' => 'details',
            'query' => [
                'stage' => 'payment',
                'detail_type' => DetailType::CARD->value,
                'selected_gateway' => 'sber',
                'manually' => 0,
                'expires_in' => 20,
            ],
        ],
        'details_phone' => [
            'name' => 'Телефон',
            'description' => 'Вид блока реквизитов phone',
            'group' => 'details',
            'query' => [
                'stage' => 'payment',
                'detail_type' => DetailType::PHONE->value,
                'selected_gateway' => 'sber',
                'manually' => 0,
                'expires_in' => 20,
            ],
        ],
        'details_mobile' => [
            'name' => 'Mobile commerce',
            'description' => 'Вид блока реквизитов mobile_commerce',
            'group' => 'details',
            'query' => [
                'stage' => 'payment',
                'detail_type' => DetailType::MOBILE_COMMERCE->value,
                'selected_gateway' => 'sber',
                'manually' => 0,
                'expires_in' => 20,
            ],
        ],
        'details_account' => [
            'name' => 'Счет',
            'description' => 'Вид блока реквизитов account_number',
            'group' => 'details',
            'query' => [
                'stage' => 'payment',
                'detail_type' => DetailType::ACCOUNT_NUMBER->value,
                'selected_gateway' => 'sber',
                'manually' => 0,
                'expires_in' => 20,
            ],
        ],
        'details_iban' => [
            'name' => 'IBAN UAH',
            'description' => 'Вид блока реквизитов iban_uah',
            'group' => 'details',
            'query' => [
                'stage' => 'payment',
                'detail_type' => DetailType::IBAN_UAH->value,
                'selected_gateway' => 'sber',
                'manually' => 0,
                'expires_in' => 20,
            ],
        ],
        'details_nspk' => [
            'name' => 'NSPK',
            'description' => 'Вид блока реквизитов nspk',
            'group' => 'details',
            'query' => [
                'stage' => 'payment',
                'detail_type' => DetailType::NSPK->value,
                'selected_gateway' => 'sber',
                'manually' => 0,
                'expires_in' => 20,
            ],
        ],
        'details_ecom' => [
            'name' => 'E-COM',
            'description' => 'Вид блока реквизитов e-com',
            'group' => 'details',
            'query' => [
                'stage' => 'payment',
                'detail_type' => DetailType::E_COM->value,
                'selected_gateway' => 'sber',
                'manually' => 0,
                'expires_in' => 20,
            ],
        ],
    ];

    public function show(Request $request): Response
    {
        $gatewayOptions = $this->gatewayOptions();

        $presetKey = $this->resolvePresetKey((string) $request->query('preset', self::DEFAULT_PRESET));
        $presetQuery = self::DEMO_PRESETS[$presetKey]['query'];

        $stage = (string) $request->query('stage', $presetQuery['stage']);
        $stage = array_key_exists($stage, self::STAGE_PRESETS) ? $stage : 'payment';

        $detailType = $this->resolveDetailType((string) $request->query('detail_type', $presetQuery['detail_type']));
        $selectedGateway = $this->resolveGateway((string) $request->query('selected_gateway', $presetQuery['selected_gateway']), $gatewayOptions);
        $manualMode = $this->toBool($request->query('manually', $presetQuery['manually']));
        $expiresInMinutes = max(1, min(120, (int) $request->query('expires_in', $presetQuery['expires_in'])));

        $preset = self::STAGE_PRESETS[$stage];
        $detailPayload = $this->detailPayload($detailType);
        $query = [
            'preset' => $presetKey,
            'stage' => $stage,
            'detail_type' => $detailType,
            'selected_gateway' => $selectedGateway['id'],
            'manually' => $manualMode ? 1 : 0,
            'expires_in' => $expiresInMinutes,
        ];

        $amount = 12450;
        $now = now();
        $expiresAt = $now->copy()->addMinutes($expiresInMinutes);

        $data = [
            'order_status' => $preset['order_status'],
            'uuid' => 'demo-order-0001',
            'name' => 'Демо-магазин',
            'amount' => $amount,
            'amount_formated' => number_format($amount, 0, ',', ''),
            'currency_symbol' => '₽',
            'support_link' => 'https://t.me/support',
            'detail' => $preset['gateway_selected'] ? $detailPayload['detail'] : null,
            'detail_type' => $preset['gateway_selected'] ? $detailType : null,
            'initials' => $preset['gateway_selected'] ? $detailPayload['initials'] : null,
            'additional_info' => $preset['gateway_selected'] ? $detailPayload['additional_info'] : null,
            'payment_gateway' => $selectedGateway['name'],
            'success_url' => route('payment.demo.show', array_merge($query, ['stage' => 'success'])),
            'fail_url' => route('payment.demo.show', array_merge($query, ['stage' => 'fail'])),
            'created_at' => $now->copy()->subMinutes(3)->toDateTimeString(),
            'expires_at' => $expiresAt->toDateTimeString(),
            'now' => $now->toDateTimeString(),
            'has_dispute' => (int) $preset['has_dispute'],
            'dispute_status' => $preset['dispute_status'],
            'dispute_cancel_reason' => 'Платеж не подтвержден по предоставленным данным.',
            'manually' => $manualMode,
            'gateway_selected' => $preset['gateway_selected'],
            'available_gateways' => array_map(static function (array $gateway): array {
                return [
                    'id' => $gateway['id'],
                    'name' => $gateway['name'],
                    'logo_path' => $gateway['logo_path'],
                ];
            }, $gatewayOptions),
            'is_demo' => true,
            'store_dispute_url' => route('payment.demo.dispute.store', $query),
            'store_payment_detail_url_template' => route('payment.demo.payment-detail.store', array_merge($query, ['paymentGateway' => '__GATEWAY__'])),
        ];

        $demo = [
            'query' => $query,
            'options' => [
                'stages' => [
                    ['value' => 'select_gateway', 'name' => 'Выбор способа оплаты'],
                    ['value' => 'payment', 'name' => 'Оплата (pending)'],
                    ['value' => 'success', 'name' => 'Успешная оплата (success)'],
                    ['value' => 'fail', 'name' => 'Неуспешная оплата (fail)'],
                    ['value' => 'dispute_review', 'name' => 'Чек на рассмотрении (dispute pending)'],
                    ['value' => 'dispute_canceled', 'name' => 'Отклоненная претензия (dispute canceled)'],
                ],
                'detail_types' => array_map(static function (DetailType $type): array {
                    return [
                        'value' => $type->value,
                        'name' => $type->value,
                    ];
                }, DetailType::cases()),
                'gateways' => array_map(static function (array $gateway): array {
                    return [
                        'value' => $gateway['id'],
                        'name' => $gateway['name'],
                    ];
                }, $gatewayOptions),
                'presets' => array_map(static function (string $key, array $preset): array {
                    return [
                        'value' => $key,
                        'name' => $preset['name'],
                        'description' => $preset['description'],
                        'group' => $preset['group'],
                        'query' => array_merge(['preset' => $key], $preset['query']),
                    ];
                }, array_keys(self::DEMO_PRESETS), array_values(self::DEMO_PRESETS)),
            ],
        ];

        return Inertia::render('PaymentLink/Index', compact('data', 'demo'));
    }

    public function storeDispute(Request $request): RedirectResponse
    {
        $query = $this->resolveQuery($request);
        $query['stage'] = 'dispute_review';

        return redirect()->route('payment.demo.show', $query);
    }

    public function storePaymentDetail(Request $request, string $paymentGateway): RedirectResponse
    {
        $query = $this->resolveQuery($request);
        $query['selected_gateway'] = $paymentGateway;
        $query['stage'] = 'payment';

        return redirect()->route('payment.demo.show', $query);
    }

    private function resolveQuery(Request $request): array
    {
        $presetKey = $this->resolvePresetKey((string) $request->query('preset', self::DEFAULT_PRESET));
        $presetQuery = self::DEMO_PRESETS[$presetKey]['query'];

        $stage = (string) $request->query('stage', $presetQuery['stage']);
        $stage = array_key_exists($stage, self::STAGE_PRESETS) ? $stage : 'payment';

        return [
            'preset' => $presetKey,
            'stage' => $stage,
            'detail_type' => $this->resolveDetailType((string) $request->query('detail_type', $presetQuery['detail_type'])),
            'selected_gateway' => (string) $request->query('selected_gateway', $presetQuery['selected_gateway']),
            'manually' => $this->toBool($request->query('manually', $presetQuery['manually'])) ? 1 : 0,
            'expires_in' => max(1, min(120, (int) $request->query('expires_in', $presetQuery['expires_in']))),
        ];
    }

    private function resolvePresetKey(string $presetKey): string
    {
        if (array_key_exists($presetKey, self::DEMO_PRESETS)) {
            return $presetKey;
        }

        return self::DEFAULT_PRESET;
    }

    private function resolveDetailType(string $detailType): string
    {
        foreach (DetailType::cases() as $case) {
            if ($case->value === $detailType) {
                return $detailType;
            }
        }

        return DetailType::CARD->value;
    }

    private function resolveGateway(string $gatewayId, array $gatewayOptions): array
    {
        foreach ($gatewayOptions as $gatewayOption) {
            if ($gatewayOption['id'] === $gatewayId) {
                return $gatewayOption;
            }
        }

        return $gatewayOptions[0];
    }

    private function toBool(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on'], true);
    }

    /**
     * @return array<int, array{id: string, name: string, logo_path: null|string}>
     */
    private function gatewayOptions(): array
    {
        return [
            ['id' => 'sber', 'name' => 'Сбербанк', 'logo_path' => null],
            ['id' => 'tbank', 'name' => 'Т-Банк', 'logo_path' => null],
            ['id' => 'mono', 'name' => 'MonoBank', 'logo_path' => null],
        ];
    }

    /**
     * @return array{detail: string, initials: string, additional_info: null|string}
     */
    private function detailPayload(string $detailType): array
    {
        return match ($detailType) {
            DetailType::PHONE->value => [
                'detail' => '79990000000',
                'initials' => 'Иванов И.И.',
                'additional_info' => null,
            ],
            DetailType::MOBILE_COMMERCE->value => [
                'detail' => '380671234567',
                'initials' => 'Petrenko P.P.',
                'additional_info' => null,
            ],
            DetailType::ACCOUNT_NUMBER->value => [
                'detail' => '40817810099910004312',
                'initials' => 'ООО "Демо"',
                'additional_info' => null,
            ],
            DetailType::IBAN_UAH->value => [
                'detail' => 'UA123456789012345678901234567',
                'initials' => 'ФОП Демонстрацiя',
                'additional_info' => '1234567890',
            ],
            DetailType::NSPK->value => [
                'detail' => 'https://example.com/demo/sbp',
                'initials' => 'СБП',
                'additional_info' => null,
            ],
            DetailType::E_COM->value => [
                'detail' => 'https://example.com/demo/e-com',
                'initials' => 'E-COM',
                'additional_info' => null,
            ],
            default => [
                'detail' => '4276380012345678',
                'initials' => 'Иванов И.И.',
                'additional_info' => null,
            ],
        };
    }
}
