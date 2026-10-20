"""Capture the actual63 reopening-qualified monthly output and replay accepted61/58 reports."""
from pathlib import Path
import hashlib,json,subprocess
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-63-period-integration-20261013'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

def save(p,data):
    with p.open('x',encoding='utf-8') as f:
        json.dump(data,f,ensure_ascii=False,indent=2)

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

def equal(original,actual):
    keys=set(original)-{'event_outputs','candidate_output'}
    assert {k:original[k] for k in keys}=={k:actual[k] for k in keys}

command=args(E,E/'command-month.json',E/'command-request.json','63')
report_path=E/'monthly-report.json'
xml_path=E/'monthly.xml'
report=run('monthly63-command-capture',command+['--report='+report_path.relative_to(ROOT).as_posix(),'--candidate-output='+xml_path.relative_to(ROOT).as_posix()])
assert report==read(report_path)
assert report['negative_base_settlement']==read(E/'native-period-settlement.json')['result']
binding=report['negative_base_settlement']['composition']['active_version_binding']
assert binding['period_history_qualification']['reopening_count']==2
assert binding['period_history_qualification']['period_state_externally_verified'] is False
assert (ROOT/report['event_outputs']['D2101']).read_bytes()==binding['active_event']['origin_xml'].encode('utf-8')
assert report['production_ready'] is False
hashes={event:sha(ROOT/path) for event,path in report['event_outputs'].items()}
assert len(hashes)==6
previous=read(ROOT/'storage/app/phase4b-dere9121-61-version-integration-20261013/monthly-report.json')
for event,path in report['event_outputs'].items():
    assert hashes[event]==report['event_validations'][event]['xml_sha256']
    assert report['event_validations'][event]['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE'
    assert hashes[event]==sha(ROOT/previous['event_outputs'][event]),event
equal(report,run('monthly63-command-replay',command))
cases=[]
for version,names in [('61',['active03','active01']),('58',['qualified-direct-only','qualified-fund-only','qualified-both-codes'])]:
    base=ROOT/('storage/app/phase4b-dere9121-'+('61-version-integration' if version=='61' else '58-no-movement-integration')+'-20261013')
    for name in names:
        if name=='active03':
            report_name,month_name,request_name='monthly-report.json','command-month.json','command-request.json'
        elif name=='active01':
            report_name,month_name,request_name='active01-monthly-report.json','command-month.json','active01-request.json'
        else:
            report_name,month_name,request_name=name+'-monthly-report.json',name+'-month.json',name+'-request.json'
        old=read(base/report_name)
        before=sha(base/report_name)
        actual=run('old'+version+'-'+name+'-replay',args(base,base/month_name,base/request_name,version))
        equal(old,actual)
        assert sha(base/report_name)==before
        saved_hashes={event:sha(ROOT/path) for event,path in old['event_outputs'].items()}
        assert all(saved_hashes[event]==old['event_validations'][event]['xml_sha256'] for event in saved_hashes)
        cases.append({'version':version,'name':name,'report_path':(base/report_name).relative_to(ROOT).as_posix(),
            'report_sha256':before,'xml_sha256':saved_hashes,'exact_replay':True})
        print('PASS:accepted'+version+' '+name+' report and XMLs exactly preserved',flush=True)
save(E/'monthly-period-validation.json',{'status':'TWO_REOPENING_CYCLES_NATIVE_ACTIVE03_MONTHLY_CAPTURED_AND_REPLAYED',
    'report_sha256':sha(report_path),'xml_sha256':hashes,'exact_replay':True,'six_prior61_xml_bodies_preserved':True,
    'predecessor_regressions':cases,'government_return_authenticated':False,'production_ready':False})
print('PASS:actual63 monthly period qualification, active03 bytes, six schemas and exact replay')
