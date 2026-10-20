"""Freeze official SGS11 response and a constant-position D2101 arithmetic candidate."""
import copy,hashlib,json
from datetime import datetime,timezone
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.06-24';S=f'scenarios/dere-constant-cost-{V}'
def read(p):return json.loads((F/p).read_text(encoding='utf-8'))
def digest(v):return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
def write(p,v):
 path=F/p
 if path.exists():assert read(p)==v,p;return
 path.parent.mkdir(parents=True,exist_ok=True);path.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
b=read('bundles/consumption-phase4b-2026.10.06-23.json');old=copy.deepcopy(b);bp=f'bundles/consumption-phase4b-{V}.json'
available=read(bp)['knowledge_available_at'] if (F/bp).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
b.update(bundle_id=f'CONSUMPTION-PHASE4B-{V}',version=V,predecessor_bundle_id=old['bundle_id'],knowledge_available_at=available)
research=ROOT/'storage/app/phase4b-d2101-research-20261006';captures=json.loads((research/'capture-manifest.json').read_text(encoding='utf-8'))
files={'bcb-sgs11-september.json':'bcb-sgs11-observed-20261006-202609.json','bcb-sgs11-metadata.html':'bcb-sgs11-metadata-observed-20261006.html','anna-identifiers.html':'anna-isin-identifiers-observed-20261006.html','rfb-cnpj-dv.pdf':'rfb-cnpj-dv-observed-20261006.pdf'}
for row in captures:
 assert row['status']=='CAPTURED';data=(research/row['file']).read_bytes();assert hashlib.sha256(data).hexdigest()==row['sha256'];target=F/'sources/official'/files[row['file']]
 if target.exists():assert target.read_bytes()==data
 else:target.write_bytes(data)
 b['official_sources'].append({'id':files[row['file']].split('.')[0].upper().replace('-','_'),'file':target.name,'sha256':row['sha256'],'url':row['url'],'retrieved_on':'2026-10-06','published_on':None,'publication_verification':'OFFICIAL_RESPONSE_OBSERVED_NOT_A_GENERAL_FISCAL_VALIDITY_DATE','proven_effective_scope':'Observed September2026 daily SGS11 rates, unit metadata, ISIN or CNPJ identity documentation only; no real position, taxpayer, offer or fund approval.'})
raw=json.loads((research/'bcb-sgs11-september.json').read_text(encoding='utf-8'))
rows=[{'date':datetime.strptime(x['data'],'%d/%m/%Y').strftime('%Y-%m-%d'),'daily_percent':x['valor']} for x in raw]
series={'series_code':11,'unit':'PERCENT_PER_BUSINESS_DAY','period':'2026-09','observed_at':available,'official_data_sha256':hashlib.sha256((research/'bcb-sgs11-september.json').read_bytes()).hexdigest(),'rows':rows};series['source_hash']=digest(series)
c=read(b['resource_paths']['rtc_code_catalog']);c['version']=V;c['dere_debt_constant_cost_calculation']={'contract':'DERE-D2101-CONSTANT-COST-CALCULATION-2026.10.06-1','status':'ACTIVE','published_at':available,'source_hash':'b7756c7b0cec5cff46d77e1b1ea5d657a4e2476fa6033f8192561c17dd70ab2d','official_data_file':files['bcb-sgs11-september.json'],'official_data_sha256':series['official_data_sha256'],'series_hash':series['source_hash'],'series':series,'scope':'CONSTANT_ACQUISITION_COST_FULL_MONTH_ONLY_NO_MONTHLY_YIELD_PROJECTION_OR_FINAL_TAX_DECISION'}
cp=f'catalogs/rtc-codes-{V}.json';write(cp,c);b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V
controls=read(b['production_controls']['path']);controls['version']=V
for row in controls['controls']:
 if row['id']=='4B.4.BASE':row['gap']+=' Candidato24: aritmetica D2101 por titulo com Selic diaria BCB11 observada, posicao de custo constante durante o mes inteiro e piso zero. Nao integrada a decisao financeira nem a posicoes com mudanca de estoque/custo.'
cp=f'production/phase4b-controls-{V}.json';write(cp,controls);b['production_controls']={'path':cp,'sha256':digest(controls)};write(bp,b);write(f'sources/official/phase4b-manifest-{V}.json',{'version':V,'retrieved_on':'2026-10-06','status':'SANDBOX_EVIDENCE_REVIEW_REQUIRED','artifacts':b['official_sources']})
for family in ['consumption-phase4b','consumption-integrated']:
 for path in sorted((F/f'scenarios/{family}-2026.10.06-23').glob('*.json')):
  v=json.loads(path.read_text(encoding='utf-8'));v.update(rule_bundle_id=b['bundle_id'],catalog_versions=b['catalog_versions'],knowledge_at=available);write(f'scenarios/{family}-{V}/{path.name}',v)
