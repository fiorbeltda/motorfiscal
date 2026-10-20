"""Correct two explicit opt-in-family labels,not engine outcomes or old108 controls."""
from pathlib import Path
from datetime import datetime,timezone
import hashlib
import json

R=Path(__file__).resolve().parents[1];E=Path(r'D:\ProjetoSaasExports\phase4b-price112-integration-20261019')
source=R/'tests/Fixtures/price112-scenario-expectations.json'
data=json.loads(source.read_text());changed=[]
mapping={'RTC_EXPORT_DATED108_PARTIAL_EXPORT_ROUTE_REQUIRED':'RTC_EXPORT_PRICE112_PARTIAL_EXPORT_ROUTE_REQUIRED',
         'RTC_EXPORT_DATED108_SEPARATE_TEMPORAL_CONTEXT_REQUIRED':'RTC_EXPORT_PRICE112_SEPARATE_TEMPORAL_CONTEXT_REQUIRED'}
for row in data['cases']:
    if row['reason']in mapping:
        changed.append({'name':row['name'],'previous':row['reason'],'current':mapping[row['reason']]})
        row['reason']=mapping[row['reason']]
assert len(changed)==2
target=R/'tests/Fixtures/price112-scenario-expectations-v2.json'
with target.open('x',encoding='utf-8')as f:json.dump(data,f,indent=2,ensure_ascii=False)
with(E/'expected-guard-label-review-v2.json').open('x',encoding='utf-8')as f:
    json.dump({'created_at':datetime.now(timezone.utc).isoformat(),'changed':changed,
        'reason':'112 requests its own route/temporal guards;two copied108 labels were wrong for this opt-in',
        'original_manifest_sha256':hashlib.sha256(source.read_bytes()).hexdigest(),
        'new_manifest_sha256':hashlib.sha256(target.read_bytes()).hexdigest(),
        'scenario_inputs_changed':False,'runtime_changed':False,'captured108_results_rewritten':False},f,indent=2)
print('PASS:two explicit112 opt-in guard labels corrected in new manifest;original evidence preserved',flush=True)
