"""Bind the D1199 local projection policy without changing predecessor resources."""
import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / 'resources/fiscal'
VERSION = '2026.10.02-17'


def read(path):
    return json.loads((ROOT / path).read_text(encoding='utf-8'))


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def write(path, value):
    target = ROOT / path
    if target.exists():
        if read(path) != value:
            raise RuntimeError(f'Refusing to overwrite {target}')
        return
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')


previous = read('bundles/consumption-phase4b-2026.10.02-16.json')
target = f'bundles/consumption-phase4b-{VERSION}.json'
available = read(target)['knowledge_available_at'] if (ROOT / target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f'CONSUMPTION-PHASE4B-{VERSION}', version=VERSION,
              predecessor_bundle_id=previous['bundle_id'], knowledge_available_at=available)
codes = read(previous['resource_paths']['rtc_code_catalog'])
codes['version'] = VERSION
codes['dere_monthly_closing_projection'] = {
    'strategy': 'DERE_MONTHLY_CLOSING_1_2_0',
    'status': 'ACTIVE', 'published_at': available,
    'source_hash': 'b52cb2cf96b176d303d64aeea0bab16e7d35bcc739478f6fc8a26b0512415feb',
    'contract': 'DERE-MONTHLY-CLOSING-LOCAL-2026.10.02-1',
    'scope': 'LOCAL_UNSIGNED_D1199_NOT_FULL_MONTHLY_DECLARATION',
    'xsd': copy.deepcopy(codes['dere_local_projection']['xsd']),
}
path = f'catalogs/rtc-codes-{VERSION}.json'
write(path, codes)
bundle['resource_paths']['rtc_code_catalog'] = path
bundle['content_hashes']['rtc_code_catalog'] = digest(codes)
bundle['catalog_versions']['rtc_codes'] = VERSION
controls = read(previous['production_controls']['path'])
controls['version'] = VERSION
for control in controls['controls']:
    if control['id'] == '4B.7.DOCUMENT':
        control['gap'] += ' D1199 local com replay e vínculo ao livro qualificado; D1101 e D1121 reconstruídos no mesmo período; cadastro e PGCC referenciais, D1106/D2101 e reabertura permanecem pendentes.'
path = f'production/phase4b-controls-{VERSION}.json'
write(path, controls)
bundle['production_controls'] = {'path': path, 'sha256': digest(controls)}
write(target, bundle)
for family in ['consumption-phase4b', 'consumption-integrated']:
    for source in sorted((ROOT / f'scenarios/{family}-2026.10.02-16').glob('*.json')):
        case = json.loads(source.read_text(encoding='utf-8'))
        case.update(rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'], knowledge_at=available)
        write(f'scenarios/{family}-{VERSION}/{source.name}', case)
write(f'sources/official/phase4b-manifest-{VERSION}.json', {
    'version': VERSION, 'retrieved_on': '2026-10-02',
    'status': 'SANDBOX_EVIDENCE_REVIEW_REQUIRED', 'artifacts': bundle['official_sources'],
})


base = read('scenarios/dere-local-2026.10.02-15/qualified-trial-balance.json')
base['deductions'] = read('scenarios/dere-local-2026.10.02-16/qualified-deductions-initial.json')['deductions']
base['prerequisites'] = {'supplier_id': '11111111000191', 'period_end': '2026-09-30', 'pgcc_source_hash': 'b'*64,
    'contributor_registration_qualified': True, 'auxiliary_obligations_qualified': True,
    'contributor_source_hash': 'd'*64, 'obligations_source_hash': 'e'*64,
    'existing_closing': False, 'negative_base_use_requested': False,
    'D1106_required': False, 'D2101_required': False, 'D1121_required': True}
base['evidence_scope'] = 'SYNTHETIC_MONTHLY_FIXTURE_NO_GOVERNMENT_RECEPTION_OR_PGCC_APPROVAL'
write(f'scenarios/dere-local-{VERSION}/qualified-monthly-closing.json',base)
print('Materialized candidate17; local monthly closing policy bound; production approval pending.')
