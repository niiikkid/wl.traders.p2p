import subprocess
import unittest
from pathlib import Path


class InstallScriptBootstrapTest(unittest.TestCase):
    def test_can_bootstrap_from_a_single_curl_command(self):
        script = Path(__file__).resolve().parents[2] / "install.sh"
        content = script.read_text(encoding="utf-8")

        self.assertIn("WL_TRADERS_SOURCE_ARCHIVE_URL", content)
        self.assertIn("codeload.github.com/niiikkid/wl.traders.p2p/tar.gz/refs/heads/main", content)
        self.assertIn("tar -xzf", content)
        self.assertIn("installer/page.html", content)
        self.assertIn("exec", content)

    def test_enforces_supported_os_and_single_instance(self):
        script = Path(__file__).resolve().parents[2] / "install.sh"
        content = script.read_text(encoding="utf-8")

        self.assertIn('VERSION_ID:-} != "26.04"', content)
        self.assertIn("wl-traders-installer.lock", content)
        self.assertIn("flock -n", content)
        self.assertIn("--expires-in 2700", content)

    def test_can_run_from_standard_input_without_bash_source(self):
        script = Path(__file__).resolve().parents[2] / "install.sh"
        result = subprocess.run(
            ["bash"],
            input=script.read_text(encoding="utf-8"),
            text=True,
            capture_output=True,
            check=False,
        )

        self.assertNotIn("BASH_SOURCE[0]: unbound variable", result.stderr)
        self.assertIn("Запустите одной командой от root", result.stdout)


if __name__ == "__main__":
    unittest.main()
