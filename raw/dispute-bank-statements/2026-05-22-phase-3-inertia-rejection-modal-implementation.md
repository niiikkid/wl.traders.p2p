# Phase 3 Inertia Rejection Modal Implementation

> Source: Cursor implementation session
> Collected: 2026-05-22
> Published: Unknown

Phase 3 of dispute bank statement feature completed: `CancelDisputeModal.vue` rejection UI with reason presets and bank statement upload.

## CancelDisputeModal.vue

- `useForm({ reasonPreset, reason, bank_statement })`
- Reason presets (dropdown): wrong_details, fake_receipt, payment_return, other (`Другая причина`)
- Fixed presets auto-fill `form.reason`; `other` shows TextInput with remaining-char counter (max 120, UI-enforced via watch)
- Hidden file input + «Выбрать файл» button; accept JPG/JPEG/PNG/PDF; helper «не более 5 МБ»
- Submit: `form.patch(route(...), { forceFormData: true, preserveScroll: true })` — routes `disputes.cancel`, `support.disputes.cancel`, `analyst.disputes.cancel` via `useViewStore`
- Submit disabled until preset selected, reason non-empty, and file chosen; spinner while `processing`
- Form reset on close and after success (`modalStore.closeAll()` + `router.visit(route(route().current()))`)
- `reasonPreset` is client-only; backend receives `reason` and `bank_statement` only

## Modal usage (unchanged)

- `resources/js/Pages/Dispute/Index.vue`
- `resources/js/Pages/Order/Index.vue`
- `resources/js/Pages/Support/Dispute/Index.vue`, `Support/Order/Index.vue`
- `resources/js/Pages/Analyst/Dispute/Index.vue`, `Analyst/Order/Index.vue`

## Not changed in Phase 3

- `DisputeResource`, `DisputeModal.vue` (Phase 4)
- API routes and H2H dispute endpoints

## Current gap after Phase 3

- Canceled disputes: no «Выписка» row in `DisputeModal` until Phase 4 adds `bank_statement_url` to `DisputeResource`
