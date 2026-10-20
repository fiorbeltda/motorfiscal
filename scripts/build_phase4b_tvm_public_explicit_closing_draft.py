"""Prepare replayable August first-acquisition sources for explicit signed closing transport."""
import copy,hashlib,json,sys,subprocess
from pathlib import Path
from datetime import datetime,timezone
from decimal import Decimal,ROUND_HALF_EVEN
from dere_daily_memory_oracle import digest,oracle,proof
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';PV='2026.10.08-28-draft31';V='2026.10.08-28-draft32'
E=ROOT/'storage/app/phase4b-realization-public-explicit-closing-draft-20261008-28';S=F/('scenarios/tvm-public-explicit-closing-'+V)
E.mkdir(parents=True,exist_ok=True);S.mkdir(parents=True,exist_ok=True)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:
  with p.open('x',encoding='utf-8') as out:out.write(json.dumps(v,ensure_ascii=False,indent=2)+'\n')
if not (E/'historical-before.json').exists():
 baseline={}
 for folder in [F,ROOT/'storage/app']:
  for p in folder.rglob('*'):
   if p.is_file() and E not in p.parents and not any(v in str(p) for v in [PV,V]) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']:baseline[p.relative_to(ROOT).as_posix()]=hashlib.sha256(p.read_bytes()).hexdigest()
 save(E/'historical-before.json',baseline)
def bundle(version,prior=None):
 b=read(F/'bundles/consumption-phase4b-2026.10.08-28-draft30.json');c=read(F/b['resource_paths']['rtc_code_catalog']);target=F/('bundles/consumption-phase4b-'+version+'.json')
 known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
 c['version']=version;q=c['financial_operation']['base']['public_offer_debt'];q['published_at']=known;a=q['sale_recognition_policy']['analysis'];a['published_at']=known;q['sale_recognition_policy']['published_at']=known;h=a['history_policy'];h['published_at']=known
 if prior:
  h.pop('public_realized_closing_policy',None);h.pop('public_position_chain_policy',None)
  h['public_explicit_net_closing_policy']='REPLAYED_PUBLIC_FIRST_ACQUISITION_NET_CLOSING_WITH_CARRYING_ROUNDING';h['permitted_bundle_manifest_hashes'].append(digest(prior))
 else:
  aug=h['daily_memory_policies']['2026-08'];q['daily_memory_calculation']=copy.deepcopy(aug);a['daily_memory_calculation']=copy.deepcopy(aug);c['dere_debt_daily_memory_calculation']=copy.deepcopy(aug)
 c['financial_operation']['base']['securities_realization'].update(published_at=known,analysis=copy.deepcopy(a),public_offer_debt=copy.deepcopy(q))
 c['dere_monthly_closing_projection']['securities_realization_binding']['published_at']=known;c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(q)
 cp='catalogs/rtc-codes-'+version+'.json';save(F/cp,c);ctrl=read(F/b['production_controls']['path']);ctrl['version']=version;ct='production/phase4b-controls-'+version+'.json';save(F/ct,ctrl)
 b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+version,version=version,knowledge_available_at=known);b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=version;b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
 return b,c
prior,pc=bundle(PV);current,c=bundle(V,prior)
for case in ['negative','positive','zero','exact'] if sys.argv[1]=='prepare' else []:
 f=read(F/('scenarios/tvm-public-gross-rounding-2026.10.08-28-draft30/'+case+'-partial-current.json'));p=f['position'];p['period']='2026-08';p.pop('memory_evidence',None)
 appropriations={d['date'].replace('2026-09','2026-08'):d['appropriation'] for d in p['contract_curve'] if Decimal(d['appropriation'])}
 for op in p['operations']:op['date']=op['date'].replace('2026-09','2026-08')
 p['method_evidence']=proof({'contract':pc['dere_debt_daily_memory_calculation']['contract'],'method':p['method']},'SYNTHETIC.EXPLICIT.CLOSING.AUG.METHOD','2026-08-01','2026-08-31')
 p['appropriations']=appropriations;curve,monthly,independent=oracle(p,pc['dere_debt_daily_memory_calculation']['series']);p.pop('appropriations');p.update(contract_curve=curve,declared_monthly=monthly)
 p['memory_evidence']=proof(p,'SYNTHETIC.EXPLICIT.CLOSING.AUG.MEMORY','2026-08-01','2026-08-31');f['independent_oracle']=independent
 f['expected']['auxiliary_base']=monthly['vApur'];f['expected']['base']=format(Decimal(700)+Decimal(monthly['vApur']),'.2f');f['source_case']=case
 save(S/('prior-'+case+'-current.json'),f)
