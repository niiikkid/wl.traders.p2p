# Internal Crypto Wallet Deposits Migration Specification

> Purpose: describe how the internal crypto wallet deposit feature is built in Cortex and turn it into a portable technical specification for another project that currently depends on an external crypto processing service.
>
> Scope: USDT/TRON wallet top-ups through internal address management, blockchain polling, QR generation, manual review, and ledger-backed settlement.
>
> Important: the other project may have different invoice tables, wallet ownership, roles, balances, currencies, frontend screens, and accounting rules. This document describes the transferable architecture and explicitly marks the places that must be adapted.

## 1. Executive Summary

The old model is:

1. The product creates a local top-up request.
2. The product asks an external crypto processor to create an invoice.
3. The processor owns the payment address, payment page, QR, status detection, callbacks, and sometimes status polling.
4. The product receives `paid` / `expired` / `failed` from the processor and then credits an internal wallet.

The internal model is:

1. The product owns a pool of deposit addresses.
2. The product creates the deposit invoice locally.
3. The product assigns one address from the pool and stores an immutable invoice snapshot.
4. The backend generates and stores the QR image.
5. The frontend renders the payment instructions itself.
6. A scheduled backend worker reads the blockchain directly.
7. The backend matches incoming transfers to invoices by address, exact amount, time window, token contract, and transaction uniqueness.
8. The backend waits for enough confirmations.
9. The backend credits the target wallet through the project's ledger/accounting module exactly once.
10. Admins resolve ambiguous, late, or wrong payments through a manual review screen.

The main architectural shift is that the local project becomes the source of truth for invoice state and settlement. The blockchain data provider, for example TronGrid, is only a read source. It must not own business status, wallet balances, or payment decisions.

## 2. What Is Implemented In Cortex

In Cortex this feature is called `wallet-deposit-invoices`.

Business goal:

- A provider needs to top up a `provider_collateral` wallet with USDT so it can continue participating in pay-in routing.
- The provider opens the SPA, chooses an eligible collateral wallet, enters an amount, and gets a TRON address + QR code + expiry time.
- Cortex watches the blockchain and credits the wallet when a matching confirmed USDT/TRON transfer is found.

Current Cortex constraints:

- Asset: `USDT`.
- Network: `TRON`.
- Create amount: positive whole number.
- Target wallet type: `provider_collateral`.
- Deposit addresses: shared platform pool, not owned by one provider integration.
- Automatic settlement: exact amount only.
- Late transfer after invoice expiry: not auto-credited.
- Balance change: only through `LedgerService`.
- Manual resolution: admin-only, audited, and visibly marked as manual.

For another project, these names will change. The transferable rule is not "use `provider_collateral`", but:

- define which internal wallets/accounts are eligible for top-up;
- enforce eligibility on the backend, not only in the UI;
- route all final credits through the project's existing accounting/ledger boundary;
- keep invoice creation, blockchain detection, and balance settlement inside the local system.

## 3. Core Invariants

These rules should survive the migration even if the other project's schema is different.

### 3.1 Local System Is Source Of Truth

The local project owns:

- invoice ID;
- invoice status;
- assigned payment address;
- expected amount;
- expiry time;
- QR image;
- matched transaction hash;
- confirmation count;
- final wallet/account credit;
- admin manual resolution state.

The blockchain API owns only raw facts:

- a transaction exists;
- token contract;
- sender;
- recipient;
- amount;
- timestamp;
- block / confirmations.

Do not let the blockchain provider or the old processor dictate internal balances.

### 3.2 One Invoice Means One Payment Instruction Snapshot

When an invoice is created, store a snapshot:

- target wallet/account ID;
- amount;
- currency;
- network;
- assigned deposit address;
- expiry time;
- QR storage path;
- status = `pending`.

Historical invoices should remain understandable even if an admin later renames an address, disables it, changes settings, or the wallet owner changes display name.

### 3.3 Exact Automatic Matching

Automatic settlement must match:

- same address;
- same currency/token contract;
- same network;
- same exact amount;
- transfer time inside the invoice window;
- transaction hash not used by another active or paid invoice;
- confirmations at or above the required threshold before final credit.

No fuzzy amount matching for automatic settlement. Fuzzy logic is allowed only for address allocation and UI warnings.

### 3.4 Idempotent Ledger Settlement

The final credit must be idempotent.

Use a deterministic key like:

```text
wallet-deposit-invoice:{invoice_id}:credit
```

