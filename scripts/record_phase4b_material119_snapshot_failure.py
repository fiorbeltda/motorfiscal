"""Preserve a demonstrated119 type/replay failure; never repair old snapshots."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json

R=Path(__file__).resolve().parents[1]
E=Path(r'D:\ProjetoSaasExports\phase4b-material-input119-integration-20261020')
S=E/'snapshot-fidelity-fix-v1'
C=E/'contract-cli-v1'
def read(p):return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb')as f:return hashlib.file_digest(f,'sha256').hexdigest()
probe=read(E/'snapshot-fidelity-probe-v1/stdout.log')
assert read(E/'snapshot-fidelity-probe-v1/execution.json')['exit_code']==0
assert probe['input_amount_type']=='float'and probe['snapshot_amount_type']=='int'
assert probe['original_native']['status']=='INVALID'
assert 'RTC_ADJUSTMENT119_AMOUNT_NOT_EXACT'in probe['original_native']['reason_codes']
assert probe['normalized_snapshot_reevaluation']['status']=='VALID'and probe['exact_replay']is False
assert sha(S/'JsonFileWriter-before.php')==sha(R/'app/Modules/Fiscal/Infrastructure/Scenario/JsonFileWriter.php')=='5ce2fd571a4619a9d535635c1c88a2143cdc32571c66a5f9057b0f0ab77fad3e'
assert sha(C/'collector.py')==read(C/'process-start.json')['collector_sha256']
receipts=[read(p)for p in C.glob('*-execution.json')]
assert len(receipts)==178
failed=[r for r in receipts if r['exit_code']!=r['expected_exit_code']]
assert len(failed)==1 and failed[0]['exit_code']==1
report={'created_at':datetime.now(timezone.utc).isoformat(),'status':'INPUT119_INTEGRAL_FLOAT_SNAPSHOT_TYPE_LOSS_REPRODUCED_NOT_ACCEPTED',
    'native_decisions_completed':89,'successful_exact_replays':88,'replay_mismatches':1,
    'source_json_token':'1.0','preserved_source_type':'float','serialized_snapshot_type':'int',
    'native_status':'INVALID','changed_reevaluation_status':'VALID',
    'writer_before_sha256':sha(S/'JsonFileWriter-before.php'),'archived_collector_sha256':sha(C/'collector.py'),
    'failing_scenario_sha256':sha(C/'044-scenario.json'),'failing_snapshot_sha256':sha(C/'044-rtc-failed-snapshot.json'),
    'native_result_sha256':sha(C/'044-rtc.stdout'),'replay_result_sha256':sha(C/'044-rtc-replay.stdout'),
    'independent_probe_sha256':sha(E/'snapshot-fidelity-probe-v1/stdout.log'),
    'failed_attempt_preserved':True,'runtime_changed':False,'full_suite_started':False,
    'fix_required':'Preserve float/integer distinction in newly written immutable JSON snapshots;add native/artisan and codec regressions. Do not normalize the invalid caller value or edit failed snapshots.',
    'phase4b_complete':False,'production_ready':False}
with(S/'failure-observation.json').open('x',encoding='utf-8')as f:json.dump(report,f,ensure_ascii=False,indent=2)
print(json.dumps(report),flush=True)
