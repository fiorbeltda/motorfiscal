"""Audit initial112 integration without granting full upstream/CLI/regression acceptance."""
from pathlib import Path
from datetime import datetime,timezone
import hashlib
import json
import re

R=Path(__file__).resolve().parents[1];F=R/'resources/fiscal';BASE=Path(r'D:\ProjetoSaasExports')
E=BASE/'phase4b-price112-integration-20261019';TARGET=E/'price112-initial-directed-validation.json'
assert not TARGET.exists()
def read(p):return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb')as f:return hashlib.file_digest(f,'sha256').hexdigest()
def canonical(v):return hashlib.sha256(json.dumps(v,sort_keys=True,ensure_ascii=False,separators=(',',':')).encode()).hexdigest()
def clean(p):return re.sub(r'\x1b\[[0-9;]*m','',p.read_text(encoding='utf-8',errors='replace'))
checks=[]
def check(v,label):
    assert v,label
    checks.append(label)

archive=read(E/'before-integration-archive.json')
check(archive['accepted108_input_files_verified']==13395,'13395 inputs verified before writes')
A=R/'storage/app/phase4b-dated108-development-20261019/dated108-development-validation.json'
check(sha(A)=='5d7585b577d2b974e2df09062eb8a271c16cb8870ae5952b11d132556a96c53e','immutable108acceptance')
accepted=read(A);pre=read(BASE/'phase4b-dated108-full-suite-20261019/preflight.json')
prior_corpus_path=BASE/'phase4b-dated108-integration-20261019/cli-v1/dated108-cli-validation.json'
check(sha(prior_corpus_path)==accepted['corpus_sha256'],'accepted108CLI receipt preserved')
prior_corpus=read(prior_corpus_path)
prior_replay_count=prior_corpus['exact_replays']+prior_corpus['historical_exact_replays']
check(prior_replay_count==543,'116own plus427historical replays define next historical control scope')
authorized={'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
            'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php'}
authorized_paths={R/p for p in authorized};unchanged=0
for i,(path,digest)in enumerate(pre['input_hashes'].items(),1):
    p=Path(path)
    if p in authorized_paths:
        relative=p.relative_to(R).as_posix();check(sha(E/'before-integration'/relative)==digest,'archived authorized predecessor:'+relative)
    else:check(sha(p)==digest,'protected108input:'+path);unchanged+=1
    if i%3000==0:print(f'Protected108 audit:{i}/13395',flush=True)
check(unchanged==13393,'13393 protected inputs unchanged;2 archived authorized runtime changes')
runtime=None
for label in ['combined-directed-v3','phpstan-v2','pint-v2','production-assess-v2']:
    receipt=read(E/(label+'.json'));check(receipt['exit_code']==0,'terminal gate:'+label)
    check(not receipt['runtime_changed_during_check'],'stable runtime:'+label)
    for channel in ['stdout','stderr']:check(sha(E/(label+'.'+channel))==receipt[channel+'_sha256'],'receipt bytes:'+label+':'+channel)
    if runtime is None:runtime=receipt['runtime_hashes']
    check(receipt['runtime_hashes']==runtime,'same87runtime:'+label)
check(len(runtime)==87,'87 current runtime hashes')
for path,digest in runtime.items():check(sha(R/path)==digest,'current runtime:'+path)
for path,digest in accepted['runtime_hashes'].items():
    if path not in authorized:check(runtime[path]==digest,'old81runtime unchanged:'+path)
