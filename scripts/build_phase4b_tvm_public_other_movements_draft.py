"""Capture public amortization, coupon and additional acquisition before later disposal."""
import copy,hashlib,json,subprocess,sys
from datetime import datetime,timezone
from decimal import Decimal
from pathlib import Path
from dere_daily_memory_oracle import digest,oracle,proof,seal
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';PV='2026.10.08-28-draft24';V='2026.10.08-28-draft25'
E=ROOT/'storage/app/phase4b-realization-public-other-movements-draft-20261008-28';S=F/('scenarios/tvm-public-other-movements-'+V)
E.mkdir(parents=True,exist_ok=True);S.mkdir(parents=True,exist_ok=True)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:
  p.parent.mkdir(parents=True,exist_ok=True)
  with p.open('x',encoding='utf-8') as stream:stream.write(json.dumps(v,ensure_ascii=False,indent=2)+'\n')
def run(args):
 p=subprocess.run(['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8');assert p.returncode==0,p.stdout+p.stderr
 return json.loads(p.stdout)
if not (E/'historical-before.json').exists():
 baseline={}
 for folder in [F,ROOT/'storage/app']:
  for p in folder.rglob('*'):
   if p.is_file() and E not in p.parents and not any(v in str(p) for v in [PV,V]) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']:baseline[p.relative_to(ROOT).as_posix()]=hashlib.sha256(p.read_bytes()).hexdigest()
 save(E/'historical-before.json',baseline)
def bundle(version,predecessor,prior=None):
 b=read(F/('bundles/consumption-phase4b-'+predecessor+'.json'));c=read(F/b['resource_paths']['rtc_code_catalog']);target=F/('bundles/consumption-phase4b-'+version+'.json')
 known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
 c['version']=version;q=c['financial_operation']['base']['public_offer_debt'];q['published_at']=known;a=q['sale_recognition_policy']['analysis'];a['published_at']=known;a['history_policy']['published_at']=known;q['sale_recognition_policy']['published_at']=known
 if prior:a['history_policy']['permitted_bundle_manifest_hashes'].append(digest(prior))
 c['financial_operation']['base']['securities_realization'].update(published_at=known,analysis=copy.deepcopy(a),public_offer_debt=copy.deepcopy(q))
 c['dere_monthly_closing_projection']['securities_realization_binding']['published_at']=known;c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(q)
 cp='catalogs/rtc-codes-'+version+'.json';save(F/cp,c);ctrl=read(F/b['production_controls']['path']);ctrl['version']=version;ct='production/phase4b-controls-'+version+'.json';save(F/ct,ctrl)
 b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+version,version=version,knowledge_available_at=known);b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=version;b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
 return b,c
prior,pc=bundle(PV,'2026.10.08-28-draft22');current,c=bundle(V,'2026.10.08-28-draft23',prior)
keys=['assessed_produced_return','excluded_art195_return','removed_pis_cofins','deferred_fair_value','unoffset_public_title_loss']
zero=dict.fromkeys(['prior_produced_return','current_produced_return_already_in_curve','deferred_fair_value_realized','rounding_bridge'],'0')
def previous(position,title,snapshot,memory,pp,policy,known,available,source_period,entry):
 scope={'supplier_id':position['supplier_id'],'period':position['period'],'title':title,'opening':position['opening']};closing={'contract':'TVM-RECOGNITION-CLOSING-MEMORY-1','position':pp,'result':memory,'known_at':known,'policy_hash':digest(policy)}
 assignment=proof({'supplier_id':position['supplier_id'],'current_period':position['period'],'title':title,'snapshot_hash':digest(snapshot),'financial_entry_id':'yield1','retained_share':'1'},'SYNTHETIC.OTHER.MOVEMENTS.ASSIGN.'+source_period,source_period+'-01',source_period+'-31')
 ref={'source_key':digest({'supplier_id':position['supplier_id'],'period':source_period,'document_id':entry['document_id'],'financial_entry_id':'yield1'}),'snapshot_hash':digest(snapshot),'decision_hash':snapshot['result']['decision_hash'],'snapshot_status':snapshot['result']['status'],
      'period':source_period,'entry_id':'yield1','retained_share':'1','available':available,'retained':available,'assignment_evidence_hash':assignment['source_hash']}
 return {'closing_memory':closing,'fiscal_sources':[{'snapshot':snapshot,'financial_entry_id':'yield1','retained_share':'1','assignment_evidence':assignment}],
  'complete_evidence':proof({'scope':scope,'sources':[ref],'closing_memory_hash':digest(closing),'opening_fiscal_components':available},'SYNTHETIC.OTHER.MOVEMENTS.COMPLETE.'+source_period,source_period+'-01',source_period+'-31')}
