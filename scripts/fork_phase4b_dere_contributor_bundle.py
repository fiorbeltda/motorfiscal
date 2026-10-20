"""Activate financial D1001 with official Table21; preserve candidate17 and command defaults."""
import copy
import hashlib
import json
import re
import zipfile
from datetime import datetime, timezone
from pathlib import Path

from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[1]
FISCAL = ROOT / 'resources/fiscal'
VERSION = '2026.10.05-18'
EVIDENCE = ROOT / 'storage/app/phase4b-contributor-20261005-18'


def read(path):
    return json.loads((FISCAL / path).read_text(encoding='utf-8'))


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def write(path, value):
    target = FISCAL / path
    if target.exists():
        if read(path) != value:
            raise RuntimeError(f'Refusing to overwrite {target}')
        return
    target.parent.mkdir(parents=True, exist_ok=True)
    with target.open('x', encoding='utf-8') as stream:
        stream.write(json.dumps(value, ensure_ascii=False, indent=2) + '\n')


sources = json.loads((EVIDENCE / 'source-downloads.json').read_text(encoding='utf-8'))
by_id = {row['id']: row for row in sources}
annex = by_id['DERE_20261005_ANNEX_I']
validation = by_id['DERE_20261005_VALIDATION']
events = by_id['DERE_20261005_EVENTS']
page = PdfReader(FISCAL / 'sources/official' / annex['source_file']).pages[104].extract_text()
rows = re.findall(r'^([0-9]{2}[A-Z]) (.*?)(?=\n[0-9]{2}[A-Z] |\Z)', page, re.M | re.S)
expected_codes = ['01A', '02A', '03A', '04A', '05A', '06A', '06B', '07A', '08A',
                  '09A', '09B', '09C', '09D', '09E', '09F', '09Z', '10A', '11A', '11B',
                  '12A', '13A', '13B', '14A', '15A', '16A', '17A']
assert [code for code, _ in rows] == expected_codes
catalog = {
    'version': 'DERE-TABLE21-1.3.0-OBSERVED-2026.10.05', 'table': '21', 'qualified': True,
    'qualification_scope': 'OFFICIAL_DOMAIN_MEMBERSHIP_ONLY_NO_CONTRIBUTOR_OR_CALENDAR_APPROVAL',
    'membership_basis': 'OFFICIAL_UNDATED_TABLE_AS_OF_KNOWLEDGE',
    'published_on': '2026-10-03', 'document_edition_on': '2026-10-02', 'observed_on': '2026-10-05',
    'fiscal_validity': 'NOT_SPECIFIED_BY_TABLE', 'source_hash': annex['sha256'],
    'source_file': annex['source_file'], 'source_url': annex['url'], 'pdf_page': 105, 'printed_page': 104,
    'production_ready': False,
    'codes': {code: {'code': code, 'description': ' '.join(description.split()), 'regime': '1',
                      'fiscal_validity': 'NOT_SPECIFIED_BY_TABLE'} for code, description in rows},
}
catalog_path = f'catalogs/dere-financial-activities-{VERSION}.json'
write(catalog_path, catalog)

# The official current SPED index still labels its complete archive 1.2.0.
# Re-downloading it gives the same bytes; the separately listed pilot lacks monthly events.
old_path = FISCAL / 'sources/official/dere-observed-20261002-06-arquivos-xsd-v-1-2-0.zip'
current_path = FISCAL / 'sources/official/dere-observed-20261005-sped-xsd.zip'
assert old_path.read_bytes() == current_path.read_bytes()
old = zipfile.ZipFile(old_path)
pilot = zipfile.ZipFile(FISCAL / 'sources/official/dere-observed-20261005-pilot-xsd.zip')
compatibility = {'observed_on': '2026-10-05', 'documents_version': '1.3.0',
    'current_sped_archive_label': '1.2.0 PRODUCAO', 'full_1_3_archive_verified': False,
    'current_sped_archive_sha256': hashlib.sha256(current_path.read_bytes()).hexdigest(),
    'pilot_archive_sha256': by_id['DERE_20261005_PILOT_XSD']['sha256'],
    'pilot_event_schemas': pilot.namelist(), 'event_schema_comparison': {},
    'scope': 'D1001_V1_0_1_COMPATIBLE_MONTHLY_SCHEMAS_FROZEN_1_2_0_NO_1_3_MONTHLY_HOMOLOGATION'}
