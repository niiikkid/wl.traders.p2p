# Team Leader Shared Insurance — Phase 2 (Admin Validation And Configuration)

> Source: Implementation session (Cursor agent), repository `p2p.cti`
> Collected: 2026-05-26
> Published: 2026-05-26

## Summary

Phase 2 of Team Leader Shared Insurance Mode: admin validation, persistence, and UI for Team Leader insurance configuration and trader connection guards. Wallet top-up, order guards, and split debit remain future phases.

## Backend

### TeamLeaderInsuranceService

`app/Services/User/TeamLeaderInsuranceService.php`

- `teamLeaderConfigurationRules(bool $isTeamLeaderRole)` — conditional Laravel rules for mode 2 fields
- `validateTeamLeaderConfiguration()` — block mode change when connected traders exist; block 2→1 when TL `reserve_balance` > 0
- `validateTraderTeamLeaderAssignment()` — TL role check, trader limit for mode 2, zero wallet balances before connect
- `validateTraderReserveLimitChange()` — reject `reserve_balance_limit` edits for traders on shared reserve
- `resolveTeamLeaderConfigurationForPersist()` — null mode-2-only fields when mode 1
- `shouldIgnoreTraderReserveLimit()` — used by `UserService` on update

### Form Requests

- `app/Http/Requests/Admin/User/StoreRequest.php` — insurance rules + trader `team_leader_id` guards on create
- `app/Http/Requests/Admin/User/UpdateRequest.php` — same + mode-change checks for existing Team Leader; reserve limit guard for traders

### DTOs and UserService

- `UserCreateDTO` / `UserUpdateDTO` — insurance mode fields
- `UserService` — persists insurance config only when role is `Team Leader`; ignores trader `reserve_balance_limit` when `usesTeamLeaderSharedReserve()`
- `AppServiceProvider` — `UserServiceContract` bound to `UserService::class` for constructor DI

## Frontend

- `resources/js/Modals/User/Partials/TeamLeaderInsuranceFields.vue` — mode selector, mode-2 limits, explanatory alerts
- `UserCreateModal.vue` — Team Leader insurance block on create
- `UserEditModal.vue` — insurance block; disable mode when `connected_trader_count > 0`; hide trader reserve + info alert when `uses_team_leader_shared_reserve`

## Validation messages (Russian)

- Mode change blocked: connected traders exist
- Mode 2→1 blocked: TL reserve non-zero
- Trader limit: `Лимит трейдеров для этого Team Leader исчерпан.`
- Zero balances required before connect to mode-2 TL
- Trader reserve edit blocked on shared reserve

## Not in Phase 2

- Wallet top-up paths (Phase 3)
- Order issuing guard (Phase 4)
- Split debit / refunds (Phase 5)
- Team Leader / trader finance pages beyond admin modals (Phase 6)
