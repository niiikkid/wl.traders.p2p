Аудит завершён. Ниже отчёт.

# Backend Security Audit Report — P2P CTI

## Executive Summary

- **Overall risk: High**
- Critical findings: 2
- High findings: 6
- Medium findings: 6
- Low/Info findings: 4

**Main risks:**
- Отключённая проверка TLS + SSRF в исходящих колбэках мерчантам (`Http::withoutVerifying()`, без таймаута и allowlist) — подтверждено и самим `todo.md`.
- 2FA можно перебрать брутфорсом (нет rate limit, сравнение через `(int)`), что обесценивает второй фактор для админов.
- Финансовые шлюзы (deposit/withdraw webhook) защищены только статичными общими токенами со сравнением `!==` (не constant-time); владелец токена может зачислить произвольный USDT любому пользователю.
- Нет rate limiting на финансовых API, сбросе пароля и проверке 2FA.

**Immediate actions:** включить TLS-верификацию и таймауты в колбэках + SSRF-allowlist; добавить throttle на 2FA/login/reset; перейти на `hash_equals` для всех статичных токенов; валидировать `amount_received` в депозитном колбэке.

## Scope

Проверено: `routes/*` , middleware, контроллеры API/Admin, Gate/политики, `InvoiceService`, `WalletController`, `PayoutController`, `CallbackService`, аутентификация (login/2FA/reset), impersonation, модель `User`, `HandleInertiaRequests`, конфиги session/api/env, провайдеры Telescope/Horizon/Pulse.

Не проверено детально (рекомендуется отдельно): `WalletService`/`Transaction` (внутренние блокировки ledger), `OrderPoolingService`/`OrderMaker` (гонки при матчинге), `IntegrationInfrastructureController` (объём выгружаемых данных через статичный токен), Telegram-команды (`TelegramChatBotService`), загрузки/экспорты (Excel-инъекции), миграции БД (decimal/индексы).

## Attack Surface Map

| Область | Точки входа | Риск |
|---|---|---|
| Merchant/H2H API | `routes/api.php` (`api-access-token`) | High |
| Deposit/Withdraw webhooks | `/api/deposit/webhook`, `/api/withdraw/webhook` (статичные токены) | Critical |
| External invoice callback | `/api/v1/callbacks/invoice` (публичный, `X-Callback-Token`) | High |
| Исходящие колбэки | `CallbackService` (SSRF, TLS off) | High |
| Auth | login/2FA/reset | High |
| Admin | `routes/web.php` group `role:Super Admin` | Medium |
| Integration API | `/api/integration/v1/*` (один статичный токен) | Medium |

---

## Findings

### CRITICAL-001: SSRF + отключённая TLS-верификация в исходящих колбэках

Severity: Critical
Category: SSRF / Outbound Requests
File: `app/Services/OrderCallback/CallbackService.php:80-100`

```80:100:app/Services/OrderCallback/CallbackService.php
    private function sendCallback(
        string $url,
        array $payload,
        ?string $token,
        Model $model,
        string $type,
        ?int $callbackRevision = null,
    ): bool {
        $startedAt = microtime(true);
        $http = Http::withoutVerifying()->acceptJson();
        ...
        $response = $http->post($url, $payload);
```

Problem: `$url` берётся из `$order->callback_url ?? $order->merchant->callback_url` (управляется мерчантом). Нет allowlist, нет блокировки приватных/loopback/link-local адресов, **нет таймаута**, а `Http::withoutVerifying()` отключает проверку сертификата.

Attack scenario: мерчант ставит `callback_url = http://169.254.169.254/latest/meta-data/` или `http://127.0.0.1:6379` → сервер сам обращается к внутренним сервисам; тело ответа пишется в `CallbackLog.response_data` (эксфильтрация). Отдельно: `withoutVerifying()` позволяет MITM перехватить колбэк вместе с заголовком `Access-Token` (это `api_access_token` мерчанта). Отсутствие таймаута → подвисание воркеров очереди (DoS).

