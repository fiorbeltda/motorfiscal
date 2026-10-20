"""Extend104 with all30 accepted101 export routes,including FX and earlier refusals."""
from pathlib import Path
from datetime import datetime, timezone
import copy
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-identity104-integration-20261019')
F = ROOT / 'resources/fiscal'
V = '2026.10.19-104-draft1'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

def canonical(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()

def save(p, value):
    with p.open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)

previous = read(E / 'identity104-case-manifest.json')
bundle = read(F / previous['bundle'])
original = read(ROOT / 'tests/Fixtures/catalog101-scenario-expectations-v2.json')
template = read(ROOT / 'tests/Fixtures/identity103-cases.json')['cases'][0]
folder = F / ('scenarios/dps-identity-upstream-' + V)
folder.mkdir(exist_ok=False)
records = copy.deepcopy(previous['cases'])
now = datetime.now(timezone.utc)
for index, old in enumerate([c for c in original['cases'] if c['name'].startswith('export-')]):
    scenario = read(ROOT / old['path'])
    original_facts = copy.deepcopy(scenario['facts'])
    facts = scenario['facts']
    known = max(now, datetime.fromisoformat(scenario['knowledge_at']))
    mappings = []
    ids = []
    for offset, role in enumerate(['RTC_FOREIGN', 'RTC_DOMESTIC']):
        ref = facts['rtc.export_foreign_document_id' if role == 'RTC_FOREIGN' else 'rtc.export_domestic_document_id']
        identity = copy.deepcopy(template['records'][offset]['identity'])
        identity.update(supplier_id=facts['rtc.supplier_id'], issuer_municipality=facts['provider.municipality_ibge'],
            number=str(100 + index * 2 + offset), issued_at=known.isoformat(), competence_date=facts['rtc.operation_date'])
        identity['id'] = 'DPS' + identity['issuer_municipality'] + '2' + identity['supplier_id'] + identity['series'].zfill(5) + identity['number'].zfill(15)
        proof = {'contract': template['policy']['contract'], 'operation_reference': facts['rtc.export_operation_reference'],
            'document_reference': ref, 'role': role, 'transaction_evidence_id': facts['rtc.export_transaction_evidence_id'],
            'supplier_id': facts['rtc.supplier_id'], 'issuer_municipality': facts['provider.municipality_ibge'],
            'identity_hash': canonical(identity), 'observed_at': known.isoformat(),
            'evidence_id': 'SYNTHETIC.LOCAL.MAPPING104.' + old['name'] + '.' + role}
        proof['source_hash'] = canonical(proof)
        mappings.append({'role': role, 'document_reference': ref, 'identity': identity, 'proof': proof})
        ids.append(identity['id'])
    prior_required = facts.get('rtc.assess_export_iss_results') is not True or facts.get('rtc.assess_export_sp_results') is not True
    reached = not prior_required and old['reason'] == 'RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN'
    facts['rtc.assess_export_dps_identity'] = True
    facts['rtc.export_dps_identity_records'] = mappings
    assert {k: v for k, v in facts.items() if k not in ['rtc.assess_export_dps_identity', 'rtc.export_dps_identity_records']} == original_facts
    scenario.update(scenario_id='IDENTITY104-upstream-' + old['name'], rule_bundle_id=bundle['bundle_id'],
        catalog_versions=bundle['catalog_versions'], knowledge_at=known.isoformat())
    target = folder / (old['name'] + '.json')
    save(target, scenario)
    records.append({'name': 'upstream-' + old['name'], 'path': target.relative_to(ROOT).as_posix(), 'sha256': sha(target),
        'original_path': old['path'], 'original_sha256': sha(ROOT / old['path']),
        'status': 'ACTION_REQUIRED' if prior_required else old['status'],
        'reason': 'RTC_EXPORT_DPS104_PRIOR_ISS_ASSESSMENTS_REQUIRED' if prior_required else old['reason'],
        'identity_status': 'LOCAL_DPS_IDENTITY_PAIR_BOUND' if reached else None,
        'identity_reason': 'DPS103_COMPOSITION_AND_LOCAL_MAPPING_ONLY' if reached else None,
        'expected_ids': ids if reached else None, 'original_knowledge_at': read(ROOT / old['path'])['knowledge_at'],
        'knowledge_at': known.isoformat()})
assert len(records) == 45
manifest = {**previous, 'created_at': datetime.now(timezone.utc).isoformat(), 'cases': records,
    'notes': previous['notes'] + ' Extended with all30 accepted101 export routes;USD/BRL,shares,result contradictions and earlier refusal gates preserved. New local mapping proofs remain synthetic.'}
save(E / 'identity104-case-manifest-v2.json', manifest)
save(ROOT / 'tests/Fixtures/identity104-scenario-expectations-v2.json', manifest)
print('PASS:45 own104 scenarios;30 upstream business facts and historical proofs unchanged', flush=True)