for event, name in [('D1001', 'evtInfoContrib-v1_0_1.xsd'), ('D1101', 'evtBalancete-v1_0_1.xsd'),
                    ('D1121', 'evtRelDeducoes-v0_0_1.xsd'), ('D1199', 'evtFechMensal-v0_0_2.xsd')]:
    compatibility['event_schema_comparison'][event] = {'schema': name,
        'bound_schema_sha256': hashlib.sha256(old.read(name)).hexdigest(),
        'present_in_pilot': name in pilot.namelist(),
        'pilot_bytes_identical': name in pilot.namelist() and old.read(name) == pilot.read(name),
        'pilot_lines_identical': name in pilot.namelist() and old.read(name).decode('utf-8-sig').splitlines()
                                == pilot.read(name).decode('utf-8-sig').splitlines()}
assert compatibility['event_schema_comparison']['D1001']['pilot_bytes_identical']
write(f'sources/official/dere-compatibility-{VERSION}.json', compatibility)

previous = read('bundles/consumption-phase4b-2026.10.02-17.json')
bundle_path = f'bundles/consumption-phase4b-{VERSION}.json'
available = read(bundle_path)['knowledge_available_at'] if (FISCAL / bundle_path).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f'CONSUMPTION-PHASE4B-{VERSION}', version=VERSION,
              predecessor_bundle_id=previous['bundle_id'], knowledge_available_at=available)
codes = read(previous['resource_paths']['rtc_code_catalog'])
codes['version'] = VERSION
codes['dere_contributor_projection'] = {
    'strategy': 'DERE_D1001_FINANCIAL_ORIGINAL_LOCAL', 'status': 'ACTIVE', 'published_at': '2026-10-03',
    'source_hash': validation['sha256'], 'layout_source_hash': events['sha256'],
    'contract': 'DERE-CONTRIBUTOR-LOCAL-2026.10.05-1',
    'qualification_policy': {'financial_evidence_required': True},
    'scope': 'FINANCIAL_ONLY_EXTERNALLY_QUALIFIED_ACTIVE_REGISTRATION_LOCAL_UNSIGNED_ORIGINAL',
    'catalog_sha256': digest(catalog), 'catalog_path': catalog_path,
    'xsd': copy.deepcopy(codes['dere_monthly_closing_projection']['xsd']),
}
closing = codes['dere_monthly_closing_projection']
closing.update(contributor_projection_required=True, contributor_operation_activity_required=True,
    contributor_catalogs={'servFinanc': catalog},
    operation_activity_codes={'CREDIT': '01A', 'FX': '02A', 'SECURITIES': '03A', 'SECURITIZATION': '04A', 'FACTORING': '05A'},
    schema_compatibility=compatibility,
    scope='LOCAL_UNSIGNED_D1001_WITH_FROZEN_1_2_MONTHLY_PROJECTIONS_NOT_FULL_DECLARATION')
# D1011/reference catalogs remain intentionally inactive; no command default changes.
codes_path = f'catalogs/rtc-codes-{VERSION}.json'
write(codes_path, codes)
bundle['resource_paths']['rtc_code_catalog'] = codes_path
bundle['content_hashes']['rtc_code_catalog'] = digest(codes)
bundle['catalog_versions']['rtc_codes'] = VERSION

