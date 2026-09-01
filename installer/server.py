#!/usr/bin/env python3
"""Temporary one-time web installer for WL Traders on a fresh Ubuntu server."""

from __future__ import annotations

import argparse
import json
import os
import re
import secrets
import shlex
import shutil
import socket
import subprocess
import threading
import time
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any
from urllib.parse import parse_qs, urlparse

REPO_ROOT = Path(__file__).resolve().parent.parent
STATE_LOCK = threading.Lock()
STATE: dict[str, Any] = {
    "phase": "ready",
    "message": "Установщик готов",
    "logs": [],
    "app_url": None,
    "error": None,
}


def add_log(message: str) -> None:
    clean = message.rstrip()
    if not clean:
        return
    with STATE_LOCK:
        STATE["logs"].append(clean)
        STATE["logs"] = STATE["logs"][-500:]


def set_state(**values: Any) -> None:
    with STATE_LOCK:
        STATE.update(values)


def public_ip() -> str:
    try:
        output = subprocess.check_output(["hostname", "-I"], text=True, timeout=5)
        for value in output.split():
            if re.fullmatch(r"\d{1,3}(?:\.\d{1,3}){3}", value) and not value.startswith("127."):
                return value
    except (OSError, subprocess.SubprocessError):
        pass
    return "127.0.0.1"


def quote(value: str | Path) -> str:
    return shlex.quote(str(value))


def validate_identifier(value: str, field: str) -> str:
    if not re.fullmatch(r"[A-Za-z0-9_]+", value):
        raise ValueError(f"{field}: разрешены только латинские буквы, цифры и подчёркивание")
    return value


def validate_path(value: str) -> Path:
    if not re.fullmatch(r"/[A-Za-z0-9_./-]+", value):
        raise ValueError("Путь установки может содержать только буквы, цифры, /, ., _ и -")
    path = Path(value)
    if not path.is_absolute() or path == Path("/") or len(path.parts) < 3:
        raise ValueError("Путь установки должен быть абсолютным, например /var/www/wl-traders")
    return path


def validate_url(value: str) -> str:
    parsed = urlparse(value)
    if parsed.scheme not in {"http", "https"} or not parsed.netloc:
        raise ValueError("Адрес приложения должен начинаться с http:// или https://")
    if parsed.username or parsed.password or not parsed.hostname or not re.fullmatch(r"[A-Za-z0-9.-]+", parsed.hostname):
        raise ValueError("В адресе приложения должен быть корректный IP или домен")
    return value.rstrip("/")


def dotenv_value(value: str) -> str:
    if value in {"null", "true", "false"} or re.fullmatch(r"[A-Za-z0-9_./:@+-]+", value):
        return value
    escaped = value.replace("\\", "\\\\").replace('"', '\\"').replace("$", "\\$")
    return '"' + escaped + '"'


