"""Reissue only bundle/knowledge metadata of the immutable synthetic upstream seed."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json

R = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-geographic116-integration-20261020')
source = E.parent/'phase4b-price112-integration-20261019/upstream112-source-seed-v1.json'
def sha(path):
    with path.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
assert sha(source) == 'aaf5e8d8df2c0f5fbf6a630638d0829e0b611423fe8260d467aff9ae84e1c5d2'
seed = json.loads(source.read_text(encoding='utf-8-sig'))
assert len(seed['cases']) == 50
for row in seed['cases']:
    assert sha(R/row['path']) == row['sha256']
    assert sha(Path(row['source_rtc_snapshot_path'])) == row['source_rtc_snapshot_sha256']
seed['created_at'] = datetime.now(timezone.utc).isoformat()
seed['source_seed_sha256'] = sha(source)
seed['target_bundle'] = 'bundles/consumption-phase4b-2026.10.20-116-draft2.json'
seed['synthetic_knowledge_at'] = '2026-10-20T12:06:00.123456+00:00'
seed['reissued_for'] = 'GEOGRAPHIC116_SYNTHETIC_PRICE_TRANSPORT_NOT_LEGAL_APPROVAL'
target = E/'geographic116-upstream-source-seed-v1.json'
with target.open('x', encoding='utf-8') as stream: json.dump(seed, stream, ensure_ascii=False, indent=2)
print(json.dumps({'cases':50, 'source_seed_sha256':sha(source), 'new_seed_sha256':sha(target),
    'material_records_rewritten':False, 'production_ready':False}), flush=True)
