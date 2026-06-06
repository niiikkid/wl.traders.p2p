# Backend Security Audit Checklist

## Project Context

P2P CTI is a high-risk fintech platform for fiat payin/payout operations between merchants and traders with USDT settlement. Primary assets are money, balances, deals, requisites, merchants, traders, admins, API keys, webhooks, OAuth identities, queues, logs, personal data, bank/card data, and operational privileges.

Backend stack to expect: PHP 8.3+, Laravel 11, Inertia Laravel v2, Sanctum v4, Horizon v5, Telescope v5, Pulse v1, Nightwatch v1, Spatie Laravel Permission v6, Google2FA, Socialite, Telegram OAuth, Zoho OAuth, Telegram Bot SDK, lab404 impersonation, Maatwebsite Excel, square1 idempotency, Sentry, TipTap PHP, MySQL 8, Redis, local/S3 storage, PHPUnit 11.

Frontend is out of primary scope except where backend security is affected: Inertia props leakage, CSRF, XSS through server-provided data, SSR payloads, Ziggy route exposure, axios credentials, and Sanctum SPA auth.

## Entry Points

Review these first:

- `routes/web.php`, `routes/api.php`, `routes/channels.php`, `routes/console.php`
- `app/Http/Controllers`, `app/Http/Middleware`, `app/Http/Requests`
- `app/Policies`, `app/Models`, `app/Services`, `app/Actions`
- `app/Jobs`, `app/Listeners`, `app/Events`, `app/Observers`, `app/Console`, `app/Providers`
- `config/*`, `database/migrations`, `database/seeders`, `bootstrap/app.php`
- `composer.json`, `composer.lock`, `package.json`, lockfiles, `.env.example`
- `storage/app/public` and export/upload paths when relevant

## Threat Model

Roles: Guest, authenticated user, Trader, Merchant, Admin, Super Admin, support/admin operator, impersonating admin, external provider, Telegram bot user, mobile app/device, queue worker, cron/console process.

Attackers: unauthenticated internet user, malicious trader, malicious merchant, compromised trader/merchant, malicious admin, impersonating admin, webhook sender, MITM/proxy, brute-force bot, API DoS actor, user trying to access another tenant's deals, user trying to alter deal status, double-payout attacker, limit-bypass attacker, OAuth identity substitution attacker.

Assets: accounts, 2FA secrets, API/OAuth/provider tokens, Telegram IDs, merchant/trader credentials, payment requisites, cards/phones/banks, deals, deposits, withdrawals, balances, ledger entries, rates, commissions, webhook secrets, files, exports, logs, queue payloads, Redis sessions/cache, Horizon/Telescope/Pulse dashboards.

## Priority Order

1. Routes and middleware.
2. Payment, deal, payin, payout, deposit controllers.
3. Balance, ledger, money services.
4. Webhook/callback controllers.
5. Auth, 2FA, OAuth.
6. Policies, middleware, roles, permissions.
7. Jobs touching money.
8. Financial migrations and constraints.
9. Config security.
10. Logs, exports, files, dependencies.

## Route Audit

Build a route risk table with method, URI, name, controller, middleware, auth required, role required, rate limit, CSRF, idempotency, financial impact, and object-level auth status.

Flag unauthenticated sensitive routes, missing role middleware, missing throttle, missing idempotency for money-changing POST routes, exposed admin/debug routes, and webhooks without signature verification.

## Authentication

Check login, registration, password reset, email verification, Sanctum, API token auth, Telegram OAuth, Zoho OAuth, Telegram Mini App auth, 2FA, sessions, remember me, logout, token revocation, and device/session invalidation.

Look for auth bypass, weak guards, mixed web/api guards, missing `auth:sanctum`, unprotected endpoints, reset token leakage, email/phone enumeration, brute force without throttling, 2FA bypass, OAuth takeover, login CSRF, session fixation, missing session regeneration after login, missing session invalidation after logout, remember token exposure, API tokens without expiration, and overbroad abilities.

Laravel expectations: regenerate session after login, invalidate session after logout, require auth on sensitive endpoints, use `auth:sanctum` for API endpoints, restrict Sanctum stateful domains, use secure/httpOnly/sameSite cookies, and keep production debug/session settings safe.

## Authorization, RBAC, ABAC

