"""Publish112 only after independent terminal audit; no legal/production approval."""
from datetime import datetime, timezone
import re
import shutil
from verify_phase4b_price112_evidence import ROOT, E, C, F, A108, read, sha, clean, gate, verify, inputs

def save(path,value):
    with path.open('x',encoding='utf-8') as f:
        import json
        json.dump(value,f,indent=2,ensure_ascii=False)

A = ROOT/'storage/app/phase4b-price112-development-20261019'
target = F/'price112-development-validation.json'
assert not A.exists() and not target.exists(), 'Exclusive acceptance; do not overwrite prior attempt'
corpus,bundle,audit = verify()
runtime = audit['runtime_hashes']
regression = read(F/'price112-regression-validation.json')
preflight = read(F/'preflight.json'); suite = read(F/'full-suite.json')
assert regression['status'] == 'PRICE112_LOCAL_INTEGRATION_REGRESSION_VERIFIED'
assert regression['full_suite'] == suite and suite['exit_code'] == 0 and suite['full_suite_complete'] is True
assert suite['tests'] == 7738 and isinstance(suite['assertions'],int) and suite['assertions'] > 107350
assert not suite['input_files_changed'] and not suite['input_files_added'] and not suite['input_files_removed']
assert sha(F/'full-suite.log') == suite['log_sha256']
assert re.search(r'Tests:\s+7738 passed\s+\('+str(suite['assertions'])+r' assertions\)',clean(F/'full-suite.log'))
assert inputs() == preflight['input_hashes']
assert regression['unchanged_input_files'] == len(preflight['input_hashes']) > 13395
assert regression['runtime_hashes'] == preflight['runtime_hashes'] == runtime
assert preflight['independent_cli_audit_checks'] == regression['independent_cli_audit_checks'] == audit['audit_checks']
assert preflight['independent_decimal_checks'] == regression['independent_decimal_checks'] == audit['independent_decimal_checks']
assert sha(C/'price112-cli-validation.json') == preflight['corpus_sha256']
for label in ['final-phpstan','final-pint','final-production-assess112']: gate(F,label)
assert '[OK] No errors' in clean(F/'final-phpstan.stdout')
assert re.search(r'PASS.*581 files',clean(F/'final-pint.stdout'))
assessment = read(F/'final-production-assess112.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 94
assert len(assessment['blockers']) == 125 and set(assessment['blockers']) == set(read(C/'production-assess112.stdout')['blockers'])
quality = dict(read(F/'final-production-assess112.json')); quality['log_sha256'] = quality['stdout_sha256']
report = {**regression,'status':'PRICE112_LOCAL_DOCUMENT_PRICE_COMPOSITION_INTEGRATION_DEVELOPMENT_VERIFIED',
    'created_at':datetime.now(timezone.utc).isoformat(),'previous108_acceptance_sha256':sha(A108),
    'scope':'LOCAL_SYNTHETIC_PRICE_AND_DISCOUNT_COMPOSITION_NOT_ISS_BASE_FX_LEGAL_APPLICABILITY_OR_ISSUANCE',
    'bundle_id':bundle['bundle_id'],'scenarios':124,'prior_exact_replays':543,
    'new_permanent_tests':538,'directed_tests':1442,'directed_assertions':21844,
    'independent_terminal_audit_checks':audit['audit_checks'],'independent_decimal_checks':audit['independent_decimal_checks'],
    'full_captured108_nominal_and_fx_comparisons':232,'actual_rtc_usd_composition_decisions':22,
    'corpus_sha256':sha(C/'price112-cli-validation.json'),'regression_preflight_sha256':sha(F/'preflight.json'),
    'regression_validation_sha256':sha(F/'price112-regression-validation.json'),
    'auditor_sha256':sha(ROOT/'scripts/finish_phase4b_price112_development.py'),
    'verifier_sha256':sha(ROOT/'scripts/verify_phase4b_price112_evidence.py'),
    'final_quality':{'price112-final-production-assess':quality},
    'quality_evidence_on_d':{p.name:sha(p) for p in F.glob('final-*') if p.is_file()},
    'remote_deployment_proven':False,'government_evidence_authenticated':False,'payment_receipt_proven':False,
    'fiscal_identifiers_qualified':False,'posting_authorized':False}
assert sha(A108) == '5d7585b577d2b974e2df09062eb8a271c16cb8870ae5952b11d132556a96c53e'
save(target,report)
A.mkdir()
copied = {}
for source,destination in [(target,A/target.name),(F/'final-production-assess112.stdout',A/'price112-final-production-assess.log')]:
    shutil.copy2(source,destination)
    assert sha(source) == sha(destination)
    copied[destination.name] = sha(destination)
save(A/'evidence-copy-receipt.json',{'created_at':datetime.now(timezone.utc).isoformat(),'copied_files':copied,'large_evidence_retained_on_d':True})
assert sha(A108) == report['previous108_acceptance_sha256']
print(str({'status':report['status'],'tests':suite['tests'],'assertions':suite['assertions'],
    'runtime_hashes':87,'acceptance_sha256':sha(A/target.name),'production_ready':False}),flush=True)
