"""Capture an actual August disposal before preparing its subsequent September realizations."""
import copy, hashlib, json, subprocess, sys
from datetime import datetime, timezone
from decimal import Decimal
from pathlib import Path
from dere_daily_memory_oracle import digest, oracle, proof, seal

ROOT=Path(__file__).resolve().parents[1]; F=ROOT/'resources/fiscal'
PV='2026.10.08-28-draft22'; V='2026.10.08-28-draft23'
E=ROOT/'storage/app/phase4b-realization-public-intermediate-sale-draft-20261008-28'
S=F/('scenarios/tvm-public-intermediate-sale-'+V)
E.mkdir(parents=True,exist_ok=True); S.mkdir(parents=True,exist_ok=True)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:
  p.parent.mkdir(parents=True,exist_ok=True)
  with p.open('x',encoding='utf-8') as stream:stream.write(json.dumps(v,ensure_ascii=False,indent=2)+'\n')
def run(args):
 p=subprocess.run(['docker','compose','exec','-T','app','php','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8');assert p.returncode==0,p.stdout+p.stderr
 return json.loads(p.stdout)
if not (E/'historical-before.json').exists():
 baseline={}
 for folder in [F,ROOT/'storage/app']:
  for p in folder.rglob('*'):
   if p.is_file() and E not in p.parents and not any(v in str(p) for v in [PV,V]) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']:baseline[p.relative_to(ROOT).as_posix()]=hashlib.sha256(p.read_bytes()).hexdigest()
 save(E/'historical-before.json',baseline)
base=read(F/'catalogs/rtc-codes-2026.10.08-28-draft21.json')
aug=base['financial_operation']['base']['securities_realization']['analysis']['history_policy']['daily_memory_policies']['2026-08']
def bundle(version,previous=None):
 b=read(F/'bundles/consumption-phase4b-2026.10.08-28-draft21.json');c=copy.deepcopy(base);target=F/('bundles/consumption-phase4b-'+version+'.json')
 known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
 c['version']=version;q=c['financial_operation']['base']['public_offer_debt'];q['published_at']=known;a=q['sale_recognition_policy']['analysis'];a['published_at']=known;q['sale_recognition_policy']['published_at']=known
 h=a['history_policy'];h['published_at']=known
 if previous:
  h.pop('public_position_chain_policy');h['public_realized_closing_policy']='REPLAYED_PUBLIC_REALIZATION_CLOSING_AND_RETAINED_CURRENT_CURVE';h['permitted_bundle_manifest_hashes'].append(digest(previous))
 else:q['daily_memory_calculation']=copy.deepcopy(aug);a['daily_memory_calculation']=copy.deepcopy(aug);c['dere_debt_daily_memory_calculation']=copy.deepcopy(aug)
 c['financial_operation']['base']['securities_realization'].update(published_at=known,analysis=copy.deepcopy(a),public_offer_debt=copy.deepcopy(q))
 c['dere_monthly_closing_projection']['securities_realization_binding']['published_at']=known
 c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(q)
 cp='catalogs/rtc-codes-'+version+'.json';save(F/cp,c)
 ctrl=read(F/b['production_controls']['path']);ctrl['version']=version;ct='production/phase4b-controls-'+version+'.json';save(F/ct,ctrl)
 b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+version,version=version,knowledge_available_at=known)
 b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=version;b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
 return b,c
