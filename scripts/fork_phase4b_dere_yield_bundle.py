"""Freeze and independently qualify the integrated constant-cost art195 yield candidate."""
import copy,hashlib,json,subprocess
from datetime import datetime,timezone
from decimal import Decimal,localcontext,ROUND_HALF_EVEN
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.06-25';S=f'scenarios/dere-yield-{V}';E=ROOT/'storage/app/phase4b-yield-20261006-25';E.mkdir(exist_ok=True)
def read(p):return json.loads((F/p).read_text(encoding='utf-8'))
def digest(v):return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
def write(p,v):
 path=F/p
 if path.exists():assert read(p)==v,p;return
 path.parent.mkdir(parents=True,exist_ok=True);path.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
def save(p,v):
 if p.exists():assert json.loads(p.read_text(encoding='utf-8'))==v,p;return
 p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
baseline={}
for base in [F,ROOT/'storage/app']:
 for path in base.rglob('*'):
  if not path.is_file() or E in path.parents or '2026.10.06-25' in str(path) or path.suffix not in ['.json','.xml','.pdf','.zip','.xsd']:continue
  baseline[path.relative_to(ROOT).as_posix()]=hashlib.sha256(path.read_bytes()).hexdigest()
if not (E/'historical-before.json').exists():save(E/'historical-before.json',baseline)
b=read('bundles/consumption-phase4b-2026.10.06-24.json');old=copy.deepcopy(b);bp=f'bundles/consumption-phase4b-{V}.json'
available=read(bp)['knowledge_available_at'] if (F/bp).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
b.update(bundle_id=f'CONSUMPTION-PHASE4B-{V}',version=V,predecessor_bundle_id=old['bundle_id'],knowledge_available_at=available)
c=read(b['resource_paths']['rtc_code_catalog']);c['version']=V
calc=copy.deepcopy(c['dere_debt_constant_cost_calculation']);calc.update(published_at=available,rounding_contract='DERE_HALF_EVEN_8_2',rounding_source_hash='639643d2eee1282bd7a8f104c492f74342bd51293845011aae2fb0a64b742d04')
c['dere_debt_constant_cost_calculation']=calc
qual={'contract':'LC214-ART195-CONSTANT-COST-QUALIFIED-2026.10.06-1','status':'ACTIVE','published_at':available,'source_hash':'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde','calculation':calc}
c['financial_operation']['base']['public_offer_debt']=copy.deepcopy(qual)
c['dere_trial_balance_projection']['d2101_origin_allowed']=True
policy=copy.deepcopy(c['dere_monthly_closing_projection']['debt_principal_movement_projection']);policy.update(contract='DERE-D2101-CONSTANT-COST-YIELD-LOCAL-2026.10.06-1',published_at=available,qualification=qual,scope='CONSTANT_COST_YIELD_ONCE_IN_D2101_EXCLUDE_GROSS_FROM_D1101_BASE')
c['dere_monthly_closing_projection']['debt_yield_projection']=policy
cp=f'catalogs/rtc-codes-{V}.json';write(cp,c);b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V
ctrl=read(b['production_controls']['path']);ctrl['version']=V
for row in ctrl['controls']:
 if row['id'] in ['4B.4.BASE','4B.7.DOCUMENT']:row['gap']+=' Candidato25: rendimento art195 de custo constante integrado a base e D2101, emissor regular fora do regime financeiro, origem fiscal unica, gross contabile separado, arredondamento8/2 half-even e saldo reconciliado. Estoque/custo variavel e fechamento oficial pendentes.'
cp=f'production/phase4b-controls-{V}.json';write(cp,ctrl);b['production_controls']={'path':cp,'sha256':digest(ctrl)};write(bp,b);write(f'sources/official/phase4b-manifest-{V}.json',{'version':V,'retrieved_on':'2026-10-06','status':'SANDBOX_EVIDENCE_REVIEW_REQUIRED','artifacts':b['official_sources']})
for family in ['consumption-phase4b','consumption-integrated']:
 for path in sorted((F/f'scenarios/{family}-2026.10.06-24').glob('*.json')):
  v=json.loads(path.read_text(encoding='utf-8'));v.update(rule_bundle_id=b['bundle_id'],catalog_versions=b['catalog_versions'],knowledge_at=available);write(f'scenarios/{family}-{V}/{path.name}',v)
