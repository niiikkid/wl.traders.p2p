# Knowledge Base Index

## telegram

Telegram bot and chat automation knowledge for the project.

| Article | Summary | Updated |
|---------|---------|---------|
| [Telegram Chat Dispute Automation Plan](telegram/telegram-chat-dispute-automation-plan.md) | **Shipped:** Phases 1–6; Features 1–2 (reply + resolution); fail-only opening; live Telegram verification complete. | 2026-05-24 |
| [Telegram Dispute Reply and Resolution Notifications Specification](telegram/telegram-dispute-reply-and-resolution-notifications-spec.md) | **Shipped:** Features 1–2; order-status rejection replies; resolution notifications with statement document/text fallback; Phase 5 verified. | 2026-05-24 |
| [Telegram Chat Types and Trader Team Notifications Specification](telegram/telegram-chat-types-and-trader-team-notifications-spec.md) | **Phases 1–7 shipped:** schema, admin UI, webhook gates, async team dispute notifications + self-scheduling reminders. Next: Phase 8 manual verification. | 2026-06-06 |

## traffic-categories

Merchant traffic category knowledge for admin-managed category setup, trader opt-in controls, and backend traffic filtering.

| Article | Summary | Updated |
|---------|---------|---------|
| [Merchant Traffic Categories Architecture](traffic-categories/merchant-traffic-categories-architecture.md) | Not shipped; steps 1–9 done (admin + trader UI + backend filter). Next: copy polish and browser verification (10–11). | 2026-05-24 |

## payment-detail-schedules

Payment detail work schedule knowledge for trader-owned reusable server-time schedules and schedule-aware availability filtering.

| Article | Summary | Updated |
|---------|---------|---------|
| [Payment Detail Work Schedule Implementation Plan](payment-detail-schedules/payment-detail-work-schedule-implementation-plan.md) | **Shipped:** trader schedule CRUD/assignment/traffic/UI; admin + Team Leader read-only schedule on requisites; phases **0–9 complete**. | 2026-05-24 |

## trader-balance-transfers

Trader-to-trader working balance transfer knowledge for Team Leader scoped wallet movement.

| Article | Summary | Updated |
|---------|---------|---------|
| [Trader Balance Transfer Implementation Plan](trader-balance-transfers/trader-balance-transfer-implementation-plan.md) | **Shipped:** atomic `trust_balance` transfers within one Team Leader; API `wallet.trader-transfer.*` + Inertia/UI on `wallet.index` (steps 1–15). Tests pending (17). | 2026-05-26 |

## dispute-bank-statements

Dispute rejection evidence requirements for bank/card statement uploads in classic Order dispute flows.

| Article | Summary | Updated |
|---------|---------|---------|
| [Dispute Bank Statement Implementation Plan](dispute-bank-statements/dispute-bank-statement-implementation-plan.md) | **Shipped:** rejection with bank/card statement; reason codes; optional statement for `wrong_details`; phases 1–7; manual UI per role complete. | 2026-05-24 |

## team-leader-insurance-mode

Team Leader shared insurance reserve mode knowledge for trader reserve delegation, admin limits, and wallet debit behavior.

| Article | Summary | Updated |
|---------|---------|---------|
| [Team Leader Shared Insurance Mode Specification](team-leader-insurance-mode/team-leader-shared-insurance-mode-spec.md) | **Phases 1–6 shipped:** domain, admin, wallet, orders, finance UI polish. Next: manual verification (7). | 2026-05-26 |

## sms-automation

SMS/push automation ingress, filtering, and admin inspection outside the main `sms_logs` pipeline.

| Article | Summary | Updated |
|---------|---------|---------|
| [Shadow SMS Log Implementation Plan](sms-automation/shadow-sms-log-implementation-plan.md) | **Shipped:** `shadow_sms_logs` async logging, admin Автоматика page, global enable toggle, search, hard delete all; migration applied. | 2026-05-24 |

## user-devices

Trader automation device connect API and admin inspection of raw device snapshots.

| Article | Summary | Updated |
|---------|---------|---------|
| [Device Connect Snapshot Implementation Plan](user-devices/device-connect-snapshot-implementation-plan.md) | **Shipped:** optional `device_connect_snapshot` on device connect, admin lazy snapshot modal; migration `2026_05_22_212222_*`. | 2026-05-23 |

## reconciliation

Merchant/provider reconciliation knowledge for admin-side comparison of local payments, payouts, commissions, statuses, and balances against external provider records.

| Article | Summary | Updated |
|---------|---------|---------|
| [Merchant Reconciliation Service Specification](reconciliation/merchant-reconciliation-service-specification.md) | Detailed phased specification for an abstract reconciliation service, with SP24 API as the first provider strategy and future-provider extension points. | 2026-06-03 |

## feature-removal

Legacy feature removal plans for safely deleting deprecated roles, pages, API surfaces, settings, and data model branches.

| Article | Summary | Updated |
|---------|---------|---------|
| [Legacy Feature Removal Technical Specification](feature-removal/legacy-feature-removal-technical-spec.md) | Steps **1–18**, **19** (frontend), **20**, **22**, **23** (frontend, mass resend only), **24**, **25** (frontend) shipped; backlog **19 backend, 21, 23 backend, 25 backend, 26–27**. | 2026-06-16 |
