"""Independent identities and positions for simultaneous private title realizations."""
import copy,hashlib,json
from datetime import datetime,timezone
from pathlib import Path
from dere_daily_memory_oracle import seal,digest
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.07-28-draft6'
E=ROOT/'storage/app/phase4b-realization-multiple-titles-draft-20261007-28';S=F/f'scenarios/tvm-multiple-titles-{V}'
E.mkdir(parents=True,exist_ok=True);S.mkdir(parents=True,exist_ok=True)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:p.parent.mkdir(parents=True,exist_ok=True);p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
if not (E/'historical-before.json').exists():
 baseline={}
 for folder in [F,ROOT/'storage/app']:
  for p in folder.rglob('*'):
   if p.is_file() and E not in p.parents and V not in str(p) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']:baseline[p.relative_to(ROOT).as_posix()]=hashlib.sha256(p.read_bytes()).hexdigest()
 save(E/'historical-before.json',baseline)
b=read(F/'bundles/consumption-phase4b-2026.10.06-28-draft5.json');target=F/f'bundles/consumption-phase4b-{V}.json'
known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
c=read(F/b['resource_paths']['rtc_code_catalog']);c['version']=V
c['financial_operation']['base']['securities_realization']['published_at']=known;c['dere_monthly_closing_projection']['securities_realization_binding']['published_at']=known
cp=f'catalogs/rtc-codes-{V}.json';save(F/cp,c)
ctrl=read(F/b['production_controls']['path']);ctrl['version']=V
ct=f'production/phase4b-controls-{V}.json';save(F/ct,ctrl)
b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,knowledge_available_at=known)
b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V;b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
title=read(F/'scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json')['facts']['rtc.financial_ledger'][0]['entries'][4]['securities_realization']['analysis_input']['title']
def valid_isin(s):
 digits=''.join(str(ord(c)-55) if c.isalpha() else c for c in s)
 return sum(sum(divmod(int(n)*(2 if i%2 else 1),10)) for i,n in enumerate(digits[::-1]))%10==0
second=next('BRFIXTDEB02'+str(n) for n in range(10) if valid_isin('BRFIXTDEB02'+str(n)))
for name,source,id,account in [('gain','first-new-fair-gain',title['idTitulo'],'debt000'),('loss','first-new-fair-loss',second,'debt2000')]:
 fixture=read(F/f'scenarios/tvm-fair-value-2026.10.06-28-draft4/{source}.json')
 fixture['position']['idTitulo']=id;fixture['position']['cCta']=account;seal(fixture['position'])
 fixture['title']={**title,'idTitulo':id,'cCta':account};save(S/(name+'.json'),fixture)
save(E/'expected.json',{'titles':[title['idTitulo'],second],'generic_base':'700.00','title_revenue':'80.00','title_deductions':'100.00','combined_net_base':'680.00','production_ready':False,'scope28_completed':False})
print('Draft6 and two distinct ISIN positions frozen.')
