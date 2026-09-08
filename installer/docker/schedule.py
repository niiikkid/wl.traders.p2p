#!/usr/bin/env python3
"""Single scheduler process, graceful stop, heartbeat and Horizon metrics."""
import os
import signal
import subprocess
import sys
import threading
import time
from pathlib import Path

heartbeat = Path('/tmp/scheduler-heartbeat')
if '--health' in sys.argv:
    sys.exit(0 if heartbeat.exists() and time.time() - heartbeat.stat().st_mtime < 150 else 1)
stop = threading.Event()
signal.signal(signal.SIGTERM, lambda *_: stop.set())
signal.signal(signal.SIGINT, lambda *_: stop.set())
last_snapshot = 0
while not stop.is_set():
    start = time.monotonic()
    subprocess.run(['php', 'artisan', 'schedule:run', '--no-interaction'], check=True, timeout=120)
    if time.time() - last_snapshot >= 300:
        subprocess.run(['php', 'artisan', 'horizon:snapshot'], check=True, timeout=60)
        last_snapshot = time.time()
    heartbeat.touch()
    stop.wait(max(0, 60 - (time.monotonic() - start)))
