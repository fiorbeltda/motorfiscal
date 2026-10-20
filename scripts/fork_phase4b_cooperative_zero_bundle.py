"""Bind article271 member/cooperative service qualification and zero calculation."""
import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / 'resources/fiscal'
VERSION = '2026.10.02-8'


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


previous = read('bundles/consumption-phase4b-2026.10.02-7.json')
target = f'bundles/consumption-phase4b-{VERSION}.json'
available = read(target)['knowledge_available_at'] if (ROOT / target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
source = 'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde'
assert hashlib.sha256((ROOT / 'sources/official/lc214-camara-observed-20261001.html').read_bytes()).hexdigest() == source
codes = read(previous['resource_paths']['rtc_code_catalog'])
codes['version'] = VERSION
assert codes['cclasstrib']['200020']['cst'] == '200'
codes['cooperative_zero'] = {'strategy': 'LC214_ART271_COOPERATIVE_SERVICE_ZERO', 'status': 'ACTIVE', 'valid_from': '2026-01-01',
    'published_at': available, 'source_id': 'LC214-CONSOLIDATED-CAMARA-OBSERVED-20261001', 'source_hash': source,
    'source_article': 'LC214 art.271 I/II and paragraph3; arts49,52', 'review_status': 'SANDBOX_REVIEW_REQUIRED',
    'scope': 'DOCUMENTED_MEMBER_COOPERATIVE_NON_FINANCIAL_DOMESTIC_SERVICES'}
new_rules = {}
for key, rule in codes['engine_decision_rules'].items():
    if rule.get('ctribnac_code') == '080101' and rule.get('tax_treatment') == 'STANDARD':
        row = copy.deepcopy(rule)
        row.update(tax_treatment='ZERO_RATE', cclasstrib='200020', published_at=available,
                   source_article='LC214 art.271 I/II', source_hash=source)
        new_rules[key+'.COOPERATIVE.ZERO'] = row
assert len(new_rules) == 2
codes['engine_decision_rules'].update(new_rules)
facts = read(previous['resource_paths']['fact_definitions'])
facts.append({'key': 'rtc.cooperative_zero_proof', 'type': 'ARRAY', 'material': True})
questions = read(previous['resource_paths']['questions'])
questions.append({'fact_key': 'rtc.cooperative_zero_proof', 'answer_type': 'ARRAY', 'rank': max(row['rank'] for row in questions) + 1,
    'prompt': 'Informe a prova vinculada às partes e ao documento: cooperativa, associado, vínculo, opção registrada/eficaz, datas de exercício/efeitos, validação regulamentar e natureza não financeira do serviço.'})
controls = read(previous['production_controls']['path'])
controls['version'] = VERSION
for control in controls['controls']:
    if control['id'] == '4B.4.BASE_AND_SPECIAL_REGIMES':
        control['gap'] += ' Cooperativas: rotas I/II art271 não financeiras com opção comprovada e cálculo zero; financeiro, intercooperativas, agropecuário e transferência272 permanecem pendentes.'
    if control['id'] == '4B.6.CLASSIFICATION':
        control['gap'] = '98 regras interpretativas candidatas; duas novas regras educacionais de cooperativas. Demais mapeamentos e aprovação formal pendentes.'
bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f'CONSUMPTION-PHASE4B-{VERSION}', version=VERSION,
              predecessor_bundle_id=previous['bundle_id'], knowledge_available_at=available)
bundle['catalog_versions']['rtc_codes'] = VERSION
for key, path, value in [('rtc_code_catalog', f'catalogs/rtc-codes-{VERSION}.json', codes),
    ('fact_definitions', f'facts/consumption-phase4b-{VERSION}.json', facts),
    ('questions', f'questions/consumption-integrated-{VERSION}.json', questions)]:
    write(path, value)
    bundle['resource_paths'][key] = path
    bundle['content_hashes'][key] = digest(value)
write(f'production/phase4b-controls-{VERSION}.json', controls)
bundle['production_controls'] = {'path': f'production/phase4b-controls-{VERSION}.json', 'sha256': digest(controls)}
write(target, bundle)
write(f'sources/official/phase4b-manifest-{VERSION}.json', {'version': VERSION, 'retrieved_on': '2026-10-02',
    'status': 'SANDBOX_EVIDENCE_REVIEW_REQUIRED', 'artifacts': bundle['official_sources']})