check('1342 passed (19170 assertions)' in clean(E/'combined-directed-v3.stdout'),'1342tests19170assertions')
check('[OK] No errors' in clean(E/'phpstan-v2.stdout'),'general phpstan passed')
check(re.search(r'PASS.*580 files',clean(E/'pint-v2.stdout'))is not None,'general pint580files')
assessment=read(E/'production-assess-v2.stdout');command=read(E/'production-assess-v2.json')['command']
check('--controls=resources/fiscal/production/phase4b-controls-2026.10.19-112-draft2.json'in command and '--target=local-validation'in command,'explicit correct controls and local-validation target')
check(assessment['ready']is False and assessment['eligible_for_local_validation']is True,'eligible locally,production blocked')
old_assessment=read(BASE/'phase4b-dated108-full-suite-20261019/final-production-assess108.stdout')
new_blocker='DOCUMENT_PRICE_COMPOSITION_LOCAL_ONLY_NOT_ISS_BASE_FX_OR_ISSUANCE_APPROVAL'
check(set(assessment['blockers'])==set(old_assessment['blockers'])|{new_blocker} and len(assessment['blockers'])==125,'exactly one added blocker,no removed gates')
check(assessment['official_sources_checked']==assessment['official_source_hashes_valid']==94,'94 official source hashes valid')
bundle_path=F/'bundles/consumption-phase4b-2026.10.19-112-draft2.json';bundle=read(bundle_path)
prior=read(F/'bundles/consumption-phase4b-2026.10.19-108-draft3.json')
check(bundle['official_sources'][:76]==prior['official_sources'],'old76 source records preserved')
for row in bundle['official_sources']:check(sha(F/'sources/official'/row['file'])==row['sha256'],'official source:'+row['id'])
for role in ['rtc_rate_catalog','rtc_transition_catalog','municipal_parameters','service_catalog','rules']:
    check(bundle['resource_paths'][role]==prior['resource_paths'][role],'untouched financial resource:'+role)
for role in ['fact_definitions','questions','rtc_code_catalog','scenario_schema']:
    check(canonical(read(F/bundle['resource_paths'][role]))==bundle['content_hashes'][role],'resource content binding:'+role)
for role in ['fact_definitions','questions']:
    current=read(F/bundle['resource_paths'][role]);old=read(F/prior['resource_paths'][role])
    check(current[:len(old)]==old and len(current)==len(old)+7,'exact7typed additions:'+role)
newfacts={r['key']:r for r in read(F/bundle['resource_paths']['fact_definitions'])}
check(newfacts['rtc.currency']['type']=='STRING' and newfacts['rtc.payment_date']['type']=='DATE','explicit extra business fields,not dropped')
controls=read(F/bundle['production_controls']['path'])
check(canonical(controls)==bundle['production_controls']['sha256']==assessment['controls_hash'],'correct controls bound')
check(all(c['state']!='APPROVED' for c in controls['controls']),'no manufactured approvals')
for kind,count in [('110',160),('111',107)]:
    index=read(R/f'tests/Fixtures/price{kind}-native112-index.json')['cases'];check(len(index)==count,'lazy fixtures:'+kind)
    for row in index:check(sha(R/row['path'])==row['sha256'],'native fixture:'+kind+':'+row['name'])
manifest_path=R/'tests/Fixtures/price112-scenario-expectations-v2.json';manifest=read(manifest_path)
check(len(manifest['cases'])==74,'74 initial scenarios')
positives=0;negative_mutations=0;early_controls=0;actual_rtc_currencies=set()
transport={'rtc.assess_export_dated_documents','rtc.assess_export_iss_results','rtc.assess_export_sp_results','rtc.assess_export_dps_identity',
           'provider.iss_regime_proof','provider.iss_regime_proof_records','rtc.assess_export_document_prices',
           'rtc.export_iss_material_records','rtc.export_iss_dated_result_records','rtc.export_dated_dps_identity_records',
           'rtc.export_contract_price_records','rtc.export_contract_price_proof_records',
           'rtc.export_price_composition_records','rtc.export_price_composition_proof_records'}