baseScenario=read('scenarios/dere-principal-movement-2026.10.06-23/financial-scenario.json');baseScenario.update(rule_bundle_id=b['bundle_id'],catalog_versions=b['catalog_versions'],knowledge_at=available)
baseScenario['facts']['rtc.credit_context']='OUTPUT';baseScenario['facts'].pop('rtc.financial_credit_proof');baseScenario['facts']['rtc.financial_scope_proof'][0].update(operation_category='SECURITIES',operation_authorization_evidence_id='FIXTURE.SECURITIES.AUTHORIZATION')
baseScenario['facts']['rtc.financial_ledger'][0]['observed_on']='2026-10-06'
baseMonth=read('scenarios/dere-principal-movement-2026.10.06-23/continuation.json');baseMonth.pop('debt_principal_movement');baseMonth['evidence_scope']='SYNTHETIC_QUALIFIED_ART195_ISSUER_POSITION_BOOK_AND_OFFER_OFFICIAL_SELIC_RATES_REAL'
profile=baseMonth['contributor'];profile['servFinanc']['tpAtividades']=['01A','03A'];p=profile['evidence']['activities'];p['value']=['01A','03A'];p.pop('source_hash');p['source_hash']=digest(p);profile['activities_source_hash']=p['source_hash'];profile.pop('source_hash');profile['source_hash']=digest(profile)
baseMonth['pgcc_identity']['contributor_source_hash']=profile['source_hash'];baseMonth['prerequisites']['contributor_source_hash']=profile['source_hash']
ids=[read('scenarios/dere-principal-movement-2026.10.06-23/multiple-titles.json')['debt_principal_movement']['titles'][i]['idTitulo'] for i in [0,1]]
def proof(value,name,start='2026-09-01',end='2026-09-30'):
 p={'qualified':True,'supplier_id':'11111111000191','evidence_id':name,'observed_on':'2026-10-06','valid_from':start,'valid_to':end,'scope':'SYNTHETIC_INDEPENDENT_POSITION_ISSUER_AND_OFFER_PROOF','value':value};p['source_hash']=digest(p);return p
def entry(name='yield1',account='debt000',var='150.00',pis='10.00',isin=ids[0],received='0.00',cost='10000.00',quantity='1000',unit='10.00'):
 pos={'supplier_id':'11111111000191','period':'2026-09','idTitulo':isin,'cCta':account,'held_entire_period':True,'constant_average_cost':True,'cost_basis_method':'WEIGHTED_AVERAGE_ACQUISITION_COST','vEntradas':'0.00','vSaidas':'0.00','quantity':quantity,'weighted_average_unit_cost':unit,'cost_basis':cost,'vVarMensal':var,'vPisCofins':pis}
 pos['position_evidence']=proof(copy.deepcopy(pos),'FIXTURE.POSITION.'+name)
 title={'idTitulo':isin,'cCta':account,'CNPJDevedor':'11222333000181','instrument_type':'CORPORATE_DEBT_PUBLIC_OFFER','public_offer_regulator':'CVM','currency':'BRL','issuer_regime':'REGULAR','issuer_in_financial_specific_regime':False,'creditor_in_financial_specific_regime':True,'holding_route':'DIRECT','vJurosRec':received}
 if account=='debtfund000':title.update(holding_route='EXCLUSIVE_FUND_95',exclusive_fund=True,descFundo='Fundo exclusivo fixture',public_offer_portfolio_percent='95.00')
 e={'supplier_id':'11111111000191','recognized_on':'2026-09-30','qualification_evidence_id':'FIXTURE.ART195.'+name,'id':name,'document_id':'public-offer-'+name,'kind':'PUBLIC_OFFER_DEBT_YIELD','amount':str(abs(Decimal(var))),'public_offer_debt':{'title':title,'position':pos,'result_account_code':'interest000'}};seal_entry(e);return e
def seal_entry(e):
 d=e['public_offer_debt'];d['eligibility_evidence']=proof({'supplier_id':e['supplier_id'],'period_end':'2026-09-30','title':d['title'],'financial_entry_id':e['id'],'document_id':e['document_id'],'amount':e['amount'],'result_account_code':d['result_account_code'],'position_input_hash':digest(d['position'])},'FIXTURE.ELIGIBILITY.'+e['id'])
