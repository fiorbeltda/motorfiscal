"""Capture active01 and freshly replay active03 and the three accepted58 closures."""
from pathlib import Path
import hashlib,json,subprocess
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-61-version-integration-20261013'
P=ROOT/'storage/app/phase4b-dere9121-58-no-movement-integration-20261013'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

def save(p,v):
    with p.open('x',encoding='utf-8') as f:
        json.dump(v,f,ensure_ascii=False,indent=2)

def run(label,args):
    result=subprocess.run(['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8')
    log=E/(label+'.log')
    with log.open('x',encoding='utf-8') as f:
        f.write(result.stdout+result.stderr)
    save(E/(label+'.json'),{'exit_code':result.returncode,'log_sha256':sha(log),'args':args})
    assert result.returncode==0,result.stderr
    return json.loads(result.stdout)

def args(base,month,request,version):
    return ['fiscal:dere:project-monthly-closing',(base/'financial-snapshot.json').relative_to(ROOT).as_posix(),
        month.relative_to(ROOT).as_posix(),'--bundle-manifest=bundles/consumption-phase4b-2026.10.13-28-draft'+version+'.json',
        '--negative-base-settlement-request='+request.relative_to(ROOT).as_posix()]

def same_report(original,actual):
    keys=set(original)-{'event_outputs','candidate_output'}
    assert {k:original[k] for k in keys}=={k:actual[k] for k in keys}

request=read(E/'command-request.json')
request['current_public_offer_return']['events']=request['current_public_offer_return']['events'][:2]
request_path=E/'active01-request.json'
save(request_path,request)
report_path=E/'active01-monthly-report.json'
xml_path=E/'active01-monthly.xml'
command=args(E,E/'command-month.json',request_path,'61')
report=run('active01-command-capture',command+['--report='+report_path.relative_to(ROOT).as_posix(),'--candidate-output='+xml_path.relative_to(ROOT).as_posix()])
assert report==read(report_path)
active=request['current_public_offer_return']['events'][-1]
assert (ROOT/report['event_outputs']['D2101']).read_bytes()==active['origin_xml'].encode('utf-8')
assert report['projection']['active_public_event_output']['sequence']=='01'
assert report['negative_base_settlement']['ibs_nominal_proposal']=='0.74'
assert report['negative_base_settlement']['cbs_nominal_proposal']=='7.02'
assert report['production_ready'] is False
baseline=read(E/'monthly-report.json')
hashes={event:sha(ROOT/path) for event,path in report['event_outputs'].items()}
assert len(hashes)==6
for event,path in report['event_outputs'].items():
    assert hashes[event]==report['event_validations'][event]['xml_sha256']
    assert report['event_validations'][event]['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE'
    if event!='D2101':
        assert hashes[event]==sha(ROOT/baseline['event_outputs'][event]),event
same_report(report,run('active01-command-replay',command))
same_report(baseline,run('active03-post-format-replay',args(E,E/'command-month.json',E/'command-request.json','61')))
cases=[]
for name in ['qualified-direct-only','qualified-fund-only','qualified-both-codes']:
    old_path=P/(name+'-monthly-report.json')
    old=read(old_path)
    before=sha(old_path)
    actual=run(name+'-compatibility-replay',args(P,P/(name+'-month.json'),P/(name+'-request.json'),'58'))
    same_report(old,actual)
    assert sha(old_path)==before
    xml_hashes={event:sha(ROOT/path) for event,path in old['event_outputs'].items()}
    assert all(xml_hashes[event]==old['event_validations'][event]['xml_sha256'] for event in xml_hashes)
    cases.append({'name':name,'report_sha256':before,'xml_sha256':xml_hashes,'exact_replay':True})
    print('PASS:accepted58 '+name+' exactly replayed under61 runtime',flush=True)
save(E/'selection-regression-validation.json',{'status':'ACTIVE01_CAPTURED_ACTIVE03_AND_THREE_NO_MOVEMENT_CLOSURES_EXACTLY_REPLAYED',
    'active01_report_sha256':sha(report_path),'active01_xml_sha256':hashes,'active01_exact_replay':True,
    'active03_post_format_exact_replay':True,'five_auxiliary_and_closing_xmls_preserved':True,'no_movement_cases':cases,
    'government_return_authenticated':False,'production_ready':False})
print('PASS:active01 actual bytes, six schemas, preserved baseline and exact replays')
