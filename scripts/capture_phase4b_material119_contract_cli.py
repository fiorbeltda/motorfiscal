"""Reproduce117/118 witnesses through both native119 CLIs and exact replays."""
from pathlib import Path
from datetime import datetime, timezone
from decimal import Decimal, ROUND_HALF_UP
import hashlib
import json
import os
import subprocess

R = Path(__file__).resolve().parents[1]
BASE = Path(r'D:\ProjetoSaasExports')
E = BASE/'phase4b-material-input119-integration-20261020'
C = E/'contract-cli-v1'
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
TEMP = '/tmp/material119-contract-cli-v1'
BUNDLE = 'bundles/consumption-phase4b-2026.10.20-119-draft1.json'
def read(p): return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb') as f: return hashlib.file_digest(f,'sha256').hexdigest()
def save(p,v):
    with p.open('x',encoding='utf-8') as f: json.dump(v,f,ensure_ascii=False,indent=2)
def inventory():
    paths = [p for folder in ['app','tests','resources/fiscal'] for p in (R/folder).rglob('*') if p.is_file()]
    paths += [Path(__file__),R/'composer.lock',R/'phpunit.xml']
    return {str(p):sha(p) for p in sorted(set(paths))}
def body(result,runner):
    if runner == 'rtc': return result
    return next((x['payload'] for x in result['trace'] if x['type']=='CONSUMPTION_REFORM'),None)
def run(label,args,expected=0):
    command = [D,'compose','exec','-T','app','php','-d','memory_limit=512M','artisan',*args]
    start = datetime.now(timezone.utc).isoformat()
    p = subprocess.run(command,cwd=R,capture_output=True)
    record = {'command':command,'started_at':start,'finished_at':datetime.now(timezone.utc).isoformat(),
              'exit_code':p.returncode,'expected_exit_code':expected}
    for stream in ['stdout','stderr']:
        with (C/(label+'.'+stream)).open('xb') as f: f.write(getattr(p,stream))
        record[stream+'_sha256'] = sha(C/(label+'.'+stream))
    save(C/(label+'-execution.json'),record)
    assert p.returncode == expected,(label,p.stdout[-1400:],p.stderr[-1400:])
    return json.loads(p.stdout)

assert not C.exists(),'Exclusive native contract capture;never overwrite or restart a live attempt'
bundle = read(R/'resources/fiscal'/BUNDLE)
sources = [BASE/'phase4b-requirements118-base-audit-20261020-v1/native-probe-v2/observations.json',
           BASE/'phase4b-requirements117-subjects-audit-20261020-v1/native-probe-v1/observations.json']
cases = [{'origin':'118','source':row} for row in read(sources[0])['cases']]
selected = {'acquirer-basis-correct','acquirer-basis-missing','acquirer-basis-whitespace','acquirer-basis-wrong-article','acquirer-basis-arbitrary'}
cases += [{'origin':'117','source':row} for row in read(sources[1])['cases'] if row['name'] in selected]
assert len(cases)==39
discount = next(row['source'] for row in cases if row['source']['name']=='discount-identical-rows')
distinct = json.loads(json.dumps(discount))
distinct['name'] = 'discount-two-explicit-distinct-lines'
for i,row in enumerate(distinct['input']['facts']['rtc.base_adjustments']): row['line_reference']=str(i+1)
cases.append({'origin':'NEW119','source':distinct,'expected_base':'800.00'})
charge = next(row['source'] for row in cases if row['source']['name']=='charge-decimal-string')
for name,amount in [('empty-token',''),('blank-token',' '),('exponent-token','1e2'),('comma-token','1,00'),('binary-integral',1.0)]:
    row = json.loads(json.dumps(charge)); row['name']='charge-'+name
    row['input']['facts']['rtc.base_adjustments'][0]['amount']=amount
    cases.append({'origin':'NEW119','source':row,'override_reason':'RTC_ADJUSTMENT119_AMOUNT_NOT_EXACT','override_status':'INVALID'})
assert len(cases)==45
amount_cases = {'charge-boolean-true','charge-boolean-false','charge-json-number','discount-boolean-amount','refund-boolean-amount'}
evidence_cases = {'charge-empty-evidence','charge-whitespace-evidence','discount-empty-evidence','refund-empty-evidence_id'}
changes = {name:('INVALID','RTC_ADJUSTMENT119_AMOUNT_NOT_EXACT') for name in amount_cases}
changes.update({name:('ACTION_REQUIRED','RTC_ADJUSTMENT119_EVIDENCE_EMPTY') for name in evidence_cases})
changes.update({name:('ACTION_REQUIRED','RTC_THIRD_PARTY_REIMBURSEMENT_PROOF_MISSING') for name in ['refund-empty-third_party_id','refund-empty-third_party_document_id']})
changes.update({'discount-identical-rows':('ACTION_REQUIRED','RTC_ADJUSTMENT119_LINE_IDENTITY_AMBIGUOUS'),
    'acquirer-basis-whitespace':('ACTION_REQUIRED','RTC_COLLECTION_BASIS_MISSING'),
    'acquirer-basis-wrong-article':('INVALID','RTC_COLLECTION119_BASIS_METHOD_CONFLICT'),
    'acquirer-basis-arbitrary':('ACTION_REQUIRED','RTC_COLLECTION119_BASIS_NOT_QUALIFIED')})