def write_env(target: Path, settings: dict[str, Any]) -> None:
    template = target / ".env.example"
    if not template.exists():
        raise RuntimeError("В проекте отсутствует .env.example")

    values = {
        "APP_NAME": settings["app_name"],
        "APP_ENV": "production",
        "APP_KEY": "",
        "APP_DEBUG": "false",
        "APP_TIMEZONE": settings["timezone"],
        "APP_URL": settings["app_url"],
        "APP_LOCALE": settings["locale"],
        "APP_FALLBACK_LOCALE": "en",
        "LOG_LEVEL": "warning",
        "DB_CONNECTION": "mysql",
        "DB_HOST": "127.0.0.1",
        "DB_PORT": "3306",
        "DB_DATABASE": settings["db_name"],
        "DB_USERNAME": settings["db_user"],
        "DB_PASSWORD": settings["db_password"],
        "SESSION_DRIVER": "redis",
        "SESSION_LIFETIME": str(settings["session_lifetime"]),
        "QUEUE_CONNECTION": "redis",
        "CACHE_STORE": "redis",
        "REDIS_CLIENT": "phpredis",
        "REDIS_HOST": "127.0.0.1",
        "REDIS_PASSWORD": "null",
        "REDIS_PORT": "6379",
        "MAIL_MAILER": "log",
        "MAIL_FROM_ADDRESS": "no-reply@example.invalid",
        "MAIL_FROM_NAME": settings["app_name"],
        "VITE_APP_NAME": settings["app_name"],
        "TELEGRAM_BOT_NAME": settings["telegram_bot_name"],
        "TELEGRAM_BOT_TOKEN": settings["telegram_bot_token"],
        "TELEGRAM_REDIRECT_URI": settings["app_url"] + "/auth/telegram/callback",
        "TELEGRAM_WEBHOOK_TOKEN": settings["telegram_webhook_token"],
        "TELESCOPE_ENABLED": "false",
        "SENTRY_LARAVEL_DSN": "",
        "TRONGRID_API_KEY": settings["trongrid_api_key"],
        "IPGEOLOCATION_API_KEY": settings["ipgeolocation_api_key"],
    }

    output: list[str] = []
    seen: set[str] = set()
    for line in template.read_text(encoding="utf-8").splitlines():
        if "=" in line and not line.lstrip().startswith("#"):
            key = line.split("=", 1)[0].strip()
            if key in values:
                output.append(f"{key}={dotenv_value(str(values[key]))}")
                seen.add(key)
                continue
        output.append(line)

    for key, value in values.items():
        if key not in seen:
            output.append(f"{key}={dotenv_value(str(value))}")

    env_path = target / ".env"
    env_path.write_text("\n".join(output) + "\n", encoding="utf-8")
    os.chmod(env_path, 0o640)


def run(
    command: str,
    *,
    cwd: Path | None = None,
    env: dict[str, str] | None = None,
    display: str | None = None,
) -> None:
    display = display or command
    add_log(f"$ {display}")
    process = subprocess.Popen(
        ["bash", "-lc", command],
        cwd=str(cwd) if cwd else None,
        env=env,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
        bufsize=1,
    )
    assert process.stdout is not None
    for line in process.stdout:
        add_log(line)
    code = process.wait()
    if code != 0:
        raise RuntimeError(f"Команда завершилась с кодом {code}: {display}")


def run_mysql(sql: str) -> str:
    process = subprocess.run(
        ["mysql", "--batch", "--skip-column-names"],
        input=sql,
        text=True,
        capture_output=True,
    )
    if process.returncode != 0:
        raise RuntimeError("MySQL: " + process.stderr.strip())
    return process.stdout.strip()


def sql_string(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "''") + "'"


