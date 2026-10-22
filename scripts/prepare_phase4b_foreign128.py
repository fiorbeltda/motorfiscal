"""Fork128 to bind the existing foreign party/COMEX contract to observed XSD127."""
import argparse
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
VERSION = '2026.10.22-128-draft1'
CONTRACT = 'DPS-LOCAL-FOREIGN-ACQUIRER-2026.10.22-128'
parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))
def canonical(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
def write(path, value):
    with path.open('x', encoding='utf-8', newline='\n') as handle:
        handle.write(json.dumps(value, ensure_ascii=False, indent=2) + '\n')
catalog = read(F / 'catalogs/rtc-codes-2026.10.22-127-draft1.json')
bundle = read(F / 'bundles/consumption-phase4b-2026.10.22-127-draft1.json')
controls = read(F / bundle['production_controls']['path'])
policy = {'contract': CONTRACT, 'scope': 'FOREIGN_ACQUIRER_DOMESTIC_RECIPIENT_LOCAL_STRUCTURAL_ONLY',
    'schema_source_sha256': catalog['dps_xsd']['source_sha256'],
    'schema_policy127_sha256': canonical(catalog['dps_layout_policy127']),
    'foreign_candidate_sha256': canonical(catalog['foreign_acquirer_candidate']),
    'available_at': catalog['dps_layout_policy127']['source_observed_at'],
    'remote_deployment_verified': False, 'document_obligation_complete': False}
catalog['dps_foreign_policy128'] = policy
catalog['version'] = VERSION
catalog['local_xml_projection']['contract_version'] = CONTRACT
catalog_path = F / f'catalogs/rtc-codes-{VERSION}.json'
write(catalog_path, catalog)
controls['version'] = VERSION
controls_path = F / f'production/phase4b-controls-{VERSION}.json'
write(controls_path, controls)
bundle.update(predecessor_bundle_id=bundle['bundle_id'], bundle_id='CONSUMPTION-PHASE4B-'+VERSION, version=VERSION,
    notice='Foreign acquirer/domestic recipient local XML128 on observed XSD20/10;not export/import qualification,authentication or production approval.')
bundle['catalog_versions']['rtc_codes'] = VERSION
bundle['resource_paths']['rtc_code_catalog'] = 'catalogs/'+catalog_path.name
bundle['content_hashes']['rtc_code_catalog'] = canonical(catalog)
bundle['production_controls'] = {'path': 'production/'+controls_path.name, 'sha256': canonical(controls)}
write(F / f'bundles/consumption-phase4b-{VERSION}.json', bundle)
write(args.output, {'policy': policy, 'policy_sha256': canonical(policy),
    'source_bytes_reused_unchanged': True, 'official_sources': len(bundle['official_sources'])})
print(json.dumps({'policy_sha256': canonical(policy), 'foreign_candidate_sha256': policy['foreign_candidate_sha256']}))
