"""Exercise import input-credit conditions independently of source-bound foreign-currency conversion."""
from pathlib import Path
import copy,hashlib,json,subprocess
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-import-fx68-integration-20261013'
F=ROOT/'resources/fiscal'
base=json.loads((F/'scenarios/import-fx-2026.10.14-68-draft1-v3/usd-direct.json').read_text(encoding='utf-8'))
legacy=json.loads((F/'scenarios/consumption-phase4b-2026.09.28-15/import-credit-proven-2026.json').read_text(encoding='utf-8'))
directory=F/'scenarios/import-fx-credit-2026.10.14-68-draft1';directory.mkdir(exist_ok=True)

def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,v):
    with p.open('x',encoding='utf-8') as f:json.dump(v,f,ensure_ascii=False,indent=2)
def run(label,args):
    result=subprocess.run(['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8')
    log=E/(label+'.log')
    with log.open('x',encoding='utf-8') as f:f.write(result.stdout+result.stderr)
    save(E/(label+'.json'),{'exit_code':result.returncode,'log_sha256':sha(log),'args':args})
    assert result.returncode==0,result.stderr
    return json.loads(result.stdout)

records=[]
for name in ['proven','no-extinction','document-unlinked','claimant-simples']:
    scenario=copy.deepcopy(base);scenario['scenario_id']='SYNTHETIC-FX68-CREDIT-'+name
    facts=scenario['facts']
    for key,value in legacy['facts'].items():
        if key.startswith('rtc.input_') or key.startswith('rtc.import_credit_') or key in ['rtc.acquirer_regime','rtc.prior_tax_extinguished','rtc.credit_adjustments']:
            facts[key]=value
    facts['rtc.credit_context']='INPUT'
    facts['rtc.presumed_credit_requested']=False
    facts['rtc.input_ibs_amount']='5.18';facts['rtc.input_cbs_amount']='46.62'
    facts['rtc.input_tax_treatments']=[{'tax':tax,'treatment':'STANDARD','document_id':'dfe-import-1','evidence_id':'SYNTHETIC-FX68-'+tax} for tax in ['IBS','CBS']]
    if name=='no-extinction':facts['rtc.prior_tax_extinguished']=False
    if name=='document-unlinked':facts['rtc.import_credit_fiscal_document_id']='other-document'
    if name=='claimant-simples':facts['rtc.acquirer_regime']='SIMPLES_NACIONAL'
    path=directory/(name+'.json');save(path,scenario)
    snapshot=E/(name+'-fx-credit-snapshot.json')
    result=run(name+'-fx-credit-capture',['fiscal:rtc:run',path.relative_to(ROOT).as_posix(),
        '--bundle-manifest=bundles/consumption-phase4b-2026.10.14-68-draft1.json','--snapshot='+snapshot.relative_to(ROOT).as_posix()])
    replay=run(name+'-fx-credit-replay',['fiscal:rtc:replay',snapshot.relative_to(ROOT).as_posix()])
    assert replay['replay_matches'] is True
    trace={r['stage']:r for r in result['trace']}
    assert trace['IMPORT_FX_TRANSACTION']['converted_gross_brl']=='5179.50'
    assert trace['IMPORT_FX_TRANSACTION']['credit_appropriated'] is False
    if name=='document-unlinked':
        assert 'RTC_IMPORT_CREDIT_DOCUMENT_LINK_CONFLICT' in result['reason_codes'],result['reason_codes']
    else:
        credit=trace['IMPORT_CREDIT']
        if name=='proven':
            assert credit['ibs']['amount']=='5.18' and credit['cbs']['amount']=='46.62'
        elif name=='no-extinction':
            assert credit['ibs']['amount']=='0.00' and credit['cbs']['amount']=='0.00'
        else:
            assert credit['ibs']['status']=='DENIED' and credit['cbs']['status']=='DENIED'
    records.append({'name':name,'snapshot_sha256':sha(snapshot),'reason_codes':result['reason_codes'],'exact_replay':True,
        'conversion_preserved':True,'credit_appropriation_not_performed':True,'production_ready':False})
    print('PASS:'+name+'; FX preserved, distinct credit condition and exact integrated replay',flush=True)
save(E/'fx-credit-validation.json',{'status':'FOUR_FX_IMPORT_CREDIT_CONDITIONS_CAPTURED_AND_REPLAYED','cases':records,
    'document_obligation_proven':False,'full_scope68_complete':False,'production_ready':False})
