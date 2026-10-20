"""Extend consolidation with explicit source profiles and all captured historical realization fixtures."""
import json,copy,hashlib,subprocess,re
from pathlib import Path
from datetime import datetime,timezone
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.09-28-draft47';OLD='2026.10.09-28-draft46'
E=ROOT/'storage/app/phase4b-realization-consolidated-expansion47-draft-20261009-28';S=F/('scenarios/tvm-realization-consolidated-'+V)
E.mkdir(exist_ok=True);S.mkdir(exist_ok=True)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,v):
 p.parent.mkdir(exist_ok=True)
 if p.exists():assert read(p)==v,p
 else:
  with p.open('x',encoding='utf-8') as out:out.write(json.dumps(v,ensure_ascii=False,indent=2)+'\n')
 if p.stat().st_size>1000000:
  h=sha(p);result=subprocess.run(['compact.exe','/C','/F','/I','/Q','/EXE:LZX',str(p)],capture_output=True);assert result.returncode==0 and sha(p)==h
previous=ROOT/'storage/app/phase4b-realization-consolidated-successor46-draft-20261009-28/consolidation-progress-report.json';assert read(previous)['representative_cases_validated'] and read(previous)['quality']['full-suite']['exit_code']==0
if not (E/'historical-before.json').exists():save(E/'historical-before.json',{p.relative_to(ROOT).as_posix():sha(p) for folder in [F,ROOT/'storage/app'] for p in folder.rglob('*') if p.is_file() and E not in p.parents and V not in str(p) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']})
b=read(F/('bundles/consumption-phase4b-'+OLD+'.json'));c=read(F/b['resource_paths']['rtc_code_catalog']);target=F/('bundles/consumption-phase4b-'+V+'.json');known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
c['version']=V;q=c['financial_operation']['base']['public_offer_debt'];a=q['sale_recognition_policy']['analysis'];h=a['history_policy'];selection=h['source_policy_selection'];profiles=selection['profiles'];cases={};equivalences=[]
folders=[]
for folder in (F/'scenarios').iterdir():
 match=re.search(r'(2026\.\d{2}\.\d{2}-28-draft\d+)$',folder.name)
 if folder.is_dir() and match and int(match[1].split('draft')[-1])<=43:folders.append((int(match[1].split('draft')[-1]),match[1],folder))
def semantic(policy):return {k:v for k,v in policy.items() if k not in ['published_at','permitted_bundle_manifest_hashes']}
for number,version,folder in sorted(folders):
 catalog=F/('catalogs/rtc-codes-'+version+'.json')
 if not catalog.exists():continue
 policy=read(catalog).get('financial_operation',{}).get('base',{}).get('securities_realization',{}).get('analysis',{}).get('history_policy')
 if not policy:continue
 for path in sorted(folder.glob('*-current.json')):
  fixture=read(path);history=fixture.get('recognition_history',{});sources=history.get('fiscal_sources',[]);name=path.name.removesuffix('-current.json');financial=folder/(name+'-financial.json');month=folder/(name+'-month.json')
  if not sources or not financial.exists() or not month.exists():continue
  manifests=sorted(set(source['snapshot']['knowledge']['content_hashes']['bundle_manifest'] for source in sources));assert all(value in policy['permitted_bundle_manifest_hashes'] for value in manifests)
  key=digest(manifests);profile={'manifest_hashes':manifests,'policy':copy.deepcopy(policy),'policy_hash':digest(policy),'origin_catalog_version':version}
  if key in profiles and profiles[key]['policy_hash']!=profile['policy_hash']:
   previous_profile=profiles[key];assert semantic(previous_profile['policy'])==semantic(policy),(key,version)
   old_permissions=set(previous_profile['policy']['permitted_bundle_manifest_hashes']);new_permissions=set(policy['permitted_bundle_manifest_hashes'])
   assert old_permissions<=new_permissions or new_permissions<=old_permissions,(key,version,'permissions not nested')
   if not old_permissions<=new_permissions:profile=copy.deepcopy(previous_profile)
   equivalences.append({'selection_key':key,'original_policy_hash':digest(policy),'previous_profile_hash':previous_profile['policy_hash'],'selected_policy_hash':profile['policy_hash'],'semantic_policy_hash':digest(semantic(policy)),'differences_restricted_to':['published_at','permitted_bundle_manifest_hashes'],'permissions_nested':True})
  profiles[key]=profile;h['permitted_bundle_manifest_hashes']=list(dict.fromkeys(h['permitted_bundle_manifest_hashes']+profile['policy']['permitted_bundle_manifest_hashes']))
  case='history-draft'+str(number)+'-'+name
  cases[case]={'fixture':path.relative_to(ROOT).as_posix(),'fixture_sha256':sha(path),'original_financial_input':financial.relative_to(ROOT).as_posix(),'original_financial_input_sha256':sha(financial),'original_month_input':month.relative_to(ROOT).as_posix(),'original_month_input_sha256':sha(month),'origin_catalog_version':version,'source_selection_key':key,'expected':fixture['expected']}
assert len(cases)==78,len(cases)
selection['published_at']=known;selection['source_hash']=digest({k:v for k,v in selection.items() if k!='source_hash'});h['published_at']=known
q['published_at']=known;q['sale_recognition_policy']['published_at']=known;a['published_at']=known
for owner in [q,a,c]:
 key='dere_debt_daily_memory_calculation' if owner is c else 'daily_memory_calculation';owner[key]['competence_policy_selection']['published_at']=known
c['financial_operation']['base']['securities_realization'].update(published_at=known,analysis=copy.deepcopy(a),public_offer_debt=copy.deepcopy(q));c['dere_monthly_closing_projection']['securities_realization_binding']['published_at']=known;c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(q)
cp='catalogs/rtc-codes-'+V+'.json';save(F/cp,c);controls=read(F/b['production_controls']['path']);controls['version']=V;ct='production/phase4b-controls-'+V+'.json';save(F/ct,controls);b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,knowledge_available_at=known);b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V;b['production_controls']={'path':ct,'sha256':digest(controls)};save(target,b)
for kind in ['shared-assets','separate-assets']:
 for path in (F/('scenarios/tvm-realization-consolidated-'+OLD)/kind).glob('*-current.json'):save(S/kind/path.name,read(path))
save(E/'expansion-cases.json',{'bundle':V,'cases':cases,'case_count':len(cases),'profiles':len(profiles),'equivalences':equivalences,'preceding_progress_report':previous.relative_to(ROOT).as_posix(),'preceding_progress_report_sha256':sha(previous),'scope28_completed':False,'unified_candidate_accepted':False,'production_ready':False})
print(json.dumps({'prepared':V,'historical_cases':len(cases),'explicit_source_profiles':len(profiles),'scope28_completed':False}))
