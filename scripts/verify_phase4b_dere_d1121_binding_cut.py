"""Verify the scoped D1121/PGCC corpus, document ceilings, no-appropriation and old replays."""
import hashlib
import json
import subprocess
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = '2026.10.05-20'
BUNDLE = f'bundles/consumption-phase4b-{VERSION}.json'
E = ROOT / 'storage/app/phase4b-d1121-binding-20261005-20'
SCENARIOS = ROOT / f'resources/fiscal/scenarios/dere-d1121-binding-{VERSION}'


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


snapshots = {}
for source in sorted(SCENARIOS.glob('financial-scenario*.json')):
    filename = f'storage/app/phase4b-d1121-binding-20261005-20/{source.stem}-snapshot.json'
    args = ['fiscal:rtc:run', source.relative_to(ROOT).as_posix(), f'--bundle-manifest={BUNDLE}']
    if not (ROOT / filename).exists():
        args.append(f'--snapshot={filename}')
    result = execute(args)
    assert result['status'] == 'ACTION_REQUIRED' and result['reason_codes'] == ['RTC_FINANCIAL_DOCUMENT_PENDING']
    tax = {row['stage']: row for row in result['trace']}['FINANCIAL_GROSS_DEBITS']
    amount = '800.00' if source.stem.endswith(('zero', 'funding-only')) else '700.00'
    assert tax['base'] == amount
    assert tax['ibs_nominal'] == ('0.80' if amount == '800.00' else '0.70')
    assert tax['cbs_nominal'] == ('7.20' if amount == '800.00' else '6.30')
    assert execute(['fiscal:rtc:replay', filename])['replay_matches']
    snapshots[source.name] = filename
cases = []
for source in sorted(SCENARIOS.glob('*.json')):
    case = json.loads(source.read_text(encoding='utf-8'))
    if 'corpus_expectation' not in case:
        continue
    positive = case['corpus_expectation']['status'] == 'PROJECTED'
    snapshot = snapshots[case.get('financial_scenario', 'financial-scenario.json')]
    args = ['fiscal:dere:project-monthly-closing', snapshot, source.relative_to(ROOT).as_posix(), f'--bundle-manifest={BUNDLE}']
    report_path = E / f'{source.stem}-monthly-report.json'
    if positive and not report_path.exists():
        args += [f'--candidate-output=storage/app/phase4b-d1121-binding-20261005-20/{source.stem}-candidate.xml',
                 f'--report={report_path.relative_to(ROOT).as_posix()}']
    report = execute(args, 0 if positive else 1)
    assert report.get('production_ready') is not True
    if positive:
        assert report['production_ready'] is False
        stored = json.loads(report_path.read_text(encoding='utf-8'))
        for key in ['decision_hash', 'snapshot_hash', 'monthly_input_hash', 'projection', 'event_validations']:
            assert report[key] == stored[key], (source.stem, key)
        document_event = source.stem in ['qualified', 'qualified-continuation']
        expected = {'D1001', 'D1011', 'D1101', 'D1199'} | ({'D1121'} if document_event else set())
        assert set(stored['event_outputs']) == expected
        binding = report['projection']['deduction_pgcc_binding']
        assert binding['reportable_amount'] == ('100.00' if document_event else '0.00')
        assert binding['other_financial_deductions'] == '200.00'
        assert binding['excluded_deduction_entry_ids'] == ['funding']
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
                assert len(xml.findall('.//d:infoConta', ns)) == (6 if source.stem == 'qualified-funding-only' else 7)
            if event == 'D1121':
                assert len(xml.findall('.//d:infoDeducao', ns)) == 1
                assert xml.find('.//d:vDed', ns).text == '100.00'
                assert (xml.find('.//d:vDedTotal', ns) is None) == (source.stem == 'qualified-continuation')
            if event == 'D1199':
                no_deductions = xml.find('.//d:indInexistDedu', ns)
                assert (no_deductions is not None) == (source.stem == 'qualified-no-appropriation')
    else:
        assert report['status'] == 'ACTION_REQUIRED'
        assert report['reason'] == case['corpus_expectation']['reason'], (source.stem, report)
    cases.append({'case': source.stem, 'status': report['status'], 'reason': report['reason']})
assert len(cases) == 19
save(E / 'd1121-pgcc-corpus.json', {'valid': True, 'cases': cases, 'financial_snapshots_replayed': len(snapshots)})
for family in ['factoring', 'securitization']:
    filename = f'financial-dere-{family}-2026.json'
    value = execute(['fiscal:rtc:run', f'resources/fiscal/scenarios/consumption-phase4b-{VERSION}/{filename}', f'--bundle-manifest={BUNDLE}'])
    assert value['reason_codes'] == ['RTC_FINANCIAL_DOCUMENT_PENDING']
    tax = {row['stage']: row for row in value['trace']}['FINANCIAL_GROSS_DEBITS']
    assert tax['base'] == '800.00' and tax['ibs_nominal'] == '0.80' and tax['cbs_nominal'] == '7.20'
    save(ROOT / f'resources/fiscal/expected/consumption-phase4b-{VERSION}/{filename}',
         {k: value[k] for k in ['status', 'reason_codes', 'missing_facts', 'decision_hash']})
for script, args in [('verify_phase4b_financial_credit_cut.py', [VERSION]), ('verify_phase4b_dere_pgcc_cut.py', [])]:
    p = subprocess.run(['python', 'scripts/' + script, *args], cwd=ROOT)
    assert p.returncode == 0, script
baseline = json.loads((E / 'historical-before.json').read_text(encoding='utf-8'))
changed = [name for name, expected in baseline.items() if not (ROOT / name).is_file()
           or hashlib.sha256((ROOT / name).read_bytes()).hexdigest() != expected]
assert changed == [], changed
save(E / 'historical-verification.json', {'checked': len(baseline), 'changed': changed})
print('Verified19 D1121/PGCC cases, four financial replays,18 unsigned event bodies and candidates20/19/18/17.')
