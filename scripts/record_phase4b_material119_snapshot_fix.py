"""Bind an additive snapshot-codec correction to the immutable pre-fix119 corpus."""
from pathlib import Path
from datetime import datetime,timezone
import hashlib
import json
import re

R=Path(__file__).resolve().parents[1]
E=Path(r'D:\ProjetoSaasExports\phase4b-material-input119-integration-20261020')
S=E/'snapshot-fidelity-fix-v1'
TARGET=E/'material119-snapshot-fidelity-validation.json'
def read(p):return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb')as f:return hashlib.file_digest(f,'sha256').hexdigest()
def execution(label,expected=0):
    path=E/label;receipt=read(path/'execution.json');assert receipt['exit_code']==expected,label
    for channel in ['stdout','stderr']:assert sha(path/(channel+'.log'))==receipt[channel+'_sha256'],label+channel
    return(path/'stdout.log').read_text(encoding='utf-8')
assert not TARGET.exists(),'Exclusive fix audit'
failure=read(S/'failure-observation.json')
assert failure['native_status']=='INVALID'and failure['changed_reevaluation_status']=='VALID'
writer='app/Modules/Fiscal/Infrastructure/Scenario/JsonFileWriter.php'
test='tests/Fiscal/Integration/JsonSnapshotFidelity119Test.php'
before=sha(S/'JsonFileWriter-before.php');after=sha(R/writer)
assert before==failure['writer_before_sha256']=='5ce2fd571a4619a9d535635c1c88a2143cdc32571c66a5f9057b0f0ab77fad3e'
assert after!=before
old=(S/'JsonFileWriter-before.php').read_text(encoding='utf-8')
new=(R/writer).read_text(encoding='utf-8')
assert old.replace('JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR','JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR')==new,'Only preserve-zero encoding flag may change the writer'
baseline=read(E/'material119-targeted-validation.json');runtime=baseline['runtime_hashes'].copy()
for path,h in runtime.items():assert sha(R/path)==h,path
runtime[writer]=after;assert len(runtime)==92
for directory,collector in [(E/'expanded-cli-v1','capture_phase4b_material119_expanded_cli.py'),(E/'cli-v1','capture_phase4b_material119_geography_cli.py')]:
    frozen=read(directory/'preflight.json')['input_hashes']
    current={str(p):sha(p)for folder in ['app','tests','resources/fiscal']for p in(R/folder).rglob('*')if p.is_file()}
    for p in [R/'scripts'/collector,R/'composer.lock',R/'phpunit.xml']:current[str(p)]=sha(p)
    assert set(current)-set(frozen)=={str(R/test)}and not set(frozen)-set(current),'Only the declared new fidelity test may be added'
    assert {p for p in frozen if current[p]!=frozen[p]}=={str(R/writer)},'Only writer changed after captured evidence'
    assert frozen[str(R/writer)]==before
independent=execution('expanded-independent-v1')
independent=json.loads(independent)
assert independent['audit_checks']==37833 and independent['independent_decimal_checks']==568
directed=execution('fidelity-directed-v2')
match=re.search(r'Tests:\s+28 passed\s+\((\d+) assertions\)',directed);assert match
assert '[OK] No errors'in execution('fidelity-phpstan-v1')
assert re.search(r'PASS.*597 files',execution('fidelity-pint-v1'))
bad_replay=json.loads(execution('fidelity-old-bad-replay-v1',expected=1))
assert bad_replay['replay_matches']is False,'Old corrupted snapshot must not be silently repaired'
assert sha(E/'contract-cli-v1/044-rtc-failed-snapshot.json')==failure['failing_snapshot_sha256']
report={'created_at':datetime.now(timezone.utc).isoformat(),'status':'MATERIAL119_SNAPSHOT_TYPE_FIDELITY_FIXED_TARGETED_NOT_FULL_ACCEPTANCE',
    'writer_path':writer,'writer_before_sha256':before,'writer_after_sha256':after,
    'writer_archive':'snapshot-fidelity-fix-v1/JsonFileWriter-before.php',
    'authorized_added_tests':{test:sha(R/test)},'targeted_tests':28,'targeted_assertions':int(match[1]),
    'runtime_hashes':runtime,'pre_fix_corpus_sha256':sha(E/'expanded-cli-v1/material119-expanded-cli-validation.json'),
    'pre_fix_independent_audit_checks':37833,'pre_fix_independent_audit_log_sha256':sha(E/'expanded-independent-v1/stdout.log'),
    'source124_business_inputs_changed':False,'old_failed_snapshot_repaired':False,
    'guard119_fiscal_semantics_changed':False,'new_contract_cli_recapture_required':True,
    'full_suite_complete':False,'phase4b_complete':False,'production_ready':False}
with TARGET.open('x',encoding='utf-8')as f:json.dump(report,f,ensure_ascii=False,indent=2)
print(json.dumps({k:report[k]for k in ['status','writer_before_sha256','writer_after_sha256','targeted_tests','targeted_assertions']}),flush=True)
