"""Native122 credit corpus from preserved120 observations,with own typed snapshots/replays."""
from pathlib import Path
from datetime import datetime,timezone
from decimal import Decimal,ROUND_HALF_UP
import hashlib,json,os,subprocess
R=Path(__file__).resolve().parents[1]
BASE=Path(r'D:\ProjetoSaasExports')
E=BASE/'phase4b-credit122-preparation-20261021';C=E/'credit-cli-v2'
D=r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
TEMP='/tmp/credit122-native-v2';BUNDLE='bundles/consumption-phase4b-2026.10.21-122-draft1.json'
SOURCE=BASE/'phase4b-requirements120-credit-audit-20261020-v1/native-probe-v2/observations.json'
def read(p):return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb')as f:return hashlib.file_digest(f,'sha256').hexdigest()
def canonical(v):return json.dumps(v,sort_keys=True,ensure_ascii=False,separators=(',',':'))
def save(p,v):
    with p.open('x',encoding='utf-8')as f:json.dump(v,f,ensure_ascii=False,indent=2)
def inventory():
    paths=[p for folder in ['app','tests','resources/fiscal']for p in(R/folder).rglob('*')if p.is_file()]
    paths += [Path(__file__),R/'composer.lock',R/'phpunit.xml',SOURCE]
    return{str(p):sha(p)for p in sorted(set(paths))}
def body(result,runner):return result if runner=='rtc'else next((x['payload']for x in result['trace']if x['type']=='CONSUMPTION_REFORM'),None)
def run(label,args,expected=0):
    command=[D,'compose','exec','-T','app','php','-d','memory_limit=512M','artisan',*args]
    start=datetime.now(timezone.utc).isoformat();p=subprocess.run(command,cwd=R,capture_output=True)
    record={'command':command,'started_at':start,'finished_at':datetime.now(timezone.utc).isoformat(),'exit_code':p.returncode,'expected_exit_code':expected}
    for stream in ['stdout','stderr']:
        with(C/(label+'.'+stream)).open('xb')as f:f.write(getattr(p,stream))
        record[stream+'_sha256']=sha(C/(label+'.'+stream))
    save(C/(label+'-execution.json'),record)
    assert p.returncode==expected,(label,p.stdout[-1400:],p.stderr[-1400:])
    return json.loads(p.stdout)
assert not C.exists(),'Exclusive credit122 native capture;do not restart live attempts'
bundle=read(R/'resources/fiscal'/BUNDLE)
review120=BASE/'phase4b-requirements120-credit-audit-20261020-v1/requirements-review-v2/requirements120-credit-review.json'
assert sha(review120)=='6ace1114457b31422e0c0c29e4adab7d0d5abeaa1168cc01235a071148718ab9'
assert sha(SOURCE)==read(review120)['native_observations_sha256'],'audited120 native observation bytes'
original=read(SOURCE)['cases'];assert len(original)==74
cases=[{'origin':'120','source':row}for row in original]
changes={name:('INVALID','RTC_CREDIT122_AMOUNT_NOT_EXACT')for name in ['adjustment-boolean-true','adjustment-boolean-false','adjustment-binary-number','adjustment-empty','adjustment-blank']}
changes.update({name:('ACTION_REQUIRED','RTC_CREDIT122_EVIDENCE_REQUIRED')for name in ['adjustment-evidence-empty','adjustment-evidence-blank']})
changes.update({'adjustment-identical-rows':('ACTION_REQUIRED','RTC_CREDIT122_LINE_IDENTITY_AMBIGUOUS'),
    'input-document-whitespace-with-aligned-treatments':('ACTION_REQUIRED','RTC_CREDIT122_DOCUMENT_ID_REQUIRED'),
    'input-document-missing':('ACTION_REQUIRED','RTC_CREDIT122_DOCUMENT_ID_REQUIRED')})
control=next(row for row in original if row['name']=='adjustment-decimal')
def extra(name,rows,status=None,reason=None,amounts=None):
    row=json.loads(json.dumps(control));row['name']=name;row['input']['facts']['rtc.credit_adjustments']=rows
    cases.append({'origin':'NEW122','source':row,'status':status,'reason':reason,'amounts':amounts})
line={'tax':'IBS','kind':'REVERSAL','amount':'0.25','evidence_id':'SYNTHETIC-122-CREDIT'}
for name,amount in [('binary-integral',1.0),('exponent','1e-2'),('plus','+1'),('comma','0,25')]:
    extra(name,[{**line,'amount':amount}],'INVALID','RTC_CREDIT122_AMOUNT_NOT_EXACT')
