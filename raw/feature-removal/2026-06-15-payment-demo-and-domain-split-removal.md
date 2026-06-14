# Payment Demo And Domain Split Removal

> Source: User conversation + code changes, 2026-06-15
> Collected: 2026-06-15
> Published: 2026-06-15

## Context

The public hosted payment form (`/payment/{order:uuid}`, `PaymentLinkController`) had already been removed earlier. Only a demo payment page (`/payment/demo`) and split-domain configuration (`PAYMENT_FORM_URL`) remained. Product uses H2H API only; demo and domain split were dead weight.

## Removed (shipped 2026-06-15)

### Backend

- `app/Http/Controllers/PaymentDemoController.php`
- Routes `payment.demo.show`, `payment.demo.dispute.store`, `payment.demo.payment-detail.store` (`GET/POST /payment/demo*`)
- `app/Http/Middleware/EnsurePaymentDomain.php`
- `app/Http/Middleware/EnsureBackofficeDomain.php`
- `config/domains.php`
- Middleware aliases `payment.domain` and `backoffice.domain` from `bootstrap/app.php`
- `backoffice.domain` removed from `routes/web.php` and `routes/auth.php` (middleware was a no-op when payment host equaled `APP_URL`)

### Frontend

- `resources/js/Pages/PaymentLink/**` (Index, stages, DemoSwitcher, etc.)
- `resources/js/Layouts/PaymentLayout.vue`
- Demo card «Демонстрационная платежная форма» in `resources/js/Pages/Integration/Index.vue`

### Configuration

- `PAYMENT_FORM_URL` and `PAYMENT_LEGACY_REDIRECT_STATUS` from `.env.example` and `.env`

### Post-change commands

- `php artisan optimize`
- `php artisan ziggy:generate resources/js/ziggy-routes.js`
- `vendor/bin/pint --dirty --format agent`

## Not affected

- H2H API (`api/h2h/order*`)
- Trader/leader deposit `payment_url` from external invoice providers
- Admin/trader `Payment/Index` (payments list in backoffice)
- `POST /payment/{order}/callback/resend` (merchant callback resend, unrelated to public form)

## Safety rationale

- No production payment flow depended on demo routes.
- `EnsureBackofficeDomain` only blocked backoffice on a separate payment host; without `PAYMENT_FORM_URL` it never restricted access.
- No database migrations required.
