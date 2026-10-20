"""Own knowledge for source-bound local D1199 BCN options; preserve all predecessor resources."""
from pathlib import Path
from datetime import datetime, timezone
import json
import hashlib
from dere_daily_memory_oracle import digest

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
V = '2026.10.09-28-draft50'
E = ROOT / 'storage/app/phase4b-negative-base50-closing-draft-20261009'
E.mkdir(exist_ok=True)


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def save(path, value):
    path.parent.mkdir(exist_ok=True)
    if path.exists():
        assert read(path) == value, path
    else:
        with path.open('x', encoding='utf-8') as stream:
            json.dump(value, stream, ensure_ascii=False, indent=2)
            stream.write('\n')


bundle = read(F / 'bundles/consumption-phase4b-2026.10.09-28-draft48.json')
catalog = read(F / bundle['resource_paths']['rtc_code_catalog'])
target = F / ('bundles/consumption-phase4b-' + V + '.json')
known = read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
policy = {'contract': 'DERE-BCN-CLOSING-OPTIONS-LOCAL-1.3.0-50', 'status': 'SANDBOX_CANDIDATE',
          'published_at': known, 'valid_from': '2026-01-01',
          'schema_sha256': '3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd',
          'annex_i_sha256': '7296b225e15fb4b395a41a9413ada1190cc2229d64334bf78d7b69f0fb8019f9',
          'mode': 'EXPLICIT_SYNTHETIC_LOCAL_REQUEST_ONLY', 'production_ready': False}
policy['mod_sha256'] = hashlib.sha256((F / 'sources/official/dere-observed-20261005-mod.pdf').read_bytes()).hexdigest()
policy['source_hash'] = digest(policy)
catalog['version'] = V
catalog['dere_negative_base_closing_options_projection'] = policy
catalogPath = 'catalogs/rtc-codes-' + V + '.json'
save(F / catalogPath, catalog)
controls = read(F / bundle['production_controls']['path'])
controls['version'] = V
controlPath = 'production/phase4b-controls-' + V + '.json'
save(F / controlPath, controls)
bundle.update(predecessor_bundle_id=bundle['bundle_id'], bundle_id='CONSUMPTION-PHASE4B-' + V,
              version=V, knowledge_available_at=known)
bundle['resource_paths']['rtc_code_catalog'] = catalogPath
bundle['content_hashes']['rtc_code_catalog'] = digest(catalog)
bundle['catalog_versions']['rtc_codes'] = V
bundle['production_controls'] = {'path': controlPath, 'sha256': digest(controls)}
save(target, bundle)
save(E / 'preparation.json', {'bundle': V, 'policy': policy, 'scope': 'LOCAL_D1199_REQUEST_DECLARATION',
                            'gross_debit_recalculation_integrated': False, 'production_ready': False})
print('PASS: own50 bundle/catalog/controls and local request policy; prior knowledge unchanged')
