"""Bind article271 network routes and documented article272 transfer eligibility."""
import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / 'resources/fiscal'
VERSION = '2026.10.02-10'


def read(path):
    return json.loads((ROOT/path).read_text(encoding='utf-8'))


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def write(path, value):
    target = ROOT/path
    if target.exists():
        if read(path) != value: raise RuntimeError(f'Refusing to overwrite {target}')
        return
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(value, ensure_ascii=False, indent=2)+'\n', encoding='utf-8')


previous = read('bundles/consumption-phase4b-2026.10.02-9.json')
target = f'bundles/consumption-phase4b-{VERSION}.json'
available = read(target)['knowledge_available_at'] if (ROOT/target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
codes = read(previous['resource_paths']['rtc_code_catalog'])
codes['version'] = VERSION
source = codes['cooperative_zero']['source_hash']
codes['cooperative_zero'].update(network_routes=True, published_at=available)
codes['cooperative_credit_transfer'] = {'strategy': 'LC214_ART272_COOPERATIVE_CREDIT_TRANSFER', 'status': 'ACTIVE',
    'valid_from': '2026-01-01', 'published_at': available, 'source_hash': source,
    'source_id': codes['cooperative_zero']['source_id'], 'source_article': 'LC214 art272 and paragraph; art54',
    'review_status': 'SANDBOX_REVIEW_REQUIRED', 'scope': 'DOCUMENTED_PREQUALIFIED_CREDITS_LINKED_TO_MEMBER_SUPPLY_NO_TRANSFER_OR_POSTING'}
facts = read(previous['resource_paths']['fact_definitions'])
facts.extend([{'key': 'rtc.assess_cooperative_credit_transfer', 'type': 'BOOLEAN', 'material': True},
              {'key': 'rtc.cooperative_credit_transfer', 'type': 'ARRAY', 'material': True}])
questions = read(previous['resource_paths']['questions'])
rank = max(row['rank'] for row in questions)+1
questions.extend([{'fact_key': 'rtc.assess_cooperative_credit_transfer', 'answer_type': 'BOOLEAN', 'rank': rank,
    'prompt': 'Deseja avaliar localmente os créditos transferíveis do associado à cooperativa pelo art272, sem efetuar a transferência?'},
    {'fact_key': 'rtc.cooperative_credit_transfer', 'answer_type': 'ARRAY', 'rank': rank+1,
    'prompt': 'Informe o conjunto completo de créditos apropriados, originais e disponíveis, vínculo com a produção deste fornecimento, provas de apropriação/extinção ou presumido qualificado e validação regulamentar da transferência.'}])
controls = read(previous['production_controls']['path'])
controls['version'] = VERSION
for control in controls['controls']:
    if control['id'] == '4B.4.BASE_AND_SPECIAL_REGIMES':
        control['gap'] += ' Rotas não financeiras entre cooperativas e originadas de banco cooperativo qualificados; demais financeiros/agropecuários pendentes.'
    if control['id'] == '4B.5.CREDITS':
        control['gap'] += ' Art272: vínculo produtivo, origem/data, saldo disponível e proposta de transferência por tributo; sem transferência efetiva e sem conceder crédito presumido automaticamente.'
bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f'CONSUMPTION-PHASE4B-{VERSION}', version=VERSION, predecessor_bundle_id=previous['bundle_id'], knowledge_available_at=available)
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
base = read('scenarios/consumption-integrated-2026.10.02-9/integrated-cooperative-zero-member-to-cooperative-2026.json')
base.update(rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'], knowledge_at=available)
cases = {}
for name, supplier_type, acquirer_type in [('single-central', 'SINGLE', 'CENTRAL'), ('federation-confederation', 'FEDERATION', 'CONFEDERATION')]:
    case = copy.deepcopy(base)
    p = case['facts']['rtc.cooperative_zero_proof'][0]
    p.update(route='COOPERATIVE_TO_COOPERATIVE', cooperative_id=p['supplier_id'], supplier_cooperative_type=supplier_type, acquirer_cooperative_type=acquirer_type)
    for key in ['supplier_registry_evidence_id', 'acquirer_registry_evidence_id', 'network_operation_evidence_id']: p[key] = 'FIXTURE.NETWORK.'+key
    cases[name] = case
bank = copy.deepcopy(base)
p = bank['facts']['rtc.cooperative_zero_proof'][0]
p.update(route='BANK_TO_PARTICIPATING_COOPERATIVE', cooperative_bank_id=p['supplier_id'], participating_cooperative_id=p['acquirer_id'], acquirer_cooperative_type='CENTRAL')
for key in ['cooperative_bank_registry_evidence_id', 'bank_participation_evidence_id', 'bank_originated_operation_evidence_id']: p[key] = 'FIXTURE.BANK.'+key
cases['bank-participant'] = bank
for name in ['wrong-network-type', 'missing-network-proof', 'wrong-bank-participant', 'missing-bank-proof']:
    case = copy.deepcopy(bank if 'bank' in name else cases['single-central'])
    p = case['facts']['rtc.cooperative_zero_proof'][0]
    if name == 'wrong-network-type': p['acquirer_cooperative_type'] = 'COMMERCIAL_COMPANY'
    elif name == 'missing-network-proof': del p['acquirer_registry_evidence_id']
    elif name == 'wrong-bank-participant': p['participating_cooperative_id'] = 'wrong-participant'
    elif name == 'missing-bank-proof': del p['bank_participation_evidence_id']
    cases[name] = case
transfer = {'member_id': base['facts']['rtc.supplier_id'], 'cooperative_id': base['facts']['rtc.acquirer_id'],
    'supply_document_id': base['facts']['rtc.document_id'], 'currency': 'BRL', 'observed_on': '2026-10-01',
    'credit_pool_complete': True, 'credits_qualified_and_net_of_annulments': True, 'transfer_not_already_recorded': True,
    'credit_pool_evidence_id': 'FIXTURE.COMPLETE.NET.CREDITS', 'transfer_regulatory_validation_evidence_id': 'FIXTURE.REGULATORY.TRANSFER', 'credits': []}
for tax, original, used, unavailable, requested in [('IBS', '100.00', '10.00', '20.00', '50.00'), ('CBS', '900.00', '100.00', '200.00', '500.00')]:
    transfer['credits'].append({'id': 'credit-'+tax, 'tax': tax, 'owner_id': transfer['member_id'], 'acquisition_document_id': 'acquisition1',
        'supply_document_id': transfer['supply_document_id'], 'appropriation_date': '2026-09-10', 'credit_kind': 'ORDINARY',
        'appropriation_evidence_id': 'FIXTURE.APPROPRIATED', 'production_link_evidence_id': 'FIXTURE.INPUT.USED.IN.SUPPLY',
        'available_balance_evidence_id': 'FIXTURE.RECONCILED.NET.BALANCE', 'extinction_evidence_id': 'FIXTURE.EXTINCT.INPUT',
        'used_to_produce_this_supply': True, 'appropriated_amount': original, 'already_used_transferred_or_refunded': used,
        'annulled_or_refund_reserved': unavailable, 'requested_transfer_amount': requested})
with_transfer = copy.deepcopy(base)
with_transfer['facts'].update({'rtc.assess_cooperative_credit_transfer': True, 'rtc.cooperative_credit_transfer': [transfer]})
cases['transfer-partial'] = copy.deepcopy(with_transfer)
for name in ['transfer-full', 'transfer-presumed', 'transfer-missing', 'transfer-wrong-owner', 'transfer-overdrawn', 'transfer-unrelated',
             'transfer-duplicate', 'transfer-future-date', 'transfer-future-observation', 'transfer-already-recorded', 'transfer-wrong-route', 'transfer-presumed-unqualified', 'transfer-missing-regulatory-proof']:
    case = copy.deepcopy(with_transfer)
    f = case['facts']; t = f['rtc.cooperative_credit_transfer'][0]; e = t['credits'][0]
    if name == 'transfer-full':
        e['requested_transfer_amount'] = '70.00'; t['credits'][1]['requested_transfer_amount'] = '600.00'
    elif name in ['transfer-presumed', 'transfer-presumed-unqualified']:
        e.update(credit_kind='PRESUMED', presumed_appropriation_qualified=name == 'transfer-presumed', presumed_rule_evidence_id='FIXTURE.ALREADY.QUALIFIED.PRESUMED')
    elif name == 'transfer-missing': del f['rtc.cooperative_credit_transfer']
    elif name == 'transfer-wrong-owner': e['owner_id'] = 'wrong-owner'
    elif name == 'transfer-overdrawn': e['requested_transfer_amount'] = '70.01'
    elif name == 'transfer-unrelated': e['used_to_produce_this_supply'] = False
    elif name == 'transfer-duplicate':
        duplicate = copy.deepcopy(e); duplicate['id'] = 'second-use'; t['credits'].append(duplicate)
    elif name == 'transfer-future-date': e['appropriation_date'] = '2026-10-02'
    elif name == 'transfer-future-observation': t['observed_on'] = '2026-10-03'
    elif name == 'transfer-already-recorded': t['transfer_not_already_recorded'] = False
    elif name == 'transfer-wrong-route': f['rtc.cooperative_zero_proof'][0] = copy.deepcopy(cases['single-central']['facts']['rtc.cooperative_zero_proof'][0])
    elif name == 'transfer-missing-regulatory-proof': del t['transfer_regulatory_validation_evidence_id']
    cases[name] = case
for name, scenario in cases.items():
    scenario['scenario_id'] = f'integrated-cooperative-network-transfer-{name}-2026'
    write(f'scenarios/consumption-integrated-{VERSION}/{scenario["scenario_id"]}.json', scenario)
print(f'Created {bundle["bundle_id"]}: {len(cases)} network/transfer cases')
