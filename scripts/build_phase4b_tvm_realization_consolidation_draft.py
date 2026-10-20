"""Prepare a unified knowledge bundle with explicit competence policies; no acceptance is inferred."""
import copy,json,hashlib,subprocess
from pathlib import Path
from datetime import datetime,timezone
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.09-28-draft44';E=ROOT/'storage/app/phase4b-realization-consolidation-preparation-20261009-28';S=F/('scenarios/tvm-realization-consolidated-'+V)
S.mkdir(exist_ok=True)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:
  with p.open('x',encoding='utf-8') as out:out.write(json.dumps(v,ensure_ascii=False,indent=2)+'\n')
 if p.stat().st_size>1000000:
  h=sha(p);r=subprocess.run(['compact.exe','/C','/F','/I','/Q','/EXE:LZX',str(p)],capture_output=True);assert r.returncode==0 and sha(p)==h
prior_report=ROOT/'storage/app/phase4b-realization-shared-asset-current-draft-20261009-28/multiple-progress-report.json';assert read(prior_report)['current_multiple_title_closing_accepted']
if not (E/'historical-before.json').exists():
 baseline={p.relative_to(ROOT).as_posix():sha(p) for folder in [F,ROOT/'storage/app'] for p in folder.rglob('*') if p.is_file() and E not in p.parents and V not in str(p) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']};save(E/'historical-before.json',baseline)
versions=['2026.10.08-28-draft38','2026.10.09-28-draft43','2026.10.08-28-draft29'];bundles=[read(F/('bundles/consumption-phase4b-'+v+'.json')) for v in versions];catalogs=[read(F/b['resource_paths']['rtc_code_catalog']) for b in bundles];b=copy.deepcopy(bundles[0]);c=copy.deepcopy(catalogs[0]);target=F/('bundles/consumption-phase4b-'+V+'.json');known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat();c['version']=V
q=c['financial_operation']['base']['public_offer_debt'];a=q['sale_recognition_policy']['analysis'];h=a['history_policy']
for other in catalogs[1:]:
 other_h=other['financial_operation']['base']['securities_realization']['analysis']['history_policy']
 for k,v in other_h.items():
  if k not in h:h[k]=copy.deepcopy(v)
  elif k=='permitted_bundle_manifest_hashes':h[k]=list(dict.fromkeys(h[k]+v))
  elif k=='daily_memory_policies':
   for period,policy in v.items():h[k].setdefault(period,copy.deepcopy(policy))
# Previous policies remain fixed. The current selection uses their actual official period data.
policies={'2026-07':copy.deepcopy(catalogs[1]['financial_operation']['base']['securities_realization']['analysis']['history_policy']['daily_memory_policies']['2026-07']),'2026-08':copy.deepcopy(catalogs[1]['dere_debt_daily_memory_calculation']),'2026-09':copy.deepcopy(catalogs[0]['dere_debt_daily_memory_calculation'])}
for period,policy in policies.items():assert policy['series']['period']==period and 'competence_policy_selection' not in policy
selection={'strategy':'BY_COMPETENCE_WITH_FROZEN_OFFICIAL_POLICY_HASHES','status':'ACTIVE','published_at':known,'source_hash':policies['2026-09']['source_hash'],'policy_hashes':{p:digest(v) for p,v in policies.items()},'policies':policies};daily=copy.deepcopy(policies['2026-09']);daily['competence_policy_selection']=selection
q.update(published_at=known,daily_memory_calculation=copy.deepcopy(daily));q['sale_recognition_policy']['published_at']=known;a.update(published_at=known,daily_memory_calculation=copy.deepcopy(daily));h['published_at']=known
c['dere_debt_daily_memory_calculation']=copy.deepcopy(daily);c['financial_operation']['base']['securities_realization'].update(published_at=known,analysis=copy.deepcopy(a),public_offer_debt=copy.deepcopy(q));c['dere_monthly_closing_projection']['securities_realization_binding'].update(published_at=known,asset_position_mode='PUBLIC_AGGREGATE_WITH_PRIVATE_DISTINCT_ACCOUNTS');c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(q)
observations=read(E/'fresh-source-observations.json');new_sources=[('LC214_PLANALTO_OBSERVED_20261009','lc214-planalto-observed-20261009.html',observations[0]),('DECRETO12955_CAMARA_OBSERVED_20261009','decreto-12955-camara-observed-20261009.html',observations[1])]
for id,file,obs in new_sources:
 src=ROOT/obs['path'];assert obs['downloaded'] and sha(src)==obs['sha256'];dst=F/'sources/official'/file
 if dst.exists():assert dst.read_bytes()==src.read_bytes()
 else:
  with dst.open('xb') as out:out.write(src.read_bytes())
 assert not any(s['id']==id for s in b['official_sources']);b['official_sources'].append({'id':id,'url':obs['url'],'file':file,'sha256':obs['sha256'],'retrieved_on':'2026-10-09','published_on':None,'publication_verification':'CURRENT_OFFICIAL_TEXT_OBSERVED_SELECTED_PROVISIONS_REVIEWED','proven_effective_scope':'Selected LC214 art192/art195 and regulation articles286/289 for development research; not an approval of every provision or of an unsupported realization bridge.'})
assert len(b['official_sources'])==51
cp='catalogs/rtc-codes-'+V+'.json';save(F/cp,c);ctrl=read(F/bundles[1]['production_controls']['path']);ctrl['version']=V;ct='production/phase4b-controls-'+V+'.json';save(F/ct,ctrl);b.update(predecessor_bundle_id=bundles[1]['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,knowledge_available_at=known);b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V;b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
for p in (F/'scenarios/tvm-shared-asset-current-2026.10.09-28-draft43').glob('*-current.json'):save(S/p.name,read(p))
save(E/'bundle-preparation.json',{'status':'UNIFIED_KNOWLEDGE_BUNDLE_PREPARATION_ONLY','bundle':V,'knowledge_available_at':known,'source_bundles':versions,'current_competences':list(policies),'official_sources_registered':51,'historical_permission_count':len(h['permitted_bundle_manifest_hashes']),'requirements_matrix_sha256':sha(E/'requirements-evidence-matrix.json'),'preceding_accepted_report_sha256':sha(prior_report),'scope28_completed':False,'unified_candidate_accepted':False,'production_ready':False});print(json.dumps({'prepared':V,'competences':list(policies),'official_sources':51,'unified_candidate_accepted':False}))
