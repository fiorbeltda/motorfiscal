"""Compare preserved monthly reports before/after explicit settlement selection source evolution."""
from pathlib import Path
import hashlib
import json
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base53-command-integration-20261009'
OLD = ROOT / 'storage/app/phase4b-negative-base50-closing-draft-20261009'
mode = sys.argv[1]
assert mode in ['before', 'after']


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


before = read(E / 'monthly-command-source-before.json')
assert sha(ROOT / before['preserved_source']) == before['before_sha256']
source_sha = sha(ROOT / before['source_path'])
assert (source_sha == before['before_sha256']) == (mode == 'before')
cases = [
    ('accepted50-options', OLD / 'command-financial-snapshot.json', OLD / 'command-month-input.json',
     'bundles/consumption-phase4b-2026.10.09-28-draft50.json', OLD / 'command-negative-base-request.json',
     OLD / 'command-monthly-report.json'),
    ('own52-baseline', ROOT / 'storage/app/phase4b-negative-base52-settlement-draft-20261009/command-financial-snapshot.json',
     E / 'command-month-input.json', 'bundles/consumption-phase4b-2026.10.09-28-draft52.json', None,
     E / 'before-baseline-report.json'),
    ('own52-options', ROOT / 'storage/app/phase4b-negative-base52-settlement-draft-20261009/command-financial-snapshot.json',
     E / 'command-month-input.json', 'bundles/consumption-phase4b-2026.10.09-28-draft52.json', E / 'command-options-request.json',
     E / 'before-options-report.json'),
]
records = []
for name, snapshot, month, bundle, request, report_path in cases:
    command = ['docker', 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'artisan',
               'fiscal:dere:project-monthly-closing', snapshot.relative_to(ROOT).as_posix(),
               month.relative_to(ROOT).as_posix(), '--bundle-manifest=' + bundle]
    if request is not None:
        command.append('--negative-base-request=' + request.relative_to(ROOT).as_posix())
    saved_sha = sha(report_path)
    result = subprocess.run(command, cwd=ROOT, capture_output=True, encoding='utf-8')
    assert result.returncode == 0, result.stdout + result.stderr
    actual = json.loads(result.stdout)
    saved = read(report_path)
    keys = ['status', 'reason', 'production_ready', 'scope', 'bundle_id', 'snapshot_hash', 'decision_hash',
            'monthly_input_hash', 'projection', 'schema_validation', 'event_validations']
    if request is not None:
        keys += ['negative_base_request_hash', 'negative_base_request_input']
    assert {key: actual[key] for key in keys} == {key: saved[key] for key in keys}, name
    assert 'negative_base_settlement' not in actual
    assert actual['production_ready'] is False and sha(report_path) == saved_sha
    for event, validation in actual['event_validations'].items():
        path = ROOT / saved['event_outputs'][event]
        assert sha(path) == validation['xml_sha256']
    records.append({'case': name, 'command': command, 'exit_code': result.returncode,
                    'report_path': report_path.relative_to(ROOT).as_posix(), 'report_sha256': saved_sha,
                    'compared_keys': keys, 'projection_and_event_hashes_match': True})
report = {'status': 'PRESERVED_MONTHLY_EXECUTIONS_COMPATIBLE', 'mode': mode,
          'current_source_sha256': source_sha, 'preserved_source_sha256': before['before_sha256'],
          'cases': records, 'settlement_selection_tested': False, 'production_ready': False}
path = E / ('compatibility-' + mode + '-source-change.json')
with path.open('x', encoding='utf-8') as stream:
    json.dump(report, stream, ensure_ascii=False, indent=2)
print('PASS: three preserved monthly executions, report/decision/projection bindings and fifteen XML hashes; ' + mode)
