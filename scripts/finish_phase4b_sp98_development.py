"""Independent terminal98 acceptance; does not turn local municipal proof binding into tax/XML approval."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re
import shutil

ROOT = Path(__file__).resolve().parents[1]
F = Path(r'D:\ProjetoSaasExports\phase4b-sp98-full-suite-20261016')
E = Path(r'D:\ProjetoSaasExports\phase4b-sp98-integration-20261016')
C = E / 'cli'
S = Path(r'D:\ProjetoSaasExports\phase4b-document97-sp-primary-research-20261016-v2')
A = ROOT / 'storage/app/phase4b-sp98-development-20261016'
OLD = ROOT / 'storage/app/phase4b-export-iss96-development-20261016/export-iss96-development-validation.json'
TARGET = F / 'sp98-development-validation.json'
assert not A.exists() and not TARGET.exists(), 'Exclusive final acceptance'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p):
    with p.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
def save(p, v):
    with p.open('x', encoding='utf-8') as stream: json.dump(v, stream, ensure_ascii=False, indent=2)
assert sha(OLD) == '5b44a4cd995dc8ae3e8c8a08f03a4361ae40c671aa151f79234286b4974f3453'
proof = read(F / 'sp98-regression-validation.json')
assert proof['status'] == 'SP98_CURRENT_MUNICIPAL_RESULT_BINDING_REGRESSION_VERIFIED'
preflight = read(F / 'preflight.json')
suite = read(F / 'full-suite.json')
assert proof['full_suite'] == suite and suite['exit_code'] == 0 and suite['full_suite_complete'] is True
assert suite['tests'] == 3173 and isinstance(suite['assertions'], int) and suite['assertions'] > 51784
assert not suite['input_files_changed'] and not suite['input_files_added'] and not suite['input_files_removed']
assert sha(F / 'full-suite.log') == suite['log_sha256']
assert re.search(r'Tests:\s+3173 passed\s+\(' + str(suite['assertions']) + r' assertions\)', (F / 'full-suite.log').read_text(encoding='utf-8'))
for path, digest in preflight['input_hashes'].items(): assert sha(Path(path)) == digest, path
assert len(preflight['input_hashes']) == proof['unchanged_input_files'] and len(preflight['input_hashes']) > 6121
assert proof['runtime_hashes'] == preflight['runtime_hashes'] and len(proof['runtime_hashes']) == 77
for path, digest in proof['runtime_hashes'].items(): assert sha(ROOT / path) == digest, path
original = (S / 'SpSoftwareExportResult97-formatted.php').read_text(encoding='utf-8')
installed = (ROOT / 'app/Modules/Fiscal/ISS/SpSoftwareExportResult97.php').read_text(encoding='utf-8')
assert installed == original.replace('namespace Phase4bPrepared;', 'namespace App\\Modules\\Fiscal\\ISS;').replace('use App\\Modules\\Fiscal\\ISS\\IssExportResultQualifier95;\n', '')
assert sha(ROOT / 'tests/Fixtures/sp97-cases.json') == sha(S / 'cases-v2.json')
quality = {}
for label in ['final-phpstan', 'final-pint', 'final-production-assess98']:
    gate = read(F / (label + '.json'))
    assert gate['exit_code'] == 0
    for channel in ['stdout', 'stderr']: assert sha(F / (label + '.' + channel)) == gate[channel + '_sha256']
    quality[label] = gate
assert '[OK] No errors' in (F / 'final-phpstan.stdout').read_text(encoding='utf-8')
assert re.search(r'PASS.*554 files', (F / 'final-pint.stdout').read_text(encoding='utf-8'))
assessment = read(F / 'final-production-assess98.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_source_hashes_valid'] == assessment['official_sources_checked'] == 62
previous = read(Path(r'D:\ProjetoSaasExports\phase4b-export-iss96-integration-20261016\cli\production-assess96.stdout'))
assert set(assessment['blockers']) == set(previous['blockers']) | {'SP_SOFTWARE_EXPORT_RESULT_LOCAL_CANDIDATE_ONLY'}
assert len(assessment['blockers']) == 121
bundle_file = ROOT / 'resources/fiscal' / proof['bundle']
assert sha(bundle_file) == proof['bundle_sha256']
bundle = read(bundle_file)
for source in bundle['official_sources']: assert sha(ROOT / 'resources/fiscal/sources/official' / source['file']) == source['sha256']
corpus_path = C / 'sp98-cli-validation.json'
assert sha(corpus_path) == preflight['corpus_sha256']
corpus = read(corpus_path)
assert len(corpus['cases']) == 30 and corpus['native_decisions'] == corpus['exact_replays'] == 60
assert len(corpus['historical_replays']) == corpus['historical_exact_replays'] == 155
for path in C.glob('*-execution.json'):
    execution = read(path)
    assert execution['exit_code'] == 0
    label = path.name.removesuffix('-execution.json')
    for channel in ['stdout', 'stderr']: assert sha(C / (label + '.' + channel)) == execution[channel + '_sha256']
for case in corpus['cases']:
    assert sha(ROOT / case['path']) == case['scenario_sha256']
    for kind in ['rtc', 'integrated']:
        snapshot = C / case[kind + '_snapshot']
        label = case['name'] + '-' + kind
        assert sha(snapshot) == case[kind + '_snapshot_sha256']
        assert read(snapshot)['result'] == read(C / (label + '.stdout'))
        assert read(C / (label + '-replay.stdout'))['replay_matches'] is True
for i, prior in enumerate(corpus['historical_replays']):
    assert sha(Path(prior['path'])) == prior['sha256']
    assert read(C / ('historical-' + str(i) + '.stdout'))['replay_matches'] is True
gate = dict(quality['final-production-assess98'])
gate['log_sha256'] = gate['stdout_sha256']
report = {**proof, 'status': 'SP98_CURRENT_MUNICIPAL_RESULT_BINDING_DEVELOPMENT_VERIFIED',
    'created_at': datetime.now(timezone.utc).isoformat(), 'previous96_acceptance_sha256': sha(OLD),
    'scope': 'LOCAL_LIVE_SP_DOCUMENTARY_RESULT_BINDING_NOT_FX_TAX_XML_OR_PRODUCTION', 'bundle_id': bundle['bundle_id'],
    'scenarios': 30, 'prior_exact_replays': 155, 'new_permanent_tests': 211, 'directed_tests': 392, 'directed_assertions': 5989,
    'corpus_sha256': sha(corpus_path), 'regression_preflight_sha256': sha(F / 'preflight.json'),
    'regression_validation_sha256': sha(F / 'sp98-regression-validation.json'),
    'final_quality': {'sp98-final-production-assess': gate},
    'quality_evidence_on_d': {p.name: sha(p) for p in F.glob('final-*') if p.is_file()},
    'source_effective_date_proven': False, 'legal_applicability_approved': False, 'fiscal_document_identifiers_qualified': False,
    'iss_supply_date_materially_qualified': False, 'full_operation_iss_rewritten': False, 'posting_authorized': False}
save(TARGET, report)
A.mkdir()
receipt = {}
for source, target in [(TARGET, A / TARGET.name), (F / 'final-production-assess98.stdout', A / 'sp98-final-production-assess.log')]:
    shutil.copy2(source, target)
    assert sha(source) == sha(target)
    receipt[target.name] = sha(target)
save(A / 'evidence-copy-receipt.json', {'created_at': datetime.now(timezone.utc).isoformat(), 'copied_files': receipt, 'large_evidence_retained_on_d': True})
assert sha(OLD) == report['previous96_acceptance_sha256']
print(json.dumps({'status': report['status'], 'tests': suite['tests'], 'assertions': suite['assertions'], 'runtime_hashes': 77,
    'copied_files_verified': len(receipt), 'acceptance_sha256': sha(A / TARGET.name), 'production_ready': False}), flush=True)