prior,pc=bundle(PV);current,c=bundle(V,prior)
keys=['assessed_produced_return','excluded_art195_return','removed_pis_cofins','deferred_fair_value','unoffset_public_title_loss']
zero=dict.fromkeys(['prior_produced_return','current_produced_return_already_in_curve','deferred_fair_value_realized','rounding_bridge'],'0')
def history(position,title,source,memory,source_position,policy,known,available,source_period,entry):
 scope={'supplier_id':position['supplier_id'],'period':position['period'],'title':title,'opening':position['opening']}
 closing={'contract':'TVM-RECOGNITION-CLOSING-MEMORY-1','position':source_position,'result':memory,'known_at':known,'policy_hash':digest(policy)}
 assignment=proof({'supplier_id':position['supplier_id'],'current_period':position['period'],'title':title,'snapshot_hash':digest(source),'financial_entry_id':'yield1','retained_share':'1'},'SYNTHETIC.INTERMEDIATE.ASSIGN.'+source_period,source_period+'-01',source_period+'-31')
 ref={'source_key':digest({'supplier_id':position['supplier_id'],'period':source_period,'document_id':entry['document_id'],'financial_entry_id':'yield1'}),'snapshot_hash':digest(source),'decision_hash':source['result']['decision_hash'],'snapshot_status':source['result']['status'],
      'period':source_period,'entry_id':'yield1','retained_share':'1','available':available,'retained':available,'assignment_evidence_hash':assignment['source_hash']}
 return {'closing_memory':closing,'fiscal_sources':[{'snapshot':source,'financial_entry_id':'yield1','retained_share':'1','assignment_evidence':assignment}],
  'complete_evidence':proof({'scope':scope,'sources':[ref],'closing_memory_hash':digest(closing),'opening_fiscal_components':available},'SYNTHETIC.INTERMEDIATE.COMPLETE.'+source_period,source_period+'-01',source_period+'-31')}
