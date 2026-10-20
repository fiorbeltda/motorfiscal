"""Freeze candidate21: D2101 empty/unmoved accounts, original unsigned event only."""
import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path
from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
VERSION = '2026.10.06-21'


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


previous = read('bundles/consumption-phase4b-2026.10.05-20.json')
bundle = copy.deepcopy(previous)
path = f'bundles/consumption-phase4b-{VERSION}.json'
available = read(path)['knowledge_available_at'] if (F / path).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
bundle.update(bundle_id=f'CONSUMPTION-PHASE4B-{VERSION}', version=VERSION, knowledge_available_at=available,
              predecessor_bundle_id=previous['bundle_id'])
source = {'id': 'DERE_CGIBS_COMPLETE_XSD_1_3_0_OBSERVED_20261006',
    'file': 'dere-observed-20261006-cgibs-xsd-v1-3-0.zip',
    'sha256': '3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd',
    'url': 'https://www.cgibs.gov.br/upload/arquivos/202610/05144621-06-arquivos-xsd-v-1-3-0.zip',
    'retrieved_on': '2026-10-06', 'published_on': None,
    'publication_verification': 'CURRENT_OFFICIAL_LINK_OBSERVED_20261006_NOT_A_FISCAL_VALIDITY_DATE',
    'proven_effective_scope': 'D2101 v0_0_3 no-movement structure only; other events retain their captured historical schemas.'}
assert hashlib.sha256((F / 'sources/official' / source['file']).read_bytes()).hexdigest() == source['sha256']
bundle['official_sources'].append(source)
codes = read(previous['resource_paths']['rtc_code_catalog'])
codes['version'] = VERSION
closing = codes['dere_monthly_closing_projection']
reference = copy.deepcopy(closing['pgcc_catalogs']['reference'])
reference['version'] = VERSION
page = PdfReader(F / 'sources/official' / reference['source_file']).pages[189].extract_text()
for code, formatted, title, estban in [
    ('1300000008', '1.3.0.00.00.00-8', 'TÍTULOS E VALORES MOBILIÁRIOS E INSTRUMENTOS FINANCEIROS DERIVATIVOS', '-'),
    ('1311033105', '1.3.1.10.33.10-5', 'Saldo Contábil Bruto', '-'),
    ('1311500009', '1.3.1.15.00.00-9', 'COTAS DE FUNDOS DE INVESTIMENTO', '130')]:
    assert formatted in page
    reference['accounts'][code] = {'code': code, 'formatted_code': formatted, 'title': title, 'estban': estban,
        'pdf_page': 190, 'institution_attributes': 'NOT_PROVEN', 'fiscal_validity': 'NOT_PROVEN_BY_OBSERVED_DOCUMENT'}
reference['qualification_scope'] = 'TEN_SELECTED_CURRENT_COSIF_MEMBERS_NO_HISTORICAL_OR_INSTITUTION_APPROVAL'
ref_path = f'catalogs/dere-cosif-reference-{VERSION}.json'
write(ref_path, reference)
closing['pgcc_catalogs']['reference'] = reference
closing['pgcc_qualification_policy']['reference_catalog_sha256'] = digest(reference)
codes['dere_pgcc_projection']['reference_catalog_path'] = ref_path
codes['dere_pgcc_projection']['qualification_policy']['reference_catalog_sha256'] = digest(reference)
closing['debt_no_movement_projection'] = {
    'contract': 'DERE-D2101-NO-MOVEMENT-LOCAL-2026.10.06-1', 'status': 'ACTIVE', 'published_at': '2026-10-06',
    'source_hash': '68a889e5ab109805e660bd5ceb613a339a1d8a43749e26826f99822d480faebd',
    'allowed_codes': ['110113001', '110113002'], 'scope': 'EMPTY_UNMOVED_ACCOUNTS_ONLY_INDEPENDENT_PROOF_REQUIRED',
    'xsd': {'source_file': source['file'], 'source_sha256': source['sha256'],
        'signature_source_file': 'w3c-xmldsig-core-schema-observed-20261002.xsd',
        'signature_source_sha256': 'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d'}}