for row in sources:
    bundle['official_sources'].append({'id': row['id'], 'file': row['source_file'], 'sha256': row['sha256'],
        'url': row['url'], 'published_on': row['published_on'], 'retrieved_on': '2026-10-05',
        'publication_verification': 'OFFICIAL_RELEASE_NOTICE' if row['published_on'] else 'PILOT_RELEASE_DATE_REVIEW_REQUIRED',
        'proven_effective_scope': 'Technical domain/layout observed; no mandatory contributor start calendar or government reception established.'})
page_sources = [
    ('SPED_INDEX', 'sped-index.html', 'https://www.gov.br/sped/pt-br/assuntos/documentos-fiscais/dere/manuais-e-documentos-tecnicos/versao-atual-1'),
    ('CGIBS_INDEX', 'cgibs-index.html', 'https://www.cgibs.gov.br/declaracao-de-regimes-especificos-dere'),
    ('PUBLICATION', 'publication.html', 'https://www.cgibs.gov.br/dere-cgibs-e-receita-federal-publicam-versao-1-3-0-da-documentacao-tecnica-e-disponibilizam-manual-do-usuario'),
    ('SPED_CURRENT_XSD', 'sped-xsd.zip', 'https://www.gov.br/sped/pt-br/assuntos/documentos-fiscais/dere/manuais-e-documentos-tecnicos/versao-atual-1/06-arquivos-xsd-v-1-2-0.zip/@@download/file')]
for identifier, suffix, url in page_sources:
    name = 'dere-observed-20261005-' + suffix
    bundle['official_sources'].append({'id': 'DERE_20261005_' + identifier, 'file': name,
        'sha256': hashlib.sha256((FISCAL / 'sources/official' / name).read_bytes()).hexdigest(),
        'url': url, 'retrieved_on': '2026-10-05', 'published_on': None,
        'proven_effective_scope': 'Observed official listing/release; archive labels and deployment remain separately qualified.'})
controls = read(previous['production_controls']['path'])
controls['version'] = VERSION
for control in controls['controls']:
    if control['id'] == '4B.7.DOCUMENT':
        control['gap'] += ' D1001 financeiro local com Tabela21 oficial1.3.0 e cinco provas independentes datadas; cadastro/calendário positivos permanecem sintéticos. XSD mensal mantido1.2.0: pacote integral1.3.0 não demonstrado. PGCC/auxiliares, alterações/exclusões, demais perfis e homologação continuam pendentes.'
controls_path = f'production/phase4b-controls-{VERSION}.json'
write(controls_path, controls)
bundle['production_controls'] = {'path': controls_path, 'sha256': digest(controls)}
write(bundle_path, bundle)
write(f'sources/official/phase4b-manifest-{VERSION}.json', {'version': VERSION,
    'retrieved_on': '2026-10-05', 'status': 'SANDBOX_EVIDENCE_REVIEW_REQUIRED', 'artifacts': bundle['official_sources']})
