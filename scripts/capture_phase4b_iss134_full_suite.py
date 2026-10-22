"""Freeze runtime/test/fiscal inputs around a complete suite; do not promote whole-phase acceptance."""
import hashlib
import json
import os
import re
import subprocess
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = Path('D:/ProjetoSaasExports/phase4b-iss134-20261022-v1/full-suite-v1')
OUT.mkdir(exist_ok=False)


def digest(path):
    with path.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()


def inventory():
    files = set()
    for folder in ['app', 'tests', 'resources/fiscal']:
        files.update(p for p in (ROOT / folder).rglob('*') if p.is_file())
    files.update(ROOT / name for name in ['composer.json', 'composer.lock', 'phpunit.xml', 'bootstrap/app.php'])
    return {str(p.relative_to(ROOT)).replace('\\', '/'): digest(p) for p in sorted(files)}


def save(name, value):
    raw = json.dumps(value, indent=2).encode()
    with (OUT / name).open('xb') as stream:
        stream.write(raw)
        stream.flush()
        os.fsync(stream.fileno())


before = inventory()
save('input-hashes-before.json', before)
command = ['D:/Docker/DockerDesktop/resources/bin/docker.exe', 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M',
           'vendor/bin/pest', '--compact', '--colors=never', '--do-not-cache-result', '--cache-directory=/tmp/phase4b-iss134-pest-cache']
started = datetime.now(timezone.utc).isoformat()
with (OUT / 'stdout.log').open('xb') as log:
    process = subprocess.Popen(command, cwd=ROOT, stdout=log, stderr=subprocess.STDOUT)
    save('process.json', {'started_at': started, 'host_docker_pid': process.pid, 'command': command, 'status': 'RUNNING'})
    print(json.dumps({'status': 'RUNNING', 'host_docker_pid': process.pid, 'frozen_inputs': len(before)}), flush=True)
    code = process.wait()
    log.flush()
    os.fsync(log.fileno())
after = inventory()
save('input-hashes-after.json', after)
plain = (OUT / 'stdout.log').read_text(encoding='utf-8', errors='replace')
match = re.search(r'Tests:\s+(\d+) passed\s+\((\d+) assertions\)', plain)
changed = [p for p, h in before.items() if after.get(p) != h]
added = sorted(set(after) - set(before))
report = {'status': 'TERMINAL', 'exit_code': code, 'started_at': started, 'finished_at': datetime.now(timezone.utc).isoformat(),
          'tests': int(match[1]) if match else None, 'assertions': int(match[2]) if match else None,
          'input_files': len(before), 'changed_inputs': changed, 'added_inputs': added, 'stdout_sha256': digest(OUT / 'stdout.log'),
          'complete': code == 0 and match is not None and not changed and not added, 'phase4b_complete': False}
save('validation.json', report)
print(json.dumps(report), flush=True)
print(plain[-1400:], flush=True)
raise SystemExit(0 if report['complete'] else 1)