or the equivalent in the target project.

If callbacks, polling jobs, retries, or manual clicks happen twice, the wallet/account must be credited once.

### 3.5 Final Statuses Do Not Move Back Automatically

Once an invoice is `paid`, `expired`, `cancelled`, `failed`, or otherwise final, background jobs must not reopen it or move it backward.

If the target product needs admin reopening, implement it as a separate explicit admin action with audit logging. Do not hide it in polling.

## 4. Domain Model

The names below are Cortex names. In the target project, map them to local equivalents.

### 4.1 Deposit Address

Cortex model: `WalletDepositAddress`.

Purpose: shared pool of addresses that can receive deposits.

Recommended fields:

| Field | Meaning |
| --- | --- |
| `id` | Local address ID. |
| `currency` | Example: `USDT`. |
| `network` | Example: `TRON`. |
| `address` | Deposit address shown to users. |
| `label` | Admin label. |
| `is_active` | Whether new invoices can use it. |
| `balance_units` | Last known on-chain token balance. |
| `last_checked_at` | Last balance refresh time. |
| `last_error` | Sanitized diagnostic for admin. |
| `metadata` | Optional operational details. |

Recommended constraints:

- unique `(currency, network, address)`;
- index `(currency, network, is_active)`;
- index `last_checked_at`.

Adaptation notes:

- If the target project encrypts addresses, keep a separate normalized hash for uniqueness/search.
- If each merchant/project must have its own addresses, replace the shared pool with scoped pools, but keep the same allocation and settlement invariants.
- If the target project supports multiple assets/networks, the pool must be filtered by invoice asset/network.

### 4.2 Deposit Settings

Cortex model: `WalletDepositSettings`.

Purpose: admin-managed operational thresholds.

Recommended settings:

| Setting | Cortex default | Meaning |
| --- | ---: | --- |
| `invoice_expires_in_minutes` | `30` | Payment window. |
| `min_confirmations` | `10` | Required confirmations before final credit. |
| `amount_collision_percent` | `5` | Address allocation safety window. |
| `manual_review_page_size` | `50` | Page size for admin blockchain transfer lookup. |

Adaptation notes:

- These can be global, per merchant, per project, or per currency/network.
- If they are scoped, save the effective settings snapshot on each invoice or make sure historical behavior remains explainable.
- Do not read operational settings only from `.env` if admins need to tune them.

### 4.3 Deposit Invoice

Cortex model: `WalletDepositInvoice`.

Purpose: local payment instruction and settlement state.

Recommended fields:

| Field | Meaning |
| --- | --- |
| `id` | Local UUID or other stable invoice ID. |
| `wallet_id` / target account ID | Internal destination for the credit. |
| `deposit_address_id` | Assigned address row. |
| `address` | Snapshot of the shown address. |
| `amount_units` | Expected amount in safe minor units / decimal representation. |
| `currency` | Example: `USDT`. |
| `network` | Example: `TRON`. |
| `status` | Local invoice status. |
| `txid` | Attached blockchain transaction hash. |
| `confirmations` | Last known confirmation count. |
| `amount_received_units` | Actual on-chain amount when matched. |
| `qr_disk`, `qr_path` | Private QR storage location. |
| `match_type` | `automatic` or `manual`. |
| `matched_at` | When the transaction was first attached. |
| `resolved_by_user_id` | Admin who manually resolved the invoice. |
| `resolved_at` | Manual resolution timestamp. |
| `resolution_note` | Admin note. |
| `expires_at` | Payment deadline. |
| `finalized_at` | Final status timestamp. |
| `last_checked_at` | Last polling/check time. |
| `poll_until_at` | Last time until which polling is allowed. |
| `error_message` | Sanitized diagnostic code. |

Fields to remove from an old external-processor integration:

- external invoice ID, unless retained only for historical records;
- external payment URL;
- external processor status;
- callback token fields for this flow;
- processor-specific DTO names in the active business code;
- public processor callback endpoint.

Migration note:

- If old invoices already exist, keep old fields as nullable historical fields or migrate them to an archive format.
- New internal invoices should not require an external invoice ID or hosted payment URL.

## 5. Status Model

Cortex statuses:

