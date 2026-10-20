"""Activate PGCC-bound D1121 for the local art192 correspondent path; freeze predecessors."""
import copy
import hashlib
import json
import re
from datetime import datetime, timezone
from pathlib import Path
from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
VERSION = '2026.10.05-20'


def read(path):
    return json.loads((F / path).read_text(encoding='utf-8'))


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def write(path, value):
    target = F / path
    if target.exists():
        assert read(path) == value, f'Refusing to overwrite {target}'
        return
    target.parent.mkdir(parents=True, exist_ok=True)
    with target.open('x', encoding='utf-8') as stream:
        stream.write(json.dumps(value, ensure_ascii=False, indent=2) + '\n')


previous = read('bundles/consumption-phase4b-2026.10.05-19.json')
bundle_path = f'bundles/consumption-phase4b-{VERSION}.json'
available = read(bundle_path)['knowledge_available_at'] if (F / bundle_path).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f'CONSUMPTION-PHASE4B-{VERSION}', version=VERSION,
              predecessor_bundle_id=previous['bundle_id'], knowledge_available_at=available)
codes = read(previous['resource_paths']['rtc_code_catalog'])
codes['version'] = VERSION
closing = codes['dere_monthly_closing_projection']
ref = copy.deepcopy(closing['pgcc_catalogs']['reference'])
ref['version'] = VERSION
page = PdfReader(F / 'sources/official' / ref['source_file']).pages[550].extract_text()
formatted = '8.1.9.99.00.00-6'
match = re.search(r'^' + re.escape(formatted) + r' (.*?)  ([0-9]+|-)\s*$', page, re.M)
assert match
ref['accounts']['8199900006'] = {'code': '8199900006', 'formatted_code': formatted, 'title': match[1],
    'pdf_page': 551, 'estban': match[2], 'institution_attributes': 'NOT_PROVEN',
    'fiscal_validity': 'NOT_PROVEN_BY_OBSERVED_DOCUMENT'}
ref['qualification_scope'] = 'SEVEN_SELECTED_CURRENT_COSIF_MEMBERS_NO_HISTORICAL_OR_INSTITUTION_APPROVAL'
ref_path = f'catalogs/dere-cosif-reference-{VERSION}.json'
write(ref_path, ref)
closing['pgcc_catalogs']['reference'] = ref
closing['pgcc_qualification_policy']['reference_catalog_sha256'] = digest(ref)
codes['dere_pgcc_projection']['qualification_policy']['reference_catalog_sha256'] = digest(ref)
codes['dere_pgcc_projection']['reference_catalog_path'] = ref_path
closing.update(pgcc_deduction_binding_required=True,
    deduction_entry_mappings={'411011105': {'kind': 'ADVISOR_EXPENSE', 'advisor_category': 'BCB_REGISTERED_CORRESPONDENT',
        'source_hash': closing['pgcc_catalogs']['tax_codes']['source_hash'], 'pdf_page': 34,
        'scope': 'QUALIFIED_CORRESPONDENT_ART192_ONLY_NOT_ALL_D1121_ACTIVITIES'}},
    scope='LOCAL_UNSIGNED_PGCC_BOUND_D1121_FOR_ART192_CORRESPONDENT_EXCLUDES_OTHER_FINANCIAL_DEDUCTIONS')
codes_path = f'catalogs/rtc-codes-{VERSION}.json'
write(codes_path, codes)
bundle['resource_paths']['rtc_code_catalog'] = codes_path
bundle['content_hashes']['rtc_code_catalog'] = digest(codes)
bundle['catalog_versions']['rtc_codes'] = VERSION
controls = read(previous['production_controls']['path'])
controls['version'] = VERSION
for control in controls['controls']:
    if control['id'] == '4B.7.DOCUMENT':
        control['gap'] += ' Candidato20: D1121 obrigatório411011105 reconcilia documento/conta/lançamento de correspondente, separando captação; provas e histórico positivos sintéticos. Demais códigos/atividades D1121, fonte documental real e recepção continuam pendentes.'
