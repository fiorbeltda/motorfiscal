"""Freeze project inputs, including shared script helpers, throughout fullPest135."""
import hashlib
import json
import os
import re
import subprocess
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = Path('D:/ProjetoSaasExports/phase4b-pair135-20261023-v1/full-suite-v1')
OUT.mkdir(exist_ok=False)


def sha(path):
    with path.open('rb') as handle:
        return hashlib.file_digest(handle, 'sha256').hexdigest()


def inventory():
    paths = set()
    for folder in ['app', 'tests', 'resources/fiscal', 'scripts', 'config', 'routes']:
        paths.update(p for p in (ROOT / folder).rglob('*') if p.is_file() and '__pycache__' not in p.parts and p.suffix != '.pyc')
    for name in ['composer.json', 'composer.lock', 'phpunit.xml', 'phpstan.neon', 'pint.json', '.gitattributes', 'bootstrap/app.php', 'bootstrap/providers.php']:
        path = ROOT / name
        if path.is_file():
            paths.add(path)
    return {str(p.relative_to(ROOT)).replace('\\', '/'): sha(p) for p in sorted(paths)}


def save(name, value):
    with (OUT / name).open('xb') as handle:
        handle.write((json.dumps(value, indent=2) + '\n').encode())
        handle.flush()
        os.fsync(handle.fileno())


before = inventory()
save('input-hashes-before.json', before)
command = ['D:/Docker/DockerDesktop/resources/bin/docker.exe', 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M',
           'vendor/bin/pest', '--compact', '--colors=never', '--do-not-cache-result', '--cache-directory=/tmp/phase4b-pair135-pest-cache']
started = datetime.now(timezone.utc).isoformat()
with (OUT / 'stdout.log').open('xb') as log:
    process = subprocess.Popen(command, cwd=ROOT, stdout=log, stderr=subprocess.STDOUT)
    save('process.json', {'status': 'RUNNING', 'started_at': started, 'host_docker_pid': process.pid,
                          'command': command, 'frozen_inputs': len(before), 'script_helpers_included': True})
    print(json.dumps({'status': 'RUNNING', 'host_docker_pid': process.pid, 'frozen_inputs': len(before)}), flush=True)
    code = process.wait()
    log.flush()
    os.fsync(log.fileno())
after = inventory()
save('input-hashes-after.json', after)
plain = (OUT / 'stdout.log').read_text(encoding='utf-8', errors='replace')
match = re.search(r'Tests:\s+(\d+) passed\s+\((\d+) assertions\)', plain)
changed = [name for name, digest in before.items() if after.get(name) != digest]
added = sorted(set(after) - set(before))
report = {'status': 'TERMINAL', 'exit_code': code, 'started_at': started, 'finished_at': datetime.now(timezone.utc).isoformat(),
          'tests': int(match[1]) if match else None, 'assertions': int(match[2]) if match else None, 'input_files': len(before),
          'changed_inputs': changed, 'added_inputs': added, 'stdout_sha256': sha(OUT / 'stdout.log'),
          'script_helpers_included': True, 'complete': code == 0 and match is not None and not changed and not added, 'phase4b_complete': False}
save('validation.json', report)
print(json.dumps(report), flush=True)
print(plain[-1400:], flush=True)
raise SystemExit(0 if report['complete'] else 1)
