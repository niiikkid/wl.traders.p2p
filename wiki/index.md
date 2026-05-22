# Knowledge Base Index

## telegram

Telegram bot and chat automation knowledge for the project.

| Article | Summary | Updated |
|---------|---------|---------|
| [Telegram Chat Dispute Automation Plan](telegram/telegram-chat-dispute-automation-plan.md) | Telegram chat dispute bot; Phases 1–6 complete; opening only for `fail` orders (replies for `success`/`pending`); Features 1–2 (reply + resolution notifications) implemented; live Telegram verification pending. | 2026-05-23 |
| [Telegram Dispute Reply and Resolution Notifications Specification](telegram/telegram-dispute-reply-and-resolution-notifications-spec.md) | Reply/resolution spec; Features 1–2 implemented; opening rejection replies for non-`fail` orders; `wrong_details` text-only rejection path; Phase 5 manual checks pending. | 2026-05-23 |

## traffic-categories

Merchant traffic category knowledge for admin-managed category setup, trader opt-in controls, and backend traffic filtering.

| Article | Summary | Updated |
|---------|---------|---------|
| [Merchant Traffic Categories Architecture](traffic-categories/merchant-traffic-categories-architecture.md) | Architecture and implementation plan for optional merchant traffic categories with admin setup, trader toggles, and payment-detail filtering. | 2026-05-22 |

## payment-detail-schedules

Payment detail work schedule knowledge for trader-owned reusable server-time schedules and schedule-aware availability filtering.

| Article | Summary | Updated |
|---------|---------|---------|
| [Payment Detail Work Schedule Implementation Plan](payment-detail-schedules/payment-detail-work-schedule-implementation-plan.md) | Detailed technical specification and phased implementation plan for reusable trader-owned payment detail work schedules, server-time interval handling, schedule-aware traffic filtering, UI management, and role read access. | 2026-05-22 |

## trader-balance-transfers

Trader-to-trader working balance transfer knowledge for Team Leader scoped wallet movement.

| Article | Summary | Updated |
|---------|---------|---------|
| [Trader Balance Transfer Implementation Plan](trader-balance-transfers/trader-balance-transfer-implementation-plan.md) | Detailed specification and implementation plan for atomic `trust_balance` transfers between traders under the same Team Leader. | 2026-05-22 |

## dispute-bank-statements

Dispute rejection evidence requirements for bank/card statement uploads in classic Order dispute flows.

| Article | Summary | Updated |
|---------|---------|---------|
| [Dispute Bank Statement Implementation Plan](dispute-bank-statements/dispute-bank-statement-implementation-plan.md) | Specification and phased plan for dispute rejection with bank/card statement; late update adds enum reason code and optional statement for `wrong_details`; rollback now clears old statement artifacts. | 2026-05-22 |

## team-leader-insurance-mode

Team Leader shared insurance reserve mode knowledge for trader reserve delegation, admin limits, and wallet debit behavior.

| Article | Summary | Updated |
|---------|---------|---------|
| [Team Leader Shared Insurance Mode Specification](team-leader-insurance-mode/team-leader-shared-insurance-mode-spec.md) | Full technical specification and step-by-step implementation plan for the second Team Leader mode where connected traders use Team Leader `reserve_balance`. | 2026-05-22 |

## sms-automation

SMS/push automation ingress, filtering, and admin inspection outside the main `sms_logs` pipeline.

| Article | Summary | Updated |
|---------|---------|---------|
| [Shadow SMS Log Implementation Plan](sms-automation/shadow-sms-log-implementation-plan.md) | Plan for `shadow_sms_logs`: async logging of stop-list, stop-word, and max-length rejections at `SmsController`, admin page in the Автоматика group, search, and hard delete all. | 2026-05-23 |

## user-devices

Trader automation device connect API and admin inspection of raw device snapshots.

| Article | Summary | Updated |
|---------|---------|---------|
| [Device Connect Snapshot Implementation Plan](user-devices/device-connect-snapshot-implementation-plan.md) | **Shipped:** optional `device_connect_snapshot` on device connect, admin lazy snapshot modal; migration `2026_05_22_212222_*`. | 2026-05-23 |
