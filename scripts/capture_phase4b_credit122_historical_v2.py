"""Reevaluate all1441 accepted119/pre119 snapshots without rewriting any historical artifact."""
from pathlib import Path
from datetime import datetime,timezone
import hashlib,json,os,subprocess
R=Path(__file__).resolve().parents[1];BASE=Path(r'D:\ProjetoSaasExports')
E=BASE/'phase4b-credit122-preparation-20261021';C=E/'historical-replay-v2'
OLD=BASE/'phase4b-material-input119-integration-20261020'
D=r'D:\Docker\DockerDesktop\resources\bin\docker.exe';TEMP='/tmp/credit122-historical-v2'
def read(p):return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb')as f:return hashlib.file_digest(f,'sha256').hexdigest()
def canonical(v):return json.dumps(v,sort_keys=True,ensure_ascii=False,separators=(',',':'))
def save(p,v):
    with p.open('x',encoding='utf-8')as f:json.dump(v,f,ensure_ascii=False,indent=2)
assert not C.exists(),'Exclusive historical attempt;never restart a live collector'
accepted=R/'storage/app/phase4b-material119-development-20261020/material119-development-validation.json'
assert sha(accepted)=='98cb2e92afe2281cc88d94c3a49f155152cc146071a12a5e70cf7511d303cc19'
prior=read(accepted)
expanded=OLD/'expanded-cli-v1/material119-expanded-cli-validation.json'
short=OLD/'cli-v1/material119-cli-validation.json';contracts=OLD/'contract-cli-v2/material119-contract-cli-validation.json'
assert sha(expanded)==prior['corpus_sha256']
old=read(expanded);targets=[{'path':row['path'],'sha256':row['sha256'],'command':row['command']}for row in old['historical_replays']]
assert len(targets)==1071
for record_path in [expanded,short,contracts]:
    for row in read(record_path)['cases']:
        for runner,command in [('rtc','fiscal:rtc:replay'),('integrated','fiscal:decision:replay')]:
            if record_path==expanded:name=row[runner+'_snapshot'];digest=row[runner+'_snapshot_sha256']
            else:name=row[runner]['snapshot'];digest=row[runner]['snapshot_sha256']
            targets.append({'path':str(record_path.parent/name),'sha256':digest,'command':command})
assert len(targets)==1441 and len({t['path']for t in targets})==1441
historical_receipt=E/'historical-replay-v1/credit122-historical-validation.json'
assert sha(historical_receipt)=='ddffca53a90188148c06c90d8b3cedb12ba24a2201e5198f823fb6505b349520'
accepted_targets=[{key:row[key]for key in ['path','sha256','command']}for row in read(historical_receipt)['records']]
assert targets==accepted_targets,'Same complete1441 historical corpus;no dropped or substituted target'
preparation=E/'credit122-expanded-preparation-validation.json'
runtime=read(preparation)['runtime_hashes']
assert len(runtime)==94
def inventory():
    paths=[p for folder in ['app','tests','resources/fiscal']for p in(R/folder).rglob('*')if p.is_file()]
    paths += [Path(__file__),R/'composer.lock',R/'phpunit.xml',accepted,expanded,short,contracts,historical_receipt,preparation]
    paths += [Path(t['path'])for t in targets]
    return{str(p):sha(p)for p in sorted(set(paths))}
for path,digest in runtime.items():assert sha(R/path)==digest,path
for t in targets:assert sha(Path(t['path']))==t['sha256'],t['path']
before=inventory();C.mkdir()
save(C/'preflight.json',{'created_at':datetime.now(timezone.utc).isoformat(),'input_hashes':before,'runtime_hashes':runtime,'planned_historical_replays':1441,'targets':targets,'accepted119_sha256':sha(accepted)})
subprocess.run([D,'compose','exec','-T','app','mkdir','-p',TEMP],cwd=R,check=True)
cid=subprocess.run([D,'compose','ps','-q','app'],cwd=R,capture_output=True,text=True,check=True).stdout.strip()
save(C/'process-start.json',{'host_python_pid':os.getpid(),'container':cid,'collector_sha256':sha(Path(__file__))})
print('LIVE:1441 historical replays;host Python PID:'+str(os.getpid()),flush=True)
records=[]
for index,t in enumerate(targets):
    path=Path(t['path']);assert sha(path)==t['sha256'];remote=TEMP+'/'+str(index)+'-snapshot.json'
    subprocess.run([D,'cp',str(path),cid+':'+remote],cwd=R,capture_output=True,check=True)
    command=[D,'compose','exec','-T','app','php','-d','memory_limit=512M','artisan',t['command'],remote]
    start=datetime.now(timezone.utc).isoformat();p=subprocess.run(command,cwd=R,capture_output=True)
    label=f'{index:04d}-replay';receipt={'command':command,'started_at':start,'finished_at':datetime.now(timezone.utc).isoformat(),'exit_code':p.returncode}
    for channel in ['stdout','stderr']:
        with(C/(label+'.'+channel)).open('xb')as f:f.write(getattr(p,channel))
        receipt[channel+'_sha256']=sha(C/(label+'.'+channel))
    save(C/(label+'-execution.json'),receipt);assert p.returncode==0,(index,path,p.stderr[-1000:])
    result=json.loads(p.stdout);assert result['replay_matches']is True,(index,path)
    if t['command']=='fiscal:decision:replay':assert canonical(result['result'])==canonical(read(path)['result']),'typed captured result:'+str(path)
    assert sha(path)==t['sha256'],'historical source changed:'+str(path)
    records.append({**t,'exact_replay':True,'execution':label+'-execution.json'})
    if(index+1)%25==0:print('PASS:historical122 '+str(index+1)+'/1441',flush=True)
assert inventory()==before,'Frozen historical122 inputs changed'
save(C/'credit122-historical-validation.json',{'created_at':datetime.now(timezone.utc).isoformat(),'status':'CREDIT122_ALL_ACCEPTED119_AND_PRIOR_REPLAYS_EXACT','historical_exact_replays':1441,'records':records,'runtime_hashes':runtime,'input_inventory_unchanged':True,'full_suite_complete':False,'phase4b_complete':False,'production_ready':False})
print('PASS:1441 historical replays exact;full122 regression pending',flush=True)