Impact: доступ к внутренней сети/метаданным облака, утечка токенов, исчерпание воркеров.
Affected role: merchant (в т.ч. скомпрометированный), MITM.
Recommended fix: включить TLS-верификацию; задать `->timeout(5)->connectTimeout(3)`; резолвить хост и запрещать приватные/loopback/link-local IP (анти-DNS-rebinding); ограничить схему `https`; проксировать исходящие запросы (как уже отмечено в `todo.md`).

Secure example:
```php
$http = Http::acceptJson()->timeout(5)->connectTimeout(3)->withOptions([
    'allow_redirects' => false,
]);
abort_unless($this->isPublicHttpsUrl($url), 422);
```

Test to add: колбэк на `http://127.0.0.1` / приватный IP отклоняется; запрос на медленный URL завершается по таймауту.
References: OWASP API Security 2023 — API7 (SSRF/Unsafe Consumption); ASVS V12/V5.

---

### CRITICAL-002: Депозитный webhook позволяет зачислить произвольный баланс по статичному токену

Severity: Critical
Category: Financial Integrity / Broken Authentication
File: `app/Http/Controllers/API/Deposit/DepositController.php:16-40`, `app/Http/Middleware/ApiDepositsAccessToken.php:20`

```16:34:app/Http/Controllers/API/Deposit/DepositController.php
    public function webhook(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'exists:users,email'],
            'amount' => ['required', 'numeric', 'min:1'],
            'transaction_id' => ['required', 'string'],
            ...
        ]);
        $user = User::where('email', $request->email)->first();
        services()->invoice()->deposit(walletID: $user->wallet->id, amount: Money::fromPrecision($request->amount, ...), balanceType: BalanceType::TRUST, ...);
```

Problem: эндпоинт защищён только общим статичным токеном (`config('api.api_deposit_token') !== $token`, **не constant-time**) и зачисляет произвольную сумму USDT любому пользователю по email. Это фактически «эмиссия денег» за одним секретом.

Attack scenario: утечка/таймин‑подбор `API_DEPOSIT_TOKEN` → атакующий шлёт `{email, amount}` и пополняет любой баланс. `transaction_id` даёт лишь защиту от повторов, но не от создания новых начислений.

Impact: прямая денежная потеря/эмиссия.
Affected assets: trust-балансы, ledger.
Recommended fix: `hash_equals` для сравнения; подпись HMAC от провайдера по телу+timestamp+nonce; сверка суммы/валюты с заранее созданным PENDING-инвойсом (как в `finishExternalDeposit`), а не доверие произвольному `amount`; IP-allowlist провайдера; rate limit.
Test to add: неверный/просроченный токен → 401; повтор `transaction_id` не дублирует; неподписанный запрос отклоняется.
References: OWASP API2 (Broken Authentication), API Financial integrity.

---

### HIGH-001: Брутфорс 2FA — нет rate limit и небезопасное сравнение кода

Severity: High
Category: 2FA / Broken Authentication
File: `app/Http/Controllers/Auth/Check2FACodeController.php:20-44`, route `routes/auth.php:56` (без `throttle`)

```36:42:app/Http/Controllers/Auth/Check2FACodeController.php
        $opt = $google2fa->getCurrentOtp($user->google2fa_secret);
        if ((int)$opt === (int) $request->input('one_time_password')) {
            session()->put('user_2fa_passed', true);
            return redirect()->route('dashboard');
        }
```

Problem: на `auth/2fa/validate` нет ограничения попыток; сравнение через `(int)` теряет ведущие нули и сводит проверку к числовому равенству. После ввода пароля (сессия уже аутентифицирована) код из 6 цифр можно перебирать.

