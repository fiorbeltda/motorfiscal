"""Freeze the evolving scope28 book-binding policy without overwriting any predecessor."""
import copy,json,hashlib
from datetime import datetime,timezone
from pathlib import Path
from dere_daily_memory_oracle import digest,proof
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.06-28-draft3'
E=ROOT/'storage/app/phase4b-realization-book-draft-20261006-28';E.mkdir(parents=True,exist_ok=True)
S=F/f'scenarios/tvm-book-{V}';S.mkdir(parents=True,exist_ok=True)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:p.parent.mkdir(parents=True,exist_ok=True);p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
if not (E/'historical-before.json').exists():
 baseline={}
 for base in [F,ROOT/'storage/app']:
  for p in base.rglob('*'):
   if p.is_file() and E not in p.parents and V not in str(p) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']:baseline[p.relative_to(ROOT).as_posix()]=hashlib.sha256(p.read_bytes()).hexdigest()
 save(E/'historical-before.json',baseline)
old=read(F/'bundles/consumption-phase4b-2026.10.06-28-draft2.json');b=copy.deepcopy(old);path=F/f'bundles/consumption-phase4b-{V}.json'
known=read(path)['knowledge_available_at'] if path.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
b.update(bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,predecessor_bundle_id=old['bundle_id'],knowledge_available_at=known)
c=read(F/old['resource_paths']['rtc_code_catalog']);c['version']=V
closing=c['dere_monthly_closing_projection'];reference=closing['pgcc_catalogs']['reference'];reference['version']=V
members=[('1100000002','1.1.0.00.00.00-2','DISPONIBILIDADES',181,'-'),('1111000008','1.1.1.10.00.00-8','CAIXA',181,'111'),
 ('7157500009','7.1.5.75.00.00-9','LUCROS COM TÍTULOS DE RENDA FIXA',506,'711'),('8152000008','8.1.5.20.00.00-8','(-) PREJUÍZOS COM TÍTULOS DE RENDA FIXA',546,'712')]
for code,formatted,title,page,estban in members:
 reference['accounts'][code]={'code':code,'formatted_code':formatted,'title':title,'pdf_page':page,'estban':estban,'institution_attributes':'NOT_PROVEN','fiscal_validity':'NOT_PROVEN_BY_OBSERVED_DOCUMENT'}
reference['qualification_scope']='FOURTEEN_SELECTED_COSIF_MEMBERS_NO_HISTORICAL_OR_INSTITUTION_APPROVAL'
save(F/f'catalogs/dere-cosif-reference-{V}.json',reference)
closing['pgcc_qualification_policy']['reference_catalog_sha256']=digest(reference)
c['dere_pgcc_projection']['qualification_policy']['reference_catalog_sha256']=digest(reference)
closing['securities_realization_binding']={'contract':'DERE-TVM-REALIZATION-BOOK-BINDING-2026.10.06-1','status':'ACTIVE','published_at':known,
 'source_hash':c['financial_operation']['base']['source_hash'],'table_source_hash':closing['pgcc_catalogs']['tax_codes']['source_hash'],
 'scope':'DEVELOPMENT_BOOK_BRIDGE_NOT_INTEGRAL_SCOPE28_ACCEPTANCE','production_ready':False}
cp=f'catalogs/rtc-codes-{V}.json';save(F/cp,c);b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V
ctrl=read(F/old['production_controls']['path']);ctrl['version']=V
for item in ctrl['controls']:
 if item['id']=='4B.7.DOCUMENT':item['gap']+=' Draft2:ponte privada sem valor justo com diarios balanceados em verificacao;oferta publica e subcontas de valor justo ainda pendentes.'
cr=f'production/phase4b-controls-{V}.json';save(F/cr,ctrl);b['production_controls']={'path':cr,'sha256':digest(ctrl)};save(path,b)
for case in ['first-gain','first-loss','first-offsetting']:
 scenario=read(F/f'scenarios/tvm-base-2026.10.06-28-draft/{case}-financial.json')
 scenario.update(rule_bundle_id=b['bundle_id'],catalog_versions=b['catalog_versions'],knowledge_at=known)
 save(S/(case+'-financial.json'),scenario)
save(E/'research-membership.json',{'source_file':reference['source_file'],'source_sha256':reference['source_hash'],'additional_members':[list(row) for row in members],
 'debt_and_cash_balance_codTrib':'118915099','table_source_sha256':closing['pgcc_catalogs']['tax_codes']['source_hash'],
 'membership_only':True,'taxpayer_mapping_evidence':'SYNTHETIC','scope28_completed':False})
print('Draft2 frozen with four additional official COSIF members and explicit journal-binding policy.')


