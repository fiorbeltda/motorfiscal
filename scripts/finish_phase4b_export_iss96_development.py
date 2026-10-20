"""Independently verify terminal96 regression/corpus and publish a scoped local acceptance for the console."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re
import shutil

ROOT = Path(__file__).resolve().parents[1]
F = Path(r'D:\ProjetoSaasExports\phase4b-export-iss96-full-suite-20261016')
E = Path(r'D:\ProjetoSaasExports\phase4b-export-iss96-integration-20261016')
C = E / 'cli'
A = ROOT / 'storage/app/phase4b-export-iss96-development-20261016'
OLD = ROOT / 'storage/app/phase4b-document94-development-20261016/document94-development-validation.json'
TARGET = F / 'export-iss96-development-validation.json'
assert not A.exists() and not TARGET.exists(), 'Exclusive final acceptance'

def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p):
    with p.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
def save(p, value):
    with p.open('x', encoding='utf-8') as stream: json.dump(value, stream, ensure_ascii=False, indent=2)

assert sha(OLD) == 'ac7e8926f3d2ec9c4c93b975f63a240186b57879fca53bc7261591fd3b1d69ce'
proof = read(F / 'export-iss96-regression-validation.json')
assert proof['status'] == 'EXPORT_ISS96_LIVE_PER_DOCUMENT_RESULT_BINDING_REGRESSION_VERIFIED'
preflight = read(F / 'preflight.json')
suite = read(F / 'full-suite.json')
assert proof['full_suite'] == suite and suite['exit_code'] == 0 and suite['full_suite_complete'] is True
assert suite['tests'] == 2962 and suite['assertions'] == 51784
assert not suite['input_files_changed'] and not suite['input_files_added'] and not suite['input_files_removed']
assert sha(F / 'full-suite.log') == suite['log_sha256']
assert re.search(r'Tests:\s+2962 passed\s+\(51784 assertions\)', (F / 'full-suite.log').read_text(encoding='utf-8'))
for path, digest in preflight['input_hashes'].items(): assert sha(Path(path)) == digest, path
assert len(preflight['input_hashes']) == 6121
assert len(proof['runtime_hashes']) == 75
for path, digest in proof['runtime_hashes'].items(): assert sha(ROOT / path) == digest, path
assert proof['runtime_hashes'] == preflight['runtime_hashes']
quality = {}
for label in ['final-phpstan', 'final-pint', 'final-production-assess96']:
    gate = read(F / (label + '.json'))
    assert gate['exit_code'] == 0
    for channel in ['stdout', 'stderr']: assert sha(F / (label + '.' + channel)) == gate[channel + '_sha256']
    quality[label] = gate
assert '[OK] No errors' in (F / 'final-phpstan.stdout').read_text(encoding='utf-8')
assert re.search(r'PASS.*549 files', (F / 'final-pint.stdout').read_text(encoding='utf-8'))
assessment = read(F / 'final-production-assess96.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_source_hashes_valid'] == assessment['official_sources_checked'] == 60
previous = read(ROOT / 'storage/app/phase4b-advance91-cli-20261015/production-assessment91-bound.stdout')
assert set(assessment['blockers']) == set(previous['blockers']) | {'ISS_EXPORT_RESULT_LOCAL_CANDIDATE_ONLY'}
assert len(assessment['blockers']) == 120
bundle_file = ROOT / 'resources/fiscal' / proof['bundle']
assert sha(bundle_file) == proof['bundle_sha256'] == 'eb311a03f50857ebf5300e77019cce46d29bb28acf3705340ec57cb4ea1a7418'
bundle = read(bundle_file)
for source in bundle['official_sources']:
    assert sha(ROOT / 'resources/fiscal/sources/official' / source['file']) == source['sha256']
corpus_path = C / 'export-iss96-cli-validation.json'
assert sha(corpus_path) == preflight['corpus_sha256']
corpus = read(corpus_path)
assert len(corpus['cases']) == 21 and corpus['native_decisions'] == corpus['exact_replays'] == 42
assert len(corpus['historical_replays']) == corpus['historical_exact_replays'] == 113
for path in C.glob('*-execution.json'):
    execution = read(path)
    assert execution['exit_code'] == 0
    label = path.name.removesuffix('-execution.json')
    for channel in ['stdout', 'stderr']: assert sha(C / (label + '.' + channel)) == execution[channel + '_sha256']
for case in corpus['cases']:
    assert sha(ROOT / case['path']) == case['scenario_sha256']
    for kind in ['rtc', 'integrated']:
        snapshot = C / case[kind + '_snapshot']
        assert sha(snapshot) == case[kind + '_snapshot_sha256']
        label = case['name'] + '-' + kind
        assert read(snapshot)['result'] == read(C / (label + '.stdout'))
        assert read(C / (label + '-replay.stdout'))['replay_matches'] is True
for i, prior in enumerate(corpus['historical_replays']):
    assert sha(ROOT / prior['path']) == prior['sha256']
    assert read(C / ('historical-' + str(i) + '.stdout'))['replay_matches'] is True
production_gate = dict(quality['final-production-assess96'])
production_gate['log_sha256'] = production_gate['stdout_sha256']
report = {**proof, 'status': 'EXPORT_ISS96_LIVE_PER_DOCUMENT_RESULT_BINDING_DEVELOPMENT_VERIFIED',
    'created_at': datetime.now(timezone.utc).isoformat(),
    'scope': 'LOCAL_LIVE_ISS_RESULT_BINDING_NOT_ISS_DOCUMENT_CONVERSION_CALCULATION_XML_OR_PRODUCTION',
    'previous94_acceptance_sha256': sha(OLD), 'bundle_id': bundle['bundle_id'], 'scenarios': 21,
    'prior_exact_replays': 113, 'new_permanent_tests': 169, 'directed_tests': 181, 'directed_assertions': 2390,
    'corpus_sha256': sha(corpus_path), 'regression_preflight_sha256': sha(F / 'preflight.json'),
    'regression_validation_sha256': sha(F / 'export-iss96-regression-validation.json'),
    'final_quality': {'export-iss96-final-production-assess': production_gate},
    'quality_evidence_on_d': {p.name: sha(p) for p in F.glob('final-*') if p.is_file()},
    'source_effective_date_proven': False, 'legal_applicability_approved': False,
    'fiscal_document_identifiers_qualified': False, 'iss_supply_date_materially_qualified': False,
    'full_operation_iss_rewritten': False, 'posting_authorized': False}
save(TARGET, report)
A.mkdir()
receipt = {}
for source, target in [(TARGET, A / TARGET.name), (F / 'final-production-assess96.stdout', A / 'export-iss96-final-production-assess.log')]:
    shutil.copy2(source, target)
    assert sha(source) == sha(target)
    receipt[target.name] = sha(target)
save(A / 'evidence-copy-receipt.json', {'created_at': datetime.now(timezone.utc).isoformat(),
    'copied_files': receipt, 'large_evidence_retained_on_d': True})
assert sha(OLD) == report['previous94_acceptance_sha256']
print(json.dumps({'status': report['status'], 'tests': suite['tests'], 'assertions': suite['assertions'],
    'runtime_hashes': 75, 'copied_files_verified': len(receipt), 'acceptance_sha256': sha(A / TARGET.name), 'production_ready': False}), flush=True)