Impact: обход второго фактора при скомпрометированном пароле, в т.ч. для админов.
Recommended fix: добавить `throttle:5,1` + `RateLimiter` с блокировкой и логом; использовать `$google2fa->verifyKey($secret, $code)` (constant-time, окно), строковое сравнение; инвалидация после N неудач.
Test to add: 6+ неверных кодов → 429; код с ведущим нулём принимается корректно и только он.
References: OWASP ASVS V2 (Authentication), API2.

---

### HIGH-002: Статичные общие токены со сравнением `!==` (не constant-time)

Severity: High
Category: API Keys & Tokens
Files: `app/Http/Middleware/ApiDepositsAccessToken.php:20`, `ApiWithdrawalsAccessToken.php:20`, `ApiBotAccessToken.php:20`

Problem: сравнение `config(...) !== $token` уязвимо к таймин-атакам и работает с единым общим секретом без ротации/скоупов/срока. `WithdrawController::webhook` по такому токену меняет статус любого pending-инвойса вывода.

Recommended fix: `hash_equals`; индивидуальные подписанные колбэки (HMAC+timestamp+event_id); ротация; per-provider токены.
Test to add: неверный токен → 401; равное по длине, но иное значение отклоняется.
References: OWASP API2/API8.

---

### HIGH-003: Внешний депозитный колбэк доверяет `amount_received`

Severity: High
Category: Financial Integrity / Webhook
File: `app/Http/Controllers/API/Deposit/DepositController.php:74-84` + `InvoiceService::finishExternalDeposit:306-336`

```319:332:app/Services/Invoice/InvoiceService.php
            $finalAmount = $amountReceived ?? $invoice->amount;
            $invoice->update(['status' => InvoiceStatus::SUCCESS, ...]);
            services()->wallet()->giveToBalance(walletID: ..., amount: $finalAmount, ...);
```

Problem: сумма зачисления берётся из тела колбэка (`amount_received`) без сверки с суммой созданного инвойса. Защита от повтора есть (статус PENDING), но не от завышения суммы скомпрометированным/поддельным провайдером (токен `X-Callback-Token` сравнивается корректно через `hash_equals`, но это единственный барьер).

Recommended fix: зачислять `min(amountReceived, invoice->amount)` или строго `invoice->amount`; логировать расхождения; HMAC-подпись провайдера.
Test to add: колбэк с завышенным `amount_received` не зачисляет больше суммы инвойса.
References: API Financial integrity; ASVS V11.

---

### HIGH-004: Отсутствие rate limiting на чувствительных эндпоинтах

Severity: High
Category: Rate Limiting
Files: `routes/api.php` (вся группа `api-access-token`, payout/withdraw/order), `routes/auth.php:28` (`forgot-password`), `auth.php:56` (2FA validate)

Problem: на создании выплат/ордеров/выводов, сбросе пароля и проверке 2FA нет throttling. `forgot-password` дополнительно даёт enumeration (разные сообщения для существующего/несуществующего email) и mail-bombing.

Recommended fix: `RateLimiter::for(...)` per-token/merchant/IP на финансовых маршрутах; `throttle` на `forgot-password` и 2FA; единое нейтральное сообщение reset.
References: OWASP API4 (Unrestricted Resource Consumption), ASVS V2/V11.

---

### HIGH-005: Telegram webhook fail-open при отсутствии секрета

Severity: High
Category: Webhook Security
File: `app/Http/Middleware/VerifyTelegramSecretToken.php:12-20`

```12:20:app/Http/Middleware/VerifyTelegramSecretToken.php
        $secret = config('telegram.webhook_secret');
        if ($secret) {
            $header = $request->header('X-Telegram-Bot-Api-Secret-Token');
            if (! $header || ! hash_equals($secret, $header)) { abort(403); }
        }
        return $next($request);
```

Problem: если `webhook_secret` не задан — middleware пропускает любой запрос (CSRF для этого пути отключён в `bootstrap/app.php`). Любой может слать апдейты в `Telegram::commandsHandler`.

