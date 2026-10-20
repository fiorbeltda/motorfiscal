"""Exclusive96 command evidence; preserve errors and bind the exact current source files."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-export-iss96-integration-20261016')
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
label, *args = sys.argv[1:]
assert label.replace('-', '').isalnum()
for suffix in ['.stdout', '.stderr', '.json']: assert not (E / (label + suffix)).exists()
command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', *args]
started = datetime.now(timezone.utc).isoformat()
tracked = [ROOT / p for p in ['app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
    'app/Modules/Fiscal/ConsumptionReform/CurrentPartialExportIss96.php', 'app/Modules/Fiscal/ISS/IssExportResultQualifier95.php',
    'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php',
    'tests/Fiscal/Unit/IssExportResultQualifier95Test.php', 'tests/Fiscal/Unit/CurrentPartialExportIss96Test.php',
    'tests/Fiscal/Integration/ExportIssResults96Test.php', 'tests/Fixtures/iss-export95-cases.json']]
tracked += list((ROOT / 'resources/fiscal/scenarios/export-iss-2026.10.16-96-draft1').glob('*.json'))
scope_hashes = {p.relative_to(ROOT).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest() for p in tracked}
proc = subprocess.run(command, cwd=ROOT, capture_output=True)
record = {'command': command, 'started_at': started, 'finished_at': datetime.now(timezone.utc).isoformat(), 'exit_code': proc.returncode,
          'scope_input_hashes_at_start': scope_hashes}
for channel in ['stdout', 'stderr']:
    raw = getattr(proc, channel)
    with (E / (label + '.' + channel)).open('xb') as stream: stream.write(raw)
    record[channel + '_sha256'] = hashlib.sha256(raw).hexdigest()
with (E / (label + '.json')).open('x', encoding='utf-8') as stream: json.dump(record, stream, indent=2)
print(json.dumps({k: v for k, v in record.items() if k != 'scope_input_hashes_at_start'}), flush=True)
if proc.returncode: print(proc.stdout.decode(errors='replace')[-4500:] + proc.stderr.decode(errors='replace')[-1500:], flush=True)
assert proc.returncode == 0, 'Failure preserved under exclusive label'
