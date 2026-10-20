"""Prepare separate, source-bound private policies without rewriting historical evidence."""
import copy,json,hashlib
from pathlib import Path
from datetime import datetime,timezone
from dere_daily_memory_oracle import digest

ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'resources/fiscal'
OLD='2026.10.09-28-draft47'
V='2026.10.09-28-draft48'
PREVIOUS=ROOT/'storage/app/phase4b-realization-consolidated-expansion47-draft-20261009-28'
E=ROOT/'storage/app/phase4b-private-consolidation48-draft-20261009-28'
E.mkdir(exist_ok=True)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,value):
 p.parent.mkdir(exist_ok=True)
 if p.exists():assert read(p)==value,p
 else:
  with p.open('x',encoding='utf-8') as out:out.write(json.dumps(value,ensure_ascii=False,indent=2)+'\n')

report=PREVIOUS/'consolidation-progress-report.json'
assert read(report)['expanded_historical_corpus_validated']
inventory=read(PREVIOUS/'next-private-corpus-inventory.json')['inspected_cases']
assert len(inventory)==15
for case in inventory:
 for kind in ['financial','monthly']:
  assert sha(ROOT/case[kind+'_input'])==case[kind+'_input_sha256']
b=read(F/('bundles/consumption-phase4b-'+OLD+'.json'))
c=read(F/b['resource_paths']['rtc_code_catalog'])
target=F/('bundles/consumption-phase4b-'+V+'.json')
known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
c['version']=V
q=c['financial_operation']['base']['public_offer_debt']
a=q['sale_recognition_policy']['analysis']
h=a['history_policy'];selection=h['source_policy_selection'];profiles=selection['profiles']
choices=[]
for original in read(PREVIOUS/'next-private-policy-selection-audit.json')['original_profiles']:
 path=ROOT/original['catalog_path'];assert sha(path)==original['catalog_sha256']
 policy=read(path)['financial_operation']['base']['securities_realization']['analysis']['history_policy']
 assert digest(policy)==original['policy_hash']
 manifests=original['manifest_hashes'];key=digest(manifests)
 assert key==original['selection_key'] and key not in profiles
 assert all(value in policy['permitted_bundle_manifest_hashes'] for value in manifests)
 profiles[key]={'manifest_hashes':manifests,'policy':copy.deepcopy(policy),'policy_hash':digest(policy),'origin_catalog_version':original['origin_catalog_version']}
 h['permitted_bundle_manifest_hashes']=list(dict.fromkeys(h['permitted_bundle_manifest_hashes']+policy['permitted_bundle_manifest_hashes']))
 choices.append(copy.deepcopy(original))
assert len(profiles)==14
selection['published_at']=known
selection['source_hash']=digest({k:v for k,v in selection.items() if k!='source_hash'})
h['published_at']=known
q['published_at']=known;q['sale_recognition_policy']['published_at']=known;a['published_at']=known
for owner in [q,a,c]:
 key='dere_debt_daily_memory_calculation' if owner is c else 'daily_memory_calculation'
 owner[key]['competence_policy_selection']['published_at']=known
c['financial_operation']['base']['securities_realization'].update(published_at=known,analysis=copy.deepcopy(a),public_offer_debt=copy.deepcopy(q))
c['dere_monthly_closing_projection']['securities_realization_binding']['published_at']=known
c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(q)
cp='catalogs/rtc-codes-'+V+'.json';save(F/cp,c)
controls=read(F/b['production_controls']['path']);controls['version']=V
ct='production/phase4b-controls-'+V+'.json';save(F/ct,controls)
b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,knowledge_available_at=known)
b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V
b['production_controls']={'path':ct,'sha256':digest(controls)};save(target,b)
save(E/'private-cases.json',{'bundle':V,'cases':inventory,'case_count':15,'source_profiles':14,'private_policy_choices':choices,'preceding_report_sha256':sha(report),'scope28_completed':False,'production_ready':False})
print(json.dumps({'prepared':V,'private_cases':15,'source_profiles':14,'production_ready':False}))
