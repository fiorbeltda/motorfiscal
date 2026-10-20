"""Own temporal knowledge for local current-base -> BCN -> tax proposal composition."""
from pathlib import Path
from datetime import datetime, timezone
import json
import hashlib
from dere_daily_memory_oracle import digest

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = ROOT / 'storage/app/phase4b-negative-base52-settlement-draft-20261009'
V = '2026.10.09-28-draft52'
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


bundle = read(F / 'bundles/consumption-phase4b-2026.10.09-28-draft50.json')
catalog = read(F / bundle['resource_paths']['rtc_code_catalog'])
target = F / ('bundles/consumption-phase4b-' + V + '.json')
known = read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
policy = {'contract': 'DERE-BCN-LOCAL-SETTLEMENT-1.3.0-52', 'status': 'SANDBOX_CANDIDATE',
          'published_at': known, 'valid_from': '2026-01-01', 'mode': 'EXPLICIT_SYNTHETIC_LOCAL_PROPOSAL_ONLY',
          'rounding': 'DERE_HALF_EVEN_8_2', 'production_ready': False}
for field, filename in [('schema_sha256', 'dere-observed-20261006-cgibs-xsd-v1-3-0.zip'),
                        ('mod_sha256', 'dere-observed-20261005-mod.pdf'),
                        ('annex_i_sha256', 'dere-observed-20261005-annex-i.pdf'),
                        ('rounding_sha256', 'dere-observed-20261005-validation.pdf')]:
    policy[field] = hashlib.sha256((F / 'sources/official' / filename).read_bytes()).hexdigest()
policy['source_hash'] = digest(policy)
catalog['version'] = V
catalog['dere_negative_base_local_settlement'] = policy
catalog_path = 'catalogs/rtc-codes-' + V + '.json'
save(F / catalog_path, catalog)
controls = read(F / bundle['production_controls']['path'])
controls['version'] = V
control_path = 'production/phase4b-controls-' + V + '.json'
save(F / control_path, controls)
bundle.update(predecessor_bundle_id=bundle['bundle_id'], bundle_id='CONSUMPTION-PHASE4B-' + V,
              version=V, knowledge_available_at=known)
bundle['resource_paths']['rtc_code_catalog'] = catalog_path
bundle['content_hashes']['rtc_code_catalog'] = digest(catalog)
bundle['catalog_versions']['rtc_codes'] = V
bundle['production_controls'] = {'path': control_path, 'sha256': digest(controls)}
save(target, bundle)
save(E / 'preparation.json', {'bundle': V, 'policy': policy,
                            'scope': 'LOCAL_RECONCILED_COMPOSITION_BCN_TAX_PROPOSAL',
                            'native_monthly_command_integration_complete': False, 'production_ready': False})
print('PASS: own52 bundle/catalog/controls and source-bound local settlement policy; predecessors unchanged')
