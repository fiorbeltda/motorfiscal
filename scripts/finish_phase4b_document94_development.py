"""Independently bind terminal94 utility regression to bundle91 and the read-only console; preserve prior acceptance."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re
import shutil

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-document94-component-integration-20261016')
S = Path(r'D:\ProjetoSaasExports\phase4b-document94-pair-binding-gap-20261016')
A = ROOT / 'storage/app/phase4b-document94-development-20261016'
OLD = ROOT / 'storage/app/phase4b-advance91-development-20261015/advance91-development-validation.json'
TARGET = E / 'document94-development-validation.json'
assert not A.exists() and not TARGET.exists(), 'Exclusive acceptance paths'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    with p.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()

def save(p, data):
    with p.open('x', encoding='utf-8') as stream:
        json.dump(data, stream, ensure_ascii=False, indent=2)

assert sha(OLD) == '0f530497e45b469ac0d7b2fa33b3965e645c3eb4e26bb1651d2f3e2f8e49db11'
old = read(OLD)
proof = read(E / 'document94-component-integration-validation.json')
assert proof['status'] == 'DOCUMENT94_UTILITY_INSTALLED_AND_FULL_REGRESSION_VERIFIED'
assert proof['full_suite_complete'] is True and proof['engine91_changed'] is False
for file, digest in proof['evidence_hashes'].items():
    assert sha(E / file) == digest, file
preflight = read(E / 'preflight.json')
for path, digest in preflight['input_hashes'].items():
    assert sha(Path(path)) == digest, path
assert len(preflight['input_hashes']) == 5360
suite = read(E / 'full-suite.json')
assert suite['exit_code'] == 0 and suite['full_suite_complete'] is True
assert suite['tests'] == 2793 and suite['assertions'] == 49475
assert not suite['input_files_changed'] and not suite['input_files_added'] and not suite['input_files_removed']
assert sha(E / 'full-suite.log') == suite['log_sha256']
assert re.search(r'Tests:\s+2793 passed\s+\(49475 assertions\)', (E / 'full-suite.log').read_text(encoding='utf-8'))
for label in ['final-phpstan', 'final-pint', 'final-production-assess91']:
    record = read(E / (label + '.json'))
    assert record['exit_code'] == 0
    for channel in ['stdout', 'stderr']:
        assert sha(E / (label + '.' + channel)) == record[channel + '_sha256']
assert '[OK] No errors' in (E / 'final-phpstan.stdout').read_text()
assert re.search(r'PASS.*544 files', (E / 'final-pint.stdout').read_text())
assessment = read(E / 'final-production-assess91.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 59
assert len(assessment['blockers']) == 119
prior_assessment = read(ROOT / 'storage/app/phase4b-advance91-cli-20261015/production-assessment91-bound.stdout')
assert set(assessment['blockers']) == set(prior_assessment['blockers'])
bundle_file = ROOT / 'resources/fiscal/bundles/consumption-phase4b-2026.10.15-91-draft1.json'
assert sha(bundle_file) == old['bundle_sha256']
bundle = read(bundle_file)
for source in bundle['official_sources']:
    assert sha(ROOT / 'resources/fiscal/sources/official' / source['file']) == source['sha256']
runtime = dict(old['runtime_hashes'])
for path, digest in runtime.items():
    assert sha(ROOT / path) == digest, path
new_runtime = 'app/Modules/Fiscal/NFSe/DocumentBaseReconciliation.php'
runtime[new_runtime] = sha(ROOT / new_runtime)
assert len(runtime) == 72
corpus = read(S / 'document94-native-pair-binding-gap.json')
assert corpus['native_decisions'] == corpus['native_exact_replays'] == 12 and len(corpus['cases']) == 6
for path in S.glob('*-execution.json'):
    record = read(path)
    assert record['exit_code'] == 0
    label = path.name.removesuffix('-execution.json')
    for channel in ['stdout', 'stderr']:
        assert sha(S / (label + '.' + channel)) == record[channel + '_sha256']
for case in corpus['cases']:
    for kind in ['rtc', 'integrated']:
        native = case['native_captures'][kind]
        snapshot = S / native['snapshot_file']
        assert sha(snapshot) == native['sha256'] and native['native_exact_replay'] is True
        label = case['name'] + '-' + kind
        assert read(snapshot)['result'] == read(S / (label + '.stdout'))
        assert read(S / (label + '-replay.stdout'))['replay_matches'] is True
gate = read(E / 'final-production-assess91.json')
gate['log_sha256'] = gate['stdout_sha256']
report = {'status': 'DOCUMENT94_UTILITY_DEVELOPMENT_VERIFIED_WITH_ENGINE91_UNCHANGED',
          'created_at': datetime.now(timezone.utc).isoformat(),
          'scope': 'DECLARED_BASE_MEMORY_COMPARISON_UTILITY_NOT_ENGINE_DOCUMENT_OBLIGATION',
          'bundle_id': bundle['bundle_id'], 'bundle_sha256': sha(bundle_file),
          'full_suite': suite, 'full_suite_complete': True, 'runtime_hashes': runtime,
          'unchanged_input_file_count': 5360, 'previous91_inputs_unchanged': 5353,
          'previous91_acceptance_sha256': sha(OLD),
          'component_integration_report': str(E / 'document94-component-integration-validation.json'),
          'component_integration_sha256': sha(E / 'document94-component-integration-validation.json'),
          'directed_tests': 58, 'directed_assertions': 686,
          'scenarios': 6, 'native_decisions': 12, 'exact_replays': 12, 'prior_exact_replays': 0,
          'native_corpus_scope': 'SIX_EXISTING_SOURCE_SCENARIOS_DUAL_RUNNERS_WITH_DOCUMENT_GATE_PRESERVED',
          'historical91_corpus_preserved_not_relabelled_as94': True,
          'native_corpus_report': str(S / 'document94-native-pair-binding-gap.json'),
          'native_corpus_sha256': sha(S / 'document94-native-pair-binding-gap.json'),
          'official_sources_valid': 59, 'production_blocker_count': 119,
          'final_quality': {'document94-final-production-assess': gate},
          'quality_evidence_on_d': {name: sha(E / name) for name in ['final-phpstan.json', 'final-phpstan.stdout', 'final-phpstan.stderr', 'final-pint.json', 'final-pint.stdout', 'final-pint.stderr']},
          'runtime_component_installed': True, 'engine91_changed': False, 'new_fiscal_bundle_activated': False,
          'engine_opt_in_implemented': False, 'paired_export_projection_complete': False,
          'document_obligation_complete': False, 'source_effective_date_proven': False, 'xml_schema_validated': False,
          'iss95_preparation_installed': False, 'phase4b_complete': False, 'production_ready': False}
save(TARGET, report)
A.mkdir()
receipt = {}
for source, destination in [(TARGET, A / TARGET.name), (E / 'final-production-assess91.stdout', A / 'document94-final-production-assess.log')]:
    assert not destination.exists()
    shutil.copy2(source, destination)
    assert sha(source) == sha(destination)
    receipt[destination.name] = sha(destination)
save(A / 'evidence-copy-receipt.json', {'created_at': datetime.now(timezone.utc).isoformat(),
    'copied_files': receipt, 'large_regression_evidence_retained_on_d': True})
assert sha(OLD) == report['previous91_acceptance_sha256']
print(json.dumps({'status': report['status'], 'tests': suite['tests'], 'assertions': suite['assertions'],
                  'runtime_hashes': len(runtime), 'copied_files_verified': len(receipt), 'production_ready': False}), flush=True)
