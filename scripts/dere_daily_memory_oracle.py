"""Independent high-precision fixture oracle for daily cost and benchmark curves."""
import copy,hashlib,json,calendar
from datetime import date,timedelta
from decimal import Decimal,localcontext,ROUND_HALF_EVEN
def digest(v):return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(",",":")).encode()).hexdigest()
D=Decimal
def fmt(v):return format(v,'f')
def q8(v):return v.quantize(D('.00000001'),rounding=ROUND_HALF_EVEN)
def q2(v):return v.quantize(D('.01'),rounding=ROUND_HALF_EVEN)
def proof(value,id,start='2026-09-01',end='2026-09-30'):
 p={'qualified':True,'evidence_id':id,'supplier_id':'11111111000191','valid_from':start,'valid_to':end,'observed_on':'2026-10-06','scope':'SYNTHETIC_INDEPENDENT_DAILY_POSITION_CURVE_AND_METHOD','value':copy.deepcopy(value)};p['source_hash']=digest(p);return p
def seal(v):
 v.pop('memory_evidence',None);v['memory_evidence']=proof(v,'FIXTURE.DAILY.COMPLETE')
def oracle(v,series):
 with localcontext() as context:
  context.prec=100;qty=D(v['opening']['quantity']);cost=D(v['opening']['acquisition_cost']);book=D(v['opening']['book_balance']);benchmark=cost;interest=D(0);variation=D(0);entries=D(0);exits=D(0);coupons=D(0);gain=D(0)
  rates={r['date']:D(r['daily_percent']) for r in series['rows']};bydate={}
  for e in v['operations']:bydate.setdefault(e['date'],[]).append(e)
  memory=[];curve=[]
  month_start=date.fromisoformat(v['period']+'-01')
  for offset in range(calendar.monthrange(month_start.year,month_start.month)[1]):
   day=(month_start+timedelta(days=offset)).isoformat();events=bydate.get(day,[])
   appropriation=D(v.get('appropriations',{}).get(day,'0'));before=v.get('appropriation_before_operations',{}).get(day);applied=False;sequence_proof=None
   def apply():
    nonlocal qty,cost,book,benchmark,entries,exits,coupons,gain,variation,applied,sequence_proof
    for e in events:
     if e['id']==before:
      sequence_proof=proof({'supplier_id':v['supplier_id'],'period':v['period'],'idTitulo':v['idTitulo'],'cCta':v['cCta'],'date':day,
       'contract_curve_id':'FIXTURE.CURVE.'+day,'operation_id':before,'appropriation':fmt(q8(appropriation)),
       'quantity_before':fmt(q8(qty)),'acquisition_cost_before':fmt(q8(cost)),'book_balance_before':fmt(q8(book)),
       'produced_yield_not_fair_value':True,'recognized_before_disposal':True},'SYNTHETIC.INDEPENDENT.INTRADAY.YIELD')
      book=q8(book+appropriation);variation=q8(variation+appropriation);applied=True
     amount=D(e['book_amount'])
     if e['kind']=='BUY':
      add=q8(D(e['quantity'])*D(e['unit_price']));qty+=D(e['quantity']);cost=q8(cost+add);benchmark=q8(benchmark+add);book+=amount;entries+=amount
     elif e['kind']=='SELL':
      part=D(e['quantity']);released=cost if part==qty else q8(cost*part/qty);releasebench=benchmark if part==qty else q8(benchmark*part/qty)
      e.setdefault('claimed_acquisition_cost_released',fmt(q8(released)));gain+=D(e['sale_proceeds'])-amount;qty-=part;cost=q8(cost-released);benchmark=q8(benchmark-releasebench);book-=amount;exits+=amount
     elif e['kind']=='AMORTIZATION':
      released=D(e['acquisition_cost_reduction']);releasebench=benchmark if released==cost else q8(benchmark*released/cost);cost=q8(cost-released);benchmark=q8(benchmark-releasebench);book-=amount;exits+=amount
      if cost==0:qty=D(0)
     else:book-=amount;coupons+=amount
   if v['method']['event_timing']=='BEFORE_DAILY_RATE':apply()
   basis=benchmark;rate=rates.get(day,D(0));daily=q8(basis*rate/100);benchmark=q8(benchmark+daily);interest=q8(interest+daily)
   if v['method']['event_timing']=='AFTER_DAILY_RATE':apply()
   if not applied:book=q8(book+appropriation);variation=q8(variation+appropriation)
   unit=q8(cost/qty) if qty else q8(D(0))
   row={'date':day,'contract_curve_id':'FIXTURE.CURVE.'+day,'appropriation':fmt(q8(appropriation)),**{'claimed_'+k:fmt(q8(value)) for k,value in [('quantity',qty),('acquisition_cost',cost),('unit_cost',unit),('book_balance',book),('selic_basis',basis)]}}
   if before is not None:row.update(appropriation_before_operation_id=before,appropriation_sequence_evidence=sequence_proof)
   curve.append(row);memory.append({'date':day,'quantity':fmt(q8(qty)),'acquisition_cost':fmt(q8(cost)),'unit_cost':fmt(unit),'book_balance':fmt(book),'selic_basis':fmt(q8(basis)),'daily_selic_interest':fmt(daily),'accumulated_selic_interest':fmt(interest),'remaining_benchmark_balance':fmt(benchmark)})
  selic=q2(interest);var=q2(variation);pis=D(v['vPisCofins']);base=max(D(0),min(selic,var)-pis)
  monthly={'vSaldoContIni':v['opening']['book_balance'],'vEntradas':fmt(q2(entries)),'vSaidas':fmt(q2(exits)),'vJurosRec':fmt(q2(coupons)),'vVarMensal':fmt(var),'vSaldoContFin':fmt(q2(book)),'vSelic':fmt(selic),'vPisCofins':fmt(pis),'vApur':fmt(q2(base))}
  return curve,monthly,{'daily_memory':memory,'closing_quantity':fmt(q8(qty)),'closing_acquisition_cost':fmt(q8(cost)),'closing_unit_cost':fmt(unit),'realized_sale_gain':fmt(q2(gain)),'monthly':monthly}