base={'supplier_id':'11111111000191','period':'2026-09','idTitulo':read('scenarios/dere-principal-movement-2026.10.06-23/acquisition.json')['debt_principal_movement']['titles'][0]['idTitulo'],'cCta':'debt000','held_entire_period':True,'constant_average_cost':True,'cost_basis_method':'WEIGHTED_AVERAGE_ACQUISITION_COST','vEntradas':'0.00','vSaidas':'0.00','quantity':'1000','weighted_average_unit_cost':'10.00','cost_basis':'10000.00','vVarMensal':'150.00','vPisCofins':'10.00'}
def seal(v):
 p={'qualified':True,'evidence_id':'FIXTURE.CONSTANT.COST.POSITION','supplier_id':v['supplier_id'],'observed_on':'2026-10-06','valid_from':'2026-09-01','valid_to':'2026-09-30','scope':'SYNTHETIC_QUALIFIED_POSITION_ONLY_OFFICIAL_RATES_REAL','value':{k:x for k,x in v.items() if k!='position_evidence'}};p['source_hash']=digest(p);v['position_evidence']=p
positive={}
for name,var,pis in [('capped-yield','150.00','10.00'),('below-cap','70.00','5.00'),('negative-variation','-50.00','0.00'),('zero-variation','0.00','0.00'),('pis-exceeds-yield','70.00','80.00'),('missing-pis','150.00',None)]:
 v=copy.deepcopy(base);v['vVarMensal']=var
 if pis is None:v.pop('vPisCofins')
 else:v['vPisCofins']=pis
 seal(v);positive[name]=v
v=copy.deepcopy(base);v.update(quantity='1000',weighted_average_unit_cost='10.12345678',cost_basis='10123.46');seal(v);positive['fractional-cost']=v
for name,v in positive.items():write(f'{S}/{name}.json',{'position':v,'expectation':{'status':'RESOLVED'}})
negative={}
def reject(name,v,reason):negative[name]={'position':v,'expectation':{'status':'ACTION_REQUIRED','reason':'RTC_DERE_D2101_'+reason}}
for name,field,value,reason in [('changing-quantity','vEntradas','100.00','CHANGING_POSITION_OR_COST_PENDING'),('changing-cost','constant_average_cost',False,'CHANGING_POSITION_OR_COST_PENDING'),('part-month','held_entire_period',False,'CHANGING_POSITION_OR_COST_PENDING'),('cost-basis-conflict','cost_basis','9999.00','QUANTITY_COST_BASIS_CONFLICT'),('wrong-period','period','2026-08','OFFICIAL_DAILY_SERIES_UNPROVEN'),('claimed-selic-conflict','claimed_vSelic','100.00','DECLARED_SELIC_OR_BASE_CONFLICT'),('claimed-base-conflict','claimed_vApur','100.00','DECLARED_SELIC_OR_BASE_CONFLICT'),('negative-pis','vPisCofins','-1.00','AMOUNT_OR_DATE_INVALID'),('malformed-amount','vVarMensal','1,00','AMOUNT_OR_DATE_INVALID'),('zero-quantity','quantity','0','QUANTITY_COST_BASIS_CONFLICT')]:
 v=copy.deepcopy(base);v[field]=value;seal(v);reject(name,v,reason)
v=copy.deepcopy(base);reject('missing-proof',v,'CONSTANT_POSITION_EVIDENCE_UNPROVEN')
v=copy.deepcopy(base);seal(v);v['position_evidence']['source_hash']='e'*64;reject('tampered-proof',v,'CONSTANT_POSITION_EVIDENCE_UNPROVEN')
for name,field,value in [('future-proof','observed_on','2026-10-07'),('incomplete-month','valid_from','2026-09-02'),('expired-proof','valid_to','2026-09-29')]:
 v=copy.deepcopy(base);seal(v);p=v['position_evidence'];p[field]=value;p.pop('source_hash');p['source_hash']=digest(p);reject(name,v,'CONSTANT_POSITION_EVIDENCE_UNPROVEN')
for n,v in negative.items():write(f'{S}/{n}.json',v)
print(f'Candidate24:{len(positive)} positive/{len(negative)} negative;21 official daily rates;47 bound official sources.')
