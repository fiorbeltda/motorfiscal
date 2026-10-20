"""Versioned local PGCC activation; official membership never supplies invented fiscal dates."""
import copy
import hashlib
import json
import re
from datetime import datetime, timezone
from pathlib import Path

from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
VERSION = '2026.10.05-19'


def read(name):
    return json.loads((F / name).read_text(encoding='utf-8'))


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def write(name, value):
    path = F / name
    if path.exists():
        assert read(name) == value, f'Refusing to overwrite {path}'
        return
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open('x', encoding='utf-8') as stream:
        stream.write(json.dumps(value, ensure_ascii=False, indent=2) + '\n')


def source(name):
    return hashlib.sha256((F / 'sources/official' / name).read_bytes()).hexdigest()


annex_name = 'dere-observed-20261005-annex-i.pdf'
validation_name = 'dere-observed-20261005-validation.pdf'
cosif_name = 'cosif-bcb-completo-observed-20261002.pdf'
annex = PdfReader(F / 'sources/official' / annex_name)
members = {}
for page_no, page in enumerate(annex.pages, 1):
    text = page.extract_text()
    if members and 'Tabela 12 –' in text:
        break
    if 'Tabela 11 –' not in text:
        continue
    for match in re.finditer(r'^([1-4][0-9]{8})\s+(.*)', text, re.M):
        code, title_line = match.groups()
        if code in members:
            assert members[code]['pdf_page'] == page_no
            continue
        members[code] = {'code': code, 'source_title_line': title_line.strip(), 'pdf_page': page_no,
                         'fiscal_validity': 'NOT_PROVEN_BY_OBSERVED_DOCUMENT'}
assert all(c in members for c in ['110112001', '311011101', '411011201'])
rn = PdfReader(F / 'sources/official' / validation_name).pages[11].extract_text()
rn = rn.split('RN - Tabela de codtribs obrigatórios')[1]
aux = {}
for event in ['D1106', 'D1121', 'D2101']:
    number = event[1:]
    block = rn.split('Evento D-' + number + ':')[1].split('Evento D-')[0]
    aux[event] = re.findall(r'\b[1-4][0-9]{8}\b', block)
    assert len(aux[event]) == len(set(aux[event])), (event, aux[event])
assert {e: len(v) for e, v in aux.items()} == {'D1106': 4, 'D1121': 34, 'D2101': 2}
tax = {'version': VERSION, 'qualified': True, 'table': '11',
       'membership_basis': 'OFFICIAL_UNDATED_TABLE_AS_OF_KNOWLEDGE',
       'fiscal_validity': 'NOT_PROVEN_BY_OBSERVED_DOCUMENT', 'published_on': '2026-10-03',
       'observed_on': '2026-10-05', 'source_hash': source(annex_name), 'source_file': annex_name,
       'auxiliary_source_hash': source(validation_name), 'auxiliary_source_file': validation_name,
       'auxiliary_pdf_page': 12, 'auxiliary_lists_complete': True, 'auxiliary_codes': aux,
       'cross_catalog_unresolved': sorted({c for codes in aux.values() for c in codes if c not in members}),
       'qualification_scope': 'TABLE11_LEAF_MEMBERSHIP_AND_COMPLETE_RN_LISTS_NOT_AUTOMATIC_ACCOUNT_CLASSIFICATION',
       'codes': members, 'production_ready': False}
tax_path = f'catalogs/dere-tax-codes-{VERSION}.json'
write(tax_path, tax)

cosif = PdfReader(F / 'sources/official' / cosif_name)
selection = {226: ['1.6.0.00.00.00-7', '1.6.1.20.01.10-4'],
             505: ['7.1.0.00.00.00-6', '7.1.1.05.99.00-2'],
             545: ['8.1.0.00.00.00-5', '8.1.1.30.00.00-9']}
