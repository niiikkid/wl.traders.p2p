import os
import subprocess
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


class InstallScriptBootstrapTest(unittest.TestCase):
    def test_shell_syntax(self):
        result = subprocess.run(['bash', '-n', str(ROOT / 'install.sh')], capture_output=True, text=True)
        self.assertEqual(0, result.returncode, result.stderr)

    def test_help_from_stdin_needs_no_privileges_or_downloads(self):
        result = subprocess.run(['bash', '-s', '--', '--help'], input=(ROOT / 'install.sh').read_text(), capture_output=True, text=True)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertIn('Docker', result.stdout)
        self.assertNotIn('BASH_SOURCE[0]: unbound variable', result.stderr)

    def test_bad_port_is_rejected_before_changes(self):
        result = subprocess.run(['bash', str(ROOT / 'install.sh')], env={**os.environ, 'WL_TRADERS_INSTALLER_PORT': '12; touch nope'}, capture_output=True, text=True)
        self.assertNotEqual(0, result.returncode)
        self.assertIn('Порт', result.stdout + result.stderr)

    def test_launcher_passes_loopback_default_and_arguments(self):
        with tempfile.TemporaryDirectory(prefix='wl launcher ') as directory:
            root = Path(directory)
            (root / 'installer').mkdir()
            (root / 'installer' / 'server.py').write_text('')
            (root / 'installer' / 'page.html').write_text('')
            (root / 'install.sh').write_text((ROOT / 'install.sh').read_text())
            binary = root / 'bin'
            binary.mkdir()
            for name, content in {
                'docker': '#!/bin/sh\nexit 0\n',
                'python3': '#!/bin/sh\ncase "$1" in\n-c) exit 0;;\n*) printf "%s\\n" "$@";;\nesac\n',
            }.items():
                path = binary / name
                path.write_text(content)
                path.chmod(0o755)
            result = subprocess.run(['bash', str(root / 'install.sh')], env={**os.environ, 'PATH': str(binary) + os.pathsep + os.environ['PATH'], 'WL_TRADERS_INSTALLER_PORT': '9876'}, capture_output=True, text=True)
            self.assertEqual(0, result.returncode, result.stderr)
            self.assertIn('--host\n127.0.0.1', result.stdout)
            self.assertIn('--port\n9876', result.stdout)
            self.assertIn(str(root / 'installer' / 'server.py'), result.stdout)

    def test_container_scripts_keep_unix_line_endings_on_windows(self):
        attributes = ROOT / '.gitattributes'
        self.assertTrue(attributes.exists(), 'Git checkouts on Windows must preserve executable script line endings')
        self.assertRegex(attributes.read_text(), r'(?m)^\*(?:\.sh)? text(?:=auto)? eol=lf$')

    def test_powershell_launcher_checks_linux_engine_and_exit_codes(self):
        path = ROOT / 'install.ps1'
        self.assertTrue(path.is_file(), 'Windows launcher is required')
        self.assertTrue(path.read_bytes().startswith(b'\xef\xbb\xbf'), 'PowerShell 5.1 needs a UTF-8 BOM for Russian text')
        script = path.read_text(encoding='utf-8-sig')
        self.assertIn('LASTEXITCODE', script)
        self.assertIn('127.0.0.1', script)
        self.assertIn('Docker Desktop', script)
        self.assertIn('Expand-Archive', script)
        self.assertNotIn('Invoke-Expression', script)


if __name__ == '__main__':
    unittest.main()
