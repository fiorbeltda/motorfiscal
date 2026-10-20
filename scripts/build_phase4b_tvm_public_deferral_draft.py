"""Capture signed public fair-value deferral independently from the qualified produced yield."""
import copy, hashlib, json, subprocess
from datetime import datetime, timezone
from decimal import Decimal
from pathlib import Path
from dere_daily_memory_oracle import oracle, proof, digest, seal

ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal'
PV='2026.10.08-28-draft14';V='2026.10.08-28-draft15'
E=ROOT/'storage/app/phase4b-realization-public-deferral-draft-20261008-28';S=F/f'scenarios/tvm-public-deferral-{V}'
E.mkdir(parents=True,exist_ok=True);S.mkdir(parents=True,exist_ok=True)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:p.parent.mkdir(parents=True,exist_ok=True);p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
def run(args):
 p=subprocess.run(['docker','compose','exec','-T','app','php','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8');assert p.returncode==0,p.stdout+p.stderr
 return json.loads(p.stdout)
if not (E/'historical-before.json').exists():
 baseline={}
 for folder in [F,ROOT/'storage/app']:
  for p in folder.rglob('*'):
   if p.is_file() and E not in p.parents and not any(v in str(p) for v in [V,PV]) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']:baseline[p.relative_to(ROOT).as_posix()]=hashlib.sha256(p.read_bytes()).hexdigest()
 save(E/'historical-before.json',baseline)
def bundle(version,base,prior=None):
 b=read(F/f'bundles/consumption-phase4b-{base}.json');c=read(F/b['resource_paths']['rtc_code_catalog']);target=F/f'bundles/consumption-phase4b-{version}.json'
 known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
 c['version']=version;c['financial_operation']['base']['deferred_fair_value_sign_policy']='INDEPENDENT_SIGNED_SUBACCOUNT_RECOGNITION'
 q=c['financial_operation']['base']['public_offer_debt'];q['published_at']=known
 if prior:
  h=q['sale_recognition_policy']['analysis']['history_policy'];h.update(published_at=known,public_deferral_policy='REPLAYED_FIRST_ACQUISITION_YIELD_AND_SIGNED_FAIR_VALUE_DAILY_BRIDGE')
  h['permitted_bundle_manifest_hashes'].append(digest(prior))
  q['sale_recognition_policy']['analysis']['published_at']=known
  sec=c['financial_operation']['base']['securities_realization'];sec.update(published_at=known,analysis=copy.deepcopy(q['sale_recognition_policy']['analysis']),public_offer_debt=copy.deepcopy(q))
  c['dere_monthly_closing_projection']['securities_realization_binding']['published_at']=known
 c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(q)
 cp=f'catalogs/rtc-codes-{version}.json';save(F/cp,c)
 ctrl=read(F/b['production_controls']['path']);ctrl['version']=version;ct=f'production/phase4b-controls-{version}.json';save(F/ct,ctrl)
 b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+version,version=version,knowledge_available_at=known)
 b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=version;b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
 return b,c
prior,pc=bundle(PV,'2026.10.07-28-draft11');current,c=bundle(V,'2026.10.07-28-draft13',prior)
history=c['financial_operation']['base']['securities_realization']['analysis']['history_policy'];policy=history['daily_memory_policies']['2026-08']
def calculate(name,position):
 path=S/(name+'-position.json');save(path,position)
 result=run(['fiscal:dere:calculate-debt-daily-memory',path.relative_to(ROOT).as_posix(),f'--bundle-manifest=bundles/consumption-phase4b-{PV}.json'])['result']
 assert result['status']=='RESOLVED',result;save(E/(name+'-memory.json'),result);return result
