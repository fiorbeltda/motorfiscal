"""Audit confirmed local candidate18 evidence; do not treat it as full phase acceptance."""
import hashlib
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
EVIDENCE = ROOT / 'storage/app/phase4b-contributor-20261005-18'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def log(name):
    return re.sub(r'\x1b\[[0-9;]*m', '', (EVIDENCE / name).read_text(encoding='utf-8', errors='replace'))


assert re.search(r'800 passed\s*\(2658 assertions\)', log('full-suite.log'))
assert '[OK] No errors' in log('phpstan.log')
assert re.search(r'PASS\s*\.+\s*354 files', log('pint.log'))
assert 'Verified8 D1001 monthly cases' in log('candidate-verifier-final.log')
for script in ['fork_phase4b_dere_contributor_bundle.py', 'verify_phase4b_dere_contributor_cut.py']:
    path = ROOT / 'scripts' / script
    compile(path.read_text(encoding='utf-8'), str(path), 'exec')

manifest = read(ROOT / 'resources/fiscal/bundles/consumption-phase4b-2026.10.05-18.json')
catalog = read(ROOT / 'resources/fiscal/catalogs/dere-financial-activities-2026.10.05-18.json')
assert len(catalog['codes']) == 26 and catalog['fiscal_validity'] == 'NOT_SPECIFIED_BY_TABLE'
assert manifest['status'] == 'SANDBOX_CANDIDATE'
for source in manifest['official_sources']:
    path = ROOT / 'resources/fiscal/sources/official' / source['file']
    assert hashlib.sha256(path.read_bytes()).hexdigest() == source['sha256']
assert (EVIDENCE / 'd1001-layout-old.txt').read_bytes() == (EVIDENCE / 'd1001-layout-new.txt').read_bytes()
assert (EVIDENCE / 'd1001-layout-new.txt').stat().st_size > 8000
gate = read(ROOT / 'storage/app/phase4b-production-assessment-20261002-18.json')
assert gate['ready'] is False and gate['eligible_for_local_validation'] is True
assert gate['official_sources_checked'] == gate['official_source_hashes_valid'] == 41
corpus = read(EVIDENCE / 'contributor-corpus.json')
assert corpus['valid'] and len(corpus['cases']) == 8 and corpus['snapshot_replay_matches']
regressions = {}
for cut in ['18', '17']:
    regressions[cut] = {}
    for family, total in [('consumption-phase4b', 15), ('consumption-integrated', 3)]:
        value = read(ROOT / f'storage/app/phase4b-financial-credit-{family}-corpus-20261002-{cut}.json')
        assert value['valid'] and value['passed'] == value['total'] == total
        regressions[cut][family] = {'total': total, 'passed': value['passed']}
baseline = read(EVIDENCE / 'historical-before.json')
changed = [name for name, expected in baseline.items() if not (ROOT / name).is_file()
           or hashlib.sha256((ROOT / name).read_bytes()).hexdigest() != expected]
assert changed == []
monthly = read(EVIDENCE / 'monthly-report.json')
assert set(monthly['event_outputs']) == {'D1001', 'D1101', 'D1121', 'D1199'}
events = {}
for event, path in monthly['event_outputs'].items():
    validation = monthly['event_validations'][event]
    assert validation['reason'] == 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE'
    assert hashlib.sha256((ROOT / path).read_bytes()).hexdigest() == validation['xml_sha256']
    events[event] = {'xml_sha256': validation['xml_sha256'], 'unsigned_body_checked': True}
assert monthly['projection']['period_closed'] is False
assert monthly['projection']['contributor_projection']['registration_performed'] is False
report = {
    'bundle_id': manifest['bundle_id'], 'local_scope_validated': True, 'phase4b_complete': False,
    'plan_scope': ['4B.7.DOCUMENT_PARTIAL', '4B.8.CORPUS_PARTIAL', '5.4.COMPONENT', '6.5.COMPONENT'],
    'tests': {'passed': 800, 'assertions': 2658, 'runtime': 'DOCKER_PHP_8.5.10'},
    'phpstan': 'PASS', 'pint': {'status': 'PASS', 'files': 354},
    'catalog': {'activities': 26, 'official_domain_qualified': True,
        'individual_fiscal_validity': 'NOT_SPECIFIED_BY_TABLE', 'contributor_start_calendar_proofs': 'SYNTHETIC_FIXTURES'},
    'documentary_corpus': corpus, 'integrated_regressions': regressions,
    'events': events, 'snapshot_hash': monthly['snapshot_hash'], 'decision_hash': monthly['decision_hash'],
    'monthly_input_hash': monthly['monthly_input_hash'],
    'historical_artifacts': {'checked': len(baseline), 'changed': changed},
    'gate': {'ready': False, 'eligible_for_local_validation': True, 'sources_checked': 41, 'blockers': len(gate['blockers'])},
    'compatibility': {'d1001_layout_normalized_equal': True, 'monthly_1_3_full_homologation': False,
        'bound_monthly_xsd_package': 'SPED_1_2_0_OBSERVED_UNCHANGED'},
    'actions': {'registration_performed': False, 'signature_created': False,
        'transmission_performed': False, 'collection_performed': False},
    'logs_sha256': {name: hashlib.sha256((EVIDENCE / name).read_bytes()).hexdigest()
                   for name in ['full-suite.log', 'phpstan.log', 'pint.log', 'candidate-verifier-final.log']},
}
path = EVIDENCE / 'verification-report.json'
if path.exists():
    assert read(path) == report, 'Refusing to overwrite differing verification evidence.'
else:
    with path.open('x', encoding='utf-8') as stream:
        stream.write(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
print('Candidate18 audit PASS; 800tests/2658assertions; historical hashes preserved; phase4B and production still open.')