if sys.argv[1]=='prepare':
 print('Four August sources and draft31/draft32 policies prepared; fiscal transport is not yet accepted.');sys.exit(0)
assert sys.argv[1]=='current'
def run(args):
 p=subprocess.run(['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8');assert p.returncode==0,p.stdout[:3000]+p.stderr
 return json.loads(p.stdout)
keys=['assessed_produced_return','excluded_art195_return','removed_pis_cofins','deferred_fair_value','unoffset_public_title_loss']
zero=dict.fromkeys(['prior_produced_return','current_produced_return_already_in_curve','deferred_fair_value_realized','rounding_bridge'],'0')
for case in ['negative','positive','zero','exact']:
 snap=E/('prior-'+case+'-snapshot.json');args=['fiscal:rtc:run',(S/('prior-'+case+'-financial.json')).relative_to(ROOT).as_posix(),'--bundle-manifest=bundles/consumption-phase4b-'+PV+'.json']
 if not snap.exists():args.append('--snapshot='+snap.relative_to(ROOT).as_posix())
 result=run(args);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'];source=read(snap)
 replay=run(['fiscal:rtc:replay',snap.relative_to(ROOT).as_posix()]);assert replay['replay_matches'];save(E/('prior-'+case+'-replay.json'),replay)
 report=E/('prior-'+case+'-monthly-report.json');args=['fiscal:dere:project-monthly-closing',snap.relative_to(ROOT).as_posix(),(S/('prior-'+case+'-month.json')).relative_to(ROOT).as_posix(),'--bundle-manifest=bundles/consumption-phase4b-'+PV+'.json']
 if not report.exists():args+=['--report='+report.relative_to(ROOT).as_posix(),'--candidate-output='+(E/('prior-'+case+'-candidate.xml')).relative_to(ROOT).as_posix()]
 projected=run(args);assert projected['projection']['status']=='PROJECTED' and not projected['production_ready']
 base=next(t['base'] for t in result['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE');entry=next(e for e in base['entries'] if e['id']=='yield1');q=entry['d2101_binding'];analysis=q['sale_recognition_binding']['analysis'];pm=q['calculation'];pp=entry['public_offer_debt']['position'];title=entry['public_offer_debt']['title']
 monthly=dict.fromkeys(keys,'0.00000000');monthly.update(assessed_produced_return=format(Decimal(q['taxable_amount']),'.8f'),excluded_art195_return=format(Decimal(q['art195_excess']),'.8f'),removed_pis_cofins=format(Decimal(q['pis_deduction_applied']),'.8f'))
 if q['gross_nature']=='D':monthly['unoffset_public_title_loss']=format(-Decimal(q['unoffset_title_loss']),'.8f')
 sale=analysis['realizations'][0];realized=Decimal(sale['book_value_released'])-Decimal(sale['acquisition_cost_released']);carrying={'prior_produced_return':format(Decimal(analysis['recognition_inventory']['closing']['current_produced_return_already_in_curve']),'.8f'),'rounding_bridge':format(Decimal(analysis['recognition_inventory']['closing']['rounding_bridge']),'.8f')}
 available=copy.deepcopy(monthly);key='unoffset_public_title_loss' if case=='negative' else 'assessed_produced_return';available[key]=format(Decimal(available[key])-realized,'.8f')
 assert sum(Decimal(v) for v in available.values())==Decimal(pm['monthly']['vSaldoContFin'])-Decimal(pm['closing_acquisition_cost'])
 for mode,fraction in [('partial',Decimal('.5')),('complete',Decimal(1))]:
  name=case+'-'+mode;p=copy.deepcopy(pp);p.pop('memory_evidence',None);p.update(period='2026-09',first_acquisition=False,vPisCofins='0.00')
  p['opening']={'quantity':pm['closing_quantity'],'unit_cost':pm['closing_unit_cost'],'acquisition_cost':pm['closing_acquisition_cost'],'book_balance':pm['monthly']['vSaldoContFin']}
  p['previous_position_evidence']=proof({'supplier_id':p['supplier_id'],'idTitulo':p['idTitulo'],'cCta':p['cCta'],'period':'2026-08','closing':p['opening']},'SYNTHETIC.EXPLICIT.PREVIOUS','2026-08-01','2026-08-31')
  p['method_evidence']=proof({'contract':c['dere_debt_daily_memory_calculation']['contract'],'method':p['method']},'SYNTHETIC.EXPLICIT.METHOD')
  appropriated=Decimal('.02') if case=='zero' else Decimal('.01');book=(Decimal(p['opening']['book_balance'])+appropriated)*fraction;assert book==book.quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
  p['operations']=[{'id':'sale1','date':'2026-09-14','sequence':1,'kind':'SELL','quantity':format(Decimal(p['opening']['quantity'])*fraction,'.8f'),'book_amount':format(book,'.2f'),'sale_proceeds':format(book,'.2f')}];p['appropriations']={'2026-09-10':str(appropriated)}
  curve,monthly_current,independent=oracle(p,c['dere_debt_daily_memory_calculation']['series']);p.pop('appropriations');p.update(contract_curve=curve,declared_monthly=monthly_current);p['memory_evidence']=proof(p,'SYNTHETIC.EXPLICIT.MEMORY')
  scope={'supplier_id':p['supplier_id'],'period':p['period'],'title':title,'opening':p['opening']};closing={'contract':'TVM-RECOGNITION-CLOSING-MEMORY-1','position':pp,'result':pm,'known_at':source['input']['knowledge_at'],'policy_hash':digest(pc['dere_debt_daily_memory_calculation'])}
  allocation=proof({'supplier_id':p['supplier_id'],'source_period':'2026-08','current_period':'2026-09','title':title,'snapshot_hash':digest(source),'decision_hash':source['result']['decision_hash'],'financial_entry_id':'yield1','method':'REPLAYED_PUBLIC_FIRST_ACQUISITION_NET_CLOSING_WITH_CARRYING_ROUNDING','daily_memory_hash':digest(pm),'analysis_hash':digest(analysis),'monthly_fiscal_components':monthly,'realized_net_carrying_return':format(realized,'.8f'),'closing_carrying_components':carrying,'retained_fiscal_components':available,'fiscal_origin':'D2101','normative_homologation_claimed':False},'SYNTHETIC.EXPLICIT.NET.CLOSING','2026-08-01','2026-08-31')
  assignment=proof({'supplier_id':p['supplier_id'],'current_period':'2026-09','title':title,'snapshot_hash':digest(source),'financial_entry_id':'yield1','retained_share':'1'},'SYNTHETIC.EXPLICIT.ASSIGN','2026-08-01','2026-08-31')
  ref={'source_key':digest({'supplier_id':p['supplier_id'],'period':'2026-08','document_id':entry['document_id'],'financial_entry_id':'yield1'}),'snapshot_hash':digest(source),'decision_hash':source['result']['decision_hash'],'snapshot_status':source['result']['status'],'period':'2026-08','entry_id':'yield1','retained_share':'1','available':available,'retained':available,'assignment_evidence_hash':assignment['source_hash'],'net_closing_evidence_hash':allocation['source_hash']}
  h={'closing_memory':closing,'fiscal_sources':[{'snapshot':source,'financial_entry_id':'yield1','retained_share':'1','assignment_evidence':assignment,'net_closing_evidence':allocation}],'complete_evidence':proof({'scope':scope,'sources':[ref],'closing_memory_hash':digest(closing),'opening_fiscal_components':available,'opening_carrying_components':carrying},'SYNTHETIC.EXPLICIT.COMPLETE','2026-08-01','2026-08-31')}
  opening={**zero,**carrying};remaining=1-fraction;parts={**zero,'prior_produced_return':format(Decimal(carrying['prior_produced_return'])*fraction,'.8f'),'rounding_bridge':format(Decimal(carrying['rounding_bridge'])*fraction,'.8f'),'current_produced_return_already_in_curve':format(appropriated*fraction,'.8f'),'new_produced_return_at_sale':'0','new_fair_value_change_at_sale':'0'}
  save(S/(name+'-current.json'),{'position':p,'title':title,'recognition_history':h,'independent_oracle':independent,'components':{'sale1':parts},'recognition_inventory':{'opening':opening,'closing':{**zero,'prior_produced_return':format(Decimal(carrying['prior_produced_return'])*remaining,'.8f'),'rounding_bridge':format(Decimal(carrying['rounding_bridge'])*remaining,'.8f'),'current_produced_return_already_in_curve':format(appropriated*remaining,'.8f')},'other_releases':[],'closing_fiscal_components':{k:format(Decimal(available[k])*remaining,'.8f') for k in keys if k!='deferred_fair_value'}},'yield_amount':format(appropriated,'.2f'),'expected':{'revenue':'0.00','deductions':'0.00','base':format(Decimal(700)+Decimal(monthly_current['vApur']),'.2f'),'auxiliary_base':monthly_current['vApur'],'opening_fiscal_components':available,'opening_carrying_components':carrying}})
 print('Prepared current fixtures for '+case,flush=True)
