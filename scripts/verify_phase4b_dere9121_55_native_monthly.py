"""Verify six native XML bodies and exact replay of public settlement command."""
from pathlib import Path
import hashlib
import json
import subprocess
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-55-command-integration-20261013'
P = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

report_path = E / 'monthly-report.json'
report = read(report_path)
assert report['negative_base_settlement'] == read(P / 'public-settlement-native-result.json')['result']
assert not report['production_ready']
assert set(report['event_validations']) == {'D1001', 'D1011', 'D1101', 'D1121', 'D1199', 'D2101'}
xmls = {}
for name, path in report['event_outputs'].items():
    xmls[name] = sha(ROOT / path)
    assert xmls[name] == report['event_validations'][name]['xml_sha256']
    assert report['event_validations'][name]['reason'] == 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE'
command = ['docker', 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'artisan',
    'fiscal:dere:project-monthly-closing', (P / 'financial-snapshot.json').relative_to(ROOT).as_posix(),
    (E / 'command-month-input.json').relative_to(ROOT).as_posix(),
    '--bundle-manifest=bundles/consumption-phase4b-2026.10.13-28-draft54.json',
    '--negative-base-settlement-request='+(E / 'command-settlement-request.json').relative_to(ROOT).as_posix()]
before = sha(report_path)
result = subprocess.run(command, cwd=ROOT, capture_output=True, encoding='utf-8')
assert result.returncode == 0, result.stderr
replayed = json.loads(result.stdout)
keys = set(report) - {'event_outputs', 'candidate_output'}
assert {k: report[k] for k in keys} == {k: replayed[k] for k in keys}
assert sha(report_path) == before
with (E / 'native-monthly-replay-validation.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'PUBLIC_D9121_MONTHLY_SELECTION_CAPTURED_AND_REPLAYED', 'report_sha256': before,
        'xml_sha256': xmls, 'exact_replay': True, 'native_proposal_matches': True, 'production_ready': False}, stream, indent=2)
print('PASS: public D9121 selection, six native XML hashes and exact report replay; production blocked')
