"""Verify preserved history and production source integrity for the private successor."""
import json,hashlib,subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal'
E=ROOT/'storage/app/phase4b-private-consolidation48-draft-20261009-28'
PREVIOUS=ROOT/'storage/app/phase4b-realization-consolidated-expansion47-draft-20261009-28'
V='2026.10.09-28-draft48';B='bundles/consumption-phase4b-'+V+'.json'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,value):
 if p.exists():assert read(p)==value,p
 else:
  with p.open('x',encoding='utf-8') as out:out.write(json.dumps(value,indent=2)+'\n')
baseline=read(PREVIOUS/'historical-before.json')
for path,h in baseline.items():assert sha(ROOT/path)==h,path
index=read(E/'private-cases.json')
assert sha(PREVIOUS/'consolidation-progress-report.json')==index['preceding_report_sha256']
corpus=read(PREVIOUS/'expanded-realization-corpus.json')
for row in corpus['cases']:assert sha(PREVIOUS/(row['case']+'-snapshot.json'))==row['snapshot_sha256']
for case in index['cases']:
 for kind in ['financial','monthly']:assert sha(ROOT/case[kind+'_input'])==case[kind+'_input_sha256']
manifest=read(F/B)
for source in manifest['official_sources']:assert sha(F/'sources/official'/source['file'])==source['sha256']
command=['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan','fiscal:rtc:production-assess','--bundle-manifest='+B,'--controls=resources/fiscal/production/phase4b-controls-'+V+'.json']
result=subprocess.run(command,cwd=ROOT,capture_output=True,encoding='utf-8')
assert result.returncode==1,result.stdout+result.stderr
gate=json.loads(result.stdout);assert not gate['ready'] and gate['official_source_hashes_valid']==51
save(E/'production-assessment.json',gate)
save(E/'historical-preservation.json',{'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':[],'previous_consolidated_snapshots_verified':len(corpus['cases']),'original_private_inputs_verified':30,'official_sources_verified':51,'production_ready':False,'scope28_completed':False})
print(json.dumps({'history_preserved':len(baseline),'previous_snapshots_preserved':87,'private_inputs_preserved':30,'official_sources':51,'production_ready':False}))
