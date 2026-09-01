import unittest
from pathlib import Path


class InstallScriptBootstrapTest(unittest.TestCase):
    def test_can_bootstrap_from_a_single_curl_command(self):
        script = Path(__file__).resolve().parents[2] / "install.sh"
        content = script.read_text(encoding="utf-8")

        self.assertIn("WL_TRADERS_SOURCE_ARCHIVE_URL", content)
        self.assertIn("codeload.github.com/niiikkid/wl.traders.p2p/tar.gz/refs/heads/main", content)
        self.assertIn("tar -xzf", content)
        self.assertIn("exec", content)


if __name__ == "__main__":
    unittest.main()