Check Spatie roles, permission names, `role`, `permission`, `can` middleware, policies, gates, controller authorization, service-level authorization, object-level authorization, admin/merchant/trader route separation, and ownership checks.

Flag BOLA/IDOR, cross-trader/merchant access, access to another user's requisites/exports/webhook configs, role changes through mass assignment, direct updates of `role_id`, `user_id`, `merchant_id`, `trader_id`, missing policies, frontend-only authorization, menu-only authorization, `find($id)` without ownership, tenantless queries, resource routes without middleware, controller actions without authorization, and services called from multiple places without actor checks.

Rule: every endpoint accepting an object ID must prove the current actor can read or mutate that exact object.

Sensitive models: User, Merchant, Trader, Deal, Order, Transaction, Payment, Requisite, Card, Deposit, Withdrawal, Payout, Balance, Ledger, Commission, ApiToken, Webhook, Export, File, Attachment.

## API Object Authorization

Audit all `GET`, `POST`, `PUT`, `PATCH`, and `DELETE` API endpoints: payin, payout, merchant, trader, admin, external integration, and mobile app APIs.

Flag predictable IDs, user-controlled owner IDs, API responses with another tenant's data, pagination/search/export without tenant scope, and nested routes missing parent-child relationship checks.

## Validation

Check Form Requests, inline validation, DTOs, service validation, API validation, webhook validation, console inputs, and file uploads.

Flag `$request->all()`, `$request->input()` without validation, `$request->only()` with sensitive fields, missing Form Requests, weak rules, `nullable` where required, `sometimes` on financial fields, numeric values without min/max, decimal values without precision, arrays without size limits, strings without max, URLs without allowlists, phone/card fields without normalization, enum/status fields without allowlists, and unsafe JSON fields.

High-risk fields: amount, rate, commission, balance, currency, status, user/merchant/trader IDs, role, permission, admin flags, callback/webhook/redirect/return URLs, files, metadata, payload, comments, rich text, bank card, phone, Telegram ID.

Financial fields must not use float. Prefer decimal strings, project money abstractions, explicit scale, min greater than zero, max limits, currency allowlists, and status transition validation.

## Mass Assignment

Audit `$fillable`, `$guarded`, casts, hidden fields, appends, mutators, factories, and seeders.

Flag `protected $guarded = [];`, sensitive fields in fillable, `create`/`update` from request payloads, and validated arrays that still contain owner/status/admin/money fields.

Sensitive fields include user/merchant/trader IDs, roles, permissions, balance, status, admin flags, active flags, 2FA secrets, API tokens, webhook secrets, commission, rate, limits, and reserved amounts.

## Financial Integrity

Audit deal creation, trader assignment, balance reservation, debits, credits, cancellations, disputes, payouts, payins, deposits, callbacks, commission, exchange rate, status transitions, and ledger.

Flag missing DB transactions, partial multi-table updates, double spend, callback re-execution, missing idempotency, missing unique operation IDs, mutable balance without immutable ledger, read-modify-write balance updates without locks, missing constraints, missing state machine, final status reversal, amount mismatch, rounding issues, floats, missing reconciliation, and missing audit trail.

Expect all money operations inside `DB::transaction`, row locks through `lockForUpdate`, immutable ledger entries, idempotent external callbacks, strict status transitions, immutable final statuses, no duplicate payout, no confirmation of cancelled/expired deals, immutable amount/owner after creation, and server-side commission calculation.

## Idempotency

The project uses `square1/laravel-idempotency`. Check financial routes, key storage, TTL, key scoping, request hash validation, replay behavior, response replay, and parallel same-key requests.

Flag money-changing POST routes without idempotency, optional idempotency keys, globally scoped keys, same key with different payload, replayed callbacks changing status, and retry jobs creating duplicate operations.

Expected scope: merchant/user/API token, endpoint, request hash, operation type. Same key with different payload must be rejected.

## Race Conditions

Review deal creation, trader matching, requisite reservation, limits, balances, statuses, jobs, callbacks, retry logic, Redis locks, and MySQL locks.

Flag two traders receiving one deal, double confirmation, parallel limit bypass, negative balances, one requisite assigned twice, webhook/manual admin conflicts, queue retry double-spend, and scheduled commands racing API actions.

Check for `DB::transaction`, `lockForUpdate`, unique indexes, optimistic versioning, Redis atomic locks, idempotency, and status checks inside the transaction.

## Status Machines