def install_system_files(target: Path, settings: dict[str, Any], php_version: str) -> None:
    app_path = str(target)
    socket_path = f"/run/php/php{php_version}-fpm.sock"
    upload_mb = int(settings["upload_limit_mb"])

    nginx = f"""server {{
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name {urlparse(settings['app_url']).hostname or '_'} _;
    root {app_path}/public;
    index index.php index.html;
    charset utf-8;
    client_max_body_size {upload_mb}M;

    location / {{
        try_files $uri $uri/ /index.php?$query_string;
    }}

    location ~ \\.php$ {{
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:{socket_path};
        fastcgi_buffer_size 32k;
        fastcgi_buffers 16 16k;
        fastcgi_busy_buffers_size 64k;
    }}

    location ~ /\\. {{
        deny all;
    }}

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    server_tokens off;
}}
"""
    Path("/etc/nginx/sites-available/wl-traders").write_text(nginx, encoding="utf-8")
    enabled = Path("/etc/nginx/sites-enabled/wl-traders")
    enabled.unlink(missing_ok=True)
    enabled.symlink_to("/etc/nginx/sites-available/wl-traders")
    Path("/etc/nginx/sites-enabled/default").unlink(missing_ok=True)

    horizon = f"""[Unit]
Description=WL Traders Laravel Horizon
After=network.target mysql.service redis-server.service
Wants=mysql.service redis-server.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory={app_path}
ExecStart=/usr/bin/php {app_path}/artisan horizon
ExecStop=/usr/bin/php {app_path}/artisan horizon:terminate
Restart=always
RestartSec=5
KillSignal=SIGTERM
TimeoutStopSec=240

[Install]
WantedBy=multi-user.target
"""
    Path("/etc/systemd/system/wl-traders-horizon.service").write_text(horizon, encoding="utf-8")

    cron = (
        f"* * * * * www-data cd {app_path} && /usr/bin/php artisan schedule:run "
        ">> /var/log/wl-traders-scheduler.log 2>&1\n"
        f"*/5 * * * * www-data cd {app_path} && /usr/bin/php artisan horizon:snapshot "
        ">> /var/log/wl-traders-scheduler.log 2>&1\n"
    )
    Path("/etc/cron.d/wl-traders").write_text(cron, encoding="utf-8")
    os.chmod("/etc/cron.d/wl-traders", 0o644)
    log_path = Path("/var/log/wl-traders-scheduler.log")
    log_path.touch(exist_ok=True)
    shutil.chown(log_path, user="www-data", group="www-data")

    php_ini = f"""memory_limit=512M
upload_max_filesize={upload_mb}M
post_max_size={upload_mb}M
max_execution_time=120
max_input_time=120
"""
    for sapi in ("cli", "fpm"):
        ini_dir = Path(f"/etc/php/{php_version}/{sapi}/conf.d")
        ini_dir.mkdir(parents=True, exist_ok=True)
        (ini_dir / "99-wl-traders.ini").write_text(php_ini, encoding="utf-8")


def install_backup(target: Path, db_name: str, retention_days: int) -> None:
    backup = f"""#!/usr/bin/env bash
set -Eeuo pipefail
DEST=/var/backups/wl-traders
STAMP=$(date +%Y%m%d-%H%M%S)
install -d -m 0700 "$DEST"
mysqldump --single-transaction --quick {quote(db_name)} | gzip -9 > "$DEST/database-$STAMP.sql.gz"
tar -C {quote(target)} -czf "$DEST/storage-$STAMP.tar.gz" storage/app
find "$DEST" -type f -mtime +{retention_days} -delete
"""
    path = Path("/usr/local/sbin/wl-traders-backup")
    path.write_text(backup, encoding="utf-8")
    os.chmod(path, 0o700)
    Path("/etc/cron.d/wl-traders-backup").write_text(
        "17 3 * * * root /usr/local/sbin/wl-traders-backup >> /var/log/wl-traders-backup.log 2>&1\n",
        encoding="utf-8",
    )
    os.chmod("/etc/cron.d/wl-traders-backup", 0o644)


def copy_project(target: Path) -> None:
    if REPO_ROOT.resolve() == target.resolve():
        add_log("Проект уже находится в целевой папке — копирование пропущено")
        return

    target.mkdir(parents=True, exist_ok=True)
    excludes = [
        ".git",
        ".env",
        "node_modules",
        "vendor",
        "public/build",
        "public/hot",
        "public/storage",
        "bootstrap/ssr",
        "._*",
    ]
    exclude_args = " ".join(f"--exclude={quote(item)}" for item in excludes)
    run(f"rsync -a --delete {exclude_args} {quote(str(REPO_ROOT) + '/')} {quote(str(target) + '/')}")