Recommended fix: при пустом секрете в проде — `abort(503)`/жёсткий отказ; обязательная конфигурация секрета; дополнительно сверять `update` с Telegram.
References: OWASP API2/API8.

---

### HIGH-006: Impersonation — обход 2FA, нет аудита/причины/срока

Severity: High
Category: Impersonation
File: `routes/web.php:590-604`

```590:600:routes/web.php
        Route::post('/impersonate/{user}', function (User $user) {
            $currentUser = request()->user();
            if ($currentUser?->canImpersonate()) {
                $currentUser->impersonate($user);
                if ($user->google2fa_secret) {
                    session()->put('user_2fa_passed', true);
                }
                return redirect()->route('dashboard');
```

Problem: при входе под пользователем принудительно ставится `user_2fa_passed=true` (обход 2FA цели). Нет журналирования (кто/кого/когда/зачем), нет причины и таймера; нет ограничения на критичные финансовые операции в режиме имперсонизации. Положительно: `canBeImpersonated()` запрещает имперсонизацию Super Admin.

Recommended fix: append-only аудит (реальный актор + цель + IP + reason + correlation id) на start/stop; обязательная причина; лимит времени; запрет денежных операций под имперсонизацией; не выставлять `user_2fa_passed` за пользователя.
References: ASVS V7 (Logging), business logic abuse.

---

### MEDIUM-001: Внешний HTTP-вызов внутри DB-транзакции (auto-withdrawal)

Severity: Medium
File: `app/Services/Invoice/InvoiceService.php:56-109`

Problem: `Http::post(...)` к withdrawal-сервису выполняется внутри `Transaction::run` с `lockForUpdate` на кошельке — блокировки удерживаются на время сетевого IO; при откате после уже инициированного внешнего вывода возможна рассинхронизация (деньги отправлены, баланс не списан/инвойс откатан).
Recommended fix: вынести внешний вызов из транзакции; двухфазная схема (PENDING → внешний вызов → подтверждение колбэком) как в `createExternalDeposit`.

### MEDIUM-002: `api_access_token` хранится в открытом виде и кэшируется по сырому токену

Severity: Medium
File: `app/Http/Middleware/ApiAccessToken.php:21-33`, `User` `$fillable`

Problem: токен ищется `where('api_access_token', $token)` (хранение в БД в открытом виде) и кэшируется на 24ч с ключом, содержащим сам токен. Утечка дампа БД/кэша = немедленная компрометация.
Recommended fix: хранить хэш токена; кэш-ключ — по хэшу; рассмотреть переход на Sanctum c хэшированными токенами и абилками.

### MEDIUM-003: Mass assignment токенов
Severity: Medium
File: `app/Models/User.php:116-117` (`apk_access_token`, `api_access_token` в `$fillable`)
Problem: токены массово присваиваемы; любой `update()`/DTO, прокидывающий эти ключи, может их перезаписать. Сейчас admin-формы используют `validated()`, но риск регресса высок.
Recommended fix: убрать из `$fillable`, задавать явным setter-методом.

### MEDIUM-004: Email enumeration на сбросе пароля
Severity: Medium
File: `app/Http/Controllers/Auth/PasswordResetLinkController.php:43-49` (см. также HIGH-004).

### MEDIUM-005: Отсутствует/не настроен CORS-конфиг
Severity: Medium
Detail: `config/cors.php` отсутствует → дефолты Laravel (`allowed_origins: *` для `api/*`). API на заголовочных токенах, но стоит явно ограничить источники и проверить `supports_credentials=false`.

### MEDIUM-006: Неполный аудит-лог действий
Severity: Medium
Detail: подтверждено `todo.md` («добавить полное логирование действий всех пользователей»). Критичные действия (admin deposit/withdraw на чужой кошелёк `routes/web.php:547-548`, `reset2fa`, ban, изменение комиссий) идут без append-only журнала с diff/актором/IP.

---

### LOW / INFO

