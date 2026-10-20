"""Audit the bounded local PGCC objective; phase4B and production remain explicitly open."""
import hashlib
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-pgcc-20261005-19'
F = ROOT / 'resources/fiscal'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def log(name):
    return re.sub(r'\x1b\[[0-9;]*m', '', (E / name).read_text(encoding='utf-8'))


assert re.search(r'830 passed\s*\(2700 assertions\)', log('full-suite.log'))
assert '[OK] No errors' in log('phpstan.log')
assert re.search(r'PASS\s*\.+\s*356 files', log('pint.log'))
assert all(code == 0 for code in read(E / 'quality-result.json').values())
assert 'Verified12 PGCC monthly cases' in log('candidate-verifier-final.log')
manifest = read(F / 'bundles/consumption-phase4b-2026.10.05-19.json')
assert manifest['status'] == 'SANDBOX_CANDIDATE'
for name, path in manifest['resource_paths'].items():
    resource = F / path
    value = sorted([read(p) for p in resource.rglob('*.json')], key=lambda r: r['rule_id']) if resource.is_dir() else read(resource)
    assert digest(value) == manifest['content_hashes'][name], name
assert digest(read(F / manifest['production_controls']['path'])) == manifest['production_controls']['sha256']
for source in manifest['official_sources']:
    assert hashlib.sha256((F / 'sources/official' / source['file']).read_bytes()).hexdigest() == source['sha256']
assert len(manifest['official_sources']) == 42
ref = read(F / 'catalogs/dere-cosif-reference-2026.10.05-19.json')
tax = read(F / 'catalogs/dere-tax-codes-2026.10.05-19.json')
assert len(ref['accounts']) == 6 and len(tax['codes']) == 756
assert {e: len(v) for e, v in tax['auxiliary_codes'].items()} == {'D1106': 4, 'D1121': 34, 'D2101': 2}
assert tax['cross_catalog_unresolved'] == ['111270002', '111290001', '411031281']
codes = read(F / manifest['resource_paths']['rtc_code_catalog'])
policy = codes['dere_pgcc_projection']['qualification_policy']
assert policy['reference_catalog_sha256'] == digest(ref) and policy['tax_catalog_sha256'] == digest(tax)
assert policy == codes['dere_monthly_closing_projection']['pgcc_qualification_policy']
assert codes['dere_monthly_closing_projection']['pgcc_catalogs'] == {'reference': ref, 'tax_codes': tax}
corpus = read(E / 'pgcc-corpus.json')
assert corpus['valid'] and len(corpus['cases']) == 12 and corpus['snapshot_replay_matches']
monthly = read(E / 'monthly-report.json')
assert set(monthly['event_outputs']) == {'D1001', 'D1011', 'D1101', 'D1199'}
assert monthly['projection']['pgcc_qualification']['required_auxiliary_events'] == []
assert monthly['projection']['deduction_event_required'] is False
assert monthly['production_ready'] is False and monthly['projection']['period_closed'] is False
events = {}
for event, filename in monthly['event_outputs'].items():
    v = monthly['event_validations'][event]
    assert v['reason'] == 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and v['unsigned_candidate_structure_checked']
    assert len(v['errors']) == 1
    assert hashlib.sha256((ROOT / filename).read_bytes()).hexdigest() == v['xml_sha256']
    events[event] = {'xml_sha256': v['xml_sha256'], 'unsigned_body_checked': True}
gate = read(ROOT / 'storage/app/phase4b-production-assessment-20261002-19.json')
assert gate['ready'] is False and gate['eligible_for_local_validation'] is True
assert gate['official_sources_checked'] == gate['official_source_hashes_valid'] == 42
regressions = {}
for cut in ['19', '18', '17']:
    regressions[cut] = {}
    for family, count in [('consumption-phase4b', 15), ('consumption-integrated', 3)]:
        value = read(ROOT / f'storage/app/phase4b-financial-credit-{family}-corpus-20261002-{cut}.json')
        assert value['valid'] and value['passed'] == value['total'] == count
        regressions[cut][family] = {'passed': count, 'total': count}
baseline = read(E / 'historical-before.json')
changed = [p for p, expected in baseline.items() if not (ROOT / p).is_file()
           or hashlib.sha256((ROOT / p).read_bytes()).hexdigest() != expected]
assert changed == []
for script in ['fork_phase4b_dere_pgcc_bundle.py', 'verify_phase4b_dere_pgcc_cut.py']:
    path = ROOT / 'scripts' / script
    compile(path.read_text(encoding='utf-8'), str(path), 'exec')
report = {'bundle_id': manifest['bundle_id'], 'local_scope_validated': True, 'phase4b_complete': False,
    'plan_scope': ['4B.7.DOCUMENT_PARTIAL', '4B.8.CORPUS_PARTIAL', '5.4.COMPONENT', '6.5.COMPONENT'],
    'tests': {'passed': 830, 'assertions': 2700, 'runtime': 'DOCKER_PHP_8.5.10'},
    'phpstan': 'PASS', 'pint': {'status': 'PASS', 'files': 356}, 'documentary_corpus': corpus,
    'catalogs': {'cosif_selected_members': 6, 'table11_leaf_codes': 756, 'auxiliary_lists': {'D1106': 4, 'D1121': 34, 'D2101': 2},
                'cross_catalog_unresolved': tax['cross_catalog_unresolved'],
                'historical_and_institution_applicability': 'INDEPENDENT_SYNTHETIC_PROOFS_IN_POSITIVE_FIXTURES_NO_AUTOMATIC_LEGAL_APPROVAL'},
    'events': events, 'snapshot_hash': monthly['snapshot_hash'], 'decision_hash': monthly['decision_hash'],
    'monthly_input_hash': monthly['monthly_input_hash'], 'integrated_regressions': regressions,
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
print(f'Candidate19 audit PASS; 830tests/2700assertions; {len(baseline)} historical artifacts preserved; production remains closed.')