extra('two-explicit-lines',[{**line,'line_reference':'1'},{**line,'line_reference':'2'}],amounts=['1.50','18.00'])
extra('same-trimmed-lines',[{**line,'line_reference':'1'},{**line,'line_reference':' 1 '}],'ACTION_REQUIRED','RTC_CREDIT122_LINE_IDENTITY_AMBIGUOUS')
extra('unrelated-document-fields',[{**line,'document_id':'A'},{**line,'document_id':'B'}],'ACTION_REQUIRED','RTC_CREDIT122_LINE_IDENTITY_AMBIGUOUS')
extra('same-evidence-other-kind',[line,{**line,'kind':'DISALLOWANCE'}],amounts=['1.50','18.00'])
extra('same-evidence-other-tax',[line,{**line,'tax':'CBS'}],amounts=['1.75','17.75'])
extra('invalid-explicit-line',[{**line,'line_reference':' '}],'ACTION_REQUIRED','RTC_CREDIT122_LINE_REFERENCE_INVALID')
extra('exact-zero-adjustment',[{**line,'amount':0}],amounts=['2.00','18.00'])
import_control=next(row for row in original if row['name']=='import-modern-proven')
for key in ['rtc.import_document_id','rtc.import_credit_fiscal_document_id','rtc.import_credit_extinction_evidence_id']:
    row=json.loads(json.dumps(import_control));row['name']='blank-'+key.split('.')[-1];row['input']['facts'][key]=' '
    cases.append({'origin':'NEW122','source':row,'status':'ACTION_REQUIRED','reason':'RTC_CREDIT122_IMPORT_IDENTIFIER_INVALID'})
for name,template,ibs,cbs in [
    ('negative-input-domestic',control,'-2.00','-18.00'),
    ('negative-input-import',import_control,'-1.00','-9.00'),
]:
    row=json.loads(json.dumps(template));row['name']=name
    row['input']['facts']['rtc.input_ibs_amount']=ibs;row['input']['facts']['rtc.input_cbs_amount']=cbs
    cases.append({'origin':'QUESTION_FIX122','source':row,'status':'ACTION_REQUIRED','reason':'RTC_NEGATIVE_INPUT_CREDIT'})
