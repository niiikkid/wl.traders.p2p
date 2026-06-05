<?php

namespace App\DTO\User;

use App\DTO\BaseDTO;

readonly class UserUpdateDTO extends BaseDTO
{
    public function __construct(
        public string $login,
        public int $role_id,
        public ?string $telegram_username = null,
        public ?bool $banned = null,
        public ?string $ban_reason = null,
        public bool $stop_traffic = false,
        public bool $can_work_without_device = false,
        public bool $is_vip = false,
        public bool $payouts_enabled = true,
        public bool $trader_economy_enabled = false,
        public bool $priority_payout_access_enabled = false,
        public bool $payout_hold_enabled = true,
        public ?int $payout_hold_minutes = null,
        public ?int $payout_active_payouts_limit = null,
        public ?float $referral_commission_percentage = null,
        public ?float $team_leader_split_from_service_percent = null,
        public ?float $payout_referral_commission_percentage = null,
        public ?float $payout_team_leader_split_from_service_percent = null,
        public ?int $reserve_balance_limit = null,
        public ?int $team_leader_id = null,
        public ?int $agent_id = null,
        public float $agent_commission_percentage = 0.20,
        public bool $team_leader_extended_access_enabled = false,
        public bool $team_leader_flexible_trader_commission_enabled = false,
        public ?float $team_leader_flexible_trader_commission_min = null,
        public ?float $team_leader_flexible_trader_commission_max = null,
        public bool $support_can_view_deposits = false,
        public bool $support_can_edit_order_amount = false,
        public bool $support_can_use_manual_control_acq = false,
        public ?string $team_leader_insurance_mode = null,
        public ?int $team_leader_trader_limit = null,
        public ?int $team_leader_reserve_balance_limit = null,
        public ?int $team_leader_reserve_stop_threshold = null,
    ) {}

    public static function makeFromRequest(array $data): static
    {
        return new static(
            login: strtolower($data['login']),
            telegram_username: self::normalizeTelegramUsername($data['telegram_username'] ?? null),
            banned: isset($data['banned']) ? (bool) $data['banned'] : null,
            ban_reason: self::normalizeBanReason($data['ban_reason'] ?? null),
            stop_traffic: (bool) ($data['stop_traffic'] ?? false),
            can_work_without_device: (bool) ($data['can_work_without_device'] ?? false),
            is_vip: (bool) ($data['is_vip'] ?? false),
            payouts_enabled: (bool) ($data['payouts_enabled'] ?? true),
            trader_economy_enabled: (bool) ($data['trader_economy_enabled'] ?? false),
            priority_payout_access_enabled: (bool) ($data['priority_payout_access_enabled'] ?? false),
            payout_hold_enabled: (bool) ($data['payout_hold_enabled'] ?? true),
            payout_hold_minutes: isset($data['payout_hold_minutes']) ? (int) $data['payout_hold_minutes'] : null,
            payout_active_payouts_limit: isset($data['payout_active_payouts_limit']) ? (int) $data['payout_active_payouts_limit'] : null,
            referral_commission_percentage: isset($data['referral_commission_percentage'])
                ? (float) $data['referral_commission_percentage']
                : null,
            team_leader_split_from_service_percent: isset($data['team_leader_split_from_service_percent'])
                ? (float) $data['team_leader_split_from_service_percent']
                : null,
            payout_referral_commission_percentage: isset($data['payout_referral_commission_percentage'])
                ? (float) $data['payout_referral_commission_percentage']
                : null,
            payout_team_leader_split_from_service_percent: isset($data['payout_team_leader_split_from_service_percent'])
                ? (float) $data['payout_team_leader_split_from_service_percent']
                : null,
            reserve_balance_limit: isset($data['reserve_balance_limit']) ? (int) $data['reserve_balance_limit'] : null,
            role_id: (int) $data['role_id'],
            team_leader_id: $data['team_leader_id'] ?? null,
            agent_id: $data['agent_id'] ?? null,
            agent_commission_percentage: isset($data['agent_commission_percentage'])
                ? (float) $data['agent_commission_percentage']
                : 0.20,
            team_leader_extended_access_enabled: (bool) ($data['team_leader_extended_access_enabled'] ?? false),
            team_leader_flexible_trader_commission_enabled: (bool) ($data['team_leader_flexible_trader_commission_enabled'] ?? false),
            team_leader_flexible_trader_commission_min: isset($data['team_leader_flexible_trader_commission_min'])
                ? (float) $data['team_leader_flexible_trader_commission_min']
                : null,
            team_leader_flexible_trader_commission_max: isset($data['team_leader_flexible_trader_commission_max'])
                ? (float) $data['team_leader_flexible_trader_commission_max']
                : null,
            support_can_view_deposits: (bool) ($data['support_can_view_deposits'] ?? false),
            support_can_edit_order_amount: (bool) ($data['support_can_edit_order_amount'] ?? false),
            support_can_use_manual_control_acq: (bool) ($data['support_can_use_manual_control_acq'] ?? false),
            team_leader_insurance_mode: $data['team_leader_insurance_mode'] ?? null,
            team_leader_trader_limit: isset($data['team_leader_trader_limit']) ? (int) $data['team_leader_trader_limit'] : null,
            team_leader_reserve_balance_limit: isset($data['team_leader_reserve_balance_limit']) ? (int) $data['team_leader_reserve_balance_limit'] : null,
            team_leader_reserve_stop_threshold: isset($data['team_leader_reserve_stop_threshold']) ? (int) $data['team_leader_reserve_stop_threshold'] : null,
        );
    }

    private static function normalizeBanReason(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function normalizeTelegramUsername(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return ltrim($value, '@');
    }
}
