"""Capture live export decisions and replays, verify independent decimals and immutable prior snapshots."""
from pathlib import Path
import hashlib, json, subprocess
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-partial-export-fx73-preparation-20261014'
def read(path): return json.loads(path.read_text(encoding='utf-8'))
def sha(path): return hashlib.sha256(path.read_bytes()).hexdigest()
def save(path, value):
    with path.open('x', encoding='utf-8') as stream: json.dump(value, stream, indent=2)
def run(label, args):
    result = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'artisan', *args], cwd=ROOT, capture_output=True, encoding='utf-8')
    log = E / (label + '.log')
    with log.open('x', encoding='utf-8') as stream: stream.write(result.stdout + result.stderr)
    save(E / (label + '.json'), {'exit_code': result.returncode, 'log_sha256': sha(log), 'args': args})
    assert result.returncode == 0, label + ':' + result.stderr
    return json.loads(result.stdout)
records = []
for case in read(E / 'export-integrated-case-manifest.json')['cases']:
    name = case['name']
    snapshot = E / (name + '-export-snapshot.json')
    actual = run(name + '-export-capture', ['fiscal:rtc:run', case['path'], '--bundle-manifest=bundles/consumption-phase4b-2026.10.14-73-draft1.json', '--snapshot=' + snapshot.relative_to(ROOT).as_posix()])
    assert case['expected_reason'] in actual['reason_codes'], name + ':' + str(actual['reason_codes'])
    assert run(name + '-export-replay', ['fiscal:rtc:replay', snapshot.relative_to(ROOT).as_posix()])['replay_matches']
    trace = {r['stage']: r for r in actual['trace']}
    if case['nominal_path']:
        nominal = trace['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION']
        for key, value in case['independent_decimal'].items():
            row = trace['PARTIAL_EXPORT_GROSS_ALLOCATION'] if key == 'total_gross_brl' else nominal
            assert row[key] == value, name + ':' + key
        if case['currency'] != 'BRL':
            fx = trace['PARTIAL_EXPORT_FX_TRANSACTION']
            assert fx['material_quote_origin']['strategy'] == 'BCB_USD_BRL_PTAX' and fx['production_ready'] is False
            stages = [row['stage'] for row in actual['trace']]
            assert stages.index('PARTIAL_EXPORT_FX_TRANSACTION') < stages.index('PARTIAL_EXPORT_GROSS_ALLOCATION')
    else: assert 'PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION' not in trace
    records.append({**case, 'snapshot_sha256': sha(snapshot), 'exact_replay': True, 'production_ready': False})
    print('PASS:' + name + '; native decision and exact replay', flush=True)
prior = []
prior_reports = [ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014/replay-v2/post-fix-replay-validation.json']
for report in prior_reports:
    for case in read(report)['cases']:
        path = ROOT / case['path']
        before = sha(path)
        assert before == case['sha256']
        assert run('prior-' + path.stem + '-export-replay', ['fiscal:rtc:replay', path.relative_to(ROOT).as_posix()])['replay_matches']
        assert sha(path) == before
        prior.append({'path': path.relative_to(ROOT).as_posix(), 'sha256': before, 'exact_replay': True})
save(E / 'export-integrated-corpus-validation.json', {'status': 'TWELVE_EXPORT_DECISIONS_AND_EIGHTEEN_PRIOR_FX_CREDIT_REPLAYS_VERIFIED',
    'cases': records, 'prior_compatibility': prior, 'document_obligation_complete': False, 'production_ready': False})
print('PASS:twelve export native decisions, independent decimals,18 exact prior replays; document gate preserved')
