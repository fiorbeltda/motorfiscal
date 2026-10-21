"""Independent122 targeted gate,not a full development acceptance."""
from pathlib import Path
from datetime import datetime,timezone
import hashlib,json,re
R=Path(__file__).resolve().parents[1];E=Path(r'D:\ProjetoSaasExports\phase4b-credit122-preparation-20261021')
checks=0
def read(p):return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb')as f:return hashlib.file_digest(f,'sha256').hexdigest()
def check(ok,label):
    global checks
    assert ok,label
    checks+=1
def gate(label):
    p=E/label;record=read(p/'execution.json');check(record['exit_code']==0,'terminal:'+label)
    for stream in ['stdout','stderr']:check(sha(p/(stream+'.log'))==record[stream+'_sha256'],'bytes:'+label+stream)
    return re.sub(r'\x1b\[[0-9;]*m','',(p/'stdout.log').read_text(encoding='utf-8'))
A=R/'storage/app/phase4b-material119-development-20261020/material119-development-validation.json'
check(sha(A)=='98cb2e92afe2281cc88d94c3a49f155152cc146071a12a5e70cf7511d303cc19','immutable119 acceptance')
old=read(A);archive=read(E/'before-integration-archive.json')
check(len(archive['archived_files'])==95,'95 archived predecessors')
for path,digest in archive['archived_files'].items():check(sha(E/'before-integration'/path)==digest,'archive bytes:'+path)
changed=[];runtime={}
for path,digest in old['runtime_hashes'].items():
    runtime[path]=sha(R/path)
    if runtime[path]!=digest:changed.append(path)
check(changed==['app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php'],'only authorized prior runtime changed')
for path,digest in archive['archived_files'].items():
    if path.startswith('resources/'):check(sha(R/path)==digest,'unchanged historical119 resource:'+path)
for filename in ['CreditInput122.php','CreditInputPolicy122.php']:
    path='app/Modules/Fiscal/ConsumptionReform/'+filename;runtime[path]=sha(R/path)
check(len(runtime)==94,'94 current runtime hashes')
version='2026.10.21-122-draft1';prior='2026.10.20-119-draft1'
bundle_path=R/'resources/fiscal/bundles'/('consumption-phase4b-'+version+'.json')
bundle=read(bundle_path);old_bundle=read(R/'resources/fiscal/bundles'/('consumption-phase4b-'+prior+'.json'))
catalog=read(R/'resources/fiscal'/bundle['resource_paths']['rtc_code_catalog'])
previous=read(R/'resources/fiscal'/old_bundle['resource_paths']['rtc_code_catalog'])
check({k:v for k,v in catalog.items()if k not in ['version','credit_input_policy122']}=={k:v for k,v in previous.items()if k!='version'},'only new policy and catalog version')
check(bundle['official_sources']==old_bundle['official_sources'],'no invented/new official observation')
check(bundle['predecessor_bundle_id']==old_bundle['bundle_id'],'predecessor119')
check(catalog['credit_input_policy122']['source']==previous['material_input_policy119']['source'],'same source117 raw provenance')
for key,value in old_bundle['resource_paths'].items():
    if key!='rtc_code_catalog':check(bundle['resource_paths'][key]==value,'unchanged other resource path:'+key)
for key,value in old_bundle['content_hashes'].items():
    if key!='rtc_code_catalog':check(bundle['content_hashes'][key]==value,'unchanged other resource content:'+key)
for source in bundle['official_sources']:check(sha(R/'resources/fiscal/sources/official'/source['file'])==source['sha256'],'official source:'+source['id'])
check(re.search(r'Tests:\s+187 passed\s+\(546 assertions\)',gate('integrated-directed-v3'))is not None,'187/546 actual directed result')
check('[OK] No errors'in gate('integration-phpstan-v1'),'general PHPStan')
check(re.search(r'PASS.*603 files',gate('integration-pint-v1'))is not None,'general Pint603')
assessment=json.loads(gate('production-assess122-directed-v1'))
check(assessment['ready']is False and assessment['eligible_for_local_validation']is True,'local only,production blocked')
check(assessment['official_sources_checked']==assessment['official_source_hashes_valid']==98,'98 official sources checked')
check(len(assessment['blockers'])==128,'128 production blockers preserved')
report={'created_at':datetime.now(timezone.utc).isoformat(),'status':'CREDIT122_TARGETED_CONTRACTS_INTEGRATED_NOT_FULL_ACCEPTANCE',
    'bundle':str(bundle_path.relative_to(R/'resources/fiscal')).replace('\\','/'),'bundle_sha256':sha(bundle_path),
    'runtime_hashes':runtime,'changed_predecessor_files':changed,'preserved119_acceptance_sha256':sha(A),
    'directed_tests':187,'directed_assertions':546,'new_permanent_tests_since119':163,'expected_total_full_suite_tests':8777,
    'official_sources_valid':98,'production_blockers':128,'independent_checks':checks,
    'native122_corpus_complete':False,'historical_replays_complete':False,'full_suite_complete':False,
    'requirements_reviewed_not_approved':54,'requirements_not_reviewed':26,'formal_phase4b_groups_closed':0,
    'phase4b_complete':False,'production_ready':False}
with(E/'credit122-targeted-validation.json').open('x',encoding='utf-8')as f:json.dump(report,f,ensure_ascii=False,indent=2)
print(json.dumps({k:report[k]for k in ['status','directed_tests','directed_assertions','independent_checks','expected_total_full_suite_tests']}),flush=True)