| Status | Meaning | Final |
| --- | --- | --- |
| `pending` | Invoice is active, no matching transfer attached. | No |
| `processing` | Matching transfer attached, waiting for confirmations. | No |
| `paid` | Wallet/account credit posted. | Yes |
| `expired` | Payment window ended before valid settlement. | Yes |
| `cancelled` | Cancelled before settlement. | Yes |
| `failed` | Internal failure requiring attention. | Yes |
| `amount_mismatch` | Reserved/problem state for wrong amount. | Yes in Cortex |

Target project adaptation:

- You may rename statuses, but keep the lifecycle:

```text
pending -> processing -> paid
pending -> expired
pending/processing -> failed
pending/processing -> cancelled
```

- Do not merge `processing` into `paid`. A transaction can exist but not have enough confirmations yet.
- Do not treat "transaction seen" as "money credited".

## 6. Address Allocation

Problem: if many invoices use the same address, incoming transfers must be attributable.

Cortex solution:

- Reuse addresses from the active pool.
- Prevent active invoices on the same address from having equal or too-close amounts.
- Default collision window is `+/-5%`.

For new invoice amount `A`, an address cannot be used if it already has an active invoice with amount `B` where:

```text
lower = A * (1 - collision_percent / 100)
upper = A * (1 + collision_percent / 100)
collision if B >= lower and B <= upper
```

Active invoices are:

- `pending`;
- `processing`;
- not expired by time.

Selection strategy in Cortex:

1. Filter active addresses by currency/network.
2. Sort by fewest open invoices.
3. Then by oldest `last_checked_at`.
4. Then by lowest ID.
5. Lock candidate rows inside a transaction.
6. Check open invoice collisions under lock.
7. Create the invoice in the same transaction.

Target project requirements:

- Use database transactions and row locks or an equivalent concurrency control mechanism.
- Avoid `float`; use decimal strings, integer minor units, or a money value object.
- If no address is available, return a clear domain error, for example `409 no deposit address available for this amount`.
- The frontend should suggest a different amount or trying again later.

## 7. Invoice Creation Flow

Backend flow:

1. Validate request.
2. Load destination wallet/account.
3. Check user can top up this wallet/account.
4. Check wallet/account is eligible for this type of deposit.
5. Convert amount into safe internal money units.
6. Load effective deposit settings.
7. In a database transaction:
   - allocate deposit address;
   - create invoice with snapshot fields;
   - set `status = pending`;
   - set `expires_at`;
   - set `poll_until_at`.
8. Generate QR image after invoice exists.
9. Store QR in private storage.
10. Save QR disk/path.
11. Return invoice resource to frontend.

In Cortex, the request body is:

```json
{
  "wallet_id": 123,
  "amount": "100"
}
```

In another project, this may become:

```json
{
  "account_id": "acc_...",
  "amount": "100",
  "asset": "USDT",
  "network": "TRON"
}
```

or:

```json
{
  "invoice_owner_id": "...",
  "balance_id": "...",
  "amount": "100"
}
```

The important part is not the field names. The important part is that the backend chooses the address, stores the payment instruction, and owns the status.

## 8. QR Generation

Cortex:

- QR is generated by backend.
- QR payload is the deposit address.
- QR is saved once to private storage.
- API returns a `qr_url`.
- Frontend renders `<img src="{qr_url}">`.

Recommended behavior:

- Generate QR after invoice creation.
- Store it under a deterministic path, for example:

```text
wallet-deposit-invoices/{invoice_id}/qr.png
```

- Do not store QR on a public disk.
- Serve QR through a controller endpoint.
- Decide whether QR endpoint requires auth:
  - Cortex QR endpoint is public if someone knows the invoice UUID.
  - A stricter project can require auth and invoice visibility.

Target project decision:

- If invoices are sensitive, require authorization on QR endpoint.
- If public payment pages exist, expose only a safe public invoice resource and QR, not internal account data.

## 9. Blockchain Boundary

Cortex service: `TronGridClient`.

Responsibilities:

- list confirmed incoming USDT/TRON transfers for an address;
- fetch one transfer by transaction hash;
- calculate confirmations;
- fetch address USDT balance for admin diagnostics;
- normalize provider response into internal DTOs;
- log outbound HTTP exchanges with sensitive headers removed.

DTO shape:

```text
TronTransfer
- txid
- from
- to
- amount
- timestamp
- confirmations

TronTransferPage
- transfers
- fingerprint / next page cursor
```

Important implementation details:

