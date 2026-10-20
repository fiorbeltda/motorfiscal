"""Own54 temporal policy for synthetic public-offer return composition; preserve52 selection."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
from dere_daily_memory_oracle import digest

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'
V = '2026.10.13-28-draft54'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def save(path, value):
    if path.exists():
        assert read(path) == value, path
    else:
        with path.open('x', encoding='utf-8') as stream:
            json.dump(value, stream, ensure_ascii=False, indent=2)
            stream.write('\n')

bundle = read(F / 'bundles/consumption-phase4b-2026.10.09-28-draft52.json')
catalog = read(F / bundle['resource_paths']['rtc_code_catalog'])
target = F / ('bundles/consumption-phase4b-' + V + '.json')
known = read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
policy = {'contract': 'DERE-PUBLIC-OFFER-BCN-LOCAL-SETTLEMENT-1.3.0-54', 'status': 'SANDBOX_CANDIDATE',
    'published_at': known, 'valid_from': '2026-01-01', 'mode': 'EXPLICIT_SYNTHETIC_LOCAL_PROPOSAL_ONLY',
    'composition': 'INITIAL_SYNTHETIC_D2101_D9121_CODBC1010', 'rounding': 'DERE_HALF_EVEN_8_2',
    'return_processing_known_at_required': True, 'production_ready': False}
for field, filename in [('schema_sha256', 'dere-observed-20261006-cgibs-xsd-v1-3-0.zip'),
    ('mod_sha256', 'dere-observed-20261005-mod.pdf'), ('annex_i_sha256', 'dere-observed-20261005-annex-i.pdf'),
    ('rounding_sha256', 'dere-observed-20261005-validation.pdf'), ('events_sha256', 'dere-observed-20261005-events.pdf')]:
    policy[field] = hashlib.sha256((F / 'sources/official' / filename).read_bytes()).hexdigest()
policy['source_hash'] = digest(policy)
catalog['version'] = V
catalog['dere_public_offer_negative_base_local_settlement'] = policy
catalog_path = 'catalogs/rtc-codes-' + V + '.json'
save(F / catalog_path, catalog)
controls = read(F / bundle['production_controls']['path'])
controls['version'] = V
controls_path = 'production/phase4b-controls-' + V + '.json'
save(F / controls_path, controls)
bundle.update(predecessor_bundle_id=bundle['bundle_id'], bundle_id='CONSUMPTION-PHASE4B-' + V,
    version=V, knowledge_available_at=known)
bundle['resource_paths']['rtc_code_catalog'] = catalog_path
bundle['content_hashes']['rtc_code_catalog'] = digest(catalog)
bundle['catalog_versions']['rtc_codes'] = V
bundle['production_controls'] = {'path': controls_path, 'sha256': digest(controls)}
save(target, bundle)
scenario = read(F / 'scenarios/dere-variable-yield-2026.10.06-27/constant-financial.json')
scenario.update(scenario_id='SYNTHETIC-D9121-54-CONSTANT', knowledge_at=known, rule_bundle_id=bundle['bundle_id'])
scenario['catalog_versions'] = bundle['catalog_versions']
save(E / 'financial-input.json', scenario)
save(E / 'public-policy-preparation.json', {'version': V, 'policy': policy, 'predecessor52_policy_preserved': True,
    'current_snapshot_captured': False, 'native_command_integrated': False, 'production_ready': False})
print('PASS: own54 bundle, public-return policy and financial input;52 policy unchanged')
