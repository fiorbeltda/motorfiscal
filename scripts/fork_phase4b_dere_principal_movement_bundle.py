"""Freeze candidate23: independently qualified principal-only D2101 movements."""
import copy,hashlib,json
from datetime import datetime,timezone
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.06-23';S=f'scenarios/dere-principal-movement-{V}'
def read(p):return json.loads((F/p).read_text(encoding='utf-8'))
def digest(v):return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
def write(p,v):
 path=F/p
 if path.exists():assert read(p)==v,p;return
 path.parent.mkdir(parents=True,exist_ok=True);path.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
b=read('bundles/consumption-phase4b-2026.10.06-22.json');old=copy.deepcopy(b);bp=f'bundles/consumption-phase4b-{V}.json'
available=read(bp)['knowledge_available_at'] if (F/bp).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
b.update(bundle_id=f'CONSUMPTION-PHASE4B-{V}',version=V,predecessor_bundle_id=old['bundle_id'],knowledge_available_at=available)
c=read(b['resource_paths']['rtc_code_catalog']);c['version']=V
p=copy.deepcopy(c['dere_monthly_closing_projection']['debt_no_movement_projection']);p.update(contract='DERE-D2101-PRINCIPAL-MOVEMENT-LOCAL-2026.10.06-1',source_hash='b7756c7b0cec5cff46d77e1b1ea5d657a4e2476fa6033f8192561c17dd70ab2d',scope='PRINCIPAL_ONLY_NO_INCOME_OR_DISPOSAL_GAIN_QUALIFIED_EXTERNAL_DAILY_SELIC_MEMORY')
c['dere_monthly_closing_projection']['debt_principal_movement_projection']=p
cp=f'catalogs/rtc-codes-{V}.json';write(cp,c);b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V
control=read(b['production_controls']['path']);control['version']=V
for row in control['controls']:
 if row['id']=='4B.7.DOCUMENT':row['gap']+=' Candidato23: D2101 principal movimentado, saldo por ISIN/conta e reconciliacao D1101; provas externas Selic previamente qualificadas. Juros/ganhos/variacoes e apuracao diaria propria pendentes.'
cp=f'production/phase4b-controls-{V}.json';write(cp,control);b['production_controls']={'path':cp,'sha256':digest(control)};write(bp,b)
write(f'sources/official/phase4b-manifest-{V}.json',{'version':V,'retrieved_on':'2026-10-06','status':'SANDBOX_EVIDENCE_REVIEW_REQUIRED','artifacts':b['official_sources']})
for family in ['consumption-phase4b','consumption-integrated']:
 for path in sorted((F/f'scenarios/{family}-2026.10.06-22').glob('*.json')):
  v=json.loads(path.read_text(encoding='utf-8'));v.update(rule_bundle_id=b['bundle_id'],catalog_versions=b['catalog_versions'],knowledge_at=available);write(f'scenarios/{family}-{V}/{path.name}',v)
