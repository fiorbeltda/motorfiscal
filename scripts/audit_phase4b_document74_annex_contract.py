"""Freeze reviewed documentary clauses and missing-schema constraints with cell provenance."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
from openpyxl import load_workbook

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-document74-annex-research-20261014'
S = ROOT / 'storage/app/phase4b-document74-primary-research-20261014'


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


observed = read(E / 'primary-annex-observation.json')
for item in observed['sources']:
    assert item['workbook_verified'] and sha(E / item['file']) == item['sha256']
layout_name = observed['sources'][0]['file']
wb = load_workbook(E / layout_name, read_only=True, data_only=False)
sheet = wb['LEIAUTE DPS_NFS-e - RT']
assert sheet['C236'].value == 'mdPrestacao'
assert 'parcela exportada' in sheet['I236'].value
assert 'NFS-e regular' in sheet['I236'].value
assert sheet['C239'].value == 'nFatura'
assert 'aguarda ato conjunto RFB/CGIBS' in sheet['I239'].value
assert sheet['C336'].value == 'vAjusteBCIBSCBSComExt'
locations = [('LEIAUTE DPS_NFS-e - RT', 'I236'), ('LEIAUTE DPS_NFS-e - RT', 'I238'),
             ('LEIAUTE DPS_NFS-e - RT', 'I239'), ('LEIAUTE DPS_NFS-e - RT', 'C336'),
             ('RN DPS_NFS-e', 'D601'), ('RN DPS_NFS-e', 'H601')]
clauses = []
for tab, address in locations:
    cell = wb[tab][address]
    clauses.append({'sheet': tab, 'cell': address, 'value': cell.value,
                    'font_strike': cell.font.strike,
                    'font_color_type': cell.font.color.type if cell.font.color else None,
                    'font_color_rgb': cell.font.color.rgb if cell.font.color and cell.font.color.type == 'rgb' else None})
wb.close()
closure = read(S / 'schema-closure-inspection.json')
absent = ['nFatura', 'vFatura', 'nContCambio', 'nDUIMP', 'nDUE', 'vAjusteBCIBSCBSComExt']
for package in closure['schemas']:
    names = {field['attributes']['name'] for field in package['observed_fields']}
    assert not names.intersection(absent)
    assert sha(S / package['archive']) == package['archive_sha256']
series = read(S / 'restricted-series-native-validation-v2.json')
for key in ['1', '00001', '89999']:
    assert series['cases'][key]['status'] == 'VALID'
for key in ['90000', '99999', '^1$', '123456', 'ABC']:
    assert series['cases'][key]['status'] == 'INVALID'
    assert any('serie' in error['message'] for error in series['cases'][key]['errors'])
report = {'status': 'DOCUMENT74_PRIMARY_ANNEX_CONTRACT_AND_SCHEMA_GAPS_REVIEWED_NOT_ACTIVATED',
          'observed_at': datetime.now(timezone.utc).isoformat(),
          'annexes': observed['sources'], 'clauses': clauses,
          'partial_export_document_pair': {'export_vserv': 'FOREIGN_ALLOCATED_SHARE',
                                           'domestic_vserv': 'DOMESTIC_ALLOCATED_SHARE',
                                           'domestic_document_is_export': False,
                                           'basis': {'sheet': 'LEIAUTE DPS_NFS-e - RT', 'cell': 'I236'}},
          'nFatura_universal_export_requirement_proven': False,
          'import_invoice_requirement_exact_scope_requires_joint_act': True,
          'observed_xsd_missing_new_elements': absent,
          'series_valid_in_preserved_restricted_package': ['1', '00001', '89999'],
          'effective_date_proven_from_filename': False,
          'future_comext_schedule_does_not_authorize_current_operation': True,
          'runtime_changed': False, 'document_obligation_complete': False,
          'scope73_complete': False, 'phase4b_complete': False, 'production_ready': False,
          'evidence': [{'path': p.relative_to(ROOT).as_posix(), 'sha256': sha(p)} for p in
                       [E / 'primary-annex-observation.json', E / 'annex-content-inspection.json',
                        E / 'nt009-full-text.txt', S / 'schema-closure-inspection.json',
                        S / 'restricted-series-native-validation.json',
                        S / 'restricted-series-native-validation-v2.json',
                        E / 'visual-review/nt009-16.png', E / 'visual-review/nt009-18.png']]}
with (E / 'document-annex-contract-validation.json').open('x', encoding='utf-8') as stream:
    json.dump(report, stream, indent=2, ensure_ascii=False)
print('PASS: official mixed consumption clause,conditional invoice scope,missing current XSD fields,and local series cases')
print('Rule601 strike:', next(row['font_strike'] for row in clauses if row['cell'] == 'D601'))