control_path = f'production/phase4b-controls-{VERSION}.json'
write(control_path, controls)
bundle['production_controls'] = {'path': control_path, 'sha256': digest(controls)}
write(bundle_path, bundle)
write(f'sources/official/phase4b-manifest-{VERSION}.json', {'version': VERSION, 'retrieved_on': '2026-10-05',
    'status': 'SANDBOX_EVIDENCE_REVIEW_REQUIRED', 'artifacts': bundle['official_sources']})
for family in ['consumption-phase4b', 'consumption-integrated']:
    for path in sorted((F / f'scenarios/{family}-2026.10.05-19').glob('*.json')):
        value = json.loads(path.read_text(encoding='utf-8'))
        value.update(rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'], knowledge_at=available)
        write(f'scenarios/{family}-{VERSION}/{path.name}', value)

scenario = read(f'scenarios/consumption-phase4b-{VERSION}/financial-borrower-credit-loan-2026.json')
scenario['scenario_id'] = 'financial-d1121-pgcc-correspondent-2026'
entry = copy.deepcopy(scenario['facts']['rtc.financial_ledger'][0]['entries'][2])
entry.update(id='correspondent', kind='ADVISOR_EXPENSE', document_id='correspondent1', amount='100.00',
    not_employee_or_administrator=True, advisor_category='BCB_REGISTERED_CORRESPONDENT',
    advisor_registration_evidence_id='FIXTURE.CORRESPONDENT.BCB.REGISTRATION')
scenario['facts']['rtc.financial_ledger'][0]['entries'].append(entry)
write(f'scenarios/dere-d1121-binding-{VERSION}/financial-scenario.json', scenario)

base = read('scenarios/dere-local-2026.10.05-19/qualified-monthly-closing.json')
account = copy.deepcopy(base['pgcc']['accounts'][5])
account.update(cCtaInterna='correspondent', cCta='correspondent000', nomeCta='Despesa de correspondente bancario',
    cCtaRef='8199900006', codTrib='411011105')
mapping = account['mapping_evidence']
mapping['evidence_id'] = 'FIXTURE.PGCC.CORRESPONDENT'
mapping['value']['mapping'] = {k: account[k] for k in ['cCta', 'cCtaRef', 'codTrib', 'natCta', 'codNat']}
mapping.pop('source_hash'); mapping['source_hash'] = digest(mapping)
base['pgcc']['accounts'].append(account)
base['pgcc'].pop('source_hash'); base['pgcc']['source_hash'] = digest(base['pgcc'])
base['book']['pgcc_source_hash'] = base['pgcc']['source_hash']
base['prerequisites'].update(pgcc_source_hash=base['pgcc']['source_hash'], D1121_required=True)
balance = copy.deepcopy(base['book']['accounts'][2])
balance.update(cCta='correspondent000', vMovDebt='100.00', vSaldoFinal='100.00', vApur='100.00', financial_entry_ids=['correspondent'])
base['book']['accounts'].append(balance)
base['book']['pgcc_accounts'].append({k: account[k] for k in ['cCta', 'indCta', 'codNat', 'valid_from']})
doc = base['deductions']['documents'][0]
doc.update(vOper='100.00', vDedTotal='100.00', vDed='100.00', financial_entry_ids=['correspondent'],
           pgcc_account_codes=['correspondent000'])
doc['items'] = []
base['deductions']['observed_on'] = '2026-10-05'
base['evidence_scope'] = 'OFFICIAL_PGCC_RN_DOMAIN_WITH_SYNTHETIC_CORRESPONDENT_DOCUMENT_AND_ACCOUNT_BINDING_PROOFS'


def bind(value):
    for document in value.get('deductions', {}).get('documents', []):
        evidence = {'qualified': True, 'supplier_id': value['book']['supplier_id'],
            'evidence_id': 'FIXTURE.D1121.DOCUMENT.ACCOUNT', 'observed_on': '2026-10-05',
            'valid_from': '2026-09-01', 'valid_to': '2026-09-30',
            'scope': 'SYNTHETIC_DOCUMENT_ACCOUNT_BINDING_NO_REMOTE_VERIFICATION',
            'value': {k: document[k] for k in ['chDFe', 'tpDFe', 'dtEmi', 'tpAtiv', 'vDed', 'financial_entry_ids', 'pgcc_account_codes']}}
        evidence['value']['period_end'] = value['book']['period_end']
        evidence['source_hash'] = digest(evidence)
        document['account_binding_evidence'] = evidence


bind(base)
write(f'scenarios/dere-local-{VERSION}/qualified-monthly-closing.json', base)
cases = {'qualified': (None, None), 'missing-document': ('document', 'RTC_DERE_D1121_PGCC_DOCUMENTS_REQUIRED'),
    'missing-binding': ('binding', 'RTC_DERE_D1121_PGCC_DOCUMENT_ACCOUNT_EVIDENCE_UNPROVEN'),
    'binding-identity-conflict': ('supplier', 'RTC_DERE_D1121_PGCC_DOCUMENT_ACCOUNT_EVIDENCE_UNPROVEN'),
    'future-binding': ('future', 'RTC_DERE_D1121_PGCC_DOCUMENT_ACCOUNT_EVIDENCE_UNPROVEN'),
    'expired-binding': ('expired', 'RTC_DERE_D1121_PGCC_DOCUMENT_ACCOUNT_EVIDENCE_UNPROVEN'),
    'account-conflict': ('account', 'RTC_DERE_D1121_PGCC_DOCUMENT_PGCC_ACCOUNT_CONFLICT'),
    'non-reportable-funding': ('funding', 'RTC_DERE_D1121_PGCC_DOCUMENT_ENTRY_NOT_REPORTABLE'),
    'amount-conflict': ('amount', 'RTC_DERE_DEDUCTIONS_FINANCIAL_LINK_AMOUNT_CONFLICT'),
    'ceiling-exceeded': ('ceiling', 'RTC_DERE_DEDUCTIONS_DOCUMENT_DEDUCTION_CEILING_EXCEEDED'),
    'duplicate-link': ('duplicate', 'RTC_DERE_D1121_PGCC_DOCUMENT_ENTRY_DUPLICATE'),
    'binding-tampered': ('tamper', 'RTC_DERE_D1121_PGCC_DOCUMENT_ACCOUNT_EVIDENCE_UNPROVEN'),
    'auxiliary-conflict': ('aux', 'RTC_DERE_MONTHLY_PGCC_AUXILIARY_OBLIGATIONS_CONFLICT')}
for name, (mutation, reason) in cases.items():
    value = copy.deepcopy(base)
    d = value['deductions']['documents'][0]
    if mutation == 'document': del value['deductions']
    elif mutation == 'binding': del d['account_binding_evidence']
    elif mutation in ['supplier', 'future', 'expired']:
        field, changed = {'supplier': ('supplier_id', '22222222000191'), 'future': ('observed_on', '2026-10-06'),
                          'expired': ('valid_to', '2026-09-29')}[mutation]
        p = d['account_binding_evidence']; p[field] = changed; p.pop('source_hash'); p['source_hash'] = digest(p)
    elif mutation == 'account': d['pgcc_account_codes'] = ['funding000']; bind(value)
    elif mutation == 'funding': d['financial_entry_ids'] = ['funding']; d['pgcc_account_codes'] = ['funding000']; bind(value)
    elif mutation == 'amount': d['vDed'] = '99.00'; bind(value)
    elif mutation == 'ceiling': d['vDedTotal'] = '99.00'
    elif mutation == 'duplicate': value['deductions']['documents'].append(copy.deepcopy(d))
    elif mutation == 'tamper': d['account_binding_evidence']['value']['vDed'] = '0.00'
    elif mutation == 'aux': value['prerequisites']['D1121_required'] = False
    value['corpus_expectation'] = {'status': 'PROJECTED' if reason is None else 'ACTION_REQUIRED', 'reason': reason}
    write(f'scenarios/dere-d1121-binding-{VERSION}/{name}.json', value)
for name, change in [('financial-scenario-zero.json', 'zero'), ('financial-scenario-funding-only.json', 'funding'),
                     ('financial-scenario-classification-conflict.json', 'classification')]:
    value = copy.deepcopy(scenario)
    value['scenario_id'] += '-' + change
    if change == 'zero': value['facts']['rtc.financial_ledger'][0]['entries'][3]['amount'] = '0.00'
    elif change == 'funding': value['facts']['rtc.financial_ledger'][0]['entries'].pop()
    else: value['facts']['rtc.financial_ledger'][0]['entries'][3]['kind'] = 'FUNDING_EXPENSE'
    write(f'scenarios/dere-d1121-binding-{VERSION}/{name}', value)

for name, mutation, reason in [
    ('qualified-continuation', 'continuation', None),
    ('qualified-no-appropriation', 'zero', None), ('qualified-funding-only', 'funding', None),
    ('entry-classification-conflict', 'classification', 'RTC_DERE_D1121_PGCC_ENTRY_CODE_CLASSIFICATION_CONFLICT'),
    ('historical-document-gap', 'history', 'RTC_DERE_DEDUCTIONS_PREVIOUS_DOCUMENT_ACCOUNT_UNPROVEN'),
    ('unsupported-code', 'unsupported', 'RTC_DERE_D1121_PGCC_CODE_MAPPING_NOT_SUPPORTED')]:
    value = copy.deepcopy(base)
    if mutation in ['continuation', 'history']:
        doc = value['deductions']['documents'][0]
        doc.update(dtEmi='2026-08-20', vOper='150.00')
        doc.pop('vDedTotal')
        doc['previous_deduction'] = {'source_hash': 'c' * 64, 'chDFe': doc['chDFe'], 'dtEmi': doc['dtEmi'],
            'supplier_id': value['book']['supplier_id'], 'tpDFe': doc['tpDFe'], 'tpAtiv': doc['tpAtiv'],
            'vOper': doc['vOper'], 'registered_period': '2026-08', 'history_complete': True, 'through_period': '2026-08',
            'vDedTotal': '150.00', 'accumulated_deduction': '50.00', 'itemized': False}
        if mutation == 'history': doc['previous_deduction']['through_period'] = '2026-07'
        bind(value)
    elif mutation in ['zero', 'funding']:
        value.pop('deductions')
        value['financial_scenario'] = 'financial-scenario-zero.json' if mutation == 'zero' else 'financial-scenario-funding-only.json'
        if mutation == 'zero':
            for key in ['vMovDebt', 'vSaldoFinal', 'vApur']: value['book']['accounts'][3][key] = '0.00'
        else:
            value['pgcc']['accounts'].pop(); value['book']['accounts'].pop(); value['book']['pgcc_accounts'].pop()
            value['pgcc'].pop('source_hash'); value['pgcc']['source_hash'] = digest(value['pgcc'])
            value['book']['pgcc_source_hash'] = value['pgcc']['source_hash']
            value['prerequisites'].update(pgcc_source_hash=value['pgcc']['source_hash'], D1121_required=False)
    elif mutation == 'classification': value['financial_scenario'] = 'financial-scenario-classification-conflict.json'
    elif mutation == 'unsupported':
        row = value['pgcc']['accounts'][6]; row['codTrib'] = '411012101'
        row['mapping_evidence']['value']['mapping']['codTrib'] = row['codTrib']
        row['mapping_evidence'].pop('source_hash'); row['mapping_evidence']['source_hash'] = digest(row['mapping_evidence'])
        value['pgcc'].pop('source_hash'); value['pgcc']['source_hash'] = digest(value['pgcc'])
        value['book']['pgcc_source_hash'] = value['pgcc']['source_hash']
        value['prerequisites']['pgcc_source_hash'] = value['pgcc']['source_hash']
    value['corpus_expectation'] = {'status': 'PROJECTED' if reason is None else 'ACTION_REQUIRED', 'reason': reason}
    write(f'scenarios/dere-d1121-binding-{VERSION}/{name}.json', value)
print('Materialized candidate20: seven COSIF members, PGCC-bound correspondent D1121, nineteen monthly corpus cases.')
