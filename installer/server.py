#!/usr/bin/env python3
"""Temporary, token-protected Docker installer. Host runtime: Python 3.10+."""
from __future__ import annotations

import argparse
import datetime as dt
import html
import ipaddress
import json
import os
import platform
import re
import secrets
import shutil
import socket
import subprocess
import sys
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
sys.path.insert(0, str(Path(__file__).resolve().parent))
INSTALL_STEPS = ('Проверка Docker', 'Подготовка файлов', 'Сборка образов', 'База данных', 'Настройка приложения', 'Запуск служб', 'Финальная проверка')
STATE_LOCK = threading.Lock()
PUBLIC_IP_LOCK = threading.Lock()
PUBLIC_IP_CACHE = None
SECRET_VALUES: set[str] = set()
STATE: dict[str, Any] = dict(phase='ready', message='Установщик готов', logs=[], app_url=None,
                            error=None, step=None, step_index=0, step_total=len(INSTALL_STEPS), progress=0, checks=[])


def default_install_path() -> Path:
    override = os.environ.get('WL_TRADERS_INSTALL_DIR')
    if override:
        return Path(override).expanduser().absolute()
    if platform.system() == 'Linux' and getattr(os, 'geteuid', lambda: -1)() == 0:
        return Path('/opt/wl-traders')
    return Path.home() / 'wl-traders'


def default_mode() -> str:
    return 'ip' if platform.system() == 'Linux' else 'local'


def redact_sensitive(message: str) -> str:
    message = re.sub(r'-----BEGIN [^-]*PRIVATE KEY-----.*?-----END [^-]*PRIVATE KEY-----', '[скрыто]', message, flags=re.S)
    message = re.sub(r'(?i)\b([A-Z0-9_]*(?:PASSWORD|TOKEN|SECRET|API_KEY|APP_KEY)[A-Z0-9_]*)=(?:"[^"]*"|\x27[^\x27]*\x27|\S+)', r'\1=[скрыто]', message)
    for value in sorted(SECRET_VALUES, key=len, reverse=True):
        if value:
            message = message.replace(value, '[скрыто]')
    return message


def add_log(message: str) -> None:
    clean = redact_sensitive(message.rstrip())
    if not clean:
        return
    line = f'[{dt.datetime.now().astimezone():%Y-%m-%d %H:%M:%S}] {clean}'
    with STATE_LOCK:
        STATE['logs'] = (STATE['logs'] + [line])[-500:]


def set_state(**values: Any) -> None:
    with STATE_LOCK:
        STATE.update(values)


def set_progress(index: int) -> None:
    step = INSTALL_STEPS[index - 1]
    set_state(phase='installing', message=step, step=step, step_index=index,
              progress=round((index - 1) / len(INSTALL_STEPS) * 100))
    add_log(f'=== {index}/{len(INSTALL_STEPS)} · {step} ===')


def public_ip() -> str:
    global PUBLIC_IP_CACHE
    if platform.system() != 'Linux':
        return '127.0.0.1'
    with PUBLIC_IP_LOCK:
        if PUBLIC_IP_CACHE:
            return PUBLIC_IP_CACHE
        candidates = [os.environ.get('WL_TRADERS_PUBLIC_IP', '')]
        for candidate in candidates:
            try:
                address = ipaddress.ip_address(candidate)
                if address.version == 4 and address.is_global:
                    PUBLIC_IP_CACHE = str(address)
                    return PUBLIC_IP_CACHE
            except ValueError:
                pass
        for endpoint in ('https://api.ipify.org', 'https://checkip.amazonaws.com'):
            try:
                with urlopen(endpoint, timeout=5) as response:
                    address = ipaddress.ip_address(response.read(64).decode('ascii').strip())
                if address.version == 4 and address.is_global:
                    PUBLIC_IP_CACHE = str(address)
                    return PUBLIC_IP_CACHE
            except (OSError, ValueError, UnicodeError):
                pass
    return '127.0.0.1'


