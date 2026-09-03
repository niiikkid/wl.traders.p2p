#!/usr/bin/env python3
"""Temporary one-time web installer for WL Traders on a fresh Ubuntu server."""

from __future__ import annotations

import argparse
import datetime as dt
import ipaddress
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
from urllib.request import urlopen
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
PUBLIC_IP_LOCK = threading.Lock()
PUBLIC_IP_CACHE: str | None = None
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
    global PUBLIC_IP_CACHE
    with PUBLIC_IP_LOCK:
        if PUBLIC_IP_CACHE:
            return PUBLIC_IP_CACHE

        configured_ip = os.environ.get("WL_TRADERS_PUBLIC_IP", "").strip()
        try:
            configured_address = ipaddress.ip_address(configured_ip)
            if configured_address.version == 4 and configured_address.is_global:
                PUBLIC_IP_CACHE = configured_ip
                return configured_ip
        except ValueError:
            pass

        for endpoint in ("https://api.ipify.org", "https://checkip.amazonaws.com"):
            try:
                with urlopen(endpoint, timeout=5) as response:  # noqa: S310 - fixed trusted endpoints
                    candidate = response.read(64).decode("ascii").strip()
                address = ipaddress.ip_address(candidate)
                if address.version == 4 and address.is_global:
                    PUBLIC_IP_CACHE = candidate
                    return candidate
            except (OSError, ValueError, UnicodeError):
                continue

        try:
            output = subprocess.check_output(["hostname", "-I"], text=True, timeout=5)
            fallback = ""
            for value in output.split():
                try:
                    address = ipaddress.ip_address(value)
                except ValueError:
                    continue
                if address.version != 4 or address.is_loopback:
                    continue
                if address.is_global:
                    PUBLIC_IP_CACHE = value
                    return value
                if not fallback:
                    fallback = value
            if fallback:
                return fallback
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
    protected_paths = [
        target,
        Path("/etc/nginx/sites-available/wl-traders"),
        Path("/etc/systemd/system/wl-traders-horizon.service"),
        Path("/etc/cron.d/wl-traders"),
        Path("/etc/systemd/system/wl-traders-installer-firewall-cleanup.service"),
        Path("/etc/systemd/system/wl-traders-installer-firewall-cleanup.timer"),
        Path("/usr/local/sbin/wl-traders-installer-firewall-cleanup"),
        CLOUDFLARE_REAL_IP_CONF,
    ]
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

    checks = [
        {"name": "Система", "value": facts["os"]},
        {"name": "Ресурсы", "value": f"{facts['cpu']} vCPU · {facts['memory_gb']} ГБ RAM · {facts['disk_free_gb']} ГБ свободно"},
        {"name": "Порт 80", "value": "свободен"},
        {"name": "Данные", "value": "предыдущая установка не найдена"},
    ]
    if settings["site_mode"] == "domain":
        domain = settings["domain"]
        server_ip = public_ip()
        if settings["https_mode"] == "cloudflare":
            validate_cloudflare_dns(domain)
            checks.append({"name": "Домен", "value": f"{domain} → HTTPS через Cloudflare"})
        else:
            validate_domain_dns(domain, server_ip)
            checks.append({"name": "Домен", "value": f"{domain} → {server_ip}"})
    return checks


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


def validate_domain(value: str) -> str:
    domain = value.strip().lower().rstrip(".")
    if len(domain) > 253 or not re.fullmatch(
        r"(?=.{4,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}",
        domain,
    ):
        raise ValueError("Укажите домен без http://, пути и порта, например pay.example.com")
    return domain


def resolved_ipv4_addresses(domain: str) -> set[str]:
    try:
        return {
            str(result[4][0])
            for result in socket.getaddrinfo(domain, 80, family=socket.AF_INET, type=socket.SOCK_STREAM)
        }
    except OSError:
        return set()


