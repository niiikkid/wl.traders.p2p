# Team Leader Shared Insurance — Phase 1 (Data And Domain Flags)

> Source: Implementation session in repository p2p.cti
> Collected: 2026-05-26
> Published: Unknown

## Scope

Phase 1 of the Team Leader Shared Insurance Mode feature: database columns, enum, User model helpers, and UserResource exposure. No admin validation, wallet logic, order guards, or UI yet.

## Shipped artifacts

### Enum

- `app/Enums/TeamLeaderInsuranceMode.php`
- Cases: `TraderReserve` (`trader_reserve`), `TeamLeaderReserve` (`team_leader_reserve`)
- Methods: `label()`, `usesSharedReserve()`
- Uses `App\Traits\Enumable`

### Migration

- `database/migrations/2026_05_25_211641_add_team_leader_insurance_mode_fields_to_users_table.php`
- Columns on `users`:
  - `team_leader_insurance_mode` — string(32), default `trader_reserve`
  - `team_leader_trader_limit` — unsignedInteger, nullable
  - `team_leader_reserve_balance_limit` — unsignedInteger, nullable
  - `team_leader_reserve_stop_threshold` — unsignedInteger, nullable
- Placement: after `team_leader_flexible_trader_commission_max`
- Existing Team Leaders receive mode 1 via column default

### User model

- Fillable + cast `team_leader_insurance_mode` → `TeamLeaderInsuranceMode`
- `usesTeamLeaderSharedReserve(): bool` — true when trader has `team_leader_id` and TL mode is `team_leader_reserve`
- `connectedTraderCount(): int` — `referrals()->role('Trader')->count()` (includes blocked/archived)
- `remainingTeamLeaderTraderSlots(): ?int` — `max(0, limit - count)` when limit set

### UserResource

Exposed fields:

- `team_leader_insurance_mode`, `team_leader_insurance_mode_label`
- `team_leader_trader_limit`, `team_leader_reserve_balance_limit`, `team_leader_reserve_stop_threshold`
- `connected_trader_count`, `remaining_team_leader_trader_slots` (when role Team Leader)
- `uses_team_leader_shared_reserve`
- Nested `team_leader` (when loaded): mode value, label, `uses_team_leader_shared_reserve`

## Not in scope (later phases)

- Form Request / DTO validation for mode changes
- `TeamLeaderInsuranceService`
- Wallet top-up paths, order debit, admin/trader UI
- Routes unchanged — no `optimize` / `ziggy:generate` required

## Verification (manual)

- Migration applied successfully
- Existing Team Leader reads `team_leader_insurance_mode` = `trader_reserve`
- `UserResource` includes new keys for Team Leader role