def total_memory_bytes() -> int:
    try:
        if platform.system() == 'Darwin':
            return int(subprocess.check_output(['sysctl', '-n', 'hw.memsize'], text=True, timeout=5))
        if platform.system() == 'Windows':
            import ctypes
            class MemoryStatus(ctypes.Structure):
                _fields_ = [('length', ctypes.c_ulong), ('load', ctypes.c_ulong)] + [(name, ctypes.c_ulonglong) for name in ('total', 'avail', 'page', 'avail_page', 'virtual', 'avail_virtual', 'extended')]
            status = MemoryStatus()
            status.length = ctypes.sizeof(status)
            ctypes.windll.kernel32.GlobalMemoryStatusEx(ctypes.byref(status))
            return status.total
        for line in Path('/proc/meminfo').read_text().splitlines():
            if line.startswith('MemTotal:'):
                return int(line.split()[1]) * 1024
    except (OSError, ValueError, subprocess.SubprocessError):
        pass
    return 0


def server_facts() -> dict[str, Any]:
    ancestor = default_install_path()
    while not ancestor.exists():
        ancestor = ancestor.parent
    disk = shutil.disk_usage(ancestor)
    return dict(os=f'{platform.system()} {platform.release()}', cpu=os.cpu_count() or 0,
                memory_gb=round(total_memory_bytes() / 1024**3, 1), disk_free_gb=round(disk.free / 1024**3, 1))


def validate_domain(value: str) -> str:
    domain = value.strip().lower().rstrip('.')
    if not re.fullmatch(r'(?=.{4,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}', domain):
        raise ValueError('Укажите домен без http://, пути и порта, например pay.example.com')
    return domain


def resolved_ipv4_addresses(domain: str) -> set[str]:
    try:
        return {str(result[4][0]) for result in socket.getaddrinfo(domain, 80, family=socket.AF_INET, type=socket.SOCK_STREAM)}
    except OSError:
        return set()


def cloudflare_ip_ranges() -> list[str]:
    def validate(groups) -> list[str]:
        ranges = []
        for version, values in zip((4, 6), groups):
            networks = [ipaddress.ip_network(value.strip(), strict=True) for value in values if value.strip()]
            if not networks or any(network.version != version or not network.is_global or network.prefixlen < (8 if version == 4 else 19) for network in networks):
                raise ValueError('invalid ranges')
            ranges.extend(str(network) for network in networks)
        return ranges
    try:
        # Official unauthenticated API avoids bot challenges on the website's text endpoints.
        with urlopen('https://api.cloudflare.com/client/v4/ips', timeout=15) as response:
            data = json.loads(response.read(65536))
        if data.get('success') is not True:
            raise ValueError('Cloudflare API failed')
        return validate([data['result']['ipv4_cidrs'], data['result']['ipv6_cidrs']])
    except (OSError, UnicodeError, ValueError, KeyError, TypeError):
        pass
    try:
        groups = []
        for version in (4, 6):
            with urlopen(f'https://www.cloudflare.com/ips-v{version}', timeout=15) as response:
                groups.append(response.read(65536).decode('ascii').splitlines())
        return validate(groups)
    except (OSError, UnicodeError, ValueError, TypeError) as exc:
        raise RuntimeError('Не удалось получить корректные официальные диапазоны Cloudflare; защита не ослаблена. Повторите позже.') from exc


def validate_domain_dns(domain: str, server_ip: str) -> list[str]:
    addresses = sorted(resolved_ipv4_addresses(domain))
    if not addresses or set(addresses) != {server_ip}:
        raise RuntimeError(f'A-запись {domain} должна вести только на {server_ip}. Для Cloudflare выберите Proxied, иначе серое облако.')
    return addresses


def validate_cloudflare_dns(domain: str) -> list[str]:
    addresses = sorted(resolved_ipv4_addresses(domain))
    networks = [ipaddress.ip_network(value) for value in cloudflare_ip_ranges()]
    if not addresses or any(not any(ipaddress.ip_address(address) in network for network in networks) for address in addresses):
        raise RuntimeError('A-записи домена не подтверждают Cloudflare Proxied (оранжевое облако).')
    return addresses


def integer(raw: Any, name: str, minimum: int, maximum: int) -> int:
    if isinstance(raw, bool) or not re.fullmatch(r'\d+', str(raw)) or not minimum <= int(raw) <= maximum:
        raise ValueError(f'{name}: допустимо целое число {minimum}–{maximum}')
    return int(raw)