def normalize_settings(raw: dict[str, Any]) -> dict[str, Any]:
    required = ["app_name", "app_url", "install_path", "db_name", "db_user", "admin_password"]
    for key in required:
        if not str(raw.get(key, "")).strip():
            raise ValueError(f"Поле {key} обязательно")

    password = str(raw["admin_password"])
    if len(password) < 12:
        raise ValueError("Пароль администратора должен содержать минимум 12 символов")

    db_password = str(raw.get("db_password", "")) or secrets.token_urlsafe(24)
    settings = {
        "app_name": str(raw["app_name"]).strip(),
        "app_url": validate_url(str(raw["app_url"]).strip()),
        "install_path": validate_path(str(raw["install_path"]).strip()),
        "timezone": str(raw.get("timezone", "Europe/Moscow")).strip(),
        "locale": str(raw.get("locale", "ru")).strip(),
        "session_lifetime": max(60, min(43200, int(raw.get("session_lifetime", 10080)))),
        "db_name": validate_identifier(str(raw["db_name"]).strip(), "База данных"),
        "db_user": validate_identifier(str(raw["db_user"]).strip(), "Пользователь БД"),
        "db_password": db_password,
        "admin_password": password,
        "telegram_bot_name": str(raw.get("telegram_bot_name", "")).strip(),
        "telegram_bot_token": str(raw.get("telegram_bot_token", "")).strip(),
        "telegram_webhook_token": str(raw.get("telegram_webhook_token", "")).strip(),
        "trongrid_api_key": str(raw.get("trongrid_api_key", "")).strip(),
        "ipgeolocation_api_key": str(raw.get("ipgeolocation_api_key", "")).strip(),
        "upload_limit_mb": max(2, min(512, int(raw.get("upload_limit_mb", 64)))),
        "create_swap": bool(raw.get("create_swap", True)),
        "enable_firewall": bool(raw.get("enable_firewall", True)),
        "install_backups": bool(raw.get("install_backups", True)),
        "backup_retention_days": max(1, min(90, int(raw.get("backup_retention_days", 7)))),
        "generate_test_data": bool(raw.get("generate_test_data", False)),
    }
    return settings


