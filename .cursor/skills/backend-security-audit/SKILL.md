---
name: backend-security-audit
description: Performs strict backend security audits for the P2P CTI Laravel fintech platform. Use when the user asks for full backend security audit, API audit, financial integrity audit, admin panel audit, integration audit, OWASP ASVS review, OWASP API Security review, BOLA/IDOR review, webhook security review, fintech threat modeling, race condition review, or privilege escalation analysis.
---

# Backend Security Audit

## Purpose

Audit the Laravel 11 backend of the P2P CTI financial platform as a senior application security engineer, Laravel architect, and fintech auditor.

Use OWASP ASVS 5.0.0 as the baseline for web application security controls and OWASP API Security Top 10 2023 for API risks, including BOLA, broken authentication, broken object property level authorization, unrestricted resource consumption, and unsafe consumption of APIs.

Assume the frontend is compromised. Backend controls must remain safe when users modify request payloads, replay requests, call APIs directly, swap object IDs, repeat webhooks, run parallel requests, and try to bypass roles.

## Modes

Infer the mode from the user's request:

- Full audit: complete backend review.
- API audit: REST/API, Sanctum, idempotency, rate limits, BOLA, auth, object access.
- Financial integrity audit: balances, deals, reserves, deposits, payouts, commissions, status transitions, ledger, idempotency, race conditions.
- Admin panel audit: roles, permissions, impersonation, destructive actions, exports, dashboards, monitoring tools.
- Integration audit: Telegram, Telegram Bot/Mini App, Zoho, Sentry, provider callbacks, merchant webhooks, callback signatures.

## Required Workflow

1. Map the project structure and backend entry points.
2. Identify routes, controllers, middleware, requests, policies, models, services, actions, jobs, listeners, observers, console commands, providers, configs, migrations, seeders, `bootstrap/app.php`, dependency manifests, `.env.example`, and storage/export surfaces.
3. Build a threat model for Guest, authenticated user, Trader, Merchant, Admin, Super Admin, support operator, impersonating admin, external provider, Telegram user, mobile app/device, queue worker, and cron process.
4. Audit routes and middleware first, then auth/authz, API object authorization, financial operations, webhooks, jobs/queues, configs, storage/exports/logs, and dependencies.
5. Classify findings as Critical, High, Medium, Low, or Info.
6. For every finding include file, code area, problem, risk, attack scenario, affected role, affected assets, recommended fix, secure code example, test to add, and OWASP references.
7. Separately highlight money loss risks, privilege escalation, BOLA/IDOR, race conditions, replay attacks, webhook spoofing, sensitive data leakage, unsafe impersonation, and audit log gaps.

Read [CHECKLIST.md](CHECKLIST.md) before starting an audit and use it as the required control matrix.

## Severity Model

Critical findings can cause money theft, balance mutation, unauthorized payout, admin takeover, 2FA bypass, mass sensitive data leakage, RCE, SQL injection over sensitive data, webhook spoofing that changes financial state, BOLA over deals/balances/requisites, double spend, or key authorization bypass.

High findings include access to another tenant's deals, merchant/trader/admin privilege escalation, unauthorized export, unsafe impersonation, stored XSS in admin, SSRF, predictable tokens, missing auth/API rate limits, unsafe uploads, sensitive data in logs, exposed Horizon/Telescope/Pulse, and mass assignment of sensitive fields.

Medium findings include incomplete validation, missing security headers, weak password/session settings, missing audit logs, excessive API response data, risky config defaults, insufficient resource limits, and missing transaction boundaries.

Low findings include minor information disclosure, non-sensitive debug leftovers, and weak internal conventions.

Info findings are hardening, observability, testing, documentation, or architecture recommendations.

## Output Format

Use this report structure:

````markdown
# Backend Security Audit Report

## Executive Summary

- Overall risk: Critical/High/Medium/Low
- Critical findings: N
- High findings: N
- Medium findings: N
- Low findings: N
- Main risks:
- Immediate actions:

## Scope

Checked:
- routes
- controllers
- requests
- services
- models
- policies
- jobs
- migrations
- config
- integrations
- queues
- storage
- dependencies

Not checked:
- ...

## Attack Surface Map

| Area | Entry Points | Risk |
|---|---|---|
| API | routes/api.php | High |
| Admin | routes/web.php admin group | Critical |
| Webhooks | /webhook/* | Critical |
| Queue | app/Jobs | High |

## Findings

### CRITICAL-001: Title

Severity: Critical  
Category: BOLA / Financial Integrity / Auth / Webhook / etc.  
File: path/to/file.php  
Code:

```php
...
```

Problem:

Attack scenario:

Impact:

Affected role:

Affected data/assets:

Recommended fix:

Secure example:

Test to add:

References:
- OWASP ASVS category
- OWASP API Security category

## Money Loss Risks

## Authorization Matrix Problems

## Route Risk Table

## Dependency Risks

## Recommended Remediation Plan

### Fix immediately

### Fix this week

### Hardening

## Suggested Security Tests
````

If evidence is incomplete, mark the finding as `Needs manual verification` and explain the risk clearly.
