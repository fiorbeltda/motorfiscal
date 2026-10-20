"""Exclusive104 command logs with before/after hashes; D storage only."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-identity104-integration-20261019')
label, *args = sys.argv[1:]
assert label.replace('-', '').isalnum() and args
assert all(not (E / (label + ext)).exists() for ext in ['.stdout', '.stderr', '.json'])
tracked = [ROOT / p for p in ['app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
    'app/Modules/Fiscal/ConsumptionReform/PartialExportDpsIdentity103.php',
    'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php',
    'tests/Fiscal/Unit/PartialExportDpsIdentity103Test.php', 'tests/Fiscal/Unit/PartialExportDpsEngine104Test.php',
    'tests/Fiscal/Integration/PartialExportDpsIdentity104Test.php', 'tests/Fixtures/identity103-cases.json',
    'tests/Fixtures/identity104-scenario-expectations.json', 'tests/Fixtures/identity104-scenario-expectations-v2.json']]
tracked += list((ROOT / 'resources/fiscal').glob('*/*2026.10.19-104-draft1.json'))
tracked += list((ROOT / 'resources/fiscal/scenarios/dps-identity-2026.10.19-104-draft1').glob('*.json'))
tracked += list((ROOT / 'resources/fiscal/scenarios/dps-identity-upstream-2026.10.19-104-draft1').glob('*.json'))

def hashes():
    return {p.relative_to(ROOT).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest() for p in tracked}

scope = hashes()
command = [r'D:\Docker\DockerDesktop\resources\bin\docker.exe', 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', *args]
started = datetime.now(timezone.utc).isoformat()
proc = subprocess.run(command, cwd=ROOT, capture_output=True)
record = {'command': command, 'started_at': started, 'finished_at': datetime.now(timezone.utc).isoformat(),
    'exit_code': proc.returncode, 'scope_input_hashes_at_start': scope, 'scope_input_hashes_at_end': hashes()}
for channel in ['stdout', 'stderr']:
    raw = getattr(proc, channel)
    with (E / (label + '.' + channel)).open('xb') as stream:
        stream.write(raw)
    record[channel + '_sha256'] = hashlib.sha256(raw).hexdigest()
with (E / (label + '.json')).open('x', encoding='utf-8') as stream:
    json.dump(record, stream, indent=2)
print(json.dumps({k: v for k, v in record.items() if not k.startswith('scope_')}), flush=True)
print(proc.stdout.decode(errors='replace')[-5000:] + proc.stderr.decode(errors='replace')[-1800:], flush=True)
assert scope == record['scope_input_hashes_at_end'], 'Inputs changed during check'
assert proc.returncode == 0