def perform_install(raw_settings: dict[str, Any], server: ThreadingHTTPServer) -> None:
    try:
        settings = normalize_settings(raw_settings)
        settings["installer_port"] = int(server.server_port)
        target: Path = settings["install_path"]
        set_state(phase="installing", message="Установка началась", app_url=settings["app_url"])

        if (target / ".env").exists():
            raise RuntimeError(f"В {target} уже существует установленный проект (.env найден)")

        add_log("Устанавливаю системные пакеты…")
        run("export DEBIAN_FRONTEND=noninteractive; apt-get update")
        run(
            "export DEBIAN_FRONTEND=noninteractive; apt-get install -y "
            "nginx mysql-server redis-server composer nodejs npm rsync unzip curl ufw "
            "php-cli php-fpm php-mysql php-redis php-bcmath php-gmp php-mbstring "
            "php-xml php-curl php-zip php-gd php-intl"
        )
        run("systemctl enable --now nginx mysql redis-server")

        if settings["create_swap"]:
            swap_exists = subprocess.run(["swapon", "--show=NAME"], capture_output=True, text=True).stdout.strip()
            if not swap_exists:
                add_log("Создаю swap-файл 2 ГБ…")
                run("fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile")
                with Path("/etc/fstab").open("a", encoding="utf-8") as handle:
                    handle.write("/swapfile none swap sw 0 0\n")

        add_log("Копирую проект…")
        copy_project(target)
        for apple_double in target.rglob("._*"):
            if apple_double.is_file():
                apple_double.unlink()
        run(f"chown -R www-data:www-data {quote(target)}")

        add_log("Создаю базу данных…")
        db_name = settings["db_name"]
        db_user = settings["db_user"]
        table_count = run_mysql(
            "SELECT COUNT(*) FROM information_schema.tables "
            f"WHERE table_schema={sql_string(db_name)};"
        )
        if table_count and int(table_count) > 0:
            raise RuntimeError(f"База {db_name} уже содержит таблицы; установка остановлена без удаления данных")
        run_mysql(
            f"CREATE DATABASE IF NOT EXISTS `{db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
            f"CREATE USER IF NOT EXISTS {sql_string(db_user)}@'localhost' IDENTIFIED BY {sql_string(settings['db_password'])};\n"
            f"ALTER USER {sql_string(db_user)}@'localhost' IDENTIFIED BY {sql_string(settings['db_password'])};\n"
            f"GRANT ALL PRIVILEGES ON `{db_name}`.* TO {sql_string(db_user)}@'localhost';\n"
            "FLUSH PRIVILEGES;\n"
        )

        add_log("Формирую production .env…")
        write_env(target, settings)
        shutil.chown(target / ".env", user="www-data", group="www-data")

        run("install -d -o www-data -g www-data -m 0755 /var/www/.cache/composer /var/www/.npm")
        run(
            f"sudo -u www-data env HOME=/var/www composer install --working-dir={quote(target)} "
            "--no-dev --prefer-dist --optimize-autoloader --no-interaction"
        )
        run(f"sudo -u www-data /usr/bin/php {quote(target / 'artisan')} ziggy:generate resources/js/ziggy-routes.js")
        run(f"sudo -u www-data env HOME=/var/www npm --prefix {quote(target)} ci")
        run(f"sudo -u www-data env HOME=/var/www npm --prefix {quote(target)} run build")
        run(f"sudo -u www-data /usr/bin/php {quote(target / 'artisan')} key:generate --force")
        run(f"sudo -u www-data /usr/bin/php {quote(target / 'artisan')} storage:link --force")
        run(f"sudo -u www-data /usr/bin/php {quote(target / 'artisan')} system:install --no-interaction")
        run(
            f"sudo -u www-data /usr/bin/php {quote(target / 'artisan')} "
            f"users:passwords:reset-admin admin {quote(settings['admin_password'])}",
            display="php artisan users:passwords:reset-admin admin [пароль скрыт]",
        )

        php_version = subprocess.check_output(
            ["php", "-r", "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;"], text=True
        ).strip()
        add_log("Настраиваю Nginx, PHP, Horizon и cron…")
        install_system_files(target, settings, php_version)
        if settings["install_backups"]:
            install_backup(target, db_name, settings["backup_retention_days"])

        run("systemctl daemon-reload")
        run("nginx -t")
        php_fpm_service = f"php{php_version}-fpm"
        run(f"systemctl enable --now {quote(php_fpm_service)} nginx mysql redis-server wl-traders-horizon cron")
        run(f"systemctl restart {quote(php_fpm_service)}")
        run("systemctl reload nginx")
        run(f"sudo -u www-data /usr/bin/php {quote(target / 'artisan')} optimize")

        if settings["enable_firewall"]:
            add_log("Включаю firewall: SSH и HTTP…")
            installer_port = int(settings["installer_port"])
            run(
                "ufw allow OpenSSH && ufw allow 'Nginx HTTP' && "
                f"ufw allow {installer_port}/tcp && ufw --force enable"
            )

        if settings["generate_test_data"]:
            add_log("Запускаю генерацию тестовых данных…")
            run(
                f"sudo -u www-data /usr/bin/php {quote(target / 'artisan')} "
                "dev:test-data:generate --force --no-interaction"
            )

        add_log("Проверяю ответ приложения…")
        run("curl --fail --silent --show-error --max-time 20 -o /dev/null http://127.0.0.1/")
        set_state(phase="done", message="WL Traders установлен", error=None)
        add_log("Установка завершена. Панель закроется автоматически.")
        if settings["enable_firewall"]:
            installer_port = int(settings["installer_port"])
            run(
                "systemd-run --unit=wl-traders-installer-firewall-cleanup "
                "--on-active=30s /usr/sbin/ufw --force delete allow "
                f"{installer_port}/tcp"
            )
        threading.Timer(20, server.shutdown).start()
    except Exception as exc:  # noqa: BLE001 - installer must surface all failures
        add_log(f"ОШИБКА: {exc}")
        set_state(phase="failed", message="Установка остановлена", error=str(exc))


