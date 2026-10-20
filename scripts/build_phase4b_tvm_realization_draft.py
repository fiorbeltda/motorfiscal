"""Freeze synthetic component oracles for the unfinished realization scope28.

These are reconciliation fixtures, not acceptance of fiscal totalization or XML.
"""
import copy,json,sys
from decimal import Decimal
from pathlib import Path
from dere_daily_memory_oracle import oracle,proof,seal,digest

ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'resources/fiscal'
DEST=F/'scenarios/tvm-realization-draft-2026.10.06-28'
DEST.mkdir(parents=True,exist_ok=True)
catalog=json.loads((F/'catalogs/rtc-codes-2026.10.06-26.json').read_text(encoding='utf-8'))
series=catalog['dere_debt_daily_memory_calculation']['series']
base=json.loads((F/'scenarios/dere-daily-memory-2026.10.06-26/partial-sale-gain.json').read_text(encoding='utf-8'))['position']
D=Decimal
def write(name,v):
 p=DEST/(name+'.json')
 if p.exists():
  old=json.loads(p.read_text(encoding='utf-8'))
  if old==v:return
  assert '--repair-draft-amortization-quantity' in sys.argv and name=='amortization-and-sale' and old['position']['operations'][1]['quantity']=='300',p
  archive=ROOT/'storage/app/phase4b-realization-draft-20261006-28/draft-initial-amortization.json'
  archive.parent.mkdir(parents=True,exist_ok=True)
  if archive.exists():assert archive.read_bytes()==p.read_bytes()
  else:archive.write_bytes(p.read_bytes())
  p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
 else:p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
def build(name,prior='0',fair='0',curve_before='0',new_produced='200',new_fair='0',public=False,total=False):
 v=copy.deepcopy(base)
 p,f,c=D(prior),D(fair),D(curve_before)
 book=D('10000')+p+f
 v['opening']['book_balance']=format(book,'.2f')
 v['previous_position_evidence']['value']['closing']=copy.deepcopy(v['opening'])
 old=v['previous_position_evidence'];old.pop('source_hash');old['source_hash']=digest(old)
 v['appropriations']={r['date']:r['appropriation'] for r in v['contract_curve']}
 if c:v['appropriations']['2026-09-10']=str(c)
 part=D('1') if total else D('.4')
 release=(book+c)*part
 gain=D(new_produced)+D(new_fair)
 sale=v['operations'][0];sale['quantity']='1000' if total else '400'
 sale['book_amount']=format(release,'.2f');sale['sale_proceeds']=format(release+gain,'.2f')
 sale['claimed_acquisition_cost_released']=format(D('10000')*part,'.8f')
 if total:v['appropriations']={}
 curve,monthly,result=oracle(v,series)
 v.pop('appropriations');v['contract_curve']=curve;v['declared_monthly']=monthly;seal(v)
 components={'prior_produced_return':str(p*part),'current_produced_return_already_in_curve':str(c*part),
  'new_produced_return_at_sale':new_produced,'deferred_fair_value_realized':str(f*part),
  'new_fair_value_change_at_sale':new_fair,'rounding_bridge':'0'}
 opening={'prior_produced_return':prior,'current_produced_return_already_in_curve':'0','deferred_fair_value_realized':fair,'rounding_bridge':'0'}
 closing={**opening,'prior_produced_return':str(p*(1-part)),'deferred_fair_value_realized':str(f*(1-part)),
  'current_produced_return_already_in_curve':str(D(monthly['vVarMensal'])-c*part)}
 expectation={'accounting_gain':str(gain),'price_minus_acquisition_cost':str(release+gain-D('10000')*part),
  'ordinary_current_component':str(f*part+D(new_fair)+(D(0) if public else D(new_produced))),
  'new_public_produced_return':new_produced if public else '0'}
 write(name,{'position':v,'public':public,'components':components,
  'recognition_inventory':{'opening':opening,'closing':closing,'other_releases':[]},
  'expectation':expectation,'independent_daily_oracle':result,
  'scope':'SYNTHETIC_DRAFT_COMPONENT_RECONCILIATION_ONLY'})
