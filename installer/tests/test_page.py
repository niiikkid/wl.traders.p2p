"""Small dependency-free installer UI contract tests (Python + Node.js).

Execute the page's real pure JavaScript helpers; browser rendering is checked
separately. Run: python3 -m unittest discover -s installer/tests -p test_page.py
"""
import json
import re
import shutil
import subprocess
import unittest
from pathlib import Path

PAGE = Path(__file__).resolve().parents[1] / "page.html"


class InstallerPageTest(unittest.TestCase):
    def js(self, assertions):
        html = PAGE.read_text()
        scripts = re.findall(r'<script id="installer-logic">(.*?)</script>', html, re.S)
        self.assertEqual(len(scripts), 1, "The page must expose its actual UI logic for lightweight tests")
        self.assertIsNotNone(shutil.which("node"), "Node.js is required to exercise the UI logic")
        result = subprocess.run(
            ["node", "-e", "const assert = require('node:assert/strict');\n" + scripts[0] + "\n" + assertions],
            capture_output=True, text=True,
        )
        self.assertEqual(result.returncode, 0, result.stderr)

    def test_three_steps_and_address_payload_match_review(self):
        html = PAGE.read_text()
        self.assertEqual(re.findall(r'<section[^>]*data-step="(\d+)"', html), ["0", "1", "2"])
        self.js("""
            assert.deepEqual(addressSettings({site_mode:'local', http_port:'8080', ip_address:'8.8.8.8'}),
                {site_mode:'local', http_port:8080, app_url:'http://localhost:8080', domain:'', https_mode:'none'});
            assert.equal(addressSettings({site_mode:'ip', ip_address:'192.168.1.15', http_port:'8181'}).app_url,
                'http://192.168.1.15:8181');
            assert.equal(addressSettings({site_mode:'ip', ip_address:'203.0.113.2', http_port:'80'}).app_url,
                'http://203.0.113.2');
            assert.equal(addressSettings({site_mode:'domain', domain:'pay.example.com', https_mode:'cloudflare'}).app_url,
                'https://pay.example.com');
            assert.equal(addressSettings({site_mode:'domain', domain:'pay.example.com', https_mode:'none'}).http_port, 80);
            for (const ip of ['bad', 'http://1.2.3.4/path', '999.1.1.1', '1.2.3.04']) {
                assert.throws(() => addressSettings({site_mode:'ip', ip_address:ip, http_port:80}));
            }
            assert.throws(() => addressSettings({site_mode:'local', http_port:0}));
            assert.throws(() => addressSettings({site_mode:'domain', domain:'https://example.com'}));
        """)

    def test_status_refresh_and_network_loss_never_imply_completion(self):
        self.js("""
            assert.equal(installationView({phase:'ready'}).showForm, true);
            assert.equal(installationView({phase:'starting', progress:0}).finished, false);
            assert.equal(installationView({phase:'installing', progress:100}).finished, false);
            assert.equal(installationView({phase:'failed', error:'disk full'}).canRetry, true);
            assert.equal(installationView({phase:'done', app_url:'http://127.0.0.1:8080'}).finished, true);
            assert.throws(() => installationView({phase:'unexpected'}));
            assert.throws(() => installationView({phase:'done', app_url:'javascript:alert(1)'}));
            (async () => {
                let response = {phase:'installing', progress:42};
                let nextPoll, lastState, failure, scheduled = 0;
                const poller = createStatusPoller({
                    request: async () => { if (response instanceof Error) throw response; return response; },
                    onState: state => {lastState = state;},
                    onConnectionError: error => {failure = error.message;},
                    schedule: fn => {nextPoll = fn; scheduled++; return scheduled;},
                    cancel: () => {},
                });
                await poller.refresh();
                assert.equal(lastState.progress, 42);
                assert.equal(scheduled, 1);
                response = new Error('connection lost');
                await nextPoll();
                assert.equal(lastState.phase, 'installing');
                assert.equal(failure, 'connection lost');
                assert.equal(scheduled, 2, 'Network loss must schedule another real status request');
                response = {phase:'done', app_url:'http://127.0.0.1:8080'};
                await nextPoll();
                assert.equal(lastState.phase, 'done');
                assert.equal(scheduled, 2, 'Stop only on a verified terminal state');
                response = {phase:'failed'};
                await poller.refresh();
                assert.equal(lastState.phase, 'failed');
                assert.equal(scheduled, 2);
            })().catch(error => {console.error(error); process.exitCode = 1;});
        """)

    def test_explicit_final_action_safe_defaults_and_token_protection(self):
        html = PAGE.read_text()
        self.assertNotIn("localStorage", html)
        self.assertNotIn("sessionStorage", html)
        self.assertNotRegex(html, r"\b(?:alert|confirm)\(")
        for obsolete in ["create_swap", "enable_firewall", "generate_test_data", "Flexible"]:
            self.assertNotIn(obsolete, html)
        self.assertRegex(html, r'<details[^>]+id="optional-settings"[^>]*>')
        self.assertNotRegex(html, r'<details[^>]+id="optional-settings"[^>]*\bopen')
        self.js("""
            assert.equal(enterAction({key:'Enter', tagName:'INPUT', step:0}), 'next');
            assert.equal(enterAction({key:'Enter', tagName:'INPUT', step:1}), 'next');
            assert.equal(enterAction({key:'Enter', tagName:'INPUT', step:2}), 'block');
            assert.equal(enterAction({key:'Enter', tagName:'TEXTAREA', step:0}), 'native');
            assert.equal(enterAction({key:'Enter', tagName:'BUTTON', step:2}), 'native');
            assert.equal(enterAction({key:'Enter', tagName:'INPUT', step:0, isComposing:true}), 'native');
            for (const path of ['/status','/install','/domain-check']) {
                assert.equal(apiPath(path, 'a&b ?'), path + '?token=a%26b%20%3F');
                assert.throws(() => apiPath(path, ''), /ссылк/);
            }
            assert.ok(createPassword().length >= 24);
            assert.notEqual(createPassword(), createPassword());
            const local = installationPayload({site_mode:'local',http_port:'8080',https_mode:'cloudflare',
                cloudflare_key:'secret',cloudflare_cert:'cert',install_backups:true,backup_retention_days:'7'});
            assert.equal(local.https_mode, 'none');
            assert.equal(local.install_backups, true);
            assert.equal(local.backup_retention_days, 7);
            assert.equal(local.cloudflare_key, undefined);
            assert.equal(local.app_url, 'http://localhost:8080');
        """)


if __name__ == "__main__":
    unittest.main()
