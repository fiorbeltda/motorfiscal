"""Activate financial scope/base; preserve honest pending specific calculation and documents."""
import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / 'resources/fiscal'
VERSION = '2026.10.02-11'


def read(path):
    return json.loads((ROOT/path).read_text(encoding='utf-8'))


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def write(path, value):
    target = ROOT/path
    if target.exists():
        if read(path) != value:
            raise RuntimeError(f'Refusing to overwrite {target}')
        return
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(value, ensure_ascii=False, indent=2)+'\n', encoding='utf-8')


previous = read('bundles/consumption-phase4b-2026.10.02-10.json')
target = f'bundles/consumption-phase4b-{VERSION}.json'
available = read(target)['knowledge_available_at'] if (ROOT/target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
codes = read(previous['resource_paths']['rtc_code_catalog'])
codes['version'] = VERSION
rule = {'status': 'ACTIVE', 'valid_from': '2026-01-01', 'published_at': available,
    'source_hash': codes['cooperative_zero']['source_hash'], 'source_id': codes['cooperative_zero']['source_id'],
    'review_status': 'SANDBOX_REVIEW_REQUIRED'}
codes['financial_operation'] = {'scope': {**rule, 'strategy': 'LC214_FINANCIAL_SCOPE_ART182_184'},
    'base': {**rule, 'strategy': 'LC214_FINANCIAL_BASE_ART192_193'}}
facts = read(previous['resource_paths']['fact_definitions'])
facts.extend([{'key': 'rtc.financial_provider', 'type': 'BOOLEAN', 'material': True},
    {'key': 'rtc.financial_scope_proof', 'type': 'ARRAY', 'material': True},
    {'key': 'rtc.financial_ledger', 'type': 'ARRAY', 'material': True}])
questions = read(previous['resource_paths']['questions'])
rank = max(row['rank'] for row in questions)+1
for offset, (key, kind, prompt) in enumerate([
    ('rtc.financial_provider', 'BOOLEAN', 'O fornecedor se enquadra no art183 da LC214? Informe prova independente do serviço executado.'),
    ('rtc.financial_scope_proof', 'ARRAY', 'Informe prova de fornecedor, documento, categoria, supervisão/autorização, serviço182 ou exceção184, vigência e observação.'),
    ('rtc.financial_ledger', 'ARRAY', 'Para regime específico, informe o livro mensal completo192/193, vinculado ao fornecedor/documento, reconhecimento temporal, receitas, exclusões e deduções qualificadas.')]):
    questions.append({'fact_key': key, 'answer_type': kind, 'rank': rank+offset, 'prompt': prompt})
controls = read(previous['production_controls']['path'])
controls['version'] = VERSION
for control in controls['controls']:
    if control['id'] == '4B.4.BASE_AND_SPECIAL_REGIMES':
        control['gap'] += ' Enquadramento182–184 ativado; software não financeiro de fornecedor183 integrado sob regras gerais. Base192/193 qualificada na memória; tributos/créditos/documentos financeiros específicos continuam pendentes.'
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
base = read('scenarios/consumption-integrated-2026.10.01-2/dps-integrated-iss-4a-rtc-2026.json')
base.update(rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'], knowledge_at=available)
f = base['facts']
f['rtc.financial_provider'] = True
proof = {'supplier_id': f['rtc.supplier_id'], 'document_id': f['rtc.document_id'], 'provider_category': 'BANK',
    'supervision': 'SFN_SUPERVISED', 'supervision_effective': True, 'supervision_registry_evidence_id': 'FIXTURE.SFN.REGISTRY',
    'provider_scope_evidence_id': 'FIXTURE.PROVIDER', 'operation_scope_evidence_id': 'FIXTURE.SOFTWARE.CONTRACT',
    'valid_from': '2026-01-01', 'valid_to': '2026-12-31', 'observed_on': '2026-10-01',
    'operation_category': 'OTHER_NON_FINANCIAL_SERVICE', 'not_art182_service': True,
    'non_financial_scope_evidence_id': 'FIXTURE.NONFINANCIAL.SOFTWARE'}
f['rtc.financial_scope_proof'] = [proof]
cases = {'bank-software': copy.deepcopy(base)}
for name, category in [('savings-bank-software', 'ECONOMIC_SAVINGS_BANK'), ('payment-institution-software', 'PAYMENT_INSTITUTION')]:
    case = copy.deepcopy(base)
    case['facts']['rtc.financial_scope_proof'][0]['provider_category'] = category
    cases[name] = case
for name in ['missing-proof', 'wrong-supplier', 'wrong-document', 'unknown-registry', 'future-observation', 'expired-proof',
    'unknown-nonfinancial-scope', 'unknown-role', 'wrong-general-kind', 'specific-ledger-missing']:
    case = copy.deepcopy(base); f = case['facts']; p = f['rtc.financial_scope_proof'][0]
    if name == 'missing-proof': del f['rtc.financial_scope_proof']
    elif name == 'wrong-supplier': p['supplier_id'] = 'other'
    elif name == 'wrong-document': p['document_id'] = 'other'
    elif name == 'unknown-registry': del p['supervision_registry_evidence_id']
    elif name == 'future-observation': p['observed_on'] = '2026-10-03'
    elif name == 'expired-proof': p['valid_to'] = '2026-09-30'
    elif name == 'unknown-nonfinancial-scope': del p['non_financial_scope_evidence_id']
    elif name == 'unknown-role': p['provider_category'] = 'FINTECH'
    elif name == 'wrong-general-kind': f['rtc.operation_kind'] = 'FINANCIAL'
    elif name == 'specific-ledger-missing':
        p.update(operation_category='CREDIT', provider_authorized_for_operation=True, operation_authorization_evidence_id='FIXTURE.AUTHORIZED.CREDIT')
        f['rtc.operation_kind'] = 'FINANCIAL'
    cases[name] = case
for name, case in cases.items():
    case['scenario_id'] = f'integrated-financial-scope-{name}-2026'
    write(f'scenarios/consumption-integrated-{VERSION}/{case["scenario_id"]}.json', case)
print(f'Created {bundle["bundle_id"]}: {len(cases)} integrated scope cases')
