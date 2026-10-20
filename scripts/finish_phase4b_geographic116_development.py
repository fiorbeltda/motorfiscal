"""Publish116 only after independent terminal audit; no legal/production approval."""
from datetime import datetime, timezone
import re
import shutil
from verify_phase4b_geographic116_expanded_evidence import ROOT, E, C, F, A112, REVIEW117, read, sha, clean, gate, verify, inputs
from verify_phase4b_geographic116_evidence import verify as verify_geographic_paths

def save(path,value):
    with path.open('x',encoding='utf-8') as f:
        import json
        json.dump(value,f,indent=2,ensure_ascii=False)

A = ROOT/'storage/app/phase4b-geographic116-development-20261020'
target = F/'geographic116-development-validation.json'
assert not A.exists() and not target.exists(), 'Exclusive acceptance; do not overwrite prior attempt'
corpus,bundle,audit = verify()
runtime = audit['runtime_hashes']
directed_geography = verify_geographic_paths()
assert directed_geography['native_decisions'] == directed_geography['exact_replays'] == 32
assert directed_geography['runtime_hashes'] == runtime

regression = read(F/'geographic116-regression-validation.json')
preflight = read(F/'preflight.json'); suite = read(F/'full-suite.json')
assert regression['status'] == 'GEOGRAPHIC116_LOCAL_INTEGRATION_REGRESSION_VERIFIED'
assert regression['full_suite'] == suite and suite['exit_code'] == 0 and suite['full_suite_complete'] is True
assert suite['tests'] == 8083 and isinstance(suite['assertions'],int) and suite['assertions'] > 116599
assert not suite['input_files_changed'] and not suite['input_files_added'] and not suite['input_files_removed']
assert sha(F/'full-suite.log') == suite['log_sha256']
assert re.search(r'Tests:\s+8083 passed\s+\('+str(suite['assertions'])+r' assertions\)',clean(F/'full-suite.log'))
assert inputs() == preflight['input_hashes']
assert regression['unchanged_input_files'] == len(preflight['input_hashes']) > 17418
assert regression['runtime_hashes'] == preflight['runtime_hashes'] == runtime
assert preflight['independent_cli_audit_checks'] == regression['independent_cli_audit_checks'] == audit['audit_checks']
assert preflight['independent_decimal_checks'] == regression['independent_decimal_checks'] == audit['independent_decimal_checks']
assert sha(C/'geographic116-expanded-cli-validation.json') == preflight['corpus_sha256']
for label in ['final-phpstan','final-pint','final-production-assess116']: gate(F,label)
assert '[OK] No errors' in clean(F/'final-phpstan.stdout')
assert re.search(r'PASS.*587 files',clean(F/'final-pint.stdout'))
assessment = read(F/'final-production-assess116.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 97
assert len(assessment['blockers']) == 126 and set(assessment['blockers']) == set(read(C/'production-assess116-expanded.stdout')['blockers'])
quality = dict(read(F/'final-production-assess116.json')); quality['log_sha256'] = quality['stdout_sha256']
subjects_review = read(REVIEW117)
assert len(subjects_review['cumulative114_115_117_unique_reviewed_ids']) == 30
assert subjects_review['formal_requirements_closed'] == 0 and len(subjects_review['confirmed_material_basis_counterexamples']) == 3
report = {**regression,'status':'GEOGRAPHIC116_LOCAL_REFERENCE_AND_PRICE_TRANSPORT_INTEGRATION_DEVELOPMENT_VERIFIED',
    'created_at':datetime.now(timezone.utc).isoformat(),'previous112_acceptance_sha256':sha(A112),
    'scope':'OBSERVED_GEOGRAPHIC_REFERENCE_CONSISTENCY_AND_REBOUND_SYNTHETIC_PRICES_NOT_HISTORICAL_FISCAL_LOCATION_ISS_BASE_FX_OR_ISSUANCE',
    'bundle_id':bundle['bundle_id'],'scenarios':140,'prior_exact_replays':791,
    'native_decisions':280,'exact_replays':280,'expanded_price_native_decisions':248,'directed_geography_native_decisions':32,
    'new_permanent_tests':345,'directed_tests':593,'directed_assertions':14934,
    'literal_lifecycle_refusals':4,'locally_composed_price_decisions':142,'historical_fiscal_destination_proven':False,
    'requirements_review117_sha256':sha(REVIEW117),'requirements_reviewed_not_approved':30,'requirements_not_reviewed_in_these_cuts':50,
    'known_open_material_collection_basis_findings':subjects_review['confirmed_material_basis_counterexamples'],
    'collection_basis_findings_fixed':False,'formal_phase4b_groups_closed_by_this_report':0,
    'independent_terminal_audit_checks':audit['audit_checks'],'independent_decimal_checks':audit['independent_decimal_checks'],
    'full_captured108_nominal_and_fx_comparisons':232,'actual_rtc_usd_composition_decisions':22,
    'corpus_sha256':sha(C/'geographic116-expanded-cli-validation.json'),'regression_preflight_sha256':sha(F/'preflight.json'),
    'regression_validation_sha256':sha(F/'geographic116-regression-validation.json'),
    'auditor_sha256':sha(ROOT/'scripts/finish_phase4b_geographic116_development.py'),
    'verifier_sha256':sha(ROOT/'scripts/verify_phase4b_geographic116_expanded_evidence.py'),
    'final_quality':{'geographic116-final-production-assess':quality},
    'quality_evidence_on_d':{p.name:sha(p) for p in F.glob('final-*') if p.is_file()},
    'remote_deployment_proven':False,'government_evidence_authenticated':False,'payment_receipt_proven':False,
    'fiscal_identifiers_qualified':False,'posting_authorized':False}
assert sha(A112) == '60acd38875bb50cf77f625951f21f5b5eee503f9c857d6aaa906f83590684034'
save(target,report)
A.mkdir()
copied = {}
for source,destination in [(target,A/target.name),(F/'final-production-assess116.stdout',A/'geographic116-final-production-assess.log')]:
    shutil.copy2(source,destination)
    assert sha(source) == sha(destination)
    copied[destination.name] = sha(destination)
save(A/'evidence-copy-receipt.json',{'created_at':datetime.now(timezone.utc).isoformat(),'copied_files':copied,'large_evidence_retained_on_d':True})
assert sha(A112) == report['previous112_acceptance_sha256']
print(str({'status':report['status'],'tests':suite['tests'],'assertions':suite['assertions'],
    'runtime_hashes':88,'acceptance_sha256':sha(A/target.name),'production_ready':False}),flush=True)
