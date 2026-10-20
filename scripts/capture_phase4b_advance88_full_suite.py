"""Stream full regression on D with immutable input inventory and terminal native evidence."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re
import shutil
import subprocess
import time

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-advance88-full-suite-20261015')
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
def sha(path): return hashlib.sha256(path.read_bytes()).hexdigest()
def save(name, value):
    with (E / name).open('x', encoding='utf-8') as stream: json.dump(value, stream, indent=2)
proof_path = ROOT / 'storage/app/phase4b-advance88-cli-20261015/advance88-integration-preflight.json'
proof = json.loads(proof_path.read_text(encoding='utf-8'))
assert proof['status'] == 'ADVANCE88_LOCAL_INTEGRATION_PREFLIGHT_VERIFIED_FULL_SUITE_PENDING'
assert shutil.disk_usage(ROOT).free > 256 * 1024 * 1024
assert shutil.disk_usage(E.parent).free > 1024 * 1024 * 1024
for path, digest in {**proof['runtime_hashes'], **proof['evidence_input_hashes']}.items(): assert sha(ROOT / path) == digest
E.mkdir(exist_ok=False)
def inputs():
    files = []
    for directory, suffixes in [('app', {'.php'}), ('tests', {'.php'}), ('resources/fiscal', {'.json', '.xml', '.xsd'})]:
        files += [p for p in (ROOT / directory).rglob('*') if p.is_file() and p.suffix in suffixes]
    files += [ROOT / p for p in ['phpunit.xml', 'composer.lock', 'compose.yaml', *proof['evidence_input_hashes']]]
    return {p.relative_to(ROOT).as_posix(): sha(p) for p in files}
before = inputs()
command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'vendor/bin/pest',
    '--colors=never', '--do-not-cache-result', '--cache-directory=/tmp/phase4b-advance88-pest-cache']
started = datetime.now(timezone.utc).isoformat()
save('preflight.json', {'started_at': started, 'command': command, 'input_hashes': before,
    'directed_preflight_sha256': sha(proof_path), 'c_free_bytes': shutil.disk_usage(ROOT).free,
    'full_suite_complete': False, 'production_ready': False})
begin = time.monotonic()
with (E / 'full-suite.log').open('xb') as log:
    process = subprocess.Popen(command, cwd=ROOT, stdout=log, stderr=subprocess.STDOUT)
    save('process-start.json', {'host_docker_pid': process.pid, 'command': command, 'started_at': started})
    print('LIVE:full88 regression;host Docker client PID ' + str(process.pid), flush=True)
    code = process.wait()
after = inputs()
changed = [p for p in before if before[p] != after.get(p)]
added = sorted(set(after) - set(before)); removed = sorted(set(before) - set(after))
output = (E / 'full-suite.log').read_text(encoding='utf-8', errors='replace')
counts = re.search(r'Tests:\s+(\d+) passed\s+\((\d+) assertions\)', output)
record = {'command': command, 'exit_code': code, 'started_at': started,
    'finished_at': datetime.now(timezone.utc).isoformat(), 'seconds': time.monotonic() - begin,
    'log_sha256': sha(E / 'full-suite.log'), 'input_files_changed': changed, 'input_files_added': added,
    'input_files_removed': removed, 'tests': int(counts[1]) if counts else None,
    'assertions': int(counts[2]) if counts else None,
    'full_suite_complete': code == 0 and counts is not None and not changed and not added and not removed,
    'registration_proven': False, 'document_obligation_complete': False, 'phase4b_complete': False, 'production_ready': False}
save('full-suite.json', record)
print(output[-2200:], flush=True); print(json.dumps(record), flush=True)
assert record['full_suite_complete'] and record['tests'] == 2678
