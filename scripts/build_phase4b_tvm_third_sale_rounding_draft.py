"""Prepare September third-period openings from captured August sales, keeping fiscal-covered and economic-only residues distinct."""
import copy,hashlib,json,subprocess
from pathlib import Path
from datetime import datetime,timezone
from decimal import Decimal
from dere_daily_memory_oracle import digest,proof
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.08-28-draft37';PV='2026.10.08-28-draft36'
E=ROOT/'storage/app/phase4b-realization-third-sale-rounding-draft-20261008-28';S=F/('scenarios/tvm-third-sale-rounding-'+V);PE=ROOT/'storage/app/phase4b-realization-three-period-august-draft-20261008-28'
E.mkdir(parents=True,exist_ok=True);S.mkdir(parents=True,exist_ok=True)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,value):
 if p.exists():assert read(p)==value,p
 else:
  with p.open('x',encoding='utf-8') as out:out.write(json.dumps(value,ensure_ascii=False,indent=2)+'\n')
  if p.stat().st_size>1000000:
   before=sha(p);r=subprocess.run(['compact.exe','/C','/F','/I','/Q','/EXE:LZX',str(p)],capture_output=True);assert r.returncode==0 and sha(p)==before,p
if not (E/'historical-before.json').exists():
 baseline={}
 for folder in [F,ROOT/'storage/app']:
  for p in folder.rglob('*'):
   if p.is_file() and E not in p.parents and V not in str(p) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']:baseline[p.relative_to(ROOT).as_posix()]=sha(p)
 save(E/'historical-before.json',baseline)
