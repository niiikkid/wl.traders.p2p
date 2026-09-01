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
- 50 ГБ NVMe;
- доступ по SSH от `root`;
- открытый TCP-порт 80 для работы приложения.

Он сам устанавливает PHP, Nginx, MySQL, Redis, Composer и Node.js. GitHub и `git` на production-сервере не используются.

### Локальная разработка

- PHP 8.3+ с расширениями `bcmath`, `gmp`, `mbstring`
- Composer
- Node.js 18+ и npm
- MySQL 8+
- Redis

## Установка на production-сервер

Установщик лежит в этом же репозитории: `install.sh` и папка `installer/`. Отдельный репозиторий для него не нужен.

### 1. Передайте текущую копию проекта на сервер

Соберите архив на локальном компьютере из папки проекта. Команда не включает `.env`, Git-историю и локальные зависимости:

```bash
cd /path/to/p2p.processing.traders

tar \
  --exclude='.git' \
  --exclude='.env' \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='public/build' \
  --exclude='._*' \
  -czf /tmp/wl-traders.tar.gz .

scp /tmp/wl-traders.tar.gz root@SERVER_IP:/root/
```

Подключитесь к серверу и распакуйте архив:

```bash
ssh root@SERVER_IP
mkdir -p /root/wl-traders-source
tar -xzf /root/wl-traders.tar.gz -C /root/wl-traders-source
cd /root/wl-traders-source
chmod +x install.sh
sudo ./install.sh
```

Скрипт выведет одноразовую ссылку вида:

```text
http://SERVER_IP:8787/?token=...
```

Откройте её с доверенного компьютера. Это временная панель настройки: укажите IP приложения, пароль администратора, параметры базы данных и, если нужны, ключи Telegram, TronGrid и IP Geolocation. Панель работает по HTTP только на время установки; не передавайте эту ссылку другим.

### 2. Что делает установщик

- копирует проект в `/var/www/wl-traders`;
- устанавливает и настраивает Nginx, PHP, MySQL, Redis, Composer и Node.js;
- создаёт отдельную MySQL-базу и пользователя;
- формирует production `.env`, генерирует ключ приложения и запускает `system:install`;
- собирает frontend;
- настраивает Horizon как службу `wl-traders-horizon`;
- добавляет Laravel scheduler и снимки Horizon в cron;
- создаёт `storage:link`, кеши Laravel, лимиты PHP/Nginx и исправляет буферы FastCGI;
- при выбранной опции включает firewall, создаёт 2 ГБ swap и ежедневные локальные бэкапы MySQL и `storage` в `/var/backups/wl-traders`;
- открывает установленный проект по адресу, указанному в панели, и выключает временную панель.

Установщик останавливается, если в целевой папке уже есть `.env` либо выбранная база содержит таблицы: существующие данные не удаляются.

### Интеграции

- **Telegram** — опционально, но нужен для Telegram-функций.
- **TronGrid** — опционально; без него не будут полноценно работать инвойсы USDT TRC20.
- **IP Geolocation** — опционально; без него не сработает часть гео-функций.
- Почта и Sentry установщиком не настраиваются.

После установки войдите в админ-панель под логином `admin` и паролем, заданным в панели. Для IP-адреса используется HTTP. HTTPS добавляется позже, когда появится домен.

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