PAGE = r"""<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>WL Traders Installer</title>
<style>
:root{color-scheme:dark;--bg:#05060a;--card:#0c0e16;--line:#252a3a;--text:#f4f6ff;--muted:#9299ae;--accent:#6d7cff;--accent2:#a66cff;--danger:#ff667d}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 20% 0,#17122e 0,transparent 28rem),radial-gradient(circle at 90% 10%,#0d2140 0,transparent 25rem),var(--bg);color:var(--text);font:15px/1.45 Inter,ui-sans-serif,system-ui,sans-serif}.wrap{width:min(980px,calc(100% - 32px));margin:42px auto 80px}.brand{display:flex;align-items:center;gap:14px;margin-bottom:26px}.mark{width:42px;height:42px;border-radius:13px;background:linear-gradient(135deg,var(--accent),var(--accent2));box-shadow:0 0 35px #746cff66}.brand h1{margin:0;font-size:24px}.brand p{margin:2px 0 0;color:var(--muted)}.card{background:#0c0e16e8;border:1px solid var(--line);border-radius:20px;padding:24px;box-shadow:0 20px 80px #0008;backdrop-filter:blur(12px);margin-bottom:18px}.card h2{font-size:16px;margin:0 0 18px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.full{grid-column:1/-1}label{display:grid;gap:7px;color:#dce0ef;font-size:13px}small,.hint{color:var(--muted)}input,select{width:100%;border:1px solid var(--line);background:#080a10;color:var(--text);border-radius:11px;padding:12px 13px;outline:none}input:focus,select:focus{border-color:var(--accent);box-shadow:0 0 0 3px #6d7cff20}.secret{font-family:ui-monospace,SFMono-Regular,monospace}.checks{display:grid;gap:12px}.check{display:flex;gap:10px;align-items:flex-start;background:#080a10;border:1px solid var(--line);border-radius:12px;padding:13px}.check input{width:auto;margin-top:3px}.optional{display:inline-block;color:#b8a8ff;background:#a66cff18;border:1px solid #a66cff44;border-radius:999px;padding:2px 8px;font-size:11px;margin-left:7px}.actions{display:flex;justify-content:space-between;align-items:center;gap:16px}.button{border:0;border-radius:13px;padding:13px 22px;color:white;background:linear-gradient(135deg,var(--accent),var(--accent2));font-weight:750;cursor:pointer;box-shadow:0 12px 28px #6d7cff38}.button:disabled{opacity:.5;cursor:not-allowed}.warning{color:#ffd08a}.status{display:none}.status.active{display:block}.bar{height:8px;background:#171a24;border-radius:99px;overflow:hidden;margin:16px 0}.bar i{display:block;width:34%;height:100%;background:linear-gradient(90deg,var(--accent),var(--accent2));border-radius:99px;animation:move 1.2s infinite alternate}.done .bar i{width:100%;animation:none}.failed .bar i{background:var(--danger);width:100%;animation:none}@keyframes move{from{transform:translateX(-70%)}to{transform:translateX(270%)}}pre{height:310px;overflow:auto;background:#05060a;border:1px solid var(--line);border-radius:13px;padding:15px;color:#c9cee2;font:12px/1.55 ui-monospace,SFMono-Regular,monospace;white-space:pre-wrap}.pill{display:inline-flex;align-items:center;gap:8px;color:var(--muted)}.dot{width:8px;height:8px;border-radius:50%;background:var(--accent);box-shadow:0 0 12px var(--accent)}@media(max-width:720px){.grid{grid-template-columns:1fr}.full{grid-column:auto}.wrap{margin-top:20px}.card{padding:18px}.actions{align-items:stretch;flex-direction:column}.button{width:100%}}
</style>
</head>
<body><main class="wrap">
<div class="brand"><div class="mark"></div><div><h1>WL Traders Installer</h1><p>Одноразовая установка на чистый Ubuntu-сервер</p></div></div>
<section class="card"><span class="warning">Панель временная и работает по HTTP. Открывайте её только с доверенного компьютера и не передавайте одноразовую ссылку другим.</span></section>
<form id="form">
<section class="card"><h2>Приложение</h2><div class="grid">
<label>Название<input name="app_name" value="WL Traders" required></label>
<label>Адрес приложения<input name="app_url" value="http://__SERVER_IP__" required></label>
<label>Папка установки<input name="install_path" value="/var/www/wl-traders" required></label>
<label>Часовой пояс<select name="timezone"><option>Europe/Moscow</option><option>UTC</option><option>Asia/Almaty</option><option>Asia/Dubai</option></select></label>
<label>Язык<select name="locale"><option value="ru">Русский</option><option value="en">English</option></select></label>
<label>Сессия, минут<input name="session_lifetime" type="number" value="10080" min="60" max="43200"></label>
<label>Лимит загрузки, МБ<input name="upload_limit_mb" type="number" value="64" min="2" max="512"></label>
<label>Пароль администратора<input class="secret" name="admin_password" type="password" minlength="12" required autocomplete="new-password"><small>Логин после установки: admin</small></label>
</div></section>
<section class="card"><h2>MySQL</h2><div class="grid">
<label>База данных<input name="db_name" value="wl_traders" pattern="[A-Za-z0-9_]+" required></label>
<label>Пользователь<input name="db_user" value="wl_traders" pattern="[A-Za-z0-9_]+" required></label>
<label class="full">Пароль БД<input class="secret" name="db_password" type="password" autocomplete="new-password"><small>Можно оставить пустым — установщик создаст случайный пароль и сохранит его только в .env.</small></label>
</div></section>
<section class="card"><h2>Опциональные интеграции</h2><div class="grid">
<label>Telegram Bot Name <span class="optional">опционально</span><input name="telegram_bot_name"></label>
<label>Telegram Bot Token <span class="optional">опционально</span><input class="secret" name="telegram_bot_token" type="password"></label>
<label>Telegram Webhook Token <span class="optional">опционально</span><input class="secret" name="telegram_webhook_token" type="password"></label>
<label>TronGrid API Key <span class="optional">инвойсы</span><input class="secret" name="trongrid_api_key" type="password"></label>
<label class="full">IP Geolocation API Key <span class="optional">гео-функции</span><input class="secret" name="ipgeolocation_api_key" type="password"></label>
</div><p class="hint">Почта и Sentry намеренно не настраиваются.</p></section>
<section class="card"><h2>Сервер</h2><div class="checks">
<label class="check"><input name="create_swap" type="checkbox" checked><span><b>Создать swap 2 ГБ</b><br><small>Только если swap ещё отсутствует.</small></span></label>
<label class="check"><input name="enable_firewall" type="checkbox" checked><span><b>Включить firewall</b><br><small>Оставит открытыми SSH и HTTP.</small></span></label>
<label class="check"><input name="install_backups" type="checkbox" checked><span><b>Ежедневный локальный бэкап</b><br><small>MySQL и storage в /var/backups/wl-traders.</small></span></label>
<label>Хранить бэкапы, дней<input name="backup_retention_days" type="number" value="7" min="1" max="90"></label>
<label class="check"><input name="generate_test_data" type="checkbox"><span><b>Создать тестовые данные</b><br><small>Для демо-сервера. На реальном production не включать.</small></span></label>
</div></section>
<section class="card"><div class="actions"><span class="warning">Установщик работает только с пустой базой и не удаляет существующие данные.</span><button class="button" type="submit">Установить WL Traders</button></div></section>
</form>
<section id="status" class="card status"><div class="pill"><span class="dot"></span><b id="message">Подготовка…</b></div><div class="bar"><i></i></div><pre id="logs"></pre></section>
</main>
<script>
const token=new URLSearchParams(location.search).get('token');
const form=document.getElementById('form'),statusBox=document.getElementById('status'),logs=document.getElementById('logs'),message=document.getElementById('message');
let timer=null;
function payload(){const f=new FormData(form),o={};for(const [k,v] of f.entries())o[k]=v;for(const k of ['create_swap','enable_firewall','install_backups','generate_test_data'])o[k]=form.elements[k].checked;return o}
async function poll(){try{const r=await fetch('/status?token='+encodeURIComponent(token)),s=await r.json();message.textContent=s.message;logs.textContent=s.logs.join('\n');logs.scrollTop=logs.scrollHeight;statusBox.className='card status active '+s.phase;if(s.phase==='done'){clearInterval(timer);setTimeout(()=>location.href=s.app_url,3500)}if(s.phase==='failed')clearInterval(timer)}catch(e){message.textContent='Панель завершает работу…'}}
form.addEventListener('submit',async e=>{e.preventDefault();if(!confirm('Начать установку на этот сервер?'))return;form.querySelector('button').disabled=true;statusBox.classList.add('active');statusBox.scrollIntoView({behavior:'smooth'});const r=await fetch('/install?token='+encodeURIComponent(token),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload())});if(!r.ok){alert((await r.json()).error);form.querySelector('button').disabled=false;return}form.style.display='none';timer=setInterval(poll,1000);poll()});
</script></body></html>"""


