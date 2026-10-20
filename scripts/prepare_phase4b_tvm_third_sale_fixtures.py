"""Reproduce twelve third-sale oracles from six replay-qualified actual source openings."""
import copy,hashlib,json,subprocess
from pathlib import Path
from decimal import Decimal,ROUND_HALF_EVEN
from dere_daily_memory_oracle import oracle,proof
ROOT=Path(__file__).resolve().parents[1];V='2026.10.08-28-draft37';E=ROOT/'storage/app/phase4b-realization-third-sale-rounding-draft-20261008-28';S=ROOT/('resources/fiscal/scenarios/tvm-third-sale-rounding-'+V)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,value):
 if p.exists():assert read(p)==value,p
 else:
  with p.open('x',encoding='utf-8') as out:json.dump(value,out,ensure_ascii=False,indent=2)
def fmt(v):return format(v,'.8f')
policy=read(ROOT/('resources/fiscal/catalogs/rtc-codes-'+V+'.json'))['dere_debt_daily_memory_calculation'];cases=[]
for path in sorted(S.glob('*-opening.json')):
 f=read(path);opening=f['scope']['opening'];carry=f['expected']['opening_carrying_components'];fiscal=f['expected']['opening_fiscal_components'];sourceposition=f['history']['closing_memory']['position'];sourcecase=path.stem.removesuffix('-opening')
 assert sha(ROOT/f['source_path'])==f['source_sha256']
 for mode,fraction in [('partial',Decimal('.5')),('complete',Decimal(1))]:
  name=sourcecase+'-'+mode;p=copy.deepcopy(sourceposition);p.pop('memory_evidence',None);p.update(period='2026-09',first_acquisition=False,opening=opening,vPisCofins='5.00');p['method_evidence']=proof({'contract':policy['contract'],'method':p['method']},'SYNTHETIC.THIRD.SALE.METHOD');p['previous_position_evidence']=proof({'supplier_id':p['supplier_id'],'idTitulo':p['idTitulo'],'cCta':p['cCta'],'period':'2026-08','closing':opening},'SYNTHETIC.THIRD.SALE.PREVIOUS','2026-08-01','2026-08-31')
  before=Decimal(opening['book_balance'])+Decimal(50);average=(before*fraction).quantize(Decimal('.00000001'),rounding=ROUND_HALF_EVEN);book=average.quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN);created=book-average;covered=Decimal(carry['fiscal_covered_rounding'])*fraction;uncovered=Decimal(carry['uncovered_created_rounding'])*fraction;total=covered+uncovered+created;prior=Decimal(carry['prior_produced_return'])*fraction;cost=Decimal(opening['acquisition_cost'])*fraction;assert cost+prior+Decimal(50)*fraction+total==book
  p['operations']=[{'id':'sale1','date':'2026-09-14','sequence':1,'kind':'SELL','quantity':fmt(Decimal(opening['quantity'])*fraction),'book_amount':format(book,'.2f'),'sale_proceeds':format(book,'.2f')}];p['appropriations']={'2026-09-10':'50.00'};curve,monthly,independent=oracle(p,policy['series']);p.pop('appropriations');p.update(contract_curve=curve,declared_monthly=monthly);p['memory_evidence']=proof(p,'SYNTHETIC.THIRD.SALE.MEMORY');remaining=1-fraction
  consumed={k:Decimal(v)*fraction for k,v in fiscal.items()};assert sum(consumed.values())==prior+covered
  amounts={'unrounded_average_release':fmt(average),'actual_book_release':format(book,'.2f'),'fiscal_covered_rounding_consumed':fmt(covered),'uncovered_created_rounding_consumed':fmt(uncovered),'created_rounding_adjustment':fmt(created),'total_rounding_release':fmt(total)}
  parts={'prior_produced_return':fmt(prior),'current_produced_return_already_in_curve':fmt(Decimal(50)*fraction),'new_produced_return_at_sale':'0','deferred_fair_value_realized':'0','new_fair_value_change_at_sale':'0','rounding_bridge':fmt(total)}
  inventory={'opening':{'prior_produced_return':carry['prior_produced_return'],'current_produced_return_already_in_curve':'0','deferred_fair_value_realized':'0','rounding_bridge':carry['rounding_bridge']},'closing':{'prior_produced_return':fmt(Decimal(carry['prior_produced_return'])*remaining),'current_produced_return_already_in_curve':fmt(Decimal(50)*remaining),'deferred_fair_value_realized':'0','rounding_bridge':fmt(Decimal(carry['rounding_bridge'])*remaining-created)},'other_releases':[],'closing_fiscal_components':{k:fmt(Decimal(v)*remaining) for k,v in fiscal.items() if k!='deferred_fair_value'}}
  want={'position':p,'title':f['scope']['title'],'recognition_history':f['history'],'independent_oracle':independent,'third_period_sale_rounding_oracle':amounts,'components':{'sale1':parts},'recognition_inventory':inventory,'yield_amount':'50.00','expected':{'base':format(Decimal(700)+Decimal(monthly['vApur']),'.2f'),'auxiliary_base':monthly['vApur'],'opening_fiscal_components':fiscal,'opening_carrying_components':carry,'revenue':'0.00','deductions':'0.00'},'runtime_sale_qualification_implemented':False}
  target=S/(name+'-current.json');save(target,want);before_hash=sha(target);result=subprocess.run(['compact.exe','/C','/F','/I','/Q','/EXE:LZX',str(target)],capture_output=True);assert result.returncode==0 and sha(target)==before_hash
  cases.append({'case':name,'fixture':target.relative_to(ROOT).as_posix(),'fixture_sha256':before_hash,'auxiliary_base':monthly['vApur'],'rounding_parts':amounts,'runtime_sale_qualification_implemented':False})
save(E/'third-sale-preparation.json',{'cases':cases,'source_openings_verified':6,'current_official_period':'2026-09','series_rows':len(policy['series']['rows']),'full_three_month_chain_accepted':False,'scope28_completed':False,'production_ready':False})
print(json.dumps({'third_sale_fixtures':len(cases),'runtime_sale_qualification_implemented':False,'series_rows':len(policy['series']['rows'])}))
