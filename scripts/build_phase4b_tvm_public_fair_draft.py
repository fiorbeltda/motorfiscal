"""Freeze mutually linked D2101 produced yield and D1101 realized fair-value components."""
import copy,hashlib,json
from datetime import datetime,timezone
from decimal import Decimal
from pathlib import Path
from dere_daily_memory_oracle import oracle,seal,digest
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.07-28-draft13'
E=ROOT/'storage/app/phase4b-realization-public-fair-draft-20261007-28';S=F/f'scenarios/tvm-public-fair-{V}'
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
b=read(F/'bundles/consumption-phase4b-2026.10.07-28-draft12.json');target=F/f'bundles/consumption-phase4b-{V}.json'
known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
c=read(F/b['resource_paths']['rtc_code_catalog']);c['version']=V
qual=c['financial_operation']['base']['public_offer_debt'];qual['published_at']=known
qual['sale_recognition_policy']['mode']='PUBLIC_CURVE_D2101_WITH_QUALIFIED_FAIR_VALUE_COMPANION_D1101';qual['sale_recognition_policy']['analysis']['published_at']=known
securities=c['financial_operation']['base']['securities_realization'];securities['published_at']=known
securities['analysis']=copy.deepcopy(qual['sale_recognition_policy']['analysis']);securities['public_curve_origin_policy']='PUBLIC_REALIZATION_WITH_SEPARATELY_QUALIFIED_D2101_CURVE';securities['public_offer_debt']=copy.deepcopy(qual)
c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(qual)
c['dere_monthly_closing_projection']['securities_realization_binding'].update(published_at=known,public_curve_origin_policy='PUBLIC_REALIZATION_WITH_SEPARATELY_QUALIFIED_D2101_CURVE')
cp=f'catalogs/rtc-codes-{V}.json';save(F/cp,c)
ctrl=read(F/b['production_controls']['path']);ctrl['version']=V;ct=f'production/phase4b-controls-{V}.json';save(F/ct,ctrl)
b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,knowledge_available_at=known)
b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V;b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
base=read(F/'scenarios/tvm-public-recognition-2026.10.07-28-draft10/partial.json')
zero=dict.fromkeys(['prior_produced_return','current_produced_return_already_in_curve','deferred_fair_value_realized','rounding_bridge'],'0')
for name,quantities,gains,fund in [('gain',[400],[50],False),('loss',[400],[-50],False),('full-gain',[1000],[50],False),('opposing-fair',[400,600],[50,-50],False),('exclusive-fund',[400],[50],True)]:
 p=copy.deepcopy(base['position']);title=copy.deepcopy(base['title']);p['operations']=p['operations'][:1];components={}
 if fund:p['cCta']='debtfund000';title.update(cCta='debtfund000',holding_route='EXCLUSIVE_FUND_95',exclusive_fund=True,descFundo='Fundo exclusivo fixture',public_offer_portfolio_percent='95.00')
 for i,(q,gain) in enumerate(zip(quantities,gains)):
  opid='sale'+str(i+1);book=Decimal(q)*Decimal('10.1')
  p['operations'].append({'id':opid,'date':'2026-09-15','sequence':i+1,'kind':'SELL','quantity':str(q),'book_amount':format(book,'.2f'),'sale_proceeds':format(book+Decimal(gain),'.2f')})
  components[opid]={**zero,'current_produced_return_already_in_curve':str(Decimal(q)/10),'new_produced_return_at_sale':'0','new_fair_value_change_at_sale':str(gain)}
 p['appropriations']={'2026-09-14':'100'};curve,monthly,independent=oracle(p,qual['daily_memory_calculation']['series']);p.pop('appropriations');p['contract_curve']=curve;p['declared_monthly']=monthly;seal(p)
 rev=sum((max(Decimal(gain),Decimal(0)) for gain in gains),Decimal(0));ded=sum((max(-Decimal(gain),Decimal(0)) for gain in gains),Decimal(0));released=sum(map(Decimal,quantities))/10
 save(S/(name+'.json'),{'position':p,'title':title,'components':components,'independent_oracle':independent,
 'recognition_inventory':{'opening':zero,'closing':{**zero,'current_produced_return_already_in_curve':str(Decimal(100)-released)},'other_releases':[]},
 'expected':{'revenue':format(rev,'.2f'),'deductions':format(ded,'.2f'),'base':format(Decimal(700)+Decimal(monthly['vApur'])+rev-ded,'.2f'),'auxiliary_base':monthly['vApur'],'fair_revenue':format(rev,'.2f'),'fair_deductions':format(ded,'.2f')}})
save(E/'research.json',{'legal_url':'https://normas.leg.br/?urn=urn%3Alex%3Abr%3Afederal%3Alei.complementar%3A2025-01-16%3B214%21art192','legal_sha256':qual['source_hash'],
 'table_file':'dere-observed-20261005-annex-i.pdf','table_sha256':hashlib.sha256((F/'sources/official/dere-observed-20261005-annex-i.pdf').read_bytes()).hexdigest(),'table_physical_pages':[23,24,34],
 'rule':'Produced public-debt yield is qualified once in D2101; independently evidenced fair-value excess realized at sale is a separate D1101 component under art192 paragraph6, with subaccount and authorized deductions.',
 'limits':['No inference that new produced return at sale receives the Selic cap','Provided journals only, without mandated reclassification or automatic posting','Synthetic eligibility and mapping do not prove PGCC reception or homologation'],
 'scope28_completed':False,'production_ready':False})
print('Draft13: five public yield/fair-value disposal fixtures frozen with separate fiscal origins.')
