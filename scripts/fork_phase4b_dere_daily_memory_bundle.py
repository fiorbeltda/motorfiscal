"""Freeze a full daily acquisition-cost memory corpus and an independent decimal oracle."""
import copy,hashlib,json,sys
from datetime import datetime,timezone,date,timedelta
from decimal import Decimal,localcontext,ROUND_HALF_EVEN
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.06-26';S=f'scenarios/dere-daily-memory-{V}';E=ROOT/'storage/app/phase4b-daily-memory-20261006-26';E.mkdir(exist_ok=True)
def read(p):return json.loads((F/p).read_text(encoding='utf-8'))
def digest(v):return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
def write(p,v):
 p=F/p
 if p.exists():
  if json.loads(p.read_text(encoding='utf-8'))==v:return
  backup=E/'draft-initial-fixtures'/p.name
  assert '--repair-initial-decimal-format' in sys.argv and p.parent==F/S and backup.read_bytes()==p.read_bytes(),p
 p.parent.mkdir(parents=True,exist_ok=True);p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
baseline={}
for base in [F,ROOT/'storage/app']:
 for p in base.rglob('*'):
  if p.is_file() and E not in p.parents and V not in str(p) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']:baseline[p.relative_to(ROOT).as_posix()]=hashlib.sha256(p.read_bytes()).hexdigest()
