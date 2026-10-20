"""Produce actual prior public decisions in separate knowledge and bind their disposal to current knowledge."""
import copy, hashlib, json, subprocess
from datetime import datetime, timezone
from decimal import Decimal
from pathlib import Path
from dere_daily_memory_oracle import oracle, proof, seal, digest
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.07-28-draft12';PV='2026.10.07-28-draft11'
E=ROOT/'storage/app/phase4b-realization-public-history-draft-20261007-28';S=F/f'scenarios/tvm-public-history-{V}'
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
old=read(F/'bundles/consumption-phase4b-2026.10.07-28-draft10.json')
def bundle(version,prior=False,permitted=None):
 b=copy.deepcopy(old);c=read(F/old['resource_paths']['rtc_code_catalog']);target=F/f'bundles/consumption-phase4b-{version}.json'
 known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
 c['version']=version;qual=c['financial_operation']['base']['public_offer_debt'];qual['published_at']=known
 if prior:
  daily=copy.deepcopy(qual['sale_recognition_policy']['analysis']['history_policy']['daily_memory_policies']['2026-08'])
  qual['daily_memory_calculation']=daily;qual.pop('sale_recognition_policy');c['dere_debt_daily_memory_calculation']=copy.deepcopy(daily)
 else:
  qual['sale_recognition_policy']['analysis']['published_at']=known
  h=qual['sale_recognition_policy']['analysis']['history_policy'];h['published_at']=known;h['permitted_bundle_manifest_hashes'].append(digest(permitted))
 c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(qual)
 cp=f'catalogs/rtc-codes-{version}.json';save(F/cp,c)
 ctrl=read(F/old['production_controls']['path']);ctrl['version']=version;ct=f'production/phase4b-controls-{version}.json';save(F/ct,ctrl)
 b.update(predecessor_bundle_id=(permitted or old)['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+version,version=version,knowledge_available_at=known)
 b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=version;b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
 return b,c
prior,pc=bundle(PV,True);current,c=bundle(V,False,prior);qual=c['financial_operation']['base']['public_offer_debt'];history=qual['sale_recognition_policy']['analysis']['history_policy']
prototype=read(F/'scenarios/dere-variable-yield-2026.10.06-27/first-purchase-before-rate-financial.json')['facts']['rtc.financial_ledger'][0]['entries'][4]
def eligible(entry,start,end):
 d=entry['public_offer_debt'];d['eligibility_evidence']=proof({'supplier_id':entry['supplier_id'],'period_end':end,'title':d['title'],
 'financial_entry_id':entry['id'],'document_id':entry['document_id'],'amount':entry['amount'],'result_account_code':d['result_account_code'],
 'position_input_hash':digest(d['position']),'position_mode':'DAILY_MEMORY'},'SYNTHETIC.PUBLIC.HISTORY.ELIGIBILITY',start,end)
def memory(name,position,version):
 path=S/(name+'-position.json');save(path,position)
 result=run(['fiscal:dere:calculate-debt-daily-memory',path.relative_to(ROOT).as_posix(),f'--bundle-manifest=bundles/consumption-phase4b-{version}.json'])['result']
 save(E/(name+'-memory.json'),result);assert result['status']=='RESOLVED',result
 return result
priors={}
for route in ['direct','fund']:
 p=read(F/'scenarios/tvm-history-draft-2026.10.06-28/previous-private-august-position.json')['position'];p['vPisCofins']='10.00'
 if route=='fund':p['cCta']='debtfund000'
 p['appropriations']={'2026-08-31':'150'};curve,monthly,independent=oracle(p,history['daily_memory_policies']['2026-08']['series']);p.pop('appropriations');p['contract_curve']=curve;p['declared_monthly']=monthly
 p.pop('memory_evidence',None);p['memory_evidence']=proof(p,'SYNTHETIC.PUBLIC.AUGUST.MEMORY','2026-08-01','2026-08-31')
 m=memory('prior-'+route,p,PV)
 for key,value in independent.items():
  if key!='daily_memory':assert m[key]==value,(key,m[key],value)
 entry=copy.deepcopy(prototype);entry.update(document_id='prior-public-'+route,recognized_on='2026-08-31',amount='150.00')
 d=entry['public_offer_debt'];d['position']=p;d['title'].update(idTitulo=p['idTitulo'],cCta=p['cCta'],vJurosRec='0.00')
 if route=='fund':d['title'].update(holding_route='EXCLUSIVE_FUND_95',exclusive_fund=True,descFundo='Fundo exclusivo fixture',public_offer_portfolio_percent='95.00')
 eligible(entry,'2026-08-01','2026-08-31')
 scenario=read(F/'scenarios/tvm-history-draft-2026.10.06-28/previous-private-august-financial.json')
 scenario.update(scenario_id='SYNTHETIC-prior-public-'+route,knowledge_at=prior['knowledge_available_at'],rule_bundle_id=prior['bundle_id'],catalog_versions=prior['catalog_versions'])
 ledger=scenario['facts']['rtc.financial_ledger'][0];ledger['entries']=[entry]
 source=S/('prior-'+route+'-financial.json');save(source,scenario);snapshot=E/('prior-'+route+'-snapshot.json')
 args=['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),f'--bundle-manifest=bundles/consumption-phase4b-{PV}.json']
 if not snapshot.exists():args.append('--snapshot='+snapshot.relative_to(ROOT).as_posix())
 result=run(args);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'],result;save(E/('prior-'+route+'-result.json'),result)
 base=next(t['base'] for t in result['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE');q=base['entries'][0]['d2101_binding']
 available={'assessed_produced_return':format(Decimal(q['taxable_amount']),'.8f'),'excluded_art195_return':format(Decimal(q['art195_excess']),'.8f'),
 'removed_pis_cofins':format(Decimal(q['pis_deduction_applied']),'.8f'),'deferred_fair_value':'0.00000000'}
 assert sum(map(Decimal,available.values()))==Decimal(150)
 priors[route]=(p,m,entry,read(snapshot),available)
zero=dict.fromkeys(['prior_produced_return','current_produced_return_already_in_curve','deferred_fair_value_realized','rounding_bridge'],'0')
for name,route,quantities in [('partial','direct',[400]),('complete','direct',[1000]),('exclusive-fund','fund',[400]),('two-sales','direct',[400,600])]:
 pp,pm,priorEntry,snapshot,available=priors[route];p=copy.deepcopy(pp);p.update(period='2026-09',first_acquisition=False)
 p['opening']={'quantity':pm['closing_quantity'],'unit_cost':pm['closing_unit_cost'],'acquisition_cost':pm['closing_acquisition_cost'],'book_balance':pm['monthly']['vSaldoContFin']}
 p['previous_position_evidence']=proof({'supplier_id':p['supplier_id'],'idTitulo':p['idTitulo'],'cCta':p['cCta'],'period':'2026-08','closing':p['opening']},'SYNTHETIC.PUBLIC.PREVIOUS','2026-08-01','2026-08-31')
 p['method_evidence']=proof({'contract':'DERE-D2101-DAILY-MEMORY-LOCAL-2026.10.06-1','method':p['method']},'SYNTHETIC.PUBLIC.CURRENT.METHOD')
 p['vPisCofins']='5.00';p['operations']=[{'id':'sale'+str(i+1),'date':'2026-09-14','sequence':i+1,'kind':'SELL','quantity':str(q),'book_amount':format(Decimal(q)*Decimal('10.2'),'.2f'),'sale_proceeds':format(Decimal(q)*Decimal('10.2'),'.2f')} for i,q in enumerate(quantities)]
 p['appropriations']={'2026-09-10':'50'};curve,monthly,independent=oracle(p,qual['daily_memory_calculation']['series']);p.pop('appropriations');p['contract_curve']=curve;p['declared_monthly']=monthly;seal(p)
 m=memory(name,p,V)
 for key,value in independent.items():
  if key!='daily_memory':assert m[key]==value,(key,m[key],value)
 entry=copy.deepcopy(priorEntry);entry.update(document_id='current-public-'+name,recognized_on='2026-09-30',amount='50.00');entry['public_offer_debt']['position']=p;eligible(entry,'2026-09-01','2026-09-30')
 title=entry['public_offer_debt']['title'];scope={'supplier_id':p['supplier_id'],'period':p['period'],'title':title,'opening':p['opening']}
 closing={'contract':'TVM-RECOGNITION-CLOSING-MEMORY-1','position':pp,'result':pm,'known_at':prior['knowledge_available_at'],'policy_hash':digest(history['daily_memory_policies']['2026-08'])}
 assignment=proof({'supplier_id':p['supplier_id'],'current_period':p['period'],'title':title,'snapshot_hash':digest(snapshot),'financial_entry_id':'yield1','retained_share':'1'},'SYNTHETIC.PUBLIC.HISTORY.ASSIGN','2026-08-01','2026-08-31')
 reference={'source_key':digest({'supplier_id':p['supplier_id'],'period':'2026-08','document_id':priorEntry['document_id'],'financial_entry_id':'yield1'}),
 'snapshot_hash':digest(snapshot),'decision_hash':snapshot['result']['decision_hash'],'snapshot_status':snapshot['result']['status'],'period':'2026-08','entry_id':'yield1','retained_share':'1','available':available,'retained':available,'assignment_evidence_hash':assignment['source_hash']}
 historyInput={'closing_memory':closing,'fiscal_sources':[{'snapshot':snapshot,'financial_entry_id':'yield1','retained_share':'1','assignment_evidence':assignment}],
 'complete_evidence':proof({'scope':scope,'sources':[reference],'closing_memory_hash':digest(closing),'opening_fiscal_components':available},'SYNTHETIC.PUBLIC.HISTORY.COMPLETE','2026-08-01','2026-08-31')}
 ratio=(Decimal(1000)-sum(map(Decimal,quantities)))/1000
 inventory={'opening':{**zero,'prior_produced_return':'150'},'closing':{**zero,'prior_produced_return':str(Decimal(150)*ratio),'current_produced_return_already_in_curve':str(Decimal(50)*ratio)},'other_releases':[],
 'closing_fiscal_components':{k:format(Decimal(available[k])*ratio,'.8f') for k in ['assessed_produced_return','excluded_art195_return','removed_pis_cofins']}}
 declarations=[]
 for day in m['daily_memory']:
  for op in day['operations']:
   fraction=Decimal(op['quantity'])/1000;parts={**zero,'prior_produced_return':str(Decimal(150)*fraction),'current_produced_return_already_in_curve':str(Decimal(50)*fraction),'new_produced_return_at_sale':'0','new_fair_value_change_at_sale':'0'}
   h={'snapshot_id':digest([reference]),'period':'2026-08','previously_recognized_produced_return':parts['prior_produced_return'],
    'previously_assessed_produced_return':format(Decimal(available['assessed_produced_return'])*fraction,'.8f'),
    'previously_excluded_art195_return':format(Decimal(available['excluded_art195_return'])*fraction,'.8f'),
    'previously_removed_pis_cofins':format(Decimal(available['removed_pis_cofins'])*fraction,'.8f'),'deferred_fair_value_released':'0'}
   hp=proof({'supplier_id':p['supplier_id'],'idTitulo':p['idTitulo'],'cCta':p['cCta'],'operation_id':op['id'],'quantity':op['quantity'],'history':h},'SYNTHETIC.PUBLIC.SALE.HISTORY','2026-08-01','2026-08-31')
   cp=proof({'supplier_id':p['supplier_id'],'period':p['period'],'idTitulo':p['idTitulo'],'cCta':p['cCta'],'sale':{'date':day['date'],**op},'components':parts,'history_evidence_hash':hp['source_hash']},'SYNTHETIC.PUBLIC.SALE.COMPONENTS')
   declarations.append({'operation_id':op['id'],'components':parts,'history':h,'history_evidence':hp,'component_evidence':cp})
 common={'supplier_id':p['supplier_id'],'period':p['period']}
 ip=proof({**common,'idTitulo':p['idTitulo'],'cCta':p['cCta'],'position_input_hash':digest(p),'inventory':inventory},'SYNTHETIC.PUBLIC.INVENTORY')
 input={'position':p,'title':title,'recognition_history':historyInput,'recognition_inventory':inventory,'realizations':declarations,'inventory_evidence':ip,
 'classification_evidence':proof({**common,'title':title,'position_input_hash':digest(p)},'SYNTHETIC.PUBLIC.CLASSIFICATION'),
 'complete_evidence':proof({**common,'position_input_hash':digest(p),'title':title,'realizations':declarations,'recognition_inventory':inventory,'inventory_evidence_hash':ip['source_hash']},'SYNTHETIC.PUBLIC.COMPLETE')}
 entry['public_offer_debt']['sale_recognition']={'analysis_input':input,'independent_evidence':proof({'supplier_id':p['supplier_id'],'period_start':'2026-09-01','period_end':'2026-09-30','financial_entry_id':'yield1','document_id':entry['document_id'],
 'analysis_input_hash':digest(input),'calculation_hash':digest(m),'policy_hash':digest(qual['sale_recognition_policy']),'appropriation_excludes_fair_value':True,'previously_recognized_returns_not_reincluded':True},'SYNTHETIC.PUBLIC.SALE.QUALIFICATION')}
 scenario=read(F/'scenarios/dere-variable-yield-2026.10.06-27/first-purchase-before-rate-financial.json');scenario.update(scenario_id='SYNTHETIC-public-history-'+name,knowledge_at=current['knowledge_available_at'],rule_bundle_id=current['bundle_id'],catalog_versions=current['catalog_versions']);scenario['facts']['rtc.financial_ledger'][0]['entries'][4]=entry
 save(S/(name+'-financial.json'),scenario);save(S/(name+'-oracle.json'),{'independent_oracle':independent,'prior_available':available,'inventory':inventory,'expected_base':format(Decimal(700)+Decimal(monthly['vApur']),'.2f'),'scope28_completed':False})
 source=S/(name+'-financial.json');snap=E/(name+'-snapshot.json');args=['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),f'--bundle-manifest=bundles/consumption-phase4b-{V}.json']
 if not snap.exists():args.append('--snapshot='+snap.relative_to(ROOT).as_posix())
 result=run(args);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'],result['reason_codes']
 operation=next(t for t in result['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE');assert operation['base']['net_base']==format(Decimal(700)+Decimal(monthly['vApur']),'.2f')
 month=read(F/'scenarios/dere-variable-yield-2026.10.06-27/first-purchase-before-rate.json')
 for account in month['book']['accounts']:
  if account['cCta']=='interest000':account.update(vMovCred='1050.00',vMovDebt='0.00',vAjusteCred='50.00',vAjusteDebt='0.00',vSaldoFinal='1050.00')
  elif account['cCta'] in ['debt000','debtfund000']:
   used=account['cCta']==p['cCta'];opening=monthly['vSaldoContIni'] if used else '0.00';debit='50.00' if used else '0.00';credit=monthly['vSaidas'] if used else '0.00';end=monthly['vSaldoContFin'] if used else '0.00'
   account.update(vSaldoInic=opening,vMovDebt=debit,vMovCred=credit,vSaldoFinal=end,vAjusteDebt=debit,vAjusteCred='0.00');account['previous_balance']['amount']=opening
 month['debt_yield']['previous_title_balances']={'yield1':proof({'supplier_id':p['supplier_id'],'period':'2026-08','idTitulo':p['idTitulo'],'cCta':p['cCta'],'vSaldoContFin':monthly['vSaldoContIni']},'SYNTHETIC.PUBLIC.PRIOR.TITLE','2026-08-01','2026-08-31')}
 d=month['debt_yield'];d['evidence']=proof({'supplier_id':p['supplier_id'],'period_end':'2026-09-30','detail':{k:x for k,x in d.items() if k!='evidence'},'operation_hash':digest(operation),'book_input_hash':digest(month['book']),'pgcc_source_hash':month['book']['pgcc_source_hash']},'SYNTHETIC.PUBLIC.HISTORY.MONTH')
 month['corpus_expectation']={'status':'PROJECTED','base':operation['base']['net_base'],'auxiliary_base':monthly['vApur']};save(S/(name+'-month.json'),month)
save(E/'research.json',{'legal_url':'https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html',
 'legal_sha256':qual['source_hash'],'documentation_url':'https://www.cgibs.gov.br/declaracao-de-regimes-especificos-dere','observed_documentation_version':'1.3.0',
 'manual_file':'dere-observed-20261005-mod.pdf','manual_sha256':hashlib.sha256((F/'sources/official/dere-observed-20261005-mod.pdf').read_bytes()).hexdigest(),'manual_physical_pages':[86,87],
 'august_series_data_sha256':history['daily_memory_policies']['2026-08']['official_data_sha256'],
 'september_series_data_sha256':qual['daily_memory_calculation']['official_data_sha256'],
 'prior_bundle':PV,'prior_manifest_hash':digest(prior),'current_bundle':V,'current_manifest_hash':digest(current),
 'rule':'Replay the actual captured financial decision and closing memory; distinguish prior taxable yield, positive exclusion above Selic and removed PIS/Cofins. A sale at book value does not newly recognize those amounts.',
 'limits':['Only already recognized yield and zero new disposal result/fair value are integrated','Prior document-pending result proves financial base and gross tax only, not receipt or transmission','Positions and eligibility are synthetic; official daily rates remain source-grounded'],
 'scope28_completed':False,'production_ready':False})
print('Draft11 prior public decisions and draft12 four current historical disposals frozen.')
