"""Bind the unfinished current realization integration to its own immutable development bundle."""
import copy,json,hashlib
from datetime import datetime,timezone
from pathlib import Path
from dere_daily_memory_oracle import digest,proof
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.06-28-draft'
E=ROOT/'storage/app/phase4b-realization-base-draft-20261006-28';E.mkdir(parents=True,exist_ok=True)
S=F/f'scenarios/tvm-base-{V}';S.mkdir(parents=True,exist_ok=True)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:p.parent.mkdir(parents=True,exist_ok=True);p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
baseline={}
for base in [F,ROOT/'storage/app']:
 for p in base.rglob('*'):
  if p.is_file() and E not in p.parents and V not in str(p) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']:
   baseline[p.relative_to(ROOT).as_posix()]=hashlib.sha256(p.read_bytes()).hexdigest()
if not (E/'historical-before.json').exists():save(E/'historical-before.json',baseline)
manifest=read(F/'bundles/consumption-phase4b-2026.10.06-27.json');old=copy.deepcopy(manifest)
bundle=F/f'bundles/consumption-phase4b-{V}.json'
known=read(bundle)['knowledge_available_at'] if bundle.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
manifest.update(bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,predecessor_bundle_id=old['bundle_id'],knowledge_available_at=known)
catalog=read(F/old['resource_paths']['rtc_code_catalog']);catalog['version']=V
captured=read(ROOT/'storage/app/phase4b-realization-history-draft-20261006-28/current-realization-history-snapshot.json')
analysis=copy.deepcopy(captured['rule']);analysis['first_acquisition_without_history_allowed']=True
policy={'contract':'LC214-TVM-REALIZATION-QUALIFIED-2026.10.06-1','status':'ACTIVE','published_at':known,
 'source_hash':catalog['financial_operation']['base']['source_hash'],
 'document_source_hash':'7296b225e15fb4b395a41a9413ada1190cc2229d64334bf78d7b69f0fb8019f9','analysis':analysis,
 'acceptance_status':'DEVELOPMENT_INTEGRAL_SCOPE28_NOT_YET_ACCEPTED','production_ready':False}
catalog['financial_operation']['base']['securities_realization']=policy
cp=f'catalogs/rtc-codes-{V}.json';save(F/cp,catalog);manifest['resource_paths']['rtc_code_catalog']=cp
manifest['content_hashes']['rtc_code_catalog']=digest(catalog);manifest['catalog_versions']['rtc_codes']=V
source=copy.deepcopy(next(s for s in manifest['official_sources'] if s['id']=='BCB_SGS11_OBSERVED_20261006_202609'))
observation=read(ROOT/'storage/app/phase4b-realization-history-draft-20261006-28/august-series-reobservation.json')
source.update(id='BCB_SGS11_OBSERVED_20261006_202608',file='bcb-sgs11-observed-20261006-202608.json',sha256=observation['sha256'],
 url=observation['url'],proven_effective_scope='Observed August2026 daily SGS11 rates for reproducing the previous closing position only;no taxpayer position approval.')
manifest['official_sources'].append(source)
controls=read(F/old['production_controls']['path']);controls['version']=V
for item in controls['controls']:
 if item['id'] in ['4B.4.BASE','4B.7.DOCUMENT']:
  item['gap']+=' Desenvolvimento28:parcelas atuais TVM com historico reproduzido integradas experimentalmente;escopo integral,perdas/casos restantes e ponte D1101/D2101 ainda em desenvolvimento.'
ctrl=f'production/phase4b-controls-{V}.json';save(F/ctrl,controls);manifest['production_controls']={'path':ctrl,'sha256':digest(controls)}
save(bundle,manifest)
scenario=read(F/'scenarios/dere-variable-yield-2026.10.06-27/constant-financial.json')
scenario.update(scenario_id='SYNTHETIC-current-private-tvm-realization',rule_bundle_id=manifest['bundle_id'],catalog_versions=manifest['catalog_versions'],knowledge_at=known)
ledger=scenario['facts']['rtc.financial_ledger'][0];ledger['entries'].pop()
input=copy.deepcopy(captured['input'])
curve_rows=[{k:r[k] for k in ['date','contract_curve_id','appropriation']} for r in input['position']['contract_curve']]
curve_value={'supplier_id':ledger['supplier_id'],'period':'2026-09','title':input['title'],
 'daily_memory_hash':captured['result']['daily_memory_hash'],'appropriation_excludes_fair_value':True,'curve_rows':curve_rows}
parent={'id':'tvm1','supplier_id':ledger['supplier_id'],'document_id':'current-tvm-realization-document','recognized_on':'2026-09-30',
 'qualification_evidence_id':'SYNTHETIC.TVM.CURRENT.QUALIFICATION','kind':'QUALIFIED_SECURITIES_REALIZATION','amount':'310.00',
 'securities_realization':{'analysis_input':input,'produced_curve_evidence':proof(curve_value,'SYNTHETIC.TVM.PRODUCED.CURVE'),'deduction_evidence':[]}}
ledger['entries'].append(parent)
value={'supplier_id':ledger['supplier_id'],'period_start':ledger['period_start'],'period_end':ledger['period_end'],
 'financial_entry_id':parent['id'],'document_id':parent['document_id'],'amount':parent['amount'],
 'analysis_input_hash':digest(input),'ledger_input_hash':digest(ledger)}
parent['securities_realization']['qualification_evidence']=proof(value,'SYNTHETIC.TVM.CURRENT.QUALIFICATION')
save(S/'mixed-realization-financial.json',scenario)
save(S/'mixed-realization-expected.json',{'net_base':'1010.00','component_revenue':'310.00','component_deductions':'0.00',
 'IBS':'1.01','CBS':'9.09','component_amounts':{'CURRENT_PRODUCED_RETURN':'150.00','DISPOSAL_NEW_PRODUCED_RETURN':'30.00',
  'DEFERRED_FAIR_VALUE_REALIZED':'80.00','NEW_FAIR_VALUE_REALIZED':'50.00'},'scope28_completed':False})
print('Scope28 development bundle frozen;current private components310 plus qualified net700 yield base1010;integral acceptance pending.')
