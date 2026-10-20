"""Audit directed schema74 evidence; deliberately not a full development acceptance."""
from pathlib import Path
import hashlib
import json
import re

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-schema74-cli-corpus-v2-20261014'
Q = ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def canonical(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


gates = {}
for label, counts in [('scope74-selection-directed-v3', (38, 108)),
                      ('scope74-cli-integration-directed', (27, 178)),
                      ('scope74-readiness-regression', (14, 75)),
                      ('scope74-selection-type-v3', None),
                      ('scope74-selection-style-v3', None),
                      ('scope74-cli-integration-style', None)]:
    record = read(Q / (label + '.json'))
    assert record['exit_code'] == 0 and record['log_sha256'] == sha(Q / (label + '.log'))
    log = re.sub(r'\x1b\[[0-9;]*m', '', (Q / (label + '.log')).read_text(encoding='utf-8'))
    if counts:
        actual = re.search(r'Tests:\s+(\d+) passed\s+\((\d+) assertions\)', log)
        assert actual and tuple(map(int, actual.groups())) == counts
    gates[label] = record
corpus = read(E / 'cli-corpus-validation.json')
manifest = ROOT / 'resources/fiscal' / corpus['bundle']
bundle = read(manifest)
assert sha(manifest) == corpus['bundle_sha256']
codes = read(ROOT / 'resources/fiscal' / bundle['resource_paths']['rtc_code_catalog'])
assert canonical(codes) == bundle['content_hashes']['rtc_code_catalog']
assert codes['local_xml_projection']['schema_selection']['environment'] == '2'
archive = ROOT / 'resources/fiscal/sources/official' / codes['dps_xsd']['source_file']
assert sha(archive) == codes['dps_xsd']['source_sha256']
assert sha(archive) == '6c7e0510d3ecff4454f291f4e10b742d27a4818f23aab181494f96d0ea79f3dc'
native_records = list(E.glob('*-execution.json'))
for execution_path in native_records:
    execution = read(execution_path)
    label = execution_path.name.removesuffix('-execution.json')
    for channel in ['stdout', 'stderr']:
        assert sha(E / (label + '.' + channel)) == execution[channel + '_sha256']
assert len(corpus['cases']) == 3
for case in corpus['cases']:
    assert sha(ROOT / case['historical_snapshot']) == case['historical_snapshot_sha256']
    assert sha(ROOT / case['new_snapshot']) == case['new_snapshot_sha256']
    name = case['name']
    historical = read(ROOT / case['historical_snapshot'])
    assert read(E / (name + '-historical-replay.stdout'))['result'] == historical['result']
    current = read(ROOT / case['new_snapshot'])
    assert read(E / (name + '-new-replay.stdout'))['result'] == current['result']
    assert len(case['xml']) == 3 and len(case['refusals']) == 4
    for xml in case['xml']:
        assert sha(ROOT / xml['xml']) == xml['xml_sha256']
        response = read(E / (name + '-project-' + xml['series'] + '.stdout'))
        assert response['schema_validation']['status'] == 'VALID'
        assert response['schema_validation']['xml_sha256'] == xml['xml_sha256']
        assert response['schema_selection']['document_obligation_qualified'] is False
        assert response['production_ready'] is False
    for refusal in case['refusals']:
        assert not (E / (name + '-forbidden-' + refusal['case'] + '.xml')).exists()
        assert read(E / (name + '-refusal-' + refusal['case'] + '.stdout'))['reason'] == refusal['reason']
assessment = read(E / 'local-production-assessment.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['bundle_manifest_hash'] == canonical(bundle)
controls = read(ROOT / 'resources/fiscal' / bundle['production_controls']['path'])
assert assessment['controls_hash'] == canonical(controls) == bundle['production_controls']['sha256']
assert assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 52
assert 'RTC_DPS_RESTRICTED_SCHEMA_LOCAL_ONLY' in assessment['blockers']
fixtures = read(E / 'permanent-fixture-installation.json')
for fixture in fixtures['files']:
    assert sha(ROOT / fixture['target']) == fixture['sha256']
paths = ['app/Console/Commands/ProjectDpsXmlCommand.php',
    'app/Modules/Fiscal/NFSe/DpsRestrictedSchemaSelection.php',
    'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php',
    'tests/Fiscal/Unit/DpsRestrictedSchemaSelection74Test.php',
    'tests/Fiscal/Unit/DpsRestrictedSchemaReadiness74Test.php',
    'tests/Fiscal/Integration/DpsRestrictedSchemaCommand74Test.php']
report = {'status': 'SCHEMA74_DIRECTED_INTEGRATION_VERIFIED_FULL_ACCEPTANCE_PENDING',
    'runtime_hashes': {path: sha(ROOT / path) for path in paths}, 'gates': gates,
    'bundle_sha256': sha(manifest), 'native_cli_executions': len(native_records),
    'xsd_valid_xml_count': 9, 'guard_refusals': 12, 'exact_replays': 6,
    'official_sources_valid': 52, 'production_blocker_count': len(assessment['blockers']),
    'full_suite_complete': False, 'document_obligation_complete': False,
    'nt009_migration_complete': False, 'phase4b_complete': False, 'production_ready': False}
with (E / 'directed-integration-validation.json').open('x', encoding='utf-8') as stream:
    json.dump(report, stream, ensure_ascii=False, indent=2)
print('PASS:directed integration verified;full suite,NT009 and Phase4B acceptance remain pending')