def validate_domain_dns(domain: str, server_ip: str) -> list[str]:
    addresses = sorted(resolved_ipv4_addresses(domain))
    if not addresses:
        raise RuntimeError(
            f"Домен {domain} пока не имеет A-записи. Добавьте в DNS адрес сервера {server_ip} и повторите"
        )
    if set(addresses) != {server_ip}:
        raise RuntimeError(
            f"A-запись {domain} ведёт на {', '.join(addresses)}, а должна вести на сервер {server_ip}. "
            "Если домен уже за Cloudflare, выберите установку «С Cloudflare» или временно "
            "выключите прокси (серое облако)"
        )
    return addresses


def validate_cloudflare_dns(domain: str) -> list[str]:
    addresses = sorted(resolved_ipv4_addresses(domain))
    if not addresses:
        raise RuntimeError(
            f"Домен {domain} пока не имеет A-записи. Добавьте домен в Cloudflare с включённым "
            "Proxied (оранжевое облако) и повторите"
        )
    return addresses


CLOUDFLARE_REAL_IP_CONF = Path("/etc/nginx/conf.d/wl-traders-cloudflare-realip.conf")


def cloudflare_ip_ranges() -> list[str]:
    ranges: list[str] = []
    for url in ("https://www.cloudflare.com/ips-v4", "https://www.cloudflare.com/ips-v6"):
        try:
            with urlopen(url, timeout=10) as response:  # noqa: S310 - fixed trusted endpoint
                body = response.read().decode("ascii")
        except (OSError, UnicodeError):
            continue
        for line in body.splitlines():
            candidate = line.strip()
            if not candidate or candidate.startswith("#"):
                continue
            try:
                ipaddress.ip_network(candidate, strict=False)
            except ValueError:
                continue
            ranges.append(candidate)
    return ranges