prior=read(F/('bundles/consumption-phase4b-'+PV+'.json'));b=read(F/'bundles/consumption-phase4b-2026.10.08-28-draft34.json');c=read(F/b['resource_paths']['rtc_code_catalog']);target=F/('bundles/consumption-phase4b-'+V+'.json');known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
method='REPLAYED_PUBLIC_CREATED_ROUNDING_CLOSING_WITH_PROVED_CURRENT_ALLOCATION';c['version']=V;q=c['financial_operation']['base']['public_offer_debt'];q['published_at']=known;a=q['sale_recognition_policy']['analysis'];a['published_at']=known;q['sale_recognition_policy']['published_at']=known;h=a['history_policy'];h['published_at']=known
for key in ['public_explicit_net_closing_policy','public_realized_closing_policy','public_position_chain_policy']:h.pop(key,None)
h['public_created_rounding_closing_policy']=method;pc=read(F/prior['resource_paths']['rtc_code_catalog']);h['daily_memory_policies']['2026-08']=copy.deepcopy(pc['dere_debt_daily_memory_calculation']);h['permitted_bundle_manifest_hashes'].append(digest(prior))
c['financial_operation']['base']['securities_realization'].update(published_at=known,analysis=copy.deepcopy(a),public_offer_debt=copy.deepcopy(q));c['dere_monthly_closing_projection']['securities_realization_binding']['published_at']=known;c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(q)
cp='catalogs/rtc-codes-'+V+'.json';save(F/cp,c);ctrl=read(F/b['production_controls']['path']);ctrl['version']=V;ct='production/phase4b-controls-'+V+'.json';save(F/ct,ctrl);b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,knowledge_available_at=known);b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V;b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
keys=['assessed_produced_return','excluded_art195_return','removed_pis_cofins','deferred_fair_value','unoffset_public_title_loss']
names=[sign+'-partial' for sign in ['negative','positive','zero','exact']]+['positive-aligned-partial','negative-aligned-partial']
for case in names:
 path=PE/(case+'-snapshot.json');source=read(path);base=next(t['base'] for t in source['result']['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE');entry=next(e for e in base['entries'] if e['id']=='yield1');binding=entry['d2101_binding'];analysis=binding['sale_recognition_binding']['analysis'];inventory=analysis['recognition_inventory'];memory=binding['calculation'];position=entry['public_offer_debt']['position'];title=entry['public_offer_debt']['title'];split=inventory['rounding_inventory']
 current=dict.fromkeys(keys,Decimal(0));current.update(assessed_produced_return=Decimal(binding['taxable_amount']),excluded_art195_return=Decimal(binding['art195_excess']),removed_pis_cofins=Decimal(binding['pis_deduction_applied']))
 curve=Decimal(inventory['closing']['current_produced_return_already_in_curve']);fraction=curve/sum(current.values());retained={k:v*fraction for k,v in current.items()};realized={k:current[k]-retained[k] for k in keys};format_values=lambda v:{k:format(a,'.8f') for k,a in v.items()}
 old={k:Decimal(inventory['closing_fiscal_components'].get(k,'0')) for k in keys};available={k:old[k]+retained[k] for k in keys};carrying={'prior_produced_return':format(Decimal(inventory['closing']['prior_produced_return'])+curve,'.8f'),'rounding_bridge':format(Decimal(inventory['closing']['rounding_bridge']),'.8f'),'fiscal_covered_rounding':format(Decimal(split['closing_existing']),'.8f'),'uncovered_created_rounding':format(Decimal(split['closing_created']),'.8f')}
 opening={'quantity':memory['closing_quantity'],'unit_cost':memory['closing_unit_cost'],'acquisition_cost':memory['closing_acquisition_cost'],'book_balance':memory['monthly']['vSaldoContFin']};scope={'supplier_id':position['supplier_id'],'period':'2026-09','title':title,'opening':opening};closing={'contract':'TVM-RECOGNITION-CLOSING-MEMORY-1','position':position,'result':memory,'known_at':source['input']['knowledge_at'],'policy_hash':digest(h['daily_memory_policies']['2026-08'])}
 allocation={'retained':format_values(retained),'realized':format_values(realized)};value={'supplier_id':scope['supplier_id'],'source_period':'2026-08','current_period':'2026-09','title':title,'snapshot_hash':digest(source),'decision_hash':source['result']['decision_hash'],'financial_entry_id':'yield1','method':method,'daily_memory_hash':digest(memory),'analysis_hash':digest(analysis),'monthly_fiscal_components':format_values(current),'current_fiscal_allocation':allocation,'previous_fiscal_closing':inventory['closing_fiscal_components'],'opening_fiscal_components':format_values(available),'opening_carrying_components':carrying,'fiscal_origin':'D2101','normative_homologation_claimed':False}
 evidence=proof(value,'SYNTHETIC.CREATED.ROUNDING.CLOSING.'+case,'2026-08-01','2026-08-31');assignment=proof({'supplier_id':scope['supplier_id'],'current_period':'2026-09','title':title,'snapshot_hash':digest(source),'financial_entry_id':'yield1','retained_share':'1'},'SYNTHETIC.CREATED.ROUNDING.ASSIGN.'+case,'2026-08-01','2026-08-31')
 ref={'source_key':digest({'supplier_id':scope['supplier_id'],'period':position['period'],'document_id':entry['document_id'],'financial_entry_id':'yield1'}),'snapshot_hash':digest(source),'decision_hash':source['result']['decision_hash'],'snapshot_status':source['result']['status'],'period':'2026-08','entry_id':'yield1','retained_share':'1','available':format_values(available),'retained':format_values(available),'assignment_evidence_hash':assignment['source_hash'],'created_rounding_closing_evidence_hash':evidence['source_hash']}
 history={'closing_memory':closing,'fiscal_sources':[{'snapshot':source,'financial_entry_id':'yield1','retained_share':'1','assignment_evidence':assignment,'current_fiscal_allocation':allocation,'created_rounding_closing_evidence':evidence}],'complete_evidence':proof({'scope':scope,'sources':[ref],'closing_memory_hash':digest(closing),'opening_fiscal_components':format_values(available),'opening_carrying_components':carrying},'SYNTHETIC.CREATED.ROUNDING.COMPLETE.'+case,'2026-08-01','2026-08-31')}
 save(S/(case+'-opening.json'),{'scope':scope,'history':history,'expected':{'opening_fiscal_components':format_values(available),'opening_carrying_components':carrying},'source_path':path.relative_to(ROOT).as_posix(),'source_sha256':sha(path),'monthly_tax_accepted':False})
save(E/'research.json',{'reference_research':(PE/'research.json').relative_to(ROOT).as_posix(),'reference_sha256':sha(PE/'research.json'),'method':method,'allocation_basis':'Synthetic independently supplied per-component realized/retained assignment; fixture half allocation is not an engine inference or a universal normative rule','new_rounding_basis':'Remaining newly created rounding is economic only, distinct from the fiscal-covered old rounding','october_full_series_used':False,'normative_homologation_claimed':False})
print(json.dumps({'opening_fixtures':len(names),'monthly_tax_accepted':False}))