v=read('scenarios/dere-schema13-2026.10.06-22/financial-scenario.json');v.update(rule_bundle_id=b['bundle_id'],catalog_versions=b['catalog_versions'],knowledge_at=available);write(f'{S}/financial-scenario.json',v)
base=read('scenarios/dere-schema13-2026.10.06-22/debt-qualified-both-codes.json');base.pop('debt_no_movement');base['evidence_scope']='SYNTHETIC_PRINCIPAL_MOVEMENTS_NO_REAL_OFFER_OR_DAILY_MEMORY_VERIFICATION'
def isin(base):
 for n in range(10):
  v=base+str(n);digits=''.join(str(ord(ch)-55) if ch.isalpha() else ch for ch in v);nums=[int(ch)*(2 if i%2 else 1) for i,ch in enumerate(digits[::-1])]
  if sum(n//10+n%10 for n in nums)%10==0:return v
 raise AssertionError(base)
ID=isin('BRFIXTDEB01');ID2=isin('BRFIXTDEB02')
def proof(value,identifier,start='2026-09-01',end='2026-09-30'):
 p={'qualified':True,'evidence_id':identifier,'supplier_id':base['book']['supplier_id'],'observed_on':'2026-10-06','valid_from':start,'valid_to':end,'scope':'SYNTHETIC_PREQUALIFIED_EXTERNAL_PROOF_NOT_REMOTE_VERIFICATION','value':value};p['source_hash']=digest(p);return p
def title(account='debt000',opening='0.00',entries='10000.00',exits='0.00',identifier=ID):
 from decimal import Decimal
 t={'idTitulo':identifier,'cCta':account,'CNPJDevedor':'11222333000181','instrument_type':'CORPORATE_DEBT_PUBLIC_OFFER','public_offer_regulator':'CVM','first_acquisition_period':opening=='0.00','vSaldoContIni':opening,'vEntradas':entries,'vSaidas':exits,'vJurosRec':'0.00','vVarMensal':'0.00','vSaldoContFin':str(Decimal(opening)+Decimal(entries)-Decimal(exits)),'vSelic':'100.00','vPisCofins':'0.00','vApur':'0.00'}
 if account=='debtfund000':t.update(descFundo='Fundo exclusivo fixture',exclusive_fund=True,public_offer_portfolio_percent='95.00')
 qualify_title(t);return t
def qualify_title(t):
 val={k:v for k,v in t.items() if k not in ['eligibility_evidence','selic_memory_evidence','previous_title_balance']}
 t['eligibility_evidence']=proof({'supplier_id':base['book']['supplier_id'],'period_end':'2026-09-30','title':val},'FIXTURE.OFFER.'+t['cCta']+t['idTitulo'])
 t['selic_memory_evidence']=proof({'supplier_id':base['book']['supplier_id'],'period_end':'2026-09-30','idTitulo':t['idTitulo'],'cCta':t['cCta'],'vSelic':t['vSelic'],'method':'DAILY_SELIC_WEIGHTED_AVERAGE_COST','title_input_hash':digest(val),'daily_memory_source_hash':'d'*64},'FIXTURE.SELIC.'+t['cCta']+t['idTitulo'])
 if not t['first_acquisition_period']:t['previous_title_balance']=proof({'supplier_id':base['book']['supplier_id'],'period':'2026-08','idTitulo':t['idTitulo'],'cCta':t['cCta'],'vSaldoContFin':t['vSaldoContIni']},'FIXTURE.PRIOR.'+t['cCta']+t['idTitulo'],'2026-08-01','2026-08-31')
def seal(v):
 d=v['debt_principal_movement'];d['evidence']=proof({'supplier_id':v['book']['supplier_id'],'period_end':v['book']['period_end'],'titles':d['titles'],'book_input_hash':digest(v['book']),'pgcc_source_hash':v['book']['pgcc_source_hash'],'principal_only_complete':True,'income_or_disposal_gain':'0.00'},'FIXTURE.D2101.PRINCIPAL.COMPLETE')
def month(titles):
 from decimal import Decimal
 v=copy.deepcopy(base)
 for a in v['book']['accounts']:
  if a['cCta'] not in ['debt000','debtfund000']:continue
  rows=[t for t in titles if t['cCta']==a['cCta']]
  for f,tf in [('vSaldoInic','vSaldoContIni'),('vMovDebt','vEntradas'),('vMovCred','vSaidas'),('vSaldoFinal','vSaldoContFin')]:a[f]=str(sum((Decimal(t[tf]) for t in rows),Decimal('0.00')))
  a['vAjusteDebt']=a['vMovDebt'];a['previous_balance']['amount']=a['vSaldoInic']
 v['debt_principal_movement']={'supplier_id':v['book']['supplier_id'],'period_end':v['book']['period_end'],'observed_on':'2026-10-06','titles':titles};seal(v);v['corpus_expectation']={'status':'PROJECTED'};return v
positives={'acquisition':month([title()]),'amortization':month([title(opening='10000.00',entries='0.00',exits='2500.00')]),'full-redemption':month([title(opening='10000.00',entries='0.00',exits='10000.00')]),'continuation':month([title(opening='10000.00',entries='2000.00',exits='500.00')]),'multiple-titles':month([title(entries='6000.00'),title(entries='4000.00',identifier=ID2)]),'same-isin-distinct-accounts':month([title(entries='6000.00'),title(account='debtfund000',entries='4000.00')]),'exclusive-fund':month([title(account='debtfund000')])}
for n,v in positives.items():write(f'{S}/{n}.json',v)
negative={}
def reject(n,v,reason):v['corpus_expectation']={'status':'ACTION_REQUIRED','reason':'RTC_DERE_D2101_'+reason};negative[n]=v
for f in ['vJurosRec','vVarMensal','vPisCofins','vApur']:
 v=copy.deepcopy(positives['acquisition']);v['debt_principal_movement']['titles'][0][f]='1.00';reject('yield-'+f,v,'YIELD_TAX_INTEGRATION_PENDING')
for n,f,value,reason in [('invalid-isin','idTitulo',ID[:-1]+str((int(ID[-1])+1)%10),'TITLE_ID_ACCOUNT_OR_ISSUER_INVALID'),('invalid-issuer','CNPJDevedor','11222333000182','TITLE_ID_ACCOUNT_OR_ISSUER_INVALID'),('government-title','instrument_type','GOVERNMENT_DEBT','PUBLIC_OFFER_ELIGIBILITY_UNPROVEN'),('private-placement','public_offer_regulator','PRIVATE','PUBLIC_OFFER_ELIGIBILITY_UNPROVEN'),('wrong-title-balance','vSaldoContFin','9999.00','TITLE_BALANCE_CONFLICT'),('missing-offer-proof','eligibility_evidence',None,'PUBLIC_OFFER_EVIDENCE_UNPROVEN'),('missing-selic-memory','selic_memory_evidence',None,'SELIC_DAILY_MEMORY_UNPROVEN')]:
 v=copy.deepcopy(positives['acquisition']);v['debt_principal_movement']['titles'][0][f]=value;reject(n,v,reason)
for n,f,value,reason in [('fund-below-95','public_offer_portfolio_percent','94.99','EXCLUSIVE_FUND_95_PERCENT_UNPROVEN'),('fund-not-exclusive','exclusive_fund',False,'EXCLUSIVE_FUND_95_PERCENT_UNPROVEN'),('missing-fund-name','descFundo','','EXCLUSIVE_FUND_95_PERCENT_UNPROVEN')]:
 v=copy.deepcopy(positives['exclusive-fund']);v['debt_principal_movement']['titles'][0][f]=value;reject(n,v,reason)
v=copy.deepcopy(positives['acquisition']);v['debt_principal_movement']['titles']*=2;reject('duplicate-composite-key',v,'TITLE_ID_ACCOUNT_OR_ISSUER_INVALID')
v=copy.deepcopy(positives['acquisition']);v['debt_principal_movement']['titles']=[];reject('empty-title-list',v,'TITLE_SCOPE_OR_DETAIL_INVALID')
v=copy.deepcopy(positives['acquisition']);v['debt_principal_movement']['evidence']['qualified']=False;reject('unqualified-complete-proof',v,'COMPLETE_PRINCIPAL_SCOPE_UNPROVEN')
v=copy.deepcopy(positives['acquisition']);v['debt_principal_movement']['evidence']['source_hash']='e'*64;reject('tampered-complete-proof',v,'COMPLETE_PRINCIPAL_SCOPE_UNPROVEN')
v=copy.deepcopy(positives['acquisition']);v['debt_principal_movement']['titles'][0]['selic_memory_evidence']['observed_on']='2026-10-07';reject('future-selic-memory',v,'SELIC_DAILY_MEMORY_UNPROVEN')
v=copy.deepcopy(positives['acquisition']);v['debt_principal_movement']['titles'][0]['eligibility_evidence']['valid_from']='2026-09-02';reject('incomplete-offer-period',v,'PUBLIC_OFFER_EVIDENCE_UNPROVEN')
v=copy.deepcopy(positives['acquisition']);v['debt_principal_movement']['semTitulos']='1';reject('inexistence-with-title',v,'TITLE_SCOPE_OR_DETAIL_INVALID')
v=copy.deepcopy(positives['acquisition']);v['debt_no_movement']={};reject('contradictory-declarations',v,'TITLE_SCOPE_OR_DETAIL_INVALID')
for n,change in [('missing-prior',None),('wrong-prior-account','debtfund000'),('wrong-prior-month','2026-07')]:
 v=copy.deepcopy(positives['continuation']);t=v['debt_principal_movement']['titles'][0]
 if change is None:t.pop('previous_title_balance')
 else:
  p=t['previous_title_balance'];p['value']['cCta' if n=='wrong-prior-account' else 'period']=change;p.pop('source_hash');p['source_hash']=digest(p)
 reject(n,v,'PREVIOUS_TITLE_BALANCE_UNPROVEN')
v=copy.deepcopy(positives['acquisition']);t=v['debt_principal_movement']['titles'][0];t['vSaldoContIni']='100.00';t['vSaldoContFin']='10100.00';reject('first-acquisition-opening',v,'FIRST_ACQUISITION_CONFLICT')
v=copy.deepcopy(positives['acquisition']);t=v['debt_principal_movement']['titles'][0];t['vEntradas']='9999.00';t['vSaldoContFin']='9999.00';qualify_title(t);reject('book-title-reconciliation',v,'TITLE_ACCOUNT_RECONCILIATION_CONFLICT')
v=copy.deepcopy(positives['same-isin-distinct-accounts']);t=v['debt_principal_movement']['titles'][1];t['CNPJDevedor']='11111111000191';qualify_title(t);seal(v);reject('isin-issuer-conflict',v,'ISIN_ISSUER_CONFLICT')
v=copy.deepcopy(positives['acquisition']);p=v['debt_principal_movement']['titles'][0]['selic_memory_evidence'];p['observed_on']='2026-10-07';p.pop('source_hash');p['source_hash']=digest(p);reject('future-selic-memory-qualified-hash',v,'SELIC_DAILY_MEMORY_UNPROVEN')
v=copy.deepcopy(positives['acquisition']);p=v['debt_principal_movement']['titles'][0]['eligibility_evidence'];p['valid_from']='2026-09-02';p.pop('source_hash');p['source_hash']=digest(p);reject('incomplete-offer-period-qualified-hash',v,'PUBLIC_OFFER_EVIDENCE_UNPROVEN')
for n,v in negative.items():write(f'{S}/{n}.json',v)
print(f'Candidate23:{len(positives)} positive/{len(negative)} negative scenarios; frozen principal-only contract.')