def oracle(pos):
 with localcontext() as context:
  context.prec=100;factor=Decimal(1)
  for row in calc['series']['rows']:factor=(factor*(1+Decimal(row['daily_percent'])/100)).quantize(Decimal('.00000001'),rounding=ROUND_HALF_EVEN)
  selic=(Decimal(pos['cost_basis'])*(factor-1)).quantize(Decimal('.00000001'),rounding=ROUND_HALF_EVEN).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
  return selic,max(Decimal(0),min(selic,Decimal(pos['vVarMensal']))-Decimal(pos['vPisCofins']))
positive={'capped-yield':[entry()], 'below-cap':[entry(var='70.00',pis='5.00')], 'negative-variation':[entry(var='-50.00',pis='0.00')], 'zero-variation':[entry(var='0.00',pis='0.00')], 'pis-exceeds-yield':[entry(var='70.00',pis='80.00')], 'exclusive-fund':[entry(account='debtfund000')], 'mixed-positive-negative':[entry(),entry(name='yield2',account='debtfund000',var='-50.00',pis='0.00')], 'multiple-titles':[entry(),entry(name='yield2',var='70.00',pis='5.00',isin=ids[1])], 'same-isin-distinct-accounts':[entry(),entry(name='yield2',account='debtfund000',var='70.00',pis='5.00')], 'coupon-receipt':[entry(received='50.00')], 'half-even-cost-even':[entry(cost='10000.00',quantity='1',unit='10000.005')], 'half-even-cost-odd':[entry(cost='10000.02',quantity='1',unit='10000.015')]}
def run(args,code=0):
 p=subprocess.run(['docker','compose','exec','-T','app','php','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8');assert p.returncode==code,p.stdout+p.stderr;return json.loads(p.stdout)
def build(name,extra):
 scenario=copy.deepcopy(baseScenario);scenario['scenario_id']='art195-'+name;scenario['facts']['rtc.financial_ledger'][0]['entries']+=extra;write(f'{S}/{name}-financial.json',scenario)
 snap=E/f'{name}-snapshot.json';args=['fiscal:rtc:run',f'resources/fiscal/{S}/{name}-financial.json',f'--bundle-manifest={bp}']
 if not snap.exists():args.append('--snapshot='+snap.relative_to(ROOT).as_posix())
 result=run(args);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'],result
 stages={x['stage']:x for x in result['trace']};op=stages['FINANCIAL_OPERATION_AND_BASE'];expected=Decimal('700.00')+sum((oracle(e['public_offer_debt']['position'])[1] for e in extra),Decimal('0.00'));assert Decimal(op['base']['net_base'])==expected
 assert run(['fiscal:rtc:replay',snap.relative_to(ROOT).as_posix()])['replay_matches']
 v=copy.deepcopy(baseMonth);accounts={a['cCta']:a for a in v['book']['accounts']};income=accounts['interest000'];plus=sum((Decimal(e['amount']) for e in extra if Decimal(e['public_offer_debt']['position']['vVarMensal'])>=0),Decimal('0.00'));minus=sum((Decimal(e['amount']) for e in extra if Decimal(e['public_offer_debt']['position']['vVarMensal'])<0),Decimal('0.00'))
 income.update(vMovCred=str(Decimal('1000.00')+plus),vMovDebt=str(minus),vAjusteCred=str(plus),vAjusteDebt='0.00',vSaldoFinal=str(Decimal('1000.00')+plus-minus));income['financial_entry_ids'] += [e['id'] for e in extra]
 for code in ['debt000','debtfund000']:
  a=accounts[code];rows=[e['public_offer_debt'] for e in extra if e['public_offer_debt']['title']['cCta']==code]
  opening=sum((Decimal(r['position']['cost_basis']) for r in rows),Decimal('0.00'));debit=sum((max(Decimal(0),Decimal(r['position']['vVarMensal'])) for r in rows),Decimal('0.00'));credit=sum((max(Decimal(0),-Decimal(r['position']['vVarMensal']))+Decimal(r['title']['vJurosRec']) for r in rows),Decimal('0.00'))
  a.update(vSaldoInic=str(opening),vMovDebt=str(debit),vMovCred=str(credit),vSaldoFinal=str(opening+debit-credit),vAjusteDebt=str(debit),vAjusteCred='0.00');a['previous_balance']['amount']=str(opening)
 priors={e['id']:proof({'supplier_id':e['supplier_id'],'period':'2026-08','idTitulo':e['public_offer_debt']['title']['idTitulo'],'cCta':e['public_offer_debt']['title']['cCta'],'vSaldoContFin':e['public_offer_debt']['position']['cost_basis']},'FIXTURE.PRIOR.'+e['id'],'2026-08-01','2026-08-31') for e in extra}
 v['debt_yield']={'supplier_id':v['book']['supplier_id'],'period_end':'2026-09-30','observed_on':'2026-10-06','financial_entry_ids':sorted(priors),'previous_title_balances':priors}
 seal_month(v,op);v['financial_scenario']=name+'-financial.json';v['corpus_expectation']={'status':'PROJECTED','base':str(expected),'auxiliary_base':str(expected-Decimal('700.00'))};write(f'{S}/{name}.json',v);return v,op

def seal_month(v,op):
 d=v['debt_yield'];d['evidence']=proof({'supplier_id':v['book']['supplier_id'],'period_end':v['book']['period_end'],'detail':{k:x for k,x in d.items() if k!='evidence'},'operation_hash':digest(op),'book_input_hash':digest(v['book']),'pgcc_source_hash':v['book']['pgcc_source_hash']},'FIXTURE.D2101.YIELD.COMPLETE')
months={}
for n,extra in positive.items():months[n]=build(n,extra)
negative={}
def badmonth(name,v,reason):v['corpus_expectation']={'status':'ACTION_REQUIRED','reason':reason};negative[name]=v
v,op=months['capped-yield'];v=copy.deepcopy(v);v.pop('debt_yield');badmonth('missing-yield-detail',v,'RTC_DERE_MONTHLY_D2101_YIELD_DETAIL_OR_POLICY_REQUIRED')
for n,change,reason in [('missing-prior',None,'YIELD_PREVIOUS_TITLE_BALANCE_UNPROVEN'),('wrong-prior-account','debtfund000','YIELD_PREVIOUS_TITLE_BALANCE_UNPROVEN'),('missing-entry',[],'YIELD_ENTRY_COVERAGE_CONFLICT'),('duplicate-entry',['yield1','yield1'],'YIELD_ENTRY_COVERAGE_CONFLICT'),('unqualified-scope',False,'YIELD_COMPLETE_SCOPE_EVIDENCE_UNPROVEN'),('tampered-scope','e'*64,'YIELD_COMPLETE_SCOPE_EVIDENCE_UNPROVEN')]:
 v,op=months['capped-yield'];v=copy.deepcopy(v)
 if n=='missing-prior':v['debt_yield']['previous_title_balances'].pop('yield1')
 elif n=='wrong-prior-account':p=v['debt_yield']['previous_title_balances']['yield1'];p['value']['cCta']=change;p.pop('source_hash');p['source_hash']=digest(p)
 elif 'entry' in n:v['debt_yield']['financial_entry_ids']=change
 elif n=='unqualified-scope':v['debt_yield']['evidence']['qualified']=False
 else:v['debt_yield']['evidence']['source_hash']=change
 badmonth(n,v,'RTC_DERE_D2101_'+reason)
v,op=months['capped-yield'];v=copy.deepcopy(v);v['debt_no_movement']={};badmonth('contradictory-declaration',v,'RTC_DERE_D2101_YIELD_SCOPE_OR_ACTIVITIES_UNPROVEN')
v,op=months['capped-yield'];v=copy.deepcopy(v);v['book']['accounts'][0]['financial_entry_ids'].remove('yield1');badmonth('unlinked-yield',v,'RTC_DERE_BALANCE_FINANCIAL_LEDGER_NOT_COMPLETELY_LINKED')
v,op=months['capped-yield'];v=copy.deepcopy(v);a=v['book']['accounts'][0];a['vAjusteCred']='51.83';a['vApur']='1098.17';badmonth('double-tax-in-balancete',v,'RTC_DERE_BALANCE_FINANCIAL_LINK_AMOUNT_CONFLICT')
v,op=months['capped-yield'];v=copy.deepcopy(v);a=next(a for a in v['book']['accounts'] if a['cCta']=='debt000');a.update(vMovDebt='151.00',vAjusteDebt='151.00',vSaldoFinal='10151.00');badmonth('asset-saldo-mismatch',v,'RTC_DERE_D2101_YIELD_ASSET_BALANCE_RECONCILIATION_CONFLICT')
v,op=months['capped-yield'];v=copy.deepcopy(v);a=v['book']['accounts'][0];a.update(vMovCred='1151.00',vAjusteCred='151.00',vSaldoFinal='1151.00');badmonth('result-gross-mismatch',v,'RTC_DERE_D2101_YIELD_RESULT_GROSS_OR_DUPLICATE_BASE_CONFLICT')
for n,v in negative.items():write(f'{S}/{n}.json',v)
# Engine-level failures are recorded independently of monthly projections.
engine={}
def badengine(n,e,reason):
 v=copy.deepcopy(baseScenario);v['scenario_id']='art195-negative-'+n;v['facts']['rtc.financial_ledger'][0]['entries'].append(e);write(f'{S}/engine-{n}.json',{'scenario':v,'expectation':reason});engine[n]=reason
for n,f,value in [('issuer-simples','issuer_regime','SIMPLES'),('issuer-financial','issuer_in_financial_specific_regime',True),('private-offer','public_offer_regulator','PRIVATE'),('creditor-outside-specific','creditor_in_financial_specific_regime',False),('fund-below-95','public_offer_portfolio_percent','94.99')]:
 e=entry(account='debtfund000') if n.startswith('fund') else entry();e['public_offer_debt']['title'][f]=value;seal_entry(e);badengine(n,e,'RTC_FINANCIAL_ART195_'+('EXCLUSIVE_FUND_95_PERCENT_UNPROVEN' if n.startswith('fund') else 'ART195_PARTIES_OR_OFFER_UNPROVEN'))
e=entry();e['public_offer_debt']['eligibility_evidence']['qualified']=False;badengine('unqualified-issuer-proof',e,'RTC_FINANCIAL_ART195_ELIGIBILITY_EVIDENCE_UNPROVEN')
e=entry();e['public_offer_debt']['position']['vEntradas']='100.00';p=e['public_offer_debt']['position'];p.pop('position_evidence');p['position_evidence']=proof(copy.deepcopy(p),'FIXTURE.POSITION.CHANGED');seal_entry(e);badengine('changing-position',e,'RTC_DERE_D2101_CHANGING_POSITION_OR_COST_PENDING')
e=entry();e['fiscal_origin']='D2101';badengine('injected-derived-origin',e,'RTC_FINANCIAL_BASE_RESERVED_DERIVED_ENTRY_METADATA')
e=entry();e['amount']='149.00';seal_entry(e);badengine('wrong-gross-amount',e,'RTC_FINANCIAL_ART195_GROSS_YIELD_AMOUNT_CONFLICT')
def badscenario(n,extras,reason):
 v=copy.deepcopy(baseScenario);v['scenario_id']='art195-negative-'+n;v['facts']['rtc.financial_ledger'][0]['entries']+=extras;write(f'{S}/engine-{n}.json',{'scenario':v,'expectation':reason});engine[n]=reason
badscenario('duplicate-document',[entry(),{'supplier_id':'11111111000191','recognized_on':'2026-09-30','qualification_evidence_id':'FIXTURE.DUPLICATE','id':'generic-duplicate','document_id':'public-offer-yield1','kind':'FINANCIAL_REVENUE','amount':'150.00'}],'RTC_FINANCIAL_BASE_PUBLIC_OFFER_DUPLICATE_DOCUMENT_OR_REVENUE')
badscenario('duplicate-title-account',[entry(),entry(name='yield2')],'RTC_FINANCIAL_BASE_PUBLIC_OFFER_DUPLICATE_TITLE_ACCOUNT')
e=entry(name='yield2',account='debtfund000');e['public_offer_debt']['title']['CNPJDevedor']='12345678000195';seal_entry(e);badscenario('isin-issuer-conflict',[entry(),e],'RTC_FINANCIAL_BASE_PUBLIC_OFFER_ISIN_ISSUER_CONFLICT')
e=entry();e['public_offer_debt']['position'].pop('position_evidence');seal_entry(e);badengine('missing-position-proof',e,'RTC_DERE_D2101_CONSTANT_POSITION_EVIDENCE_UNPROVEN')
e=entry(received='20000.00');badengine('negative-closing-stock',e,'RTC_FINANCIAL_ART195_TITLE_CLOSING_BALANCE_NEGATIVE')
e=entry();e['public_offer_debt']['title']['CNPJDevedor']=e['supplier_id'];seal_entry(e);badengine('issuer-is-creditor',e,'RTC_FINANCIAL_ART195_ART195_PARTIES_OR_OFFER_UNPROVEN')
print(f'Candidate25:{len(positive)} positive monthly/{len(negative)} negative monthly/{len(engine)} negative engine scenarios;47 bound official sources.')
