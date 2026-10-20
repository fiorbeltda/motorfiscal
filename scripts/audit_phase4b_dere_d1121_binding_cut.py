"""Audit the local D1121/PGCC objective without declaring phase or production completion."""
import hashlib
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = ROOT / 'storage/app/phase4b-d1121-binding-20261005-20'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def log(name):
    return re.sub(r'\x1b\[[0-9;]*m', '', (E / name).read_text(encoding='utf-8'))


assert re.search(r'860 passed\s*\(2752 assertions\)', log('full-suite.log'))
assert '[OK] No errors' in log('phpstan.log')
assert re.search(r'PASS\s*\.+\s*358 files', log('pint.log'))
assert all(code == 0 for code in read(E / 'quality-result.json').values())
assert 'Verified19 D1121/PGCC cases' in log('candidate-verifier-final.log')
manifest = read(F / 'bundles/consumption-phase4b-2026.10.05-20.json')
assert manifest['status'] == 'SANDBOX_CANDIDATE'
for name, path in manifest['resource_paths'].items():
    resource = F / path
    value = sorted([read(p) for p in resource.rglob('*.json')], key=lambda r: r['rule_id']) if resource.is_dir() else read(resource)
    assert digest(value) == manifest['content_hashes'][name], name
assert digest(read(F / manifest['production_controls']['path'])) == manifest['production_controls']['sha256']
for source in manifest['official_sources']:
    assert hashlib.sha256((F / 'sources/official' / source['file']).read_bytes()).hexdigest() == source['sha256']
assert len(manifest['official_sources']) == 42
codes = read(F / manifest['resource_paths']['rtc_code_catalog'])
closing = codes['dere_monthly_closing_projection']
ref = read(F / codes['dere_pgcc_projection']['reference_catalog_path'])
assert len(ref['accounts']) == 7
assert digest(ref) == closing['pgcc_qualification_policy']['reference_catalog_sha256']
assert ref == closing['pgcc_catalogs']['reference']
assert closing['pgcc_deduction_binding_required'] is True
assert list(closing['deduction_entry_mappings']) == ['411011105']
corpus = read(E / 'd1121-pgcc-corpus.json')
assert corpus['valid'] and len(corpus['cases']) == 19 and corpus['financial_snapshots_replayed'] == 4
reports = {}
for name in ['qualified', 'qualified-continuation', 'qualified-no-appropriation', 'qualified-funding-only']:
    monthly = read(E / f'{name}-monthly-report.json')
    assert monthly['production_ready'] is False and monthly['projection']['period_closed'] is False
    binding = monthly['projection']['deduction_pgcc_binding']
    assert binding['other_financial_deductions'] == '200.00'
    document_event = name in ['qualified', 'qualified-continuation']
    assert binding['reportable_amount'] == ('100.00' if document_event else '0.00')
    assert ('D1121' in monthly['event_outputs']) == document_event
    events = {}
    for event, filename in monthly['event_outputs'].items():
        v = monthly['event_validations'][event]
        assert v['reason'] == 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and v['unsigned_candidate_structure_checked']
        assert len(v['errors']) == 1
        assert hashlib.sha256((ROOT / filename).read_bytes()).hexdigest() == v['xml_sha256']
        events[event] = {'xml_sha256': v['xml_sha256'], 'unsigned_body_checked': True}
    reports[name] = {'events': events, 'binding': binding, 'snapshot_hash': monthly['snapshot_hash'],
                     'decision_hash': monthly['decision_hash'], 'monthly_input_hash': monthly['monthly_input_hash']}
assert sum(len(v['events']) for v in reports.values()) == 18
gate = read(ROOT / 'storage/app/phase4b-production-assessment-20261002-20.json')
assert gate['ready'] is False and gate['eligible_for_local_validation'] is True
assert gate['official_sources_checked'] == gate['official_source_hashes_valid'] == 42
regressions = {}
for cut in ['20', '19', '18', '17']:
    regressions[cut] = {}
    for family, count in [('consumption-phase4b', 15), ('consumption-integrated', 3)]:
        value = read(ROOT / f'storage/app/phase4b-financial-credit-{family}-corpus-20261002-{cut}.json')
        assert value['valid'] and value['passed'] == value['total'] == count
        regressions[cut][family] = {'passed': count, 'total': count}
baseline = read(E / 'historical-before.json')
changed = [p for p, expected in baseline.items() if not (ROOT / p).is_file()
           or hashlib.sha256((ROOT / p).read_bytes()).hexdigest() != expected]
assert changed == []
for name in ['fork_phase4b_dere_d1121_binding_bundle.py', 'verify_phase4b_dere_d1121_binding_cut.py']:
    path = ROOT / 'scripts' / name
    compile(path.read_text(encoding='utf-8'), str(path), 'exec')
report = {'bundle_id': manifest['bundle_id'], 'local_scope_validated': True, 'phase4b_complete': False,
    'plan_scope': ['4B.7.DOCUMENT_PARTIAL', '4B.8.CORPUS_PARTIAL', '5.4.COMPONENT', '6.5.COMPONENT'],
    'tests': {'passed': 860, 'assertions': 2752, 'runtime': 'DOCKER_PHP_8.5.10'},
    'phpstan': 'PASS', 'pint': {'status': 'PASS', 'files': 358}, 'documentary_corpus': corpus,
    'scope': {'codTrib': ['411011105'], 'activity': '01', 'family': 'CREDIT_FX_SECURITIES_ART192',
              'positive_document_and_account_proofs': 'SYNTHETIC_NO_REMOTE_VERIFICATION', 'other_d1121_codes_implemented': False},
    'monthly_reports': reports, 'integrated_regressions': regressions,
    'historical_artifacts': {'checked': len(baseline), 'changed': changed},
    'gate': {'ready': False, 'eligible_for_local_validation': True, 'sources_checked': 42, 'blockers': len(gate['blockers'])},
    'actions': {'signature_created': False, 'transmission_performed': False, 'period_closed': False, 'collection_performed': False},
    'logs_sha256': {name: hashlib.sha256((E / name).read_bytes()).hexdigest()
                   for name in ['full-suite.log', 'phpstan.log', 'pint.log', 'candidate-verifier-final.log']}}
path = E / 'verification-report.json'
if path.exists():
    assert read(path) == report, 'Refusing to overwrite differing verification evidence.'
else:
    with path.open('x', encoding='utf-8') as stream:
        stream.write(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
print(f'Candidate20 audit PASS; 860tests/2752assertions; {len(baseline)} historical artifacts preserved; production remains closed.')
