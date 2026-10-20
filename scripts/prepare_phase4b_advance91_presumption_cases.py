"""Preserve original28 fixtures and add explicit documentary-date compositions, not invented material delivery."""
from pathlib import Path
from copy import deepcopy
import json

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-advance91-integration-20261015'
S = E / 'scenarios-v2'
target = E / 'advance91-case-manifest-v2.json'
assert not S.exists() and not target.exists()
manifest = json.loads((E / 'advance91-case-manifest.json').read_text(encoding='utf-8'))
base = json.loads((ROOT / manifest['cases'][0]['path']).read_text(encoding='utf-8'))
S.mkdir()
for name, expected_date, issued in [('document-issue-presumption', None, '2026-10-20'),
                                  ('document-expected-date-presumption', '2026-10-20', '2026-10-18')]:
    scenario = deepcopy(base); scenario['scenario_id'] = 'SYNTHETIC-RECORDED-ADVANCE91-' + name
    facts = scenario['facts']; operation = facts['rtc.tax_point_contexts'][0]
    operation['actual_supply'] = None
    operation['fiscal_document'] = {'operation_id': 'OP87', 'document_id': facts['rtc.document_id'],
        'kind': 'FISCAL_DOCUMENT_ISSUED', 'date': issued, 'observed_on': issued, 'evidence_id': 'SYNTHETIC.PRESUMPTION.DOC91',
        'local_integrity_checked': True, 'expected_delivery_on': expected_date}
    facts['rtc.recorded_advance_final_contexts'][0]['supply_evidence']['evidence_id'] = 'SYNTHETIC.PRESUMPTION.DOC91'
    path = S / (name + '.json')
    with path.open('x', encoding='utf-8') as stream: json.dump(scenario, stream, ensure_ascii=False, indent=2)
    manifest['cases'].append({'case': name, 'path': path.relative_to(ROOT).as_posix(), 'status': 'ACTION_REQUIRED',
        'reason': 'RTC_RECORDED_ADVANCE_DOCUMENT_AND_POSTING_NOT_PROVEN', 'expected_cbs_balance': '6.30'})
with target.open('x', encoding='utf-8') as stream: json.dump(manifest, stream, indent=2)
print('PASS:30 cases;28 original plus2 qualified documentary presumptions;material and presumptive bases stay explicit')
