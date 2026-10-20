"""Prepare successor46 with the explicit source39 permission required by the original multiple-account case."""
import json,copy,hashlib,subprocess
from pathlib import Path
from datetime import datetime,timezone
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.09-28-draft46';OLD='2026.10.09-28-draft45';E=ROOT/'storage/app/phase4b-realization-consolidated-successor46-draft-20261009-28';S=F/('scenarios/tvm-realization-consolidated-'+V)
E.mkdir(exist_ok=True);S.mkdir(exist_ok=True)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,v):
 p.parent.mkdir(exist_ok=True)
 if p.exists():assert read(p)==v,p
 else:
  with p.open('x',encoding='utf-8') as out:out.write(json.dumps(v,ensure_ascii=False,indent=2)+'\n')
 if p.stat().st_size>1000000:
  h=sha(p);r=subprocess.run(['compact.exe','/C','/F','/I','/Q','/EXE:LZX',str(p)],capture_output=True);assert r.returncode==0 and sha(p)==h
prep=ROOT/'storage/app/phase4b-realization-consolidation-preparation-20261009-28'
assert read(prep/'policy-selection-tests.json')['exit_code']==0 and read(prep/'regression43-directed.json')['exit_code']==0 and read(prep/'phpstan.json')['exit_code']==0
if not (E/'historical-before.json').exists():save(E/'historical-before.json',{p.relative_to(ROOT).as_posix():sha(p) for folder in [F,ROOT/'storage/app'] for p in folder.rglob('*') if p.is_file() and E not in p.parents and V not in str(p) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']})
b=read(F/('bundles/consumption-phase4b-'+OLD+'.json'));c=read(F/b['resource_paths']['rtc_code_catalog']);target=F/('bundles/consumption-phase4b-'+V+'.json');known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat();c['version']=V;q=c['financial_operation']['base']['public_offer_debt'];a=q['sale_recognition_policy']['analysis'];h=a['history_policy'];source39=read(F/'bundles/consumption-phase4b-2026.10.08-28-draft39.json');source41=read(F/'catalogs/rtc-codes-2026.10.09-28-draft41.json')['financial_operation']['base']['securities_realization']['analysis']['history_policy'];assert digest(source39) in source41['permitted_bundle_manifest_hashes'];h['permitted_bundle_manifest_hashes']=list(dict.fromkeys(h['permitted_bundle_manifest_hashes']+[digest(source39)]));h['published_at']=known
profiles={}
for version,folder in [('2026.10.09-28-draft43','tvm-shared-asset-current-'),('2026.10.09-28-draft41','tvm-historical-multiple-current-'),('2026.10.08-28-draft15','tvm-public-deferral-'),('2026.10.08-28-draft38','tvm-third-sale-integrated-')]:
 policy=read(F/('catalogs/rtc-codes-'+version+'.json'))['financial_operation']['base']['securities_realization']['analysis']['history_policy']
 for path in (F/('scenarios/'+folder+version)).glob('*-current.json'):
  fixture=read(path);history=fixture.get('recognition_history',{})
  sources=history.get('fiscal_sources',[])
  if not sources:continue
  manifests=sorted(set(s['snapshot']['knowledge']['content_hashes']['bundle_manifest'] for s in sources))
  assert all(m in policy['permitted_bundle_manifest_hashes'] for m in manifests)
  key=digest(manifests);profile={'manifest_hashes':manifests,'policy':copy.deepcopy(policy),'policy_hash':digest(policy),'origin_catalog_version':version}
  if key in profiles:assert profiles[key]['policy_hash']==profile['policy_hash'],(key,version)
  profiles[key]=profile
assert len(profiles)>=4,len(profiles)
selection={'strategy':'BY_EXACT_REPLAYED_MANIFEST_SET','status':'ACTIVE','published_at':known,'profiles':profiles}
selection['source_hash']=digest(selection);h['source_policy_selection']=selection
q['published_at']=known;q['sale_recognition_policy']['published_at']=known;a['published_at']=known
for owner in [q,a,c]:
 key='dere_debt_daily_memory_calculation' if owner is c else 'daily_memory_calculation';owner[key]['competence_policy_selection']['published_at']=known
c['financial_operation']['base']['securities_realization'].update(published_at=known,analysis=copy.deepcopy(a),public_offer_debt=copy.deepcopy(q));c['dere_monthly_closing_projection']['securities_realization_binding']['published_at']=known;c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(q)
cp='catalogs/rtc-codes-'+V+'.json';save(F/cp,c);ctrl=read(F/b['production_controls']['path']);ctrl['version']=V;ct='production/phase4b-controls-'+V+'.json';save(F/ct,ctrl);b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,knowledge_available_at=known);b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V;b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
for kind,version in [('shared-assets','2026.10.09-28-draft43'),('separate-assets','2026.10.09-28-draft41')]:
 source=F/('scenarios/'+('tvm-shared-asset-current-' if kind=='shared-assets' else 'tvm-historical-multiple-current-')+version)
 for p in source.glob('*-current.json'):save(S/kind/p.name,read(p))
save(E/'preparation.json',{'bundle':V,'predecessor':OLD,'added_historical_source_bundle':'2026.10.08-28-draft39','added_historical_manifest_hash':digest(source39),'original_multiple_account_permission_proved_in':'2026.10.09-28-draft41','current_competences':['2026-07','2026-08','2026-09'],'official_sources_registered':51,'scope28_completed':False,'unified_candidate_accepted':False,'production_ready':False});print(json.dumps({'prepared':V,'source39_permitted':True,'official_sources':51,'unified_candidate_accepted':False}))