def normalize_settings(raw: dict[str, Any]) -> dict[str, Any]:
    for key, value in raw.items():
        if isinstance(value, str) and key not in {'cloudflare_cert', 'cloudflare_key'} and any(ord(char) < 32 for char in value):
            raise ValueError(f'{key}: недопустимые управляющие символы')
    password = str(raw.get('admin_password', ''))
    if len(password) < 8:
        raise ValueError('Пароль администратора должен содержать минимум 8 символов')
    if password != str(raw.get('admin_password_confirmation', '')):
        raise ValueError('Пароль администратора и подтверждение не совпадают')
    name = str(raw.get('app_name', 'WL Traders')).strip()
    if not 1 <= len(name) <= 80:
        raise ValueError('Название приложения: 1–80 символов')
    path = Path(str(raw.get('install_path') or default_install_path())).expanduser()
    if not path.is_absolute() or path == Path(path.anchor) or path == Path.home() or '..' in path.parts:
        raise ValueError('Укажите абсолютный путь отдельной папки установки')
    timezone = str(raw.get('timezone', 'Europe/Moscow'))
    try:
        ZoneInfo(timezone)
    except ZoneInfoNotFoundError as exc:
        # Windows often has no IANA database; PHP performs authoritative validation before migration.
        if platform.system() != 'Windows' or not re.fullmatch(r'(?:UTC|(?:Africa|America|Antarctica|Arctic|Asia|Atlantic|Australia|Europe|Indian|Pacific)/[A-Za-z_+/-]+)', timezone):
            raise ValueError('Указан неизвестный часовой пояс') from exc
    mode = str(raw.get('site_mode') or default_mode())
    if mode not in {'local', 'ip', 'domain'}:
        raise ValueError('Выберите локальный доступ, IP или домен')
    https = str(raw.get('https_mode') or 'none')
    if https not in {'none', 'cloudflare'}:
        raise ValueError('Неизвестный способ HTTPS')
    if mode != 'domain':
        https = 'none'
    port = integer(raw.get('http_port', 8080 if mode == 'local' else 80), 'HTTP-порт', 1, 65535)
    domain = validate_domain(str(raw.get('domain', ''))) if mode == 'domain' else ''
    supplied_url = str(raw.get('app_url', '')).strip().rstrip('/')
    if mode == 'domain':
        if port != 80:
            raise ValueError('Для домена HTTP-порт должен быть 80')
        app_url = f'{"https" if https == "cloudflare" else "http"}://{domain}'
        if supplied_url and supplied_url != app_url:
            raise ValueError('Адрес приложения не совпадает с доменом и HTTPS')
    else:
        host = 'localhost' if mode == 'local' else public_ip()
        app_url = supplied_url or f'http://{host}{":" + str(port) if port != 80 else ""}'
        parsed = urlparse(app_url)
        if parsed.scheme != 'http' or not parsed.hostname or parsed.username or parsed.password:
            raise ValueError('Укажите адрес http:// без имени пользователя и пароля')
        if parsed.path not in {'', '/'} or parsed.query or parsed.fragment:
            raise ValueError('Укажите адрес без пути, параметров и #фрагмента')
        if (parsed.port or 80) != port:
            raise ValueError('HTTP-порт не совпадает с портом в адресе приложения')
        if mode == 'local' and parsed.hostname not in {'localhost', '127.0.0.1'}:
            raise ValueError('Локальный адрес должен быть localhost или 127.0.0.1')
        if mode == 'ip':
            try:
                ipaddress.ip_address(parsed.hostname)
            except ValueError as exc:
                raise ValueError('Для доступа по IP укажите IP-адрес') from exc
    cert, key = str(raw.get('cloudflare_cert', '')).strip(), str(raw.get('cloudflare_key', '')).strip()
    if https == 'cloudflare':
        if '-----BEGIN CERTIFICATE-----' not in cert:
            raise ValueError('Укажите origin-сертификат Cloudflare (PEM)')
        if not re.search(r'-----BEGIN (?:RSA |EC )?PRIVATE KEY-----', key):
            raise ValueError('Укажите приватный ключ origin-сертификата (PEM)')
    settings = dict(app_name=name, install_path=path, timezone=timezone, site_mode=mode, https_mode=https,
                    domain=domain, app_url=app_url, http_port=port, bind_address='127.0.0.1' if mode == 'local' else '0.0.0.0',
                    cloudflare_cert=cert, cloudflare_key=key, admin_password=password)
    locale = str(raw.get('locale', 'ru'))
    if locale not in {'ru', 'en'}:
        raise ValueError('Язык интерфейса должен быть ru или en')
    settings['locale'] = locale
    for field in ('db_name', 'db_user'):
        value = str(raw.get(field, 'wl_traders'))
        if not re.fullmatch(r'[A-Za-z0-9_]{1,32}', value) or (field == 'db_user' and value.lower() == 'root'):
            raise ValueError(f'{field}: используйте 1–32 латинских букв, цифр, подчёркиваний; не root')
        settings[field] = value
    db_password = str(raw.get('db_password', ''))
    if db_password and len(db_password) < 16:
        raise ValueError('Пароль базы данных: минимум 16 символов или пустое поле')
    settings['db_password'] = db_password
    for field in ('telegram_bot_name', 'telegram_bot_token', 'telegram_webhook_token', 'trongrid_api_key', 'ipgeolocation_api_key'):
        settings[field] = str(raw.get(field, '')).strip()
    for field, default, low, high in [('upload_limit_mb', 64, 2, 512), ('session_lifetime', 10080, 60, 43200), ('backup_retention_days', 7, 1, 90)]:
        settings[field] = integer(raw.get(field, default), field, low, high)
    for field, default in [('install_backups', True), ('generate_test_data', False)]:
        if not isinstance(raw.get(field, default), bool):
            raise ValueError(f'{field}: ожидается true/false')
        settings[field] = raw.get(field, default)
    if settings['generate_test_data']:
        raise ValueError('Docker-установщик создаёт только администратора. Демо-данные не поддерживаются в production-образе.')
    return settings