for name,route,sign in [('gain','direct',1),('loss','direct',-1),('fund-gain','fund',1),('fund-loss','fund',-1)]:
 oldFolder=F/'scenarios/tvm-public-history-2026.10.07-28-draft12'
 scenario=read(oldFolder/('prior-'+route+'-financial.json'));scenario.update(scenario_id='SYNTHETIC-public-deferral-'+name,knowledge_at=prior['knowledge_available_at'],rule_bundle_id=prior['bundle_id'],catalog_versions=prior['catalog_versions'])
 ledger=scenario['facts']['rtc.financial_ledger'][0];yieldEntry=ledger['entries'][0];position=yieldEntry['public_offer_debt']['position'];yieldMemory=calculate(name+'-yield',position)
 fv=read(F/'scenarios/tvm-history-draft-2026.10.06-28/previous-private-august-financial.json')['facts']['rtc.financial_ledger'][0]['entries'][1]
 fv.update(document_id='SYNTHETIC.public-fv-'+name,fair_value_nature='C' if sign>0 else 'D',recognized_on='2026-08-31')
 fv['security_identity'].update(cCta=position['cCta'])
 fv['fair_value_sign_evidence']=proof({'supplier_id':ledger['supplier_id'],'period_start':ledger['period_start'],'period_end':ledger['period_end'],
  'financial_entry_id':fv['id'],'document_id':fv['document_id'],'amount':fv['amount'],'fair_value_nature':fv['fair_value_nature'],
  'security_identity':fv['security_identity'],'subaccount_evidence_id':fv['subaccount_evidence_id'],'exceeds_produced_return':True},'SYNTHETIC.PUBLIC.DEFERRAL.SIGN','2026-08-01','2026-08-31')
 ledger['entries'].append(fv);source=S/(name+'-prior-financial.json');save(source,scenario);snapshot=E/(name+'-prior-snapshot.json')
 args=['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),f'--bundle-manifest=bundles/consumption-phase4b-{PV}.json']
 if not snapshot.exists():args.append('--snapshot='+snapshot.relative_to(ROOT).as_posix())
 result=run(args);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'],result['reason_codes'];save(E/(name+'-prior-result.json'),result)
 snap=read(snapshot);base=next(t['base'] for t in result['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE');q=next(e['d2101_binding'] for e in base['entries'] if e['id']=='yield1')
 assert base['net_base']=='99.31' and base['deferred']==format(Decimal(sign*200),'.2f')
 full=copy.deepcopy(position);full['appropriations']={'2026-08-31':str(150+sign*200)}
 curve,monthly,independent=oracle(full,policy['series']);full.pop('appropriations');full['contract_curve']=curve;full['declared_monthly']=monthly;full.pop('memory_evidence',None)
 full['memory_evidence']=proof(full,'SYNTHETIC.PUBLIC.DEFERRAL.FULL.MEMORY','2026-08-01','2026-08-31');memory=calculate(name+'-full',full)
 for key,value in independent.items():
  if key!='daily_memory':assert memory[key]==value,(key,memory[key],value)
 title=copy.deepcopy(yieldEntry['public_offer_debt']['title']);scope={'supplier_id':ledger['supplier_id'],'period':'2026-09','title':title,
  'opening':{'quantity':memory['closing_quantity'],'unit_cost':memory['closing_unit_cost'],'acquisition_cost':memory['closing_acquisition_cost'],'book_balance':monthly['vSaldoContFin']}}
 closing={'contract':'TVM-RECOGNITION-CLOSING-MEMORY-1','position':full,'result':memory,'known_at':prior['knowledge_available_at'],'policy_hash':digest(policy)}
 zero=dict.fromkeys(['assessed_produced_return','excluded_art195_return','removed_pis_cofins','deferred_fair_value'],'0.00000000')
 available={'yield1':{**zero,'assessed_produced_return':format(Decimal(q['taxable_amount']),'.8f'),'excluded_art195_return':format(Decimal(q['art195_excess']),'.8f'),'removed_pis_cofins':format(Decimal(q['pis_deduction_applied']),'.8f')},fv['id']:{**zero,'deferred_fair_value':format(Decimal(sign*200),'.8f')}}
 expected={key:format(sum(Decimal(v[key]) for v in available.values()),'.8f') for key in zero};sources=[];references=[]
 for entry in ledger['entries']:
  assignment=proof({'supplier_id':scope['supplier_id'],'current_period':scope['period'],'title':title,'snapshot_hash':digest(snap),'financial_entry_id':entry['id'],'retained_share':'1'},'SYNTHETIC.PUBLIC.DEFERRAL.ASSIGN.'+entry['id'],'2026-08-01','2026-08-31')
  item={'snapshot':snap,'financial_entry_id':entry['id'],'retained_share':'1','assignment_evidence':assignment}
  if entry['id']==fv['id']:
   value={'supplier_id':scope['supplier_id'],'period':'2026-08','title':title,'financial_entry_id':fv['id'],'document_id':fv['document_id'],
    'yield_entry_id':'yield1','yield_binding_hash':digest(q),'yield_memory_hash':digest(yieldMemory),'closing_memory_hash':digest(closing),
    'recognized_on':fv['recognized_on'],'deferred_signed_amount':format(Decimal(sign*200),'.2f'),'subaccount_evidence_id':fv['subaccount_evidence_id'],'produced_yield_excludes_fair_value':True}
   item['public_deferral_bridge']={'yield_entry_id':'yield1','independent_evidence':proof(value,'SYNTHETIC.PUBLIC.DEFERRAL.SPLIT','2026-08-01','2026-08-31')}
  sources.append(item);references.append({'source_key':digest({'supplier_id':scope['supplier_id'],'period':'2026-08','document_id':entry['document_id'],'financial_entry_id':entry['id']}),
   'snapshot_hash':digest(snap),'decision_hash':result['decision_hash'],'snapshot_status':result['status'],'period':'2026-08','entry_id':entry['id'],'retained_share':'1',
   'available':available[entry['id']],'retained':available[entry['id']],'assignment_evidence_hash':assignment['source_hash']})
 references.sort(key=lambda r:r['source_key']);value={'scope':scope,'sources':references,'closing_memory_hash':digest(closing),'opening_fiscal_components':expected}
 save(S/(name+'-history.json'),{'scope':scope,'history':{'closing_memory':closing,'fiscal_sources':sources,'complete_evidence':proof(value,'SYNTHETIC.PUBLIC.DEFERRAL.COMPLETE','2026-08-01','2026-08-31')},
  'rule':history,'known_at':current['knowledge_available_at'],'expected':expected,'independent_oracle':independent})
save(E/'research.json',{'legal_url':'https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html','legal_sha256':history['source_hash'],
 'documentation_url':'https://www.cgibs.gov.br/declaracao-de-regimes-especificos-dere','documentation_version':'1.3.0','observed_on':'2026-10-08',
 'table_sha256':hashlib.sha256((F/'sources/official/dere-observed-20261005-annex-i.pdf').read_bytes()).hexdigest(),'table_physical_pages':[23,24,34],
 'rule':'Replay the actual qualified public yield and independent signed deferral in the same prior snapshot, then reconcile each daily fair-value difference against full carrying memory.',
 'limits':['First acquisition in prior month, purchases only, full retained share, one independently signed fair-value recognition date','Full carrying memory is historical reconciliation, not a replacement D2101 yield declaration','Prior document pending is not receipt, posting or homologation','Current realization integration and integral scope28 remain pending'],
 'scope28_completed':False,'production_ready':False})
for name,previous,quantity in [('gain','gain',400),('loss','loss',400),('fund-gain','fund-gain',400),('fund-loss','fund-loss',400),('full-gain','gain',1000),('full-loss','loss',1000)]:
 h=read(S/(previous+'-history.json'));sign=1 if 'gain' in name else -1;fraction=Decimal(quantity)/1000;remaining=1-fraction
 p=copy.deepcopy(h['history']['closing_memory']['position']);p.update(period='2026-09',first_acquisition=False,opening=h['scope']['opening'],vPisCofins='5.00')
 p['previous_position_evidence']=proof({'supplier_id':p['supplier_id'],'idTitulo':p['idTitulo'],'cCta':p['cCta'],'period':'2026-08','closing':p['opening']},'SYNTHETIC.PUBLIC.DEFERRAL.PREVIOUS','2026-08-01','2026-08-31')
 p['method_evidence']=proof({'contract':'DERE-D2101-DAILY-MEMORY-LOCAL-2026.10.06-1','method':p['method']},'SYNTHETIC.PUBLIC.DEFERRAL.CURRENT.METHOD')
 book=Decimal(quantity)*Decimal('10.4' if sign>0 else '10.0');p['operations']=[{'id':'sale1','date':'2026-09-14','sequence':1,'kind':'SELL','quantity':str(quantity),'book_amount':format(book,'.2f'),'sale_proceeds':format(book,'.2f')}]
 p['appropriations']={'2026-09-10':'50'};curve,monthly,independent=oracle(p,c['dere_debt_daily_memory_calculation']['series']);p.pop('appropriations');p['contract_curve']=curve;p['declared_monthly']=monthly;seal(p)
 zero=dict.fromkeys(['prior_produced_return','current_produced_return_already_in_curve','deferred_fair_value_realized','rounding_bridge'],'0')
 components={**zero,'prior_produced_return':str(Decimal(150)*fraction),'current_produced_return_already_in_curve':str(Decimal(50)*fraction),'deferred_fair_value_realized':str(Decimal(sign*200)*fraction),'new_produced_return_at_sale':'0','new_fair_value_change_at_sale':'0'}
 release=Decimal(sign*200)*fraction;rev=max(release,Decimal(0));ded=max(-release,Decimal(0))
 save(S/(name+'-current.json'),{'position':p,'title':h['scope']['title'],'recognition_history':h['history'],'historical_case':previous,
  'components':{'sale1':components},'independent_oracle':independent,
  'recognition_inventory':{'opening':{**zero,'prior_produced_return':'150','deferred_fair_value_realized':str(sign*200)},
   'closing':{**zero,'prior_produced_return':str(Decimal(150)*remaining),'current_produced_return_already_in_curve':str(Decimal(50)*remaining),'deferred_fair_value_realized':str(Decimal(sign*200)*remaining)},'other_releases':[],
   'closing_fiscal_components':{key:format(Decimal(h['expected'][key])*remaining,'.8f') for key in ['assessed_produced_return','excluded_art195_return','removed_pis_cofins']}},
  'expected':{'revenue':format(rev,'.2f'),'deductions':format(ded,'.2f'),'base':format(Decimal(700)+Decimal(monthly['vApur'])+release,'.2f'),'auxiliary_base':monthly['vApur'],'fair_revenue':format(rev,'.2f'),'fair_deductions':format(ded,'.2f')}})
print('Four signed histories and six current realizations frozen; prior produced yield remains 150, taxable 99.31.')
