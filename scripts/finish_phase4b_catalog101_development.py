"""Independent terminal101 audit; official-row availability is not tax/document approval."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re
import shutil

ROOT = Path(__file__).resolve().parents[1]
F = Path(r'D:\ProjetoSaasExports\phase4b-catalog101-full-suite-20261016')
E = Path(r'D:\ProjetoSaasExports\phase4b-catalog101-integration-20261016')
C = E / 'cli'
P = Path(r'D:\ProjetoSaasExports\phase4b-catalog100-reference-preparation-20261016')
A = ROOT / 'storage/app/phase4b-catalog101-development-20261016'
OLD = ROOT / 'storage/app/phase4b-sp98-development-20261016/sp98-development-validation.json'
TARGET = F / 'catalog101-development-validation.json'
assert not A.exists() and not TARGET.exists(), 'Exclusive final acceptance'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p):
    with p.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
def save(p, value):
    with p.open('x', encoding='utf-8') as stream: json.dump(value, stream, ensure_ascii=False, indent=2)
def canonical(v): return hashlib.sha256(json.dumps(v, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
assert sha(OLD) == '29b80efd975c9a5597938aefd55e77a6f0fca7d7dd779efc9e220342b3a8fad7'
proof = read(F / 'catalog101-regression-validation.json')
assert proof['status'] == 'CATALOG101_REFERENCE_INTEGRATION_REGRESSION_VERIFIED'
preflight = read(F / 'preflight.json')
suite = read(F / 'full-suite.json')
assert proof['full_suite'] == suite and suite['exit_code'] == 0 and suite['full_suite_complete'] is True
assert suite['tests'] == 6296 and isinstance(suite['assertions'], int) and suite['assertions'] > 55383
assert not suite['input_files_changed'] and not suite['input_files_added'] and not suite['input_files_removed']
assert sha(F / 'full-suite.log') == suite['log_sha256']
assert re.search(r'Tests:\s+6296 passed\s+\(' + str(suite['assertions']) + r' assertions\)', (F / 'full-suite.log').read_text(encoding='utf-8'))
for path, digest in preflight['input_hashes'].items(): assert sha(Path(path)) == digest, path
assert len(preflight['input_hashes']) == proof['unchanged_input_files'] > 7577
assert proof['runtime_hashes'] == preflight['runtime_hashes'] and len(proof['runtime_hashes']) == 78
for path, digest in proof['runtime_hashes'].items(): assert sha(ROOT / path) == digest, path
original = (P / 'CClassTribReference100-formatted.php').read_text(encoding='utf-8')
installed = (ROOT / 'app/Modules/Fiscal/ConsumptionReform/CClassTribReference100.php').read_text(encoding='utf-8')
assert installed == original.replace('namespace Phase4bPrepared;', 'namespace App\\Modules\\Fiscal\\ConsumptionReform;')
assert sha(ROOT / 'tests/Fixtures/cclass100-reference-cases.json') == sha(P / 'reference-cases.json')
bundle_path = ROOT / 'resources/fiscal' / proof['bundle']
assert sha(bundle_path) == proof['bundle_sha256']
bundle = read(bundle_path)
previous = read(ROOT / 'resources/fiscal/bundles/consumption-phase4b-2026.10.16-98-draft1.json')
assert bundle['status'] == 'SANDBOX_CANDIDATE'
for key in ['rtc_rate_catalog', 'rtc_transition_catalog', 'fact_definitions', 'questions']:
    assert read(ROOT / 'resources/fiscal' / bundle['resource_paths'][key]) == read(ROOT / 'resources/fiscal' / previous['resource_paths'][key])
codes = read(ROOT / 'resources/fiscal' / bundle['resource_paths']['rtc_code_catalog'])
before = read(ROOT / 'resources/fiscal' / previous['resource_paths']['rtc_code_catalog'])
for key in ['engine_decision_rules', 'correlations', 'cindop']:
    assert codes[key] == before[key], key
policy = codes['cclass_reference_policy']
assert len(codes['cclasstrib']) == 173 and len(codes['cst_reference_rows']) == 18
assert canonical(codes['cclasstrib']) == policy['rows_sha256'] == 'c840248705b0fe23e4ee2331459de873a4c3a777cf36a1677e651f4048399107'
assert canonical(codes['cst_reference_rows']) == policy['cst_rows_sha256'] == '94bd20f6efe0eaff2a48eea4d9a5b93afccd322fd5ce6387f05b93cb21bf852b'
assert policy == read(P / 'reference-cases.json')['policy']
assert codes['cclasstrib']['410028']['document_and_group_indicators']['indNFSe'] == 0
for source in bundle['official_sources']: assert sha(ROOT / 'resources/fiscal/sources/official' / source['file']) == source['sha256']
quality = {}
for label in ['final-phpstan', 'final-pint', 'final-production-assess101']:
    gate = read(F / (label + '.json'))
    assert gate['exit_code'] == 0
    for channel in ['stdout', 'stderr']: assert sha(F / (label + '.' + channel)) == gate[channel + '_sha256']
    quality[label] = gate
assert '[OK] No errors' in (F / 'final-phpstan.stdout').read_text(encoding='utf-8')
assert re.search(r'PASS.*558 files', (F / 'final-pint.stdout').read_text(encoding='utf-8'))
assessment = read(F / 'final-production-assess101.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_source_hashes_valid'] == assessment['official_sources_checked'] == 66
prior_assess = read(Path(r'D:\ProjetoSaasExports\phase4b-sp98-integration-20261016\cli\production-assess98.stdout'))
assert set(assessment['blockers']) == set(prior_assess['blockers']) | {'CCLASSTRIB_REFERENCE_LOCAL_ONLY_NOT_CLASSIFICATION_APPROVAL'}
assert len(assessment['blockers']) == 122
corpus_path = C / 'catalog101-cli-validation.json'
assert sha(corpus_path) == preflight['corpus_sha256']
corpus = read(corpus_path)
assert len(corpus['cases']) == 61 and corpus['native_decisions'] == corpus['exact_replays'] == 122
assert len(corpus['historical_replays']) == corpus['historical_exact_replays'] == 215
for path in C.glob('*-execution.json'):
    gate = read(path)
    assert gate['exit_code'] == 0
    label = path.name.removesuffix('-execution.json')
    for channel in ['stdout', 'stderr']: assert sha(C / (label + '.' + channel)) == gate[channel + '_sha256']
for case in corpus['cases']:
    assert sha(ROOT / case['path']) == case['scenario_sha256']
    current = read(ROOT / case['path']); source = read(ROOT / case['original_path'])
    assert sha(ROOT / case['original_path']) == case['original_sha256']
    assert current['facts'] == source['facts']
    assert datetime.fromisoformat(current['knowledge_at']) >= datetime.fromisoformat(source['knowledge_at'])
    for kind in ['rtc', 'integrated']:
        snapshot = C / case[kind + '_snapshot']; label = case['name'] + '-' + kind
        assert sha(snapshot) == case[kind + '_snapshot_sha256']
        assert read(snapshot)['result'] == read(C / (label + '.stdout'))
        assert read(C / (label + '-replay.stdout'))['replay_matches'] is True
for i, prior in enumerate(corpus['historical_replays']):
    assert sha(Path(prior['path'])) == prior['sha256']
    assert read(C / ('historical-' + str(i) + '.stdout'))['replay_matches'] is True
gate = dict(quality['final-production-assess101']); gate['log_sha256'] = gate['stdout_sha256']
report = {**proof, 'status': 'CATALOG101_REFERENCE_INTEGRATION_DEVELOPMENT_VERIFIED',
    'created_at': datetime.now(timezone.utc).isoformat(), 'previous98_acceptance_sha256': sha(OLD),
    'scope': 'OFFICIAL_REFERENCE_SOURCE_TIME_VALIDITY_MODEL_NOT_TAX_DOCUMENT_OR_PRODUCTION_APPROVAL',
    'bundle_id': bundle['bundle_id'], 'scenarios': 61, 'prior_exact_replays': 215,
    'new_permanent_tests': 3123, 'directed_tests': 3186, 'directed_assertions': 40482,
    'corpus_sha256': sha(corpus_path), 'regression_preflight_sha256': sha(F / 'preflight.json'),
    'regression_validation_sha256': sha(F / 'catalog101-regression-validation.json'),
    'final_quality': {'catalog101-final-production-assess': gate},
    'quality_evidence_on_d': {p.name: sha(p) for p in F.glob('final-*') if p.is_file()},
    'remote_deployment_proven': False, 'legal_applicability_approved': False, 'posting_authorized': False}
save(TARGET, report)
A.mkdir()
receipt = {}
for source, target in [(TARGET, A / TARGET.name), (F / 'final-production-assess101.stdout', A / 'catalog101-final-production-assess.log')]:
    shutil.copy2(source, target)
    assert sha(source) == sha(target)
    receipt[target.name] = sha(target)
save(A / 'evidence-copy-receipt.json', {'created_at': datetime.now(timezone.utc).isoformat(), 'copied_files': receipt, 'large_evidence_retained_on_d': True})
assert sha(OLD) == report['previous98_acceptance_sha256']
print(json.dumps({'status': report['status'], 'tests': suite['tests'], 'assertions': suite['assertions'], 'runtime_hashes': 78,
    'copied_files_verified': len(receipt), 'acceptance_sha256': sha(A / TARGET.name), 'production_ready': False}), flush=True)