build('private-accounting-gain')
build('public-produced-return',public=True)
build('prior-return',prior='100',new_produced='160')
build('deferred-fair-value',fair='200',new_produced='120')
build('mixed-components',prior='100',fair='200',new_produced='30',new_fair='50')
build('current-curve',curve_before='100',new_produced='160')
build('complete-prior-realization',prior='100',new_produced='100',total=True)
build('complete-loss',new_produced='0',new_fair='-100',total=True)
def complex_case(name,prior,fair,operations,parts,retained_prior,retained_fair,other=None):
 v=copy.deepcopy(base);v['opening']['book_balance']=format(D('10000')+D(prior)+D(fair),'.2f')
 v['previous_position_evidence']['value']['closing']=copy.deepcopy(v['opening'])
 old=v['previous_position_evidence'];old.pop('source_hash');old['source_hash']=digest(old)
 v['operations']=operations;v['appropriations']={'2026-09-30':'150'}
 curve,monthly,result=oracle(v,series)
 v.pop('appropriations');v['contract_curve']=curve;v['declared_monthly']=monthly;seal(v)
 components={};expectations={}
 for op,allocation in parts.items():
  p,f,np,nf=map(D,allocation)
  components[op]={'prior_produced_return':str(p),'current_produced_return_already_in_curve':'0',
   'new_produced_return_at_sale':str(np),'deferred_fair_value_realized':str(f),
   'new_fair_value_change_at_sale':str(nf),'rounding_bridge':'0'}
  expectations[op]={'accounting_gain':str(np+nf),'price_minus_acquisition_cost':str(p+f+np+nf),
   'ordinary_current_component':str(f+np+nf),'new_public_produced_return':'0'}
 empty={'prior_produced_return':'0','current_produced_return_already_in_curve':'0','deferred_fair_value_realized':'0','rounding_bridge':'0'}
 inventory={'opening':{**empty,'prior_produced_return':prior,'deferred_fair_value_realized':fair},
  'closing':{**empty,'prior_produced_return':retained_prior,'deferred_fair_value_realized':retained_fair,'current_produced_return_already_in_curve':'150'},'other_releases':other or []}
 write(name,{'position':v,'public':False,'components_by_sale':components,'expectations_by_sale':expectations,
  'recognition_inventory':inventory,'independent_daily_oracle':result,'scope':'SYNTHETIC_DRAFT_COMPONENT_RECONCILIATION_ONLY'})
def sale(id,day,qty,book,price):return {'id':id,'date':day,'sequence':1,'kind':'SELL','quantity':qty,'book_amount':book,'sale_proceeds':price}
complex_case('offsetting-sales','0','0',[sale('sale1','2026-09-14','400','4000.00','4200.00'),sale('sale2','2026-09-15','300','3000.00','2800.00')],
 {'sale1':['0','0','0','200'],'sale2':['0','0','0','-200']},'0','0')
complex_case('two-sales-history','200','0',[sale('sale1','2026-09-14','400','4080.00','4200.00'),sale('sale2','2026-09-15','300','3060.00','3100.00')],
 {'sale1':['80','0','120','0'],'sale2':['60','0','40','0']},'60','0')
empty={'prior_produced_return':'0','current_produced_return_already_in_curve':'0','deferred_fair_value_realized':'0','rounding_bridge':'0'}
complex_case('coupon-and-sale','100','0',[{'id':'coupon1','date':'2026-09-10','sequence':1,'kind':'COUPON','book_amount':'50.00'},sale('sale1','2026-09-14','400','4020.00','4200.00')],
 {'sale1':['20','0','180','0']},'30','0',[{'operation_id':'coupon1','components':{**empty,'prior_produced_return':'50'}}])
complex_case('amortization-and-sale','0','200',[{'id':'amort1','date':'2026-09-10','sequence':1,'kind':'AMORTIZATION','book_amount':'2040.00','acquisition_cost_reduction':'2000'},sale('sale1','2026-09-14','375','3060.00','3100.00')],
 {'sale1':['0','60','40','0']},'0','100',[{'operation_id':'amort1','components':{**empty,'deferred_fair_value_realized':'40'}}])
print('Twelve draft reconciliation fixtures frozen; fiscal/XML acceptance remains pending.')
