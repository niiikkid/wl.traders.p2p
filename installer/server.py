#!/usr/bin/env python3
"""Temporary one-time web installer for WL Traders on a fresh Ubuntu server."""

from __future__ import annotations

import argparse
import datetime as dt
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
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

REPO_ROOT = Path(__file__).resolve().parent.parent
INSTALL_LOG = Path("/var/log/wl-traders-installer.log")
SUPPORTED_UBUNTU_VERSION = "26.04"
MIN_MEMORY_BYTES = int(3.5 * 1024**3)
MIN_DISK_BYTES = 45 * 1024**3
INSTALL_STEPS = (
    "Проверка сервера",
    "Системные пакеты",
    "Подготовка проекта",
    "База данных",
    "Сборка приложения",
    "Службы и защита",
    "Финальная проверка",
)
STATE_LOCK = threading.Lock()
STATE: dict[str, Any] = {
    "phase": "ready",
    "message": "Установщик готов",
    "logs": [],
    "app_url": None,
    "error": None,
    "step": None,
    "step_index": 0,
    "step_total": len(INSTALL_STEPS),
    "progress": 0,
    "checks": [],
}


def redact_sensitive(message: str) -> str:
    pattern = re.compile(
        r"(?i)\b([A-Z0-9_]*(?:PASSWORD|TOKEN|SECRET|API_KEY)[A-Z0-9_]*)=(?:\"[^\"]*\"|'[^']*'|\S+)"
    )
    return pattern.sub(lambda match: f"{match.group(1)}=[скрыто]", message)


def add_log(message: str) -> None:
    clean = redact_sensitive(message.rstrip())
    if not clean:
        return
    timestamp = dt.datetime.now(dt.UTC).astimezone().strftime("%Y-%m-%d %H:%M:%S")
    line = f"[{timestamp}] {clean}"
    with STATE_LOCK:
        STATE["logs"].append(line)
        STATE["logs"] = STATE["logs"][-500:]
    try:
        INSTALL_LOG.parent.mkdir(parents=True, exist_ok=True)
        with INSTALL_LOG.open("a", encoding="utf-8") as handle:
            handle.write(line + "\n")
        os.chmod(INSTALL_LOG, 0o600)
    except OSError:
        pass


def set_state(**values: Any) -> None:
    with STATE_LOCK:
        STATE.update(values)


def set_progress(index: int, message: str | None = None) -> None:
    step = INSTALL_STEPS[index - 1]
    set_state(
        phase="installing",
        message=message or step,
        step=step,
        step_index=index,
        step_total=len(INSTALL_STEPS),
        progress=round((index - 1) / len(INSTALL_STEPS) * 100),
    )
    add_log(f"=== {index}/{len(INSTALL_STEPS)} · {step} ===")


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


def read_os_release(path: Path = Path("/etc/os-release")) -> dict[str, str]:
    values: dict[str, str] = {}
    try:
        for line in path.read_text(encoding="utf-8").splitlines():
            if "=" not in line or line.startswith("#"):
                continue
            key, value = line.split("=", 1)
            values[key] = value.strip().strip('"')
    except OSError:
        pass
    return values


def total_memory_bytes() -> int:
    try:
        for line in Path("/proc/meminfo").read_text(encoding="utf-8").splitlines():
            if line.startswith("MemTotal:"):
                return int(line.split()[1]) * 1024
    except (OSError, ValueError, IndexError):
        pass
    return 0


def environment_issues(
    *,
    os_id: str,
    version_id: str,
    cpu_count: int,
    memory_bytes: int,
    disk_bytes: int,
) -> list[str]:
    issues: list[str] = []
    if os_id != "ubuntu" or version_id != SUPPORTED_UBUNTU_VERSION:
        issues.append(
            f"Нужна Ubuntu {SUPPORTED_UBUNTU_VERSION}; обнаружено {os_id or 'unknown'} {version_id or 'unknown'}"
        )
    if cpu_count < 2:
        issues.append("Нужно минимум 2 vCPU")
    if memory_bytes < MIN_MEMORY_BYTES:
        issues.append("Нужно минимум 4 ГБ RAM")
    if disk_bytes < MIN_DISK_BYTES:
        issues.append("Нужен диск объёмом не менее 50 ГБ")
    return issues


def server_facts() -> dict[str, Any]:
    release = read_os_release()
    disk = shutil.disk_usage("/")
    cpu_count = os.cpu_count() or 0
    memory = total_memory_bytes()
    issues = environment_issues(
        os_id=release.get("ID", ""),
        version_id=release.get("VERSION_ID", ""),
        cpu_count=cpu_count,
        memory_bytes=memory,
        disk_bytes=disk.total,
    )
    return {
        "os": release.get("PRETTY_NAME", "Не определена"),
        "cpu": cpu_count,
        "memory_gb": round(memory / 1024**3, 1),
        "disk_gb": round(disk.total / 1024**3, 1),
        "disk_free_gb": round(disk.free / 1024**3, 1),
        "issues": issues,
    }


def port_is_available(port: int) -> bool:
    for host, family in (("0.0.0.0", socket.AF_INET), ("::", socket.AF_INET6)):
        try:
            with socket.socket(family, socket.SOCK_STREAM) as probe:
                probe.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
                probe.bind((host, port))
        except OSError:
            return False
    return True


def only_default_nginx_site_enabled() -> bool:
    enabled = Path("/etc/nginx/sites-enabled")
    try:
        names = {path.name for path in enabled.iterdir()}
    except OSError:
        return False
    active = subprocess.run(
        ["systemctl", "is-active", "--quiet", "nginx"],
        check=False,
        capture_output=True,
    ).returncode == 0
    return active and names <= {"default"}


