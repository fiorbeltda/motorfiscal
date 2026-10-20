"""Own current-facts scenarios98; documentary assertions never replace native FX/classification gates."""
from pathlib import Path
from datetime import datetime, timezone
from copy import deepcopy
import hashlib
import json
import shutil

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = Path(r'D:\ProjetoSaasExports\phase4b-sp98-integration-20261016')
S = Path(r'D:\ProjetoSaasExports\phase4b-document97-sp-primary-research-20261016-v2')
V = '2026.10.16-98-draft1'
BUNDLE = 'bundles/consumption-phase4b-' + V + '.json'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def save(p, v):
    with p.open('x', encoding='utf-8') as stream: json.dump(v, stream, ensure_ascii=False, indent=2)
def canonical(v): return hashlib.sha256(json.dumps(v, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
bundle = read(F / BUNDLE)
folder = F / 'scenarios' / ('export-sp-' + V)
folder.mkdir(exist_ok=False)
template = read(S / 'cases-v2.json')[0]['proof']
old = read(Path(r'D:\ProjetoSaasExports\phase4b-export-iss96-integration-20261016\export-iss96-case-manifest-v2.json'))
known = datetime.now(timezone.utc).isoformat()
def seal(proof): proof['source_hash'] = canonical({k: v for k, v in proof.items() if k != 'source_hash'})
def scenario(name, original):
    row = next(c for c in old['cases'] if c['name'] == original)
    doc = read(ROOT / row['path'])
    doc.update(scenario_id='SYNTHETIC-EXPORT-SP98-' + name, knowledge_at=known,
        rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'])
    facts = doc['facts']
    facts['rtc.export_operation_reference'] = doc['scenario_id']
    facts['rtc.assess_export_sp_results'] = True
    proofs = []
    for record in facts['rtc.export_iss_result_contexts']:
        context = record['iss_context']
        context['operation_id'] = doc['scenario_id']
        context['result_proof']['value']['operation_id'] = doc['scenario_id']
        seal(context['result_proof'])
        proof = deepcopy(template)
        proof['evidence_id'] = 'SYNTHETIC-MUNICIPAL-SP98-' + name + '-' + record['role']
        proof['observed_at'] = known
        value = proof['value']
        for key in ['operation_id', 'document_id', 'supplier_id', 'acquirer_id', 'recipient_id', 'transaction_evidence_id', 'supply_on']:
            value[key] = context[key]
        scope = context['result_proof']['value']['result_scope']
        value.update(national_result_proof_hash=context['result_proof']['source_hash'], result_subject_scope=scope,
            result_subject_id='SYNTHETIC-RESULT-SUBJECT-' + record['role'], software_asset_id='SYNTHETIC-ASSET-' + record['role'],
            br_person_link='NO_BR_LINK' if scope == 'ABROAD' else 'HAS_BR_LINK')
        seal(proof)
        proofs.append({'role': record['role'], 'municipal_proof': proof})
    facts['rtc.export_sp_result_proofs'] = proofs
    return doc, row['independent_decimal']

cases = []
def add(name, mutate=lambda d: None, *, original='usd-direct', reason='RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN', bound=True, reseal=True):
    doc, decimal = scenario(name, original)
    mutate(doc)
    if reseal:
        for row in doc['facts'].get('rtc.export_sp_result_proofs', []): seal(row['municipal_proof'])
    path = folder / (name + '.json')
    save(path, doc)
    scopes = [r['municipal_proof']['value']['result_subject_scope'] for r in doc['facts'].get('rtc.export_sp_result_proofs', [])]
    links = [r['municipal_proof']['value']['br_person_link'] for r in doc['facts'].get('rtc.export_sp_result_proofs', [])]
    cases.append({'name': name, 'path': path.relative_to(ROOT).as_posix(), 'reason': reason, 'expected_bound': bound,
        'independent_decimal': decimal, 'expected_scopes': scopes if bound else [], 'expected_br_links': links if bound else []})

for original in ['usd-direct', 'usd-weekend', 'usd-quarter', 'usd-mostly-foreign', 'usd-mostly-domestic', 'brl-control']:
    add(original, original=original)
add('foreign-subject-software-br-linked', lambda d: d['facts']['rtc.export_sp_result_proofs'][0]['municipal_proof']['value'].update(br_person_link='HAS_BR_LINK'))
add('foreign-rtc-result-br', original='foreign-rtc-iss-br')
add('domestic-rtc-result-abroad', original='domestic-rtc-iss-abroad')
def continuing(d):
    for row in d['facts']['rtc.export_sp_result_proofs']:
        row['municipal_proof']['value'].update(service_execution='CONTINUING_MONTHLY', monthly_stage={
            'period': d['competence_date'][:7], 'stage_id': 'SYNTHETIC-STAGE-' + row['role'],
            'completed_on': d['competence_date'], 'completion_evidence_reference': 'SYNTHETIC-COMPLETION-' + row['role']})
add('continuing-monthly-stages', continuing)
add('proof-order-reversed', lambda d: d['facts']['rtc.export_sp_result_proofs'].reverse())
for role_index, role_name in [(0, 'foreign'), (1, 'domestic')]:
    for field, value, suffix in [('br_person_link', 'NOT_PROVEN', 'link-unproven'),
        ('result_subject_scope', 'MIXED', 'mixed-result'), ('supplier_establishment_municipality', '3304557', 'wrong-municipality'),
        ('national_result_proof_hash', '0' * 64, 'wrong-national-proof')]:
        add(role_name + '-' + suffix, lambda d, i=role_index, k=field, v=value: d['facts']['rtc.export_sp_result_proofs'][i]['municipal_proof']['value'].update({k: v}),
            reason='RTC_EXPORT_SP98_MUNICIPAL_RESULT_NOT_BOUND', bound=False)
add('missing-proofs', lambda d: d['facts'].pop('rtc.export_sp_result_proofs'), reason='RTC_EXPORT_SP98_TWO_MUNICIPAL_PROOFS_REQUIRED', bound=False)
add('duplicate-role', lambda d: d['facts']['rtc.export_sp_result_proofs'][1].update(role='RTC_FOREIGN'), reason='RTC_EXPORT_SP98_DUPLICATE_MUNICIPAL_ROLE', bound=False)
add('swapped-sibling-proof', lambda d: d['facts']['rtc.export_sp_result_proofs'][0].update(municipal_proof=deepcopy(d['facts']['rtc.export_sp_result_proofs'][1]['municipal_proof'])),
    reason='RTC_EXPORT_SP98_MUNICIPAL_RESULT_NOT_BOUND', bound=False)
add('proof-tampered', lambda d: d['facts']['rtc.export_sp_result_proofs'][0]['municipal_proof']['value'].update(br_person_link='HAS_BR_LINK'),
    reason='RTC_EXPORT_SP98_MUNICIPAL_RESULT_NOT_BOUND', bound=False, reseal=False)
add('payment-not-municipal-result', lambda d: d['facts']['rtc.export_sp_result_proofs'][0]['municipal_proof'].update(kind='PAYMENT'),
    reason='RTC_EXPORT_SP98_MUNICIPAL_RESULT_NOT_BOUND', bound=False)
def unfinished(d):
    continuing(d)
    d['facts']['rtc.export_sp_result_proofs'][0]['municipal_proof']['value']['monthly_stage']['completed_on'] = '2026-09-24'
add('unfinished-monthly-stage', unfinished, reason='RTC_EXPORT_SP98_MUNICIPAL_RESULT_NOT_BOUND', bound=False)
add('national-assessment-disabled', lambda d: d['facts'].update({'rtc.assess_export_iss_results': False}),
    reason='RTC_EXPORT_SP98_NATIONAL_RESULT_ASSESSMENT_REQUIRED', bound=False)
add('disabled-malformed-proofs', lambda d: d['facts'].update({'rtc.assess_export_sp_results': False, 'rtc.export_sp_result_proofs': []}), bound=False)
add('national-proof-fails-first', lambda d: d['facts']['rtc.export_iss_result_contexts'][0]['iss_context']['result_proof'].update(source_hash='0' * 64),
    reason='RTC_EXPORT_ISS96_INDEPENDENT_ISS_RESULT_NOT_BOUND', bound=False)
add('wrong-route', lambda d: d['facts'].update({'rtc.market': 'DOMESTIC'}), reason='RTC_EXPORT_ISS96_PARTIAL_EXPORT_ROUTE_REQUIRED', bound=False)
add('temporal-combination', lambda d: d['facts'].update({'rtc.assess_advance_origin': True}), reason='RTC_EXPORT_ISS96_SEPARATE_TEMPORAL_CONTEXT_REQUIRED', bound=False)
manifest = {'created_at': datetime.now(timezone.utc).isoformat(), 'bundle': BUNDLE, 'cases': cases,
    'full_suite_complete': False, 'production_ready': False}
save(E / 'sp98-case-manifest.json', manifest)
save(ROOT / 'tests/Fixtures/sp98-scenario-expectations.json', manifest)
assert not (ROOT / 'tests/Fixtures/sp97-cases.json').exists()
shutil.copy2(S / 'cases-v2.json', ROOT / 'tests/Fixtures/sp97-cases.json')
print('PASS:own98 scenarios=' + str(len(cases)) + ';permanent97 fixture140;old scenarios unchanged')
