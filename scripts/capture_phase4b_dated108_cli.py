"""108 native58-case corpus and427 historical exact replays; exclusive D evidence,not regression acceptance."""
from pathlib import Path
from datetime import datetime, timezone
from decimal import Decimal
import hashlib
import json
import subprocess

ROOT=Path(__file__).resolve().parents[1]
E=Path(r'D:\ProjetoSaasExports\phase4b-dated108-integration-20261019')
OLD=Path(r'D:\ProjetoSaasExports\phase4b-identity104-integration-20261019\cli-v2')
C=E/'cli-v1';F=ROOT/'resources/fiscal';D=r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
TEMP='/tmp/phase4b-dated108-cli-v1'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):
    with p.open('rb') as f:return hashlib.file_digest(f,'sha256').hexdigest()
def save(p,v):
    with p.open('x',encoding='utf-8') as f:json.dump(v,f,ensure_ascii=False,indent=2)
directed=read(E/'dated108-directed-validation.json')
for p,h in directed['runtime_hashes'].items():assert sha(ROOT/p)==h,p
base=read(ROOT/'tests/Fixtures/dated108-scenario-expectations.json')
upstream=read(ROOT/'tests/Fixtures/dated108-upstream-expectations.json')
assert base['bundle']==upstream['bundle'] and len(base['cases'])==22 and len(upstream['cases'])==36
manifest={'bundle':base['bundle'],'cases':base['cases']+upstream['cases']}
bundle=read(F/manifest['bundle']);assert sha(F/manifest['bundle'])==directed['bundle_sha256']
prior=read(OLD/'identity104-cli-validation.json')
old_cases={c['name']:c for c in prior['cases']}
decimal_controls={c['name']:c['independent_decimal'] for c in read(ROOT/'storage/app/phase4b-partial-export-fx73-preparation-20261014/export-integrated-case-manifest.json')['cases'] if c['nominal_path']}
C.mkdir(exist_ok=False)
subprocess.run([D,'compose','exec','-T','app','mkdir','-p',TEMP+'/history'],cwd=ROOT,check=True)
cid=subprocess.run([D,'compose','ps','-q','app'],cwd=ROOT,capture_output=True,text=True,check=True).stdout.strip();assert cid
save(C/'process-start.json',{'created_at':datetime.now(timezone.utc).isoformat(),'container':cid,
    'scenarios':58,'planned_native_decisions':116,'planned_historical_replays':427,'bundle_sha256':sha(F/manifest['bundle'])})

def run(label,args,expected_exit=0):
    cmd=[D,'compose','exec','-T','app','php','-d','memory_limit=512M','artisan',*args]
    record={'command':cmd,'expected_exit_code':expected_exit,'started_at':datetime.now(timezone.utc).isoformat()}
    proc=subprocess.run(cmd,cwd=ROOT,capture_output=True)
    record.update(exit_code=proc.returncode,finished_at=datetime.now(timezone.utc).isoformat())
    for ch in ['stdout','stderr']:
        with (C/(label+'.'+ch)).open('xb') as f:f.write(getattr(proc,ch))
        record[ch+'_sha256']=sha(C/(label+'.'+ch))
    save(C/(label+'-execution.json'),record)
    assert proc.returncode==expected_exit,(label,proc.stdout.decode(errors='replace')[-1800:],proc.stderr.decode(errors='replace'))
    return json.loads(proc.stdout)

def rtc_body(result,kind):
    return result if kind=='rtc' else {r['type']:r['payload'] for r in result['trace']}['CONSUMPTION_REFORM']

