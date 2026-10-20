"""Inventory immutable positive public monthly sources for wider own54 settlement coverage."""
from pathlib import Path
import hashlib
import json
ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / 'storage/app/phase4b-variable-yield-20261006-27'
SCENARIOS = ROOT / 'resources/fiscal/scenarios/dere-variable-yield-2026.10.06-27'
E = ROOT / 'storage/app/phase4b-dere9121-56-corpus-preparation-20261013'
E.mkdir(exist_ok=True)

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def ref(path):
    return {'path': path.relative_to(ROOT).as_posix(), 'sha256': sha(path)}

cases = []
for path in sorted(SOURCE.glob('*-monthly-report-final.json')):
    report = json.loads(path.read_text(encoding='utf-8'))
    debt = report.get('projection', {}).get('debt_yield_projection')
    if not debt:
        continue
    name = path.name.removesuffix('-monthly-report-final.json')
    month_path = SCENARIOS / (name + '.json')
    month = json.loads(month_path.read_text(encoding='utf-8'))
    financial_path = SCENARIOS / month['financial_scenario']
    xml = ROOT / report['event_outputs']['D2101']
    assert sha(xml) == report['event_validations']['D2101']['xml_sha256']
    cases.append({'name': name, 'source_month': ref(month_path), 'source_financial_input': ref(financial_path),
        'source_monthly_report': ref(path), 'source_d2101': ref(xml), 'original_public_total': debt['total_vApur'],
        'title_count': len(debt['qualified_entries']), 'settlement_under54_captured': name == 'constant'})
assert len(cases) > 1
with (E / 'source-corpus-manifest.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'WIDER_PUBLIC_SETTLEMENT_CORPUS_INVENTORIED_NOT_RECAPTURED', 'cases': cases,
        'target_bundle': '2026.10.13-28-draft54', 'no_movement_coverage_complete': False,
        'wider_public_corpus_complete': False, 'production_ready': False}, stream, indent=2)
print('PASS: '+str(len(cases))+' immutable positive public monthly sources inventoried for own54 settlement extension')
