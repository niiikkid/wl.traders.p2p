# Phase 2 Validation and Service Implementation

> Source: Repository implementation in Cursor
> Collected: 2026-05-22
> Published: Unknown

## Summary

Phase 2 of dispute bank statement feature completed: validation, service contract, and all web cancel call sites.

## CancelRequest

- `reason`: required, string, max 120 (was max 255)
- `bank_statement`: required, file, max 5120 KB, `ReceiptFileRule`
- attributes: `причина отклонения`, `выписка по карте`

## DisputeServiceContract and DisputeService::cancel()

- Signature: `cancel(int $disputeID, string $reason, UploadedFile $bankStatement): bool`
- Inside transaction: pending check, `finishOrderAsFailed`, `replaceBankStatement()`, update status/reason/bank_statement, `checkRejectedDisputesLimit()`

## Controllers updated

- `app/Http/Controllers/DisputeController.php::cancel()`
- `app/Http/Controllers/Support/DisputeController.php::cancel()`
- `app/Http/Controllers/Analyst/DisputeController.php::cancel()`

All pass `validated('reason')` and `file('bank_statement')`.

## GenerateTestDataCommand

- `makeTestBankStatementFile()` creates minimal PNG for test data dispute cancellations

## Not changed (still Phase 3–4)

- `CancelDisputeModal.vue` — still free-text only, no `bank_statement` upload (UI rejection fails validation until Phase 3)
- `DisputeResource`, `DisputeModal.vue`
- API routes unchanged

## Acceptance (backend)

- Reject without reason or statement → validation error
- Unsupported type / over 5 MB → validation error
- Repeated rejection after rollback overwrites statement via `replaceBankStatement()`