records=[];nominal_controls=0;decimal_checks=0
for case in manifest['cases']:
    assert sha(ROOT/case['path'])==case['scenario_sha256']
    source=read(ROOT/case['path'])
    if 'original_path' in case:
        assert sha(ROOT/case['original_path'])==case['original_sha256']
        original=read(ROOT/case['original_path']);restored=dict(source['facts'])
        for key in ['rtc.assess_export_dated_documents','rtc.export_iss_material_records','rtc.export_iss_dated_result_records',
            'rtc.export_dated_dps_identity_records','provider.iss_regime','provider.iss_regime_proof_records']:restored.pop(key,None)
        for key in ['rtc.assess_export_iss_results','rtc.assess_export_sp_results','rtc.assess_export_dps_identity']:
            if key in original['facts']:restored[key]=original['facts'][key]
            else:restored.pop(key,None)
        assert restored==original['facts']
    record=dict(case)
    for kind,cmd,replaycmd in [('rtc','fiscal:rtc:run','fiscal:rtc:replay'),('integrated','fiscal:scenario:run','fiscal:decision:replay')]:
        label=case['name']+'-'+kind;remote=TEMP+'/'+label+'-snapshot.json'
        actual=run(label,[cmd,case['path'],'--bundle-manifest='+manifest['bundle'],'--snapshot='+remote],
            1 if kind=='rtc' and case['status']=='INVALID' else 0)
        assert actual['status']==case['status'] and case['reason'] in actual['reason_codes'],(label,actual['status'],actual['reason_codes'])
        rtc=rtc_body(actual,kind);stages={r['stage']:r for r in rtc['trace']}
        assert rtc['decision']==[]
        for old_stage in ['PARTIAL_EXPORT_ISS_RESULTS','PARTIAL_EXPORT_SP_RESULTS','PARTIAL_EXPORT_DPS_IDENTITY']:assert old_stage not in stages
        if case['dated_status'] is None:
            assert 'PARTIAL_EXPORT_DATED_DOCUMENTS' not in stages
        else:
            dated=stages['PARTIAL_EXPORT_DATED_DOCUMENTS']
            assert dated['status']==case['dated_status'] and dated['reason']==case['dated_reason']
            if case.get('dated_subreason') is not None:assert dated['dated_reason']==case['dated_subreason']
            for flag in ['dps_competence_legally_approved','legal_applicability_approved','fiscal_document_identifiers_qualified',
                'government_evidence_authenticated','iss_supply_date_materially_qualified','documentary_currency_conversion_qualified',
                'per_document_tax_amount_calculated','document_obligation_complete','xml_generated','posting_authorized','production_ready']:
                assert dated[flag] is False,(label,flag)
            assert dated['identity_carrier_excluded_from_qualification_facts'] is True and dated['regime_proof_normalized_from_singleton_transport'] is True
            if dated['status']=='LOCAL_DATED_DPS_IDENTITY_PAIR_BOUND':
                assert dated['rtc_operation_date']==source['facts']['rtc.operation_date']
                for doc in dated['documents']:assert doc['identity']['competence_date']==doc['iss_supply_on']
                assert [d['document_reference'] for d in dated['documents']]==[source['facts']['rtc.export_foreign_document_id'],source['facts']['rtc.export_domestic_document_id']]
        if case.get('fx_refusal_before_nominal') is True:assert 'PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION' not in stages
        if 'PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION' in stages:
            nominal=stages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION']
            old_case=old_cases.get(case['name'],old_cases['local-pair'] if 'original_path' not in case else None)
            if old_case:
                snapshot=OLD/old_case[kind+'_snapshot'];assert sha(snapshot)==old_case[kind+'_snapshot_sha256']
                old_rtc=rtc_body(read(snapshot)['result'],kind);old_stages={r['stage']:r for r in old_rtc['trace']}
                if 'PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION' in old_stages:
                    assert nominal==old_stages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION'],label
                    nominal_controls+=1
            control_name=case['name'].removeprefix('upstream-export-')
            if control_name in decimal_controls:
                expected=decimal_controls[control_name]
                for key,value in expected.items():
                    actual_value=(str(Decimal(nominal['foreign_immune_gross_brl'])+Decimal(nominal['domestic_taxable_base_brl']))
                        if key=='total_gross_brl' else nominal[key])
                    assert actual_value==value,(label,key,actual_value,value);decimal_checks+=1
        replay=run(label+'-replay',[replaycmd,remote]);assert replay['replay_matches'] is True
        if kind=='integrated':assert replay['result']==actual
        local=C/(label+'-snapshot.json');subprocess.run([D,'cp',cid+':'+remote,str(local)],cwd=ROOT,capture_output=True,check=True)
        assert read(local)['result']==actual
        record[kind+'_snapshot']=local.name;record[kind+'_snapshot_sha256']=sha(local)
    records.append(record)
    print('PASS:'+case['name']+';both runners/exact replays',flush=True)

targets=[]
for row in prior['historical_replays']:
    path=Path(row['path']);assert sha(path)==row['sha256'];targets.append((path,row['command']))
for case in prior['cases']:
    for kind,command in [('rtc','fiscal:rtc:replay'),('integrated','fiscal:decision:replay')]:
        path=OLD/case[kind+'_snapshot'];assert sha(path)==case[kind+'_snapshot_sha256'];targets.append((path,command))
assert len(targets)==427
history=[]
for i,(path,command) in enumerate(targets):
    remote=TEMP+'/history/'+str(i)+'-snapshot.json';digest=sha(path)
    subprocess.run([D,'cp',str(path),cid+':'+remote],cwd=ROOT,capture_output=True,check=True)
    result=run('historical-'+str(i),[command,remote]);assert result['replay_matches'] is True and sha(path)==digest
    if command=='fiscal:decision:replay':assert result['result']==read(path)['result']
    history.append({'path':str(path),'sha256':digest,'command':command,'exact_replay':True})
    if (i+1)%25==0:print('PASS:historical '+str(i+1)+'/427',flush=True)
assessment=run('production-assess108',['fiscal:rtc:production-assess','--bundle-manifest='+manifest['bundle'],
    '--controls=resources/fiscal/'+bundle['production_controls']['path'],'--target=local-validation'])
previous=read(OLD/'production-assess104.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_source_hashes_valid']==assessment['official_sources_checked']==76
assert set(assessment['blockers'])==set(previous['blockers'])|{'DATED_ISS_DPS_LOCAL_EVIDENCE_ONLY_NOT_LEGAL_OR_DOCUMENT_APPROVAL'}
for p,h in directed['runtime_hashes'].items():assert sha(ROOT/p)==h,p
assert nominal_controls>0 and decimal_checks==72
save(C/'dated108-cli-validation.json',{'status':'DATED108_NATIVE_EXPANDED_CORPUS_VERIFIED_REGRESSION_PENDING',
    'created_at':datetime.now(timezone.utc).isoformat(),'bundle':manifest['bundle'],'bundle_sha256':sha(F/manifest['bundle']),
    'cases':records,'native_decisions':116,'exact_replays':116,'historical_replays':history,'historical_exact_replays':427,
    'nominal_controls_equal_to_captured104':nominal_controls,'independent_decimal_checks':decimal_checks,
    'official_sources_valid':76,'production_blocker_count':len(assessment['blockers']),
    'runtime_hashes':directed['runtime_hashes'],'full_upstream_corpus_complete':True,
    'document_obligation_complete':False,'full_suite_complete':False,'phase4b_complete':False,'production_ready':False})
print('PASS:116 native decisions/exact own replays,427 historical,76 sources/124 blockers;full regression still pending',flush=True)
