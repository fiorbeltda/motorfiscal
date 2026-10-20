"""Re-evaluate all56 public cases under current runtime, preserving old54 captured-policy behavior."""
from pathlib import Path
import hashlib,json,subprocess
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-61-version-integration-20261013'
P=ROOT/'storage/app/phase4b-dere9121-56-corpus-preparation-20261013'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def run(args):
    command=['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan',*args]
    result=subprocess.run(command,cwd=ROOT,capture_output=True,encoding='utf-8')
    assert result.returncode==0,result.stderr
    return json.loads(result.stdout)

records=[]
for case in read(P/'source-corpus-manifest.json')['cases']:
    name=case['name']
    snapshot=P/(name+'-snapshot.json')
    report_path=P/(name+'-monthly-report.json')
    before=sha(report_path)
    financial=run(['fiscal:rtc:replay',snapshot.relative_to(ROOT).as_posix()])
    assert financial['replay_matches'] is True
    actual=run(['fiscal:dere:project-monthly-closing',snapshot.relative_to(ROOT).as_posix(),
        (P/(name+'-month.json')).relative_to(ROOT).as_posix(),'--bundle-manifest=bundles/consumption-phase4b-2026.10.13-28-draft54.json',
        '--negative-base-settlement-request='+(P/(name+'-request.json')).relative_to(ROOT).as_posix()])
    saved=read(report_path)
    keys=set(saved)-{'candidate_output','event_outputs'}
    assert {k:actual[k] for k in keys}=={k:saved[k] for k in keys},name
    assert sha(report_path)==before and not actual['production_ready']
    for event,path in saved['event_outputs'].items():
        assert sha(ROOT/path)==actual['event_validations'][event]['xml_sha256']
    records.append({'name':name,'financial_replay_matches':True,'monthly_report_matches':True,'report_sha256':before,
        'xml_sha256':{k:v['xml_sha256'] for k,v in actual['event_validations'].items()}})
    print('PASS:current61 runtime preserves public54 case '+name,flush=True)
with (E/'public-corpus56-regression.json').open('x',encoding='utf-8') as stream:
    json.dump({'status':'ALL20_PUBLIC54_CASES_PRESERVED_AFTER_PUBLIC_VERSION_ACTIVATION','cases':records,
        'xml_hashes':120,'production_ready':False},stream,indent=2)
