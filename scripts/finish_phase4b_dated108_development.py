"""Independent terminal audit and small verified copies; never approves fiscal issuance."""
from datetime import datetime, timezone
import re
import shutil
from verify_phase4b_dated108_evidence import ROOT, E, C, F, A104, read, sha, save, clean, gate, verify, inputs

A = ROOT / 'storage/app/phase4b-dated108-development-20261019'
target = F / 'dated108-development-validation.json'
assert not A.exists() and not target.exists(), 'Exclusive acceptance'
corpus, bundle, runtime, old_inputs = verify()
proof = read(F / 'dated108-regression-validation.json')
preflight = read(F / 'preflight.json')
suite = read(F / 'full-suite.json')
assert proof['status'] == 'DATED108_LOCAL_INTEGRATION_REGRESSION_VERIFIED'
assert proof['full_suite'] == suite and suite['exit_code'] == 0 and suite['full_suite_complete'] is True
assert suite['tests'] == 7200 and isinstance(suite['assertions'], int) and suite['assertions'] > 98175
assert not suite['input_files_changed'] and not suite['input_files_added'] and not suite['input_files_removed']
assert sha(F / 'full-suite.log') == suite['log_sha256']
assert re.search(r'Tests:\s+7200 passed\s+\(' + str(suite['assertions']) + r' assertions\)', clean(F / 'full-suite.log'))
assert inputs(old_inputs, bundle) == preflight['input_hashes']
assert proof['unchanged_input_files'] == len(preflight['input_hashes']) > 11133
assert proof['runtime_hashes'] == preflight['runtime_hashes'] == runtime
assert sha(C / 'dated108-cli-validation.json') == preflight['corpus_sha256']
for label in ['final-phpstan', 'final-pint', 'final-production-assess108']:
    gate(F, label)
assert '[OK] No errors' in clean(F / 'final-phpstan.stdout')
assert re.search(r'PASS.*572 files', clean(F / 'final-pint.stdout'))
assessment = read(F / 'final-production-assess108.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_source_hashes_valid'] == assessment['official_sources_checked'] == 76
assert set(assessment['blockers']) == set(read(C / 'production-assess108.stdout')['blockers']) and len(assessment['blockers']) == 124
quality = dict(read(F / 'final-production-assess108.json'))
quality['log_sha256'] = quality['stdout_sha256']
report = {**proof, 'status': 'DATED108_LOCAL_DATED_COMPOSITION_INTEGRATION_DEVELOPMENT_VERIFIED',
    'created_at': datetime.now(timezone.utc).isoformat(), 'previous104_acceptance_sha256': sha(A104),
    'scope': 'LOCAL_SYNTHETIC_MATERIAL_DATE_RESULT_AND_DPS_COMPETENCE_BINDING_NOT_LEGAL_APPLICABILITY_OR_ISSUANCE',
    'bundle_id': bundle['bundle_id'], 'scenarios': 58, 'prior_exact_replays': 427,
    'new_permanent_tests': 671, 'directed_tests': 904, 'directed_assertions': 12595,
    'nominal_controls_equal_to_captured104': corpus['nominal_controls_equal_to_captured104'], 'independent_decimal_checks': 72,
    'independent_upstream_captured104_controls': corpus['independent_upstream_captured104_controls'],
    'corpus_sha256': sha(C / 'dated108-cli-validation.json'), 'regression_preflight_sha256': sha(F / 'preflight.json'),
    'regression_validation_sha256': sha(F / 'dated108-regression-validation.json'),
    'auditor_sha256': sha(ROOT / 'scripts/finish_phase4b_dated108_development.py'),
    'verifier_sha256': sha(ROOT / 'scripts/verify_phase4b_dated108_evidence.py'),
    'final_quality': {'dated108-final-production-assess': quality},
    'quality_evidence_on_d': {p.name: sha(p) for p in F.glob('final-*') if p.is_file()},
    'remote_deployment_proven': False, 'legal_applicability_approved': False, 'posting_authorized': False}
save(target, report)
A.mkdir()
receipt = {}
for source, destination in [(target, A / target.name), (F / 'final-production-assess108.stdout', A / 'dated108-final-production-assess.log')]:
    shutil.copy2(source, destination)
    assert sha(source) == sha(destination)
    receipt[destination.name] = sha(destination)
save(A / 'evidence-copy-receipt.json', {'created_at': datetime.now(timezone.utc).isoformat(), 'copied_files': receipt, 'large_evidence_retained_on_d': True})
assert sha(A104) == report['previous104_acceptance_sha256']
print(str({'status': report['status'], 'tests': suite['tests'], 'assertions': suite['assertions'],
    'runtime_hashes': 83, 'acceptance_sha256': sha(A / target.name), 'production_ready': False}), flush=True)
