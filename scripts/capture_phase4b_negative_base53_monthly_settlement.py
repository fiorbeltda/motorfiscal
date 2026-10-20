"""Capture and replay native53 settlement selection, preserving prior outputs."""
from pathlib import Path
import subprocess
import hashlib
import json
from dere_daily_memory_oracle import digest

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base53-command-integration-20261009'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

base = ['docker', 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'artisan',
    'fiscal:dere:project-monthly-closing',
    'storage/app/phase4b-negative-base52-settlement-draft-20261009/command-financial-snapshot.json',
    E.relative_to(ROOT).as_posix() + '/command-month-input.json',
    '--bundle-manifest=bundles/consumption-phase4b-2026.10.09-28-draft52.json',
    '--negative-base-settlement-request=' + E.relative_to(ROOT).as_posix() + '/command-settlement-request.json']
request = read(E / 'command-settlement-request.json')
prepared = read(E / 'prepared-settlement-proposal.json')
records = []
for mode in ['derived', 'both']:
    command = base.copy()
    if mode == 'both':
        command.append('--negative-base-request=' + E.relative_to(ROOT).as_posix() + '/command-options-request.json')
    report_path = E / ('settlement-' + mode + '-report.json')
    candidate = E / ('settlement-' + mode + '.xml')
    assert not report_path.exists() and not candidate.exists()
    capture = subprocess.run(command + ['--report=' + report_path.relative_to(ROOT).as_posix(),
        '--candidate-output=' + candidate.relative_to(ROOT).as_posix()], cwd=ROOT, capture_output=True, encoding='utf-8')
    assert capture.returncode == 0, capture.stdout + capture.stderr
    actual = read(report_path)
    assert actual == json.loads(capture.stdout)
    assert actual['negative_base_settlement'] == prepared
    assert actual['negative_base_settlement_request_hash'] == digest(request)
    assert actual['production_ready'] is False
    before = read(E / 'before-options-report.json')
    before['projection']['negative_base_options_projection']['request_hash'] = digest(
        read(E / 'command-options-request.json') if mode == 'both' else request)
    assert actual['projection'] == before['projection']
    assert actual['event_validations'] == before['event_validations']
    hashes = {}
    for name, output in actual['event_outputs'].items():
        path = ROOT / output
        hashes[name] = sha(path)
        assert hashes[name] == actual['event_validations'][name]['xml_sha256']
        assert hashes[name] == sha(ROOT / before['event_outputs'][name])
    replay = subprocess.run(command, cwd=ROOT, capture_output=True, encoding='utf-8')
    assert replay.returncode == 0, replay.stdout + replay.stderr
    rerun = json.loads(replay.stdout)
    excluded = ['candidate_output', 'event_outputs']
    assert {k: v for k, v in actual.items() if k not in excluded} == {k: v for k, v in rerun.items() if k not in excluded}
    records.append({'mode': mode, 'report_sha256': sha(report_path), 'event_xml_sha256': hashes,
        'exact_native_replay': True, 'prepared_proposal_matches': True, 'declaration_matches_before_options': True})
with (E / 'native-settlement-validation.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'NATIVE_MONTHLY_SETTLEMENT_SELECTION_CAPTURED_AND_REPLAYED', 'cases': records,
        'government_return_authenticated': False, 'eligible_for_posting': False, 'production_ready': False}, stream, indent=2)
print('PASS: two native settlement selections, exact replays, ten preserved XML hashes')