base = read('scenarios/consumption-integrated-2026.10.02-6/integrated-institutional-immunity-nonprofit-education-2026.json')
base.update(rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'], knowledge_at=available)
f = base['facts']
for key in ['rtc.institutional_immunity_proof', 'iss.evaluate_institutional_immunity', 'iss.institutional_immunity_proof']:
    f.pop(key, None)
f.update({'rtc.tax_treatment': 'ZERO_RATE', 'rtc.treatment_rule_id': 'RTC.COOPERATIVE.ART271-1',
          'rtc.treatment_evidence_id': 'FIXTURE.COOPERATIVE.DOCUMENTED.OPTION', 'rtc.acquirer_regime': 'REGULAR'})
proof = {'supplier_id': f['rtc.supplier_id'], 'acquirer_id': f['rtc.acquirer_id'], 'document_id': f['rtc.document_id'],
    'cooperative_id': f['rtc.supplier_id'], 'member_id': f['rtc.acquirer_id'], 'route': 'COOPERATIVE_TO_REGULAR_MEMBER',
    'service_category': 'NON_FINANCIAL', 'valid_from': '2026-01-01', 'valid_to': '2026-12-31', 'observed_on': '2026-09-30',
    'option_exercised_on': '2025-12-10', 'option_at_start_of_operations': False,
    'option_registered_and_effective': True, 'membership_or_participation_valid': True}
for key in ['cooperative_registry_evidence_id', 'option_registration_evidence_id', 'option_regulatory_validation_evidence_id',
            'membership_evidence_id', 'service_scope_evidence_id']:
    proof[key] = 'FIXTURE.COOPERATIVE.'+key.upper()
f['rtc.cooperative_zero_proof'] = [proof]
cases = {'cooperative-to-member': copy.deepcopy(base)}
for name in ['member-to-cooperative', 'startup-option', 'missing-proof', 'wrong-document', 'wrong-parties', 'option-expired',
             'option-not-effective', 'unknown-membership', 'nonregular-member', 'wrong-route-parties', 'current-year-option',
             'missing-regulatory-evidence', 'financial-service', 'future-observation']:
    scenario = copy.deepcopy(base)
    sf = scenario['facts']
    p = sf['rtc.cooperative_zero_proof'][0]
    if name == 'member-to-cooperative':
        p.update(route='MEMBER_TO_COOPERATIVE', cooperative_id=sf['rtc.acquirer_id'], member_id=sf['rtc.supplier_id'])
    elif name == 'startup-option':
        p.update(option_at_start_of_operations=True, operations_started_on='2026-01-01', option_exercised_on='2026-01-01')
    elif name == 'missing-proof': del sf['rtc.cooperative_zero_proof']
    elif name == 'wrong-document': p['document_id'] = 'wrong-document'
    elif name == 'wrong-parties': p['supplier_id'] = 'wrong-supplier'
    elif name == 'option-expired': p['valid_to'] = '2026-09-30'
    elif name == 'option-not-effective': p['option_registered_and_effective'] = False
    elif name == 'unknown-membership': del p['membership_or_participation_valid']
    elif name == 'nonregular-member': sf['rtc.acquirer_regime'] = 'SIMPLES_NACIONAL'
    elif name == 'wrong-route-parties': p['member_id'] = 'wrong-member'
    elif name == 'current-year-option': p['option_exercised_on'] = '2026-01-01'
    elif name == 'missing-regulatory-evidence': del p['option_regulatory_validation_evidence_id']
    elif name == 'financial-service': p['service_category'] = 'FINANCIAL'
    elif name == 'future-observation': p['observed_on'] = '2026-10-03'
    cases[name] = scenario
family = read('scenarios/consumption-integrated-2026.10.02-6/integrated-institutional-immunity-family-payer-with-4a-2026.json')
individual = copy.deepcopy(base)
for key, value in family['facts'].items():
    if key.startswith(('payment.', 'invoice.', 'inss.', 'irrf.', 'pcc.', 'federal.')):
        individual['facts'][key] = value
individual['facts'].update({'customer.person_type': 'PF', 'rtc.buyer_relation': 'B2C',
    'rtc.acquirer_id': '12345678902', 'rtc.recipient_id': '12345678902'})
individual['facts']['rtc.cooperative_zero_proof'][0].update(acquirer_id='12345678902', member_id='12345678902')
cases['individual-member-with-4a'] = individual
for name, scenario in cases.items():
    scenario['scenario_id'] = f'integrated-cooperative-zero-{name}-2026'
    write(f'scenarios/consumption-integrated-{VERSION}/{scenario["scenario_id"]}.json', scenario)
print(f'Created {bundle["bundle_id"]}: {len(cases)} cooperative cases')