codes_path = f'catalogs/rtc-codes-{VERSION}.json'
write(codes_path, codes)
bundle['resource_paths']['rtc_code_catalog'] = codes_path
bundle['content_hashes']['rtc_code_catalog'] = digest(codes)
bundle['catalog_versions']['rtc_codes'] = VERSION
controls = read(previous['production_controls']['path'])
controls['version'] = VERSION
for control in controls['controls']:
    if control['id'] == '4B.7.DOCUMENT':
        control['gap'] += ' Candidato21: D2101 original sem movimento, somente contas zeradas com prova independente sintética; novo XSD completo1.3.0 vinculado somente ao D2101. Movimentos, saldos não nulos, D1106 e migração dos outros eventos continuam pendentes.'
controls_path = f'production/phase4b-controls-{VERSION}.json'
write(controls_path, controls)
bundle['production_controls'] = {'path': controls_path, 'sha256': digest(controls)}
write(path, bundle)
write(f'sources/official/phase4b-manifest-{VERSION}.json', {'version': VERSION, 'retrieved_on': '2026-10-06',
    'status': 'SANDBOX_EVIDENCE_REVIEW_REQUIRED', 'artifacts': bundle['official_sources']})
for family in ['consumption-phase4b', 'consumption-integrated']:
    for p in sorted((F / f'scenarios/{family}-2026.10.05-20').glob('*.json')):
        v = json.loads(p.read_text(encoding='utf-8'))
        v.update(rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'], knowledge_at=available)
        write(f'scenarios/{family}-{VERSION}/{p.name}', v)
financial = read('scenarios/dere-d1121-binding-2026.10.05-20/financial-scenario.json')
financial.update(rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'], knowledge_at=available,
                 scenario_id='financial-d2101-no-movement-local-2026')
write(f'scenarios/dere-d2101-no-movement-{VERSION}/financial-scenario.json', financial)
base = read('scenarios/dere-local-2026.10.05-20/qualified-monthly-closing.json')
base['identity']['generated_at'] = '2026-10-06T08:00:00.000-03:00'
base['book']['observed_on'] = '2026-10-06'
base['evidence_scope'] = 'SYNTHETIC_NO_MOVEMENT_EVIDENCE_FOR_EMPTY_ACCOUNTS_NO_REAL_CONTRIBUTOR_OR_TITLE_VERIFICATION'
root = copy.deepcopy(base['pgcc']['accounts'][0])
root.update(cCtaInterna='TVM', cCta='TVM000', nomeCta='Titulos e valores mobiliarios', cCtaRef='1300000008')
base['pgcc']['accounts'].append(root)
for internal, ref, tax, title in [('debt', '1311033105', '110113001', 'Titulos com oferta publica'),
                                  ('debtfund', '1311500009', '110113002', 'Fundo exclusivo de titulos com oferta publica')]:
    account = copy.deepcopy(base['pgcc']['accounts'][4])
    account.update(cCtaInterna=internal, cCta=internal+'000', nomeCta=title, cCtaRef=ref, codTrib=tax,
                   cCtaSup='TVM000', natCta='D', codNat='1')
    base['pgcc']['accounts'].append(account)
    row = copy.deepcopy(base['book']['accounts'][1])
    row.update(cCta=account['cCta'], vSaldoInic='0.00', vMovDebt='0.00', vMovCred='0.00', vSaldoFinal='0.00', vApur='0.00', financial_entry_ids=[])
    base['book']['accounts'].append(row)
    base['book']['pgcc_accounts'].append({k: account[k] for k in ['cCta', 'indCta', 'codNat', 'valid_from']})
for a in base['pgcc']['accounts'][-3:]:
    p = a['mapping_evidence']
    p['evidence_id'] = 'FIXTURE.PGCC.'+a['cCta']
    p['value']['mapping'] = {k: a[k] for k in ['cCta', 'cCtaRef', 'codTrib', 'natCta', 'codNat'] if k in a}
    p.pop('source_hash'); p['source_hash'] = digest(p)
base['pgcc'].pop('source_hash');base['pgcc']['source_hash'] = digest(base['pgcc'])
base['book']['pgcc_source_hash'] = base['pgcc']['source_hash']
base['prerequisites'].update(pgcc_source_hash=base['pgcc']['source_hash'], D2101_required=True)


def bind(v):
    d = v['debt_no_movement']
    p = {'qualified': True, 'evidence_id': 'FIXTURE.D2101.NO_MOVEMENT', 'supplier_id': v['book']['supplier_id'],
        'observed_on': '2026-10-06', 'valid_from': '2026-09-01', 'valid_to': '2026-09-30',
        'scope': 'SYNTHETIC_COMPLETE_NO_MOVEMENT_PROOF_NOT_REMOTE_VERIFICATION',
        'value': {'supplier_id': v['book']['supplier_id'], 'period_end': v['book']['period_end'], 'semTitulos': '1',
            'account_codes': sorted(d['account_codes']), 'titles': [], 'book_input_hash': digest(v['book']),
            'pgcc_source_hash': v['pgcc']['source_hash']}}
    p['source_hash'] = digest(p)
    d['evidence'] = p


base['debt_no_movement'] = {'supplier_id': base['book']['supplier_id'], 'period_end': '2026-09-30',
    'observed_on': '2026-10-06', 'semTitulos': '1', 'titles': [], 'account_codes': ['debt000', 'debtfund000']}
bind(base)
write(f'scenarios/dere-local-{VERSION}/qualified-monthly-closing.json', base)
cases = {}
for name in ['qualified-both-codes', 'qualified-direct-only', 'qualified-fund-only', 'qualified-no-obligation']:
    v = copy.deepcopy(base)
    if name != 'qualified-both-codes':
        remove = ['debtfund000'] if name == 'qualified-direct-only' else ['debt000'] if name == 'qualified-fund-only' else ['debt000', 'debtfund000', 'TVM000']
        v['pgcc']['accounts'] = [a for a in v['pgcc']['accounts'] if a['cCta'] not in remove]
        for key in ['accounts', 'pgcc_accounts']:v['book'][key] = [a for a in v['book'][key] if a['cCta'] not in remove]
        v['pgcc'].pop('source_hash');v['pgcc']['source_hash'] = digest(v['pgcc'])
        v['book']['pgcc_source_hash'] = v['pgcc']['source_hash']
        v['prerequisites']['pgcc_source_hash'] = v['pgcc']['source_hash']
        if name == 'qualified-no-obligation':
            del v['debt_no_movement'];v['prerequisites']['D2101_required'] = False
        else:
            v['debt_no_movement']['account_codes'] = [a for a in v['debt_no_movement']['account_codes'] if a not in remove];bind(v)
    cases[name] = (v, None)
mutations = {
    'missing-declaration': 'NO_MOVEMENT_DECLARATION_REQUIRED', 'missing-proof': 'NO_MOVEMENT_EVIDENCE_UNPROVEN',
    'unqualified-proof': 'NO_MOVEMENT_EVIDENCE_UNPROVEN', 'tampered-proof': 'NO_MOVEMENT_EVIDENCE_UNPROVEN',
    'wrong-proof-supplier': 'NO_MOVEMENT_EVIDENCE_UNPROVEN', 'book-hash-conflict': 'NO_MOVEMENT_EVIDENCE_UNPROVEN',
    'future-proof': 'EVIDENCE_PERIOD_OR_OBSERVATION_CONFLICT', 'expired-proof': 'EVIDENCE_PERIOD_OR_OBSERVATION_CONFLICT',
    'incomplete-proof-month': 'EVIDENCE_PERIOD_OR_OBSERVATION_CONFLICT',
    'missing-account': 'ACCOUNT_SCOPE_CONFLICT', 'duplicate-account': 'ACCOUNT_SCOPE_CONFLICT',
    'other-supplier': 'ACCOUNT_SCOPE_CONFLICT', 'other-period': 'ACCOUNT_SCOPE_CONFLICT',
    'title-detail': 'NO_MOVEMENT_DECLARATION_REQUIRED', 'indicator-conflict': 'NO_MOVEMENT_DECLARATION_REQUIRED',
    'nonzero-balance': 'NONZERO_ACCOUNT_DETAIL_REQUIRED', 'nonzero-movement': 'NONZERO_ACCOUNT_DETAIL_REQUIRED',
    'generation-before-evidence': 'GENERATION_DATE_CONFLICT'}
for name, reason in mutations.items():
    v = copy.deepcopy(base); d = v['debt_no_movement'];p = d['evidence']
    if name == 'missing-declaration':del v['debt_no_movement']
    elif name == 'missing-proof':del d['evidence']
    elif name == 'unqualified-proof':p['qualified'] = False
    elif name == 'tampered-proof':p['evidence_id'] += '.tampered'
    elif name == 'wrong-proof-supplier':p['supplier_id'] = '22222222000191'
    elif name == 'book-hash-conflict':p['value']['book_input_hash'] = '0'*64
    elif name == 'future-proof':p['observed_on'] = '2026-10-07'
    elif name == 'expired-proof':p['valid_to'] = '2026-09-29'
    elif name == 'incomplete-proof-month':p['valid_from'] = '2026-09-02'
    elif name == 'missing-account':d['account_codes'].pop()
    elif name == 'duplicate-account':d['account_codes'].append(d['account_codes'][0])
    elif name == 'other-supplier':d['supplier_id'] = '22222222000191'
    elif name == 'other-period':d['period_end'] = '2026-08-31'
    elif name == 'title-detail':d['titles'] = [{'idTitulo': 'BR0000000000'}]
    elif name == 'indicator-conflict':d['semTitulos'] = '0'
    elif name == 'nonzero-balance':
        v['book']['accounts'][-2]['vSaldoInic'] = v['book']['accounts'][-2]['vSaldoFinal'] = '100.00'
        v['book']['accounts'][-2]['previous_balance']['amount'] = '100.00';bind(v)
    elif name == 'nonzero-movement':
        v['book']['accounts'][-2]['vMovDebt'] = v['book']['accounts'][-2]['vMovCred'] = '100.00'
        v['book']['accounts'][-2]['vAjusteDebt'] = '100.00';bind(v)
    elif name == 'generation-before-evidence':v['identity']['generated_at'] = '2026-10-05T08:00:00.000-03:00'
    if name in ['unqualified-proof', 'wrong-proof-supplier', 'book-hash-conflict', 'future-proof', 'expired-proof', 'incomplete-proof-month']:
        p.pop('source_hash');p['source_hash'] = digest(p)
    cases[name] = (v, 'RTC_DERE_D2101_'+reason)
v = copy.deepcopy(base);v['prerequisites']['D2101_required'] = False
cases['obligation-conflict'] = (v, 'RTC_DERE_MONTHLY_PGCC_AUXILIARY_OBLIGATIONS_CONFLICT')
v = copy.deepcopy(cases['qualified-no-obligation'][0]);v['debt_no_movement'] = base['debt_no_movement']
cases['unexpected-declaration'] = (v, 'RTC_DERE_MONTHLY_D2101_UNEXPECTED_DECLARATION')
for name, (v, reason) in cases.items():
    v['corpus_expectation'] = {'status': 'PROJECTED' if reason is None else 'ACTION_REQUIRED', 'reason': reason}
    write(f'scenarios/dere-d2101-no-movement-{VERSION}/{name}.json', v)
print(f'Candidate21 frozen: {len(cases)} D2101 cases, {len(bundle["official_sources"])} sources, production closed.')
