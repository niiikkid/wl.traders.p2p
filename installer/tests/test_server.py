import importlib.util
import json
import sys
import tempfile
import threading
import time
import unittest
from pathlib import Path
from unittest.mock import patch
from urllib.request import Request, urlopen
from urllib.error import HTTPError

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import server as installer


def payload(**extra):
    data = dict(app_name='WL Traders', site_mode='local', install_path=str(Path.home() / 'wl-traders'),
                admin_password='correct-horse-battery', admin_password_confirmation='correct-horse-battery',
                db_name='wl_traders', db_user='wl_traders')
    data.update(extra)
    return data


class ValidationTest(unittest.TestCase):
    def test_local_defaults(self):
        settings = installer.normalize_settings(payload())
        self.assertEqual(('http://localhost:8080', 8080, '127.0.0.1'),
                         (settings['app_url'], settings['http_port'], settings['bind_address']))

    def test_ip_custom_port(self):
        settings = installer.normalize_settings(payload(site_mode='ip', app_url='http://192.0.2.1:8090', http_port=8090))
        self.assertEqual('0.0.0.0', settings['bind_address'])

    def test_inconsistent_ports_rejected(self):
        for extra in [dict(http_port=8081, app_url='http://localhost:8080'),
                      dict(site_mode='ip', http_port=80, app_url='http://192.0.2.1:8080'),
                      dict(http_port=True), dict(http_port=70000), dict(http_port='80; touch x')]:
            with self.subTest(extra=extra), self.assertRaises(ValueError):
                installer.normalize_settings(payload(**extra))

    def test_local_cannot_publish_foreign_url(self):
        with self.assertRaises(ValueError):
            installer.normalize_settings(payload(app_url='http://192.0.2.1:8080'))

    def test_domain_http(self):
        settings = installer.normalize_settings(payload(site_mode='domain', domain='Pay.Example.com.'))
        self.assertEqual('http://pay.example.com', settings['app_url'])
        self.assertEqual(80, settings['http_port'])

    def test_rejects_invalid_values(self):
        for extra in [dict(admin_password_confirmation='wrong'), dict(timezone='Mars/Olympus'),
                      dict(db_name='a; DROP'), dict(app_name='a\nAPP_KEY=bad'),
                      dict(site_mode='ip', app_url='https://192.0.2.1'),
                      dict(site_mode='domain', domain='https://bad.com'), dict(install_path='/')]:
            with self.subTest(extra=extra), self.assertRaises(ValueError):
                installer.normalize_settings(payload(**extra))

    def test_desktop_never_fetches_public_ip(self):
        with patch.object(installer.platform, 'system', return_value='Darwin'), patch.object(installer, 'urlopen') as fetch:
            self.assertEqual('127.0.0.1', installer.public_ip())
            fetch.assert_not_called()

    def test_default_paths(self):
        with patch.dict(installer.os.environ, {'WL_TRADERS_INSTALL_DIR': '/tmp/custom'}):
            self.assertEqual(Path('/tmp/custom'), installer.default_install_path())

    def test_cf_dns_rejects_non_cf_address(self):
        with patch.object(installer, 'resolved_ipv4_addresses', return_value={'8.8.8.8'}), \
             patch.object(installer, 'cloudflare_ip_ranges', return_value=['104.16.0.0/13']):
            with self.assertRaises(RuntimeError):
                installer.validate_cloudflare_dns('pay.example.com')

    def test_redaction(self):
        secret = 'arbitrary-unique-password'
        with patch.object(installer, 'SECRET_VALUES', {secret}):
            value = installer.redact_sensitive('DB_PASSWORD=hunter2 APP_KEY=base64:xxx ' + secret)
        for text in ['hunter2', 'base64:xxx', secret]:
            self.assertNotIn(text, value)


class HttpTest(unittest.TestCase):
    def setUp(self):
        installer.set_state(phase='ready', logs=[])
        self.server = installer.ThreadingHTTPServer(('127.0.0.1', 0), installer.InstallerHandler)
        self.server.install_token = 'test-token'
        self.server.expires_at = time.monotonic() + 100
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.thread.start()
        self.base = 'http://127.0.0.1:' + str(self.server.server_port)

    def tearDown(self):
        self.server.shutdown()
        self.server.server_close()
        self.thread.join()

    def post(self):
        request = Request(self.base + '/install?token=test-token', data=json.dumps(payload()).encode(),
                          headers={'Content-Type': 'application/json'})
        try:
            with urlopen(request) as response:
                return response.status
        except HTTPError as exc:
            code = exc.code
            exc.close()
            return code

    def test_atomic_claim_and_retry(self):
        with patch.object(installer, 'perform_install'):
            results = []
            threads = [threading.Thread(target=lambda: results.append(self.post())) for _ in range(4)]
            for thread in threads:
                thread.start()
            for thread in threads:
                thread.join()
            self.assertEqual([202, 409, 409, 409], sorted(results))
            installer.set_state(phase='failed')
            self.assertEqual(202, self.post())

    def test_token_required(self):
        with self.assertRaises(HTTPError) as error:
            urlopen(self.base + '/status')
        self.assertEqual(403, error.exception.code)
        error.exception.close()


if __name__ == '__main__':
    unittest.main()