before=inventory(); C.mkdir()
save(C/'preflight.json',{'created_at':datetime.now(timezone.utc).isoformat(),'input_hashes':before,
    'source_observations':{str(p):sha(p) for p in sources},'planned_cases':45,'planned_decisions':90,'planned_exact_replays':90})
subprocess.run([D,'compose','exec','-T','app','mkdir','-p',TEMP],cwd=R,check=True)
cid=subprocess.run([D,'compose','ps','-q','app'],cwd=R,capture_output=True,text=True,check=True).stdout.strip()
save(C/'process-start.json',{'host_python_pid':os.getpid(),'collector_sha256':sha(Path(__file__)),'container':cid})
records=[]; monetary_checks=0
for index,case in enumerate(cases):
    old=case['source']; name=old['name']; scenario=json.loads(json.dumps(old['input']))
    scenario['scenario_id']='MATERIAL119-CONTRACT-'+name
    scenario['knowledge_at']='2026-10-20T14:22:21.000000+00:00'
    scenario['rule_bundle_id']=bundle['bundle_id']; scenario['catalog_versions']=bundle['catalog_versions']
    local_input=C/(f'{index:03d}-scenario.json'); save(local_input,scenario)
    remote_input=TEMP+'/'+local_input.name
    subprocess.run([D,'cp',str(local_input),cid+':'+remote_input],cwd=R,capture_output=True,check=True)
    record={'name':name,'origin':case['origin'],'scenario':local_input.name,'scenario_sha256':sha(local_input),
            'old_rtc_status':old.get('rtc',{}).get('status'),'formerly_threw':old.get('rtc_exception') is not None}
    for runner,command,replay_command in [('rtc','fiscal:rtc:run','fiscal:rtc:replay'),('integrated','fiscal:scenario:run','fiscal:decision:replay')]:
        status,reason=changes.get(name,(case.get('override_status'),case.get('override_reason')))
        if status is None:
            status=old[runner]['status']; reason=old[runner]['reason_codes'][0]
        label=f'{index:03d}-{runner}'; remote=TEMP+'/'+label+'-snapshot.json'
        result=run(label,[command,remote_input,'--bundle-manifest='+BUNDLE,'--snapshot='+remote],1 if runner=='rtc' and status=='INVALID' else 0)
        assert result['status']==status and reason in result['reason_codes'],(name,runner,result['status'],result['reason_codes'],status,reason)
        rtc=body(result,runner)
        if rtc is not None and rtc['decision']:
            decision=rtc['decision']; facts=scenario['facts']; gross=Decimal(facts['rtc.gross_amount']); base=gross
            for adjustment in facts['rtc.base_adjustments']:
                amount=Decimal(str(adjustment['amount']))
                base=base+amount if adjustment['kind']=='INCLUDED_CHARGE' else base-amount
            assert Decimal(decision['base']['taxable'])==base
            if 'expected_base' in case: assert decision['base']['taxable']==case['expected_base']
            for tax,rate,amount in [('IBS','state_rate_percent','state_amount'),('IBS','municipal_rate_percent','municipal_amount'),('CBS','federal_rate_percent','nominal_amount')]:
                component=decision[tax.lower()]
                nominal=(base*Decimal(component[rate])/Decimal(100)).quantize(Decimal('.01'),rounding=ROUND_HALF_UP)
                assert Decimal(component[amount])==nominal
                monetary_checks+=1
            monetary_checks+=1
        if name=='acquirer-basis-correct': assert rtc['decision']['collection']['extinction_status']=='UNCONFIRMED'
        replay=run(label+'-replay',[replay_command,remote]); assert replay['replay_matches'] is True
        if runner=='integrated': assert replay['result']==result
        snapshot=C/(label+'-snapshot.json')
        subprocess.run([D,'cp',cid+':'+remote,str(snapshot)],cwd=R,capture_output=True,check=True)
        assert read(snapshot)['input']==scenario and read(snapshot)['result']==result
        record[runner]={'status':status,'reason':reason,'snapshot':snapshot.name,'snapshot_sha256':sha(snapshot),'exact_replay':True}
    records.append(record)
    print(f'PASS:contract {index+1}/45 {name};two native decisions and exact replays',flush=True)
assert inventory()==before,'Frozen contract-capture inputs changed'
save(C/'material119-contract-cli-validation.json',{'status':'MATERIAL119_CONTRACT_NATIVE_WITNESSES_AND_EXACT_REPLAYS_VERIFIED',
    'created_at':datetime.now(timezone.utc).isoformat(),'bundle':BUNDLE,'bundle_sha256':sha(R/'resources/fiscal'/BUNDLE),
    'cases':records,'native_decisions':90,'exact_replays':90,'independent_decimal_checks':monetary_checks,
    'former_exception_cases_controlled':1,'input_inventory_unchanged':True,
    'full_suite_complete':False,'phase4b_complete':False,'production_ready':False})
print('PASS:90 native contract decisions and90 exact replays;full regression pending',flush=True)