if not (E/'historical-before.json').exists():(E/'historical-before.json').write_text(json.dumps(baseline,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
b=read('bundles/consumption-phase4b-2026.10.06-25.json');old=copy.deepcopy(b);bp=f'bundles/consumption-phase4b-{V}.json'
available=read(bp)['knowledge_available_at'] if (F/bp).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
b.update(bundle_id=f'CONSUMPTION-PHASE4B-{V}',version=V,predecessor_bundle_id=old['bundle_id'],knowledge_available_at=available)
c=read(b['resource_paths']['rtc_code_catalog']);c['version']=V
calc=copy.deepcopy(c['dere_debt_constant_cost_calculation']);calc.update(contract='DERE-D2101-DAILY-MEMORY-LOCAL-2026.10.06-1',published_at=available,scope='LOCAL_EXPLICIT_METHOD_DAILY_MEMORY_NO_TAX_OR_XML_ACTIVATION')
c['dere_debt_daily_memory_calculation']=calc
cp=f'catalogs/rtc-codes-{V}.json';write(cp,c);b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V
ctrl=read(b['production_controls']['path']);ctrl['version']=V
for r in ctrl['controls']:
 if r['id'] in ['4B.4.BASE','4B.7.DOCUMENT']:r['gap']+=' Candidato26: memoria diaria local de custo medio, compras/saidas/amortizacoes/cupons, curvas contabeis e benchmark Selic com convencao temporal expressa. Homologacao do metodo, tributacao do ganho de venda e integracao mensal variavel pendentes.'
cp=f'production/phase4b-controls-{V}.json';write(cp,ctrl);b['production_controls']={'path':cp,'sha256':digest(ctrl)};write(bp,b)
write(f'sources/official/phase4b-manifest-{V}.json',{'version':V,'retrieved_on':'2026-10-06','status':'SANDBOX_EVIDENCE_REVIEW_REQUIRED','artifacts':b['official_sources']})
for family in ['consumption-phase4b','consumption-integrated']:
 for p in sorted((F/f'scenarios/{family}-2026.10.06-25').glob('*.json')):
  v=json.loads(p.read_text(encoding='utf-8'));v.update(rule_bundle_id=b['bundle_id'],catalog_versions=b['catalog_versions'],knowledge_at=available);write(f'scenarios/{family}-{V}/{p.name}',v)
D=Decimal
def fmt(v):return format(v,'f')
def q8(v):return v.quantize(D('.00000001'),rounding=ROUND_HALF_EVEN)
def q2(v):return v.quantize(D('.01'),rounding=ROUND_HALF_EVEN)
def proof(value,id,start='2026-09-01',end='2026-09-30'):
 p={'qualified':True,'evidence_id':id,'supplier_id':'11111111000191','valid_from':start,'valid_to':end,'observed_on':'2026-10-06','scope':'SYNTHETIC_INDEPENDENT_DAILY_POSITION_CURVE_AND_METHOD','value':copy.deepcopy(value)};p['source_hash']=digest(p);return p
def seal(v):
 v.pop('memory_evidence',None);v['memory_evidence']=proof(v,'FIXTURE.DAILY.COMPLETE')
def oracle(v):
 with localcontext() as context:
  context.prec=100;qty=D(v['opening']['quantity']);cost=D(v['opening']['acquisition_cost']);book=D(v['opening']['book_balance']);benchmark=cost;interest=D(0);variation=D(0);entries=D(0);exits=D(0);coupons=D(0);gain=D(0)
  rates={r['date']:D(r['daily_percent']) for r in calc['series']['rows']};bydate={}
  for e in v['operations']:bydate.setdefault(e['date'],[]).append(e)
  memory=[];curve=[]
  for offset in range(30):
   day=(date(2026,9,1)+timedelta(days=offset)).isoformat();events=bydate.get(day,[])
   def apply():
    nonlocal qty,cost,book,benchmark,entries,exits,coupons,gain
    for e in events:
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
   appropriation=D(v.get('appropriations',{}).get(day,'0'));book=q8(book+appropriation);variation=q8(variation+appropriation);unit=q8(cost/qty) if qty else q8(D(0))
   row={'date':day,'contract_curve_id':'FIXTURE.CURVE.'+day,'appropriation':fmt(q8(appropriation)),**{'claimed_'+k:fmt(q8(value)) for k,value in [('quantity',qty),('acquisition_cost',cost),('unit_cost',unit),('book_balance',book),('selic_basis',basis)]}}
   curve.append(row);memory.append({'date':day,'quantity':fmt(q8(qty)),'acquisition_cost':fmt(q8(cost)),'unit_cost':fmt(unit),'book_balance':fmt(book),'selic_basis':fmt(q8(basis)),'daily_selic_interest':fmt(daily),'accumulated_selic_interest':fmt(interest),'remaining_benchmark_balance':fmt(benchmark)})
  selic=q2(interest);var=q2(variation);pis=D(v['vPisCofins']);base=max(D(0),min(selic,var)-pis)
  monthly={'vSaldoContIni':v['opening']['book_balance'],'vEntradas':fmt(q2(entries)),'vSaidas':fmt(q2(exits)),'vJurosRec':fmt(q2(coupons)),'vVarMensal':fmt(var),'vSaldoContFin':fmt(q2(book)),'vSelic':fmt(selic),'vPisCofins':fmt(pis),'vApur':fmt(q2(base))}
  return curve,monthly,{'daily_memory':memory,'closing_quantity':fmt(q8(qty)),'closing_acquisition_cost':fmt(q8(cost)),'closing_unit_cost':fmt(unit),'realized_sale_gain':fmt(q2(gain)),'monthly':monthly}
isin=read('scenarios/dere-yield-2026.10.06-25/capped-yield-financial.json')['facts']['rtc.financial_ledger'][0]['entries'][-1]['public_offer_debt']['title']['idTitulo']
base={'supplier_id':'11111111000191','period':'2026-09','idTitulo':isin,'cCta':'debt000','first_acquisition':False,
 'opening':{'quantity':'1000','unit_cost':'10.00','acquisition_cost':'10000.00','book_balance':'10000.00'},
 'method':{'cost_method':'WEIGHTED_AVERAGE_ACQUISITION_COST','event_timing':'BEFORE_DAILY_RATE','benchmark_method':'REMAINING_POSITION_COMPOUND_WITH_REALIZED_INTEREST_RETAINED','coupon_effect':'ACCOUNTING_ONLY_NO_ACQUISITION_COST_OR_BENCHMARK_REDUCTION','normative_homologation_claimed':False},
 'operations':[],'appropriations':{'2026-09-30':'150.00'},'vPisCofins':'10.00'}
def event(id,day,kind,book,**extra):return {'id':id,'date':day,'sequence':1,'kind':kind,'book_amount':book,**extra}
positives={}
def positive(name,modify):
 v=copy.deepcopy(base);modify(v);v['method_evidence']=proof({'contract':calc['contract'],'method':v['method']},'FIXTURE.DAILY.METHOD')
 if not v['first_acquisition']:v['previous_position_evidence']=proof({'supplier_id':v['supplier_id'],'idTitulo':v['idTitulo'],'cCta':v['cCta'],'period':'2026-08','closing':v['opening']},'FIXTURE.DAILY.PREVIOUS','2026-08-31','2026-08-31')
 v['contract_curve'],v['declared_monthly'],expect=oracle(v);v.pop('appropriations');seal(v);positives[name]=v;write(f'{S}/{name}.json',{'position':v,'expectation':{'status':'RESOLVED','oracle':expect}})
positive('constant',lambda v:None)
positive('first-purchase-before-rate',lambda v:v.update(first_acquisition=True,opening={'quantity':'0','unit_cost':'0','acquisition_cost':'0','book_balance':'0.00'},operations=[event('buy1','2026-09-14','BUY','10000.00',quantity='1000',unit_price='10.00')]))
positive('first-purchase-after-rate',lambda v:(v.update(first_acquisition=True,opening={'quantity':'0','unit_cost':'0','acquisition_cost':'0','book_balance':'0.00'},operations=[event('buy1','2026-09-14','BUY','10000.00',quantity='1000',unit_price='10.00')]),v['method'].update(event_timing='AFTER_DAILY_RATE')))
positive('weighted-additional-purchase',lambda v:v.update(operations=[event('buy1','2026-09-14','BUY','6000.00',quantity='500',unit_price='12.00')]))
positive('partial-sale-gain',lambda v:v.update(operations=[event('sale1','2026-09-14','SELL','4000.00',quantity='400',sale_proceeds='4200.00')]))
positive('complete-sale',lambda v:v.update(operations=[event('sale1','2026-09-14','SELL','10000.00',quantity='1000',sale_proceeds='9900.00')],appropriations={}))
positive('partial-amortization',lambda v:v.update(operations=[event('amort1','2026-09-14','AMORTIZATION','2000.00',acquisition_cost_reduction='2000.00')]))
positive('complete-amortization',lambda v:v.update(operations=[event('amort1','2026-09-14','AMORTIZATION','10000.00',acquisition_cost_reduction='10000.00')],appropriations={}))
positive('coupon',lambda v:v.update(operations=[event('coupon1','2026-09-14','COUPON','50.00')]))
positive('weekend-purchase',lambda v:v.update(operations=[event('buy1','2026-09-05','BUY','6000.00',quantity='500',unit_price='12.00')]))
positive('holiday-purchase',lambda v:v.update(operations=[event('buy1','2026-09-07','BUY','6000.00',quantity='500',unit_price='12.00')]))
positive('same-day-ordered-buy-sale',lambda v:v.update(operations=[event('buy1','2026-09-14','BUY','6000.00',quantity='500',unit_price='12.00'),dict(event('sale1','2026-09-14','SELL','3200.00',quantity='300',sale_proceeds='3300.00'),sequence=2)]))
positive('different-book-and-acquisition-cost',lambda v:v['opening'].update(book_balance='10100.00'))
positive('negative-appropriation',lambda v:v.update(appropriations={'2026-09-30':'-50.00'},vPisCofins='0.00'))
positive('zero-variation',lambda v:v.update(appropriations={},vPisCofins='0.00'))
positive('fractional-quantity-pu',lambda v:v.update(operations=[event('buy1','2026-09-14','BUY','30.37',quantity='3',unit_price='10.12345678')]))
negative={}
def bad(name,modify,reason,origin='constant',reseal=True):
 v=copy.deepcopy(positives[origin]);modify(v)
 if reseal:seal(v)
 negative[name]=reason;write(f'{S}/{name}.json',{'position':v,'expectation':{'status':'ACTION_REQUIRED','reason':'RTC_DERE_D2101_DAILY_'+reason}})
bad('missing-memory-proof',lambda v:v.pop('memory_evidence'),'COMPLETE_MEMORY_EVIDENCE_UNPROVEN',reseal=False)
bad('tampered-memory-proof',lambda v:v['memory_evidence'].update(source_hash='0'*64),'COMPLETE_MEMORY_EVIDENCE_UNPROVEN',reseal=False)
bad('unqualified-memory-proof',lambda v:v['memory_evidence'].update(qualified=False),'COMPLETE_MEMORY_EVIDENCE_UNPROVEN',reseal=False)
bad('missing-method-proof',lambda v:v.pop('method_evidence'),'TEMPORAL_OR_BENCHMARK_METHOD_UNPROVEN')
bad('ambiguous-timing',lambda v:v['method'].update(event_timing='UNSPECIFIED'),'TEMPORAL_OR_BENCHMARK_METHOD_UNPROVEN')
bad('homologation-claim',lambda v:v['method'].update(normative_homologation_claimed=True),'TEMPORAL_OR_BENCHMARK_METHOD_UNPROVEN')
bad('missing-prior',lambda v:v.pop('previous_position_evidence'),'PREVIOUS_POSITION_UNPROVEN')
bad('wrong-prior-account',lambda v:v['previous_position_evidence']['value'].update(cCta='other'),'PREVIOUS_POSITION_UNPROVEN')
bad('wrong-opening-average',lambda v:v['opening'].update(unit_cost='11.00'),'OPENING_QUANTITY_COST_CONFLICT')
bad('curve-lacuna',lambda v:v['contract_curve'].pop(4),'OPERATIONS_OR_CURVE_COVERAGE_INVALID')
bad('curve-duplicate-day',lambda v:v['contract_curve'][4].update(date='2026-09-04'),'CURVE_DATE_OR_ID_INVALID')
bad('curve-position-conflict',lambda v:v['contract_curve'][4].update(claimed_quantity='999'),'DAILY_POSITION_OR_CURVE_CONFLICT')
bad('curve-benchmark-conflict',lambda v:v['contract_curve'][4].update(claimed_selic_basis='1.00'),'DAILY_POSITION_OR_CURVE_CONFLICT')
bad('summary-selic-conflict',lambda v:v['declared_monthly'].update(vSelic='0.00'),'MONTHLY_SUMMARY_CONFLICT')
bad('summary-closing-conflict',lambda v:v['declared_monthly'].update(vSaldoContFin='1.00'),'MONTHLY_SUMMARY_CONFLICT')
bad('sale-exceeds-stock',lambda v:v['operations'][0].update(quantity='1001'),'AMOUNT_DATE_OR_STOCK_INVALID','partial-sale-gain')
bad('sale-cost-conflict',lambda v:v['operations'][0].update(claimed_acquisition_cost_released='3999.00'),'AMOUNT_DATE_OR_STOCK_INVALID','partial-sale-gain')
bad('purchase-amount-conflict',lambda v:v['operations'][0].update(book_amount='6001.00'),'AMOUNT_DATE_OR_STOCK_INVALID','weighted-additional-purchase')
bad('duplicate-operation',lambda v:v['operations'].append(copy.deepcopy(v['operations'][0])),'OPERATION_ID_SEQUENCE_OR_KIND_INVALID','weighted-additional-purchase')
bad('intraday-order-conflict',lambda v:v['operations'][1].update(sequence=1),'OPERATION_ORDER_OR_DATE_INVALID','same-day-ordered-buy-sale')
bad('event-after-month',lambda v:v['operations'][0].update(date='2026-10-01'),'OPERATION_ORDER_OR_DATE_INVALID','weighted-additional-purchase')
bad('negative-book',lambda v:v['operations'][0].update(book_amount='20000.00'),'AMOUNT_DATE_OR_STOCK_INVALID','coupon')
bad('amortization-exceeds-cost',lambda v:v['operations'][0].update(acquisition_cost_reduction='10001.00'),'AMOUNT_DATE_OR_STOCK_INVALID','partial-amortization')
bad('curve-after-total-sale',lambda v:v['contract_curve'][-1].update(appropriation='1.00'),'CURVE_WITHOUT_HELD_POSITION','complete-sale')
bad('missing-first-buy',lambda v:v.update(operations=[]),'FIRST_ACQUISITION_MISSING','first-purchase-before-rate')
bad('future-observed-proof',lambda v:v['memory_evidence'].update(observed_on='2026-10-07'),'COMPLETE_MEMORY_EVIDENCE_UNPROVEN',reseal=False)
bad('memory-invalid-date',lambda v:v['contract_curve'][0].update(date='2026-09-31'),'CURVE_DATE_OR_ID_INVALID')
bad('noncanonical-quantity',lambda v:v['opening'].update(quantity='1e3'),'AMOUNT_DATE_OR_STOCK_INVALID')
print(f'Candidate26 frozen:{len(positives)} positive/{len(negative)} negative;independent decimal oracle and daily curves;47 official sources.')
