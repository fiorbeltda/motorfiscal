"""New119 synthetic transport seed; immutable original108/112 monetary sources remain unchanged."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json

R = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-material-input119-integration-20261020')
source = E.parent/'phase4b-price112-integration-20261019/upstream112-source-seed-v1.json'
def sha(p):
    with p.open('rb') as f:
        return hashlib.file_digest(f, 'sha256').hexdigest()
assert sha(source) == 'aaf5e8d8df2c0f5fbf6a630638d0829e0b611423fe8260d467aff9ae84e1c5d2'
seed = json.loads(source.read_text(encoding='utf-8-sig'))
assert len(seed['cases']) == 50
for row in seed['cases']:
    assert sha(R/row['path']) == row['sha256']
    assert sha(Path(row['source_rtc_snapshot_path'])) == row['source_rtc_snapshot_sha256']
seed['created_at'] = datetime.now(timezone.utc).isoformat()
seed['source_seed_sha256'] = sha(source)
seed['target_bundle'] = 'bundles/consumption-phase4b-2026.10.20-119-draft1.json'
seed['synthetic_knowledge_at'] = '2026-10-20T14:22:21.000000+00:00'
seed['reissued_for'] = 'MATERIAL119_SYNTHETIC_PRICE_TRANSPORT_NOT_LEGAL_APPROVAL'
target = E/'material119-upstream-source-seed-v1.json'
with target.open('x', encoding='utf-8') as f:
    json.dump(seed,f,ensure_ascii=False,indent=2)
print(json.dumps({'cases':50,'new_seed_sha256':sha(target),'material_records_rewritten':False,'production_ready':False}),flush=True)