if sys.argv[1]=='prepare':
 snapshot=read(ROOT/'storage/app/phase4b-realization-public-mixed-history-draft-20261008-28/net-negative-2026-07-snapshot.json')
 entry=next(t['base']['entries'][0] for t in snapshot['result']['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE');q=entry['d2101_binding'];pp=entry['public_offer_debt']['position'];pm=q['calculation'];title=entry['public_offer_debt']['title']
 p=copy.deepcopy(read(F/'scenarios/tvm-public-mixed-history-2026.10.08-28-draft21/net-negative-2026-08-financial.json')['facts']['rtc.financial_ledger'][0]['entries'][0]['public_offer_debt']['position'])
 p['vPisCofins']='5.00';p['operations']=[{'id':'sale1','date':'2026-08-14','sequence':1,'kind':'SELL','quantity':'400','book_amount':'4060.00','sale_proceeds':'4060.00'}];p['appropriations']={'2026-08-10':'50'}
 curve,monthly,independent=oracle(p,aug['series']);p.pop('appropriations');p.update(contract_curve=curve,declared_monthly=monthly);p.pop('memory_evidence');p['memory_evidence']=proof(p,'SYNTHETIC.INTERMEDIATE.AUG.MEMORY','2026-08-01','2026-08-31')
 available=dict.fromkeys(keys,'0.00000000');available['assessed_produced_return']='100.00000000'
 h=history(p,title,snapshot,pm,pp,base['financial_operation']['base']['securities_realization']['analysis']['history_policy']['daily_memory_policies']['2026-07'],snapshot['input']['knowledge_at'],available,'2026-07',entry)
 save(S/'prior-august-current.json',{'position':p,'title':title,'recognition_history':h,'independent_oracle':independent,
  'components':{'sale1':{**zero,'prior_produced_return':'40','current_produced_return_already_in_curve':'20','new_produced_return_at_sale':'0','new_fair_value_change_at_sale':'0'}},
  'recognition_inventory':{'opening':{**zero,'prior_produced_return':'100'},'closing':{**zero,'prior_produced_return':'60','current_produced_return_already_in_curve':'30'},'other_releases':[],
   'closing_fiscal_components':{'assessed_produced_return':'60.00000000','excluded_art195_return':'0.00000000','removed_pis_cofins':'0.00000000','unoffset_public_title_loss':'0.00000000'}},
  'expected':{'revenue':'0.00','deductions':'0.00','base':'745.00','auxiliary_base':'45.00'}})
 print('August prior movement fixture prepared; capture its financial input before current stage.');sys.exit(0)
assert sys.argv[1]=='current'
source=S/'prior-august-financial.json';snapshot_path=E/'prior-august-snapshot.json';args=['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),'--bundle-manifest=bundles/consumption-phase4b-'+PV+'.json']
if not snapshot_path.exists():args.append('--snapshot='+snapshot_path.relative_to(ROOT).as_posix())
result=run(args);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'];save(E/'prior-august-result.json',result)
snapshot=read(snapshot_path);ledger=snapshot['input']['facts']['rtc.financial_ledger'][0];base_result=next(t['base'] for t in result['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE')
entry=next(e for e in base_result['entries'] if e['id']=='yield1');q=entry['d2101_binding'];pp=entry['public_offer_debt']['position'];pm=q['calculation'];title=entry['public_offer_debt']['title']
assert base_result['net_base']=='745.00' and pm['monthly']['vSaldoContFin']=='6090.00' and pm['closing_quantity']=='600.00000000'
available=dict(zip(keys,['87.00000000','0.00000000','3.00000000','0.00000000','0.00000000']))
for name,quantity in [('partial',300),('complete',600)]:
 p=copy.deepcopy(pp);p.update(period='2026-09',first_acquisition=False)
 p['opening']={'quantity':pm['closing_quantity'],'unit_cost':pm['closing_unit_cost'],'acquisition_cost':pm['closing_acquisition_cost'],'book_balance':pm['monthly']['vSaldoContFin']}
 p['previous_position_evidence']=proof({'supplier_id':p['supplier_id'],'idTitulo':p['idTitulo'],'cCta':p['cCta'],'period':'2026-08','closing':p['opening']},'SYNTHETIC.INTERMEDIATE.SEP.PREVIOUS','2026-08-01','2026-08-31')
 p['method_evidence']=proof({'contract':aug['contract'],'method':p['method']},'SYNTHETIC.INTERMEDIATE.SEP.METHOD')
 fraction=Decimal(quantity)/600;remaining=1-fraction;book=Decimal(6140)*fraction
 p['operations']=[{'id':'sale1','date':'2026-09-14','sequence':1,'kind':'SELL','quantity':str(quantity),'book_amount':format(book,'.2f'),'sale_proceeds':format(book,'.2f')}];p['appropriations']={'2026-09-10':'50'}
 curve,monthly,independent=oracle(p,c['dere_debt_daily_memory_calculation']['series']);p.pop('appropriations');p.update(contract_curve=curve,declared_monthly=monthly);seal(p)
 h=history(p,title,snapshot,pm,pp,aug,prior['knowledge_available_at'],available,'2026-08',entry)
 save(S/(name+'-current.json'),{'position':p,'title':title,'recognition_history':h,'independent_oracle':independent,
  'components':{'sale1':{**zero,'prior_produced_return':format(Decimal(90)*fraction,'.8f'),'current_produced_return_already_in_curve':format(Decimal(50)*fraction,'.8f'),'new_produced_return_at_sale':'0','new_fair_value_change_at_sale':'0'}},
  'recognition_inventory':{'opening':{**zero,'prior_produced_return':'90'},'closing':{**zero,'prior_produced_return':format(Decimal(90)*remaining,'.8f'),'current_produced_return_already_in_curve':format(Decimal(50)*remaining,'.8f')},'other_releases':[],
   'closing_fiscal_components':{key:format(Decimal(available[key])*remaining,'.8f') for key in keys if key!='deferred_fair_value'}},
  'expected':{'revenue':'0.00','deductions':'0.00','base':format(Decimal(700)+Decimal(monthly['vApur']),'.2f'),'auxiliary_base':monthly['vApur'],'fair_revenue':'0.00','fair_deductions':'0.00','opening_fiscal_components':available}})
print('Actual prior August disposal captured and two subsequent September realizations prepared.')
