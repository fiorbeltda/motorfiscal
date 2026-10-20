"""Retain an actually replayed prior public loss without creating a current fiscal deduction."""
import copy,hashlib,json,subprocess
from datetime import datetime,timezone
from decimal import Decimal
from pathlib import Path
from dere_daily_memory_oracle import oracle,proof,seal,digest
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';PV='2026.10.08-28-draft17';V='2026.10.08-28-draft18'
E=ROOT/'storage/app/phase4b-realization-public-negative-yield-draft-20261008-28';S=F/f'scenarios/tvm-public-negative-yield-{V}'
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
 c['version']=version;q=c['financial_operation']['base']['public_offer_debt'];q['published_at']=known
 if prior:
  h=q['sale_recognition_policy']['analysis']['history_policy'];h.update(published_at=known,public_negative_yield_policy='REPLAYED_UNOFFSET_TITLE_LOSS_NO_CURRENT_DEDUCTION');h['permitted_bundle_manifest_hashes'].append(digest(prior))
  q['sale_recognition_policy']['analysis']['published_at']=known
  c['financial_operation']['base']['securities_realization'].update(published_at=known,analysis=copy.deepcopy(q['sale_recognition_policy']['analysis']),public_offer_debt=copy.deepcopy(q))
  c['dere_monthly_closing_projection']['securities_realization_binding']['published_at']=known
 c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(q)
 cp=f'catalogs/rtc-codes-{version}.json';save(F/cp,c)
 ctrl=read(F/b['production_controls']['path']);ctrl['version']=version;ct=f'production/phase4b-controls-{version}.json';save(F/ct,ctrl)
 b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+version,version=version,knowledge_available_at=known)
 b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=version;b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
 return b,c
