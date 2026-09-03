# WL Traders

[![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel 11](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![Vue 3](https://img.shields.io/badge/Vue-3-42B883?logo=vuedotjs&logoColor=white)](https://vuejs.org/)
[![MySQL 8](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Redis](https://img.shields.io/badge/Redis-queues-DC382D?logo=redis&logoColor=white)](https://redis.io/)

**WL Traders** — self-hosted платформа для мерчантов и трейдеров: приём P2P-платежей, выплаты, распределение заявок, споры, комиссии и расчёты в USDT.

![Панель управления WL Traders](https://raw.githubusercontent.com/niiikkid/wl.traders.p2p/682c3cc/docs/images/wl-traders-dashboard.png)

## Для чего нужна платформа

- **Мерчанту** — подключить приём платежей и выплаты через API, видеть статусы операций и получать callback-уведомления.
- **Трейдеру** — управлять реквизитами, лимитами и графиками работы, обрабатывать сделки, выплаты и споры.
- **Команде процессинга** — контролировать пользователей, оборот, доход, комиссии, антифрод, финансы и работу всей площадки из одной панели.

Платформа подходит как основа для собственной P2P-площадки: данные, правила обработки и инфраструктура остаются под контролем владельца.

## Возможности

- приём фиатных P2P-платежей и проведение выплат;
- H2H API для мерчантов, callback-и и журнал запросов;
- кабинеты администратора, мерчанта, трейдера, тимлидера и поддержки;
- распределение заявок по реквизитам трейдеров;
- лимиты, расписания и статистика платёжных реквизитов;
- споры, чеки, банковские выписки и ручная обработка;
- комиссии, внутренние балансы и учёт в USDT;
- пополнение через USDT TRC20;
- антифрод, 2FA, журнал действий и история входов;
- интеграция с Android-приложением, SMS и Telegram;
- очереди, мониторинг и отчёты по работе системы.

## Стек

- **Backend:** PHP 8.3, Laravel 11, Sanctum, Horizon
- **Frontend:** Vue 3, Inertia.js, Vite, Tailwind CSS, DaisyUI
- **Данные:** MySQL 8, Redis
- **Мониторинг:** Laravel Pulse, Telescope, Nightwatch, Sentry

## Требования

### Production-сервер

Установщик рассчитан на **чистый Ubuntu 26.04** или аналогичный свежий Ubuntu-сервер. Минимальная конфигурация:

- 2 vCPU 3.3 ГГц;
- 4 ГБ RAM;
- 20 ГБ SSD/NVMe;
- доступ по SSH от `root`;
- открытые TCP-порты 80 и 443 при установке с доменом (только 80 при доступе по IP).

Он сам устанавливает PHP, Nginx, MySQL, Redis, Composer и Node.js. `git` на production-сервере не нужен.

### Локальная разработка

- PHP 8.3+ с расширениями `bcmath`, `gmp`, `mbstring`
- Composer
- Node.js 18+ и npm
- MySQL 8+
- Redis

## Установка на production-сервер

Установщик рассчитан на **чистую Ubuntu 26.04**. Подключитесь к серверу как `root` и запустите:

```bash
curl -fsSL https://raw.githubusercontent.com/niiikkid/wl.traders.p2p/main/install.sh | bash
```

Скрипт проверит ОС, свободный порт и отсутствие другой запущенной установки, затем покажет временную ссылку:

```text
http://SERVER_IP:8787/?token=...
```

Откройте её со своего компьютера. Панель проведёт по шести коротким шагам. Можно выбрать:

- **домен** — установщик проверит DNS и откроет продукт по домену. Далее можно выбрать **«Без Cloudflare»** (сайт по HTTP) или **«С Cloudflare»** (сайт сразу по HTTPS);
- **IP-адрес** — продукт будет доступен по HTTP.

Для домена без Cloudflare создайте DNS-запись типа `A`, направленную на IP сервера. Для варианта «С Cloudflare» домен должен быть добавлен в Cloudflare с включённым **Proxied** (оранжевое облако), а также нужен **Origin Certificate** (SSL/TLS → Origin Certificates): его сертификат и ключ вставляются в панель установщика, а в SSL/TLS выбирается режим **Full (strict)**. Продукт сразу работает по HTTPS, а Cloudflare принимает трафик перед сервером и скрывает IP сервера.

Безопасные значения для базы, firewall, swap и бэкапов уже выбраны. Дополнительные ключи Telegram, TronGrid и IP Geolocation можно оставить пустыми.

Ссылка защищена случайным токеном, перестаёт принимать новую установку через 45 минут и автоматически закрывается после завершения. Панель временно работает по HTTP, поэтому никому не передавайте ссылку.

### Интеграции

- **Telegram** — опционально, но нужен для Telegram-функций.
- **TronGrid** — опционально; без него не будут полноценно работать инвойсы USDT TRC20.
- **IP Geolocation** — опционально; без него не сработает часть гео-функций.
- Почта и Sentry установщиком не настраиваются.

После установки войдите в админ-панель под логином `admin` и паролем, заданным в панели. HTTPS используется при выборе «С Cloudflare»; для «Без Cloudflare» и доступа по IP — HTTP.

## Быстрый запуск для разработки

```bash
git clone git@github.com:niiikkid/wl.traders.p2p.git
cd wl.traders.p2p

composer install
npm ci
cp .env.example .env
php artisan key:generate
```

Укажите подключение к MySQL и Redis в `.env`, затем выполните:

```bash
php artisan migrate
npm run build
php artisan serve
```

В отдельных процессах запустите очередь и frontend для разработки:

```bash
php artisan horizon
npm run dev
```

> Для рабочего сервера дополнительно нужно настроить веб-сервер, HTTPS, планировщик Laravel, постоянный запуск очередей и все используемые интеграции. Тестовые сидеры создают пользователей с известными паролями — не используйте их в production.

## Основные настройки

Конфигурация хранится в `.env`. Перед запуском проверьте:

- подключение к MySQL и Redis;
- URL приложения;
- токены API и webhook-секреты;
- Telegram-бота, если нужны Telegram-функции;
- TronGrid для полноценных USDT TRC20-инвойсов;
- IP Geolocation для гео-функций;
- параметры мобильного приложения и обработки SMS.

Почта и Sentry в текущей конфигурации не используются.

Не добавляйте реальные токены и пароли в Git.

## Связанные проекты

- [P2P App](https://github.com/niiikkid/p2p-app) — Android-приложение для автоматики и обработки SMS.
- [Payment System](https://github.com/niiikkid/payment.system) — связанный криптопроцессинг.

## Поисковые ключи

`P2P payment processing` · `self-hosted P2P platform` · `P2P acquiring` · `merchant payment gateway` · `trader platform` · `merchant API` · `payment orchestration` · `fiat payments` · `USDT processing` · `USDT TRC20` · `P2P payments` · `P2P payouts` · `платформа для мерчантов` · `платформа для трейдеров` · `P2P процессинг` · `приём P2P платежей`
