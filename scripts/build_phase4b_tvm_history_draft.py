"""Prepare immutable prior-period inputs using official August SGS11 and real RTC snapshots."""
import copy,json,hashlib,subprocess,urllib.request
from datetime import datetime,timezone
from pathlib import Path
from dere_daily_memory_oracle import oracle,proof,seal,digest
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal'
E=ROOT/'storage/app/phase4b-realization-history-draft-20261006-28';E.mkdir(parents=True,exist_ok=True)
S=F/'scenarios/tvm-history-draft-2026.10.06-28';S.mkdir(parents=True,exist_ok=True)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
catalog=read(F/'catalogs/rtc-codes-2026.10.06-27.json');daily=copy.deepcopy(catalog['dere_debt_daily_memory_calculation'])
raw=(ROOT/'storage/app/phase4b-realization-draft-20261006-28/bcb-sgs11-observed-20261006-202608.json').read_bytes()
observation=E/'august-series-reobservation.json'
if not observation.exists():
 url='https://api.bcb.gov.br/dados/serie/bcdata.sgs.11/dados?formato=json&dataInicial=01/08/2026&dataFinal=31/08/2026'
 checked=urllib.request.urlopen(url,timeout=45).read();assert checked==raw
 save(observation,{'url':url,'observed_at':datetime.now(timezone.utc).replace(microsecond=0).isoformat(),
  'sha256':hashlib.sha256(checked).hexdigest(),'bytes':len(checked),'rows':len(json.loads(checked))})
observed=read(observation)['observed_at']
official=F/'sources/official/bcb-sgs11-observed-20261006-202608.json'
if official.exists():assert official.read_bytes()==raw
else:official.write_bytes(raw)
aug=copy.deepcopy(daily);series=aug['series'];series.pop('source_hash')
series.update(period='2026-08',official_data_sha256=hashlib.sha256(raw).hexdigest(),observed_at=observed,
 rows=[{'date':datetime.strptime(row['data'],'%d/%m/%Y').date().isoformat(),'daily_percent':row['valor']} for row in json.loads(raw)])
series['source_hash']=digest(series)
aug.update(series_hash=series['source_hash'],official_data_sha256=series['official_data_sha256'],official_data_file=official.name,published_at=observed)
manifest=read(F/'bundles/consumption-phase4b-2026.10.06-27.json')
policy={'contract':'TVM-RECOGNITION-HISTORY-REPLAY-2026.10.06-1','status':'ACTIVE','published_at':observed,
 'source_hash':catalog['financial_operation']['base']['source_hash'],
 'permitted_bundle_manifest_hashes':[digest(manifest)],'daily_memory_policies':{'2026-08':aug,'2026-09':daily},
 'acceptance_status':'DRAFT_NOT_BOUND_TO_ACCEPTED_REALIZATION_BUNDLE','production_ready':False}
save(F/'catalogs/tvm-recognition-history-draft-2026.10.06-28-v2.json',policy)
base=read(F/'scenarios/dere-daily-memory-2026.10.06-26/partial-sale-gain.json')['position']
v=copy.deepcopy(base);v.update(period='2026-08',first_acquisition=True,opening={'quantity':'0','unit_cost':'0','acquisition_cost':'0','book_balance':'0.00'},vPisCofins='0.00')
v.pop('previous_position_evidence')
v['operations']=[{'id':'buy1','date':'2026-08-03','sequence':1,'kind':'BUY','quantity':'1000','unit_price':'10','book_amount':'10000.00'}]
v['method_evidence']=proof({'contract':daily['contract'],'method':v['method']},'SYNTHETIC.HISTORY.AUGUST.METHOD','2026-08-01','2026-08-31')
v['appropriations']={'2026-08-20':'100','2026-08-31':'200'}
v['contract_curve'],v['declared_monthly'],expected=oracle(v,series);v.pop('appropriations')
v.pop('memory_evidence');v['memory_evidence']=proof(v,'SYNTHETIC.HISTORY.AUGUST.MEMORY','2026-08-01','2026-08-31')
save(S/'previous-private-august-position.json',{'position':v,'independent_oracle':expected})
scenario=read(F/'scenarios/dere-variable-yield-2026.10.06-27/constant-financial.json')
scenario.update(scenario_id='SYNTHETIC-previous-private-tvm-august',competence_date='2026-08-31')
scenario['facts']['rtc.operation_date']='2026-08-31'
ledger=scenario['facts']['rtc.financial_ledger'][0];ledger.update(period_start='2026-08-01',period_end='2026-08-31')
identity={'idTitulo':v['idTitulo'],'cCta':v['cCta'],'CNPJDevedor':'11222333000181','currency':'BRL'}
entry=copy.deepcopy(ledger['entries'][0]);entry.update(id='prior-return',recognized_on='2026-08-20',amount='100.00',security_identity=identity)
fv=copy.deepcopy(entry);fv.update(id='prior-fair-value',document_id='prior-fv-document',recognized_on='2026-08-31',kind='FAIR_VALUE_UNREALIZED',amount='200.00',
 exceeds_produced_return=True,subaccount_evidence_id='SYNTHETIC.HISTORY.SUBACCOUNT.FV000',security_identity=identity)
ledger['entries']=[entry,fv];save(S/'previous-private-august-financial.json',scenario)
snap=E/'previous-private-august-snapshot.json';result=E/'previous-private-august-result.json'
if not snap.exists():
 p=subprocess.run(['docker','compose','exec','-T','app','php','artisan','fiscal:rtc:run',
  (S/'previous-private-august-financial.json').relative_to(ROOT).as_posix(),
  '--bundle-manifest=bundles/consumption-phase4b-2026.10.06-27.json','--snapshot='+snap.relative_to(ROOT).as_posix()],cwd=ROOT,capture_output=True,encoding='utf-8')
 assert p.returncode==0,p.stdout+p.stderr
 r=json.loads(p.stdout);assert r['status']=='RESOLVED' or (r['status']=='ACTION_REQUIRED' and r['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING']),r;save(result,r)
if snap.exists() and not result.exists():
 r=read(snap)['result'];assert r['status']=='ACTION_REQUIRED' and r['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'];save(result,r)
assert read(snap)['result']==read(result)
print('Draft history inputs frozen:31 calendar days/21 official August rates;prior private income100 and deferred FV200 in an actual RTC snapshot.')
