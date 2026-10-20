"""Prepare subsequent sales with separately proved old and newly created rounding."""
import copy,hashlib,json
from pathlib import Path
from datetime import datetime,timezone
from decimal import Decimal,ROUND_HALF_EVEN
from dere_daily_memory_oracle import digest,oracle,proof
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.08-28-draft33'
E=ROOT/'storage/app/phase4b-realization-historical-sale-rounding-draft-20261008-28';S=F/('scenarios/tvm-historical-sale-rounding-'+V)
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
   if p.is_file() and E not in p.parents and V not in str(p) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']:baseline[p.relative_to(ROOT).as_posix()]=hashlib.sha256(p.read_bytes()).hexdigest()
 save(E/'historical-before.json',baseline)
b=read(F/'bundles/consumption-phase4b-2026.10.08-28-draft32.json');c=read(F/b['resource_paths']['rtc_code_catalog']);target=F/('bundles/consumption-phase4b-'+V+'.json');known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
method='PROVED_HISTORICAL_AVERAGE_RELEASE_WITH_SEPARATE_CREATED_ROUNDING_8_2'
c['version']=V;q=c['financial_operation']['base']['public_offer_debt'];q['published_at']=known;a=q['sale_recognition_policy']['analysis'];a['published_at']=known;a['history_policy']['published_at']=known;a['historical_sale_book_rounding_policy']=method;q['sale_recognition_policy']['published_at']=known
c['financial_operation']['base']['securities_realization'].update(published_at=known,analysis=copy.deepcopy(a),public_offer_debt=copy.deepcopy(q));c['dere_monthly_closing_projection']['securities_realization_binding']['published_at']=known;c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(q)
cp='catalogs/rtc-codes-'+V+'.json';save(F/cp,c);ctrl=read(F/b['production_controls']['path']);ctrl['version']=V;ct='production/phase4b-controls-'+V+'.json';save(F/ct,ctrl);b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,knowledge_available_at=known);b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V;b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
for sign in ['negative','positive','zero','exact']:
 for mode,fraction in [('partial',Decimal('.5')),('complete',Decimal(1))]+([('aligned-partial',Decimal('.5'))] if sign in ['positive','negative'] else []):
  name=sign+'-'+mode;f=read(F/('scenarios/tvm-public-explicit-closing-2026.10.08-28-draft32/'+sign+'-'+mode.split('-')[-1]+'-current.json'));p=f['position'];p.pop('memory_evidence');p['vPisCofins']='5.00';appropriated=Decimal('50.02') if mode.startswith('aligned') else Decimal(50);p['appropriations']={'2026-09-10':str(appropriated)}
  before=Decimal(p['opening']['book_balance'])+appropriated;average=(before*fraction).quantize(Decimal('.00000001'),rounding=ROUND_HALF_EVEN);book=average.quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN);created=book-average
  old=Decimal(f['recognition_inventory']['opening']['rounding_bridge'])*fraction;total=old+created;p['operations'][0].update(book_amount=format(book,'.2f'),sale_proceeds=format(book,'.2f'))
  curve,monthly,independent=oracle(p,c['dere_debt_daily_memory_calculation']['series']);p.pop('appropriations');p.update(contract_curve=curve,declared_monthly=monthly);p['memory_evidence']=proof(p,'SYNTHETIC.HISTORICAL.SALE.ROUNDING.MEMORY')
  parts=f['components']['sale1'];parts['current_produced_return_already_in_curve']=format(appropriated*fraction,'.8f');parts['rounding_bridge']=format(total,'.8f');closing=f['recognition_inventory']['closing'];closing['current_produced_return_already_in_curve']=format(appropriated*(1-fraction),'.8f');closing['rounding_bridge']=format(Decimal(f['recognition_inventory']['opening']['rounding_bridge'])-total,'.8f')
  f['independent_oracle']=independent;f['yield_amount']=format(appropriated,'.2f');f['expected'].update(base=format(Decimal(700)+Decimal(monthly['vApur']),'.2f'),auxiliary_base=monthly['vApur']);f['historical_sale_rounding_oracle']={'unrounded_average_release':format(average,'.8f'),'actual_book_release':format(book,'.2f'),'existing_rounding_consumed':format(old,'.8f'),'created_rounding_adjustment':format(created,'.8f'),'total_rounding_release':format(total,'.8f')}
  save(S/(name+'-current.json'),f)
print('Draft33 and ten historical sale-rounding fixtures prepared; acceptance remains pending.')
