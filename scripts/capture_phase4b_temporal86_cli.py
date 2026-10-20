"""Capture 14 native temporal cases through RTC/ISS, exact replays and explicit production refusal."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess

ROOT=Path(__file__).resolve().parents[1]
P=ROOT/'storage/app/phase4b-temporal86-integration-preparation-20261015'
E=ROOT/'storage/app/phase4b-temporal86-cli-20261015'
F=ROOT/'resources/fiscal'
D=r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
def read(path):return json.loads(path.read_text(encoding='utf-8'))
def sha(path):return hashlib.sha256(path.read_bytes()).hexdigest()
def save(name,value):
    with (E/name).open('x',encoding='utf-8') as stream:json.dump(value,stream,ensure_ascii=False,indent=2)
def relative(path):return path.relative_to(ROOT).as_posix()
def run(label,args):
    command=[D,'compose','exec','-T','app','php','-d','memory_limit=512M','artisan',*args]
    started=datetime.now(timezone.utc).isoformat();result=subprocess.run(command,cwd=ROOT,capture_output=True)
    for channel in ['stdout','stderr']:
        with (E/(label+'.'+channel)).open('xb') as stream:stream.write(getattr(result,channel))
    save(label+'-execution.json',{'command':command,'exit_code':result.returncode,'started_at':started,
        'finished_at':datetime.now(timezone.utc).isoformat(),'stdout_sha256':sha(E/(label+'.stdout')),'stderr_sha256':sha(E/(label+'.stderr'))})
    assert result.returncode==0,(label,result.stderr.decode(),result.stdout.decode()[-1500:])
    return json.loads(result.stdout)
E.mkdir(exist_ok=False)
manifest=read(P/'temporal86-case-manifest-v2.json');M=manifest['bundle'];bundle=read(F/M)
records=[]
for case in manifest['cases']:
    name=case['case'];path=case['path'];rtc_snapshot=E/(name+'-rtc-snapshot.json');full_snapshot=E/(name+'-integrated-snapshot.json')
    rtc=run(name+'-rtc',['fiscal:rtc:run',path,'--bundle-manifest='+M,'--snapshot='+relative(rtc_snapshot)])
    full=run(name+'-integrated',['fiscal:scenario:run',path,'--bundle-manifest='+M,'--snapshot='+relative(full_snapshot)])
    assert rtc['status']==case['status']
    assert full['status']==('VALID_WITH_WARNINGS' if case['status']=='VALID' else case['status'])
    if case['reason'] is not None:assert case['reason'] in rtc['reason_codes'] and case['reason'] in full['reason_codes']
    if case['status']=='VALID':
        events={r['type']:r['payload'] for r in full['trace']}
        assert events['ISS_CALCULATION']['tax_amount']=='20.00'
        assert events['DECISION_COMPLETED']['warnings']==['RULE_BUNDLE_NOT_PRODUCTION_APPROVED','MUNICIPAL_PARAMETERS_ARE_FIXTURES']
    assert rtc==read(rtc_snapshot)['result'] and full==read(full_snapshot)['result']
    assert run(name+'-rtc-replay',['fiscal:rtc:replay',relative(rtc_snapshot)])['replay_matches'] is True
    replay=run(name+'-integrated-replay',['fiscal:decision:replay',relative(full_snapshot)])
    assert replay['replay_matches'] is True and replay['result']==full
    if name=='advance-candidate-not-booked':
        trace={r['stage']:r for r in rtc['trace']};candidate=trace['TEMPORAL_ADVANCE_RECONCILIATION']
        assert candidate['taxes']['CBS']['arithmetic_balance']=='6.30' and candidate['prior_advance_debits_proven'] is False
    records.append({**case,'scenario_sha256':sha(ROOT/path),'rtc_snapshot':relative(rtc_snapshot),'rtc_snapshot_sha256':sha(rtc_snapshot),
        'integrated_snapshot':relative(full_snapshot),'integrated_snapshot_sha256':sha(full_snapshot),'rtc_exact_replay':True,'integrated_exact_replay':True})
    print('PASS:'+name+';two runners and exact replays',flush=True)
prior=[]
simples=read(ROOT/'storage/app/phase4b-simples78-cli-v2-20261015/simples78-cli-corpus-validation.json')['cases'][0]
for label,path,command in [
    ('prior-simples78',simples['rtc_snapshot'],'fiscal:rtc:replay'),
    ('prior-foreign76','storage/app/phase4b-foreign-acquirer76-cli-20261015/nif-snapshot.json','fiscal:decision:replay'),
    ('prior-export73','storage/app/phase4b-partial-export-fx73-preparation-20261014/usd-direct-export-snapshot.json','fiscal:rtc:replay')]:
    digest=sha(ROOT/path);assert run(label,[command,path])['replay_matches'] is True;assert sha(ROOT/path)==digest
    prior.append({'path':path,'sha256':digest,'exact_replay':True})
assessment=run('production-assessment',['fiscal:rtc:production-assess','--bundle-manifest='+M,
    '--controls=resources/fiscal/'+bundle['production_controls']['path'],'--target=local-validation'])
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_sources_checked']==assessment['official_source_hashes_valid']==59
old=read(ROOT/'storage/app/phase4b-simples78-development-20261015/simples78-final-production-assess.log')
assert not set(old['blockers'])-set(assessment['blockers'])
assert set(assessment['blockers'])-set(old['blockers'])=={'CONTROL_NOT_APPROVED:4B.1.TEMPORAL_EVENT_SETTLEMENT'}
save('temporal86-cli-corpus-validation.json',{'status':'TEMPORAL86_RTC_ISS_NATIVE_CORPUS_VERIFIED_FULL_REGRESSION_PENDING',
    'bundle':M,'bundle_sha256':sha(F/M),'cases':records,'prior_compatibility':prior,'native_decisions':28,'exact_replays':28,
    'official_sources_valid':59,'production_blocker_count':len(assessment['blockers']),'full_suite_complete':False,
    'prior_advance_debits_proven':False,'document_settlement_complete':False,'phase4b_complete':False,'production_ready':False})
print('PASS:14 scenarios/28 native decisions/28 exact replays/3 historical replays;59 sources;new settlement gate retained')