def configure_cloudflare_real_ip() -> bool:
    ranges = cloudflare_ip_ranges()
    if not ranges:
        return False
    lines = [f"set_real_ip_from {value};" for value in ranges]
    lines.append("real_ip_header CF-Connecting-IP;")
    CLOUDFLARE_REAL_IP_CONF.write_text("\n".join(lines) + "\n", encoding="utf-8")
    return True


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
    if settings["https_mode"] == "cloudflare":
        values["SESSION_SECURE_COOKIE"] = "true"

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

    default_server = " default_server" if settings["site_mode"] == "ip" else ""
    server_name = settings["domain"] if settings["site_mode"] == "domain" else "_"
    proxy_directives = ""
    if settings["https_mode"] == "cloudflare":
        proxy_directives = (
            "\n    # Трафик приходит через Cloudflare — приложение считает его HTTPS\n"
            "    fastcgi_param HTTPS on;"
        )
    nginx = f"""server {{
    listen 80{default_server};
    listen [::]:80{default_server};
    server_name {server_name};
    root {app_path}/public;
    index index.php index.html;
    charset utf-8;
    client_max_body_size {upload_mb}M;
    client_body_timeout 60s;
    client_header_timeout 20s;
    keepalive_timeout 65s;
    access_log /var/log/nginx/wl-traders-access.log;
    error_log /var/log/nginx/wl-traders-error.log warn;{proxy_directives}

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

    if settings["https_mode"] == "cloudflare":
        if configure_cloudflare_real_ip():
            add_log("Cloudflare: real IP включён (CF-Connecting-IP)")
        else:
            add_log("ВНИМАНИЕ: не удалось получить диапазоны IP Cloudflare — real IP не настроен")

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


def schedule_firewall_cleanup(installer_port: int, delay_minutes: int) -> None:
    service_name = "wl-traders-installer-firewall-cleanup"
    script_path = Path(f"/usr/local/sbin/{service_name}")
    service_path = Path(f"/etc/systemd/system/{service_name}.service")
    timer_path = Path(f"/etc/systemd/system/{service_name}.timer")
    run_at = (dt.datetime.now().astimezone() + dt.timedelta(minutes=delay_minutes)).strftime(
        "%Y-%m-%d %H:%M:%S"
    )

    script_path.write_text(
        "#!/usr/bin/env bash\n"
        "set -u\n"
        f"/usr/sbin/ufw --force delete allow {installer_port}/tcp || true\n"
        f"/usr/bin/systemctl disable {service_name}.timer || true\n"
        f"rm -f {quote(service_path)} {quote(timer_path)} {quote(script_path)}\n"
        "/usr/bin/systemctl daemon-reload\n",
        encoding="utf-8",
    )
    os.chmod(script_path, 0o700)
    service_path.write_text(
        "[Unit]\nDescription=Close temporary WL Traders installer port\n\n"
        "[Service]\nType=oneshot\n"
        f"ExecStart={script_path}\n",
        encoding="utf-8",
    )
    timer_path.write_text(
        "[Unit]\nDescription=Schedule temporary WL Traders installer port cleanup\n\n"
        f"[Timer]\nOnCalendar={run_at}\nPersistent=true\nUnit={service_name}.service\n\n"
        "[Install]\nWantedBy=timers.target\n",
        encoding="utf-8",
    )
    run("systemctl daemon-reload")
    run(f"systemctl enable {service_name}.timer && systemctl restart {service_name}.timer")


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
    required = ["app_name", "install_path", "db_name", "db_user", "admin_password"]
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

    site_mode = str(raw.get("site_mode", "")).strip()
    legacy_url = str(raw.get("app_url", "")).strip()
    if not site_mode:
        parsed_legacy_url = urlparse(validate_url(legacy_url))
        try:
            ipaddress.ip_address(parsed_legacy_url.hostname or "")
            site_mode = "ip"
        except ValueError:
            site_mode = "domain"
    if site_mode not in {"ip", "domain"}:
        raise ValueError("Выберите доступ по IP-адресу или домену")

    https_mode = str(raw.get("https_mode", "none")).strip() or "none"
    if https_mode not in {"none", "cloudflare"}:
        raise ValueError("Выберите способ HTTPS: без Cloudflare или с Cloudflare")

    domain = ""
    if site_mode == "domain":
        domain_value = str(raw.get("domain", "")).strip()
        if not domain_value and legacy_url:
            domain_value = urlparse(validate_url(legacy_url)).hostname or ""
        domain = validate_domain(domain_value)
        scheme = "https" if https_mode == "cloudflare" else "http"
        app_url = f"{scheme}://{domain}"
    else:
        https_mode = "none"
        app_url = validate_url(legacy_url or f"http://{public_ip()}")
        parsed_app_url = urlparse(app_url)
        try:
            ipaddress.ip_address(parsed_app_url.hostname or "")
        except ValueError as exc:
            raise ValueError("Для доступа по IP укажите IP-адрес сервера, а не домен") from exc
        if parsed_app_url.scheme != "http":
            raise ValueError("Без домена доступ по IP работает через http://")
        if parsed_app_url.port not in {None, 80}:
            raise ValueError("Для доступа по IP используйте стандартный HTTP-порт 80")

    db_password = str(raw.get("db_password", "")) or secrets.token_urlsafe(24)
    if len(db_password) < 16:
        raise ValueError("Пароль базы данных должен содержать минимум 16 символов или оставьте поле пустым")
    settings = {
        "app_name": app_name,
        "site_mode": site_mode,
        "https_mode": https_mode,
        "domain": domain,
        "app_url": app_url,
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
    firewall_enabled: bool,
    installer_port: int | None,
) -> None:
    add_log("Отменяю только изменения, созданные этой попыткой…")

    if system_files_created:
        subprocess.run(["systemctl", "disable", "--now", "wl-traders-horizon"], capture_output=True, text=True)
        subprocess.run(
            ["systemctl", "disable", "--now", "wl-traders-installer-firewall-cleanup.timer"],
            capture_output=True,
            text=True,
        )
        for path in (
            Path("/etc/nginx/sites-enabled/wl-traders"),
            Path("/etc/nginx/sites-available/wl-traders"),
            Path("/etc/systemd/system/wl-traders-horizon.service"),
            Path("/etc/cron.d/wl-traders"),
            Path("/etc/cron.d/wl-traders-backup"),
            Path("/usr/local/sbin/wl-traders-backup"),
            Path("/etc/systemd/system/wl-traders-installer-firewall-cleanup.service"),
            Path("/etc/systemd/system/wl-traders-installer-firewall-cleanup.timer"),
            Path("/usr/local/sbin/wl-traders-installer-firewall-cleanup"),
            CLOUDFLARE_REAL_IP_CONF,
        ):
            path.unlink(missing_ok=True)
        subprocess.run(["systemctl", "daemon-reload"], capture_output=True, text=True)

    if firewall_enabled and installer_port is not None:
        subprocess.run(
            ["ufw", "--force", "delete", "allow", f"{installer_port}/tcp"],
            capture_output=True,
            text=True,
        )

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
    installer_port: int | None = None

    try:
        settings = normalize_settings(raw_settings)
        installer_port = int(server.server_port)
        settings["installer_port"] = installer_port
        target = settings["install_path"]
        assert isinstance(target, Path)
        staging = target.parent / f".{target.name}.installing"
        set_state(
            phase="installing",
            message="Установка началась",
            app_url=settings["app_url"],
            site_mode=settings["site_mode"],
            https_mode=settings["https_mode"],
            domain=settings["domain"],
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
            add_log("Включаю firewall: SSH, сайт и временно панель установки…")
            installer_port = int(settings["installer_port"])
            nginx_firewall_profile = "Nginx HTTP"
            schedule_firewall_cleanup(installer_port, delay_minutes=50)
            firewall_enabled = True
            run(
                f"ufw allow OpenSSH && ufw allow {quote(nginx_firewall_profile)} && "
                f"ufw allow {installer_port}/tcp && ufw --force enable"
            )

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

        if firewall_enabled:
            assert installer_port is not None
            schedule_firewall_cleanup(installer_port, delay_minutes=2)

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
                {
                    "name": "SSL",
                    "value": (
                        "включён через Cloudflare"
                        if settings["https_mode"] == "cloudflare"
                        else "не настроен · можно включить через Cloudflare"
                    ),
                },
                {"name": "Бэкапы", "value": "проверены" if settings["install_backups"] else "отключены"},
            ],
        )
        add_log("Установка и основные production-проверки завершены.")
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
            firewall_enabled=firewall_enabled,
            installer_port=installer_port,
        )
        set_state(
            phase="failed",
            message="Установка остановлена и безопасно отменена",
            error=str(exc),
            progress=0,
        )
        threading.Timer(3600, server.shutdown).start()


PAGE = Path(__file__).with_name("page.html").read_text(encoding="utf-8")

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

    def read_json_payload(self, max_length: int = 131072) -> dict[str, Any]:
        if self.headers.get_content_type() != "application/json":
            raise ValueError("Ожидался JSON-запрос")
        length = int(self.headers.get("Content-Length", "0"))
        if length <= 0 or length > max_length:
            raise ValueError("Некорректный размер запроса")
        payload = json.loads(self.rfile.read(length))
        if not isinstance(payload, dict):
            raise ValueError("Ожидался JSON-объект")
        return payload

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
        path = urlparse(self.path).path
        if path == "/domain-check":
            try:
                payload = self.read_json_payload(max_length=8192)
                domain = validate_domain(str(payload.get("domain", "")))
                https_mode = str(payload.get("https_mode", "none")).strip() or "none"
                server_ip = public_ip()
                if https_mode == "cloudflare":
                    addresses = validate_cloudflare_dns(domain)
                else:
                    addresses = validate_domain_dns(domain, server_ip)
            except (ValueError, TypeError, json.JSONDecodeError, RuntimeError) as exc:
                self.json_response({"error": str(exc)}, HTTPStatus.BAD_REQUEST)
                return
            self.json_response(
                {"ok": True, "domain": domain, "server_ip": server_ip, "https_mode": https_mode, "addresses": addresses}
            )
            return
        if path != "/install":
            self.send_error(HTTPStatus.NOT_FOUND)
            return
        with STATE_LOCK:
            if STATE["phase"] != "ready":
                self.json_response({"error": "Установка уже запущена"}, HTTPStatus.CONFLICT)
                return
        try:
            payload = self.read_json_payload()
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
