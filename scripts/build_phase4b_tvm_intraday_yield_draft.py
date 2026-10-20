"""Freeze independently evidenced produced yield incorporated before a same-day disposal."""
import copy,hashlib,json
from datetime import datetime,timezone
from decimal import Decimal
from pathlib import Path
from dere_daily_memory_oracle import oracle,proof,seal,digest
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.08-28-draft16'
E=ROOT/'storage/app/phase4b-realization-intraday-yield-draft-20261008-28';S=F/f'scenarios/tvm-intraday-yield-{V}'
E.mkdir(parents=True,exist_ok=True);S.mkdir(parents=True,exist_ok=True)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:p.parent.mkdir(parents=True,exist_ok=True);p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
if not (E/'historical-before.json').exists():
 baseline={}
 for folder in [F,ROOT/'storage/app']:
  for p in folder.rglob('*'):
   if p.is_file() and E not in p.parents and V not in str(p) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']:baseline[p.relative_to(ROOT).as_posix()]=hashlib.sha256(p.read_bytes()).hexdigest()
 save(E/'historical-before.json',baseline)
b=read(F/'bundles/consumption-phase4b-2026.10.08-28-draft15.json');target=F/f'bundles/consumption-phase4b-{V}.json'
known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
c=read(F/b['resource_paths']['rtc_code_catalog']);c['version']=V
q=c['financial_operation']['base']['public_offer_debt'];q['published_at']=known
daily=q['daily_memory_calculation'];daily.update(published_at=known,appropriation_sequence_policy='INDEPENDENT_PRODUCED_YIELD_BEFORE_SALE')
q['sale_recognition_policy']['analysis'].update(published_at=known,daily_memory_calculation=copy.deepcopy(daily))
c['dere_debt_daily_memory_calculation']=copy.deepcopy(daily)
sec=c['financial_operation']['base']['securities_realization'];sec.update(published_at=known,analysis=copy.deepcopy(q['sale_recognition_policy']['analysis']),public_offer_debt=copy.deepcopy(q))
c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(q)
c['dere_monthly_closing_projection']['securities_realization_binding']['published_at']=known
cp=f'catalogs/rtc-codes-{V}.json';save(F/cp,c)
ctrl=read(F/b['production_controls']['path']);ctrl['version']=V;ct=f'production/phase4b-controls-{V}.json';save(F/ct,ctrl)
b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,knowledge_available_at=known)
b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V;b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
base=read(F/'scenarios/tvm-public-fair-2026.10.07-28-draft13/gain.json')
zero=dict.fromkeys(['prior_produced_return','current_produced_return_already_in_curve','deferred_fair_value_realized','rounding_bridge'],'0')
for name,quantity,saleDate,fund,fv in [('partial',400,'2026-09-15',False,0),('complete',1000,'2026-09-15',False,0),('buy-sell-same-day',1000,'2026-09-14',False,0),('exclusive-fund',400,'2026-09-15',True,0),('with-fair-value',400,'2026-09-15',False,20)]:
 p=copy.deepcopy(base['position']);title=copy.deepcopy(base['title']);p['vPisCofins']='0.00';p['operations']=p['operations'][:1]
 p['method']['appropriation_timing']='EXPLICIT_BEFORE_SALE_OR_AFTER_OPERATIONS';p['method_evidence']=proof({'contract':'DERE-D2101-DAILY-MEMORY-LOCAL-2026.10.06-1','method':p['method']},'SYNTHETIC.INTRADAY.METHOD')
 if fund:p['cCta']='debtfund000';title.update(cCta='debtfund000',holding_route='EXCLUSIVE_FUND_95',exclusive_fund=True,descFundo='Fundo exclusivo fixture',public_offer_portfolio_percent='95.00')
 book=Decimal(quantity)*10+50
 p['operations'].append({'id':'sale1','date':saleDate,'sequence':2 if saleDate=='2026-09-14' else 1,'kind':'SELL','quantity':str(quantity),'book_amount':format(book,'.2f'),'sale_proceeds':format(book+fv,'.2f')})
 p['appropriations']={saleDate:'50'};p['appropriation_before_operations']={saleDate:'sale1'};curve,monthly,independent=oracle(p,daily['series'])
 p.pop('appropriations');p.pop('appropriation_before_operations');p['contract_curve']=curve;p['declared_monthly']=monthly;seal(p)
 save(S/(name+'.json'),{'position':p,'title':title,'components':{'sale1':{**zero,'current_produced_return_already_in_curve':'50','new_produced_return_at_sale':'0','new_fair_value_change_at_sale':str(fv)}},'independent_oracle':independent,
  'recognition_inventory':{'opening':zero,'closing':zero,'other_releases':[]},
  'expected':{'revenue':format(Decimal(fv),'.2f'),'deductions':'0.00','base':format(Decimal(700)+Decimal(monthly['vApur'])+fv,'.2f'),'auxiliary_base':monthly['vApur'],'fair_revenue':format(Decimal(fv),'.2f'),'fair_deductions':'0.00'}})
save(E/'research.json',{'legal_url':'https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html','legal_sha256':q['source_hash'],
 'manual_file':'dere-observed-20261005-mod.pdf','manual_sha256':hashlib.sha256((F/'sources/official/dere-observed-20261005-mod.pdf').read_bytes()).hexdigest(),'manual_physical_pages':[86,87],
 'table_file':'dere-observed-20261005-annex-i.pdf','table_sha256':hashlib.sha256((F/'sources/official/dere-observed-20261005-annex-i.pdf').read_bytes()).hexdigest(),'table_physical_pages':[23,24],
 'rule':'Qualified produced yield actually recognized and incorporated in the asset before a same-day disposal contributes once to vVarMensal and to D2101. Independent intraday proof binds the operation, pre-recognition quantity/cost/book balance and produced-yield classification.',
 'limits':['No transformation of a bare sale price gain into an appropriation','No general resolution of new_produced_return_at_sale residuals','Intraday sequence is a proven local convention, not an official homologation claim','Existing after-operation memory remains unchanged for earlier policies'],
 'scope28_completed':False,'production_ready':False})
print('Draft16: five proved intraday produced-yield fixtures frozen.')