accounts = {}
for page_no, codes in selection.items():
    text = cosif.pages[page_no - 1].extract_text()
    for formatted in codes:
        match = re.search(r'^' + re.escape(formatted) + r' (.*?)  ([0-9]+|-)\s*$', text, re.M)
        assert match, (formatted, page_no)
        code = re.sub(r'\D', '', formatted)
        accounts[code] = {'code': code, 'formatted_code': formatted, 'title': match[1].strip(),
                          'pdf_page': page_no, 'estban': match[2], 'institution_attributes': 'NOT_PROVEN',
                          'fiscal_validity': 'NOT_PROVEN_BY_OBSERVED_DOCUMENT'}
ref = {'version': VERSION, 'qualified': True, 'planoCtaRef': '1',
       'membership_basis': 'OFFICIAL_UNDATED_TABLE_AS_OF_KNOWLEDGE',
       'fiscal_validity': 'NOT_PROVEN_BY_OBSERVED_DOCUMENT', 'observed_on': '2026-10-02',
       'source_hash': source(cosif_name), 'source_file': cosif_name,
       'source_url': 'https://www3.bcb.gov.br/aplica/cosif/completo',
       'qualification_scope': 'SIX_SELECTED_CURRENT_COSIF_MEMBERS_NO_HISTORICAL_OR_INSTITUTION_APPROVAL',
       'accounts': accounts, 'production_ready': False}
ref_path = f'catalogs/dere-cosif-reference-{VERSION}.json'
write(ref_path, ref)
policy = {'official_catalogs_required': True, 'reference_catalog_sha256': digest(ref), 'tax_catalog_sha256': digest(tax)}

previous = read('bundles/consumption-phase4b-2026.10.05-18.json')
bundle_path = f'bundles/consumption-phase4b-{VERSION}.json'
available = read(bundle_path)['knowledge_available_at'] if (F / bundle_path).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f'CONSUMPTION-PHASE4B-{VERSION}', version=VERSION,
              predecessor_bundle_id=previous['bundle_id'], knowledge_available_at=available)
bundle['official_sources'].append({'id': 'COSIF_BCB_SELECTED_20261002', 'file': cosif_name,
    'sha256': source(cosif_name), 'url': ref['source_url'], 'retrieved_on': '2026-10-02',
    'published_on': None, 'publication_verification': 'CURRENT_ELENCO_OBSERVED_NO_HISTORICAL_ACCOUNT_APPROVAL',
    'proven_effective_scope': 'Six observed members only; institution applicability and fiscal dates require independent proof.'})
codes = read(previous['resource_paths']['rtc_code_catalog'])
codes['version'] = VERSION
closing = codes['dere_monthly_closing_projection']
closing.update(pgcc_qualification_required=True, pgcc_projection_required=True,
               pgcc_catalogs={'reference': ref, 'tax_codes': tax}, pgcc_qualification_policy=policy,
               scope='LOCAL_UNSIGNED_D1001_D1011_AND_FROZEN_MONTHLY_EVENTS_WITH_INDEPENDENT_SYNTHETIC_APPLICABILITY_PROOFS')
codes['dere_pgcc_projection'] = {'contract': 'DERE-PGCC-LOCAL-2026.10.02-1', 'status': 'ACTIVE',
    'published_at': '2026-10-03', 'source_hash': source(validation_name),
    'historical_declaration_required': True, 'qualification_policy': policy,
    'reference_catalog_path': ref_path, 'tax_catalog_path': tax_path,
    'xsd': copy.deepcopy(closing['xsd']), 'scope': 'COSIF_SELECTED_MEMBERS_LOCAL_ORIGINAL_UNSIGNED_WITH_INDEPENDENT_DATED_MAPPING'}
codes_path = f'catalogs/rtc-codes-{VERSION}.json'
write(codes_path, codes)
bundle['resource_paths']['rtc_code_catalog'] = codes_path
bundle['content_hashes']['rtc_code_catalog'] = digest(codes)
bundle['catalog_versions']['rtc_codes'] = VERSION
controls = read(previous['production_controls']['path'])
controls['version'] = VERSION
for control in controls['controls']:
    if control['id'] == '4B.7.DOCUMENT':
        control['gap'] += ' Candidato19: D1011 original local, seis referências COSIF, domínio Tabela11 e RN completa de auxiliares; aplicação temporal/institucional e classificação dependem de provas externas, sintéticas no corpus. Sem aprovação histórica universal COSIF nem execução D1106/D2101.'
