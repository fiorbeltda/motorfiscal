"""Read official workbooks without writing them; fork immutable catalog/bundle126.

Use bundled workspace Python for openpyxl. Local coverage dates are inherited
Sandbox limits, never asserted as official publication or deployment dates.
"""
import argparse
import copy
import hashlib
import json
import shutil
from pathlib import Path

from openpyxl import load_workbook

ROOT = Path(__file__).resolve().parents[1]
FISCAL = ROOT / 'resources/fiscal'
VERSION = '2026.10.22-126-draft1'
parser = argparse.ArgumentParser()
parser.add_argument('--capture', type=Path, required=True)
args = parser.parse_args()

def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))
def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()
def canonical(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
def write(path, value):
    with path.open('x', encoding='utf-8') as handle:
        handle.write(json.dumps(value, ensure_ascii=False, indent=2) + '\n')

capture = read(args.capture / 'capture.json')
records = {item['file']: item for item in capture['records']}
for name, record in records.items():
    assert sha(args.capture / name) == record['sha256'], name

def extract(name, code_column):
    path = args.capture / name
    book = load_workbook(path, read_only=True, data_only=True)
    sheet = book.active
    values = list(sheet.values)
    rows = {}
    for number, row in enumerate(values[1:], 2):
        code = row[code_column]
        if code is None or not isinstance(code, str) or len(code) != 6 or not code.isdigit():
            continue
        assert code not in rows, (name, code)
        rows[code] = {'source_row': number, 'values': list(row)}
    result = {'source': {key: records[name][key] for key in ['url', 'sha256', 'observed_at', 'bytes']},
              'source_sheet': sheet.title, 'headers': list(values[0]), 'rows': rows}
    book.close()
    assert sha(path) == records[name]['sha256']
    return result

rtc = extract('cindop103.xlsx', 0)
restricted = extract('restricted-cindop.xlsx', 6)
assert len(rtc['rows']) == 40 and len(restricted['rows']) == 26
catalog = read(FISCAL / 'catalogs/rtc-codes-2026.10.21-122-draft1.json')
bundle = read(FISCAL / 'bundles/consumption-phase4b-2026.10.21-122-draft1.json')
controls = read(FISCAL / bundle['production_controls']['path'])
reference = {'contract': 'CINDOP-REFERENCE126-V1',
    'scope': 'EXPLICIT_LOCAL_REFERENCE_SELECTION_NOT_OFFICIAL_DEPLOYMENT_OR_TAX_CLASSIFICATION',
    'available_at': max(record['observed_at'] for record in records.values()),
    'sources': {'RTC_1_03_00': rtc, 'RESTRICTED_ANNEX_C_1_01': restricted},
    'observed_pages': {name: {key: records[name][key] for key in ['url', 'sha256', 'observed_at']}
                       for name in ['rtc.html', 'restricted.html', 'production.html']},
    'profiles': {
        'LOCAL_SANDBOX': {'edition': 'RTC_1_03_00', 'document_model': 'NFSE',
                          'coverage_from': '2026-01-05', 'coverage_through': '2026-12-31',
                          'date_scope': 'INHERITED_LOCAL_SANDBOX_COVERAGE_NOT_LEGAL_EFFECT',
                          'deployment_effective_from': None},
        'PRODUCTION_RESTRICTED': {'edition': 'RESTRICTED_ANNEX_C_1_01', 'document_model': 'NFSE',
                                 'deployment_effective_from': None},
        'PRODUCTION': {'edition': None, 'document_model': 'NFSE', 'deployment_effective_from': None}},
    'official_effective_from': None, 'remote_deployment_proven': False,
    'decision_authority': False}
catalog['cindop_reference126'] = reference
catalog['cindop'] = {code: {'operation': row['values'][1] or '', 'characteristic': row['values'][2] or '',
    'destination_basis': row['values'][3] or '', 'legal_reference': row['values'][4] or '',
    'nfse_supported': row['values'][7] == 'S'} for code, row in rtc['rows'].items()}
catalog['sources']['cindop'] = {'file': 'catalog126-cindop103-observed20261022.xlsx', 'version': '1.03.00',
    **rtc['source'], 'effective_from': None, 'status': 'LOCAL_REFERENCE_CANDIDATE_NOT_DEPLOYMENT'}
catalog['version'] = VERSION
catalog['status'] = 'SANDBOX_CANDIDATE'
for name in ['cindop103.xlsx', 'restricted-cindop.xlsx', 'rtc.html', 'restricted.html', 'production.html', 'nt009.pdf', 'restricted-layout.xlsx']:
    dest_name = 'catalog126-' + name.removesuffix(Path(name).suffix) + '-observed20261022' + Path(name).suffix
    dest = FISCAL / 'sources/official' / dest_name
    if dest.exists():
        assert sha(dest) == records[name]['sha256']
    else:
        shutil.copyfile(args.capture / name, dest)
    bundle['official_sources'].append({'id': 'CATALOG126_' + name.upper().replace('.', '_').replace('-', '_'),
        'file': dest_name, 'version': 'OBSERVED_2026.10.22', **{k: records[name][k] for k in ['url', 'sha256', 'observed_at']},
        'published_on': None, 'effective_from': None,
        'proven_effective_scope': 'Local reference only;official temporal/environment activation remains unproved.'})
controls['version'] = VERSION
controls_path = FISCAL / f'production/phase4b-controls-{VERSION}.json'
write(controls_path, controls)
catalog_path = FISCAL / f'catalogs/rtc-codes-{VERSION}.json'
write(catalog_path, catalog)
bundle['predecessor_bundle_id'] = bundle['bundle_id']
bundle['bundle_id'] = 'CONSUMPTION-PHASE4B-' + VERSION
bundle['version'] = VERSION
bundle['knowledge_available_at'] = reference['available_at']
bundle['catalog_versions']['rtc_codes'] = VERSION
bundle['resource_paths']['rtc_code_catalog'] = 'catalogs/' + catalog_path.name
bundle['content_hashes']['rtc_code_catalog'] = canonical(catalog)
bundle['production_controls'] = {'path': 'production/' + controls_path.name, 'sha256': canonical(controls)}
bundle['notice'] = 'Explicit cIndOp126 local reference selection;1.03.00 is not remote deployment,tax authority or complete Phase4B acceptance. Prior versions preserved.'
write(FISCAL / f'bundles/consumption-phase4b-{VERSION}.json', bundle)
report = {'reference_sha256': canonical(reference), 'available_at': reference['available_at'],
          'rtc_rows': len(rtc['rows']), 'restricted_rows': len(restricted['rows']),
          'only_rtc': sorted(set(rtc['rows']) - set(restricted['rows'])),
          'only_restricted': sorted(set(restricted['rows']) - set(rtc['rows'])),
          'catalog_sha256': sha(catalog_path), 'activated_officially': False}
write(args.capture.parent / 'preparation.json', report)
print(json.dumps(report))
