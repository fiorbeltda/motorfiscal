"""Check D2101 no-movement command corpus, replay, schemas and predecessor preservation."""
import hashlib
import json
import subprocess
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = '2026.10.06-21'
BUNDLE = f'bundles/consumption-phase4b-{VERSION}.json'
E = ROOT / 'storage/app/phase4b-d2101-no-movement-20261006-21'
SCENARIOS = ROOT / f'resources/fiscal/scenarios/dere-d2101-no-movement-{VERSION}'


def execute(args, code=0):
    p = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php', 'artisan', *args],
                       cwd=ROOT, capture_output=True, encoding='utf-8')
    assert p.returncode == code, p.stdout + p.stderr
    v = json.loads(p.stdout)
    assert 'error' not in v, v
    return v


def save(path, value):
    if path.exists():
        assert json.loads(path.read_text(encoding='utf-8')) == value, f'Preserved evidence differs: {path}'
        return
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open('x', encoding='utf-8') as stream:
        stream.write(json.dumps(value, ensure_ascii=False, indent=2) + '\n')


snapshot = E.relative_to(ROOT).as_posix() + '/financial-snapshot.json'
args = ['fiscal:rtc:run', (SCENARIOS/'financial-scenario.json').relative_to(ROOT).as_posix(), f'--bundle-manifest={BUNDLE}']
if not (ROOT/snapshot).exists():args.append(f'--snapshot={snapshot}')
result = execute(args)
assert result['reason_codes'] == ['RTC_FINANCIAL_DOCUMENT_PENDING']
tax = {r['stage']:r for r in result['trace']}['FINANCIAL_GROSS_DEBITS']
assert tax['base'] == '700.00' and tax['ibs_nominal'] == '0.70' and tax['cbs_nominal'] == '6.30'
assert execute(['fiscal:rtc:replay', snapshot])['replay_matches']
cases = []
for source in sorted(SCENARIOS.glob('*.json')):
    case = json.loads(source.read_text(encoding='utf-8'))
    if 'corpus_expectation' not in case:continue
    positive = case['corpus_expectation']['status'] == 'PROJECTED'
    report_path = E / f'{source.stem}-monthly-report.json'
    args = ['fiscal:dere:project-monthly-closing', snapshot, source.relative_to(ROOT).as_posix(), f'--bundle-manifest={BUNDLE}']
    if positive and not report_path.exists():
        args += [f'--candidate-output={E.relative_to(ROOT).as_posix()}/{source.stem}-candidate.xml',
                 f'--report={report_path.relative_to(ROOT).as_posix()}']
    report = execute(args, 0 if positive else 1)
    assert report.get('production_ready') is False
    if positive:
        stored = json.loads(report_path.read_text(encoding='utf-8'))
        for key in ['decision_hash', 'snapshot_hash', 'monthly_input_hash', 'projection', 'event_validations']:
            assert report[key] == stored[key], (source.stem,key)
        debt = source.stem != 'qualified-no-obligation'
        expected = {'D1001','D1011','D1101','D1121','D1199'} | ({'D2101'} if debt else set())
        assert set(stored['event_outputs']) == expected
        assert report['projection']['period_closed'] is False
        assert report['projection']['deduction_pgcc_binding']['reportable_amount'] == '100.00'
        assert report['projection']['deduction_pgcc_binding']['other_financial_deductions'] == '200.00'
        assert ('debt_no_movement_projection' in report['projection']) == debt
        for event, filename in stored['event_outputs'].items():
            data = (ROOT/filename).read_bytes();v = report['event_validations'][event]
            assert hashlib.sha256(data).hexdigest() == v['xml_sha256']
            assert v['reason'] == 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and v['unsigned_candidate_structure_checked']
            assert len(v['errors']) == 1
            xml = ET.fromstring(data); ns = {'d':xml.tag.split('}')[0][1:]}
            assert xml.find('.//d:nrInsc',ns).text == '11111111'
            assert xml.find('.//d:tpOper',ns).text == '1' and xml.find('.//d:tpAmb',ns).text == '2'
            if event == 'D2101':
                assert ns['d'].endswith('/evtDebOpOfPublic/v0_0_3')
                assert xml.find('.//d:semTitulos',ns).text == '1'
                assert xml.find('.//d:infoTitulos',ns) is None and xml.find('.//d:detTitulo',ns) is None
                assert v['schema_sha256'] == '3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd'
    else:
        assert report['status'] == 'ACTION_REQUIRED'
        assert report['reason'] == case['corpus_expectation']['reason'], (source.stem,report)
    cases.append({'case':source.stem,'status':report['status'],'reason':report['reason']})
assert len(cases) == 24
save(E/'d2101-no-movement-corpus.json',{'valid':True,'cases':cases,'financial_snapshots_replayed':1})
gate = execute(['fiscal:rtc:production-assess',f'--bundle-manifest={BUNDLE}',
    f'--controls=resources/fiscal/production/phase4b-controls-{VERSION}.json','--target=local-validation'])
assert gate['ready'] is False and gate['eligible_for_local_validation'] is True
assert gate['official_sources_checked'] == gate['official_source_hashes_valid'] == 43
save(E/'production-assessment.json',gate)
for family in ['factoring', 'securitization']:
    filename = f'financial-dere-{family}-2026.json'
    value = execute(['fiscal:rtc:run',f'resources/fiscal/scenarios/consumption-phase4b-{VERSION}/{filename}',f'--bundle-manifest={BUNDLE}'])
    assert value['reason_codes'] == ['RTC_FINANCIAL_DOCUMENT_PENDING']
    tax = {r['stage']:r for r in value['trace']}['FINANCIAL_GROSS_DEBITS']
    assert tax['base'] == '800.00' and tax['ibs_nominal'] == '0.80' and tax['cbs_nominal'] == '7.20'
    save(ROOT/f'resources/fiscal/expected/consumption-phase4b-{VERSION}/{filename}',
         {k:value[k] for k in ['status','reason_codes','missing_facts','decision_hash']})
if '--local-only' not in sys.argv:
    for script,args in [('verify_phase4b_financial_credit_cut.py',[VERSION]),('verify_phase4b_dere_d1121_binding_cut.py',[])]:
        p = subprocess.run(['python','scripts/'+script,*args],cwd=ROOT)
        assert p.returncode == 0,script
baseline = json.loads((E/'historical-before.json').read_text(encoding='utf-8'))
changed = [p for p,h in baseline.items() if not (ROOT/p).is_file() or hashlib.sha256((ROOT/p).read_bytes()).hexdigest() != h]
assert changed == [],changed
save(E/'historical-verification.json',{'checked':len(baseline),'changed':changed})
print('Verified24 D2101 cases, one financial replay,23 unsigned event bodies; historical hashes preserved.'
      if '--local-only' in sys.argv else
      'Verified24 D2101 cases, one financial replay,23 unsigned event bodies and candidates21/20/19/18/17.')