controls_path = f'production/phase4b-controls-{VERSION}.json'
write(controls_path, controls)
bundle['production_controls'] = {'path': controls_path, 'sha256': digest(controls)}
write(bundle_path, bundle)
write(f'sources/official/phase4b-manifest-{VERSION}.json', {'version': VERSION,
    'retrieved_on': '2026-10-05', 'status': 'SANDBOX_EVIDENCE_REVIEW_REQUIRED', 'artifacts': bundle['official_sources']})
for family in ['consumption-phase4b', 'consumption-integrated']:
    for path in sorted((F / f'scenarios/{family}-2026.10.05-18').glob('*.json')):
        case = json.loads(path.read_text(encoding='utf-8'))
        case.update(rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'], knowledge_at=available)
        write(f'scenarios/{family}-{VERSION}/{path.name}', case)


def proof(name, value):
    p = {'evidence_id': 'FIXTURE.PGCC.' + name, 'qualified': True, 'supplier_id': '11111111000191',
         'value': value, 'valid_from': '2026-01-01', 'valid_to': '2026-12-31', 'observed_on': '2026-10-05',
         'scope': 'SYNTHETIC_APPLICABILITY_AND_CLASSIFICATION_NOT_OFFICIAL_CONTRIBUTOR_APPROVAL'}
    p['source_hash'] = digest(p)
    return p


base = read('scenarios/dere-local-2026.10.05-18/qualified-monthly-closing.json')
plan = {'complete': True, 'supplier_id': '11111111000191', 'source_hash': '', 'planoCtaRef': '1',
        'freqEncerr': 'M', 'period_end': '2026-09-30', 'valid_from': '2026-01-01', 'observed_on': '2026-10-05',
        'institution_type': 'COMMERCIAL_BANK', 'institution_evidence': proof('INSTITUTION', 'COMMERCIAL_BANK'), 'accounts': []}
for internal, parent, code, nature, tax_code in [
        ('ASSET', None, '1600000007', '1', None), ('REVENUE', None, '7100000006', '4', None),
        ('EXPENSE', None, '8100000005', '5', None),
        ('interest', 'REVENUE', '7110599002', '4', '311011101'),
        ('principal', 'ASSET', '1612001104', '1', '110112001'),
        ('funding', 'EXPENSE', '8113000009', '5', '411011201')]:
    row = {'cCtaInterna': internal, 'cDbrMista': '000', 'cCta': internal + '000',
           'nomeCta': accounts[code]['title'], 'indCta': 'S' if parent is None else 'A',
           'nivelCta': 1 if parent is None else 2, 'natCta': 'C' if nature == '4' else 'D',
           'codNat': nature, 'cCtaRef': code, 'valid_from': '2026-01-01'}
    if parent is not None:
        row.update(cCtaSup=parent + '000', descCta='Mapeamento sintético para prova do contrato local; aplicação normativa não atestada.', codTrib=tax_code)
    mapping = {k: row[k] for k in ['cCta', 'cCtaRef', 'codTrib', 'natCta', 'codNat'] if k in row}
    row['mapping_evidence'] = proof(internal, {'institution_type': plan['institution_type'], 'mapping': mapping})
    plan['accounts'].append(row)
plan.pop('source_hash')
plan['source_hash'] = digest(plan)
base['pgcc'] = plan
base['pgcc_identity'] = {'generated_at': '2026-10-05T08:00:00.000-03:00', 'event_sequence': 1,
    'contributor_supplier_id': plan['supplier_id'], 'contributor_qualified': True, 'dere_start_qualified': True,
    'contributor_source_hash': base['contributor']['source_hash'],
    'dere_start_source_hash': base['contributor']['dere_start_source_hash'],
    'contributor_valid_from': '2026-01-01', 'dere_start_on': '2026-01-01', 'observed_on': '2026-10-05'}
for row in base['book']['accounts']:
    row['cCta'] += '000'
base['book']['pgcc_accounts'] = [{k: row[k] for k in ['cCta', 'indCta', 'codNat', 'valid_from']}
                                 for row in plan['accounts'] if row['indCta'] == 'A']
base['book']['pgcc_source_hash'] = plan['source_hash']
base['prerequisites'].update(pgcc_source_hash=plan['source_hash'], D1121_required=False,
                            obligations_source_hash=tax['auxiliary_source_hash'])
base['identity']['generated_at'] = '2026-10-05T08:00:00.000-03:00'
base['evidence_scope'] = 'OFFICIAL_MEMBERSHIP_AND_RN_LISTS_WITH_SYNTHETIC_INSTITUTION_HISTORY_MAPPING_AND_OPTIONAL_LEGACY_DEDUCTION_DETAIL'
write(f'scenarios/dere-local-{VERSION}/qualified-monthly-closing.json', base)
cases = {'qualified': (None, None), 'missing-institution': ('institution', 'RTC_DERE_PGCC_INSTITUTION_EVIDENCE_UNPROVEN'),
    'missing-mapping': ('mapping', 'RTC_DERE_PGCC_ACCOUNT_MAPPING_EVIDENCE_UNPROVEN'),
    'future-mapping': ('future', 'RTC_DERE_PGCC_ACCOUNT_MAPPING_EVIDENCE_UNPROVEN'),
    'expired-mapping': ('expired', 'RTC_DERE_PGCC_ACCOUNT_MAPPING_EVIDENCE_UNPROVEN'),
    'reference-absent': ('reference', 'RTC_DERE_PGCC_OFFICIAL_REFERENCE_ACCOUNT_ABSENT'),
    'tax-code-absent': ('tax', 'RTC_DERE_PGCC_OFFICIAL_TAX_CODE_ABSENT'),
    'mapping-identity-conflict': ('supplier', 'RTC_DERE_PGCC_ACCOUNT_MAPPING_EVIDENCE_UNPROVEN'),
    'auxiliary-conflict': ('aux', 'RTC_DERE_MONTHLY_PGCC_AUXILIARY_OBLIGATIONS_CONFLICT'),
    'book-conflict': ('book', 'RTC_DERE_MONTHLY_PGCC_BOOK_ACCOUNTS_CONFLICT'),
    'hierarchy-conflict': ('parent', 'RTC_DERE_PGCC_PARENT_ACCOUNT_INVALID'),
    'missing-calendar': ('calendar', 'RTC_DERE_CONTRIBUTOR_DERE_START_EVIDENCE_UNPROVEN')}
for name, (mutation, reason) in cases.items():
    case = copy.deepcopy(base)
    row = case['pgcc']['accounts'][3]
    if mutation == 'institution':
        del case['pgcc']['institution_evidence']
    elif mutation == 'mapping':
        del row['mapping_evidence']
    elif mutation in ['future', 'expired', 'supplier']:
        field, value = {'future': ('observed_on', '2026-10-06'), 'expired': ('valid_to', '2026-09-29'),
                        'supplier': ('supplier_id', '22222222000191')}[mutation]
        row['mapping_evidence'][field] = value
        row['mapping_evidence'].pop('source_hash')
        row['mapping_evidence']['source_hash'] = digest(row['mapping_evidence'])
    elif mutation == 'reference':
        row['cCtaRef'] = '9999999999'
    elif mutation == 'tax':
        row['codTrib'] = '399999999'
        row['mapping_evidence'] = proof('interest', {'institution_type': plan['institution_type'],
            'mapping': {k: row[k] for k in ['cCta', 'cCtaRef', 'codTrib', 'natCta', 'codNat']}})
    elif mutation == 'aux':
        case['prerequisites']['D1121_required'] = True
    elif mutation == 'book':
        case['book']['pgcc_accounts'][0]['codNat'] = '5'
    elif mutation == 'parent':
        row['cCtaSup'] = 'ABSENT000'
    elif mutation == 'calendar':
        case['contributor']['dere_start_qualified'] = False
    case['corpus_expectation'] = {'status': 'PROJECTED' if reason is None else 'ACTION_REQUIRED', 'reason': reason}
    write(f'scenarios/dere-pgcc-{VERSION}/{name}.json', case)
print(f'Materialized candidate19: {len(members)} Table11 leaves, six COSIF members, auxiliary lists4/34/2, twelve corpus cases.')