Financial entities need strict transitions: Deal, Order, Payin, Payout, Deposit, Withdrawal, Dispute, BalanceOperation, ProviderCallback.

Check statuses, allowed transitions, actor permissions, side effects, final statuses, rollback rules, timeout rules, and retry rules.

Flag any direct status mutation from request as Critical or High when it affects money or access.

## Webhook Security

Audit Telegram Bot webhooks, deposit provider callbacks, payment system callbacks, merchant callbacks, mobile app/device callbacks, and any `/webhook/*`, `/callback/*`, `/notify/*`.

Flag missing signature, timestamp, replay protection, IP allowlist where applicable, idempotency, transaction boundaries, amount/currency/order verification, strict schema validation, unknown event rejection, SSRF through callback URLs, secret leaks in responses, and raw sensitive payloads in logs.

Expect HMAC, timestamp tolerance, nonce/event ID, constant-time `hash_equals`, payload hash, unique provider event ID, strict event allowlist, safe logging, and no blind trust in client-supplied status.

## API Keys and Tokens

Audit Sanctum tokens, merchant keys, provider tokens, Telegram tokens, OAuth tokens, Sentry DSN, webhook secrets, app/encryption keys.

Flag secrets in code/config/logs, tokens returned repeatedly after creation, plaintext storage, no rotation, no expiration, missing abilities/scopes, missing `last_used_at`, missing revoke path, admin actions through API tokens, and overbroad Sanctum abilities.

Expect hashed tokens, one-time display of plain token, scopes/abilities, expiration, rotation, revoke, audit log, and per-token rate limiting.

## CSRF, CORS, Sanctum SPA

Check `config/cors.php`, `config/sanctum.php`, `config/session.php`, middleware groups, axios setup, Inertia forms, and CSRF exclusions.

Flag wildcard origins with credentials, broad stateful domains, CSRF disabled on sensitive routes, unsafe CSRF exceptions, cookie-auth API without CSRF, SameSite=None without Secure, and broad session domains.

## Rate Limiting

Check login, password reset, 2FA verify, OTP, Telegram auth, OAuth callbacks, deal creation, payin/payout, exports, webhooks, search, reports, and uploads.

Expect `RateLimiter::for(...)`, per-role/per-merchant/per-token/IP limits, auth backoff, lockout audit logs, max pagination sizes, and abuse controls for exports/reports.

## Sensitive Data Exposure

Audit API responses, Inertia props, resources, model `$hidden`, logs, Sentry, Telescope, exports, notifications, emails, and queue payloads.

Flag leaks of password hashes, remember tokens, 2FA secrets/recovery codes, API/OAuth/provider tokens, bot tokens, webhook secrets, card/phone/bank details, raw provider callbacks, IP/user agent, admin notes, internal IDs, other users' balances, permissions, config, and env.

## Inertia and Backend Leakage

Check `HandleInertiaRequests`, `Inertia::share`, controller props, auth user props, permissions props, Ziggy routes, and SSR payloads.

Flag full User models in props, excessive roles/permissions, cross-role merchant/trader data, hidden fields reconstructed in arrays, sensitive flash messages, SQL/stack traces in errors, and unnecessary exposure of admin routes.

## XSS and Rich Text

Audit TipTap PHP/Vue, comments, descriptions, admin notes, messages, dispute messages, notifications, rich text storage/rendering, and exports.

Flag stored XSS, raw HTML output, unsafe markdown/HTML, missing sanitize allowlist, `javascript:` links, image tracking/SSRF risks, admin panel XSS, and Excel/CSV formula injection.

Expect server-side sanitize allowlists, escaped output by default, CSP recommendations, and formula injection protection for values beginning with `=`, `+`, `-`, or `@`.

## SQL and Query Safety

Review `DB::raw`, `whereRaw`, `orderByRaw`, `selectRaw`, dynamic table/column names, search/sort/filter params, report queries, and export queries.

Flag user input in raw SQL, dynamic `orderBy($request->sort)` without allowlist, raw LIKE without binding, report filters without validation, and unsafe scopes.

## Uploads, Storage, Exports

Audit uploads, local/S3 disks, public storage, Maatwebsite Excel exports/imports, and attachment downloads.

Flag arbitrary uploads, public private files, path traversal, filename injection, MIME spoofing, missing size limit, unauthenticated downloads, predictable paths, Excel/CSV injection, sensitive exports without audit logs, links without expiration, public buckets, and unsafe public disk usage.