- **LOW-001**: `.env.example` с `APP_DEBUG=true`, `APP_ENV=local`, без `SESSION_SECURE_COOKIE=true`. Убедиться, что прод переопределяет (`APP_DEBUG=false`, secure cookie). `session.same_site=lax`, `http_only=true` — ок.
- **LOW-002**: Полный `$payload` логируется в `Log::error` депозитного колбэка — возможна утечка чувствительных данных в логи.
- **INFO-001**: Telescope/Horizon/Pulse корректно закрыты гейтом `Super Admin`; Telescope скрывает чувствительные заголовки и фильтрует записи в проде — хорошо.
- **INFO-002**: BOLA-контроль в API сделан корректно через `Gate::authorize('api-access-to-merchant'/'access-to-order')` с проверкой владения; депозиты/выводы используют `lockForUpdate` и проверки статус-машины (защита от двойного списания/повтора) — хорошая база.

---

## Money Loss Risks
- CRITICAL-002 (эмиссия баланса по статичному токену), HIGH-003 (завышение `amount_received`), MEDIUM-001 (рассинхрон при авто-выводе), HIGH-002 (подмена статуса вывода по общему токену).

## Authorization Matrix Problems
- Объектная авторизация в API в целом корректна. Основной пробел — не объектные риски: статичные общие токены (deposit/withdraw/integration) дают широкий доступ без скоупов; impersonation обходит 2FA.

## Route Risk Table (выжимка)
| URI | Auth | Rate limit | Idempotency | Money | Замечание |
|---|---|---|---|---|---|
| `POST /api/payouts` | merchant token | нет | pooling cache | да | нет throttle |
| `POST /api/wallet/withdraw` | merchant token | нет | нет | да | внешний вызов в транзакции |
| `POST /api/deposit/webhook` | static token `!==` | нет | по `transaction_id` | да | CRITICAL-002 |
| `POST /api/withdraw/webhook` | static token `!==` | нет | статус-машина | да | HIGH-002 |
| `POST /api/v1/callbacks/invoice` | `X-Callback-Token` hash_equals | нет | статус PENDING | да | доверяет `amount_received` |
| `POST /telegram/webhook` | secret (fail-open) | нет | — | нет | HIGH-005 |
| `POST forgot-password` | guest | нет | — | нет | enumeration |
| `POST auth/2fa/validate` | auth | нет | — | нет | брутфорс 2FA |

## Recommended Remediation Plan

**Fix immediately**
1. Колбэки: включить TLS-верификацию, таймауты, SSRF-allowlist/прокси (CRITICAL-001).
2. Депозитный колбэк: HMAC-подпись + сверка суммы с инвойсом; `hash_equals` для всех статичных токенов (CRITICAL-002, HIGH-002/003).
3. Throttle на 2FA/login/forgot-password + `verifyKey` (HIGH-001/004).

**Fix this week**
4. Telegram webhook fail-closed (HIGH-005).
5. Аудит-лог + запрет 2FA-обхода для impersonation (HIGH-006, MEDIUM-006).
6. Вынести внешний вызов из транзакции авто-вывода (MEDIUM-001).

**Hardening**
7. Хэширование `api_access_token`, убрать токены из `$fillable`, опубликовать `config/cors.php`, нейтральные сообщения reset, ограничить логирование payload.

## Suggested Security Tests
- Колбэк на приватный IP/таймаут отклоняется; депозитный колбэк с завышенной суммой не зачисляет лишнее; повтор `transaction_id`/replay не дублирует ledger; 6+ неверных 2FA → 429; неверный/таймин-эквивалентный статичный токен → 401; merchant A не видит сделки merchant B; impersonation пишет реального актора и не выставляет `user_2fa_passed`.

---

Хотите, чтобы я сразу подготовил патчи по разделу «Fix immediately» (TLS+timeout+SSRF в `CallbackService`, `hash_equals` в middleware, throttle на 2FA/reset)? Это безопасные точечные изменения, не ломающие текущие потоки.