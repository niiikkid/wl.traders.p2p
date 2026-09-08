import json
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from test_server import payload
import server
try:
    import runtime
except ImportError:
    runtime = None


class RuntimeTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.path = Path(self.temp.name) / 'installation with spaces'

    def make_runtime(self):
        self.assertIsNotNone(runtime, 'Docker runtime is not implemented')
        settings = server.normalize_settings(payload(install_path=str(self.path)))
        return runtime.DockerRuntime(server.REPO_ROOT, settings, lambda line: None, lambda index: None, set())

    def test_refuses_foreign_directory(self):
        obj = self.make_runtime()
        self.path.mkdir()
        (self.path / 'unrelated.txt').write_text('keep me')
        with self.assertRaisesRegex(RuntimeError, 'принадлежит'):
            obj.prepare()
        self.assertEqual('keep me', (self.path / 'unrelated.txt').read_text())

    def test_retry_preserves_secrets_and_project(self):
        obj = self.make_runtime()
        obj.prepare(copy_source=False)
        before = (self.path / 'config' / 'state.json').read_bytes()
        obj.settings['admin_password'] = 'different-password'
        obj.settings['db_password'] = 'different-db-password'
        obj.prepare(copy_source=False)
        self.assertEqual(before, (self.path / 'config' / 'state.json').read_bytes())
        env = (self.path / 'config' / 'app.env').read_text()
        self.assertIn('DB_HOST=mysql', env)
        self.assertIn('REDIS_HOST=redis', env)
        self.assertNotIn('different-password', env)
        self.assertNotIn('different-db-password', env)
        self.assertEqual(0o600, (self.path / 'config' / 'state.json').stat().st_mode & 0o777)

    def test_prepare_generates_and_preserves_webhook_api_tokens(self):
        obj = self.make_runtime()
        obj.prepare(copy_source=False)
        first_state = json.loads((self.path / 'config' / 'state.json').read_text())
        first_env = dict(
            line.split('=', 1)
            for line in (self.path / 'config' / 'app.env').read_text().splitlines()
            if '=' in line
        )

        for name in ('API_DEPOSIT_TOKEN', 'API_WITHDRAW_TOKEN'):
            self.assertGreaterEqual(len(first_env[name]), 32)
            self.assertEqual(first_state[name.lower()], first_env[name])

        self.make_runtime().prepare(copy_source=False)
        second_state = json.loads((self.path / 'config' / 'state.json').read_text())
        second_env = dict(
            line.split('=', 1)
            for line in (self.path / 'config' / 'app.env').read_text().splitlines()
            if '=' in line
        )

        self.assertEqual(first_state, second_state)
        self.assertEqual(first_env['API_DEPOSIT_TOKEN'], second_env['API_DEPOSIT_TOKEN'])
        self.assertEqual(first_env['API_WITHDRAW_TOKEN'], second_env['API_WITHDRAW_TOKEN'])

    def test_os_lock_rejects_concurrent_install(self):
        obj = self.make_runtime()
        with obj.lock():
            with self.assertRaises(RuntimeError):
                with self.make_runtime().lock():
                    pass
        with obj.lock():
            pass

    def test_compose_argv_has_no_shell(self):
        obj = self.make_runtime()
        obj.prepare(copy_source=False)
        command = obj.compose_args('ps')
        self.assertEqual(['docker', 'compose'], command[:2])
        self.assertIn(str(self.path / 'compose.yaml'), command)
        self.assertEqual('ps', command[-1])

    def test_proxy_restricts_real_peer_not_spoofable_header(self):
        obj = self.make_runtime()
        obj.settings.update(https_mode='cloudflare', domain='pay.example.com', cloudflare_cert='cert', cloudflare_key='key')
        config = obj.nginx_config(['104.16.0.0/13', '2606:4700::/32'])
        self.assertIn('geo $realip_remote_addr $cloudflare_peer', config)
        self.assertIn('if ($cloudflare_peer = 0) { return 403; }', config)
        self.assertIn('ssl_protocols TLSv1.2 TLSv1.3;', config)
        self.assertNotIn('allow 127.0.0.1', config)

    def test_source_copy_excludes_local_secrets(self):
        obj = self.make_runtime()
        source = Path(self.temp.name) / 'source'
        source.mkdir()
        for name in ['.env', '.env.local', 'artisan']:
            (source / name).write_text(name)
        (source / '.env.example').write_text('safe')
        obj.source = source
        obj.prepare(copy_source=False)
        obj.copy_source()
        self.assertFalse((self.path / 'src' / '.env').exists())
        self.assertFalse((self.path / 'src' / '.env.local').exists())
        self.assertTrue((self.path / 'src' / '.env.example').exists())

    def test_rejects_remote_docker_context(self):
        obj = self.make_runtime()
        with patch.dict(runtime.os.environ, {'DOCKER_HOST': 'ssh://root@remote', 'DOCKER_CONTEXT': ''}):
            with self.assertRaisesRegex(RuntimeError, 'локальный'):
                obj.check_local_engine()
        with patch.dict(runtime.os.environ, {'DOCKER_HOST': '', 'DOCKER_CONTEXT': ''}), \
             patch.object(obj, 'run', return_value='[{"Endpoints":{"docker":{"Host":"tcp://remote:2376"}}}]'):
            with self.assertRaises(RuntimeError):
                obj.check_local_engine()

    def test_occupied_foreign_port_rejected(self):
        import socket
        obj = self.make_runtime()
        with socket.socket() as listener:
            listener.bind(('127.0.0.1', 0))
            listener.listen()
            obj.settings['http_port'] = listener.getsockname()[1]
            with patch.object(obj, 'compose', return_value='[]'):
                with self.assertRaisesRegex(RuntimeError, 'занят'):
                    obj.check_ports()

    def test_source_retry_does_not_leave_removed_code(self):
        obj = self.make_runtime()
        source = Path(self.temp.name) / 'source'
        source.mkdir()
        (source / 'removed.php').write_text('old')
        obj.source = source
        obj.prepare(copy_source=False)
        obj.copy_source()
        (source / 'removed.php').unlink()
        (source / 'new.php').write_text('new')
        obj.copy_source()
        self.assertFalse((self.path / 'src/removed.php').exists())
        self.assertTrue((self.path / 'src/new.php').exists())

    def test_utf8_credentials_survive_windows_legacy_locale(self):
        obj = self.make_runtime()
        secret = 'пароль-базы-надёжный-123'
        obj.settings['db_password'] = secret
        obj.prepare(copy_source=False)
        original = Path.read_text
        def windows_read(path, *args, **kwargs):
            kwargs.setdefault('encoding', 'cp1251')
            return original(path, *args, **kwargs)
        with patch.object(Path, 'read_text', windows_read):
            obj.prepare(copy_source=False)
        self.assertEqual(secret, obj.state['db_password'])
        self.assertIn('DB_PASSWORD=' + secret, (self.path / 'config/app.env').read_text(encoding='utf-8'))

    def test_recreates_services_after_atomic_configuration_changes(self):
        obj = self.make_runtime()
        obj.prepare(copy_source=False)
        info = json.dumps({'OSType': 'linux', 'NCPU': 2, 'MemTotal': 4 * 1024**3})
        with patch.object(obj, 'check_local_engine'), patch.object(obj, 'check_ports'), \
             patch.object(obj, 'prepare'), patch.object(obj, 'run', side_effect=['5.3.1', info]), \
             patch.object(obj, 'compose', return_value='') as compose:
            obj.install()
        starts = [call.args for call in compose.call_args_list if call.args[0] == 'up']
        for service in ('app', 'web', 'horizon', 'schedule', 'backup'):
            matching = [args for args in starts if service in args]
            self.assertTrue(matching, service)
            self.assertIn('--force-recreate', matching[-1], service)

    def test_bootstrap_never_uses_destructive_installer(self):
        self.assertIsNotNone(runtime, 'Docker runtime is not implemented')
        bootstrap = (server.REPO_ROOT / 'installer/docker/bootstrap.php').read_text()
        self.assertNotIn("'system:install'", bootstrap)
        self.assertNotIn('migrate:fresh', bootstrap)
        self.assertIn("'migrate'", bootstrap)
        self.assertIn('.initialized', bootstrap)


if __name__ == '__main__':
    unittest.main()
