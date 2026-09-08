#!/usr/bin/env python3
"""Portable daily snapshots; no Docker socket or host utilities required."""
import datetime
import gzip
import json
import os
import signal
import subprocess
import sys
import tarfile
import tempfile
import threading
import time
from pathlib import Path

DEST = Path('/backups')


def backup():
    os.umask(0o077)
    DEST.mkdir(parents=True, exist_ok=True)
    state = json.loads(Path('/config/state.json').read_text())
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S')
    env = dict(os.environ, MYSQL_PWD=state['db_password'])
    with tempfile.TemporaryDirectory(prefix='.wl-backup-', dir=DEST) as temporary:
        sql = Path(temporary) / 'database.sql'
        with sql.open('wb') as handle:
            result = subprocess.run(['mysqldump', '-h', 'mysql', '-u', state['db_user'],
                                     '--single-transaction', '--quick', '--no-tablespaces',
                                     '--set-gtid-purged=OFF', '--triggers', state['db_name']],
                                    env=env, stdout=handle, stderr=subprocess.PIPE, timeout=1800)
        if result.returncode or sql.stat().st_size == 0:
            raise RuntimeError('mysqldump failed; previous backups retained. Check MySQL readiness and credentials.')
        archive = Path(temporary) / 'snapshot.tar.gz'
        with tarfile.open(archive, 'w:gz') as output:
            output.add(sql, arcname='database.sql')
            output.add('/storage/app', arcname='storage/app')
            marker = Path('/storage/.initialized')
            if marker.exists():
                output.add(marker, arcname='storage/.initialized')
            output.add('/config', arcname='config')
            output.add('/installation', arcname='installation')
        with tarfile.open(archive) as check:
            if not {'database.sql', 'config', 'storage/app'}.issubset(check.getnames()):
                raise RuntimeError('Backup verification failed')
        final = DEST / ('wl-backup-' + stamp + '-' + os.urandom(3).hex() + '.tar.gz')
        os.replace(archive, final)
    (DEST / '.last-success').touch()
    cutoff = time.time() - int(os.environ.get('BACKUP_RETENTION_DAYS', '7')) * 86400
    for old in DEST.glob('wl-backup-*.tar.gz'):
        if old.is_file() and not old.is_symlink() and old.stat().st_mtime < cutoff:
            old.unlink()
    print('Backup verified: ' + final.name, flush=True)


if __name__ == '__main__':
    if '--health' in sys.argv:
        marker = DEST / '.last-success'
        sys.exit(0 if marker.exists() and time.time() - marker.stat().st_mtime < 26 * 3600 else 1)
    if '--once' in sys.argv:
        backup()
    else:
        stop = threading.Event()
        signal.signal(signal.SIGTERM, lambda *_: stop.set())
        signal.signal(signal.SIGINT, lambda *_: stop.set())
        while not stop.is_set():
            try:
                backup()
                delay = 86400
            except Exception as exc:
                print('Backup failed: ' + str(exc), file=sys.stderr, flush=True)
                delay = 300
            stop.wait(delay)
