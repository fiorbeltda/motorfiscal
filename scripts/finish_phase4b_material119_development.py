"""Audit and publish scoped119 development acceptance;not phase4B or production closure."""
from datetime import datetime,timezone
import json
import re
import shutil
from capture_phase4b_material119_full_suite import R,BASE,E,C,F,A112,REVIEW120,inputs,read,sha,clean
from verify_phase4b_material119_postfix_evidence_v2 import verify,gate
from verify_phase4b_material119_short_postfix_evidence import verify as verify_short

A=R/'storage/app/phase4b-material119-development-20261020'
target=F/'material119-development-validation.json'
assert not A.exists()and not target.exists(),'Exclusive development acceptance'
corpus,bundle,audit=verify();short=verify_short()
suite=read(F/'full-suite.json');regression=read(F/'material119-regression-validation.json');preflight=read(F/'preflight.json')
assert suite['exit_code']==0 and suite['full_suite_complete']is True and suite['tests']==8614
assert isinstance(suite['assertions'],int)and suite['assertions']>116599
assert regression['full_suite']==suite and regression['full_suite_complete']is True
assert sha(F/'full-suite.log')==suite['log_sha256']
assert re.search(r'Tests:\s+8614 passed\s+\('+str(suite['assertions'])+r' assertions\)',clean(F/'full-suite.log'))
assert not suite['input_files_changed']and not suite['input_files_added']and not suite['input_files_removed']
assert inputs()==preflight['input_hashes']
assert audit['runtime_hashes']==short['runtime_hashes']==preflight['runtime_hashes']==regression['runtime_hashes']
assert len(audit['runtime_hashes'])==92
assert sha(C/'material119-expanded-cli-validation.json')==preflight['corpus_sha256']
assert sha(E/'material119-short-validation.json')==preflight['short_validation_sha256']
assert sha(E/'material119-snapshot-fidelity-validation.json')==preflight['snapshot_fix_sha256']
assert sha(REVIEW120)==preflight['requirements120_review_sha256']
credit=read(REVIEW120)
assert len(credit['cumulative_unique_reviewed_ids'])==54 and credit['cumulative_not_reviewed_count']==26 and credit['formal_requirements_closed']==0
assert credit['native_runner_exceptions']==6 and len(credit['confirmed_accepted_malformed_input_cases'])==5
for label in ['final-phpstan','final-pint','final-production-assess119']:gate(F,label)
assert '[OK] No errors'in clean(F/'final-phpstan.stdout')
assert re.search(r'PASS.*597 files',clean(F/'final-pint.stdout'))
assessment=read(F/'final-production-assess119.stdout')
assert assessment['ready']is False and assessment['eligible_for_local_validation']is True
assert assessment['official_sources_checked']==assessment['official_source_hashes_valid']==98 and len(assessment['blockers'])==127
quality=dict(read(F/'final-production-assess119.json'));quality['log_sha256']=quality['stdout_sha256']
report={**regression,'status':'MATERIAL119_LOCAL_INPUT_GUARDS_AND_SNAPSHOT_FIDELITY_DEVELOPMENT_VERIFIED',
    'created_at':datetime.now(timezone.utc).isoformat(),'bundle_id':bundle['bundle_id'],
    'scope':'EXACT_ORDINARY_BASE_ADJUSTMENT_INPUTS_AND_COLLECTION_REFERENCES_PLUS_JSON_SNAPSHOT_TYPE_FIDELITY_NOT_COMPLETE_CREDIT_CONTRACTS_OR_PHASE4B',
    'previous112_acceptance_sha256':sha(A112),'scenarios':185,'native_decisions':370,'exact_replays':370,'prior_exact_replays':1071,
    'new_permanent_tests_since112':876,'directed_tests_before_codec':599,'directed_assertions_before_codec':9439,
    'snapshot_fidelity_tests':28,'snapshot_fidelity_assertions':188,
    'locally_composed_price_decisions':142,'literal_lifecycle_refusals':4,'actual_rtc_usd_composition_decisions':22,
    'full_captured108_nominal_and_fx_comparisons':232,
    'independent_terminal_audit_checks':audit['audit_checks']+short['audit_checks'],
    'requirements_review120_sha256':sha(REVIEW120),'requirements_reviewed_not_approved':54,'requirements_not_reviewed':26,
    'known_open_credit120_findings':credit['confirmed_accepted_malformed_input_cases'],
    'known_credit120_exception_invocations':6,'known_credit120_findings_fixed':False,
    'formal_phase4b_groups_closed_by_this_report':0,'document_obligation_complete':False,
    'corpus_sha256':sha(C/'material119-expanded-cli-validation.json'),'short_validation_sha256':sha(E/'material119-short-validation.json'),
    'snapshot_fidelity_validation_sha256':sha(E/'material119-snapshot-fidelity-validation.json'),
    'regression_preflight_sha256':sha(F/'preflight.json'),'regression_validation_sha256':sha(F/'material119-regression-validation.json'),
    'auditor_sha256':sha(R/'scripts/finish_phase4b_material119_development.py'),
    'verifier_sha256':sha(R/'scripts/verify_phase4b_material119_postfix_evidence_v2.py'),
    'final_quality':{'material119-final-production-assess':quality},
    'quality_evidence_on_d':{p.name:sha(p)for p in F.glob('final-*')if p.is_file()},
    'government_evidence_authenticated':False,'payment_receipt_proven':False,'posting_authorized':False,
    'phase4b_complete':False,'production_ready':False}
assert sha(A112)=='60acd38875bb50cf77f625951f21f5b5eee503f9c857d6aaa906f83590684034'
def save(p,v):
    with p.open('x',encoding='utf-8')as f:json.dump(v,f,ensure_ascii=False,indent=2)
save(target,report);A.mkdir();copied={}
for source,dest in [(target,A/target.name),(F/'final-production-assess119.stdout',A/'material119-final-production-assess.log')]:
    shutil.copy2(source,dest);assert sha(source)==sha(dest);copied[dest.name]=sha(dest)
save(A/'evidence-copy-receipt.json',{'created_at':datetime.now(timezone.utc).isoformat(),'copied_files':copied,'large_evidence_retained_on_d':True})
print(json.dumps({'status':report['status'],'tests':suite['tests'],'assertions':suite['assertions'],'runtime_hashes':92,
    'acceptance_sha256':sha(A/target.name),'phase4b_complete':False,'production_ready':False}),flush=True)
