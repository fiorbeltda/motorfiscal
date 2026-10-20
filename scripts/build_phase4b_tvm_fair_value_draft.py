"""Freeze explicit fair-value realization and current-curve fixtures for integral scope28 progress."""
import copy,json,hashlib
from decimal import Decimal
from datetime import datetime,timezone
from pathlib import Path
from dere_daily_memory_oracle import oracle,seal,digest
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.06-28-draft4'
E=ROOT/'storage/app/phase4b-realization-fair-value-draft-20261006-28';E.mkdir(parents=True,exist_ok=True)
S=F/f'scenarios/tvm-fair-value-{V}';S.mkdir(parents=True,exist_ok=True)
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
old=read(F/'bundles/consumption-phase4b-2026.10.06-28-draft3.json');b=copy.deepcopy(old);target=F/f'bundles/consumption-phase4b-{V}.json'
known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
b.update(bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,predecessor_bundle_id=old['bundle_id'],knowledge_available_at=known)
c=read(F/old['resource_paths']['rtc_code_catalog']);c['version']=V
qualifier=c['financial_operation']['base']['securities_realization'];qualifier['published_at']=known
qualifier['deferred_fair_value_code_policy']='DERE_RECOGNIZED_BY_COMPETENCE_REALIZED_311011702_411011701'
closing=c['dere_monthly_closing_projection'];closing['securities_realization_binding'].update(published_at=known,fair_value_journal_mode='EXPLICIT_RECLASSIFICATION_AND_INDEPENDENT_SUBLEDGER')
reference=closing['pgcc_catalogs']['reference'];reference['version']=V;reference['qualification_scope']='FIFTEEN_SELECTED_COSIF_MEMBERS_NO_HISTORICAL_OR_INSTITUTION_APPROVAL'
reference['accounts']['7151000000']={'code':'7151000000','formatted_code':'7.1.5.10.00.00-0','title':'RENDAS DE TÍTULOS DE RENDA FIXA','pdf_page':506,'estban':'711','institution_attributes':'NOT_PROVEN','fiscal_validity':'NOT_PROVEN_BY_OBSERVED_DOCUMENT'}
save(F/f'catalogs/dere-cosif-reference-{V}.json',reference)
closing['pgcc_qualification_policy']['reference_catalog_sha256']=digest(reference);c['dere_pgcc_projection']['qualification_policy']['reference_catalog_sha256']=digest(reference)
cp=f'catalogs/rtc-codes-{V}.json';save(F/cp,c);b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V
ctrl=read(F/old['production_controls']['path']);ctrl['version']=V
for row in ctrl['controls']:
 if row['id']=='4B.7.DOCUMENT':row['gap']+=' Draft4:valor justo requer subconta, diarios reais fornecidos, ajustes e evidencia independente;oferta publica e demais caminhos28 continuam em desenvolvimento.'
ct=f'production/phase4b-controls-{V}.json';save(F/ct,ctrl);b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
base=read(F/'scenarios/tvm-base-2026.10.06-28-draft/first-gain.json')['position']
for name,curve,produced,fair,book,price,revenue,deductions in [
 ('first-new-fair-gain','0','30','50','10000.00','10080.00','80.00','0.00'),
 ('first-new-fair-loss','0','0','-100','10000.00','9900.00','0.00','100.00'),
 ('first-current-curve','50','150','0','10050.00','10200.00','200.00','0.00'),
 ('first-opposing-new-components','0','30','-30','10000.00','10000.00','30.00','30.00')]:
 p=copy.deepcopy(base);p['operations'][1]['book_amount']=book;p['operations'][1]['sale_proceeds']=price
 p['appropriations']={'2026-09-14':curve} if Decimal(curve) else {}
 curve_rows,monthly,result=oracle(p,c['dere_debt_daily_memory_calculation']['series']);p.pop('appropriations');p['contract_curve']=curve_rows;p['declared_monthly']=monthly;seal(p)
 parts={'prior_produced_return':'0','current_produced_return_already_in_curve':curve,'new_produced_return_at_sale':produced,
  'deferred_fair_value_realized':'0','new_fair_value_change_at_sale':fair,'rounding_bridge':'0'}
 save(S/(name+'.json'),{'position':p,'independent_oracle':result,'components':{'sale1':parts},'expected':{'revenue':revenue,'deductions':deductions}})
save(E/'research.json',{'legal_source_sha256':qualifier['source_hash'],'table_source_sha256':closing['pgcc_catalogs']['tax_codes']['source_hash'],
 'deferred_positive_codTrib':'311011702','deferred_negative_codTrib':'411011701','new_positive_codTrib':'311011107','new_negative_codTrib':'411011102',
 'table_pdf_pages':[23,24,34],'cosif_member':'7151000000','cosif_pdf_pages':[506,517],
 'reclassification':'Provided synthetic book journals, never generated postings or a claim of mandatory accounting reclassification',
 'normative_homologation_claimed':False,'scope28_completed':False})
print('Draft4 and four independent first-month fair-value/current-curve fixtures frozen.')

