"""Create an opt-in local foreign-buyer candidate bundle after directed gates."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = ROOT / 'storage/app/phase4b-foreign-acquirer76-preparation-20261015'
Q = ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014'
B = Path(r'D:\ProjetoSaasExports\phase4b-document75-bcb-currency-research-20261014')
V = '2026.10.15-76-draft1'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def canonical(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def save(path, value):
    with path.open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)


for label in ['scope76-foreign-candidate-directed-v2', 'scope76-foreign-candidate-type-v2',
              'scope76-foreign-candidate-style-v2', 'scope76-foreign-readiness-directed']:
    record = read(Q / (label + '.json'))
    assert record['exit_code'] == 0 and sha(Q / (label + '.log')) == record['log_sha256'], label
assert (E / 'before-integration-archive.json').exists()
currency = read(B / 'currency-code-research-validation.json')
source = currency['source']
assert sha(B / 'bcb-currency-table.html') == source['sha256']
assert currency['historical_competence_validity_proven'] is False
source_name = 'bcb-currency-codes-observed-20261014.html'
code_path = 'catalogs/rtc-codes-' + V + '.json'
control_path = 'production/phase4b-controls-' + V + '.json'
manifest_path = 'bundles/consumption-phase4b-' + V + '.json'
targets = [F / code_path, F / control_path, F / manifest_path,
           F / 'sources/official' / source_name, E / 'foreign-candidate-bundle-installation.json']
assert not any(path.exists() for path in targets), 'Exclusive installation target exists'
bundle = read(F / 'bundles/consumption-phase4b-2026.10.14-74-draft1.json')
codes = read(F / bundle['resource_paths']['rtc_code_catalog'])
controls = read(F / bundle['production_controls']['path'])
codes['version'] = V
codes['local_xml_projection']['contract_version'] = 'DPS-LOCAL-FOREIGN-ACQUIRER-CANDIDATE-2026.10.15-76'
codes['foreign_acquirer_candidate'] = {'scope': 'LOCAL_STRUCTURAL_CANDIDATE_ONLY',
    'currency_competence_validity_proven': False,
    'currency_code_source_sha256': source['sha256'], 'currency_code_observed_at': source['observed_at'],
    'observed_currency_codes': currency['current_symbol_mapping'],
    'numeric_code_observation_does_not_qualify_currency_or_fx': True,
    'scope_note': 'Foreign buyer and domestic recipient only; no ISS export or mixed export document pair claim.'}
controls['version'] = V
bundle.update(predecessor_bundle_id=bundle['bundle_id'], bundle_id='CONSUMPTION-PHASE4B-' + V,
    version=V, knowledge_available_at=datetime.now(timezone.utc).replace(microsecond=0).isoformat())
bundle['resource_paths']['rtc_code_catalog'] = code_path
bundle['catalog_versions']['rtc_codes'] = V
bundle['content_hashes']['rtc_code_catalog'] = canonical(codes)
bundle['production_controls'] = {'path': control_path, 'sha256': canonical(controls)}
bundle['official_sources'].append({'id': 'BCB_CURRENCY_CODE_TABLE_OBSERVED_20261014',
    'url': source['url'], 'file': source_name, 'sha256': source['sha256'],
    'version': 'OBSERVED-2026.10.14-NOT-HISTORICALLY-QUALIFIED', 'observed_at': source['observed_at'],
    'published_on': None, 'proven_effective_scope': 'Numeric code mapping observation only;not historical currency validity or fiscal FX origin.'})
with (F / 'sources/official' / source_name).open('xb') as stream:
    stream.write((B / 'bcb-currency-table.html').read_bytes())
assert sha(F / 'sources/official' / source_name) == source['sha256']
save(F / code_path, codes)
save(F / control_path, controls)
save(F / manifest_path, bundle)
save(E / 'foreign-candidate-bundle-installation.json', {'status': 'LOCAL_FOREIGN_ACQUIRER_CANDIDATE76_BUNDLE_CREATED',
    'bundle': manifest_path, 'bundle_sha256': sha(F / manifest_path),
    'currency_source_sha256': source['sha256'], 'document_obligation_complete': False,
    'nt009_migration_complete': False, 'production_ready': False})
print('PASS:exclusive local foreign-buyer bundle created;no currency validity or fiscal export approval')
