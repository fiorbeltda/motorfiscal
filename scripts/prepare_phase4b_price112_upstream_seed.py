"""Preserve all58 captured108 controls and prepare the50 missing112 transport cases."""
from pathlib import Path
from datetime import datetime,timezone
import hashlib
import json

R=Path(__file__).resolve().parents[1];BASE=Path(r'D:\ProjetoSaasExports')
E=BASE/'phase4b-price112-integration-20261019';C=BASE/'phase4b-dated108-integration-20261019/cli-v1'
def read(p):return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb')as f:return hashlib.file_digest(f,'sha256').hexdigest()
initial=E/'price112-initial-directed-validation.json'
assert sha(initial)=='d991e06414407b5fcda7806252ab9ccad5db15bf884ab5853485cbff4dd9ef52'
proof=read(initial)
for p,h in proof['runtime_hashes'].items():assert sha(R/p)==h,p
accepted=read(R/'storage/app/phase4b-dated108-development-20261019/dated108-development-validation.json')
assert sha(C/'dated108-cli-validation.json')==accepted['corpus_sha256']
captured={row['path']:row for row in read(C/'dated108-cli-validation.json')['cases']}
cases=[]
for row in proof['remaining_original108_scenarios_to_expand']:
    source=R/row['path'];assert sha(source)==row['sha256']
    old=captured[row['path']];snap=C/old['rtc_snapshot'];assert sha(snap)==old['rtc_snapshot_sha256']
    stages={s['stage']:s for s in read(snap)['result']['trace']}
    cases.append({**row,'source_rtc_snapshot_path':str(snap),'source_rtc_snapshot_sha256':sha(snap),
        'captured108_nominal_stage':stages.get('PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION'),
        'captured108_fx_stage':stages.get('PARTIAL_EXPORT_FX_TRANSACTION')})
assert len(cases)==50
target=E/'upstream112-source-seed-v1.json'
with target.open('x',encoding='utf-8')as f:json.dump({'created_at':datetime.now(timezone.utc).isoformat(),
    'initial_directed_validation_sha256':sha(initial),'original108_cli_receipt_sha256':sha(C/'dated108-cli-validation.json'),
    'source_bundle':'bundles/consumption-phase4b-2026.10.19-108-draft3.json',
    'target_bundle':'bundles/consumption-phase4b-2026.10.19-112-draft2.json',
    'synthetic_knowledge_at':'2026-10-19T19:00:00.123456+00:00','cases':cases,
    'full58source_inventory':proof['complete_source108_inventory'],'runtime_modified':False,'production_ready':False},f,indent=2,ensure_ascii=False)
print(json.dumps({'prepared':len(cases),'successful_usd_dated_sources':sum(c['rtc_gross_currency']=='USD'and c['dated_status']=='LOCAL_DATED_DPS_IDENTITY_PAIR_BOUND'for c in cases),
                  'seed_sha256':sha(target)}),flush=True)
