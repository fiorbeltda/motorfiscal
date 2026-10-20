"""Freeze a public-offer disposal of already appropriated yield, without new sale result."""
import copy, hashlib, json
from datetime import datetime, timezone
from decimal import Decimal
from pathlib import Path
from dere_daily_memory_oracle import oracle, seal, digest

ROOT=Path(__file__).resolve().parents[1]; F=ROOT/'resources/fiscal'; V='2026.10.07-28-draft10'
E=ROOT/'storage/app/phase4b-realization-public-recognition-draft-20261007-28'; S=F/f'scenarios/tvm-public-recognition-{V}'
E.mkdir(parents=True,exist_ok=True); S.mkdir(parents=True,exist_ok=True)
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists(): assert read(p)==v,p
 else: p.parent.mkdir(parents=True,exist_ok=True); p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
if not (E/'historical-before.json').exists():
 baseline={}
 for folder in [F,ROOT/'storage/app']:
  for p in folder.rglob('*'):
   if p.is_file() and E not in p.parents and V not in str(p) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']:
    baseline[p.relative_to(ROOT).as_posix()]=hashlib.sha256(p.read_bytes()).hexdigest()
 save(E/'historical-before.json',baseline)
b=read(F/'bundles/consumption-phase4b-2026.10.07-28-draft9.json'); target=F/f'bundles/consumption-phase4b-{V}.json'
known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
c=read(F/b['resource_paths']['rtc_code_catalog']); c['version']=V
qual=c['financial_operation']['base']['public_offer_debt']; qual['published_at']=known
analysis=copy.deepcopy(c['financial_operation']['base']['securities_realization']['analysis']); analysis['published_at']=known
analysis['daily_memory_calculation']=copy.deepcopy(qual['daily_memory_calculation'])
qual['sale_recognition_policy']={'mode':'REPLAYED_ALREADY_RECOGNIZED_YIELD_DISPOSAL_NO_NEW_RESULT','analysis':analysis}
c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(qual)
cp=f'catalogs/rtc-codes-{V}.json'; save(F/cp,c)
ctrl=read(F/b['production_controls']['path']); ctrl['version']=V
ct=f'production/phase4b-controls-{V}.json'; save(F/ct,ctrl)
b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,knowledge_available_at=known)
b['resource_paths']['rtc_code_catalog']=cp; b['content_hashes']['rtc_code_catalog']=digest(c); b['catalog_versions']['rtc_codes']=V
b['production_controls']={'path':ct,'sha256':digest(ctrl)}; save(target,b)
prototype=read(F/'scenarios/dere-variable-yield-2026.10.06-27/first-purchase-before-rate-financial.json')['facts']['rtc.financial_ledger'][0]['entries'][-1]
base=read(F/'scenarios/tvm-base-2026.10.06-28-draft/first-gain.json')['position']
zero=dict.fromkeys(['prior_produced_return','current_produced_return_already_in_curve','deferred_fair_value_realized','rounding_bridge'],'0')
for name,quantity,fund,gains in [('partial',400,False,[0]),('complete',1000,False,[0]),('exclusive-fund',400,True,[0]),
 ('two-sales',400,False,[0,0]),('new-produced',400,False,[10]),('offsetting-new-produced',400,False,[10,-10])]:
 p=copy.deepcopy(base); title=copy.deepcopy(prototype['public_offer_debt']['title'])
 if fund:
  p['cCta']='debtfund000'; title.update(cCta='debtfund000',holding_route='EXCLUSIVE_FUND_95',exclusive_fund=True,descFundo='Fundo exclusivo fixture',public_offer_portfolio_percent='95.00')
 title['vJurosRec']='0.00'; title['idTitulo']=p['idTitulo']; title['cCta']=p['cCta']
 buy=copy.deepcopy(p['operations'][0]); sales=[]; components={}
 for i,gain in enumerate(gains):
  q=quantity if i==0 else 1000-quantity; book=Decimal(q)*Decimal('10.1'); opid='sale'+str(i+1)
  sales.append({'id':opid,'sequence':i+1,'date':'2026-09-15','kind':'SELL','quantity':str(q),'book_amount':format(book,'.2f'),'sale_proceeds':format(book+Decimal(gain),'.2f')})
  components[opid]={**zero,'current_produced_return_already_in_curve':str(Decimal(q)/10),'new_produced_return_at_sale':str(gain),'new_fair_value_change_at_sale':'0'}
 p['operations']=[buy,*sales]; p['appropriations']={'2026-09-14':'100'}
 curve,monthly,result=oracle(p,qual['daily_memory_calculation']['series']); p.pop('appropriations'); p['contract_curve']=curve; p['declared_monthly']=monthly; seal(p)
 released=sum((Decimal(v['current_produced_return_already_in_curve']) for v in components.values()),Decimal(0))
 save(S/(name+'.json'),{'position':p,'title':title,'components':components,'independent_oracle':result,
  'recognition_inventory':{'opening':zero,'closing':{**zero,'current_produced_return_already_in_curve':str(Decimal(100)-released)},'other_releases':[]},
  'expected':{'revenue':'100.00','deductions':'0.00','base':format(Decimal('700')+Decimal(monthly['vApur']),'.2f'),
   'released_curve':format(released,'.8f'),'auxiliary_base':monthly['vApur']}})
save(E/'research.json',{'legal_url':'https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html',
 'legal_sha256':qual['source_hash'],'manual_file':'dere-observed-20261005-mod.pdf',
 'manual_sha256':hashlib.sha256((F/'sources/official/dere-observed-20261005-mod.pdf').read_bytes()).hexdigest(),
 'manual_physical_pages':[86,87],'table_file':'dere-observed-20261005-annex-i.pdf','table_physical_pages':[23,24,34],
 'interpretation':'A disposal at book value releases yield already incorporated in the asset; it does not add it again to vVarMensal. Exact individual components and recognition inventory must prove this. No inference that all new sale gains receive the Selic cap.',
 'normative_homologation_claimed':False,'scope28_completed':False,'production_ready':False})
print('Draft10: four public recognized-yield sale fixtures and two new-result counterexamples frozen.')
