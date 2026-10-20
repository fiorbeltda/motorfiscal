"""Verify own candidate CLI evidence and final directed gates, not full development acceptance."""
from pathlib import Path
import hashlib
import json
import re

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-foreign-acquirer76-cli-20261015'
Q = ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014'
F = ROOT / 'resources/fiscal'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def canonical(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


gates = {}
for label in ['scope76-foreign-candidate-directed-v2', 'scope76-foreign-candidate-type-v2',
              'scope76-foreign-readiness-directed', 'scope76-foreign-command-regression',
              'scope76-foreign-candidate-style-v2', 'scope76-foreign-command-style',
              'scope76-phpstan-all', 'scope76-pint-all']:
    record = read(Q / (label + '.json'))
    assert record['exit_code'] == 0 and record['log_sha256'] == sha(Q / (label + '.log')), label
    gates[label] = record
for label, tests, assertions in [('scope76-foreign-candidate-directed-v2', 55, 143),
                                 ('scope76-foreign-readiness-directed', 15, 82),
                                 ('scope76-foreign-command-regression', 70, 397)]:
    log = re.sub(r'\x1b\[[0-9;]*m', '', (Q / (label + '.log')).read_text(encoding='utf-8'))
    assert re.search(r'Tests:\s+' + str(tests) + r' passed\s+\(' + str(assertions) + r' assertions\)', log)
corpus = read(E / 'foreign-acquirer-cli-validation.json')
assert len(corpus['cases']) == 3 and len(corpus['refusals']) == 5
bundle = read(F / corpus['bundle'])
assert sha(F / corpus['bundle']) == corpus['bundle_sha256']
codes = read(F / bundle['resource_paths']['rtc_code_catalog'])
controls = read(F / bundle['production_controls']['path'])
assert canonical(codes) == bundle['content_hashes']['rtc_code_catalog']
assert canonical(controls) == bundle['production_controls']['sha256']
for execution_path in E.glob('*-execution.json'):
    label = execution_path.name.removesuffix('-execution.json')
    execution = read(execution_path)
    for channel in ['stdout', 'stderr']:
        assert sha(E / (label + '.' + channel)) == execution[channel + '_sha256']
for case in corpus['cases']:
    snapshot = read(ROOT / case['snapshot'])
    assert sha(ROOT / case['snapshot']) == case['snapshot_sha256']
    assert read(E / (case['case'] + '-replay.stdout'))['result'] == snapshot['result']
    projected = read(E / (case['case'] + '-project.stdout'))
    assert sha(ROOT / case['xml']) == case['xml_sha256'] == projected['schema_validation']['xml_sha256']
    assert projected['schema_validation']['status'] == 'VALID'
    assert projected['identity_hash'] == canonical(read(ROOT / case['identity']))
    binding = projected['projection']['foreign_acquirer_binding']
    assert binding['identity_input_hash'] == projected['identity_hash']
    for flag in ['country_table_validity_qualified', 'currency_competence_validity_proven',
                 'comext_mode_fiscally_qualified', 'identity_authenticity_verified',
                 'document_obligation_complete', 'production_ready']:
        assert binding[flag] is False
assessment = read(E / 'production-assessment.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 53
assert 'RTC_DPS_RESTRICTED_SCHEMA_LOCAL_ONLY' in assessment['blockers']
for source in bundle['official_sources']:
    assert sha(F / 'sources/official' / source['file']) == source['sha256']
fixtures = read(E / 'permanent-fixture-installation.json')
for fixture in fixtures['files']:
    assert sha(ROOT / fixture['path']) == fixture['sha256']
paths = ['app/Modules/Fiscal/NFSe/DpsForeignAcquirerCandidate.php',
    'app/Modules/Fiscal/NFSe/DpsForeignPartyProjector.php', 'app/Modules/Fiscal/NFSe/DpsXmlProjector.php',
    'app/Modules/Fiscal/NFSe/DpsRestrictedSchemaSelection.php',
    'app/Console/Commands/ProjectDpsXmlCommand.php',
    'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php',
    'tests/Fiscal/Unit/DpsForeignAcquirerCandidate76Test.php',
    'tests/Fiscal/Unit/DpsForeignPartyProjector75Test.php',
    'tests/Fiscal/Unit/DpsRestrictedSchemaReadiness74Test.php',
    'tests/Fiscal/Integration/DpsForeignAcquirerCommand76Test.php']
report = {'status': 'FOREIGN_ACQUIRER76_LOCAL_INTEGRATION_PREFLIGHT_VERIFIED_FULL_SUITE_PENDING',
    'runtime_hashes': {path: sha(ROOT / path) for path in paths}, 'gates': gates,
    'bundle_sha256': corpus['bundle_sha256'], 'native_candidates': 3, 'refusals': 5,
    'exact_replays': 3, 'official_sources_valid': 53, 'production_blocker_count': len(assessment['blockers']),
    'full_suite_complete': False, 'document_obligation_complete': False,
    'mixed_export_pair_complete': False, 'nt009_migration_complete': False,
    'phase4b_complete': False, 'production_ready': False}
with (E / 'foreign-acquirer-integration-preflight.json').open('x', encoding='utf-8') as stream:
    json.dump(report, stream, ensure_ascii=False, indent=2)
print('PASS:three source-bound candidates and domestic tax decisions;full suite and fiscal documentary qualifications pending')
