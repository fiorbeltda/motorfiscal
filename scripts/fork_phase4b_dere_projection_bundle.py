"""Bind local DeRE projection and documentary rounding without changing historical decisions."""
import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]/'resources/fiscal'
VERSION = '2026.10.02-14'


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


previous = read('bundles/consumption-phase4b-2026.10.02-13.json')
target = f'bundles/consumption-phase4b-{VERSION}.json'
available = read(target)['knowledge_available_at'] if (ROOT/target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f'CONSUMPTION-PHASE4B-{VERSION}', version=VERSION,
    predecessor_bundle_id=previous['bundle_id'], knowledge_available_at=available)
codes = read(previous['resource_paths']['rtc_code_catalog']);codes['version'] = VERSION
annex_hash = 'b52cb2cf96b176d303d64aeea0bab16e7d35bcc739478f6fc8a26b0512415feb'
codes['financial_operation']['tax'].update(rounding_contract='DERE_HALF_EVEN_8_2', rounding_source_hash=annex_hash, published_at=available)
policy = {'contract': 'DERE-FINANCIAL-LOCAL-2026.10.02-1', 'status': 'ACTIVE', 'published_at': available,
    'source_hash': annex_hash, 'scope': 'LOCAL_UNSIGNED_QUALIFIED_TRANSACTION_ALLOCATION_NOT_FULL_MONTHLY_DECLARATION', 'base_codes': {}}
for category, event, event_code, pair in [('CREDIT', 'evtOperFinanc', '2211', ('1005','1010')),
    ('SECURITIES', 'evtOperFinanc', '2211', ('1005','1010')),
    ('SECURITIZATION', 'evtAntecReceb', '2221', ('1215','1220')),
    ('FACTORING', 'evtAntecReceb', '2221', ('1235','1240'))]:
    policy['base_codes'][category] = {scope: {'code': code, 'event': event, 'event_code': event_code,
        'namespace': f'http://www.dere.gov.br/schemas/{event}/v0_0_1', 'root_schema': f'{event}-v0_0_1.xsd'}
        for scope, code in zip(['ISS','NO_ISS'],pair)}
policy['xsd'] = {'source_file': 'dere-observed-20261002-06-arquivos-xsd-v-1-2-0.zip',
    'source_sha256': 'c750f7d522400e5bd93b7ddd1d1d0d8108cd033eb250c1f10011ac57cc732818',
    'signature_source_file': 'w3c-xmldsig-core-schema-observed-20261002.xsd',
    'signature_source_sha256': 'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d'}
codes['dere_local_projection'] = policy
path=f'catalogs/rtc-codes-{VERSION}.json';write(path,codes)
bundle['resource_paths']['rtc_code_catalog']=path;bundle['content_hashes']['rtc_code_catalog']=digest(codes)
bundle['catalog_versions']['rtc_codes']=VERSION
for index in ['dere-source-observation-20261002.json','dere-supplement-observation-20261002.json']:
    for i,a in enumerate(read('sources/official/'+index)['artifacts']):
        bundle['official_sources'].append({'id': 'DERE-OBSERVED-'+a['sha256'][:12].upper(), 'url': a['url'],
            'file': Path(a['path']).name, 'sha256': a['sha256'], 'version': 'observed-2026-10-02',
            'published_on': None, 'retrieved_on': '2026-10-02', 'source_kind': 'OFFICIAL_OBSERVED_DOCUMENT_OR_SCHEMA',
            'superseded_by': None, 'publication_verification': 'REVIEW_REQUIRED',
            'proven_effective_scope': 'Observed for local development; mandatory deployment and normative publication require independent qualification.'})
controls=read(previous['production_controls']['path']);controls['version']=VERSION
for c in controls['controls']:
    if c['id']=='4B.7.DOCUMENT':c['gap']+=' DeRE: projetor transacional192/193 exige alocação mensal qualificada, corpo validado nos XSDs originais sem assinatura; eventos mensais, regras completas, implantação e ISS/4A financeiros ainda pendentes.'
    if c['id']=='4B.4.BASE_AND_SPECIAL_REGIMES':c['gap']+=' Arredondamento DeRE de débitos brutos em14; bases, gross-down e créditos específicos ainda exigem revisão completa da política documental.'
path=f'production/phase4b-controls-{VERSION}.json';write(path,controls)
bundle['production_controls']={'path':path,'sha256':digest(controls)}
write(target,bundle)
for family in ['consumption-phase4b','consumption-integrated']:
    for source in sorted((ROOT/f'scenarios/{family}-2026.10.02-13').glob('*.json')):
        case=json.loads(source.read_text(encoding='utf-8'));case.update(rule_bundle_id=bundle['bundle_id'],catalog_versions=bundle['catalog_versions'],knowledge_at=available)
        write(f'scenarios/{family}-{VERSION}/{source.name}',case)
write(f'sources/official/phase4b-manifest-{VERSION}.json',{'version':VERSION,'retrieved_on':'2026-10-02','status':'SANDBOX_EVIDENCE_REVIEW_REQUIRED','artifacts':bundle['official_sources']})
print('Materialized DeRE local candidate14 with32 bound official source hashes; original contracts preserved.')
