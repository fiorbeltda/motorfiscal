"""Freeze variable-position art195 integration with daily memory and independent book arithmetic."""
import copy,hashlib,json,subprocess,sys
from datetime import datetime,timezone
from decimal import Decimal,ROUND_HALF_EVEN
from pathlib import Path
from dere_daily_memory_oracle import oracle,proof,seal
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.06-27';S=f'scenarios/dere-variable-yield-{V}';E=ROOT/'storage/app/phase4b-variable-yield-20261006-27';E.mkdir(exist_ok=True)
def read(p):return json.loads((F/p).read_text(encoding='utf-8'))
def digest(v):return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
def write(p,v):
 path=F/p
 if path.exists():
  if read(p)==v:return
  backup=E/'draft-initial-priors'/path.name
  assert '--repair-initial-prior-array' in sys.argv and path.parent==F/S and backup.read_bytes()==path.read_bytes(),p
 path.parent.mkdir(parents=True,exist_ok=True);path.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
def save(p,v):
 if p.exists():assert json.loads(p.read_text(encoding='utf-8'))==v,p;return
 p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
baseline={}
for base in [F,ROOT/'storage/app']:
 for p in base.rglob('*'):
  if p.is_file() and E not in p.parents and V not in str(p) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']:baseline[p.relative_to(ROOT).as_posix()]=hashlib.sha256(p.read_bytes()).hexdigest()
if not (E/'historical-before.json').exists():save(E/'historical-before.json',baseline)
b=read('bundles/consumption-phase4b-2026.10.06-26.json');old=copy.deepcopy(b);bp=f'bundles/consumption-phase4b-{V}.json'
available=read(bp)['knowledge_available_at'] if (F/bp).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
b.update(bundle_id=f'CONSUMPTION-PHASE4B-{V}',version=V,predecessor_bundle_id=old['bundle_id'],knowledge_available_at=available)
c=read(b['resource_paths']['rtc_code_catalog']);c['version']=V;calc=c['dere_debt_daily_memory_calculation']
qual=c['financial_operation']['base']['public_offer_debt'];qual.update(contract='LC214-ART195-DAILY-MEMORY-QUALIFIED-2026.10.06-1',published_at=available,daily_memory_calculation=copy.deepcopy(calc))
policy=c['dere_monthly_closing_projection']['debt_yield_projection'];policy.update(contract='DERE-D2101-DAILY-MEMORY-YIELD-LOCAL-2026.10.06-1',published_at=available,qualification=copy.deepcopy(qual),scope='DAILY_MEMORY_QUALIFIED_VARIABLE_POSITION_YIELD_ONCE_IN_D2101')
cp=f'catalogs/rtc-codes-{V}.json';write(cp,c);b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V
ctrl=read(b['production_controls']['path']);ctrl['version']=V
for r in ctrl['controls']:
 if r['id'] in ['4B.4.BASE','4B.7.DOCUMENT']:r['gap']+=' Candidato27: posicao variavel com memoria diaria local reproduzida integrada a base e D2101, compras/saidas/amortizacoes/cupons reconciliados ao D1101 e origem unica. Convencoes sem homologacao e ganho de venda nao enquadrado permanecem bloqueados.'
cp=f'production/phase4b-controls-{V}.json';write(cp,ctrl);b['production_controls']={'path':cp,'sha256':digest(ctrl)};write(bp,b)
write(f'sources/official/phase4b-manifest-{V}.json',{'version':V,'retrieved_on':'2026-10-06','status':'SANDBOX_EVIDENCE_REVIEW_REQUIRED','artifacts':b['official_sources']})
for family in ['consumption-phase4b','consumption-integrated']:
 for p in sorted((F/f'scenarios/{family}-2026.10.06-26').glob('*.json')):
  v=json.loads(p.read_text(encoding='utf-8'));v.update(rule_bundle_id=b['bundle_id'],catalog_versions=b['catalog_versions'],knowledge_at=available);write(f'scenarios/{family}-{V}/{p.name}',v)
baseScenario=read('scenarios/dere-yield-2026.10.06-25/capped-yield-financial.json');baseScenario.update(rule_bundle_id=b['bundle_id'],catalog_versions=b['catalog_versions'],knowledge_at=available)
prototype=copy.deepcopy(baseScenario['facts']['rtc.financial_ledger'][0]['entries'].pop())
baseMonth=read('scenarios/dere-yield-2026.10.06-25/capped-yield.json');baseMonth.pop('debt_yield');baseMonth['evidence_scope']='SYNTHETIC_QUALIFIED_VARIABLE_MEMORY_OFFER_ISSUER_AND_BOOK_OFFICIAL_DAILY_RATES_REAL'
D=Decimal
def cents(v):return format(v.quantize(D('.01'),rounding=ROUND_HALF_EVEN),'f')
def signedTotals(pos):
 rows=pos['contract_curve'];return tuple(D(cents(sum((max(D(0),sign*D(r['appropriation'])) for r in rows),D(0)))) for sign in [D(1),D(-1)])
