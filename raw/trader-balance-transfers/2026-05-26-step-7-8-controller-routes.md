# Step 7–8: TraderBalanceTransferController and Routes

> Source: Implementation session (Cursor), repository `p2p.cti`
> Collected: 2026-05-26
> Published: 2026-05-26

## Summary

Shipped thin JSON API controller and trader-authenticated routes for trader balance transfers. Steps 7–8 of the implementation plan; Inertia props and Vue UI remain steps 9–15.

## Files added

- `app/Http/Controllers/Wallet/TraderBalanceTransferController.php`

## Routes (`routes/web.php`)

Registered in the trader middleware group (`auth`, `banned`, `role:Trader|Super Admin`), immediately after `wallet.index`:

| Method | URI | Route name | Action |
|--------|-----|------------|--------|
| GET | `/wallet/trader-transfer/recipient` | `wallet.trader-transfer.recipient` | `recipient` |
| POST | `/wallet/trader-transfer` | `wallet.trader-transfer.store` | `store` |

```php
Route::prefix('wallet/trader-transfer')->name('wallet.trader-transfer.')->group(function () {
    Route::get('/recipient', [TraderBalanceTransferController::class, 'recipient'])->name('recipient');
    Route::post('/', [TraderBalanceTransferController::class, 'store'])->name('store');
});
```

Ziggy regenerated (`php artisan ziggy:generate resources/js/ziggy-routes.js`).

## Controller actions

Constructor-injected `TraderBalanceTransferService` (singleton).

### `recipient(CheckRecipientRequest $request)`

1. `resolveRecipient($request->user(), $request->recipientLogin())`
2. Success: `response()->json(recipientPreview($recipient))` — flat `{ login, avatar_uuid, avatar_style }`
3. `TraderBalanceTransferException` → `failTransfer()` → `422` + `{ message }`

### `store(StoreTransferRequest $request)`

1. `transfer($request->user(), $request->recipientLogin(), $request->amountMoney())`
2. Success: `200` + `{ message: "Средства переведены." }`
3. `TraderBalanceTransferException` → same `failTransfer()` mapping

### `failTransfer(TraderBalanceTransferException $exception)`

Always `422` with `{ message: $exception->getMessage() }` for:

- `recipientNotAvailable()`
- `insufficientTrustBalance()`
- `transferUnavailable()`

Form Request validation errors (amount, 2FA) use Laravel default `422` with `errors` object (not handled in controller).

`authorize()` failure on Form Requests → `403` before controller.

## Not in scope (steps 7–8)

- Inertia props on `WalletController@index` (step 9)
- Vue modal and «Перевести средства» button (steps 10–15)
- Automated tests (step 17, when requested)

## Next

Step 9: expose `traderBalanceTransfer` props on trader finances page (`transferAvailable`, trust balance for max amount, `has_2fa`).