Expect extension/MIME/size validation, random filenames, private disk by default, signed temporary URLs, download authorization, restricted formats, formula escaping, and export generation/download audit logs.

## SSRF and Outbound Requests

Audit Laravel HTTP client, Guzzle, merchant callback URLs, geolocation, provider APIs, Zoho/Telegram, and integrations.

Flag user-controlled URLs, callback URLs without allowlist, redirects to internal IPs, metadata service access, missing timeouts, unbounded retries, disabled TLS verification, and DNS rebinding risk.

Expect HTTPS-only where possible, private/localhost/link-local IP blocks, timeouts, controlled redirects, provider allowlists, and signed callbacks.

## Queues, Horizon, Jobs

Audit `app/Jobs`, queue payloads, failed jobs, retries, Horizon config, job middleware, unique jobs, and job idempotency.

Flag money jobs that re-run side effects, user-controlled model IDs without fresh authorization/invariant checks, sensitive serialized data, secrets in payloads, infinite retries, failed job leaks, public Horizon, and dangerous code from payloads.

Expect idempotent jobs, unique operation IDs, no secrets in payloads, fresh model reads, transaction-scoped status checks, safe retries, max attempts/backoff, and Horizon protected by auth/role in production.

## Monitoring and Debug Tools

Audit Telescope, Pulse, Horizon, Debugbar, gates, middleware, service providers, and env config.

Flag production Telescope/Debugbar exposure, public Pulse/Horizon, request/token/password storage in Telescope, Sentry PII/secrets, and weak gates.

## Impersonation

The project uses `lab404/laravel-impersonate`; treat it as high-risk.

Check who can impersonate, target restrictions, admin/super-admin protection, money action restrictions, start/stop audit logs, visible mode banner, reason requirement, time limit, fresh 2FA/session, direct routes, and CSRF.

Flag admin creating payouts while impersonating, confirming deals as a trader, missing audit log, no reason, no time limit, missing 2FA, direct route escalation, and missing CSRF.

Expect real actor and impersonated user in audit logs, no impersonating admins/super-admins, and blocking critical financial operations unless explicitly allowed.

## 2FA

Audit Google2FA setup, secret generation/storage, recovery codes, enable/disable flow, login verification, admin action requirements, and reset flow.

Flag plaintext secrets, disabling without password/2FA, OAuth/remember-session bypass, no rate limit, plaintext recovery codes, no audit log, and no 2FA requirement for admin/financial actions.

Expect encrypted secrets, hashed recovery codes, rate limiting, password/current 2FA to disable, 2FA for admins, and fresh 2FA for payout/admin sensitive operations.

## OAuth Telegram and Zoho

Check Socialite callbacks, state, redirect URI, account linking, email/phone trust, Telegram identity validation, and token storage.

Flag OAuth CSRF, takeover by email-only match, trusting unverified email, linking without re-auth, open redirect, missing state, Telegram ID mapping issues, and plaintext provider tokens.

## Telegram Bot and Mini App

Check webhook routes, bot token handling, chat ID mapping, Telegram login/initData validation, command handlers, user binding, callback queries, and rate limits.

Flag webhook without secret, missing initData validation, binding another user's account, command data leaks, no rate limit, sensitive chat messages, and callback queries changing financial status without authorization.

## Business Logic Abuse

Validate these invariants: traders cannot see other traders' deals; merchants cannot change trader assignment; users cannot assign roles to themselves; completed deals cannot be cancelled; users cannot confirm another user's deal; limits cannot be bypassed; commission cannot be bypassed; amount must be positive; requisites must belong to the actor; payouts require available balance; payout cannot repeat; amount/currency/owner cannot change after creation; client cannot set exchange rate; provider callbacks cannot be manually triggered.

## Database Schema

Audit migrations for FKs, unique indexes, decimal precision, nullability, defaults, tenant indexes, constraints, status fields, and soft deletes.

Flag float/double money, nullable amount, free-string status without enum/check, missing FKs, missing unique provider event/idempotency constraints, missing ownership indexes, dangerous cascade deletes, soft-deleted sensitive records still accessible, and missing immutable ledger table.

Expected indexes: `merchant_id + external_id`, `user_id + id`, `provider + provider_event_id`, and `idempotency_key + actor_id + endpoint`.