class InstallerHandler(BaseHTTPRequestHandler):
    server_version = "WLTradersInstaller/1.0"

    def log_message(self, format: str, *args: Any) -> None:
        return

    def authorized(self) -> bool:
        query = parse_qs(urlparse(self.path).query)
        supplied = query.get("token", [""])[0]
        return secrets.compare_digest(supplied, self.server.install_token)  # type: ignore[attr-defined]

    def json_response(self, data: dict[str, Any], status: HTTPStatus = HTTPStatus.OK) -> None:
        body = json.dumps(data, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Cache-Control", "no-store")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self) -> None:  # noqa: N802
        if not self.authorized():
            self.send_error(HTTPStatus.FORBIDDEN)
            return
        path = urlparse(self.path).path
        if path == "/":
            body = PAGE.replace("__SERVER_IP__", public_ip()).encode("utf-8")
            self.send_response(HTTPStatus.OK)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.send_header("Cache-Control", "no-store")
            self.send_header("X-Frame-Options", "DENY")
            self.send_header("Referrer-Policy", "no-referrer")
            self.send_header("Content-Security-Policy", "default-src 'self' 'unsafe-inline'")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)
            return
        if path == "/status":
            with STATE_LOCK:
                snapshot = dict(STATE)
                snapshot["logs"] = list(STATE["logs"])
            self.json_response(snapshot)
            return
        self.send_error(HTTPStatus.NOT_FOUND)

    def do_POST(self) -> None:  # noqa: N802
        if not self.authorized():
            self.send_error(HTTPStatus.FORBIDDEN)
            return
        if urlparse(self.path).path != "/install":
            self.send_error(HTTPStatus.NOT_FOUND)
            return
        with STATE_LOCK:
            if STATE["phase"] != "ready":
                self.json_response({"error": "Установка уже запущена"}, HTTPStatus.CONFLICT)
                return
        try:
            length = int(self.headers.get("Content-Length", "0"))
            if length <= 0 or length > 131072:
                raise ValueError("Некорректный размер запроса")
            payload = json.loads(self.rfile.read(length))
            normalize_settings(payload)
        except (ValueError, TypeError, json.JSONDecodeError) as exc:
            self.json_response({"error": str(exc)}, HTTPStatus.BAD_REQUEST)
            return
        set_state(phase="starting", message="Подготовка установки")
        thread = threading.Thread(target=perform_install, args=(payload, self.server), daemon=True)
        thread.start()
        self.json_response({"ok": True}, HTTPStatus.ACCEPTED)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--host", default="0.0.0.0")
    parser.add_argument("--port", type=int, default=8787)
    parser.add_argument("--token", required=True)
    args = parser.parse_args()
    if os.geteuid() != 0:
        raise SystemExit("Установщик должен быть запущен от root")
    server = ThreadingHTTPServer((args.host, args.port), InstallerHandler)
    server.install_token = args.token  # type: ignore[attr-defined]
    server.serve_forever()


if __name__ == "__main__":
    main()
