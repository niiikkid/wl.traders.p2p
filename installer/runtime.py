"""Small Docker Compose orchestrator; never provisions host packages or deletes data."""
from __future__ import annotations

import base64
import contextlib
import ipaddress
import json
import os
import re
import secrets
import shutil
import socket
import subprocess
import threading
import time
import uuid
from pathlib import Path
from typing import Any


def atomic_write(path: Path, text: str, mode: int = 0o600) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_name('.' + path.name + '.' + secrets.token_hex(6))
    descriptor = os.open(temporary, os.O_WRONLY | os.O_CREAT | os.O_EXCL, mode)
    with os.fdopen(descriptor, 'w', encoding='utf-8', newline='\n') as handle:
        handle.write(text)
        handle.flush()
        os.fsync(handle.fileno())
    os.replace(temporary, path)
    os.chmod(path, mode)


class DockerRuntime:
    def __init__(self, source: Path, settings: dict[str, Any], log=print, progress=lambda value: None, secret_values=None):
        self.source = source.resolve()
        self.settings = settings
        self.target = Path(settings['install_path']).absolute()
        self.log = log
        self.progress = progress
        self.secret_values = secret_values if secret_values is not None else set()
        self.state: dict[str, Any] = {}

    @contextlib.contextmanager
    def lock(self):
        self.target.parent.mkdir(parents=True, exist_ok=True)
        lock_path = self.target.parent / ('.' + self.target.name + '.installer.lock')
        if lock_path.is_symlink():
            raise RuntimeError('Файл блокировки является ссылкой; установка остановлена')
        handle = open(lock_path, 'a+b')
        os.chmod(lock_path, 0o600)
        try:
            try:
                if os.name == 'nt':
                    import msvcrt
                    handle.seek(0)
                    if not handle.read(1):
                        handle.write(b'0')
                        handle.flush()
                    handle.seek(0)
                    msvcrt.locking(handle.fileno(), msvcrt.LK_NBLCK, 1)
                else:
                    import fcntl
                    fcntl.flock(handle.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
            except OSError as exc:
                raise RuntimeError('Другая установка уже работает в этой папке') from exc
            yield
        finally:
            handle.close()  # OS releases the lock even after a process crash; no stale PID deletion.

    def run(self, argv: list[str], timeout: int = 300, input_text: str | None = None, quiet=False) -> str:
        self.log('$ ' + ' '.join(argv if not quiet else argv[:2] + ['[служебная проверка]']))
        environment = {key: value for key, value in os.environ.items()
                       if not key.startswith(('COMPOSE_', 'WL_BIND_', 'WL_HTTP_', 'WL_CONTAINER_', 'WL_BACKUP_'))}
        try:
            process = subprocess.Popen(argv, cwd=self.target if self.target.exists() else self.source,
                                       env=environment,
                                       stdin=subprocess.PIPE if input_text is not None else subprocess.DEVNULL,
                                       stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True,
                                       encoding='utf-8', errors='replace')
        except FileNotFoundError as exc:
            raise RuntimeError('Docker CLI не найден. Установите Docker Desktop или Docker Engine с Compose.') from exc
        lines = []
        def read_output():
            assert process.stdout is not None
            for line in process.stdout:
                lines.append(line)
                if len(lines) > 1000:
                    del lines[:500]
                if not quiet:
                    self.log(line.rstrip())
        reader = threading.Thread(target=read_output, daemon=True)
        reader.start()
        if input_text is not None:
            assert process.stdin is not None
            try:
                process.stdin.write(input_text)
                process.stdin.close()
            except BrokenPipeError:
                pass
        try:
            code = process.wait(timeout=timeout)
        except subprocess.TimeoutExpired as exc:
            process.kill()
            process.wait()
            reader.join(timeout=5)
            raise RuntimeError(f'Превышено время ожидания ({timeout} с). Docker может продолжать операцию; данные сохранены. Проверьте docker compose ps и повторите.') from exc
        reader.join(timeout=5)
        if process.stdout:
            process.stdout.close()
        output = ''.join(lines)
        if code:
            raise RuntimeError(f'Docker/команда завершилась с кодом {code}: {output[-6000:]}')
        return output

    def compose_args(self, *args: str) -> list[str]:
        return ['docker', 'compose', '--project-directory', str(self.target), '--env-file', str(self.target / '.env'), '-f', str(self.target / 'compose.yaml'), *args]

    def compose(self, *args: str, timeout=300, **kwargs) -> str:
        return self.run(self.compose_args(*args), timeout=timeout, **kwargs)

    def check_local_engine(self) -> None:
        endpoint = os.environ.get('DOCKER_HOST', '')
        context = os.environ.get('DOCKER_CONTEXT', '')
        if context or not endpoint:
            try:
                command = ['docker', 'context', 'inspect'] + ([context] if context else [])
                endpoint = json.loads(self.run(command, timeout=30, quiet=True))[0]['Endpoints']['docker']['Host']
            except (ValueError, KeyError, IndexError) as exc:
                raise RuntimeError('Не удалось определить локальный Docker context') from exc
        if not endpoint.startswith(('unix://', 'npipe://')):
            raise RuntimeError('Нужен локальный Docker Engine/Desktop (unix/npipe); удалённые TCP/SSH contexts не поддерживаются')

    def copy_source(self) -> None:
        target = self.target.resolve()
        if self.source == target or self.source.is_relative_to(target):
            raise RuntimeError('Папка исходников должна быть отдельно от папки установки')
        skip = {'.git', 'node_modules', 'vendor', '.idea', '.vscode', '.hermes', '__pycache__', '.pytest_cache', '.DS_Store'}
        def ignore(directory, names):
            ignored = []
            for name in names:
                path = Path(directory) / name
                rel = path.relative_to(self.source).as_posix()
                if (name in skip or name.startswith('._') or (name.startswith('.env') and name != '.env.example')
                    or path.is_symlink() or path.resolve() == target
                    or rel in {'public/build', 'public/hot', 'public/storage', 'bootstrap/ssr', 'bootstrap/cache', 'storage', 'database/database.sqlite'}):
                    ignored.append(name)
            return ignored
        staging = self.target / ('src-next-' + secrets.token_hex(6))
        shutil.copytree(self.source, staging, ignore=ignore)
        current = self.target / 'src'
        if current.is_symlink():
            raise RuntimeError('Папка src не должна быть символической ссылкой')
        if current.exists():
            # Retain old source for recovery; never merge stale files into a new release.
            current.rename(self.target / ('src-previous-' + secrets.token_hex(6)))
        staging.rename(current)

    def check_ports(self) -> None:
        output = self.compose('ps', '--format', 'json', quiet=True)
        try:
            containers = json.loads(output) if output.strip().startswith('[') else [json.loads(line) for line in output.splitlines() if line.strip()]
        except ValueError as exc:
            raise RuntimeError('Не удалось проверить занятые порты Docker') from exc
        owned = {publisher['PublishedPort'] for container in containers if container.get('Service') == 'web'
                 for publisher in (container.get('Publishers') or [])}
        ports = [80, 443] if self.settings['https_mode'] == 'cloudflare' else [self.settings['http_port']]
        for port in ports:
            if port in owned:
                continue
            try:
                with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as probe:
                    probe.bind((self.settings['bind_address'], port))
            except OSError as exc:
                raise RuntimeError(f'Порт {port} занят или недоступен. Выберите другой порт либо остановите использующую его службу. Другие службы не изменены.') from exc

    def prepare(self, copy_source=True) -> None:
        marker = self.target / '.wl-traders-install.json'
        if marker.is_symlink() or self.target.is_symlink() or (self.target.exists() and not self.target.is_dir()):
            raise RuntimeError('Папка установки не принадлежит установщику (ссылка или файл)')
        if self.target.exists() and any(self.target.iterdir()) and not marker.is_file():
            raise RuntimeError('Папка не принадлежит установщику WL Traders. Выберите новую отдельную папку.')
        if marker.exists():
            try:
                identity = json.loads(marker.read_text(encoding='utf-8'))
                if identity['kind'] != 'wl-traders-docker-v1' or str(uuid.UUID(identity['id'])) != identity['id']:
                    raise ValueError('invalid marker')
            except (OSError, ValueError, KeyError) as exc:
                raise RuntimeError('Папка не принадлежит поддерживаемой установке WL Traders') from exc
            if copy_source and (self.target / '.initialized').exists():
                self.log('Перед повторной установкой создаю резервную копию существующих данных и конфигурации.')
                self.compose('up', '-d', '--wait', '--wait-timeout', '180', 'mysql', timeout=240)
                self.compose('run', '--rm', '--no-deps', 'backup', '--once', timeout=1800)
                # Prevent a daily process from combining old single-file mounts with new settings.
                self.compose('stop', 'backup')
        else:
            self.target.mkdir(parents=True, exist_ok=True, mode=0o700)
            identity = dict(kind='wl-traders-docker-v1', id=str(uuid.uuid4()))
            atomic_write(marker, json.dumps(identity))
        for name in ('config', 'backups', 'config/tls'):
            path = self.target / name
            if path.is_symlink():
                raise RuntimeError('Папка конфигурации/копий не должна быть символической ссылкой')
            path.mkdir(exist_ok=True, mode=0o700)
        state_path = self.target / 'config/state.json'
        if state_path.exists():
            self.state = json.loads(state_path.read_text(encoding='utf-8'))
            for key in ('app_key', 'db_password', 'db_root_password', 'admin_password', 'db_name', 'db_user'):
                if not self.state.get(key):
                    raise RuntimeError('Неполная сохранённая конфигурация; восстановите config/state.json из резервной копии. Новые секреты не созданы.')
            if any(self.settings[key] != self.state[key] for key in ('db_name', 'db_user')):
                raise RuntimeError('Нельзя менять имя существующей базы или пользователя при повторной установке')
            self.log('Повторная установка: ключ приложения, пароли и база сохранены; пароль администратора не меняется.')
        else:
            if (self.target / '.env').exists():
                raise RuntimeError('Утерян config/state.json; восстановите резервную копию, не создавайте новые ключи для существующих данных')
            self.state = dict(app_key='base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
                              db_password=self.settings['db_password'] or secrets.token_urlsafe(32),
                              db_root_password=secrets.token_urlsafe(32), admin_password=self.settings['admin_password'],
                              db_name=self.settings['db_name'], db_user=self.settings['db_user'])
            atomic_write(state_path, json.dumps(self.state, ensure_ascii=False))
        self.secret_values.update(str(value) for key, value in self.state.items() if 'password' in key or 'key' in key)
        if copy_source:
            self.copy_source()
        # Compose env_file raw preserves dollar signs, quotes, spaces and backslashes verbatim.
        s = self.settings
        env = dict(APP_NAME=s['app_name'], APP_ENV='production', APP_KEY=self.state['app_key'], APP_DEBUG='false',
                   APP_TIMEZONE=s['timezone'], APP_URL=s['app_url'], APP_LOCALE=s['locale'], APP_FALLBACK_LOCALE='en',
                   LOG_CHANNEL='stderr', LOG_LEVEL='warning', DB_CONNECTION='mysql', DB_HOST='mysql', DB_PORT='3306',
                   DB_DATABASE=self.state['db_name'], DB_USERNAME=self.state['db_user'], DB_PASSWORD=self.state['db_password'],
                   SESSION_DRIVER='redis', SESSION_LIFETIME=s['session_lifetime'], SESSION_SECURE_COOKIE=str(s['https_mode'] == 'cloudflare').lower(),
                   QUEUE_CONNECTION='redis', CACHE_STORE='redis', REDIS_CLIENT='phpredis', REDIS_HOST='redis', REDIS_PORT='6379',
                   MAIL_MAILER='log', MAIL_FROM_ADDRESS='no-reply@example.invalid', MAIL_FROM_NAME=s['app_name'],
                   TELEGRAM_REDIRECT_URI=s['app_url'] + '/auth/telegram/callback', TELESCOPE_ENABLED='false', NIGHTWATCH_ENABLED='false')
        previous_env = {}
        app_env_path = self.target / 'config/app.env'
        if app_env_path.exists():
            previous_env = dict(line.split('=', 1) for line in app_env_path.read_text(encoding='utf-8').splitlines() if '=' in line)
        for field in ('telegram_bot_name', 'telegram_bot_token', 'telegram_webhook_token', 'trongrid_api_key', 'ipgeolocation_api_key'):
            env[field.upper()] = s[field] or previous_env.get(field.upper(), '')
        # Keep manually configured optional integrations while updating installer-managed values.
        previous_env.update(env)
        atomic_write(app_env_path, ''.join(f'{key}={value}\n' for key, value in previous_env.items()))
        mysql_env = dict(MYSQL_DATABASE=self.state['db_name'], MYSQL_USER=self.state['db_user'], MYSQL_PASSWORD=self.state['db_password'], MYSQL_ROOT_PASSWORD=self.state['db_root_password'])
        atomic_write(self.target / 'config/mysql.env', ''.join(f'{key}={value}\n' for key, value in mysql_env.items()))
        project = 'wl-traders-' + identity['id'][:12]
        port = 443 if s['https_mode'] == 'cloudflare' else s['http_port']
        atomic_write(self.target / '.env', f'COMPOSE_PROJECT_NAME={project}\nCOMPOSE_PROFILES={"backups" if s["install_backups"] else ""}\nWL_BIND_ADDRESS={s["bind_address"]}\nWL_HTTP_PORT={port}\nWL_CONTAINER_PORT={443 if s["https_mode"] == "cloudflare" else 80}\nWL_BACKUP_RETENTION_DAYS={s["backup_retention_days"]}\n')
        origin = Path(__file__).resolve().parent.parent
        compose_text = (origin / 'compose.yaml').read_text(encoding='utf-8')
        if s['https_mode'] == 'cloudflare':
            compose_text = compose_text.replace('    # Additional Cloudflare HTTP port', '      - "0.0.0.0:80:80"\n    # Additional Cloudflare HTTP port')
        atomic_write(self.target / 'compose.yaml', compose_text)
        ranges = []
        if s['https_mode'] == 'cloudflare':
            from server import cloudflare_ip_ranges, validate_cloudflare_dns
            validate_cloudflare_dns(s['domain'])
            ranges = cloudflare_ip_ranges()
            atomic_write(self.target / 'config/tls/origin.pem', s['cloudflare_cert'] + '\n')
            atomic_write(self.target / 'config/tls/origin.key', s['cloudflare_key'] + '\n')
            atomic_write(self.target / 'config/cloudflare-ranges.json', json.dumps(ranges))
        atomic_write(self.target / 'config/nginx.conf', self.nginx_config(ranges))
        atomic_write(self.target / 'config/php.ini', f'memory_limit=512M\nupload_max_filesize={s["upload_limit_mb"]}M\npost_max_size={s["upload_limit_mb"]}M\nmax_execution_time=120\nopcache.enable=1\nopcache.validate_timestamps=0\n', 0o644)

    def nginx_config(self, ranges: list[str]) -> str:
        cf = self.settings['https_mode'] == 'cloudflare'
        trust = ''
        if cf:
            for value in ranges:
                ipaddress.ip_network(value, strict=True)
            trust = 'geo $realip_remote_addr $cloudflare_peer {\n default 0;\n' + ''.join(f' {value} 1;\n' for value in ranges) + '}\n'
            trust += ''.join(f'set_real_ip_from {value};\n' for value in ranges)
            trust += 'real_ip_header CF-Connecting-IP;\nreal_ip_recursive on;\n'
        listen = 'listen 443 ssl;\n listen [::]:443 ssl;' if cf else 'listen 80;\n listen [::]:80;'
        tls = 'ssl_certificate /etc/wl-config/origin.pem;\n ssl_certificate_key /etc/wl-config/origin.key;\n ssl_protocols TLSv1.2 TLSv1.3;\n if ($cloudflare_peer = 0) { return 403; }' if cf else ''
        redirect = ''
        if cf:
            redirect = f'''server {{
 listen 80;
 listen [::]:80;
 server_name {self.settings['domain']};
 if ($cloudflare_peer = 0) {{ return 403; }}
 return 301 https://{self.settings['domain']}$request_uri;
}}
'''
        return trust + redirect + f'''server {{
 {listen}
 server_name {self.settings['domain'] or '_'};
 {tls}
 root /var/www/html/public;
 index index.php;
 server_tokens off;
 client_max_body_size {self.settings['upload_limit_mb']}M;
 add_header X-Content-Type-Options nosniff always;
 add_header X-Frame-Options SAMEORIGIN always;
 location / {{ try_files $uri $uri/ /index.php?$query_string; }}
 location = /index.php {{
  include fastcgi_params;
  fastcgi_param SCRIPT_FILENAME $document_root/index.php;
  fastcgi_param HTTP_PROXY "";
  fastcgi_param HTTPS {"on" if cf else "off"};
  fastcgi_pass app:9000;
  fastcgi_buffer_size 32k;
  fastcgi_buffers 16 16k;
 }}
 location ~ \\.php$ {{ return 404; }}
 location ~ /\\. {{ deny all; }}
}}
# Separate non-published listener for container readiness. Never bypasses the origin ACL.
server {{
 listen 127.0.0.1:8081;
 location = /up {{
  include fastcgi_params;
  fastcgi_param SCRIPT_FILENAME /var/www/html/public/index.php;
  fastcgi_param SCRIPT_NAME /index.php;
  fastcgi_pass app:9000;
 }}
}}
'''

    def install(self) -> list[dict[str, str]]:
        self.progress(1)
        self.check_local_engine()
        version = self.run(['docker', 'compose', 'version', '--short'], timeout=30, quiet=True).strip().lstrip('v')
        match = re.match(r'(\d+)\.(\d+)', version)
        if not match or tuple(map(int, match.groups())) < (2, 30):
            raise RuntimeError('Нужен Docker Compose 2.30+ (обновите Docker Desktop / compose-plugin)')
        info = json.loads(self.run(['docker', 'info', '--format', '{{json .}}'], timeout=30, quiet=True))
        if info.get('OSType') != 'linux':
            raise RuntimeError('Docker должен использовать Linux containers')
        if info.get('NCPU', 0) < 2 or info.get('MemTotal', 0) < 3500000000:
            raise RuntimeError('Выделите Docker минимум 2 CPU и 4 ГБ памяти (рекомендуется 6–8 ГБ для сборки)')
        ancestor = self.target
        while not ancestor.exists():
            ancestor = ancestor.parent
        if shutil.disk_usage(ancestor).free < 20 * 1024**3:
            raise RuntimeError('Нужно минимум 20 ГБ свободного места')
        self.progress(2)
        self.prepare()
        self.compose('config', '--quiet', quiet=True)
        self.check_ports()
        self.progress(3)
        self.compose('build', '--pull', 'app', 'web', 'backup', timeout=3600)
        self.progress(4)
        self.compose('up', '-d', '--wait', '--wait-timeout', '180', 'mysql', 'redis', timeout=240)
        self.progress(5)
        # system:install drops every table; deliberately use the non-destructive bootstrap instead.
        self.compose('run', '--rm', '--no-deps', 'init', timeout=600)
        atomic_write(self.target / '.initialized', time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()))
        self.compose('up', '-d', '--no-deps', '--force-recreate', '--wait', '--wait-timeout', '120', 'app', timeout=180)
        self.compose('run', '--rm', '--no-deps', 'web', 'nginx', '-t', timeout=60)
        self.progress(6)
        services = ['web', 'horizon', 'schedule']
        if self.settings['install_backups']:
            self.compose('run', '--rm', '--no-deps', 'backup', '--once', timeout=600)
            services.append('backup')
        else:
            self.compose('stop', 'backup')
        # Atomic host-file replacement requires fresh bind mounts, even with unchanged images.
        self.compose('up', '-d', '--no-deps', '--force-recreate', '--wait', '--wait-timeout', '180', *services, timeout=240)
        self.progress(7)
        self.compose('exec', '-T', 'web', 'wget', '-qO-', 'http://127.0.0.1:8081/up', timeout=30)
        self.compose('exec', '-T', 'horizon', 'php', 'artisan', 'horizon:status', timeout=60)
        self.compose('exec', '-T', 'app', 'php', 'artisan', 'schedule:list', '--no-ansi', timeout=60)
        self.compose('ps', timeout=30)
        return [dict(name='Docker', value='MySQL, Redis, PHP, Nginx, Horizon и планировщик готовы'),
                dict(name='Данные', value='Тома и секреты сохраняются при повторной установке'),
                dict(name='Сайт', value='Внутренняя HTTP-проверка /up пройдена'),
                dict(name='Cloudflare', value='Nginx TLS-конфигурация и ACL проверены; внешний HTTPS через Cloudflare требует отдельной проверки' if self.settings['https_mode'] == 'cloudflare' else 'Не используется; HTTP без шифрования'),
                dict(name='Бэкапы', value='Пробная копия создана; далее ежедневно' if self.settings['install_backups'] else 'Отключены')]