def perform_install(raw_settings: dict[str, Any], server: ThreadingHTTPServer) -> None:
    from runtime import DockerRuntime
    try:
        settings = normalize_settings(raw_settings)
        SECRET_VALUES.update(str(value) for key, value in settings.items() if any(part in key for part in ('password', 'token', 'key', 'cert')) and value)
        set_state(phase='installing', error=None, logs=[], checks=[], app_url=settings['app_url'],
                  site_mode=settings['site_mode'], https_mode=settings['https_mode'])
        runtime = DockerRuntime(REPO_ROOT, settings, add_log, set_progress, SECRET_VALUES)
        with runtime.lock():
            checks = runtime.install()
        set_state(phase='done', message='WL Traders установлен; локальные проверки пройдены', checks=checks, progress=100)
        server.expires_at = time.monotonic() + 180
        add_log('Данные и конфигурация: ' + str(settings['install_path']))
    except Exception as exc:
        error = redact_sensitive(str(exc))
        add_log('ОШИБКА: ' + error)
        set_state(phase='failed', message='Установка остановлена. Данные сохранены; можно повторить.', error=error)
        server.expires_at = time.monotonic() + 3600


PAGE = Path(__file__).with_name('page.html').read_text(encoding='utf-8')


class InstallerHandler(BaseHTTPRequestHandler):
    server_version = 'WLTradersInstaller/3.0'

    def setup(self) -> None:
        super().setup()
        self.connection.settimeout(20)

    def log_message(self, format: str, *args: Any) -> None:
        pass

    def authorized(self) -> bool:
        supplied = parse_qs(urlparse(self.path).query).get('token', [''])[0]
        with STATE_LOCK:
            running = STATE['phase'] in {'starting', 'installing', 'done'}
        return secrets.compare_digest(supplied, self.server.install_token) and (running or time.monotonic() < self.server.expires_at)

    def respond(self, body: bytes, content_type: str, status: int = 200) -> None:
        self.send_response(status)
        for name, value in [('Content-Type', content_type), ('Cache-Control', 'no-store'), ('X-Content-Type-Options', 'nosniff'), ('X-Frame-Options', 'DENY'), ('Referrer-Policy', 'no-referrer'), ('Content-Security-Policy', "default-src 'self' 'unsafe-inline'"), ('Content-Length', str(len(body)))]:
            self.send_header(name, value)
        self.end_headers()
        self.wfile.write(body)

    def json_response(self, data: dict[str, Any], status: int = 200) -> None:
        self.respond(json.dumps(data, ensure_ascii=False).encode(), 'application/json; charset=utf-8', status)

    def read_json_payload(self, max_length: int = 131072) -> dict[str, Any]:
        if self.headers.get_content_type() != 'application/json':
            raise ValueError('Ожидался JSON-запрос')
        length = int(self.headers.get('Content-Length', '0'))
        if not 0 < length <= max_length:
            raise ValueError('Некорректный размер запроса')
        payload = json.loads(self.rfile.read(length))
        if not isinstance(payload, dict):
            raise ValueError('Ожидался JSON-объект')
        return payload

    def do_GET(self) -> None:
        if not self.authorized():
            self.send_error(403)
            return
        path = urlparse(self.path).path
        if path == '/':
            facts = server_facts()
            page = PAGE
            values = dict(SERVER_IP=public_ip(), SERVER_OS=facts['os'], SERVER_CPU=facts['cpu'], SERVER_MEMORY=facts['memory_gb'], SERVER_DISK_FREE=facts['disk_free_gb'], INSTALL_PATH=str(default_install_path()), DEFAULT_MODE=default_mode())
            for name, value in values.items():
                page = page.replace('__' + name + '__', html.escape(str(value), quote=True))
            self.respond(page.encode(), 'text/html; charset=utf-8')
        elif path == '/status':
            with STATE_LOCK:
                snapshot = dict(STATE, logs=list(STATE['logs']))
            self.json_response(snapshot)
        else:
            self.send_error(404)

    def do_POST(self) -> None:
        if not self.authorized():
            self.send_error(403)
            return
        path = urlparse(self.path).path
        if path not in {'/install', '/domain-check'}:
            self.send_error(404)
            return
        try:
            payload = self.read_json_payload()
            if path == '/domain-check':
                domain = validate_domain(str(payload.get('domain', '')))
                https = str(payload.get('https_mode', 'none'))
                if https not in {'none', 'cloudflare'}:
                    raise ValueError('Неизвестный способ HTTPS')
                server_ip = public_ip()
                addresses = validate_cloudflare_dns(domain) if https == 'cloudflare' else validate_domain_dns(domain, server_ip)
                self.json_response(dict(ok=True, domain=domain, server_ip=server_ip, https_mode=https, addresses=addresses,
                                        message='Проверены только DNS-записи; TLS и доступ через Cloudflare ещё не проверены.'))
                return
            normalize_settings(payload)
        except (ValueError, TypeError, RuntimeError, OSError) as exc:
            self.json_response({'error': redact_sensitive(str(exc))}, 400)
            return
        with STATE_LOCK:
            claimed = STATE['phase'] in {'ready', 'failed'}
            if claimed:
                STATE.update(phase='starting', message='Подготовка установки', error=None)
        if not claimed:
            self.json_response({'error': 'Установка уже запущена'}, 409)
            return
        try:
            threading.Thread(target=perform_install, args=(payload, self.server), daemon=True).start()
        except Exception:
            set_state(phase='failed')
            raise
        self.json_response({'ok': True}, 202)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument('--host', default='127.0.0.1')
    parser.add_argument('--port', type=int, default=8787)
    parser.add_argument('--token', default=None)
    parser.add_argument('--expires-in', type=int, default=2700)
    args = parser.parse_args()
    server = ThreadingHTTPServer((args.host, args.port), InstallerHandler)
    server.install_token = args.token or secrets.token_urlsafe(32)
    server.expires_at = time.monotonic() + max(300, min(args.expires_in, 7200))
    url = f'http://127.0.0.1:{args.port}/?token={server.install_token}'
    print('Откройте установщик: ' + url, flush=True)
    if args.host not in {'127.0.0.1', 'localhost', '::1'}:
        print('ВНИМАНИЕ: панель доступна по сети без TLS; используйте SSH-туннель для передачи секретов.', flush=True)
    if platform.system() == 'Linux':
        print(f'Для удалённого сервера: ssh -L {args.port}:127.0.0.1:{args.port} root@{public_ip()}', flush=True)
        print('Затем откройте указанный локальный URL на своём компьютере. Остановка панели: Ctrl+C.', flush=True)
    elif platform.system() in {'Darwin', 'Windows'}:
        import webbrowser
        webbrowser.open(url)
    def expire_idle_panel():
        while True:
            time.sleep(10)
            with STATE_LOCK:
                idle = STATE['phase'] not in {'starting', 'installing'}
            if idle and time.monotonic() >= server.expires_at:
                server.shutdown()
                return
    threading.Thread(target=expire_idle_panel, daemon=True).start()
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()


if __name__ == '__main__':
    main()