- USDT/TRON uses TRC20 token units with 6 decimals.
- The local money layer may use different precision; conversion must be explicit.
- Verify the USDT contract address.
- Verify recipient address.
- Normalize TRON address formats if APIs return both base58 and hex forms.
- Use HTTP connect timeout and total timeout.
- Bound pagination.
- Do not pass raw blockchain payloads into ledger/accounting code.

Target project adaptation:

- If using another chain, replace `TronGridClient` with a chain-specific reader.
- If using another token, verify the token contract / mint / asset ID.
- If using native coin deposits, adapt matching to native transfer format.
- Keep the same service boundary: external chain API returns facts, business actions decide settlement.

## 10. Automatic Polling And Settlement

Cortex runs every minute:

```text
wallet-deposit-invoices:dispatch-polls
  -> PollWalletDepositInvoiceJob
  -> ScanWalletDepositInvoiceForPayment
  -> ApplyWalletDepositTransfer
```

### 10.1 Dispatcher

The command selects open invoices:

- status is `pending` or `processing`;
- `poll_until_at` is null or in the future.

It chunks IDs and dispatches one job per invoice.

### 10.2 Poll Job

The job:

1. Re-loads the invoice by ID.
2. Skips if invoice no longer exists.
3. Skips if invoice is no longer open for polling.
4. Calls the scanning action.
5. On exception, stores a sanitized error code and reports the exception.

The job must be idempotent. Re-running it must not double-credit.

### 10.3 Scan Action

For one invoice:

1. If final, return.
2. If expired by time, mark `expired`.
3. If status is `processing` and `txid` exists:
   - refresh confirmations;
   - settle if enough confirmations.
4. If status is `pending`:
   - list incoming transfers for invoice address;
   - restrict by invoice creation/expiry window;
   - filter exact amount;
   - reject already-used tx hashes;
   - if no matches, only update `last_checked_at`;
   - if multiple matches, store diagnostic and do not guess;
   - if exactly one match, attach it and move to `processing` or `paid`.

### 10.4 Settlement Action

Cortex action: `ApplyWalletDepositTransfer`.

This is the single safe settlement point.

Inside a DB transaction:

1. Lock invoice row.
2. Load target wallet/account.
3. If final:
   - automatic call returns without changing;
   - manual call is rejected.
4. Validate recipient address.
5. Validate amount unless manual override is explicitly allowed.
6. Validate target wallet/account eligibility.
7. Validate tx hash is not attached to another `processing` or `paid` invoice.
8. Save txid, amount received, confirmations, match type, matched time.
9. If confirmations are enough:
   - set status `paid`;
   - set `finalized_at`;
   - credit wallet/account through ledger with idempotency key.
10. If confirmations are not enough:
   - set status `processing`.

Target project adaptation:

- Replace `LedgerService::credit` with the target project's balance posting boundary.
- If the target project has double-entry ledger, create a transaction + entries.
- If it has simple balances, still add an idempotency table/key before changing balance.
- Store enough metadata for audit: `txid`, amount, address, match type, confirmations, admin ID if manual.

## 11. Manual Review

Manual review exists because automatic settlement deliberately refuses to guess.

Problem cases:

- wrong amount;
- late transfer;
- multiple exact matches;
- transaction not confirmed yet;
- transaction already attached elsewhere;
- blockchain API temporary failure;
- user claims payment but automatic lookup did not find it.

Admin flow:

1. Admin opens invoice.
2. Admin requests incoming transfers for the invoice address.
3. Backend reads blockchain data and returns a paginated list.
4. UI shows warnings:
   - amount matches invoice;
   - transfer is inside invoice window;
   - tx already attached;
   - explorer link.
5. Admin chooses one tx and confirms.
6. Backend re-fetches the selected tx fresh by hash.
7. Backend validates address, token, uniqueness, wallet eligibility.
8. Backend attaches tx as `manual`.
9. If confirmations are enough, backend settles immediately.
10. Otherwise invoice becomes `processing`.
11. Audit log records manual resolution.

Cortex allows manual amount override:

- automatic settlement credits the invoice amount only if exact match;
- manual settlement may replace invoice amount with the actual on-chain amount;
- this is admin-only and audited.

Target project decision:

- Decide whether manual amount override is allowed.
- If not allowed, manual attach should still require exact amount.
- If allowed, UI must warn clearly that the credited amount will be the actual on-chain amount, not the original invoice amount.

## 12. API Surface

Use project-specific route names, but keep these capabilities.

### 12.1 User Invoice API

Required:

