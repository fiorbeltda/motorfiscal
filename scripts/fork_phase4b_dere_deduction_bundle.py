"""Bind the D1121 local projection policy without changing predecessor resources."""
import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / 'resources/fiscal'
VERSION = '2026.10.02-16'


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


previous = read('bundles/consumption-phase4b-2026.10.02-15.json')
target = f'bundles/consumption-phase4b-{VERSION}.json'
available = read(target)['knowledge_available_at'] if (ROOT / target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f'CONSUMPTION-PHASE4B-{VERSION}', version=VERSION,
              predecessor_bundle_id=previous['bundle_id'], knowledge_available_at=available)
codes = read(previous['resource_paths']['rtc_code_catalog'])
codes['version'] = VERSION
codes['dere_deduction_projection'] = {
    'strategy': 'DERE_DOCUMENT_DEDUCTIONS_1_2_0',
    'status': 'ACTIVE', 'published_at': available,
    'source_hash': 'b52cb2cf96b176d303d64aeea0bab16e7d35bcc739478f6fc8a26b0512415feb',
    'contract': 'DERE-DEDUCTIONS-LOCAL-2026.10.02-1',
    'scope': 'LOCAL_UNSIGNED_D1121_NOT_FULL_MONTHLY_DECLARATION',
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
        control['gap'] += ' D1121 local com replay e vínculo ao livro qualificado; documentos e histórico qualificados vinculados; PGCC referencial e D1199 continuam pendentes.'
path = f'production/phase4b-controls-{VERSION}.json'
write(path, controls)
bundle['production_controls'] = {'path': path, 'sha256': digest(controls)}
write(target, bundle)
for family in ['consumption-phase4b', 'consumption-integrated']:
    for source in sorted((ROOT / f'scenarios/{family}-2026.10.02-15').glob('*.json')):
        case = json.loads(source.read_text(encoding='utf-8'))
        case.update(rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'], knowledge_at=available)
        write(f'scenarios/{family}-{VERSION}/{source.name}', case)
write(f'sources/official/phase4b-manifest-{VERSION}.json', {
    'version': VERSION, 'retrieved_on': '2026-10-02',
    'status': 'SANDBOX_EVIDENCE_REVIEW_REQUIRED', 'artifacts': bundle['official_sources'],
})

for continued in [False, True]:
    doc = {'tpDFe': '03', 'chDFe': '1' * 44, 'dtEmi': '2026-08-20' if continued else '2026-09-20', 'tpAtiv': '01',
           'vOper': '1000.00', 'vDedTotal': '800.00', 'vDed': '200.00',
           'document_validity_qualified': True, 'document_source_hash': 'b' * 64, 'evidence_id': 'FIXTURE.DFE',
           'financial_entry_ids': ['funding'],
           'items': [{'nItem': '1', 'vItem': '1000.00', 'vItemDedTotal': '800.00', 'vItemDed': '200.00', 'source_item_qualified': True}]}
    if continued:
        del doc['vDedTotal']
        del doc['items'][0]['vItemDedTotal']
        doc['previous_deduction'] = {'source_hash': 'c' * 64, 'chDFe': doc['chDFe'], 'dtEmi': doc['dtEmi'],
            'supplier_id': '11111111000191', 'tpDFe': '03', 'tpAtiv': '01', 'vOper': '1000.00',
            'registered_period': '2026-08', 'through_period': '2026-08', 'history_complete': True,
            'vDedTotal': '800.00', 'accumulated_deduction': '600.00', 'itemized': True,
            'items': {'1': {'vItem': '1000.00', 'vItemDedTotal': '800.00', 'accumulated_deduction': '600.00'}}}
    write(f"scenarios/dere-local-{VERSION}/qualified-deductions-{'continued' if continued else 'initial'}.json", {
        'evidence_scope': 'SYNTHETIC_FIXTURE_NOT_REAL_DOCUMENT_VALIDATION',
        'identity': {'generated_at': '2026-10-01T12:00:00.000-03:00', 'event_sequence': 1},
        'deductions': {'supplier_id': '11111111000191', 'document_id': 'dps-1', 'period_end': '2026-09-30', 'observed_on': '2026-10-01',
            'documents_qualified': True, 'deduction_allocation_complete': True, 'monthly_period_closed_previously': False,
            'source_hash': 'a' * 64, 'documents': [doc]},
    })
print('Materialized candidate16; original D1121 policy bound, production controls remain open.')