def position(name,account='debt000',isin=None,zero_sale_gain=True):
 p=copy.deepcopy(read(f'scenarios/dere-daily-memory-2026.10.06-26/{name}.json')['position']);p.update(cCta=account,idTitulo=isin or p['idTitulo'])
 p['appropriations']={r['date']:r['appropriation'] for r in p['contract_curve']}
 if zero_sale_gain:
  for e in p['operations']:
   if e['kind']=='SELL':e['sale_proceeds']=e['book_amount']
 p['contract_curve'],p['declared_monthly'],_=oracle(p,calc['series']);p.pop('appropriations')
 if not p['first_acquisition']:p['previous_position_evidence']=proof({'supplier_id':p['supplier_id'],'idTitulo':p['idTitulo'],'cCta':p['cCta'],'period':'2026-08','closing':p['opening']},'FIXTURE.VARIABLE.PREVIOUS','2026-08-01','2026-08-31')
 seal(p);return p
def entry(pos,name='yield1'):
 e=copy.deepcopy(prototype);e.update(id=name,document_id='variable-offer-'+name,amount=cents(abs(D(pos['declared_monthly']['vVarMensal']))));detail=e['public_offer_debt'];detail.update(position_mode='DAILY_MEMORY',position=pos)
 t=detail['title'];t.update(cCta=pos['cCta'],idTitulo=pos['idTitulo'],vJurosRec=pos['declared_monthly']['vJurosRec'])
 if pos['cCta']=='debtfund000':t.update(holding_route='EXCLUSIVE_FUND_95',exclusive_fund=True,descFundo='Fundo exclusivo fixture',public_offer_portfolio_percent='95.00')
 sealEntry(e);return e
def sealEntry(e):
 d=e['public_offer_debt'];d['eligibility_evidence']=proof({'supplier_id':e['supplier_id'],'period_end':'2026-09-30','title':d['title'],'financial_entry_id':e['id'],'document_id':e['document_id'],'amount':e['amount'],'result_account_code':d['result_account_code'],'position_input_hash':digest(d['position']),'position_mode':'DAILY_MEMORY'},'FIXTURE.VARIABLE.ELIGIBILITY')
positive={}
for name in ['constant','first-purchase-before-rate','first-purchase-after-rate','weighted-additional-purchase','partial-sale-gain','complete-sale','partial-amortization','complete-amortization','coupon','weekend-purchase','holiday-purchase','same-day-ordered-buy-sale','different-book-and-acquisition-cost','negative-appropriation','zero-variation','fractional-quantity-pu']:
 positive[name]=[entry(position(name))]
