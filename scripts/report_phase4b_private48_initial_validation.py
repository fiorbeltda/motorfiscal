"""Freeze the proven private subset without claiming common-corpus acceptance."""
import json,hashlib,re
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-private-consolidation48-draft-20261009-28'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
quality={}
for name in ['private-capture','private-native-corpus-check','source-policy-guards','phpstan','reproduce','preservation']:
 value=read(E/(name+'.json'))
 assert value['exit_code']==0 and value['log_sha256']==sha(E/(name+'.log'))
 quality[name]=value
plain=lambda name:re.sub(r'\x1b\[[0-9;]*m','',(E/(name+'.log')).read_text(encoding='utf-8'))
assert re.search(r'30 passed.*?\(372 assertions\)',plain('private-capture'))
assert re.search(r'15 passed.*?\(45 assertions\)',plain('source-policy-guards'))
corpus=read(E/'private-native-corpus.json');assert corpus['financial_replays']==15 and corpus['xml_bodies']==75
for row in corpus['cases']:
 assert sha(E/(row['case']+'-snapshot.json'))==row['snapshot_sha256']
 replay=read(E/(row['case']+'-replay.json'))
 assert replay['replay_matches'] and replay['original_hash']==row['decision_hash']
 report=read(E/(row['case']+'-monthly-report.json'))
 assert report['projection']['status']=='PROJECTED' and not report['production_ready']
 assert len(report['event_outputs'])==row['xml_count']
 for event,path in report['event_outputs'].items():assert sha(ROOT/path)==report['event_validations'][event]['xml_sha256']
preservation=read(E/'historical-preservation.json');gate=read(E/'production-assessment.json')
assert not gate['ready'] and gate['official_source_hashes_valid']==51 and preservation['historical_artifacts_changed']==[]
report={'status':'PRIVATE_INITIAL_VALIDATION_ONLY','bundle':'2026.10.09-28-draft48','quality':quality,'private_cases':15,'private_replays':15,'private_xml_bodies':75,'source_profiles':14,'historical_preservation':preservation,'private_initial_validation_proven':True,'regression87_accepted':False,'common_native_corpus_accepted':False,'full_quality_accepted':False,'scope28_completed':False,'phase4b_completed':False,'production_ready':False,'remaining':['Finish common-knowledge regression of 87 cases','Capture/replay/project the common corpus under knowledge48','Complete expanded negative corpus and full quality','Prove public new return at sale document bridge','Audit original scope28 and phase4b.8 acceptance']}
target=E/'private-initial-validation-report.json'
if target.exists():assert read(target)==report
else:
 with target.open('x',encoding='utf-8') as out:out.write(json.dumps(report,indent=2)+'\n')
print(json.dumps({'private_cases':15,'replays':15,'xml_bodies':75,'common_corpus_accepted':False,'scope28_completed':False}))
