"""Publish scoped122 terminal acceptance only after both immutable collectors finish."""
from datetime import datetime, timezone
from pathlib import Path
import json
import re
import shutil
from phase4b_credit122_capture_support import R, E, read, sha, save, core_inventory

F = E.parent/'phase4b-credit122-full-suite-20261021-v2'
H = E/'historical-replay-v2'
A = R/'storage/app/phase4b-credit122-development-20261021'
target = F/'credit122-development-validation.json'
assert not target.exists() and not A.exists(), 'Exclusive scoped acceptance'
regression_path = F/'credit122-regression-validation.json'
history_path = H/'credit122-historical-validation.json'
regression, history = read(regression_path), read(history_path)
suite = read(F/'full-suite.json')
native_path = E/'credit122-current-native-audit-v1.json'
native = read(native_path)
matrix_path = E.parent/'phase4b-requirements125-catalog-review-20261021-v1/consolidated-original80-v1/requirements80-current-matrix.json'
matrix = read(matrix_path)
assert suite == regression['full_suite'] and suite['exit_code'] == 0 and suite['full_suite_complete']
assert suite['tests'] == 9061 and suite['assertions'] == 143868
assert sha(F/'full-suite.log') == suite['log_sha256']
assert not suite['input_files_changed'] and not suite['input_files_added'] and not suite['input_files_removed']
clean = lambda p: re.sub(r'\x1b\[[0-9;]*m', '', p.read_text(encoding='utf-8-sig'))
assert re.search(r'Tests:\s+9061 passed\s+\(143868 assertions\)', clean(F/'full-suite.log'))
assert native['native_decisions'] == native['exact_replays'] == 550 and native['audit_checks'] == 17333
assert history['historical_exact_replays'] == len(history['records']) == 1441 and history['input_inventory_unchanged']
assert regression['runtime_hashes'] == history['runtime_hashes'] == native['runtime_hashes'] == matrix['runtime_hashes_verified']
assert len(native['runtime_hashes']) == 94
for path, digest in native['runtime_hashes'].items():
    assert sha(R/path) == digest, path
core = core_inventory()
frozen = read(F/'preflight.json')['input_hashes']
for path, digest in frozen.items():
    assert sha(path) == digest, path
assert set(core) <= set(frozen) and all(frozen[path] == digest for path,digest in core.items())
assert matrix['examined_ids'] == len(matrix['requirements']) == 80 and matrix['items_with_later_local_fix_overlays'] == 17
assert all(row['reviewed'] and not row['formal_completion_approved'] for row in matrix['requirements'])
for label in ['final-phpstan','final-pint','final-production-assess122']:
    receipt = read(F/(label+'.json'))
    assert receipt['exit_code'] == 0
    for stream in ['stdout','stderr']:
        assert sha(F/(label+'.'+stream)) == receipt[stream+'_sha256']
assert '[OK] No errors' in clean(F/'final-phpstan.stdout')
assert re.search(r'PASS.*606 files',clean(F/'final-pint.stdout'))
assessment = read(F/'final-production-assess122.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 98
assert len(assessment['blockers']) == 128
assert native['credit_corpus_sha256'] == sha(E/'credit-cli-v2/credit122-cli-validation.json')
assert native['expanded_corpus_sha256'] == sha(E/'expanded-cli-v1/credit122-expanded-cli-validation.json')
report = {**regression, 'created_at':datetime.now(timezone.utc).isoformat(),
 'status':'CREDIT122_LOCAL_STRUCTURAL_INPUT_QUESTIONS_AND_TYPED_REGRESSION_DEVELOPMENT_VERIFIED',
 'scope':'VERSIONED_CREDIT_INPUT_AND_CORRECTION_QUESTIONS_WITH_PRESERVED_PRICE_GEOGRAPHY_CONTRACTS_NOT_COMPLETE_PHASE4B',
 'development_acceptance_complete':True, 'full_suite':suite,
 'scenarios':275, 'native_decisions':550, 'exact_replays':550, 'historical_exact_replays':1441, 'prior_exact_replays':1441,
 'independent_current_native_checks':17333, 'examined_original_ids_not_approved':80,
 'original_matrix_path':str(matrix_path), 'original_matrix_sha256':sha(matrix_path),
 'later_local_fix_requirement_overlays':17,
 'regression_validation_sha256':sha(regression_path), 'historical_validation_sha256':sha(history_path),
 'native_audit_sha256':sha(native_path), 'auditor_sha256':sha(Path(__file__)),
 'evidence_root_on_d':str(E), 'historical_root_on_d':str(H),
 'formal_phase4b_groups_closed':0, 'document_obligation_complete':False,
 'government_evidence_authenticated':False,'posting_authorized':False,
 'phase4b_complete':False,'production_ready':False,
 'final_quality':{'final-production-assess122':{**read(F/'final-production-assess122.json'), 'log_sha256':sha(F/'final-production-assess122.stdout')}},
 'quality_evidence_on_d':{p.name:sha(p) for p in F.glob('final-*') if p.is_file()}}
save(target,report)
A.mkdir()
copies = {}
for source,destination in [(target,A/target.name),(F/'final-production-assess122.stdout',A/'final-production-assess122.log'),(matrix_path,A/'requirements80-current-matrix.json')]:
    shutil.copy2(source,destination)
    assert sha(source) == sha(destination)
    copies[destination.name] = sha(destination)
save(A/'evidence-copy-receipt.json',{'created_at':datetime.now(timezone.utc).isoformat(),'copied_files':copies,'large_evidence_retained_on_d':True})
print(json.dumps({'status':report['status'],'tests':9061,'assertions':143868,'native_decisions':550,
                  'historical_exact_replays':1441,'acceptance_sha256':sha(target),'phase4b_complete':False}),flush=True)
