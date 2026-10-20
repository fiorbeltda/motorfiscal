"""Accept the private/common corpus locally while retaining all phase-wide open gates."""
import json,hashlib,re
from pathlib import Path
from decimal import Decimal,ROUND_HALF_EVEN
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal'
E=ROOT/'storage/app/phase4b-private-consolidation48-draft-20261009-28'
P=ROOT/'storage/app/phase4b-realization-consolidated-expansion47-draft-20261009-28'
V='2026.10.09-28-draft48'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,value):
 if p.exists():assert read(p)==value,p
 else:
  with p.open('x',encoding='utf-8') as out:out.write(json.dumps(value,ensure_ascii=False,indent=2)+'\n')
names=['private-capture','private-native-corpus-check','source-policy-guards','private-book-guards','regression87-recaptured','regression-native-corpus-check','full-suite','phpstan','pint-all','reproduce','preservation','candidate27','candidate26','candidate25']
quality={name:read(E/(name+'.json')) for name in names}
for name,value in quality.items():assert value['exit_code']==0 and value['log_sha256']==sha(E/(name+'.log'))
plain=lambda name:re.sub(r'\x1b\[[0-9;]*m','',(E/(name+'.log')).read_text(encoding='utf-8'))
assert re.search(r'1836 passed.*?\(44012 assertions\)',plain('full-suite'))
assert re.search(r'87 passed.*?\(972 assertions\)',plain('regression87-recaptured'))
for item in read(E/'regression-positive-validation-report.json')['implementation_hashes']:assert sha(ROOT/item['path'])==item['sha256']
manifest_path=F/('bundles/consumption-phase4b-'+V+'.json');manifest=read(manifest_path)
catalog=read(F/manifest['resource_paths']['rtc_code_catalog'])
assert digest(catalog)==manifest['content_hashes']['rtc_code_catalog']
profiles=catalog['financial_operation']['base']['securities_realization']['analysis']['history_policy']['source_policy_selection']['profiles']
assert len(profiles)==14
index=read(E/'private-cases.json');assert sha(P/'consolidation-progress-report.json')==index['preceding_report_sha256']
for choice in index['private_policy_choices']:
 assert sha(ROOT/choice['catalog_path'])==choice['catalog_sha256']
 assert profiles[choice['selection_key']]['policy_hash']==choice['policy_hash']
for row in read(P/'input-compression-preservation.json')['files']:assert sha(ROOT/row['path'])==row['sha256']
for metadata in index['cases']:
 for kind in ['financial','monthly']:assert sha(ROOT/metadata[kind+'_input'])==metadata[kind+'_input_sha256']
baseline=read(P/'historical-before.json')
for path,h in baseline.items():assert sha(ROOT/path)==h,path
for source in manifest['official_sources']:assert sha(F/'sources/official'/source['file'])==source['sha256']
gate=read(E/'production-assessment.json');assert not gate['ready'] and gate['official_source_hashes_valid']==51
cases=[];xml_count=0
for name,expected,folder in [('private-native-corpus.json',15,'tvm-private-consolidated-'),('regression-native-corpus.json',87,'tvm-realization-consolidated-')]:
 corpus=read(E/name);assert len(corpus['cases'])==expected and corpus['financial_replays']==expected
 for row in corpus['cases']:
  case=row['case'];snapshot_path=E/(case+'-snapshot.json');assert sha(snapshot_path)==row['snapshot_sha256']
  financial=F/('scenarios/'+folder+V)/(case+'-financial.json');monthly=financial.with_name(case+'-month.json')
  assert sha(financial)==row['financial_input_sha256'] and sha(monthly)==row['monthly_input_sha256']
  snapshot=read(snapshot_path);assert snapshot['input']==read(financial)
  assert snapshot['knowledge']['content_hashes']['bundle_manifest']==digest(manifest)
  replay=read(E/(case+'-replay.json'));assert replay['replay_matches'] and replay['original_hash']==row['decision_hash']
  report=read(E/(case+'-monthly-report.json'))
  assert report['snapshot_hash']==digest(snapshot) and report['decision_hash']==row['decision_hash'] and report['monthly_input_hash']==digest(read(monthly))
  assert report['projection']['status']=='PROJECTED' and not report['production_ready']
  assert len(report['event_outputs'])==row['xml_count']
  for event,path in report['event_outputs'].items():
   validation=report['event_validations'][event]
   assert sha(ROOT/path)==validation['xml_sha256'] and validation['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and len(validation['errors'])==1
   assert validation['schema_sha256']=='3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd'
   xml_count+=1
  for key,rate in [('ibs_nominal','.001'),('cbs_nominal','.009')]:assert Decimal(row[key])==(Decimal(row['base'])*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
  cases.append(row)
assert len(cases)==len({row['case'] for row in cases})==102 and xml_count==595
for failure in ['regression87','regression87-rebound']:
 value=read(E/(failure+'.json'));assert value['exit_code']==1 and sha(E/(failure+'.log'))==value['log_sha256']
save(E/'final-audit-implementation-hashes.json',[{'path':Path(__file__).relative_to(ROOT).as_posix(),'sha256':sha(Path(__file__))}])
save(E/'consolidation-progress-report.json',{'status':'PRIVATE_AND_HISTORICAL_COMMON_KNOWLEDGE_CORPUS_VALIDATED','bundle':V,'quality':quality,'full_suite_tests':1836,'full_suite_assertions':44012,'corpus':{'cases':cases,'case_count':102,'financial_replays':102,'xml_bodies':595},'separate_ibs_cbs_verified':True,'source_profiles':14,'official_sources_verified':51,'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':[],'private_and_historical_corpus_validated':True,'scope28_completed':False,'phase4b_completed':False,'unified_candidate_accepted':False,'production_ready':False,'remaining':['Complete scope-level negative requirement audit','Prove public new return at sale document bridge','Complete integral scope28 and phase4b.8 acceptance audit']})
print(json.dumps({'common_cases':102,'replays':102,'xml_bodies':595,'full_suite_tests':1836,'full_suite_assertions':44012,'scope28_completed':False,'production_ready':False}))
