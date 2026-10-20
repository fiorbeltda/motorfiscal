"""Generate own104 scenarios from the independently expected103 corpus; no historical mutation."""
from pathlib import Path
from datetime import datetime, timezone
import copy
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-identity104-integration-20261019')
V = '2026.10.19-104-draft1'
F = ROOT / 'resources/fiscal'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

def save(p, value):
    with p.open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)

fixture = ROOT / 'tests/Fixtures/identity103-cases.json'
base = read(F / 'scenarios/catalog-reference-2026.10.16-101-draft1-v2/export-brl-control.json')
bundle_path = 'bundles/consumption-phase4b-' + V + '.json'
bundle = read(F / bundle_path)
selected = ['local-pair', 'reversed-roles', 'good-series-1', 'good-series-00001', 'good-series-49999',
    'good-number-999999999999999', 'missing-record', 'duplicate-role', 'reference-sibling-swapped',
    'identity-scope-environment', 'identity-scope-emission_channel', 'tampered-id',
    'proof-source-hash', 'wrong-proof-operation_reference', 'same-canonical-id-despite-series-padding']
folder = F / ('scenarios/dps-identity-' + V)
folder.mkdir(exist_ok=False)
cases = {c['name']: c for c in read(fixture)['cases']}
records = []
now = datetime.now(timezone.utc)
for name in selected:
    case = cases[name]
    assert case['facts'] == cases['local-pair']['facts']
    scenario = copy.deepcopy(base)
    scenario.update(scenario_id='IDENTITY104-' + name, rule_bundle_id=bundle['bundle_id'],
        catalog_versions=bundle['catalog_versions'], knowledge_at=max(now, datetime.fromisoformat(case['known_at'])).isoformat())
    scenario['facts'] = copy.deepcopy(case['facts'])
    scenario['facts']['rtc.assess_export_dps_identity'] = True
    scenario['facts']['rtc.export_dps_identity_records'] = case['records']
    target = folder / (name + '.json')
    save(target, scenario)
    positive = case['expected_status'] == 'LOCAL_DPS_IDENTITY_PAIR_BOUND'
    records.append({'name': name, 'path': target.relative_to(ROOT).as_posix(), 'sha256': sha(target),
        'status': 'ACTION_REQUIRED' if positive else case['expected_status'],
        'reason': 'RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN' if positive else case['expected_reason'],
        'identity_status': case['expected_status'], 'identity_reason': case['expected_reason'],
        'original_knowledge_at': case['known_at'], 'knowledge_at': scenario['knowledge_at'],
        'expected_ids': case.get('expected_ids')})
manifest = {'created_at': datetime.now(timezone.utc).isoformat(), 'bundle': bundle_path,
    'fixture_sha256': sha(fixture), 'synthetic_local_mappings_only': True, 'cases': records,
    'notes': 'Original103 facts/references and identity/proof timestamps unchanged;only opt-in/mapping facts added. As-of never reduced. No fiscal document authorization or XML.'}
save(E / 'identity104-case-manifest.json', manifest)
save(ROOT / 'tests/Fixtures/identity104-scenario-expectations.json', manifest)
print('PASS:15 own104 scenarios;original references/proof dates preserved', flush=True)