for family in ['consumption-phase4b', 'consumption-integrated']:
    for source in sorted((FISCAL / f'scenarios/{family}-2026.10.02-17').glob('*.json')):
        case = json.loads(source.read_text(encoding='utf-8'))
        case.update(rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'], knowledge_at=available)
        write(f'scenarios/{family}-{VERSION}/{source.name}', case)

base = read('scenarios/dere-local-2026.10.02-17/qualified-monthly-closing.json')
profile = {'complete': True, 'supplier_id': base['book']['supplier_id'], 'registered_supplier_id': base['book']['supplier_id'],
    'registration_status': 'ACTIVE', 'registration_qualified': True, 'regimes_qualified': True,
    'tax_nature_qualified': True, 'dere_start_qualified': True, 'valid_from': '2026-01-01',
    'period_end': base['book']['period_end'], 'observed_on': '2026-10-05',
    'registration_validity': {'valid_from': '2026-01-01', 'valid_to': '2026-12-31'},
    'dere_start_on': '2026-01-01', 'regTribPrinc': '1', 'regTribSecund': [], 'indNatTrib': '0',
    'servFinanc': {'tpAtividades': ['01A']}, 'evidence_scope': 'SYNTHETIC_CONTRIBUTOR_AND_START_CALENDAR', 'evidence': {}}
values = {'registration': 'ACTIVE', 'regimes': {'principal': '1', 'secondary': []},
          'tax_nature': '0', 'dere_start': '2026-01-01', 'activities': ['01A']}
for name, value in values.items():
    proof = {'evidence_id': 'FIXTURE.D1001.' + name.upper(), 'supplier_id': profile['supplier_id'],
        'qualified': True, 'value': value, 'valid_from': '2026-01-01', 'valid_to': '2026-12-31',
        'observed_on': '2026-10-05', 'scope': 'SYNTHETIC_EXTERNAL_QUALIFICATION_NOT_OFFICIAL_REGISTRATION'}
    proof['source_hash'] = digest(proof)
    profile[name + '_source_hash'] = proof['source_hash']
    profile['evidence'][name] = proof
profile['source_hash'] = digest(profile)
base['contributor'] = profile
base['contributor_identity'] = {'generated_at': '2026-10-05T08:00:00.000-03:00', 'event_sequence': 1}
base['prerequisites']['contributor_source_hash'] = profile['source_hash']
base['evidence_scope'] = 'OFFICIAL_TABLE21_DOMAIN_WITH_SYNTHETIC_REGISTRATION_REGIMES_NATURE_ACTIVITIES_AND_START_PROOFS'
write(f'scenarios/dere-local-{VERSION}/qualified-monthly-closing.json', base)

cases = {'qualified': (None, None), 'invalid-activity': ('activity', 'RTC_DERE_CONTRIBUTOR_ACTIVITY_NOT_EFFECTIVE'),
    'activity-not-effective': ('expiry', 'RTC_DERE_CONTRIBUTOR_ACTIVITIES_EVIDENCE_NOT_EFFECTIVE'),
    'identity-conflict': ('identity', 'RTC_DERE_CONTRIBUTOR_REGISTRATION_IDENTITY_OR_VALUE_CONFLICT'),
    'future-observation': ('future', 'RTC_DERE_CONTRIBUTOR_ACTIVITIES_EVIDENCE_NOT_KNOWN'),
    'missing-registration': ('registration', 'RTC_DERE_CONTRIBUTOR_REGISTRATION_EVIDENCE_UNPROVEN'),
    'missing-start-calendar': ('calendar', 'RTC_DERE_CONTRIBUTOR_DERE_START_EVIDENCE_UNPROVEN'),
    'operation-activity-conflict': ('operation', 'RTC_DERE_MONTHLY_CONTRIBUTOR_OPERATION_ACTIVITY_CONFLICT')}
for name, (mutation, reason) in cases.items():
    case = copy.deepcopy(base)
    p = case['contributor']
    if mutation in ['activity', 'operation']:
        p['servFinanc']['tpAtividades'] = ['99Z' if mutation == 'activity' else '04A']
        p['evidence']['activities']['value'] = p['servFinanc']['tpAtividades']
    elif mutation == 'expiry':
        p['evidence']['activities']['valid_to'] = '2026-09-29'
    elif mutation == 'identity':
        p['evidence']['registration']['supplier_id'] = '22222222000191'
    elif mutation == 'future':
        p['evidence']['activities']['observed_on'] = '2026-10-06'
    elif mutation == 'registration':
        del p['evidence']['registration']
    elif mutation == 'calendar':
        p['dere_start_qualified'] = False
    p.pop('source_hash', None)
    p['source_hash'] = digest(p)
    case['prerequisites']['contributor_source_hash'] = p['source_hash']
    case['corpus_expectation'] = {'status': 'PROJECTED' if reason is None else 'ACTION_REQUIRED', 'reason': reason}
    write(f'scenarios/dere-contributor-{VERSION}/{name}.json', case)
print('Materialized candidate18: 26 official activities; 8 monthly corpus cases; production gate remains closed.')