```text
GET  /api/v1/wallet-deposit-invoices
POST /api/v1/wallet-deposit-invoices
GET  /api/v1/wallet-deposit-invoices/{invoice}
GET  /api/v1/wallet-deposit-invoices/{invoice}/qr
```

Create response should include:

```json
{
  "id": "uuid",
  "status": "pending",
  "wallet_id": 123,
  "amount": "100.00000000",
  "currency": "USDT",
  "network": "TRON",
  "address": "T...",
  "qr_url": "/api/v1/wallet-deposit-invoices/{id}/qr",
  "txid": null,
  "tx_explorer_url": null,
  "amount_received": null,
  "confirmations": 0,
  "required_confirmations": 10,
  "match_type": null,
  "expires_at": "2026-06-16T00:30:00.000000Z",
  "created_at": "2026-06-16T00:00:00.000000Z",
  "updated_at": "2026-06-16T00:00:00.000000Z"
}
```

In the target project, replace `wallet_id` with the local balance/account/invoice owner reference.

### 12.2 Admin Settings API

Required:

```text
GET   /api/v1/wallet-deposit-settings
PATCH /api/v1/wallet-deposit-settings
```

Fields:

- invoice lifetime;
- minimum confirmations;
- amount collision percent;
- manual review page size;
- supported currency/network, read-only if hardcoded.

### 12.3 Admin Address Pool API

Required:

```text
GET   /api/v1/wallet-deposit-addresses
POST  /api/v1/wallet-deposit-addresses
GET   /api/v1/wallet-deposit-addresses/{address}
PATCH /api/v1/wallet-deposit-addresses/{address}
POST  /api/v1/wallet-deposit-addresses/{address}/refresh-balance
```

Admin must be able to:

- add address;
- label address;
- enable/disable address for new invoices;
- see active invoice count;
- refresh on-chain token balance;
- see sanitized lookup errors.

### 12.4 Admin Manual Review API

Required:

```text
GET  /api/v1/wallet-deposit-invoices/{invoice}/transfers
POST /api/v1/wallet-deposit-invoices/{invoice}/manual-attach-transfer
```

Transfer row shape:

```json
{
  "txid": "...",
  "from": "T...",
  "to": "T...",
  "amount": "100.00000000",
  "currency": "USDT",
  "network": "TRON",
  "timestamp": "2026-06-16T00:12:00.000000Z",
  "confirmed": true,
  "matches_invoice_amount": true,
  "inside_invoice_window": true,
  "already_attached": false,
  "explorer_url": "https://tronscan.org/#/transaction/..."
}
```

Manual attach request:

```json
{
  "txid": "transaction_hash",
  "note": "Admin matched transfer after manual review."
}
```

## 13. Frontend UX

### 13.1 User Top-Up Flow

1. User chooses an eligible wallet/account.
2. User enters amount.
3. Frontend creates invoice.
4. Frontend renders:
   - exact amount;
   - deposit address;
   - copy buttons;
   - QR image from backend;
   - countdown to expiry;
   - status badge.
5. Frontend polls invoice every 10 seconds or another conservative interval.
6. Stop polling when status is final.
7. On `paid`, refresh wallet/account balance.

UI warnings:

- Send exact amount.
- Use correct network.
- Pay before expiry.
- Late/wrong amount requires admin review.
- Do not reuse an old invoice.

### 13.2 User Status Display

Recommended mapping:

| Status | UI |
| --- | --- |
| `pending` | Show payment details and countdown. |
| `processing` | Payment detected, waiting for confirmations. |
| `paid` | Success, show explorer link and refresh balance. |
| `expired` | Expired, offer new invoice. |
| `cancelled` | Cancelled, terminal. |
| `failed` | Internal failure, contact support/admin. |
| `amount_mismatch` | Needs admin review. |

### 13.3 Admin Screens

Minimum admin screens:

- deposit settings;
- address pool;
- invoice list;
- invoice detail;
- transfer lookup/manual review;
- balance/ledger history linked to invoice.

Admin invoice detail should show:

- target wallet/account;
- owner/project/customer context;
- amount;
- assigned address;
- status;
- txid/explorer;
- confirmations;
- match type;
- resolved by / note;
- last checked time;
- diagnostic code;
- ledger transaction link if available.

## 14. Migration From External Processor

### 14.1 Inventory Old Integration

Find and classify:

