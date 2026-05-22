# Knowledge Base Index

## telegram

Telegram bot and chat automation knowledge for the project.

| Article | Summary | Updated |
|---------|---------|---------|
| [Telegram Chat Dispute Automation Plan](telegram/telegram-chat-dispute-automation-plan.md) | Telegram chat dispute bot; Phases 1–6 complete; Features 1–2 (reply + resolution notifications) implemented; live Telegram verification pending. | 2026-05-22 |
| [Telegram Dispute Reply and Resolution Notifications Specification](telegram/telegram-dispute-reply-and-resolution-notifications-spec.md) | Reply/resolution spec; Features 1–2 implemented (`sendChatMessage`, `sendChatDocument`, resolution job, `DisputeService` dispatch); includes `wrong_details` text-only rejection path without statement; Phase 5 manual checks pending. | 2026-05-22 |

## traffic-categories

Merchant traffic category knowledge for admin-managed category setup, trader opt-in controls, and backend traffic filtering.

| Article | Summary | Updated |
|---------|---------|---------|
| [Merchant Traffic Categories Architecture](traffic-categories/merchant-traffic-categories-architecture.md) | Architecture and implementation plan for optional merchant traffic categories with admin setup, trader toggles, and payment-detail filtering. | 2026-05-22 |

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
