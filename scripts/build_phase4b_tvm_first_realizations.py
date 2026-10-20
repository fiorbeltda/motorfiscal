"""Independent Decimal positions for first-month gains, losses and offsetting sales."""
import copy,json
from pathlib import Path
from dere_daily_memory_oracle import oracle,seal
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal'
DEST=F/'scenarios/tvm-base-2026.10.06-28-draft'
base=json.loads((F/'scenarios/dere-daily-memory-2026.10.06-26/first-purchase-before-rate.json').read_text(encoding='utf-8'))['position']
series=json.loads((F/'catalogs/rtc-codes-2026.10.06-28-draft.json').read_text(encoding='utf-8'))['dere_debt_daily_memory_calculation']['series']
cases={'first-gain':([('sale1','1000','10000.00','10200.00','200')],'200.00','0.00'),
 'first-loss':([('sale1','1000','10000.00','9900.00','-100')],'0.00','100.00'),
 'first-offsetting':([('sale1','400','4000.00','4200.00','200'),('sale2','300','3000.00','2800.00','-200')],'200.00','200.00')}
for name,(sales,revenue,deductions) in cases.items():
 p=copy.deepcopy(base);p['vPisCofins']='0.00';p['appropriations']={};components={}
 for index,(id,qty,book,price,gain) in enumerate(sales):
  p['operations'].append({'id':id,'date':f'2026-09-{15+index:02}','sequence':1,'kind':'SELL','quantity':qty,'book_amount':book,'sale_proceeds':price})
  components[id]={'prior_produced_return':'0','current_produced_return_already_in_curve':'0','new_produced_return_at_sale':gain,
   'deferred_fair_value_realized':'0','new_fair_value_change_at_sale':'0','rounding_bridge':'0'}
 curve,monthly,result=oracle(p,series);p.pop('appropriations');p['contract_curve']=curve;p['declared_monthly']=monthly;seal(p)
 fixture={'position':p,'independent_oracle':result,'components':components,'expected':{'revenue':revenue,'deductions':deductions}}
 path=DEST/(name+'.json')
 if path.exists():assert json.loads(path.read_text(encoding='utf-8'))==fixture,path
 else:path.write_text(json.dumps(fixture,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print('Three independent first-acquisition realization fixtures frozen.')