prior,pc=bundle(PV,'2026.10.07-28-draft11');current,c=bundle(V,'2026.10.08-28-draft16',prior)
history=c['financial_operation']['base']['securities_realization']['analysis']['history_policy'];policy=history['daily_memory_policies']['2026-08'];priors={}
for route in ['direct','fund']:
 scenario=read(F/('scenarios/tvm-public-history-2026.10.07-28-draft12/prior-'+route+'-financial.json'));scenario.update(scenario_id='SYNTHETIC-prior-public-negative-'+route,knowledge_at=prior['knowledge_available_at'],rule_bundle_id=prior['bundle_id'],catalog_versions=prior['catalog_versions'])
 ledger=scenario['facts']['rtc.financial_ledger'][0];entry=ledger['entries'][0];p=entry['public_offer_debt']['position'];p['vPisCofins']='0.00';p['appropriations']={'2026-08-31':'-150'}
 curve,monthly,independent=oracle(p,policy['series']);p.pop('appropriations');p['contract_curve']=curve;p['declared_monthly']=monthly;p.pop('memory_evidence',None);p['memory_evidence']=proof(p,'SYNTHETIC.PUBLIC.NEGATIVE.PRIOR.MEMORY','2026-08-01','2026-08-31')
 entry['document_id']='SYNTHETIC.prior-negative-public-'+route
 d=entry['public_offer_debt'];d['eligibility_evidence']=proof({'supplier_id':ledger['supplier_id'],'period_end':'2026-08-31','title':d['title'],'financial_entry_id':'yield1','document_id':entry['document_id'],'amount':'150.00','result_account_code':d['result_account_code'],'position_input_hash':digest(p),'position_mode':'DAILY_MEMORY'},'SYNTHETIC.NEGATIVE.PUBLIC.ELIGIBILITY','2026-08-01','2026-08-31')
 source=S/('prior-'+route+'-financial.json');save(source,scenario);snapshot=E/('prior-'+route+'-snapshot.json');args=['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),f'--bundle-manifest=bundles/consumption-phase4b-{PV}.json']
 if not snapshot.exists():args.append('--snapshot='+snapshot.relative_to(ROOT).as_posix())
 result=run(args);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'];save(E/('prior-'+route+'-result.json'),result)
 base=next(t['base'] for t in result['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE');q=base['entries'][0]['d2101_binding'];assert base['net_base']=='0.00' and q['unoffset_title_loss']=='150.00' and q['gross_nature']=='D' and q['taxable_amount']=='0.00'
 memory=q['calculation']
 for key,value in independent.items():
  if key!='daily_memory':assert memory[key]==value,(key,memory[key],value)
 save(E/('prior-'+route+'-memory.json'),memory);priors[route]=(p,memory,entry,read(snapshot))
zero=dict.fromkeys(['prior_produced_return','current_produced_return_already_in_curve','deferred_fair_value_realized','rounding_bridge'],'0')
for name,route,quantity in [('partial','direct',400),('complete','direct',1000),('exclusive-fund','fund',400)]:
 pp,pm,priorEntry,snapshot=priors[route];p=copy.deepcopy(pp);p.update(period='2026-09',first_acquisition=False,vPisCofins='5.00')
 p['opening']={'quantity':pm['closing_quantity'],'unit_cost':pm['closing_unit_cost'],'acquisition_cost':pm['closing_acquisition_cost'],'book_balance':pm['monthly']['vSaldoContFin']}
 p['previous_position_evidence']=proof({'supplier_id':p['supplier_id'],'idTitulo':p['idTitulo'],'cCta':p['cCta'],'period':'2026-08','closing':p['opening']},'SYNTHETIC.NEGATIVE.PUBLIC.PREVIOUS','2026-08-01','2026-08-31')
 p['method_evidence']=proof({'contract':'DERE-D2101-DAILY-MEMORY-LOCAL-2026.10.06-1','method':p['method']},'SYNTHETIC.NEGATIVE.PUBLIC.CURRENT.METHOD')
 book=Decimal(quantity)*Decimal('9.9');p['operations']=[{'id':'sale1','date':'2026-09-14','sequence':1,'kind':'SELL','quantity':str(quantity),'book_amount':format(book,'.2f'),'sale_proceeds':format(book,'.2f')}]
 p['appropriations']={'2026-09-10':'50'};curve,monthly,independent=oracle(p,c['dere_debt_daily_memory_calculation']['series']);p.pop('appropriations');p['contract_curve']=curve;p['declared_monthly']=monthly;seal(p)
 title=copy.deepcopy(priorEntry['public_offer_debt']['title']);scope={'supplier_id':p['supplier_id'],'period':'2026-09','title':title,'opening':p['opening']}
 closing={'contract':'TVM-RECOGNITION-CLOSING-MEMORY-1','position':pp,'result':pm,'known_at':prior['knowledge_available_at'],'policy_hash':digest(policy)}
 available=dict.fromkeys(['assessed_produced_return','excluded_art195_return','removed_pis_cofins','deferred_fair_value','unoffset_public_title_loss'],'0.00000000');available['unoffset_public_title_loss']='-150.00000000'
 assignment=proof({'supplier_id':p['supplier_id'],'current_period':'2026-09','title':title,'snapshot_hash':digest(snapshot),'financial_entry_id':'yield1','retained_share':'1'},'SYNTHETIC.NEGATIVE.PUBLIC.ASSIGN','2026-08-01','2026-08-31')
 reference={'source_key':digest({'supplier_id':p['supplier_id'],'period':'2026-08','document_id':priorEntry['document_id'],'financial_entry_id':'yield1'}),'snapshot_hash':digest(snapshot),'decision_hash':snapshot['result']['decision_hash'],'snapshot_status':snapshot['result']['status'],'period':'2026-08','entry_id':'yield1','retained_share':'1','available':available,'retained':available,'assignment_evidence_hash':assignment['source_hash']}
 historyInput={'closing_memory':closing,'fiscal_sources':[{'snapshot':snapshot,'financial_entry_id':'yield1','retained_share':'1','assignment_evidence':assignment}],
  'complete_evidence':proof({'scope':scope,'sources':[reference],'closing_memory_hash':digest(closing),'opening_fiscal_components':available},'SYNTHETIC.NEGATIVE.PUBLIC.COMPLETE','2026-08-01','2026-08-31')}
 fraction=Decimal(quantity)/1000;remaining=1-fraction
 save(S/(name+'-current.json'),{'position':p,'title':title,'recognition_history':historyInput,'independent_oracle':independent,
  'components':{'sale1':{**zero,'prior_produced_return':str(Decimal(-150)*fraction),'current_produced_return_already_in_curve':str(Decimal(50)*fraction),'new_produced_return_at_sale':'0','new_fair_value_change_at_sale':'0'}},
  'recognition_inventory':{'opening':{**zero,'prior_produced_return':'-150'},'closing':{**zero,'prior_produced_return':str(Decimal(-150)*remaining),'current_produced_return_already_in_curve':str(Decimal(50)*remaining)},'other_releases':[],
   'closing_fiscal_components':{key:format(Decimal(available[key])*remaining,'.8f') for key in ['assessed_produced_return','excluded_art195_return','removed_pis_cofins','unoffset_public_title_loss']}},
  'expected':{'revenue':'0.00','deductions':'0.00','base':format(Decimal(700)+Decimal(monthly['vApur']),'.2f'),'auxiliary_base':monthly['vApur'],'fair_revenue':'0.00','fair_deductions':'0.00','prior_loss':'-150.00000000'}})
save(E/'research.json',{'legal_url':'https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html','source_sha256':history['source_hash'],
 'manual_file':'dere-observed-20261005-mod.pdf','manual_sha256':hashlib.sha256((F/'sources/official/dere-observed-20261005-mod.pdf').read_bytes()).hexdigest(),'manual_physical_pages':[86,87],
 'rule':'A negative public yield produces zero isolated D2101 base and cannot offset another title. Replayed prior loss is retained as a separate signed memory balance, not a positive art195 exclusion, tax-paid amount or current deduction.',
 'scope28_completed':False,'production_ready':False})
print('Two prior negative public decisions and three current non-deducting realizations frozen.')