def run_preflight(settings: dict[str, Any]) -> list[dict[str, str]]:
    facts = server_facts()
    if facts["issues"]:
        raise RuntimeError("; ".join(facts["issues"]))

    target: Path = settings["install_path"]
    staging = target.parent / f".{target.name}.installing"
    protected_paths = (
        target,
        Path("/etc/nginx/sites-available/wl-traders"),
        Path("/etc/systemd/system/wl-traders-horizon.service"),
        Path("/etc/cron.d/wl-traders"),
    )
    existing = [str(path) for path in protected_paths if path.exists() or path.is_symlink()]
    if existing:
        raise RuntimeError("Найдена предыдущая установка: " + ", ".join(existing))
    if staging.exists() and not (staging / ".wl-traders-installer-id").is_file():
        raise RuntimeError(f"Временная папка {staging} существует и не принадлежит установщику")
    if not port_is_available(80) and not only_default_nginx_site_enabled():
        raise RuntimeError("TCP-порт 80 уже занят. Остановите использующую его службу и повторите установку")
    if shutil.disk_usage(target.parent if target.parent.exists() else "/").free < 10 * 1024**3:
        raise RuntimeError("Для установки нужно не менее 10 ГБ свободного места")
    try:
        socket.getaddrinfo("archive.ubuntu.com", 443, type=socket.SOCK_STREAM)
        socket.getaddrinfo("github.com", 443, type=socket.SOCK_STREAM)
    except OSError as exc:
        raise RuntimeError("Сервер не может разрешить адреса Ubuntu/GitHub. Проверьте DNS и сеть") from exc

    return [
        {"name": "Система", "value": facts["os"]},
        {"name": "Ресурсы", "value": f"{facts['cpu']} vCPU · {facts['memory_gb']} ГБ RAM · {facts['disk_free_gb']} ГБ свободно"},
        {"name": "Порт 80", "value": "свободен"},
        {"name": "Данные", "value": "предыдущая установка не найдена"},
    ]


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
    if parsed.path not in {"", "/"} or parsed.query or parsed.fragment:
        raise ValueError("Укажите только адрес приложения, без пути, параметров и #фрагмента")
    try:
        port = parsed.port
    except ValueError as exc:
        raise ValueError("В адресе приложения указан некорректный порт") from exc
    if port not in {None, 80, 443}:
        raise ValueError("Приложение должно использовать стандартный порт 80 или 443")
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
    client_body_timeout 60s;
    client_header_timeout 20s;
    keepalive_timeout 65s;
    access_log /var/log/nginx/wl-traders-access.log;
    error_log /var/log/nginx/wl-traders-error.log warn;

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

    location ~ /\\.(?!well-known(?:/|$)) {{
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
After=network-online.target mysql.service redis-server.service
Wants=network-online.target mysql.service redis-server.service

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
NoNewPrivileges=true
PrivateTmp=true
ProtectHome=true
UMask=0027
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
"""
    Path("/etc/systemd/system/wl-traders-horizon.service").write_text(horizon, encoding="utf-8")

    cron = (
        "SHELL=/bin/bash\nPATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\nMAILTO=\"\"\n"
        f"* * * * * www-data cd {app_path} && /usr/bin/php artisan schedule:run --no-ansi "
        ">> /var/log/wl-traders-scheduler.log 2>&1\n"
        f"*/5 * * * * www-data cd {app_path} && /usr/bin/php artisan horizon:snapshot --no-ansi "
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

    logrotate = """/var/log/wl-traders-scheduler.log /var/log/wl-traders-backup.log /var/log/wl-traders-installer.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0640 www-data adm
}
"""
    Path("/etc/logrotate.d/wl-traders").write_text(logrotate, encoding="utf-8")


def install_backup(target: Path, db_name: str, retention_days: int) -> None:
    backup = f"""#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
DEST=/var/backups/wl-traders
STAMP=$(date +%Y%m%d-%H%M%S)
install -d -m 0700 "$DEST"
DB_TMP="$DEST/.database-$STAMP.sql.gz.tmp"
STORAGE_TMP="$DEST/.storage-$STAMP.tar.gz.tmp"
trap 'rm -f "$DB_TMP" "$STORAGE_TMP"' EXIT
mysqldump --single-transaction --quick --routines --events --triggers {quote(db_name)} | gzip -9 > "$DB_TMP"
tar -C {quote(target)} -czf "$STORAGE_TMP" storage/app
test -s "$DB_TMP"
test -s "$STORAGE_TMP"
mv "$DB_TMP" "$DEST/database-$STAMP.sql.gz"
mv "$STORAGE_TMP" "$DEST/storage-$STAMP.tar.gz"
find "$DEST" -type f -mtime +{retention_days} -delete
"""
    path = Path("/usr/local/sbin/wl-traders-backup")
    path.write_text(backup, encoding="utf-8")
    os.chmod(path, 0o700)
    Path("/etc/cron.d/wl-traders-backup").write_text(
        "SHELL=/bin/bash\nPATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\nMAILTO=\"\"\n"
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
    if password != str(raw.get("admin_password_confirmation", "")):
        raise ValueError("Пароль администратора и подтверждение не совпадают")

    timezone = str(raw.get("timezone", "Europe/Moscow")).strip()
    try:
        ZoneInfo(timezone)
    except ZoneInfoNotFoundError as exc:
        raise ValueError("Указан неизвестный часовой пояс") from exc

    locale = str(raw.get("locale", "ru")).strip()
    if locale not in {"ru", "en"}:
        raise ValueError("Язык интерфейса должен быть ru или en")

    app_name = str(raw["app_name"]).strip()
    if len(app_name) > 80:
        raise ValueError("Название приложения не должно быть длиннее 80 символов")

    db_password = str(raw.get("db_password", "")) or secrets.token_urlsafe(24)
    if len(db_password) < 16:
        raise ValueError("Пароль базы данных должен содержать минимум 16 символов или оставьте поле пустым")
    settings = {
        "app_name": app_name,
        "app_url": validate_url(str(raw["app_url"]).strip()),
        "install_path": validate_path(str(raw["install_path"]).strip()),
        "timezone": timezone,
        "locale": locale,
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


def cleanup_failed_install(
    *,
    target: Path | None,
    staging: Path | None,
    marker: str,
    db_name: str | None,
    db_user: str | None,
    database_created: bool,
    user_created: bool,
    system_files_created: bool,
) -> None:
    add_log("Отменяю только изменения, созданные этой попыткой…")
    if system_files_created:
        subprocess.run(["systemctl", "disable", "--now", "wl-traders-horizon"], capture_output=True, text=True)
        for path in (
            Path("/etc/nginx/sites-enabled/wl-traders"),
            Path("/etc/nginx/sites-available/wl-traders"),
            Path("/etc/systemd/system/wl-traders-horizon.service"),
            Path("/etc/cron.d/wl-traders"),
            Path("/etc/cron.d/wl-traders-backup"),
            Path("/usr/local/sbin/wl-traders-backup"),
        ):
            path.unlink(missing_ok=True)
        subprocess.run(["systemctl", "daemon-reload"], capture_output=True, text=True)

    for candidate in (staging, target):
        if not candidate or not candidate.exists():
            continue
        marker_path = candidate / ".wl-traders-installer-id"
        try:
            owned = marker_path.read_text(encoding="utf-8").strip() == marker
        except OSError:
            owned = False
        if owned:
            shutil.rmtree(candidate, ignore_errors=True)

    if database_created and db_name:
        try:
            run_mysql(f"DROP DATABASE IF EXISTS `{db_name}`;")
        except Exception as exc:  # noqa: BLE001
            add_log(f"Не удалось удалить незавершённую базу: {exc}")
    if user_created and db_user:
        try:
            run_mysql(f"DROP USER IF EXISTS {sql_string(db_user)}@'localhost'; FLUSH PRIVILEGES;")
        except Exception as exc:  # noqa: BLE001
            add_log(f"Не удалось удалить незавершённого пользователя БД: {exc}")


def perform_install(raw_settings: dict[str, Any], server: ThreadingHTTPServer) -> None:
    target: Path | None = None
    staging: Path | None = None
    marker = secrets.token_hex(16)
    db_name: str | None = None
    db_user: str | None = None
    database_created = False
    user_created = False
    system_files_created = False
    firewall_enabled = False

    try:
        settings = normalize_settings(raw_settings)
        settings["installer_port"] = int(server.server_port)
        target = settings["install_path"]
        assert isinstance(target, Path)
        staging = target.parent / f".{target.name}.installing"
        set_state(
            phase="installing",
            message="Установка началась",
            app_url=settings["app_url"],
            error=None,
            logs=[],
            checks=[],
        )

        set_progress(1)
        checks = run_preflight(settings)
        set_state(checks=checks)
        add_log("Сервер подходит. Существующие данные и службы WL Traders не найдены.")

        set_progress(2)
        apt_options = "-o Acquire::Retries=3 -o DPkg::Lock::Timeout=180"
        run(f"export DEBIAN_FRONTEND=noninteractive; apt-get {apt_options} update")
        run(
            f"export DEBIAN_FRONTEND=noninteractive; apt-get {apt_options} install -y --no-install-recommends "
            "nginx mysql-server redis-server composer nodejs npm rsync unzip curl ufw cron logrotate ca-certificates "
            "php-cli php-fpm php-mysql php-redis php-bcmath php-gmp php-mbstring "
            "php-xml php-curl php-zip php-gd php-intl"
        )
        run("systemctl enable --now mysql redis-server cron")
        run("php -r 'exit(version_compare(PHP_VERSION, \"8.3.0\", \">=\") ? 0 : 1);'")
        run(
            "php -r '$required=[\"bcmath\",\"curl\",\"dom\",\"fileinfo\",\"gd\",\"gmp\",\"intl\",\"mbstring\",\"pdo_mysql\",\"redis\",\"xml\",\"zip\"]; "
            "$missing=array_values(array_filter($required,fn($ext)=>!extension_loaded($ext))); "
            "if($missing){fwrite(STDERR,\"Отсутствуют PHP-модули: \".implode(\", \",$missing).PHP_EOL);exit(1);}'"
        )

        if settings["create_swap"]:
            swap_exists = subprocess.run(["swapon", "--show=NAME"], capture_output=True, text=True).stdout.strip()
            if not swap_exists:
                add_log("Создаю swap-файл 2 ГБ…")
                run("fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile")
                fstab = Path("/etc/fstab")
                if "/swapfile none swap sw 0 0" not in fstab.read_text(encoding="utf-8"):
                    with fstab.open("a", encoding="utf-8") as handle:
                        handle.write("/swapfile none swap sw 0 0\n")

        set_progress(3)
        if staging.exists():
            shutil.rmtree(staging)
        copy_project(staging)
        (staging / ".wl-traders-installer-id").write_text(marker + "\n", encoding="utf-8")
        for apple_double in staging.rglob("._*"):
            if apple_double.is_file():
                apple_double.unlink()
        run(f"chown -R www-data:www-data {quote(staging)}")
        write_env(staging, settings)
        shutil.chown(staging / ".env", user="www-data", group="www-data")

        set_progress(4)
        db_name = settings["db_name"]
        db_user = settings["db_user"]
        assert isinstance(db_name, str) and isinstance(db_user, str)
        database_exists = run_mysql(
            "SELECT COUNT(*) FROM information_schema.schemata "
            f"WHERE schema_name={sql_string(db_name)};"
        )
        user_exists = run_mysql(
            "SELECT COUNT(*) FROM mysql.user "
            f"WHERE user={sql_string(db_user)} AND host='localhost';"
        )
        if int(database_exists or "0") > 0 or int(user_exists or "0") > 0:
            raise RuntimeError(
                f"База или пользователь БД с именем {db_name}/{db_user} уже существует. "
                "Выберите другие имена; существующие данные не изменены"
            )
        run_mysql(f"CREATE DATABASE `{db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")
        database_created = True
        run_mysql(
            f"CREATE USER {sql_string(db_user)}@'localhost' IDENTIFIED BY {sql_string(settings['db_password'])};"
        )
        user_created = True
        run_mysql(
            f"GRANT ALL PRIVILEGES ON `{db_name}`.* TO {sql_string(db_user)}@'localhost'; FLUSH PRIVILEGES;"
        )

        set_progress(5)
        run("install -d -o www-data -g www-data -m 0755 /var/www/.cache/composer /var/www/.npm")
        run(
            f"sudo -u www-data env HOME=/var/www COMPOSER_NO_AUDIT=0 composer install --working-dir={quote(staging)} "
            "--no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress"
        )
        run(f"sudo -u www-data composer check-platform-reqs --working-dir={quote(staging)} --no-dev")
        run(f"sudo -u www-data /usr/bin/php {quote(staging / 'artisan')} ziggy:generate resources/js/ziggy-routes.js")
        run(f"sudo -u www-data env HOME=/var/www npm --prefix {quote(staging)} ci --no-audit --no-fund")
        run(f"sudo -u www-data env HOME=/var/www npm --prefix {quote(staging)} run build")
        run(f"sudo -u www-data /usr/bin/php {quote(staging / 'artisan')} key:generate --force")
        run(f"sudo -u www-data /usr/bin/php {quote(staging / 'artisan')} system:install --no-interaction")
        run(
            f"sudo -u www-data /usr/bin/php {quote(staging / 'artisan')} "
            f"users:passwords:reset-admin -- admin {quote(settings['admin_password'])}",
            display="php artisan users:passwords:reset-admin admin [пароль скрыт]",
        )
        shutil.rmtree(staging / "node_modules", ignore_errors=True)
        staging.rename(target)
        staging = None
        run(f"sudo -u www-data /usr/bin/php {quote(target / 'artisan')} storage:link --force")

        set_progress(6)
        php_version = subprocess.check_output(
            ["php", "-r", "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;"], text=True
        ).strip()
        install_system_files(target, settings, php_version)
        system_files_created = True
        if settings["install_backups"]:
            install_backup(target, db_name, settings["backup_retention_days"])

        run("systemctl daemon-reload")
        run("nginx -t")
        php_fpm_service = f"php{php_version}-fpm"
        run(f"systemctl enable --now {quote(php_fpm_service)} nginx mysql redis-server wl-traders-horizon cron")
        run(f"systemctl restart {quote(php_fpm_service)} wl-traders-horizon")
        run("systemctl reload nginx")
        run(f"sudo -u www-data /usr/bin/php {quote(target / 'artisan')} optimize")

        if settings["enable_firewall"]:
            add_log("Включаю firewall: SSH, HTTP и временно панель установки…")
            installer_port = int(settings["installer_port"])
            run(
                "ufw allow OpenSSH && ufw allow 'Nginx HTTP' && "
                f"ufw allow {installer_port}/tcp && ufw --force enable"
            )
            firewall_enabled = True

        if settings["generate_test_data"]:
            add_log("Запускаю генерацию тестовых данных…")
            run(
                f"sudo -u www-data /usr/bin/php {quote(target / 'artisan')} "
                "dev:test-data:generate --force --no-interaction"
            )

        set_progress(7)
        host = urlparse(settings["app_url"]).hostname or public_ip()
        run("nginx -t")
        run(f"systemctl is-active {quote(php_fpm_service)} nginx mysql redis-server wl-traders-horizon cron")
        run("redis-cli ping")
        run(f"sudo -u www-data /usr/bin/php {quote(target / 'artisan')} horizon:status")
        run(f"sudo -u www-data /usr/bin/php {quote(target / 'artisan')} schedule:list --no-ansi")
        run(f"curl --fail --silent --show-error --max-time 20 -H {quote('Host: ' + host)} http://127.0.0.1/up")
        run(f"curl --fail --silent --show-error --max-time 20 -o /dev/null -H {quote('Host: ' + host)} http://127.0.0.1/")
        if settings["install_backups"]:
            run("/usr/local/sbin/wl-traders-backup")
            backups = list(Path("/var/backups/wl-traders").glob("*"))
            if len(backups) < 2 or any(path.stat().st_size == 0 for path in backups):
                raise RuntimeError("Проверочная резервная копия не создана")

        (target / ".wl-traders-installer-id").unlink(missing_ok=True)
        set_state(
            phase="done",
            message="WL Traders установлен и проверен",
            progress=100,
            error=None,
            checks=checks
            + [
                {"name": "Сайт", "value": "отвечает"},
                {"name": "Очереди", "value": "Horizon работает"},
                {"name": "Планировщик", "value": "расписание загружено"},
                {"name": "Бэкапы", "value": "проверены" if settings["install_backups"] else "отключены"},
            ],
        )
        add_log("Установка и основные production-проверки завершены.")
        if firewall_enabled:
            installer_port = int(settings["installer_port"])
            run(
                "systemd-run --unit=wl-traders-installer-firewall-cleanup "
                "--on-active=2min /usr/sbin/ufw --force delete allow "
                f"{installer_port}/tcp"
            )
        threading.Timer(110, server.shutdown).start()
    except Exception as exc:  # noqa: BLE001 - installer must surface all failures
        add_log(f"ОШИБКА: {exc}")
        cleanup_failed_install(
            target=target,
            staging=staging,
            marker=marker,
            db_name=db_name,
            db_user=db_user,
            database_created=database_created,
            user_created=user_created,
            system_files_created=system_files_created,
        )
        set_state(
            phase="failed",
            message="Установка остановлена и безопасно отменена",
            error=str(exc),
            progress=0,
        )
        threading.Timer(3600, server.shutdown).start()


PAGE = r"""<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#000000">
<title>Установка WL Traders</title>
<style>
:root{color-scheme:dark;--void:#000;--surface:#07080b;--surface-2:#0b0d11;--line:rgba(240,240,250,.14);--line-strong:rgba(240,240,250,.28);--text:#f0f0fa;--muted:rgba(240,240,250,.58);--faint:rgba(240,240,250,.38);--danger:#ff7b82;--warning:#e6c98a;--success:#dfffe8;--display:"SFMono-Regular",Consolas,"Liberation Mono",monospace;--body:"Helvetica Neue",Helvetica,Arial,sans-serif;--max:1260px}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;min-width:320px;background:var(--void);color:var(--text);font:15px/1.55 var(--body);-webkit-font-smoothing:antialiased}button,input,select{font:inherit}button{color:inherit}::selection{background:var(--text);color:var(--void)}
.shell{position:relative;isolation:isolate;min-height:100svh;overflow:hidden}.space{position:fixed;z-index:-2;inset:0;pointer-events:none;background:radial-gradient(circle at 76% 18%,rgba(112,126,158,.12),transparent 26rem),radial-gradient(circle at 18% 92%,rgba(255,255,255,.035),transparent 28rem),#000}.space:before{content:"";position:absolute;width:min(74vw,980px);aspect-ratio:1;right:-32vw;top:-18vw;border:1px solid rgba(240,240,250,.1);border-radius:50%;box-shadow:0 0 0 110px rgba(240,240,250,.012),0 0 0 220px rgba(240,240,250,.008)}.space:after{content:"";position:absolute;inset:0;opacity:.55;background-image:radial-gradient(circle,#fff 0 1px,transparent 1.25px),radial-gradient(circle,rgba(255,255,255,.55) 0 1px,transparent 1.2px);background-position:0 0,46px 28px;background-size:151px 151px,233px 233px;mask-image:linear-gradient(to bottom,black,transparent 72%)}
.topbar{position:sticky;top:0;z-index:20;border-bottom:1px solid var(--line);background:rgba(0,0,0,.82);backdrop-filter:blur(18px)}.topbar-inner{width:min(calc(100% - 48px),var(--max));height:72px;margin:auto;display:flex;align-items:center;justify-content:space-between;gap:24px}.brand{display:flex;align-items:center;gap:13px}.brand-mark{width:36px;height:36px;display:grid;place-items:center;border:1px solid var(--text);background:var(--text);color:#000;font:600 11px/1 var(--display);letter-spacing:-.06em}.brand-name{font:500 14px/1.2 var(--display);letter-spacing:.08em;text-transform:uppercase}.brand-name small{display:block;margin-top:5px;color:var(--faint);font-size:9px;font-weight:400;letter-spacing:.16em}.session{display:flex;align-items:center;gap:10px;color:var(--muted);font:10px/1 var(--display);letter-spacing:.12em;text-transform:uppercase}.session-dot{width:6px;height:6px;border-radius:50%;background:var(--text);box-shadow:0 0 0 5px rgba(240,240,250,.07)}
.layout{width:min(calc(100% - 48px),var(--max));margin:0 auto;padding:54px 0 88px;display:grid;grid-template-columns:260px minmax(0,1fr);gap:clamp(42px,6vw,92px)}.rail{align-self:start;position:sticky;top:110px}.rail-kicker,.kicker{color:var(--muted);font:10px/1.4 var(--display);letter-spacing:.18em;text-transform:uppercase}.rail h1{margin:17px 0 14px;font:300 clamp(31px,3.2vw,46px)/1.04 var(--display);letter-spacing:-.065em}.rail-copy{margin:0;color:var(--muted);font-size:14px;max-width:230px}.progress{margin:38px 0 0;padding:0;list-style:none;border-top:1px solid var(--line)}.progress a{min-height:52px;display:grid;grid-template-columns:28px 1fr;align-items:center;border-bottom:1px solid var(--line);color:var(--faint);text-decoration:none;font:10px/1.2 var(--display);letter-spacing:.1em;text-transform:uppercase;transition:color .18s ease,border-color .18s ease}.progress a:hover,.progress a.active{color:var(--text);border-color:var(--line-strong)}.progress span{color:var(--faint)}.security-note{margin-top:32px;padding:16px 0;border-block:1px solid var(--line);color:var(--muted);font-size:12px}.security-note b{display:block;margin-bottom:6px;color:var(--warning);font:500 10px/1.3 var(--display);letter-spacing:.12em;text-transform:uppercase}
.content{min-width:0}.intro{padding:4px 0 34px;border-bottom:1px solid var(--line)}.intro-row{display:flex;align-items:flex-end;justify-content:space-between;gap:30px}.intro h2{max-width:720px;margin:13px 0 0;font:300 clamp(37px,5vw,68px)/.98 var(--display);letter-spacing:-.075em}.intro h2 span{color:rgba(240,240,250,.48)}.system-tag{flex:0 0 auto;margin-bottom:4px;padding:8px 10px;border:1px solid var(--line);color:var(--muted);font:9px/1 var(--display);letter-spacing:.12em;text-transform:uppercase}.server-facts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1px;margin-top:27px;background:var(--line);border:1px solid var(--line)}.server-fact{min-width:0;padding:13px 14px;background:#050609}.server-fact b{display:block;color:var(--faint);font:8px/1.3 var(--display);letter-spacing:.1em;text-transform:uppercase}.server-fact span{display:block;margin-top:7px;overflow:hidden;text-overflow:ellipsis;color:var(--text);font:11px/1.35 var(--display);white-space:nowrap}
.form-section{scroll-margin-top:100px;padding:42px 0;border-bottom:1px solid var(--line)}.section-head{display:grid;grid-template-columns:46px minmax(0,1fr);gap:16px;margin-bottom:27px}.section-number{padding-top:4px;color:var(--faint);font:10px/1 var(--display);letter-spacing:.12em}.section-head h3{margin:0;font:400 22px/1.2 var(--display);letter-spacing:-.035em}.section-head p{margin:8px 0 0;color:var(--muted);font-size:14px;max-width:660px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:19px 20px}.full{grid-column:1/-1}label.field{display:grid;gap:8px;color:rgba(240,240,250,.84);font-size:12px}.label-row{display:flex;align-items:center;justify-content:space-between;gap:10px}.optional{color:var(--faint);font:9px/1 var(--display);letter-spacing:.1em;text-transform:uppercase}.field small,.hint{color:var(--faint);font-size:11px}.control{position:relative}input:not([type=checkbox]),select{width:100%;min-height:49px;border:1px solid var(--line);border-radius:2px;background:rgba(240,240,250,.025);color:var(--text);padding:13px 14px;outline:none;transition:border-color .18s ease,background .18s ease}select{appearance:none;background-image:linear-gradient(45deg,transparent 50%,var(--faint) 50%),linear-gradient(135deg,var(--faint) 50%,transparent 50%);background-position:calc(100% - 18px) 21px,calc(100% - 13px) 21px;background-size:5px 5px,5px 5px;background-repeat:no-repeat}input:hover,select:hover{border-color:var(--line-strong)}input:focus,select:focus{border-color:var(--text);background:rgba(240,240,250,.045);box-shadow:0 0 0 3px rgba(240,240,250,.07)}input::placeholder{color:rgba(240,240,250,.25)}.secret{padding-right:78px!important;font-family:var(--display)}.reveal-secret{position:absolute;right:1px;top:1px;width:74px;height:47px;border:0;border-left:1px solid var(--line);background:transparent;cursor:pointer;color:var(--muted);font:8px/1 var(--display);letter-spacing:.06em}.reveal-secret:hover{color:var(--text);background:rgba(240,240,250,.04)}.advanced{margin-top:25px;border:1px solid var(--line);background:#050609}.advanced summary{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:17px 19px;cursor:pointer;color:var(--text);font:11px/1.3 var(--display);list-style:none}.advanced summary::-webkit-details-marker{display:none}.advanced summary:after{content:"+";color:var(--muted);font-size:18px}.advanced[open] summary:after{content:"−"}.advanced-body{padding:20px;border-top:1px solid var(--line)}.generate-secret{position:absolute;right:75px;top:1px;width:94px;height:47px;border:0;border-left:1px solid var(--line);background:transparent;color:var(--muted);cursor:pointer;font:8px/1 var(--display);letter-spacing:.06em}.generate-secret:hover{color:var(--text);background:rgba(240,240,250,.04)}.secret.has-generator{padding-right:170px!important}
.integration-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1px;background:var(--line);border:1px solid var(--line)}.integration{min-width:0;padding:21px;background:#050609}.integration-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:17px}.integration h4{margin:0;font:400 14px/1.25 var(--display);letter-spacing:-.02em}.integration p{margin:6px 0 0;color:var(--faint);font-size:11px}.badge{flex:0 0 auto;padding:5px 7px;border:1px solid var(--line);color:var(--faint);font:8px/1 var(--display);letter-spacing:.1em;text-transform:uppercase}.integration .field+.field{margin-top:13px}.integration.full{grid-column:1/-1}.no-config{margin:18px 0 0;color:var(--muted);font-size:12px}
.checks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1px;background:var(--line);border:1px solid var(--line)}.check{position:relative;min-height:118px;display:flex;align-items:flex-start;gap:13px;padding:20px;background:#050609;cursor:pointer;transition:background .18s ease}.check:hover{background:#0a0b0f}.check input{appearance:none;flex:0 0 auto;width:18px;height:18px;margin:1px 0 0;border:1px solid var(--line-strong);background:#000;display:grid;place-items:center}.check input:checked{border-color:var(--text);background:var(--text)}.check input:checked:after{content:"";width:8px;height:4px;border-left:2px solid #000;border-bottom:2px solid #000;transform:translateY(-1px) rotate(-45deg)}.check b{display:block;color:var(--text);font:400 13px/1.25 var(--display)}.check small{display:block;margin-top:8px;color:var(--faint);font-size:11px;line-height:1.45}.retention{margin-top:19px;max-width:320px}
.launch{padding:34px 0 0}.launch-panel{position:relative;overflow:hidden;border:1px solid var(--line-strong);background:#050609;padding:clamp(24px,4vw,38px)}.launch-panel:after{content:"DEPLOY";position:absolute;right:-10px;bottom:-29px;color:rgba(240,240,250,.035);font:300 clamp(72px,11vw,138px)/1 var(--display);letter-spacing:-.09em;pointer-events:none}.launch-copy,.actions{position:relative;z-index:1}.launch-copy h3{margin:0;font:300 clamp(25px,3vw,38px)/1.05 var(--display);letter-spacing:-.05em}.launch-copy p{max-width:650px;margin:12px 0 0;color:var(--muted);font-size:13px}.review{position:relative;z-index:1;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1px;margin-top:25px;border:1px solid var(--line);background:var(--line)}.review div{padding:13px;background:#030405}.review b{display:block;color:var(--faint);font:8px/1 var(--display);letter-spacing:.1em;text-transform:uppercase}.review span{display:block;margin-top:7px;color:var(--text);font:11px/1.35 var(--display)}.actions{margin-top:27px;display:flex;align-items:center;justify-content:space-between;gap:22px}.warning{display:flex;align-items:flex-start;gap:9px;color:var(--warning);font-size:11px;max-width:470px}.warning:before{content:"!";flex:0 0 auto;width:17px;height:17px;display:grid;place-items:center;border:1px solid currentColor;font:600 9px/1 var(--display)}.button{min-height:48px;flex:0 0 auto;border:1px solid var(--text);border-radius:2px;padding:0 22px;background:var(--text);color:#000;cursor:pointer;font:500 10px/1 var(--display);letter-spacing:.13em;text-transform:uppercase;transition:background .18s ease,color .18s ease,transform .18s ease}.button:hover{background:#fff;transform:translateY(-1px)}.button:disabled{opacity:.38;cursor:not-allowed;transform:none}.button.secondary{background:transparent;color:var(--text)}
.status{display:none;margin-top:30px;border:1px solid var(--line-strong);background:#030405}.status.active{display:block}.status-head{min-height:67px;padding:0 20px;display:flex;align-items:center;justify-content:space-between;gap:18px;border-bottom:1px solid var(--line)}.pill{display:flex;align-items:center;gap:11px;font:400 12px/1 var(--display);letter-spacing:.04em}.dot{width:7px;height:7px;border-radius:50%;background:var(--text);box-shadow:0 0 0 6px rgba(240,240,250,.06);animation:pulse 1.7s infinite}.status.done .dot{background:var(--success)}.status.failed .dot{background:var(--danger)}.status-label{color:var(--faint);font:9px/1 var(--display);letter-spacing:.13em;text-transform:uppercase}.bar{height:3px;background:rgba(240,240,250,.08);overflow:hidden}.bar i{display:block;width:0;height:100%;background:var(--text);transition:width .35s ease}.status.done .bar i{background:var(--success)}.status.failed .bar i{background:var(--danger)}.status-details{padding:18px 20px;border-bottom:1px solid var(--line);color:var(--muted);font-size:12px}.result-actions{display:none;gap:12px;padding:20px;border-bottom:1px solid var(--line)}.status.done .result-actions{display:flex}.technical summary{padding:15px 20px;cursor:pointer;color:var(--faint);font:9px/1 var(--display);letter-spacing:.12em;text-transform:uppercase}.technical[open] summary{border-bottom:1px solid var(--line)}pre{height:300px;margin:0;overflow:auto;padding:20px;color:rgba(240,240,250,.68);background:#000;font:11px/1.65 var(--display);white-space:pre-wrap;word-break:break-word}.status.done pre{color:rgba(223,255,232,.72)}.status.failed pre{color:rgba(255,123,130,.82)}
.footer{width:min(calc(100% - 48px),var(--max));margin:auto;padding:28px 0 42px;border-top:1px solid var(--line);display:flex;justify-content:space-between;gap:24px;color:var(--faint);font:9px/1.4 var(--display);letter-spacing:.1em;text-transform:uppercase}
@keyframes pulse{50%{opacity:.35}}@keyframes scan{0%{transform:translateX(-110%)}100%{transform:translateX(310%)}}
:focus-visible{outline:2px solid var(--text);outline-offset:3px}
@media(max-width:920px){.layout{grid-template-columns:1fr;padding-top:38px}.rail{position:static}.rail-copy{max-width:620px}.progress{display:grid;grid-template-columns:repeat(4,1fr);margin-top:25px}.progress a{padding:0 8px}.security-note{margin-top:24px}.intro-row{align-items:flex-start}.system-tag{display:none}}
@media(max-width:640px){.topbar-inner,.layout,.footer{width:min(calc(100% - 28px),var(--max))}.topbar-inner{height:64px}.session span:last-child{display:none}.layout{padding:30px 0 58px;gap:34px}.rail h1{font-size:34px}.progress{grid-template-columns:repeat(2,1fr)}.intro h2{font-size:39px}.grid,.integration-grid,.checks{grid-template-columns:1fr}.integration.full{grid-column:auto}.section-head{grid-template-columns:32px 1fr}.form-section{padding:34px 0}.actions{align-items:stretch;flex-direction:column}.button{width:100%}.status-head{align-items:flex-start;flex-direction:column;padding-block:17px}.footer{flex-direction:column}}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*,*:before,*:after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}}
</style>
</head>
<body><div class="shell"><div class="space" aria-hidden="true"></div>
<header class="topbar"><div class="topbar-inner"><div class="brand"><div class="brand-mark">WL</div><div class="brand-name">WL Traders<small>Установка на сервер</small></div></div><div class="session"><span class="session-dot"></span><span>Одноразовая ссылка</span></div></div></header>
<main class="layout">
<aside class="rail"><div class="rail-kicker">Установка · 4 раздела</div><h1>Настройка перед установкой.</h1><p class="rail-copy">Заполните основные параметры. Большинство значений уже подходят для обычного сервера — их можно не менять.</p><nav aria-label="Разделы установщика"><ol class="progress"><li><a class="active" href="#application"><span>01</span>Приложение</a></li><li><a href="#database"><span>02</span>База данных</a></li><li><a href="#integrations"><span>03</span>Дополнения</a></li><li><a href="#server"><span>04</span>Сервер</a></li></ol></nav><div class="security-note"><b>Важно</b>Эта страница временная и работает без HTTPS. Открывайте её только со своего компьютера и никому не отправляйте ссылку.</div></aside>
<div class="content"><header class="intro"><div class="intro-row"><div><span class="kicker">Шаг 1 · Заполните настройки</span><h2>Установка WL Traders<br><span>на ваш Ubuntu‑сервер.</span></h2></div><span class="system-tag">Ubuntu 26.04</span></div><div class="server-facts"><div class="server-fact"><b>Система</b><span>__SERVER_OS__</span></div><div class="server-fact"><b>Процессор</b><span>__SERVER_CPU__ vCPU</span></div><div class="server-fact"><b>Память</b><span>__SERVER_MEMORY__ ГБ</span></div><div class="server-fact"><b>Свободно</b><span>__SERVER_DISK_FREE__ ГБ</span></div></div></header>
<form id="form">
<section class="form-section" id="application"><div class="section-head"><span class="section-number">01</span><div><h3>Основные настройки</h3><p>Название и адрес, по которому будет открываться WL Traders. Если домена пока нет, оставьте IP‑адрес сервера.</p></div></div><div class="grid">
<label class="field"><span>Название</span><input name="app_name" value="WL Traders" required></label>
<label class="field"><span>Адрес приложения</span><input name="app_url" value="http://__SERVER_IP__" required inputmode="url"></label>
<label class="field"><span>Папка на сервере</span><input class="secret" name="install_path" value="/var/www/wl-traders" required><small>Куда установщик скопирует файлы WL Traders.</small></label>
<label class="field"><span>Часовой пояс</span><select name="timezone"><option>Europe/Moscow</option><option>UTC</option><option>Asia/Almaty</option><option>Asia/Dubai</option></select><small>Используется для времени сделок, отчётов и журналов.</small></label>
<label class="field"><span>Язык интерфейса</span><select name="locale"><option value="ru">Русский</option><option value="en">English</option></select></label>
<label class="field"><span>Срок входа без повторной авторизации, минут</span><input name="session_lifetime" type="number" value="10080" min="60" max="43200"><small>10080 минут — это 7 дней.</small></label>
<label class="field"><span>Максимальный размер файла, МБ</span><input name="upload_limit_mb" type="number" value="64" min="2" max="512"><small>Ограничение для чеков, выписок и других загрузок.</small></label>
<label class="field"><span>Пароль администратора</span><span class="control"><input class="secret has-generator" name="admin_password" type="password" minlength="12" required autocomplete="new-password"><button class="generate-secret" type="button">Создать</button><button class="reveal-secret" type="button" aria-label="Показать пароль">Показать</button></span><small>Минимум 12 символов · логин после установки: admin</small></label>
<label class="field"><span>Повторите пароль</span><span class="control"><input class="secret" name="admin_password_confirmation" type="password" minlength="12" required autocomplete="new-password"><button class="reveal-secret" type="button" aria-label="Показать пароль">Показать</button></span><small>Защищает от случайной опечатки.</small></label>
</div></section>
<section class="form-section" id="database"><div class="section-head"><span class="section-number">02</span><div><h3>База данных</h3><p>Здесь будут храниться пользователи, сделки и настройки. Рекомендуемые названия уже заполнены — обычно их менять не нужно.</p></div></div><div class="grid">
<label class="field"><span>Название базы</span><input class="secret" name="db_name" value="wl_traders" pattern="[A-Za-z0-9_]+" required></label>
<label class="field"><span>Пользователь</span><input class="secret" name="db_user" value="wl_traders" pattern="[A-Za-z0-9_]+" required></label>
<label class="field full"><span class="label-row"><span>Пароль базы данных</span><span class="optional">Можно не заполнять</span></span><span class="control"><input class="secret" name="db_password" type="password" autocomplete="new-password"><button class="reveal-secret" type="button" aria-label="Показать пароль">Показать</button></span><small>Если оставить поле пустым, установщик сам создаст надёжный пароль и сохранит его в настройках приложения.</small></label>
</div></section>
<section class="form-section" id="integrations"><div class="section-head"><span class="section-number">03</span><div><h3>Дополнительные подключения</h3><p>Этот раздел можно полностью пропустить. Ключи понадобятся только для функций, описанных ниже, и сохранятся на вашем сервере.</p></div></div><div class="integration-grid">
<article class="integration"><div class="integration-head"><div><h4>Telegram</h4><p>Нужен для уведомлений и функций Telegram‑бота.</p></div><span class="badge">Необязательно</span></div><label class="field"><span>Имя бота</span><input name="telegram_bot_name" autocomplete="off"></label><label class="field"><span>Токен бота</span><input class="secret" name="telegram_bot_token" type="password" autocomplete="new-password"></label><label class="field"><span>Секретный токен webhook</span><input class="secret" name="telegram_webhook_token" type="password" autocomplete="new-password"></label></article>
<article class="integration"><div class="integration-head"><div><h4>TronGrid</h4><p>Нужен для полноценной работы счетов USDT в сети TRC20.</p></div><span class="badge">Необязательно</span></div><label class="field"><span>API‑ключ TronGrid</span><input class="secret" name="trongrid_api_key" type="password" autocomplete="new-password"></label></article>
<article class="integration full"><div class="integration-head"><div><h4>Определение страны по IP</h4><p>Нужно для географических ограничений и проверок пользователей.</p></div><span class="badge">Необязательно</span></div><label class="field"><span>API‑ключ IP Geolocation</span><input class="secret" name="ipgeolocation_api_key" type="password" autocomplete="new-password"></label></article>
</div><p class="no-config">Почта и Sentry намеренно не настраиваются этим установщиком.</p></section>
<section class="form-section" id="server"><div class="section-head"><span class="section-number">04</span><div><h3>Защита и резервные копии</h3><p>Для обычной установки оставьте первые три пункта включёнными. Тестовые данные нужны только для демонстрации.</p></div></div><div class="checks">
<label class="check"><input name="create_swap" type="checkbox" checked><span><b>Добавить 2 ГБ резервной памяти</b><small>Помогает серверу не зависнуть при нехватке оперативной памяти. Создаётся только при необходимости.</small></span></label>
<label class="check"><input name="enable_firewall" type="checkbox" checked><span><b>Включить сетевую защиту</b><small>Оставит открытыми доступ по SSH и сайт. Временный порт установщика закроется автоматически.</small></span></label>
<label class="check"><input name="install_backups" type="checkbox" checked><span><b>Ежедневная резервная копия</b><small>База данных и загруженные файлы сохраняются в /var/backups/wl-traders.</small></span></label>
<label class="check"><input name="generate_test_data" type="checkbox"><span><b>Тестовые данные</b><small>Только для демо‑сервера. Не включайте на реальном production.</small></span></label>
</div><label class="field retention"><span>Хранить бэкапы, дней</span><input name="backup_retention_days" type="number" value="7" min="1" max="90"></label></section>
<section class="launch"><div class="launch-panel"><div class="launch-copy"><span class="kicker">Последний шаг</span><h3>Проверьте настройки и начните установку.</h3><p>Установщик сначала проверит сервер, затем установит и сам проверит WL Traders. При ошибке незавершённая попытка будет безопасно отменена.</p></div><div class="review"><div><b>Адрес</b><span id="review-url">—</span></div><div><b>Защита</b><span id="review-security">Firewall · бэкапы · swap</span></div><div><b>Дополнения</b><span id="review-integrations">Не выбраны</span></div></div><div class="actions"><span class="warning">Установщик не изменяет существующую базу или предыдущую установку.</span><button class="button" type="submit">Проверить и установить →</button></div></div></section>
</form>
<section id="status" class="status" aria-live="polite"><div class="status-head"><div class="pill"><span class="dot"></span><b id="message">Подготовка…</b></div><span id="status-label" class="status-label">Ход установки</span></div><div class="bar"><i id="progress-bar"></i></div><div id="status-details" class="status-details">Сервер проверяется перед любыми изменениями.</div><div class="result-actions"><a id="open-app" class="button" href="#">Открыть WL Traders →</a></div><details id="technical" class="technical"><summary>Технический журнал</summary><pre id="logs"></pre></details></section>
</div></main><footer class="footer"><span>WL Traders · Установка на сервер</span><span>Одноразовая страница · Данные остаются на сервере</span></footer></div>
<script>
const token=new URLSearchParams(location.search).get('token');
const form=document.getElementById('form'),statusBox=document.getElementById('status'),logs=document.getElementById('logs'),message=document.getElementById('message'),submitButton=form.querySelector('button[type="submit"]'),progressBar=document.getElementById('progress-bar'),statusLabel=document.getElementById('status-label'),statusDetails=document.getElementById('status-details'),technical=document.getElementById('technical'),openApp=document.getElementById('open-app');
let timer=null;
function payload(){const f=new FormData(form),o={};for(const [k,v] of f.entries())o[k]=v;for(const k of ['create_swap','enable_firewall','install_backups','generate_test_data'])o[k]=form.elements[k].checked;return o}
async function poll(){try{const r=await fetch('/status?token='+encodeURIComponent(token)),s=await r.json();message.textContent=s.message;logs.textContent=s.logs.join('\n');logs.scrollTop=logs.scrollHeight;statusBox.className='status active '+s.phase;progressBar.style.width=(s.progress||0)+'%';statusLabel.textContent=s.step_index?`${s.step_index} из ${s.step_total}`:'Готово';statusDetails.textContent=s.error||s.step||'Основные проверки пройдены.';if(s.phase==='done'){clearInterval(timer);openApp.href=s.app_url}if(s.phase==='failed'){clearInterval(timer);technical.open=true}}catch(e){message.textContent='Панель завершает работу…'}}
form.addEventListener('submit',async e=>{e.preventDefault();if(!confirm('Начать установку WL Traders на этом сервере?'))return;submitButton.disabled=true;statusBox.classList.add('active');statusBox.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});const r=await fetch('/install?token='+encodeURIComponent(token),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload())});if(!r.ok){alert((await r.json()).error);submitButton.disabled=false;return}form.style.display='none';timer=setInterval(poll,1000);poll()});
document.querySelectorAll('.reveal-secret').forEach(button=>button.addEventListener('click',()=>{const input=button.parentElement.querySelector('input');const show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Скрыть':'Показать';button.setAttribute('aria-label',show?'Скрыть пароль':'Показать пароль')}));
document.querySelector('.generate-secret').addEventListener('click',()=>{const chars='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';const values=crypto.getRandomValues(new Uint32Array(24));const password=[...values].map(v=>chars[v%chars.length]).join('');form.elements.admin_password.value=password;form.elements.admin_password_confirmation.value=password});
function updateReview(){document.getElementById('review-url').textContent=form.elements.app_url.value||'—';const enabled=['enable_firewall','install_backups','create_swap'].filter(k=>form.elements[k].checked).length;document.getElementById('review-security').textContent=`${enabled} из 3 включено`;const integrations=['telegram_bot_token','trongrid_api_key','ipgeolocation_api_key'].filter(k=>form.elements[k].value.trim()).length;document.getElementById('review-integrations').textContent=integrations?`${integrations} подключено`:'Не выбраны'}form.addEventListener('input',updateReview);updateReview();
const sectionLinks=[...document.querySelectorAll('.progress a')];if('IntersectionObserver'in window){const observer=new IntersectionObserver(entries=>{entries.forEach(entry=>{if(entry.isIntersecting){sectionLinks.forEach(link=>link.classList.toggle('active',link.getAttribute('href')==='#'+entry.target.id))}})},{rootMargin:'-22% 0px -65%',threshold:0});document.querySelectorAll('.form-section').forEach(section=>observer.observe(section))}
</script></body></html>"""

class InstallerHandler(BaseHTTPRequestHandler):
    server_version = "WLTradersInstaller/2.0"

    def log_message(self, format: str, *args: Any) -> None:
        return

    def authorized(self) -> bool:
        query = parse_qs(urlparse(self.path).query)
        supplied = query.get("token", [""])[0]
        token_matches = secrets.compare_digest(supplied, self.server.install_token)  # type: ignore[attr-defined]
        with STATE_LOCK:
            waiting_for_start = STATE["phase"] == "ready"
        not_expired = not waiting_for_start or time.monotonic() < self.server.expires_at  # type: ignore[attr-defined]
        return token_matches and not_expired

    def json_response(self, data: dict[str, Any], status: HTTPStatus = HTTPStatus.OK) -> None:
        body = json.dumps(data, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Cache-Control", "no-store")
        self.send_header("X-Content-Type-Options", "nosniff")
        self.send_header("X-Frame-Options", "DENY")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self) -> None:  # noqa: N802
        if not self.authorized():
            self.send_error(HTTPStatus.FORBIDDEN)
            return
        path = urlparse(self.path).path
        if path == "/":
            facts = server_facts()
            body = (
                PAGE.replace("__SERVER_IP__", public_ip())
                .replace("__SERVER_OS__", str(facts["os"]))
                .replace("__SERVER_CPU__", str(facts["cpu"]))
                .replace("__SERVER_MEMORY__", str(facts["memory_gb"]))
                .replace("__SERVER_DISK_FREE__", str(facts["disk_free_gb"]))
                .encode("utf-8")
            )
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
            if self.headers.get_content_type() != "application/json":
                raise ValueError("Ожидался JSON-запрос")
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
    parser.add_argument("--expires-in", type=int, default=2700)
    args = parser.parse_args()
    if os.geteuid() != 0:
        raise SystemExit("Установщик должен быть запущен от root")
    server = ThreadingHTTPServer((args.host, args.port), InstallerHandler)
    server.install_token = args.token  # type: ignore[attr-defined]
    server.expires_at = time.monotonic() + max(300, min(args.expires_in, 7200))  # type: ignore[attr-defined]
    server.daemon_threads = True
    server.serve_forever()


if __name__ == "__main__":
    main()
