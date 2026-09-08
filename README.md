# WL Traders

**Своя P2P-площадка для мерчантов и трейдеров:** приём платежей, выплаты, распределение заявок, споры, комиссии и расчёты в USDT. Данные и правила работы остаются у владельца.

![Панель управления WL Traders](https://raw.githubusercontent.com/niiikkid/wl.traders.p2p/682c3cc/docs/images/wl-traders-dashboard.png)

- **Мерчантам:** API, платежи и выплаты, статусы и callback-уведомления.
- **Трейдерам:** реквизиты, лимиты, графики, обработка сделок и споров.
- **Команде процессинга:** пользователи, комиссии, балансы, антифрод, 2FA и отчёты.
- USDT TRC20, Telegram и Android-приложение для работы с SMS.

## Установка

Нужен **компьютер или VPS**, а не обычный хостинг сайтов: минимум 2 ядра, 4 ГБ памяти и 20 ГБ свободного места, доступ в интернет. Все службы приложения запускаются в Docker; отдельно ставить PHP, MySQL или Node.js не нужно.

### На сервере Ubuntu 22.04+ / Debian 12+

**1.** Откройте Терминал (Mac/Linux) или PowerShell (Windows) на своём компьютере и подключитесь к серверу:

```bash
ssh -L 8787:127.0.0.1:8787 root@IP_СЕРВЕРА
```

Вместо `IP_СЕРВЕРА` укажите адрес из панели хостера. Этот способ защищает ввод пароля и ключей установщика через SSH.

**2.** В открывшемся подключении выполните:

```bash
(dir=$(mktemp -d) && trap 'rm -rf "$dir"' EXIT && curl -fsSL https://raw.githubusercontent.com/niiikkid/wl.traders.p2p/main/install.sh -o "$dir/install.sh" && bash "$dir/install.sh")
```

На чистой Ubuntu 22.04+/Debian 12+ установщик сам добавит Docker и Python. На другом Linux заранее установите [Docker Engine с Compose](https://docs.docker.com/engine/install/) и Python 3.10+; команда запуска та же.

**3.** Откройте показанную ссылку `http://127.0.0.1:8787/?token=…` в браузере **своего компьютера**. Выберите адрес сайта, задайте пароль администратора и нажмите **«Установить»**. Не закрывайте SSH до завершения.

### На Mac

Установите и запустите [Docker Desktop](https://www.docker.com/products/docker-desktop/), установите [Python 3.10+](https://www.python.org/downloads/). В Терминале выполните:

```bash
(dir=$(mktemp -d) && trap 'rm -rf "$dir"' EXIT && curl -fsSL https://raw.githubusercontent.com/niiikkid/wl.traders.p2p/main/install.sh -o "$dir/install.sh" && bash "$dir/install.sh")
```

Откройте ссылку из терминала. Для знакомства выберите **«На этом компьютере»** — сайт будет доступен только вам по `http://localhost:8080`. `sudo` на Mac не нужен.

### На Windows

Установите [Docker Desktop](https://www.docker.com/products/docker-desktop/) с WSL 2 и режимом **Linux containers**, затем [Python 3.10+](https://www.python.org/downloads/windows/) с галочкой **Add Python to PATH**. Если установка попросит перезагрузку — выполните её. Запустите Docker Desktop и откройте новый PowerShell:

```powershell
$dir = Join-Path $env:TEMP ([guid]::NewGuid().ToString())
New-Item -ItemType Directory -Path $dir -ErrorAction Stop | Out-Null
try {
    Invoke-WebRequest -UseBasicParsing -ErrorAction Stop https://raw.githubusercontent.com/niiikkid/wl.traders.p2p/main/install.ps1 -OutFile "$dir\install.ps1"
    powershell -ExecutionPolicy Bypass -File "$dir\install.ps1"
} finally { Remove-Item -LiteralPath $dir -Recurse -Force }
```

Откройте ссылку из PowerShell и выберите **«На этом компьютере»**. Настройка политики запуска действует только для этого процесса.

### Какой адрес выбрать

| Режим | Для чего |
|---|---|
| На этом компьютере | Знакомство с продуктом; доступ только с этого компьютера. |
| IP-адрес сервера | Проверка без домена. Откройте выбранный порт сайта у хостера. |
| Домен | Создайте A-запись на IP сервера. Для HTTPS выберите Cloudflare, вставьте Origin Certificate и его ключ, включите **Proxied** и **Full (strict)**. |

**HTTP не шифрует пароли и данные. Для реальных платежей используйте HTTPS.** При режиме Cloudflare нужны порты 80/443; мастер установки не нужно открывать в интернет. Обычный домен без Cloudflare работает по HTTP.

Логин после установки — **`admin`**, пароль — заданный вами. Telegram, TronGrid и IP Geolocation можно пропустить; соответствующие функции без них ограничены. Почта и Sentry не настраиваются.

## После установки

Папка по умолчанию: `/opt/wl-traders` на сервере, `wl-traders` в домашней папке на Mac/Windows. Внутри неё:

```bash
docker compose ps                # состояние служб
docker compose stop              # остановить, сохранив данные
docker compose up -d --wait       # снова запустить
docker compose run --rm backup --once  # сделать резервную копию
```

Ежедневные копии включены по умолчанию и сохраняются в `backups` внутри папки установки. Данные хранятся отдельно от контейнеров. **Не выполняйте `docker compose down -v`** — эта команда удаляет базу и файлы. Сохраняйте также папку установки с её настройками и секретами; копии только на том же диске не защищают от потери сервера.

Если установка прервалась, исправьте указанную причину и повторите запуск с той же папкой. Не удаляйте её и Docker-тома. Повторный запуск установщика — не способ переноса старой установки без Docker; для неё нужен отдельный перенос базы, файлов и ключа приложения.

На компьютере Docker Desktop должен работать; после выхода из системы/сна сайт может быть недоступен. Для постоянной работы используйте VPS.

[Что проверено и ограничения Windows / Cloudflare](docs/installer-verification.md).

## Разработка и связанные проекты

Стек: Laravel 11 / PHP 8.4, Vue 3 / Inertia / Vite, MySQL 8.4, Redis, Nginx. Код можно получить через **Code → Download ZIP** или `git clone https://github.com/niiikkid/wl.traders.p2p.git`; затем запустить `bash install.sh` или `install.ps1` из папки проекта.

- [P2P App](https://github.com/niiikkid/p2p-app) — Android-приложение для SMS.
- [Payment System](https://github.com/niiikkid/payment.system) — криптопроцессинг.

Не публикуйте пароли, токены, резервные копии и `.env` в Git.
