import importlib.util
import tempfile
import unittest
from pathlib import Path


MODULE_PATH = Path(__file__).resolve().parents[1] / "server.py"
SPEC = importlib.util.spec_from_file_location("wl_traders_installer", MODULE_PATH)
assert SPEC and SPEC.loader
installer = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(installer)


class InstallerValidationTest(unittest.TestCase):
    def valid_payload(self):
        return {
            "app_name": "WL Traders",
            "app_url": "http://203.0.113.10",
            "install_path": "/var/www/wl-traders",
            "timezone": "Europe/Moscow",
            "locale": "ru",
            "session_lifetime": 10080,
            "db_name": "wl_traders",
            "db_user": "wl_traders",
            "db_password": "",
            "admin_password": "correct-horse-battery-staple",
            "admin_password_confirmation": "correct-horse-battery-staple",
            "upload_limit_mb": 64,
        }

    def test_requires_ubuntu_2604(self):
        issues = installer.environment_issues(
            os_id="ubuntu",
            version_id="24.04",
            cpu_count=2,
            memory_bytes=4 * 1024**3,
            disk_bytes=50 * 1024**3,
        )
        self.assertTrue(any("26.04" in issue for issue in issues))

    def test_accepts_realistic_four_gigabyte_server(self):
        issues = installer.environment_issues(
            os_id="ubuntu",
            version_id="26.04",
            cpu_count=2,
            memory_bytes=int(3.75 * 1024**3),
            disk_bytes=48 * 1024**3,
        )
        self.assertEqual([], issues)

    def test_rejects_mismatched_admin_password_confirmation(self):
        payload = self.valid_payload()
        payload["admin_password_confirmation"] = "different-password"
        with self.assertRaisesRegex(ValueError, "не совпадают"):
            installer.normalize_settings(payload)

    def test_rejects_unknown_timezone(self):
        payload = self.valid_payload()
        payload["timezone"] = "Mars/Olympus"
        with self.assertRaisesRegex(ValueError, "часовой пояс"):
            installer.normalize_settings(payload)

    def test_rejects_app_url_with_query_or_fragment(self):
        payload = self.valid_payload()
        payload["app_url"] = "http://203.0.113.10/setup?debug=1"
        with self.assertRaisesRegex(ValueError, "без пути"):
            installer.normalize_settings(payload)

    def test_redacts_common_secret_assignments(self):
        text = "DB_PASSWORD=hunter2 TELEGRAM_BOT_TOKEN=123:secret API_KEY=abc"
        redacted = installer.redact_sensitive(text)
        self.assertNotIn("hunter2", redacted)
        self.assertNotIn("123:secret", redacted)
        self.assertNotIn("abc", redacted)
        self.assertEqual(3, redacted.count("[скрыто]"))

    def test_env_file_is_private_and_production_safe(self):
        payload = self.valid_payload()
        settings = installer.normalize_settings(payload)
        with tempfile.TemporaryDirectory() as directory:
            target = Path(directory)
            (target / ".env.example").write_text(
                "APP_ENV=local\nAPP_DEBUG=true\nAPP_URL=http://localhost\n"
                "DB_PASSWORD=\nTELESCOPE_ENABLED=true\n",
                encoding="utf-8",
            )
            installer.write_env(target, settings)
            env_path = target / ".env"
            content = env_path.read_text(encoding="utf-8")
            self.assertIn("APP_ENV=production", content)
            self.assertIn("APP_DEBUG=false", content)
            self.assertIn("TELESCOPE_ENABLED=false", content)
            self.assertEqual(0o640, env_path.stat().st_mode & 0o777)


if __name__ == "__main__":
    unittest.main()
