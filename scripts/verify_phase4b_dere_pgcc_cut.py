"""Check candidate19, blocked PGCC cases, local XML bodies and frozen historical artifacts."""
import hashlib
import json
import subprocess
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = '2026.10.05-19'
BUNDLE = f'bundles/consumption-phase4b-{VERSION}.json'
E = ROOT / 'storage/app/phase4b-pgcc-20261005-19'


def execute(args, code=0):
    p = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php', 'artisan', *args],
                       cwd=ROOT, capture_output=True, encoding='utf-8')
    assert p.returncode == code, p.stdout + p.stderr
    value = json.loads(p.stdout)
    assert 'error' not in value, value
    return value


def save(path, value):
    if path.exists():
        assert json.loads(path.read_text(encoding='utf-8')) == value, f'Preserved evidence differs: {path}'
        return
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open('x', encoding='utf-8') as stream:
        stream.write(json.dumps(value, ensure_ascii=False, indent=2) + '\n')


snapshot = 'storage/app/phase4b-pgcc-20261005-19/snapshot.json'
args = ['fiscal:rtc:run', f'resources/fiscal/scenarios/consumption-phase4b-{VERSION}/financial-borrower-credit-loan-2026.json',
        f'--bundle-manifest={BUNDLE}']
if not (ROOT / snapshot).exists():
    args.append(f'--snapshot={snapshot}')
result = execute(args)
assert result['reason_codes'] == ['RTC_FINANCIAL_DOCUMENT_PENDING']
assert execute(['fiscal:rtc:replay', snapshot])['replay_matches']
cases = []
for source in sorted((ROOT / f'resources/fiscal/scenarios/dere-pgcc-{VERSION}').glob('*.json')):
    case = json.loads(source.read_text(encoding='utf-8'))
    positive = source.stem == 'qualified'
    args = ['fiscal:dere:project-monthly-closing', snapshot, source.relative_to(ROOT).as_posix(), f'--bundle-manifest={BUNDLE}']
    if positive and not (E / 'monthly-report.json').exists():
        args += ['--candidate-output=storage/app/phase4b-pgcc-20261005-19/monthly-candidate.xml',
                 '--report=storage/app/phase4b-pgcc-20261005-19/monthly-report.json']
    report = execute(args, 0 if positive else 1)
    assert report['production_ready'] is False
    if positive:
        stored = json.loads((E / 'monthly-report.json').read_text(encoding='utf-8'))
        for key in ['decision_hash', 'snapshot_hash', 'monthly_input_hash', 'projection', 'event_validations']:
            assert report[key] == stored[key], key
        assert set(stored['event_outputs']) == {'D1001', 'D1011', 'D1101', 'D1199'}
        assert report['projection']['pgcc_qualification']['required_auxiliary_events'] == []
        assert report['projection']['deduction_event_required'] is False
        assert report['projection']['period_closed'] is False
        for event, filename in stored['event_outputs'].items():
            data = (ROOT / filename).read_bytes()
            v = report['event_validations'][event]
            assert hashlib.sha256(data).hexdigest() == v['xml_sha256']
            assert v['reason'] == 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and v['unsigned_candidate_structure_checked']
            assert len(v['errors']) == 1, v
            xml = ET.fromstring(data)
            ns = {'d': xml.tag.split('}')[0][1:]}
            assert xml.find('.//d:nrInsc', ns).text == '11111111'
            assert xml.find('.//d:tpOper', ns).text == '1' and xml.find('.//d:tpAmb', ns).text == '2'
            if event == 'D1011':
                assert len(xml.findall('.//d:infoConta', ns)) == 6
                assert [x.text for x in xml.findall('.//d:codTrib', ns)] == ['311011101', '110112001', '411011201']
                assert len(xml.findall('.//d:Signature', ns)) == 0
    else:
        assert report['status'] == case['corpus_expectation']['status'], report
        assert report['reason'] == case['corpus_expectation']['reason'], (source.stem, report)
    cases.append({'case': source.stem, 'status': report['status'], 'reason': report['reason']})
assert len(cases) == 12
save(E / 'pgcc-corpus.json', {'valid': True, 'cases': cases, 'snapshot_replay_matches': True})

for family in ['factoring', 'securitization']:
    filename = f'financial-dere-{family}-2026.json'
    value = execute(['fiscal:rtc:run', f'resources/fiscal/scenarios/consumption-phase4b-{VERSION}/{filename}', f'--bundle-manifest={BUNDLE}'])
    assert value['status'] == 'ACTION_REQUIRED' and value['reason_codes'] == ['RTC_FINANCIAL_DOCUMENT_PENDING']
    tax = {row['stage']: row for row in value['trace']}['FINANCIAL_GROSS_DEBITS']
    assert tax['base'] == '800.00' and tax['ibs_nominal'] == '0.80' and tax['cbs_nominal'] == '7.20'
    save(ROOT / f'resources/fiscal/expected/consumption-phase4b-{VERSION}/{filename}',
         {k: value[k] for k in ['status', 'reason_codes', 'missing_facts', 'decision_hash']})
for script, args in [('verify_phase4b_financial_credit_cut.py', [VERSION]), ('verify_phase4b_dere_contributor_cut.py', [])]:
    p = subprocess.run(['python', 'scripts/' + script, *args], cwd=ROOT)
    assert p.returncode == 0, script
baseline = json.loads((E / 'historical-before.json').read_text(encoding='utf-8'))
changed = [name for name, expected in baseline.items() if not (ROOT / name).is_file()
           or hashlib.sha256((ROOT / name).read_bytes()).hexdigest() != expected]
assert changed == [], changed
save(E / 'historical-verification.json', {'checked': len(baseline), 'changed': changed})
print('Verified12 PGCC monthly cases, four unsigned event bodies, candidates19/18/17 and historical preservation.')
