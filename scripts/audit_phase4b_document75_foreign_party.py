"""Audit foreign-party component evidence without promoting it to an integrated fiscal route."""
from pathlib import Path
import hashlib
import json
import re

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-document75-foreign-party-native-v3-20261015'
Q = ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014'
FRESH = Path(r'D:\ProjetoSaasExports\phase4b-document75-schema-observation-20261015')


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def canonical(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


gates = {}
for label in ['scope75-foreign-party-regression-v3', 'scope75-foreign-party-type-v2',
              'scope75-foreign-party-style-v5', 'scope75-foreign-party-native-v3']:
    record = read(Q / (label + '.json'))
    assert record['exit_code'] == 0 and sha(Q / (label + '.log')) == record['log_sha256']
    gates[label] = record
plain = re.sub(r'\x1b\[[0-9;]*m', '', (Q / 'scope75-foreign-party-regression-v3.log').read_text(encoding='utf-8'))
assert re.search(r'Tests:\s+75 passed\s+\(421 assertions\)', plain)
assert '[OK] No errors' in (Q / 'scope75-foreign-party-type-v2.log').read_text(encoding='utf-8')
native = read(E / 'foreign-party-native-validation.json')
assert native['valid_structural_envelopes'] == 8 and native['invalid_structural_envelopes'] == 7
projector = ROOT / 'app/Modules/Fiscal/NFSe/DpsForeignPartyProjector.php'
assert native['projector_sha256'] == sha(projector)
assert native['exercise_sha256'] == sha(ROOT / 'scripts/exercise_phase4b_document75_foreign_party.php')
for case in native['cases']:
    xml = E / (case['label'] + '.xml')
    assert sha(xml) == case['xml_sha256'] == case['validation']['xml_sha256']
    if case['validation']['status'] == 'VALID':
        assert canonical(case['party_input']) == case['party_input_hash']
        assert case['evidence_links']['identification_evidence_id'] == case['party_input']['identification_evidence_id']
assert sha(ROOT / native['base_xml_path']) == native['base_xml_sha256']
accepted = read(ROOT / 'storage/app/phase4b-schema74-development-20261014/schema74-development-validation.json')
for path, digest in accepted['runtime_hashes'].items():
    assert sha(ROOT / path) == digest, 'Accepted74 file changed: ' + path
fresh = read(FRESH / 'fresh-schema-validation.json')
assert fresh['archive_equals_accepted74'] and fresh['accepted74_closure_members_equal']
assert fresh['mixed_mode_five_available_in_published_artifact'] is False
assert not any(fresh['new_fields_present'].values())
for source in fresh['observations']:
    assert sha(FRESH / source['name']) == source['sha256']
assert fresh['remote_environment_deployment_verified'] is False
usage = [path.relative_to(ROOT).as_posix() for path in (ROOT / 'app').rglob('*.php')
         if path != projector and 'DpsForeignPartyProjector' in path.read_text(encoding='utf-8')]
assert not usage, 'Do not claim unintegrated preparation after wiring a production caller'
report = {'status': 'FOREIGN_PARTY_COMPONENT_DIRECTED_VALIDATION_NOT_INTEGRATED',
    'projector_sha256': sha(projector),
    'test_sha256': sha(ROOT / 'tests/Fiscal/Unit/DpsForeignPartyProjector75Test.php'),
    'native_report_sha256': sha(E / 'foreign-party-native-validation.json'), 'gates': gates,
    'regression_tests': 75, 'regression_assertions': 421,
    'valid_structural_envelopes': 8, 'invalid_structural_envelopes': 7,
    'accepted74_six_bound_files_unchanged': True,
    'fresh_schema_observation_path': str(FRESH / 'fresh-schema-validation.json'),
    'fresh_schema_observation_sha256': sha(FRESH / 'fresh-schema-validation.json'),
    'active_route_callers': usage, 'integrated_fiscal_route_complete': False,
    'current_full_suite_complete': False, 'predecessor74_full_suite_complete': True,
    'foreign_identity_authenticity_verified': False, 'country_table_validity_qualified': False,
    'document_obligation_complete': False, 'nt009_migration_complete': False,
    'phase4b_complete': False, 'production_ready': False}
with (E / 'foreign-party-component-validation.json').open('x', encoding='utf-8') as stream:
    json.dump(report, stream, ensure_ascii=False, indent=2)
print('PASS:75/421 regression;8 native structures/7 corruptions;component not wired to fiscal route')