assert len(cases)==90
question_cut=read(E/'question-fix-v2/question-fix-directed-validation.json')
assert question_cut['tests']==197 and question_cut['assertions']==698
for relative,digest in question_cut['runtime_hashes'].items():assert sha(R/relative)==digest,relative
correction_facts={
    'RTC_CREDIT122_EVIDENCE_REQUIRED':['rtc.credit_adjustments'],
    'RTC_CREDIT122_LINE_REFERENCE_INVALID':['rtc.credit_adjustments'],
    'RTC_CREDIT122_LINE_IDENTITY_AMBIGUOUS':['rtc.credit_adjustments'],
    'RTC_CREDIT_ADJUSTMENT_UNPROVEN':['rtc.credit_adjustments'],
    'RTC_CREDIT_TAX_UNKNOWN':['rtc.credit_adjustments'],
    'RTC_NEGATIVE_CREDIT_ADJUSTMENT':['rtc.credit_adjustments'],
    'RTC_CREDIT_ADJUSTMENT_EXCEEDS_AVAILABLE':['rtc.credit_adjustments'],
    'RTC_NEGATIVE_INPUT_CREDIT':['rtc.input_ibs_amount','rtc.input_cbs_amount'],
}
question_definitions={q['fact_key']:q for q in read(R/'resources/fiscal'/bundle['resource_paths']['questions'])}
correction_checks=0
before=inventory();C.mkdir()
save(C/'preflight.json',{'created_at':datetime.now(timezone.utc).isoformat(),'input_hashes':before,'source120_sha256':sha(SOURCE),'planned_cases':90,'planned_decisions':180,'planned_exact_replays':180,'runtime_hashes':question_cut['runtime_hashes']})
subprocess.run([D,'compose','exec','-T','app','mkdir','-p',TEMP],cwd=R,check=True)
cid=subprocess.run([D,'compose','ps','-q','app'],cwd=R,capture_output=True,text=True,check=True).stdout.strip()
save(C/'process-start.json',{'host_python_pid':os.getpid(),'collector_sha256':sha(Path(__file__)),'container':cid})
records=[];decimal_checks=0
for index,case in enumerate(cases):
    old=case['source'];name=old['name'];scenario=json.loads(json.dumps(old['input']))
    if case['origin']=='120':assert sha(R/'resources/fiscal'/old['path'])==old['source_sha256'],'preserved source fixture:'+name
    scenario['scenario_id']='CREDIT122-'+name;scenario['knowledge_at']='2026-10-21T11:10:00.000000+00:00'
    scenario['rule_bundle_id']=bundle['bundle_id'];scenario['catalog_versions']=bundle['catalog_versions']
    local_input=C/(f'{index:03d}-scenario.json');save(local_input,scenario);remote_input=TEMP+'/'+local_input.name
    subprocess.run([D,'cp',str(local_input),cid+':'+remote_input],cwd=R,capture_output=True,check=True)
    record={'name':name,'origin':case['origin'],'scenario':local_input.name,'scenario_sha256':sha(local_input),'previous_exception_runners':[r for r in ['rtc','integrated']if r+'_exception'in old]}
    for runner,command,replay_command in [('rtc','fiscal:rtc:run','fiscal:rtc:replay'),('integrated','fiscal:scenario:run','fiscal:decision:replay')]:
        status,reason=changes.get(name,(case.get('status'),case.get('reason')))
        if status is None:status=old[runner]['status'];reason=old[runner]['reason_codes'][0]
        label=f'{index:03d}-{runner}';remote=TEMP+'/'+label+'-snapshot.json'
        result=run(label,[command,remote_input,'--bundle-manifest='+BUNDLE,'--snapshot='+remote],1 if runner=='rtc'and status=='INVALID'else 0)
        assert result['status']==status and reason in result['reason_codes'],(name,runner,result['status'],result['reason_codes'],status,reason)
        rtc=body(result,runner)
        if status=='ACTION_REQUIRED' and reason in correction_facts:
            required=correction_facts[reason]
            assert result['missing_facts']==required,(name,runner,result['missing_facts'])
            questions={q['fact_key']:q for q in result['questions']}
            for key in required:
                assert key in questions and questions[key]['prompt']==question_definitions[key]['prompt']
                assert questions[key]['answer_type']==question_definitions[key]['answer_type']
                correction_checks+=3
            assert rtc and rtc['missing_facts']==required
            correction_checks+=1
        if rtc and isinstance(rtc.get('decision'),dict):
            decision=rtc['decision'];facts=scenario['facts']
            for tax,key in [('IBS','ibs'),('CBS','cbs')]:
                credit=decision.get(key,{}).get('credit',{})
                if credit.get('status')=='ALLOWED':
                    expected=Decimal(facts['rtc.input_ibs_amount'if tax=='IBS'else'rtc.input_cbs_amount'])
                    for row in facts.get('rtc.credit_adjustments',[]):
                        if row['tax']==tax:expected-=Decimal(str(row['amount']))
                    assert Decimal(credit['amount'])==expected.quantize(Decimal('.01'),rounding=ROUND_HALF_UP)
                    if case.get('amounts'):assert credit['amount']==case['amounts'][0 if tax=='IBS'else 1]
                    decimal_checks+=1
                elif credit.get('status')in ['DENIED','CONDITIONAL']:
                    assert Decimal(credit['amount'])==0;decimal_checks+=1
        replay=run(label+'-replay',[replay_command,remote]);assert replay['replay_matches']is True
        if runner=='integrated':assert canonical(replay['result'])==canonical(result)
        snapshot=C/(label+'-snapshot.json');subprocess.run([D,'cp',cid+':'+remote,str(snapshot)],cwd=R,capture_output=True,check=True)
        captured=read(snapshot);assert canonical(captured['input'])==canonical(scenario)and canonical(captured['result'])==canonical(result),'typed snapshot fidelity:'+name
        record[runner]={'status':status,'reason':reason,'snapshot':snapshot.name,'snapshot_sha256':sha(snapshot),'exact_replay':True}
    records.append(record);print(f'PASS:credit122 {index+1}/90 {name};two native decisions and exact replays',flush=True)
assert inventory()==before,'Frozen122 credit capture inputs changed'
save(C/'credit122-cli-validation.json',{'created_at':datetime.now(timezone.utc).isoformat(),'status':'CREDIT122_NATIVE_INPUT_CONTRACTS_AND_REPLAYS_VERIFIED','bundle':BUNDLE,'bundle_sha256':sha(R/'resources/fiscal'/BUNDLE),'cases':records,'native_decisions':180,'exact_replays':180,'correction_question_checks':correction_checks,'runtime_hashes':question_cut['runtime_hashes'],'prior_credit_cli_v1_preserved':True,'independent_decimal_checks':decimal_checks,'former_exception_invocations_controlled':6,'input_inventory_unchanged':True,'full_suite_complete':False,'phase4b_complete':False,'production_ready':False})
print('PASS:180 native decisions and180 exact replays;full122 regression pending',flush=True)
