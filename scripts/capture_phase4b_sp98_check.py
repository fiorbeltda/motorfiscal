"""Exclusive98 command evidence bound to exact current implementation files."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-sp98-integration-20261016')
label, *args = sys.argv[1:]
assert label.replace('-', '').isalnum()
assert all(not (E / (label + ext)).exists() for ext in ['.stdout', '.stderr', '.json'])
tracked = [ROOT / p for p in ['app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
    'app/Modules/Fiscal/ConsumptionReform/CurrentPartialExportSp98.php', 'app/Modules/Fiscal/ISS/SpSoftwareExportResult97.php',
    'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php',
    'tests/Fiscal/Unit/SpSoftwareExportResult97Test.php', 'tests/Fiscal/Unit/CurrentPartialExportSp98Test.php',
    'tests/Fiscal/Integration/ExportSpResults98Test.php', 'tests/Fixtures/sp97-cases.json', 'tests/Fixtures/sp98-scenario-expectations.json']]
tracked += list((ROOT / 'resources/fiscal/scenarios/export-sp-2026.10.16-98-draft1').glob('*.json'))
scope = {p.relative_to(ROOT).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest() for p in tracked}
command = [r'D:\Docker\DockerDesktop\resources\bin\docker.exe', 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', *args]
started = datetime.now(timezone.utc).isoformat()
proc = subprocess.run(command, cwd=ROOT, capture_output=True)
record = {'command': command, 'started_at': started, 'finished_at': datetime.now(timezone.utc).isoformat(),
    'exit_code': proc.returncode, 'scope_input_hashes_at_start': scope}
for channel in ['stdout', 'stderr']:
    raw = getattr(proc, channel)
    with (E / (label + '.' + channel)).open('xb') as stream: stream.write(raw)
    record[channel + '_sha256'] = hashlib.sha256(raw).hexdigest()
with (E / (label + '.json')).open('x', encoding='utf-8') as stream: json.dump(record, stream, indent=2)
print(json.dumps({k: v for k, v in record.items() if k != 'scope_input_hashes_at_start'}), flush=True)
if proc.returncode: print(proc.stdout.decode(errors='replace')[-4500:] + proc.stderr.decode(errors='replace')[-1500:], flush=True)
assert proc.returncode == 0