for row in manifest['cases']:
    check(sha(R/row['path'])==row['scenario_sha256'],'scenario hash:'+row['name'])
    scenario=read(R/row['path']);facts=scenario['facts']
    check(scenario['rule_bundle_id']==bundle['bundle_id'] and scenario['catalog_versions']==bundle['catalog_versions'],'scenario bundle:'+row['name'])
    if row.get('negative_mutation'):negative_mutations+=1;continue
    if row.get('upstream108_path'):
        check(sha(R/row['upstream108_path'])==row['upstream108_sha256'],'original upstream hash:'+row['name'])
        early_controls+=1;continue
    positives+=1;actual_rtc_currencies.add(facts['rtc.gross_currency'])
    check(sha(R/row['source_fixture_path'])==row['source_fixture_sha256'],'source111fixture bytes:'+row['name'])
    fixture=read(R/row['source_fixture_path']);c=fixture['input'];source=c['input110']
    check(canonical(fixture)==row['source_fixture_canonical_sha256'],'source111fixture content:'+row['name'])
    check(facts['rtc.export_contract_price_records']==source['price_records'],'contract prices unchanged:'+row['name'])
    check(facts['rtc.export_price_composition_records']==c['records'],'composition records unchanged:'+row['name'])
    for key,value in source['facts'].items():
        if key not in transport:check(facts[key]==value,'business fact unchanged:'+row['name']+':'+key)
    check(facts['rtc.export_iss_material_records']==source['material_records'] and facts['rtc.export_iss_dated_result_records']==source['result_records'],'material/result proofs unchanged:'+row['name'])
    for current,old in zip(facts['rtc.export_dated_dps_identity_records'],source['identity_records']):
        a=dict(current);b=dict(old);ap=dict(a.pop('proof'));bp=dict(b.pop('proof'))
        for key in ['dated_pair_binding_hash','dated_document_hash','observed_at','source_hash']:ap.pop(key,None);bp.pop(key,None)
        check(a==b and ap==bp,'only synthetic identity binding reissued:'+row['name'])
    check(row['expected_iss_prices']==[d['price_for_iss_evaluation_in_input_currency']for d in fixture['expected_result']['documents']],'independent prepared price oracle:'+row['name'])
check((positives,negative_mutations,early_controls)==(50,16,8),'50prepared positive/16transport/8early controls')
check(actual_rtc_currencies=={'BRL'},'initial positive RTC scope is BRL;declared document currencies are not actual RTC-FX coverage')
upstream=[]
for name in ['dated108-scenario-expectations.json','dated108-upstream-expectations.json']:
    for row in read(R/'tests/Fixtures'/name)['cases']:
        scenario=read(R/row['path']);upstream.append({'name':row['name'],'path':row['path'],'sha256':sha(R/row['path']),
            'status':row['status'],'reason':row['reason'],'dated_status':row['dated_status'],'dated_reason':row['dated_reason'],
            'rtc_gross_currency':scenario['facts'].get('rtc.gross_currency'),
            'already_in112_initial':any(c.get('upstream108_path')==row['path']for c in manifest['cases'])})
check(len(upstream)==58,'complete58source108inventory retained for next expansion')
report={'status':'PRICE112_INITIAL_DIRECTED_VERIFIED_UPSTREAM_CLI_FULL_REGRESSION_PENDING',
 'created_at':datetime.now(timezone.utc).isoformat(),'audit_checks':len(checks),
 'bundle':bundle_path.relative_to(R).as_posix(),'bundle_sha256':sha(bundle_path),
 'new_permanent_tests':438,'directed_with_predecessors_tests':1342,'directed_assertions':19170,
 'initial_engine_scenarios':74,'initial_engine_positive_compositions':50,'actual_rtc_currencies_in_initial_positives':sorted(actual_rtc_currencies),
 'provided_document_currency_is_not_actual_rtc_fx_coverage':True,
 'remaining_original108_scenarios_to_expand':[r for r in upstream if not r['already_in112_initial']],
 'complete_source108_inventory':upstream,'runtime_hashes':runtime,'authorized_changed_old_runtime_paths':sorted(authorized),
 'protected108_inputs_unchanged':unchanged,'acceptance108_sha256':sha(A),
 'quality_receipts':{label:sha(E/(label+'.json'))for label in ['combined-directed-v3','phpstan-v2','pint-v2','production-assess-v2']},
 'official_sources_checked':94,'production_blocker_count':125,
 'current_full_suite_expected_not_executed':7638,'full_suite_complete':False,'corpus_cli_complete':False,
 'full_upstream112_corpus_complete':False,'independent_integration_acceptance_published':False,
 'prior_replay_control_scope':prior_replay_count,'these_prior_replays_executed_again_for112':False,
 'auditor_sha256':sha(Path(__file__)),'production_ready':False}
with TARGET.open('x',encoding='utf-8')as f:json.dump(report,f,indent=2,ensure_ascii=False)
print(json.dumps({k:report[k]for k in ['status','audit_checks','directed_with_predecessors_tests','directed_assertions','protected108_inputs_unchanged']}),flush=True)
