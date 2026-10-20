"""Capture three installed no-movement monthly executions and exact replays."""
from pathlib import Path
import hashlib,json,subprocess
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-58-no-movement-integration-20261013'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def save(path,data):
    with path.open('x',encoding='utf-8') as stream:
        json.dump(data,stream,ensure_ascii=False,indent=2)

def run(label,args):
    result=subprocess.run(['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8')
    log=E/(label+'.log')
    with log.open('x',encoding='utf-8') as stream:
        stream.write(result.stdout+result.stderr)
    save(E/(label+'.json'),{'exit_code':result.returncode,'log_sha256':sha(log),'args':args})
    assert result.returncode==0,result.stderr
    return json.loads(result.stdout)

cases=[]
for name in ['qualified-direct-only','qualified-fund-only','qualified-both-codes']:
    captured=read(E/(name+'-no-movement-settlement.json'))
    month=E/(name+'-month.json')
    request=E/(name+'-request.json')
    save(month,captured['input']['month'])
    save(request,captured['input']['request'])
    report_path=E/(name+'-monthly-report.json')
    xml=E/(name+'-monthly.xml')
    args=['fiscal:dere:project-monthly-closing',(E/'financial-snapshot.json').relative_to(ROOT).as_posix(),month.relative_to(ROOT).as_posix(),
        '--bundle-manifest=bundles/consumption-phase4b-2026.10.13-28-draft58.json','--negative-base-settlement-request='+request.relative_to(ROOT).as_posix()]
    actual=run(name+'-command-capture',args+['--report='+report_path.relative_to(ROOT).as_posix(),'--candidate-output='+xml.relative_to(ROOT).as_posix()])
    assert actual==read(report_path) and actual['negative_base_settlement']==captured['result']
    assert actual['status']=='ACTION_REQUIRED' and not actual['production_ready']
    assert len(actual['event_validations'])==6
    hashes={}
    for event,path in actual['event_outputs'].items():
        hashes[event]=sha(ROOT/path)
        assert hashes[event]==actual['event_validations'][event]['xml_sha256']
        assert actual['event_validations'][event]['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE'
    replay=run(name+'-command-replay',args)
    keys=set(actual)-{'event_outputs','candidate_output'}
    assert {k:actual[k] for k in keys}=={k:replay[k] for k in keys}
    cases.append({'name':name,'report_sha256':sha(report_path),'xml_sha256':hashes,'exact_replay':True})
    print('PASS:'+name+'; six native XMLs and exact no-movement monthly replay',flush=True)
save(E/'native-monthly-validation.json',{'status':'THREE_NO_MOVEMENT_PUBLIC_MONTHLY_SELECTIONS_CAPTURED_AND_REPLAYED','cases':cases,'production_ready':False})