if sys.argv[1]=='prepare':
 original=read(F/'scenarios/tvm-public-intermediate-sale-2026.10.08-28-draft23/prior-august-current.json')
 for case in ['amortization','coupon','purchase']:
  f=copy.deepcopy(original);p=f['position'];title=f['title'];p['appropriations']={'2026-08-10':'50'};other=[]
  if case=='purchase':
   p['operations']=[{'id':'buy1','date':'2026-08-05','sequence':1,'kind':'BUY','quantity':'1000','unit_price':'12','book_amount':'12000.00'},
    {'id':'sale1','date':'2026-08-14','sequence':1,'kind':'SELL','quantity':'1000','book_amount':'11075.00','sale_proceeds':'11075.00'}]
   released_prior,released_current,closing_prior,closing_current='50','25','50','25'
  else:
   op={'id':'release1','date':'2026-08-12','sequence':1,'kind':'AMORTIZATION' if case=='amortization' else 'COUPON','book_amount':'2030.00' if case=='amortization' else '30.00'}
   if case=='amortization':op['acquisition_cost_reduction']='2000.00000000'
   else:title['vJurosRec']='30.00'
   p['operations']=[op,{'id':'sale1','date':'2026-08-14','sequence':1,'kind':'SELL','quantity':'500','book_amount':'4060.00' if case=='amortization' else '5060.00','sale_proceeds':'4060.00' if case=='amortization' else '5060.00'}]
   other=[{'operation_id':'release1','components':{**zero,'prior_produced_return':'20','current_produced_return_already_in_curve':'10'},
    'fiscal_components':{'assessed_produced_return':'20.00000000','excluded_art195_return':'0.00000000','removed_pis_cofins':'0.00000000','unoffset_public_title_loss':'0.00000000'}}]
   released_prior,released_current,closing_prior,closing_current='40','20','40','20'
  policy=pc['dere_debt_daily_memory_calculation'];curve,monthly,independent=oracle(p,policy['series']);p.pop('appropriations');p.update(contract_curve=curve,declared_monthly=monthly);p.pop('memory_evidence');p['memory_evidence']=proof(p,'SYNTHETIC.OTHER.MOVEMENTS.AUG.'+case,'2026-08-01','2026-08-31')
  snapshot=f['recognition_history']['fiscal_sources'][0]['snapshot'];entry=next(t['base']['entries'][0] for t in snapshot['result']['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE');pm=entry['d2101_binding']['calculation'];pp=entry['public_offer_debt']['position'];available=dict.fromkeys(keys,'0.00000000');available['assessed_produced_return']='100.00000000'
  f['recognition_history']=previous(p,title,snapshot,pm,pp,pc['financial_operation']['base']['securities_realization']['analysis']['history_policy']['daily_memory_policies']['2026-07'],snapshot['input']['knowledge_at'],available,'2026-07',entry)
  f['components']={'sale1':{**zero,'prior_produced_return':released_prior,'current_produced_return_already_in_curve':released_current,'new_produced_return_at_sale':'0','new_fair_value_change_at_sale':'0'}}
  f['historical_allocations']={'sale1':{'previously_assessed_produced_return':format(Decimal(released_prior),'.8f')}}
  f['recognition_inventory']={'opening':{**zero,'prior_produced_return':'100'},'closing':{**zero,'prior_produced_return':closing_prior,'current_produced_return_already_in_curve':closing_current},'other_releases':other,
   'closing_fiscal_components':{'assessed_produced_return':format(Decimal(closing_prior),'.8f'),'excluded_art195_return':'0.00000000','removed_pis_cofins':'0.00000000','unoffset_public_title_loss':'0.00000000'}}
  f['independent_oracle']=independent;f['expected'].update(base=format(Decimal(700)+Decimal(monthly['vApur']),'.2f'),auxiliary_base=monthly['vApur']);f.update(document_id='SYNTHETIC.PUBLIC.MOVEMENTS.'+case.upper()+'.YIELD',scenario_id='SYNTHETIC-public-other-movements-prior-'+case)
  save(S/('prior-'+case+'-current.json'),f)
 print('Three prior movement fixtures prepared.');sys.exit(0)
assert sys.argv[1]=='current'
for case in ['amortization','coupon','purchase']:
 source=S/('prior-'+case+'-financial.json');snapshot_path=E/('prior-'+case+'-snapshot.json');args=['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),'--bundle-manifest=bundles/consumption-phase4b-'+PV+'.json']
 if not snapshot_path.exists():args.append('--snapshot='+snapshot_path.relative_to(ROOT).as_posix())
 result=run(args);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'];save(E/('prior-'+case+'-result.json'),result)
 snapshot=read(snapshot_path);base=next(t['base'] for t in result['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE');entry=next(e for e in base['entries'] if e['id']=='yield1');q=entry['d2101_binding'];pm=q['calculation'];pp=entry['public_offer_debt']['position'];title=copy.deepcopy(entry['public_offer_debt']['title']);title['vJurosRec']='0.00'
 available=dict(zip(keys,['72.50000000','0.00000000','2.50000000','0.00000000','0.00000000'] if case=='purchase' else ['58.00000000','0.00000000','2.00000000','0.00000000','0.00000000']))
 prior_net=sum(Decimal(v) for v in available.values());assert prior_net==Decimal(pm['monthly']['vSaldoContFin'])-Decimal(pm['closing_acquisition_cost'])
 opening_qty=Decimal(pm['closing_quantity'])
 for mode,fraction in [('partial',Decimal('.5')),('complete',Decimal(1))]:
  name=case+'-'+mode;p=copy.deepcopy(pp);p.update(period='2026-09',first_acquisition=False)
  p['opening']={'quantity':pm['closing_quantity'],'unit_cost':pm['closing_unit_cost'],'acquisition_cost':pm['closing_acquisition_cost'],'book_balance':pm['monthly']['vSaldoContFin']}
  p['previous_position_evidence']=proof({'supplier_id':p['supplier_id'],'idTitulo':p['idTitulo'],'cCta':p['cCta'],'period':'2026-08','closing':p['opening']},'SYNTHETIC.OTHER.MOVEMENTS.PREVIOUS','2026-08-01','2026-08-31');p['method_evidence']=proof({'contract':c['dere_debt_daily_memory_calculation']['contract'],'method':p['method']},'SYNTHETIC.OTHER.MOVEMENTS.METHOD')
  book=(Decimal(p['opening']['book_balance'])+50)*fraction
  p['operations']=[{'id':'sale1','date':'2026-09-14','sequence':1,'kind':'SELL','quantity':format(opening_qty*fraction,'.8f'),'book_amount':format(book,'.2f'),'sale_proceeds':format(book,'.2f')}];p['appropriations']={'2026-09-10':'50'}
  curve,monthly,independent=oracle(p,c['dere_debt_daily_memory_calculation']['series']);p.pop('appropriations');p.update(contract_curve=curve,declared_monthly=monthly);seal(p)
  h=previous(p,title,snapshot,pm,pp,pc['dere_debt_daily_memory_calculation'],prior['knowledge_available_at'],available,'2026-08',entry);remaining=1-fraction
  save(S/(name+'-current.json'),{'position':p,'title':title,'recognition_history':h,'independent_oracle':independent,
   'components':{'sale1':{**zero,'prior_produced_return':format(prior_net*fraction,'.8f'),'current_produced_return_already_in_curve':format(Decimal(50)*fraction,'.8f'),'new_produced_return_at_sale':'0','new_fair_value_change_at_sale':'0'}},
   'recognition_inventory':{'opening':{**zero,'prior_produced_return':format(prior_net,'.8f')},'closing':{**zero,'prior_produced_return':format(prior_net*remaining,'.8f'),'current_produced_return_already_in_curve':format(Decimal(50)*remaining,'.8f')},'other_releases':[],
    'closing_fiscal_components':{key:format(Decimal(available[key])*remaining,'.8f') for key in keys if key!='deferred_fair_value'}},
   'expected':{'revenue':'0.00','deductions':'0.00','base':format(Decimal(700)+Decimal(monthly['vApur']),'.2f'),'auxiliary_base':monthly['vApur'],'fair_revenue':'0.00','fair_deductions':'0.00','opening_fiscal_components':available}})
print('Three actual prior decisions captured and six subsequent realization fixtures prepared.')
