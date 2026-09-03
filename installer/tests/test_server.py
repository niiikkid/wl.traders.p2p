import importlib.util
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch


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

    def test_public_ip_prefers_explicit_bootstrap_value(self):
        previous_cache = getattr(installer, "PUBLIC_IP_CACHE")
        setattr(installer, "PUBLIC_IP_CACHE", None)
        self.addCleanup(setattr, installer, "PUBLIC_IP_CACHE", previous_cache)

        with patch.dict(installer.os.environ, {"WL_TRADERS_PUBLIC_IP": "8.8.8.8"}):
            with patch.object(installer, "urlopen") as request:
                self.assertEqual("8.8.8.8", installer.public_ip())

        request.assert_not_called()

    def test_public_ip_does_not_cache_private_fallback(self):
        previous_cache = getattr(installer, "PUBLIC_IP_CACHE")
        setattr(installer, "PUBLIC_IP_CACHE", None)
        self.addCleanup(setattr, installer, "PUBLIC_IP_CACHE", previous_cache)

        with patch.dict(installer.os.environ, {"WL_TRADERS_PUBLIC_IP": ""}):
            with patch.object(installer, "urlopen", side_effect=OSError):
                with patch.object(installer.subprocess, "check_output", return_value="10.0.0.5\n"):
                    self.assertEqual("10.0.0.5", installer.public_ip())

        self.assertIsNone(getattr(installer, "PUBLIC_IP_CACHE"))

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

    def test_domain_mode_builds_http_application_url(self):
        payload = self.valid_payload()
        payload.update(
            {
                "site_mode": "domain",
                "domain": "Pay.Example.com.",
            }
        )

        settings = installer.normalize_settings(payload)

        self.assertEqual("domain", settings["site_mode"])
        self.assertEqual("pay.example.com", settings["domain"])
        self.assertEqual("http://pay.example.com", settings["app_url"])

    def test_cloudflare_mode_builds_https_application_url(self):
        payload = self.valid_payload()
        payload.update(
            {
                "site_mode": "domain",
                "domain": "pay.example.com",
                "https_mode": "cloudflare",
            }
        )

        settings = installer.normalize_settings(payload)

        self.assertEqual("cloudflare", settings["https_mode"])
        self.assertEqual("https://pay.example.com", settings["app_url"])

    def test_ip_mode_forces_plain_http_despite_cloudflare_choice(self):
        payload = self.valid_payload()
        payload.update({"site_mode": "ip", "https_mode": "cloudflare"})

        settings = installer.normalize_settings(payload)

        self.assertEqual("none", settings["https_mode"])
        self.assertTrue(settings["app_url"].startswith("http://"))

    def test_rejects_unknown_https_mode(self):
        payload = self.valid_payload()
        payload.update(
            {"site_mode": "domain", "domain": "pay.example.com", "https_mode": "vpn"}
        )

        with self.assertRaisesRegex(ValueError, "HTTPS"):
            installer.normalize_settings(payload)

    def test_domain_mode_rejects_url_in_domain_field(self):
        payload = self.valid_payload()
        payload.update(
            {
                "site_mode": "domain",
                "domain": "https://pay.example.com",
            }
        )

        with self.assertRaisesRegex(ValueError, "без http"):
            installer.normalize_settings(payload)

    def test_ip_mode_requires_plain_http_ip_address(self):
        payload = self.valid_payload()
        payload.update({"site_mode": "ip", "app_url": "https://203.0.113.10"})

        with self.assertRaisesRegex(ValueError, "http://"):
            installer.normalize_settings(payload)

    def test_ip_mode_rejects_http_on_port_443(self):
        payload = self.valid_payload()
        payload.update({"site_mode": "ip", "app_url": "http://203.0.113.10:443"})

        with self.assertRaisesRegex(ValueError, "порт 80"):
            installer.normalize_settings(payload)

    @patch.object(installer, "resolved_ipv4_addresses", return_value={"203.0.113.10"})
    def test_domain_dns_must_point_only_to_server(self, _resolver):
        self.assertEqual(
            ["203.0.113.10"],
            installer.validate_domain_dns("pay.example.com", "203.0.113.10"),
        )

        with patch.object(
            installer,
            "resolved_ipv4_addresses",
            return_value={"203.0.113.10", "198.51.100.20"},
        ):
            with self.assertRaisesRegex(RuntimeError, "серое облако"):
                installer.validate_domain_dns("pay.example.com", "203.0.113.10")

    @patch.object(
        installer,
        "resolved_ipv4_addresses",
        return_value={"104.26.6.25", "172.67.74.17"},
    )
    def test_cloudflare_dns_accepts_proxied_addresses(self, _resolver):
        self.assertEqual(
            ["104.26.6.25", "172.67.74.17"],
            installer.validate_cloudflare_dns("pay.example.com"),
        )

    @patch.object(installer, "resolved_ipv4_addresses", return_value=set())
    def test_cloudflare_dns_requires_resolution(self, _resolver):
        with self.assertRaisesRegex(RuntimeError, "Proxied"):
            installer.validate_cloudflare_dns("pay.example.com")

    def test_installer_page_contains_six_steps_and_cloudflare_choice(self):
        page = (MODULE_PATH.parent / "page.html").read_text(encoding="utf-8")

        self.assertEqual(6, page.count('class="panel" data-step='))
        self.assertIn("Cloudflare", page)
        self.assertIn("https_mode", page)
        self.assertIn("Flexible", page)
        self.assertIn("Always Use HTTPS", page)
        self.assertNotIn("DNS only", page)

    def test_hidden_attribute_is_enforced_in_css(self):
        page = (MODULE_PATH.parent / "page.html").read_text(encoding="utf-8")

        self.assertIn("[hidden]{display:none!important}", page)
        self.assertIn('id="install" type="submit" hidden', page)

    def test_temporary_firewall_rule_has_persistent_one_shot_cleanup(self):
        source = MODULE_PATH.read_text(encoding="utf-8")

        self.assertIn("OnCalendar=", source)
        self.assertIn("Persistent=true", source)
        self.assertIn("ufw --force delete allow", source)
        self.assertIn("wl-traders-installer-firewall-cleanup.timer", source)

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