positive['exclusive-fund']=[entry(position('weighted-additional-purchase',account='debtfund000'))]
positive['mixed-titles']=[entry(position('weighted-additional-purchase')),entry(position('negative-appropriation',account='debtfund000'),name='yield2')]
isin2=read('scenarios/dere-yield-2026.10.06-25/multiple-titles-financial.json')['facts']['rtc.financial_ledger'][0]['entries'][-1]['public_offer_debt']['title']['idTitulo']
positive['same-account-multiple-titles']=[entry(position('weighted-additional-purchase')),entry(position('coupon',isin=isin2),name='yield2')]
p=position('constant');p['appropriations']={'2026-09-15':'150.00','2026-09-30':'-50.00'};p['contract_curve'],p['declared_monthly'],_=oracle(p,calc['series']);p.pop('appropriations');seal(p);positive['intramonth-positive-and-negative']=[entry(p)]
def run(args):
 p=subprocess.run(['docker','compose','exec','-T','app','php','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8');assert p.returncode==0,p.stdout+p.stderr;return json.loads(p.stdout)
def sealMonth(v,op):
 d=v['debt_yield'];d['evidence']=proof({'supplier_id':v['book']['supplier_id'],'period_end':v['book']['period_end'],'detail':{k:x for k,x in d.items() if k!='evidence'},'operation_hash':digest(op),'book_input_hash':digest(v['book']),'pgcc_source_hash':v['book']['pgcc_source_hash']},'FIXTURE.VARIABLE.MONTH')
months={}
for name,extra in positive.items():
 scenario=copy.deepcopy(baseScenario);scenario['scenario_id']='variable-art195-'+name;scenario['facts']['rtc.financial_ledger'][0]['entries']+=extra;write(f'{S}/{name}-financial.json',scenario)
 snap=E/f'{name}-snapshot.json';args=['fiscal:rtc:run',f'resources/fiscal/{S}/{name}-financial.json',f'--bundle-manifest={bp}']
 if not snap.exists():args+=['--snapshot='+snap.relative_to(ROOT).as_posix()]
 result=run(args);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'],(name,result)
 op={s['stage']:s for s in result['trace']}['FINANCIAL_OPERATION_AND_BASE'];expected=D('700.00')+sum((D(e['public_offer_debt']['position']['declared_monthly']['vApur']) for e in extra),D(0));assert D(op['base']['net_base'])==expected
 assert run(['fiscal:rtc:replay',snap.relative_to(ROOT).as_posix()])['replay_matches']
 month=copy.deepcopy(baseMonth);accounts={a['cCta']:a for a in month['book']['accounts']};income=accounts['interest000'];gross=[signedTotals(e['public_offer_debt']['position']) for e in extra];plus=sum((r[0] for r in gross),D(0));minus=sum((r[1] for r in gross),D(0))
 income.update(vMovCred=cents(D('1000')+plus),vMovDebt=cents(minus),vAjusteCred=cents(plus),vAjusteDebt='0.00',vSaldoFinal=cents(D('1000')+plus-minus));income['financial_entry_ids']=[i for i in income['financial_entry_ids'] if i!='yield1']+[e['id'] for e in extra]
 for code in ['debt000','debtfund000']:
  a=accounts[code];positions=[e['public_offer_debt']['position'] for e in extra if e['public_offer_debt']['title']['cCta']==code]
  opening=sum((D(p['declared_monthly']['vSaldoContIni']) for p in positions),D(0));debit=sum((D(p['declared_monthly']['vEntradas'])+signedTotals(p)[0] for p in positions),D(0));credit=sum((D(p['declared_monthly']['vSaidas'])+D(p['declared_monthly']['vJurosRec'])+signedTotals(p)[1] for p in positions),D(0))
  a.update(vSaldoInic=cents(opening),vMovDebt=cents(debit),vMovCred=cents(credit),vSaldoFinal=cents(opening+debit-credit),vAjusteDebt=cents(debit),vAjusteCred='0.00');a['previous_balance']['amount']=cents(opening)
 priors={e['id']:proof({'supplier_id':e['supplier_id'],'period':'2026-08','idTitulo':e['public_offer_debt']['title']['idTitulo'],'cCta':e['public_offer_debt']['title']['cCta'],'vSaldoContFin':e['public_offer_debt']['position']['declared_monthly']['vSaldoContIni']},'FIXTURE.VARIABLE.TITLE.PRIOR','2026-08-01','2026-08-31') for e in extra if not e['public_offer_debt']['position']['first_acquisition']}
 month['debt_yield']={'supplier_id':month['book']['supplier_id'],'period_end':'2026-09-30','observed_on':'2026-10-06','financial_entry_ids':sorted(e['id'] for e in extra),'previous_title_balances':priors if priors else []};sealMonth(month,op)
 month.update(financial_scenario=name+'-financial.json',corpus_expectation={'status':'PROJECTED','base':cents(expected),'auxiliary_base':cents(expected-D('700'))});write(f'{S}/{name}.json',month);months[name]=(month,op)
negative={}
def badMonth(name,origin,modify,reason):
 v=copy.deepcopy(months[origin][0]);modify(v);v['corpus_expectation']={'status':'ACTION_REQUIRED','reason':reason};write(f'{S}/{name}.json',v);negative[name]=reason
badMonth('missing-prior','weighted-additional-purchase',lambda v:v['debt_yield']['previous_title_balances'].pop('yield1'),'RTC_DERE_D2101_YIELD_PREVIOUS_TITLE_BALANCE_UNPROVEN')
badMonth('unexpected-prior-first-acquisition','first-purchase-before-rate',lambda v:v['debt_yield'].update(previous_title_balances={'yield1':{}}), 'RTC_DERE_D2101_YIELD_PREVIOUS_TITLE_BALANCE_UNPROVEN')
badMonth('book-uses-acquisition-cost-for-opening','different-book-and-acquisition-cost',lambda v:(v['book']['accounts'][4].update(vSaldoInic='10000.00',vSaldoFinal='10150.00'),v['book']['accounts'][4]['previous_balance'].update(amount='10000.00')),'RTC_DERE_D2101_YIELD_ASSET_BALANCE_RECONCILIATION_CONFLICT')
badMonth('incomplete-scope','weighted-additional-purchase',lambda v:v['debt_yield']['evidence'].update(qualified=False),'RTC_DERE_D2101_YIELD_COMPLETE_SCOPE_EVIDENCE_UNPROVEN')
badMonth('missing-entry','weighted-additional-purchase',lambda v:v['debt_yield'].update(financial_entry_ids=[]),'RTC_DERE_D2101_YIELD_ENTRY_COVERAGE_CONFLICT')
badMonth('missing-yield-detail','weighted-additional-purchase',lambda v:v.pop('debt_yield'),'RTC_DERE_MONTHLY_D2101_YIELD_DETAIL_OR_POLICY_REQUIRED')
badMonth('result-gross-netted','intramonth-positive-and-negative',lambda v:v['book']['accounts'][0].update(vMovCred='1100.00',vMovDebt='0.00',vAjusteCred='100.00'),'RTC_DERE_D2101_YIELD_RESULT_GROSS_OR_DUPLICATE_BASE_CONFLICT')
engine={}
def badEngine(name,e,reason):
 scenario=copy.deepcopy(baseScenario);scenario['scenario_id']='variable-negative-'+name;scenario['facts']['rtc.financial_ledger'][0]['entries'].append(e);write(f'{S}/engine-{name}.json',{'scenario':scenario,'expectation':reason});engine[name]=reason
for name in ['partial-sale-gain','complete-sale']:
 badEngine(name,entry(position(name,zero_sale_gain=False)),'RTC_FINANCIAL_ART195_DAILY_SALE_GAIN_TAX_TREATMENT_PENDING')
p=position('partial-sale-gain',zero_sale_gain=False);p['operations'].append({'id':'sale2','sequence':1,'date':'2026-09-21','kind':'SELL','quantity':'400','book_amount':'4000.00','sale_proceeds':'3800.00'})
p['appropriations']={r['date']:r['appropriation'] for r in p['contract_curve']};p['contract_curve'],p['declared_monthly'],_=oracle(p,calc['series']);p.pop('appropriations');seal(p)
badEngine('offsetting-sale-gains',entry(p),'RTC_FINANCIAL_ART195_DAILY_SALE_GAIN_TAX_TREATMENT_PENDING')
p=position('constant');p['appropriations']={'2026-09-15':'0.006','2026-09-30':'-0.004'};p['contract_curve'],p['declared_monthly'],_=oracle(p,calc['series']);p.pop('appropriations');seal(p)
badEngine('unallocated-gross-rounding',entry(p),'RTC_FINANCIAL_ART195_DAILY_GROSS_ROUNDING_RECONCILIATION_PENDING')
p=position('different-book-and-acquisition-cost');p['operations']=[{'id':'sale1','sequence':1,'date':'2026-09-14','kind':'SELL','quantity':'400','book_amount':'4040.00','sale_proceeds':'4040.00'}]
p['appropriations']={r['date']:r['appropriation'] for r in p['contract_curve']};p['contract_curve'],p['declared_monthly'],_=oracle(p,calc['series']);p.pop('appropriations');seal(p)
badEngine('zero-accounting-gain-with-acquisition-difference',entry(p),'RTC_FINANCIAL_ART195_DAILY_SALE_GAIN_TAX_TREATMENT_PENDING')
for name,origin,modify,reason in [
 ('coupon-conflict','coupon',lambda e:e['public_offer_debt']['title'].update(vJurosRec='49.00'),'RTC_FINANCIAL_ART195_DAILY_COUPON_AMOUNT_CONFLICT'),
 ('missing-memory','weighted-additional-purchase',lambda e:e['public_offer_debt']['position'].pop('memory_evidence'),'RTC_DERE_D2101_DAILY_COMPLETE_MEMORY_EVIDENCE_UNPROVEN'),
 ('curve-tampered','weighted-additional-purchase',lambda e:e['public_offer_debt']['position']['contract_curve'][14].update(claimed_quantity='999'),'RTC_DERE_D2101_DAILY_COMPLETE_MEMORY_EVIDENCE_UNPROVEN'),
 ('unqualified-offer','weighted-additional-purchase',lambda e:e['public_offer_debt']['title'].update(public_offer_regulator='PRIVATE'),'RTC_FINANCIAL_ART195_ART195_PARTIES_OR_OFFER_UNPROVEN'),
 ('gross-amount-conflict','weighted-additional-purchase',lambda e:e.update(amount='149.00'),'RTC_FINANCIAL_ART195_GROSS_YIELD_AMOUNT_CONFLICT'),
 ('invalid-position-mode','weighted-additional-purchase',lambda e:e['public_offer_debt'].update(position_mode='UNSPECIFIED'),'RTC_FINANCIAL_ART195_POSITION_MODE_CONFLICT')]:
 e=entry(position(origin));modify(e);sealEntry(e);badEngine(name,e,reason)
print(f'Candidate27 frozen:{len(positive)} positive/{len(negative)} negative monthly/{len(engine)} negative engine cases;47 sources.')
