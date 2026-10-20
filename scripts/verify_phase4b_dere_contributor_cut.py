"""Verify candidate18 monthly corpus, frozen schemas and deterministic replay without remote writes."""
import hashlib
import json
import subprocess
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = '2026.10.05-18'
BUNDLE = f'bundles/consumption-phase4b-{VERSION}.json'
EVIDENCE = ROOT / 'storage/app/phase4b-contributor-20261005-18'


def execute(args, expected_code=0):
    process = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php', 'artisan', *args],
        cwd=ROOT, capture_output=True, encoding='utf-8', check=False)
    if process.returncode != expected_code:
        raise RuntimeError(process.stdout + process.stderr)
    value = json.loads(process.stdout)
    if 'error' in value:
        raise RuntimeError(value)
    return value


def save(path, value):
    if path.exists():
        assert json.loads(path.read_text(encoding='utf-8')) == value, f'Preserved evidence differs: {path}'
        return
    with path.open('x', encoding='utf-8') as stream:
        stream.write(json.dumps(value, ensure_ascii=False, indent=2) + '\n')


snapshot = 'storage/app/phase4b-contributor-20261005-18/snapshot.json'
args = ['fiscal:rtc:run', f'resources/fiscal/scenarios/consumption-phase4b-{VERSION}/financial-borrower-credit-loan-2026.json',
        f'--bundle-manifest={BUNDLE}']
if not (ROOT / snapshot).exists():
    args.append(f'--snapshot={snapshot}')
result = execute(args)
assert result['status'] == 'ACTION_REQUIRED' and result['reason_codes'] == ['RTC_FINANCIAL_DOCUMENT_PENDING']
assert execute(['fiscal:rtc:replay', snapshot])['replay_matches']
cases = []
for source in sorted((ROOT / f'resources/fiscal/scenarios/dere-contributor-{VERSION}').glob('*.json')):
    case = json.loads(source.read_text(encoding='utf-8'))
    positive = source.stem == 'qualified'
    candidate = 'storage/app/phase4b-contributor-20261005-18/monthly-candidate.xml'
    stored_path = EVIDENCE / 'monthly-report.json'
    args = ['fiscal:dere:project-monthly-closing', snapshot, source.relative_to(ROOT).as_posix(), f'--bundle-manifest={BUNDLE}']
    if positive and not stored_path.exists():
        args += [f'--candidate-output={candidate}', '--report=storage/app/phase4b-contributor-20261005-18/monthly-report.json']
    report = execute(args, 0 if positive else 1)
    assert report['production_ready'] is False
    if positive:
        stored = json.loads(stored_path.read_text(encoding='utf-8'))
        for key in ['decision_hash', 'snapshot_hash', 'monthly_input_hash', 'projection', 'event_validations']:
            assert report[key] == stored[key], key
        assert set(stored['event_outputs']) == {'D1001', 'D1101', 'D1121', 'D1199'}
        for event, filename in stored['event_outputs'].items():
            data = (ROOT / filename).read_bytes()
            v = report['event_validations'][event]
            assert hashlib.sha256(data).hexdigest() == v['xml_sha256']
            assert v['reason'] == 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and v['unsigned_candidate_structure_checked']
            assert len(v['errors']) == 1
            xml = ET.fromstring(data)
            namespace = {'d': xml.tag.split('}')[0][1:]}
            assert xml.find('.//d:nrInsc', namespace).text == '11111111'
            assert xml.find('.//d:tpOper', namespace).text == '1' and xml.find('.//d:tpAmb', namespace).text == '2'
            if event == 'D1001':
                assert xml.find('.//d:tpAtividade', namespace).text == '01A'
                assert report['projection']['contributor_projection']['registration_performed'] is False
            else:
                previous = json.loads((ROOT / 'storage/app/phase4b-dere-monthly-closing-20261002-17-report.json').read_text(encoding='utf-8'))
                assert v['xml_sha256'] == previous['event_validations'][event]['xml_sha256']
        assert report['projection']['period_closed'] is False
    else:
        assert report['status'] == case['corpus_expectation']['status']
        assert report['reason'] == case['corpus_expectation']['reason']
    cases.append({'case': source.stem, 'status': report['status'], 'reason': report['reason']})
assert len(cases) == 8
save(EVIDENCE / 'contributor-corpus.json', {'valid': True, 'cases': cases, 'snapshot_replay_matches': True})

for family in ['factoring', 'securitization']:
    filename = f'financial-dere-{family}-2026.json'
    value = execute(['fiscal:rtc:run', f'resources/fiscal/scenarios/consumption-phase4b-{VERSION}/{filename}', f'--bundle-manifest={BUNDLE}'])
    assert value['status'] == 'ACTION_REQUIRED' and value['reason_codes'] == ['RTC_FINANCIAL_DOCUMENT_PENDING']
    tax = {row['stage']: row for row in value['trace']}['FINANCIAL_GROSS_DEBITS']
    assert tax['base'] == '800.00' and tax['ibs_nominal'] == '0.80' and tax['cbs_nominal'] == '7.20'
    path = ROOT / f'resources/fiscal/expected/consumption-phase4b-{VERSION}/{filename}'
    path.parent.mkdir(parents=True, exist_ok=True)
    save(path, {key: value[key] for key in ['status', 'reason_codes', 'missing_facts', 'decision_hash']})

process = subprocess.run(['python', 'scripts/verify_phase4b_financial_credit_cut.py', VERSION], cwd=ROOT, check=False)
assert process.returncode == 0
process = subprocess.run(['python', 'scripts/verify_phase4b_dere_closing_cut.py'], cwd=ROOT, check=False)
assert process.returncode == 0

baseline = json.loads((EVIDENCE / 'historical-before.json').read_text(encoding='utf-8'))
changed = [name for name, expected in baseline.items() if not (ROOT / name).is_file()
           or hashlib.sha256((ROOT / name).read_bytes()).hexdigest() != expected]
assert changed == [], changed
save(EVIDENCE / 'historical-verification.json', {'checked': len(baseline), 'changed': changed})
print('Verified8 D1001 monthly cases; four event bodies, candidate18/17 replays and historical hashes preserved.')
