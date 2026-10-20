"""Capture own FX integrated decisions and replays; independently verify allocation and nominal taxes."""
from pathlib import Path
from decimal import Decimal,ROUND_HALF_UP
import hashlib,json,subprocess
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-import-fx68-integration-20261013'

def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,v):
    with p.open('x',encoding='utf-8') as f:json.dump(v,f,ensure_ascii=False,indent=2)
def run(label,args):
    result=subprocess.run(['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8')
    log=E/(label+'.log')
    with log.open('x',encoding='utf-8') as f:f.write(result.stdout+result.stderr)
    save(E/(label+'.json'),{'exit_code':result.returncode,'log_sha256':sha(log),'args':args})
    assert result.returncode==0,label+':'+result.stderr
    return json.loads(result.stdout)

records=[]
for case in read(E/'scenario-manifest-v3.json')['cases']:
    name=case['name'];snapshot=E/(name+'-integrated-snapshot.json')
    report=run(name+'-integrated-capture',['fiscal:rtc:run',case['path'],
        '--bundle-manifest=bundles/consumption-phase4b-2026.10.14-68-draft1.json','--snapshot='+snapshot.relative_to(ROOT).as_posix()])
    assert case['expected_reason'] in report['reason_codes']
    replay=run(name+'-integrated-replay',['fiscal:rtc:replay',snapshot.relative_to(ROOT).as_posix()])
    assert replay['replay_matches'] is True
    result=read(snapshot)['result'];trace={row['stage']:row for row in result['trace']}
    if case['positive_fx_path']:
        conversion=trace['IMPORT_FX_TRANSACTION']['conversion']
        amount=(Decimal(conversion['original_amount'])*Decimal(conversion['selected_quote']['rate'])).quantize(Decimal('.01'),rounding=ROUND_HALF_UP)
        assert str(amount)==case['converted_gross_brl']==conversion['converted_gross_brl']
        if name=='brl-adjustment':amount+=Decimal('10')
        if name=='usd-partial':amount=(amount*Decimal('.25')).quantize(Decimal('.01'),rounding=ROUND_HALF_UP)
        assert Decimal(trace['IMPORT_BRAZIL_ALLOCATION']['brazil_taxable_base_brl'])==amount
        nominal=trace['IMPORT_NOMINAL_CALCULATION']
        for tax,rate in [('ibs_state','.001'),('ibs_municipal','0'),('cbs','.009')]:
            assert Decimal(nominal[tax])==(amount*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_UP)
        assert 'IMPORT_LOCAL_DPS_PROJECTION' in trace
    else:
        assert 'IMPORT_NOMINAL_CALCULATION' not in trace
    records.append({'name':name,'snapshot_sha256':sha(snapshot),'expected_reason':case['expected_reason'],
        'exact_replay':True,'nominal_calculated':case['positive_fx_path'],'production_ready':False})
    print('PASS:'+name+'; full decision replay and independent nominal/guard validation',flush=True)
save(E/'integrated-corpus-validation.json',{'status':'FOURTEEN_INTEGRATED_IMPORT_FX_DECISIONS_CAPTURED_AND_REPLAYED',
    'cases':records,'nominal_paths':6,'refusals':8,'exact_replays':14,'full_suite_complete':False,
    'unquoted_currency_path_qualified':False,'production_ready':False})