- external processor config;
- API client;
- create invoice call;
- callback endpoint;
- callback signature/token verification;
- status polling job;
- external invoice ID columns;
- payment URL columns;
- external status fields;
- local settlement action;
- ledger/balance credit code;
- frontend redirect/payment page usage;
- tests that mock processor callbacks.

### 14.2 Decide Historical Data Policy

Options:

1. Keep old processor invoices read-only.
2. Migrate open external invoices to internal invoices only if payment address and ownership data are reliable.
3. Close old open invoices and force users to create new internal invoices.

Recommended:

- Keep paid historical invoices for reporting.
- Do not try to silently convert old open processor invoices unless there is a strong business need.
- New invoices after cutover should be internal only.

### 14.3 Add Internal Tables

Add or adapt:

- deposit addresses;
- deposit settings;
- new invoice fields;
- indexes for status/polling/address/txid;
- optional unique or partial unique protection for attached tx hashes.

### 14.4 Build Internal Blockchain Reader

Implement:

- list incoming transfers by address;
- fetch transfer by txid;
- confirmation count;
- address balance;
- explorer URL builder;
- DTO normalization;
- logging and timeouts.

### 14.5 Replace Create Flow

Old:

```text
create local draft -> call processor -> save external_invoice_id/payment_url
```

New:

```text
validate target -> allocate local address -> create local invoice -> generate QR -> return local payment details
```

### 14.6 Replace Callback/Polling Flow

Old:

```text
processor callback/status -> map external status -> settle local wallet
```

New:

```text
scheduled local poll -> detect blockchain transfer -> wait confirmations -> settle local wallet
```

Remove callback endpoint for this feature unless it is still needed for old historical invoices.

### 14.7 Add Manual Review

This is not optional in practice. Without manual review, support cannot handle:

- wrong amount;
- late payment;
- ambiguous transfer;
- user complaint;
- blockchain API gaps.

### 14.8 Update Frontend

Replace:

- external `payment_url`;
- redirect to hosted processor page;
- external status labels;
- processor callback assumptions.

With:

- local address/QR rendering;
- local countdown;
- local invoice polling;
- status `pending/processing/paid/...`;
- admin address/settings/manual review screens.

### 14.9 Remove Old Processor Code

After cutover:

- delete external processor client if no longer used;
- remove processor env vars;
- remove callback route;
- remove processor DTOs;
- update API docs;
- update tests;
- update logs/alerts to monitor blockchain polling instead.

If the other project is already live, remove in two deployments:

1. Add internal flow and stop creating new external invoices.
2. After old open invoices close, remove external callback/client code.

## 15. Security Requirements

Do:

- validate target wallet/account ownership;
- validate wallet/account type and currency;
- never accept frontend-provided address, status, confirmations, or paid amount as truth;
- fetch manual tx fresh before applying;
- verify token contract;
- verify recipient address;
- verify tx uniqueness;
- keep API keys in env/config;
- redact API keys from logs;
- rate-limit sensitive public endpoints if any;
- audit admin settings changes, address changes, and manual settlements;
- keep QR files private unless deliberately exposing a safe public endpoint.

Do not:

- credit based only on user-uploaded txid;
- credit before enough confirmations;
- auto-credit wrong amount;
- reopen final invoices in background jobs;
- let one tx settle two invoices;
- hide balance movement outside the ledger/accounting module.

## 16. Testing Requirements

Minimum automated coverage for the target project:

### Invoice Creation

- creates internal invoice for eligible wallet/account;
- rejects ineligible wallet/account;
- rejects unsupported currency/network;
- rejects invalid amount;
- assigns active address;
- fails with a clear error if no address is available;
- stores QR privately;
- returns expected API resource fields.

### Address Allocation

- prevents same/near amount collision on same address;
- allows same amount on different address;
- ignores expired/final invoices for collision;
- locks or otherwise protects concurrent creates.

### Polling

- marks expired when time passes;
- leaves pending when no transfer found;
- attaches exact transfer and moves to `processing`;
- moves to `paid` when confirmations reach threshold;
- does not credit twice on repeated job;
- stores sanitized error on blockchain failure.

### Settlement

- rejects wrong recipient address;
- rejects wrong amount for automatic settlement;
- rejects ineligible target wallet/account;
- rejects tx already used;
- credits through ledger/accounting once;
- stores metadata.

### Manual Review