## Redis, Cache, Session

Check Redis sessions, cache keys, locks, rate limit keys, Horizon, queue, and cache invalidation.

Flag secrets in cache, predictable cross-tenant keys, missing tenant scoping, stale permissions cache, excessive session lifetime, insecure cookies, missing session invalidation after password/2FA changes, and missing Redis locks for critical non-DB flows.

## Logging and Audit Trail

Audit logging for admin actions, role/permission changes, login/logout, failed login, 2FA changes, token create/revoke, OAuth link/unlink, impersonation, payout/deposit/deal status changes, manual corrections, exports, webhook events, and balance changes.

Flag no audit logs, mutable/deletable logs, missing actor/real actor/IP/user agent/before-after/reason/correlation ID, sensitive data in logs, and log injection.

Expect append-only audit logs with actor, real actor, entity, diff, IP, user agent, request ID, reason for sensitive actions, and no secrets.

## Error Handling and Config

Check exception handling, API errors, validation errors, Sentry, debug mode, custom exceptions, `.env.example`, `config/*.php`, composer scripts, Vite config, and app config.

Flag production stack traces, SQL errors, secrets in errors, enumeration through auth errors, swallowed exceptions, partial financial updates, `APP_DEBUG=true`, local env defaults, insecure sessions/cookies/CORS, public monitoring tools, public S3, sync queue in production, weak trusted proxies, and committed secrets.

Baseline production env:

```env
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

## Dependencies and Supply Chain

Review dependency manifests, lockfiles, dev packages, abandoned packages, scripts, and post-install hooks.

Useful read-only or advisory commands to suggest or run only when appropriate: `composer audit`, `npm audit`, `composer outdated --direct`, `npm outdated`, `php artisan about`, `php artisan route:list`.

## Search Patterns

Dangerous Laravel/PHP:

```text
$request->all()
$request->input()
$request->get()
Model::create(
->update($request
protected $guarded = [];
DB::raw(
whereRaw(
orderByRaw(
selectRaw(
eval(
unserialize(
shell_exec(
exec(
system(
passthru(
file_get_contents($request
Storage::get($request
redirect($request
Http::get($request
Http::post($request
```

Financial:

```text
->balance
available_balance
reserved_balance
amount
commission
rate
status
payout
payin
deposit
withdraw
callback
webhook
confirm
approve
cancel
complete
```

Authorization:

```text
findOrFail($id)
find($id)
where('id', $id)
Route::resource
withoutMiddleware
can:
role:
permission:
authorize(
Gate::
Policy
```

Debug and secrets:

```text
Telescope
Horizon
Pulse
Debugbar
APP_DEBUG
dump(
dd(
ray(
token
secret
password
api_key
private_key
webhook_secret
bot_token
client_secret
SENTRY
TELEGRAM
ZOHO
```

## Testing Expectations

For every issue, propose a focused test. Security tests should cover auth, authorization, financial integrity, webhook verification, impersonation, and export isolation.

Examples: unauthenticated users cannot access protected routes; wrong roles are blocked; merchant A cannot access merchant B deals; trader A cannot access trader B deals; users cannot update owner/status/balance fields; payouts cannot duplicate with same idempotency key or parallel requests; balances cannot go negative; final deals cannot change; callback replay does not duplicate ledger entries; invalid webhook signatures/timestamps/replays are rejected; impersonation requires permission and logs real actor; exports cannot include another tenant's data and formula values are escaped.

## Architecture Recommendations

If absent, recommend:

- Immutable ledger with operation UUID, wallet, actor, type, direction, amount, currency, before/after balances, related entity, metadata, timestamps, and no update/delete path.
- Status transition service/state machine for all financial statuses.
- Append-only audit log for admin actions, role changes, money changes, impersonation, exports, and webhook processing.
- Central `WebhookVerifier::verify($request, $provider)` checking signature, timestamp, replay, event ID, and schema.
- Money/Ledger service boundary that prevents direct balance mutation outside controlled services.

## Do Not Recommend

Do not recommend disabling security controls, storing tokens in plaintext, using floats for money, relying on frontend role checks, treating Spatie Permission as object authorization, treating Sanctum as sufficient without route middleware, treating validation as authorization, ignoring business logic vulnerabilities, taking destructive actions, running commands that mutate production data, or printing real secrets in reports.
