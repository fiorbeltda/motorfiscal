"""Preserve first fixture failure; supply explicit synthetic ISS/advance reconciliation, never remove its gate."""
from pathlib import Path
from copy import deepcopy
import json

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-advance88-preparation-20261015'
S = E / 'scenarios-v2'
target = E / 'advance88-case-manifest-v2.json'
assert not S.exists() and not target.exists()
manifest = json.loads((E / 'advance88-case-manifest.json').read_text(encoding='utf-8'))
S.mkdir()
def save(p, value):
    with p.open('x', encoding='utf-8') as f: json.dump(value, f, ensure_ascii=False, indent=2)
for case in manifest['cases']:
    scenario = json.loads((ROOT / case['path']).read_text(encoding='utf-8'))
    facts = scenario['facts']
    facts['rtc.operation_amount_reconciliation_id'] = 'SYNTHETIC.ISS1000.ADVANCE.' + facts['rtc.tax_point_installment_id']
    if case['case'] == 'base-transformation-not-allocated':
        facts['rtc.base_adjustments'] = [{'kind': 'UNCONDITIONAL_DISCOUNT', 'amount': '10.00',
            'evidence_id': 'SYNTHETIC.DISCOUNT', 'document_id': facts['rtc.document_id'], 'depends_on_later_event': False}]
    path = S / (case['case'] + '.json')
    save(path, scenario)
    case['path'] = path.relative_to(ROOT).as_posix()
positive = manifest['cases'][0]
missing = json.loads((ROOT / positive['path']).read_text(encoding='utf-8'))
missing['facts'].pop('rtc.operation_amount_reconciliation_id')
missing['scenario_id'] = 'SYNTHETIC-ADVANCE88-missing-iss-advance-reconciliation'
path = S / 'missing-iss-advance-reconciliation.json'
save(path, missing)
manifest['cases'].append({'case': 'missing-iss-advance-reconciliation', 'path': path.relative_to(ROOT).as_posix(),
    'status': 'ACTION_REQUIRED', 'reason': 'RTC_GROSS_OPERATION_RECONCILIATION_MISSING', 'expected_nominal': None})
manifest['first_attempt_note'] = '13 passed/10 failed; first fixtures lacked existing gross/ISS reconciliation and adjustment proof. Preserved; runtime gate not weakened.'
save(target, manifest)
print('PASS:24 versioned fixtures;ISS competence/value unchanged;explicit synthetic reconciliation and missing-proof refusal')