- lists incoming transfers with flags;
- bypasses cache when requested;
- re-fetches tx before attach;
- rejects missing tx;
- rejects final invoice;
- records admin ID/note;
- logs audit event;
- handles amount override according to product decision.

### API Authorization

- normal users see only their own invoices/wallets/accounts;
- admins see all;
- non-admin cannot manage addresses/settings/manual attach;
- QR endpoint follows the chosen public/auth policy.

## 17. Acceptance Criteria

The migration is complete when:

- New deposit invoices are created without external processor calls.
- Users see local address, exact amount, QR, expiry, and status.
- The backend polls blockchain directly.
- Exact confirmed transfers settle automatically.
- Ledger/accounting credit is idempotent.
- Wrong/late/ambiguous transfers do not auto-credit.
- Admin can manage deposit addresses.
- Admin can manage operational settings.
- Admin can inspect incoming transfers for an invoice address.
- Admin can manually attach a tx with audit logging.
- Old processor callback endpoint is removed or limited to historical invoices.
- External processor env/config/client code is removed or clearly deprecated.
- Frontend no longer depends on external `payment_url`.
- Tests cover money movement, statuses, idempotency, and authorization.

## 18. Implementation Plan For Another Project

### Phase 1: Discovery

- Document current external processor flow.
- Identify local wallet/account/balance model.
- Identify invoice model and statuses.
- Identify where final balance credit currently happens.
- Decide historical data policy.
- Decide supported assets/networks for first internal release.

### Phase 2: Internal Data Foundation

- Add address pool.
- Add deposit settings.
- Extend or replace deposit invoice fields.
- Add tx uniqueness protection.
- Add indexes for polling.

### Phase 3: Blockchain Reader

- Build chain API client.
- Normalize transfers into DTOs.
- Add explorer URL builder.
- Add timeout/logging/redaction.
- Fake the client in tests.

### Phase 4: Invoice Creation

- Replace external create call with address allocation.
- Store invoice snapshot.
- Generate private QR.
- Return local payment details.

### Phase 5: Polling And Settlement

- Add dispatcher command.
- Add per-invoice job.
- Add scan action.
- Add single settlement action.
- Route final credit through ledger/accounting with idempotency.

### Phase 6: Admin Manual Review

- Add transfer lookup endpoint.
- Add manual attach endpoint.
- Add admin UI warnings.
- Add audit logs.

### Phase 7: Frontend Cutover

- Replace external payment page with local payment view.
- Poll local invoice status.
- Add admin settings/address/review screens.
- Update copy and error handling.

### Phase 8: Cleanup

- Stop creating external invoices.
- Remove/deprecate external processor callback.
- Remove unused config/client/DTOs.
- Update docs and tests.
- Add operational alerts for polling failures and invoices stuck in `processing`.

## 19. Operational Monitoring

Track:

- count of open `pending` invoices;
- count of invoices stuck in `processing`;
- count of polling failures;
- count of multiple-match diagnostics;
- count of manual settlements;
- average time from create to paid;
- TronGrid/API error rate;
- address pool availability;
- addresses with high active invoice count;
- ledger settlement failures.

Alert on:

- no active deposit addresses;
- high blockchain API failure rate;
- invoices stuck in `processing` longer than expected;
- repeated tx uniqueness rejections;
- manual settlements above normal baseline.

## 20. Project-Specific Decisions To Make Before Implementation

Answer these for the target project:

1. Which wallet/account types can be topped up?
2. Who can create a deposit invoice?
3. Are addresses shared globally, per merchant, per provider, or per project?
4. Which assets/networks are supported in v1?
5. Are create amounts whole numbers only or decimals?
6. What is the required confirmation count?
7. What is the invoice expiry window?
8. Is manual amount override allowed?
9. Should QR endpoint be public or authenticated?
10. What happens to old external processor invoices?
11. What ledger/accounting operation records the credit?
12. What audit log exists for admin manual settlement?
13. What frontend screens are needed for users and admins?
14. What monitoring is required for operations/support?

## 21. Portable Summary

The feature is not "integrate TronGrid" and not "replace one processor API with another API".

The feature is an internal payment attribution and settlement module:

- the local project issues payment instructions;
- the local project owns invoice lifecycle;
- the local project reads blockchain facts;
- the local project makes conservative matching decisions;
- the local project credits balances through its own accounting boundary;
- admins resolve edge cases with explicit, audited actions.

That is the part to copy into another project. Table names, wallet names, roles, route names, and frontend layout can change.
