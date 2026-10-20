"""Freeze independent cumulative gross rounding allocations; never invent book postings."""
import copy,hashlib,json
from datetime import datetime,timezone
from decimal import Decimal,ROUND_HALF_EVEN
from pathlib import Path
from dere_daily_memory_oracle import oracle,seal,digest
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.07-28-draft9'
E=ROOT/'storage/app/phase4b-realization-rounding-draft-20261007-28';S=F/f'scenarios/tvm-rounding-{V}'
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
b=read(F/'bundles/consumption-phase4b-2026.10.07-28-draft8.json');target=F/f'bundles/consumption-phase4b-{V}.json'
known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
c=read(F/b['resource_paths']['rtc_code_catalog']);c['version']=V;c['financial_operation']['base']['securities_realization']['published_at']=known
c['dere_monthly_closing_projection']['securities_realization_binding'].update(published_at=known,daily_journal_rounding_mode='PROVIDED_CUMULATIVE_GROSS_HALF_EVEN_8_2')
cp=f'catalogs/rtc-codes-{V}.json';save(F/cp,c)
ctrl=read(F/b['production_controls']['path']);ctrl['version']=V
ct=f'production/phase4b-controls-{V}.json';save(F/ct,ctrl)
b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,knowledge_available_at=known)
b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V;b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
base=read(F/'scenarios/tvm-base-2026.10.06-28-draft/first-gain.json')['position']
zero=dict.fromkeys(['prior_produced_return','current_produced_return_already_in_curve','deferred_fair_value_realized','rounding_bridge'],'0')
for name,appropriations,rev,ded,net in [
 ('positive-fractions',{'2026-09-16':'.004','2026-09-17':'.004','2026-09-18':'.004'},'200.01','0.00','900.01'),
 ('negative-fractions',{'2026-09-16':'-.004','2026-09-17':'-.004','2026-09-18':'-.004'},'200.00','0.01','899.99'),
 ('opposing-fractions',{'2026-09-16':'.004','2026-09-17':'-.004','2026-09-18':'.004','2026-09-19':'-.004'},'200.01','0.01','900.00')]:
 p=copy.deepcopy(base);p['operations'][1].update(quantity='400',book_amount='4000.00',sale_proceeds='4200.00');p['operations'][1].pop('claimed_acquisition_cost_released',None)
 p['appropriations']=appropriations;curve,monthly,result=oracle(p,c['dere_debt_daily_memory_calculation']['series']);p.pop('appropriations');p['contract_curve']=curve;p['declared_monthly']=monthly;seal(p)
 raw={'C':Decimal(0),'D':Decimal(0)};booked={'C':Decimal(0),'D':Decimal(0)};allocations=[]
 for date,value in sorted(appropriations.items()):
  value=Decimal(value);nature='D' if value<0 else 'C';raw[nature]+=abs(value);total=raw[nature].quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN);increment=total-booked[nature];booked[nature]=total
  signed=-increment if nature=='D' and increment else increment
  allocations.append({'date':date,'nature':nature,'raw_amount':format(value,'.8f'),'booked_amount':format(signed,'.2f'),'rounding_residual':format(value-signed,'.8f'),
   'accumulated_raw_gross':format(raw[nature],'.8f'),'accumulated_booked_gross':format(total,'.2f')})
 loss=[]
 if Decimal(ded):loss=[{'component_id':'CURVE_DEBIT','component_kind':'CURRENT_PRODUCED_RETURN','signed_unrounded_amount':format(-raw['D'],'.8f'),'amount':ded,'bucket':'DEDUCTION','expected_codTrib':'411011102','source_article':'LC214 art187; art192 caput III'}]
 save(S/(name+'.json'),{'position':p,'independent_oracle':result,'components':{'sale1':{**zero,'new_produced_return_at_sale':'200','new_fair_value_change_at_sale':'0'}},
  'recognition_inventory':{'opening':zero,'closing':{**zero,'current_produced_return_already_in_curve':format(sum(map(Decimal,appropriations.values())),'.8f')},'other_releases':[]},
  'curve_deduction_components':loss,'daily_journal_rounding':{'method':'CUMULATIVE_GROSS_HALF_EVEN_8_2','normative_homologation_claimed':False,'allocations':allocations},
  'expected':{'revenue':rev,'deductions':ded,'net_base':net,'gross_curve_credit':format(booked['C'],'.2f'),'gross_curve_debit':format(booked['D'],'.2f')}})
save(E/'research.json',{'manual_url':'https://www.cgibs.gov.br/declaracao-de-regimes-especificos-dere','manual_file':'dere-observed-20261005-mod.pdf',
 'manual_sha256':hashlib.sha256((F/'sources/official/dere-observed-20261005-mod.pdf').read_bytes()).hexdigest(),'physical_page':66,
 'official_rule':'D1101 values at two decimal places following ABNT NBR5891; gross movements and adjustment limits remain required.',
 'local_method':'Provided cumulative gross HALF_EVEN allocations, separately for debit and credit; independent evidence required; not an official mandate for daily posting.',
 'normative_homologation_claimed':False,'scope28_completed':False,'production_ready':False})
print('Draft9 and three independent sub-cent daily allocation fixtures frozen.')
