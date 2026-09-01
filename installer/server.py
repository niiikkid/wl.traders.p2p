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
<meta name="theme-color" content="#000000">
<title>WL Traders · Production Installer</title>
<style>
:root{color-scheme:dark;--void:#000;--surface:#07080b;--surface-2:#0b0d11;--line:rgba(240,240,250,.14);--line-strong:rgba(240,240,250,.28);--text:#f0f0fa;--muted:rgba(240,240,250,.58);--faint:rgba(240,240,250,.38);--danger:#ff7b82;--warning:#e6c98a;--success:#dfffe8;--display:"SFMono-Regular",Consolas,"Liberation Mono",monospace;--body:"Helvetica Neue",Helvetica,Arial,sans-serif;--max:1260px}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;min-width:320px;background:var(--void);color:var(--text);font:15px/1.55 var(--body);-webkit-font-smoothing:antialiased}button,input,select{font:inherit}button{color:inherit}::selection{background:var(--text);color:var(--void)}
.shell{position:relative;isolation:isolate;min-height:100svh;overflow:hidden}.space{position:fixed;z-index:-2;inset:0;pointer-events:none;background:radial-gradient(circle at 76% 18%,rgba(112,126,158,.12),transparent 26rem),radial-gradient(circle at 18% 92%,rgba(255,255,255,.035),transparent 28rem),#000}.space:before{content:"";position:absolute;width:min(74vw,980px);aspect-ratio:1;right:-32vw;top:-18vw;border:1px solid rgba(240,240,250,.1);border-radius:50%;box-shadow:0 0 0 110px rgba(240,240,250,.012),0 0 0 220px rgba(240,240,250,.008)}.space:after{content:"";position:absolute;inset:0;opacity:.55;background-image:radial-gradient(circle,#fff 0 1px,transparent 1.25px),radial-gradient(circle,rgba(255,255,255,.55) 0 1px,transparent 1.2px);background-position:0 0,46px 28px;background-size:151px 151px,233px 233px;mask-image:linear-gradient(to bottom,black,transparent 72%)}
.topbar{position:sticky;top:0;z-index:20;border-bottom:1px solid var(--line);background:rgba(0,0,0,.82);backdrop-filter:blur(18px)}.topbar-inner{width:min(calc(100% - 48px),var(--max));height:72px;margin:auto;display:flex;align-items:center;justify-content:space-between;gap:24px}.brand{display:flex;align-items:center;gap:13px}.brand-mark{width:36px;height:36px;display:grid;place-items:center;border:1px solid var(--text);background:var(--text);color:#000;font:600 11px/1 var(--display);letter-spacing:-.06em}.brand-name{font:500 14px/1.2 var(--display);letter-spacing:.08em;text-transform:uppercase}.brand-name small{display:block;margin-top:5px;color:var(--faint);font-size:9px;font-weight:400;letter-spacing:.16em}.session{display:flex;align-items:center;gap:10px;color:var(--muted);font:10px/1 var(--display);letter-spacing:.12em;text-transform:uppercase}.session-dot{width:6px;height:6px;border-radius:50%;background:var(--text);box-shadow:0 0 0 5px rgba(240,240,250,.07)}
.layout{width:min(calc(100% - 48px),var(--max));margin:0 auto;padding:54px 0 88px;display:grid;grid-template-columns:260px minmax(0,1fr);gap:clamp(42px,6vw,92px)}.rail{align-self:start;position:sticky;top:110px}.rail-kicker,.kicker{color:var(--muted);font:10px/1.4 var(--display);letter-spacing:.18em;text-transform:uppercase}.rail h1{margin:17px 0 14px;font:300 clamp(31px,3.2vw,46px)/1.04 var(--display);letter-spacing:-.065em}.rail-copy{margin:0;color:var(--muted);font-size:14px;max-width:230px}.progress{margin:38px 0 0;padding:0;list-style:none;border-top:1px solid var(--line)}.progress a{min-height:52px;display:grid;grid-template-columns:28px 1fr;align-items:center;border-bottom:1px solid var(--line);color:var(--faint);text-decoration:none;font:10px/1.2 var(--display);letter-spacing:.1em;text-transform:uppercase;transition:color .18s ease,border-color .18s ease}.progress a:hover,.progress a.active{color:var(--text);border-color:var(--line-strong)}.progress span{color:var(--faint)}.security-note{margin-top:32px;padding:16px 0;border-block:1px solid var(--line);color:var(--muted);font-size:12px}.security-note b{display:block;margin-bottom:6px;color:var(--warning);font:500 10px/1.3 var(--display);letter-spacing:.12em;text-transform:uppercase}
.content{min-width:0}.intro{padding:4px 0 34px;border-bottom:1px solid var(--line)}.intro-row{display:flex;align-items:flex-end;justify-content:space-between;gap:30px}.intro h2{max-width:720px;margin:13px 0 0;font:300 clamp(37px,5vw,68px)/.98 var(--display);letter-spacing:-.075em}.intro h2 span{color:rgba(240,240,250,.48)}.system-tag{flex:0 0 auto;margin-bottom:4px;padding:8px 10px;border:1px solid var(--line);color:var(--muted);font:9px/1 var(--display);letter-spacing:.12em;text-transform:uppercase}
.form-section{scroll-margin-top:100px;padding:42px 0;border-bottom:1px solid var(--line)}.section-head{display:grid;grid-template-columns:46px minmax(0,1fr);gap:16px;margin-bottom:27px}.section-number{padding-top:4px;color:var(--faint);font:10px/1 var(--display);letter-spacing:.12em}.section-head h3{margin:0;font:400 22px/1.2 var(--display);letter-spacing:-.035em}.section-head p{margin:8px 0 0;color:var(--muted);font-size:14px;max-width:660px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:19px 20px}.full{grid-column:1/-1}label.field{display:grid;gap:8px;color:rgba(240,240,250,.84);font-size:12px}.label-row{display:flex;align-items:center;justify-content:space-between;gap:10px}.optional{color:var(--faint);font:9px/1 var(--display);letter-spacing:.1em;text-transform:uppercase}.field small,.hint{color:var(--faint);font-size:11px}.control{position:relative}input:not([type=checkbox]),select{width:100%;min-height:49px;border:1px solid var(--line);border-radius:2px;background:rgba(240,240,250,.025);color:var(--text);padding:13px 14px;outline:none;transition:border-color .18s ease,background .18s ease}select{appearance:none;background-image:linear-gradient(45deg,transparent 50%,var(--faint) 50%),linear-gradient(135deg,var(--faint) 50%,transparent 50%);background-position:calc(100% - 18px) 21px,calc(100% - 13px) 21px;background-size:5px 5px,5px 5px;background-repeat:no-repeat}input:hover,select:hover{border-color:var(--line-strong)}input:focus,select:focus{border-color:var(--text);background:rgba(240,240,250,.045);box-shadow:0 0 0 3px rgba(240,240,250,.07)}input::placeholder{color:rgba(240,240,250,.25)}.secret{padding-right:50px!important;font-family:var(--display)}.reveal-secret{position:absolute;right:1px;top:1px;width:46px;height:47px;border:0;border-left:1px solid var(--line);background:transparent;cursor:pointer;color:var(--muted);font:9px/1 var(--display);letter-spacing:.08em}.reveal-secret:hover{color:var(--text);background:rgba(240,240,250,.04)}
.integration-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1px;background:var(--line);border:1px solid var(--line)}.integration{min-width:0;padding:21px;background:#050609}.integration-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:17px}.integration h4{margin:0;font:400 14px/1.25 var(--display);letter-spacing:-.02em}.integration p{margin:6px 0 0;color:var(--faint);font-size:11px}.badge{flex:0 0 auto;padding:5px 7px;border:1px solid var(--line);color:var(--faint);font:8px/1 var(--display);letter-spacing:.1em;text-transform:uppercase}.integration .field+.field{margin-top:13px}.integration.full{grid-column:1/-1}.no-config{margin:18px 0 0;color:var(--muted);font-size:12px}
.checks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1px;background:var(--line);border:1px solid var(--line)}.check{position:relative;min-height:118px;display:flex;align-items:flex-start;gap:13px;padding:20px;background:#050609;cursor:pointer;transition:background .18s ease}.check:hover{background:#0a0b0f}.check input{appearance:none;flex:0 0 auto;width:18px;height:18px;margin:1px 0 0;border:1px solid var(--line-strong);background:#000;display:grid;place-items:center}.check input:checked{border-color:var(--text);background:var(--text)}.check input:checked:after{content:"";width:8px;height:4px;border-left:2px solid #000;border-bottom:2px solid #000;transform:translateY(-1px) rotate(-45deg)}.check b{display:block;color:var(--text);font:400 13px/1.25 var(--display)}.check small{display:block;margin-top:8px;color:var(--faint);font-size:11px;line-height:1.45}.retention{margin-top:19px;max-width:320px}
.launch{padding:34px 0 0}.launch-panel{position:relative;overflow:hidden;border:1px solid var(--line-strong);background:#050609;padding:clamp(24px,4vw,38px)}.launch-panel:after{content:"DEPLOY";position:absolute;right:-10px;bottom:-29px;color:rgba(240,240,250,.035);font:300 clamp(72px,11vw,138px)/1 var(--display);letter-spacing:-.09em;pointer-events:none}.launch-copy,.actions{position:relative;z-index:1}.launch-copy h3{margin:0;font:300 clamp(25px,3vw,38px)/1.05 var(--display);letter-spacing:-.05em}.launch-copy p{max-width:650px;margin:12px 0 0;color:var(--muted);font-size:13px}.actions{margin-top:27px;display:flex;align-items:center;justify-content:space-between;gap:22px}.warning{display:flex;align-items:flex-start;gap:9px;color:var(--warning);font-size:11px;max-width:470px}.warning:before{content:"!";flex:0 0 auto;width:17px;height:17px;display:grid;place-items:center;border:1px solid currentColor;font:600 9px/1 var(--display)}.button{min-height:48px;flex:0 0 auto;border:1px solid var(--text);border-radius:2px;padding:0 22px;background:var(--text);color:#000;cursor:pointer;font:500 10px/1 var(--display);letter-spacing:.13em;text-transform:uppercase;transition:background .18s ease,color .18s ease,transform .18s ease}.button:hover{background:#fff;transform:translateY(-1px)}.button:disabled{opacity:.38;cursor:not-allowed;transform:none}
.status{display:none;margin-top:30px;border:1px solid var(--line-strong);background:#030405}.status.active{display:block}.status-head{min-height:67px;padding:0 20px;display:flex;align-items:center;justify-content:space-between;gap:18px;border-bottom:1px solid var(--line)}.pill{display:flex;align-items:center;gap:11px;font:400 12px/1 var(--display);letter-spacing:.04em}.dot{width:7px;height:7px;border-radius:50%;background:var(--text);box-shadow:0 0 0 6px rgba(240,240,250,.06);animation:pulse 1.7s infinite}.status.done .dot{background:var(--success)}.status.failed .dot{background:var(--danger)}.status-label{color:var(--faint);font:9px/1 var(--display);letter-spacing:.13em;text-transform:uppercase}.bar{height:2px;background:rgba(240,240,250,.08);overflow:hidden}.bar i{display:block;width:38%;height:100%;background:var(--text);animation:scan 1.4s ease-in-out infinite}.status.done .bar i{width:100%;animation:none;background:var(--success)}.status.failed .bar i{animation:none;background:var(--danger)}pre{height:360px;margin:0;overflow:auto;padding:20px;color:rgba(240,240,250,.68);background:#000;font:11px/1.65 var(--display);white-space:pre-wrap;word-break:break-word}.status.done pre{color:rgba(223,255,232,.72)}.status.failed pre{color:rgba(255,123,130,.82)}
.footer{width:min(calc(100% - 48px),var(--max));margin:auto;padding:28px 0 42px;border-top:1px solid var(--line);display:flex;justify-content:space-between;gap:24px;color:var(--faint);font:9px/1.4 var(--display);letter-spacing:.1em;text-transform:uppercase}
@keyframes pulse{50%{opacity:.35}}@keyframes scan{0%{transform:translateX(-110%)}100%{transform:translateX(310%)}}
:focus-visible{outline:2px solid var(--text);outline-offset:3px}
@media(max-width:920px){.layout{grid-template-columns:1fr;padding-top:38px}.rail{position:static}.rail-copy{max-width:620px}.progress{display:grid;grid-template-columns:repeat(4,1fr);margin-top:25px}.progress a{padding:0 8px}.security-note{margin-top:24px}.intro-row{align-items:flex-start}.system-tag{display:none}}
@media(max-width:640px){.topbar-inner,.layout,.footer{width:min(calc(100% - 28px),var(--max))}.topbar-inner{height:64px}.session span:last-child{display:none}.layout{padding:30px 0 58px;gap:34px}.rail h1{font-size:34px}.progress{grid-template-columns:repeat(2,1fr)}.intro h2{font-size:39px}.grid,.integration-grid,.checks{grid-template-columns:1fr}.integration.full{grid-column:auto}.section-head{grid-template-columns:32px 1fr}.form-section{padding:34px 0}.actions{align-items:stretch;flex-direction:column}.button{width:100%}.status-head{align-items:flex-start;flex-direction:column;padding-block:17px}.footer{flex-direction:column}}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*,*:before,*:after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}}
</style>
</head>
<body><div class="shell"><div class="space" aria-hidden="true"></div>
<header class="topbar"><div class="topbar-inner"><div class="brand"><div class="brand-mark">WL</div><div class="brand-name">WL Traders<small>Deployment systems</small></div></div><div class="session"><span class="session-dot"></span><span>One-time secure session</span></div></div></header>
<main class="layout">
<aside class="rail"><div class="rail-kicker">Production installer · 01</div><h1>Подготовка системы к запуску.</h1><p class="rail-copy">Настройте приложение, инфраструктуру и нужные интеграции. Установщик развернёт весь рабочий контур автоматически.</p><nav aria-label="Разделы установщика"><ol class="progress"><li><a class="active" href="#application"><span>01</span>Приложение</a></li><li><a href="#database"><span>02</span>MySQL</a></li><li><a href="#integrations"><span>03</span>Интеграции</a></li><li><a href="#server"><span>04</span>Сервер</a></li></ol></nav><div class="security-note"><b>Временная панель</b>Работает по HTTP только во время установки. Не передавайте одноразовую ссылку другим.</div></aside>
<div class="content"><header class="intro"><div class="intro-row"><div><span class="kicker">Self-hosted · Ubuntu · Automated</span><h2>Один запуск.<br><span>Полный production‑контур.</span></h2></div><span class="system-tag">SYS / READY</span></div></header>
<form id="form">
<section class="form-section" id="application"><div class="section-head"><span class="section-number">01</span><div><h3>Приложение</h3><p>Базовые параметры будущей панели. Адрес можно указать как IP сервера или подготовленный домен.</p></div></div><div class="grid">
<label class="field"><span>Название</span><input name="app_name" value="WL Traders" required></label>
<label class="field"><span>Адрес приложения</span><input name="app_url" value="http://__SERVER_IP__" required inputmode="url"></label>
<label class="field"><span>Папка установки</span><input class="secret" name="install_path" value="/var/www/wl-traders" required></label>
<label class="field"><span>Часовой пояс</span><select name="timezone"><option>Europe/Moscow</option><option>UTC</option><option>Asia/Almaty</option><option>Asia/Dubai</option></select></label>
<label class="field"><span>Язык интерфейса</span><select name="locale"><option value="ru">Русский</option><option value="en">English</option></select></label>
<label class="field"><span>Сессия, минут</span><input name="session_lifetime" type="number" value="10080" min="60" max="43200"></label>
<label class="field"><span>Лимит загрузки, МБ</span><input name="upload_limit_mb" type="number" value="64" min="2" max="512"></label>
<label class="field"><span>Пароль администратора</span><span class="control"><input class="secret" name="admin_password" type="password" minlength="12" required autocomplete="new-password"><button class="reveal-secret" type="button" aria-label="Показать пароль">SHOW</button></span><small>Минимум 12 символов · логин после установки: admin</small></label>
</div></section>
<section class="form-section" id="database"><div class="section-head"><span class="section-number">02</span><div><h3>База данных</h3><p>Установщик создаст отдельную MySQL‑базу и пользователя. Существующие таблицы никогда не удаляются.</p></div></div><div class="grid">
<label class="field"><span>Название базы</span><input class="secret" name="db_name" value="wl_traders" pattern="[A-Za-z0-9_]+" required></label>
<label class="field"><span>Пользователь</span><input class="secret" name="db_user" value="wl_traders" pattern="[A-Za-z0-9_]+" required></label>
<label class="field full"><span class="label-row"><span>Пароль базы данных</span><span class="optional">Автогенерация доступна</span></span><span class="control"><input class="secret" name="db_password" type="password" autocomplete="new-password"><button class="reveal-secret" type="button" aria-label="Показать пароль">SHOW</button></span><small>Оставьте пустым — будет создан случайный пароль, который сохранится только в production .env.</small></label>
</div></section>
<section class="form-section" id="integrations"><div class="section-head"><span class="section-number">03</span><div><h3>Интеграции</h3><p>Подключайте только используемые сервисы. Все ключи опциональны и останутся в конфигурации вашего сервера.</p></div></div><div class="integration-grid">
<article class="integration"><div class="integration-head"><div><h4>Telegram</h4><p>Бот и webhook‑функции</p></div><span class="badge">Optional</span></div><label class="field"><span>Bot Name</span><input name="telegram_bot_name" autocomplete="off"></label><label class="field"><span>Bot Token</span><input class="secret" name="telegram_bot_token" type="password" autocomplete="new-password"></label><label class="field"><span>Webhook Token</span><input class="secret" name="telegram_webhook_token" type="password" autocomplete="new-password"></label></article>
<article class="integration"><div class="integration-head"><div><h4>TronGrid</h4><p>Полноценные USDT TRC20‑инвойсы</p></div><span class="badge">Invoices</span></div><label class="field"><span>API Key</span><input class="secret" name="trongrid_api_key" type="password" autocomplete="new-password"></label></article>
<article class="integration full"><div class="integration-head"><div><h4>IP Geolocation</h4><p>Географические проверки и связанные функции</p></div><span class="badge">Geo</span></div><label class="field"><span>API Key</span><input class="secret" name="ipgeolocation_api_key" type="password" autocomplete="new-password"></label></article>
</div><p class="no-config">Почта и Sentry намеренно не настраиваются этим установщиком.</p></section>
<section class="form-section" id="server"><div class="section-head"><span class="section-number">04</span><div><h3>Сервер</h3><p>Рекомендуемые production‑настройки уже включены. Тестовые данные оставлены выключенными.</p></div></div><div class="checks">
<label class="check"><input name="create_swap" type="checkbox" checked><span><b>Swap · 2 ГБ</b><small>Создаётся только при отсутствии swap на сервере.</small></span></label>
<label class="check"><input name="enable_firewall" type="checkbox" checked><span><b>Firewall</b><small>Оставит открытыми SSH и HTTP, временный порт закроется после запуска.</small></span></label>
<label class="check"><input name="install_backups" type="checkbox" checked><span><b>Ежедневный бэкап</b><small>MySQL и storage сохраняются локально в /var/backups/wl-traders.</small></span></label>
<label class="check"><input name="generate_test_data" type="checkbox"><span><b>Тестовые данные</b><small>Только для демо‑сервера. Не включайте на реальном production.</small></span></label>
</div><label class="field retention"><span>Хранить бэкапы, дней</span><input name="backup_retention_days" type="number" value="7" min="1" max="90"></label></section>
<section class="launch"><div class="launch-panel"><div class="launch-copy"><span class="kicker">Final sequence</span><h3>Система готова к развёртыванию.</h3><p>Будут установлены Nginx, PHP, MySQL, Redis, Composer и Node.js, затем собран frontend и запущены фоновые службы.</p></div><div class="actions"><span class="warning">Запуск разрешён только для пустой базы. Установка не удаляет существующие данные.</span><button class="button" type="submit">Запустить установку →</button></div></div></section>
</form>
<section id="status" class="status" aria-live="polite"><div class="status-head"><div class="pill"><span class="dot"></span><b id="message">Подготовка…</b></div><span class="status-label">Deployment telemetry</span></div><div class="bar"><i></i></div><pre id="logs"></pre></section>
</div></main><footer class="footer"><span>WL Traders / Production installer</span><span>One-time session · No data leaves this server</span></footer></div>
<script>
const token=new URLSearchParams(location.search).get('token');
const form=document.getElementById('form'),statusBox=document.getElementById('status'),logs=document.getElementById('logs'),message=document.getElementById('message'),submitButton=form.querySelector('button[type="submit"]');
let timer=null;
function payload(){const f=new FormData(form),o={};for(const [k,v] of f.entries())o[k]=v;for(const k of ['create_swap','enable_firewall','install_backups','generate_test_data'])o[k]=form.elements[k].checked;return o}
async function poll(){try{const r=await fetch('/status?token='+encodeURIComponent(token)),s=await r.json();message.textContent=s.message;logs.textContent=s.logs.join('\n');logs.scrollTop=logs.scrollHeight;statusBox.className='status active '+s.phase;if(s.phase==='done'){clearInterval(timer);setTimeout(()=>location.href=s.app_url,3500)}if(s.phase==='failed')clearInterval(timer)}catch(e){message.textContent='Панель завершает работу…'}}
form.addEventListener('submit',async e=>{e.preventDefault();if(!confirm('Начать production-установку на этом сервере?'))return;submitButton.disabled=true;statusBox.classList.add('active');statusBox.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});const r=await fetch('/install?token='+encodeURIComponent(token),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload())});if(!r.ok){alert((await r.json()).error);submitButton.disabled=false;return}form.style.display='none';timer=setInterval(poll,1000);poll()});
document.querySelectorAll('.reveal-secret').forEach(button=>button.addEventListener('click',()=>{const input=button.previousElementSibling;const show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'HIDE':'SHOW';button.setAttribute('aria-label',show?'Скрыть пароль':'Показать пароль')}));
const sectionLinks=[...document.querySelectorAll('.progress a')];if('IntersectionObserver'in window){const observer=new IntersectionObserver(entries=>{entries.forEach(entry=>{if(entry.isIntersecting){sectionLinks.forEach(link=>link.classList.toggle('active',link.getAttribute('href')==='#'+entry.target.id))}})},{rootMargin:'-22% 0px -65%',threshold:0});document.querySelectorAll('.form-section').forEach(section=>observer.observe(section))}
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
