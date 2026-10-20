"""Actual new-origin policy decisions, exact replays and separate old68 snapshot compatibility."""
from pathlib import Path
import hashlib,json,subprocess
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-fx70-origin-preparation-20261014'
P=ROOT/'storage/app/phase4b-import-fx68-integration-20261013'

def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,v):
    with p.open('x',encoding='utf-8') as f:json.dump(v,f,ensure_ascii=False,indent=2)
def run(label,args):
    executed=subprocess.run(['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8')
    log=E/(label+'.log')
    with log.open('x',encoding='utf-8') as f:f.write(executed.stdout+executed.stderr)
    save(E/(label+'.json'),{'exit_code':executed.returncode,'log_sha256':sha(log),'args':args})
    assert executed.returncode==0,executed.stderr
    return json.loads(executed.stdout)
records=[]
for case in read(E/'integrated-origin-case-manifest.json')['cases']:
    name=case['name'];snapshot=E/(name+'-origin-snapshot.json')
    actual=run(name+'-origin-capture',['fiscal:rtc:run',case['path'],
        '--bundle-manifest=bundles/consumption-phase4b-2026.10.14-70-draft1.json','--snapshot='+snapshot.relative_to(ROOT).as_posix()])
    assert case['expected_reason'] in actual['reason_codes'],name+':'+str(actual['reason_codes'])
    replay=run(name+'-origin-replay',['fiscal:rtc:replay',snapshot.relative_to(ROOT).as_posix()]);assert replay['replay_matches']
    trace={r['stage']:r for r in actual['trace']}
    if case['nominal_path']:
        fx=trace['IMPORT_FX_TRANSACTION'];assert fx['material_quote_origin']['strategy']=='BCB_USD_BRL_PTAX'
        before=read(P/(name+'-integrated-snapshot.json'))
        old_trace={r['stage']:r for r in before['result']['trace']}
        assert trace['IMPORT_BRAZIL_ALLOCATION']==old_trace['IMPORT_BRAZIL_ALLOCATION']
        assert trace['IMPORT_NOMINAL_CALCULATION']==old_trace['IMPORT_NOMINAL_CALCULATION']
        assert fx['production_ready'] is False
    else:assert 'IMPORT_NOMINAL_CALCULATION' not in trace
    records.append({'name':name,'snapshot_sha256':sha(snapshot),'expected_reason':case['expected_reason'],
        'exact_replay':True,'nominal_path':case['nominal_path'],'production_ready':False})
    print('PASS:'+name+'; qualified origin or expected refusal, exact replay',flush=True)
old=[]
for case in read(P/'integrated-corpus-validation.json')['cases']:
    path=P/(case['name']+'-integrated-snapshot.json');before=sha(path)
    assert run('old68-'+case['name']+'-replay',['fiscal:rtc:replay',path.relative_to(ROOT).as_posix()])['replay_matches']
    assert sha(path)==before
    old.append({'name':case['name'],'sha256':before,'exact_replay':True})
save(E/'integrated-origin-capture-validation.json',{'status':'NINE_MATERIAL_ORIGIN_DECISIONS_AND_FOURTEEN_OLD68_REPLAYS_CAPTURED',
    'cases':records,'old68_compatibility':old,'non_usd_origin_qualified':False,'production_ready':False})
print('PASS:nine new origin selections,14 exact predecessor replays, non-USD origin remains explicit')
