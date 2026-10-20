"""Activate a separate local-only schema bundle after directed code quality gates."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import xml.etree.ElementTree as ET
import zipfile

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = ROOT / 'storage/app/phase4b-local-schema74-integration-20261014'
S = ROOT / 'storage/app/phase4b-document74-primary-research-20261014'
Q = ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014'
V = '2026.10.14-74-draft1'
X = '{http://www.w3.org/2001/XMLSchema}'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def save(path, value):
    with path.open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)
        stream.write('\n')


def copy_exclusive(source, target):
    with target.open('xb') as stream:
        stream.write(source.read_bytes())
    assert sha(target) == sha(source)


gate_coverage = {
    'scope74-selection-directed-v3': ['vendor/bin/pest',
        'tests/Fiscal/Unit/DpsRestrictedSchemaSelection74Test.php',
        'tests/Fiscal/Unit/DpsXmlProjectorTest.php',
        'tests/Fiscal/Unit/DpsXmlSchemaValidatorTest.php',
        'tests/Fiscal/Unit/DpsRestrictedSchemaReadiness74Test.php'],
    'scope74-selection-type-v3': ['vendor/bin/phpstan',
        'app/Modules/Fiscal/NFSe/DpsRestrictedSchemaSelection.php',
        'app/Console/Commands/ProjectDpsXmlCommand.php',
        'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php'],
    'scope74-selection-style-v3': ['vendor/bin/pint', '--test',
        'app/Modules/Fiscal/NFSe/DpsRestrictedSchemaSelection.php',
        'app/Console/Commands/ProjectDpsXmlCommand.php',
        'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php',
        'tests/Fiscal/Unit/DpsRestrictedSchemaSelection74Test.php',
        'tests/Fiscal/Unit/DpsRestrictedSchemaReadiness74Test.php'],
}
for label, required_arguments in gate_coverage.items():
    record = read(Q / (label + '.json'))
    assert record['exit_code'] == 0 and sha(Q / (label + '.log')) == record['log_sha256'], label
    assert all(argument in record['command'] for argument in required_arguments), label
    assert record['command'][:7] == ['docker', 'compose', 'exec', '-T', 'app', 'php', '-d'], label
    assert 'memory_limit=512M' in record['command'], label
assert __import__('shutil').disk_usage(ROOT).free > 100 * 1024 * 1024, 'Insufficient disk margin for small bundle installation'
assessor = ROOT / 'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php'
assert 'RTC_DPS_RESTRICTED_SCHEMA_LOCAL_ONLY' in assessor.read_text(encoding='utf-8'), 'Dedicated production guard not installed'
accepted = read(ROOT / 'storage/app/phase4b-partial-export-fx73-preparation-20261014/export-integration-development-validation.json')
assert accepted['full_suite_complete'] and accepted['full_suite']['tests'] == 2266
bundle = read(F / 'bundles/consumption-phase4b-2026.10.14-73-draft1.json')
codes = read(F / bundle['resource_paths']['rtc_code_catalog'])
observed = read(S / 'primary-source-observation.json')
source = next(row for row in observed['sources'] if row['name'] == 'restricted-xsd-0.zip')
assert source['content_verified'] and sha(S / source['name']) == source['sha256']
assert source['sha256'] == '6c7e0510d3ecff4454f291f4e10b742d27a4818f23aab181494f96d0ea79f3dc'
closure = read(S / 'schema-closure-inspection.json')['schemas'][0]
assert closure['archive'] == source['name'] and closure['root'] == 'DPS_v1.01.xsd'
with zipfile.ZipFile(S / source['name']) as archive:
    documents = []
    for member, expected in closure['members'].items():
        raw = archive.read(member)
        assert hashlib.sha256(raw).hexdigest() == expected
        documents.append(ET.fromstring(raw))
types, elements = {}, {}
for document in documents:
    for node in document:
        name = node.get('name')
        if name and node.tag in {X + 'complexType', X + 'simpleType'}:
            types[name] = node
        elif name and node.tag == X + 'element':
            elements[name] = node


def children(node):
    for child in node:
        if child.tag == X + 'element':
            yield child
        elif child.tag in {X + name for name in ['sequence', 'choice', 'all', 'group', 'complexContent', 'extension']}:
            yield from children(child)


fields = {}


def walk(element, path, visited):
    name = element.get('type', '').split(':')[-1]
    node = element.find(X + 'complexType')
    if node is None:
        node = element.find(X + 'simpleType')
    if node is None:
        node = types.get(name)
    if node is None:
        return
    if node.tag == X + 'simpleType':
        restriction = node.find(X + 'restriction')
        facets = {'xsd_type': name}
        if restriction is not None:
            facets['base'] = restriction.get('base', '')
            enum = [item.get('value') for item in restriction.findall(X + 'enumeration')]
            if enum:
                facets['enum'] = enum
            for kind in ['pattern', 'length', 'minLength', 'maxLength']:
                item = restriction.find(X + kind)
                if item is not None:
                    facets[kind] = item.get('value')
        fields[path] = facets
        return
    if name in visited:
        return
    for child in children(node):
        if child.get('name'):
            walk(child, path + '.' + child.get('name'), visited | {name})


walk(elements['DPS'], 'DPS', set())
fields = {key: value for key, value in sorted(fields.items()) if key.startswith('DPS.infDPS.')}
assert fields['DPS.infDPS.serv.comExt.mdPrestacao']['enum'] == ['0', '1', '2', '3', '4']
assert fields['DPS.infDPS.serie']['pattern'] == '[0-9]{1,4}|[0-8][0-9]{4}'
for name in ['nFatura', 'vFatura', 'nContCambio']:
    assert 'DPS.infDPS.serv.comExt.' + name not in fields
archive_name = 'nfse-restricted-xsd-observed-20261014-20260727.zip'
target_bundle = F / ('bundles/consumption-phase4b-' + V + '.json')
targets = [target_bundle, F / ('catalogs/rtc-codes-' + V + '.json'),
           F / ('production/phase4b-controls-' + V + '.json'), F / 'sources/official' / archive_name,
           E / 'restricted-schema-bundle-installation.json']
assert not any(path.exists() for path in targets), 'Exclusive targets already exist'
copy_exclusive(S / source['name'], F / 'sources/official' / archive_name)
source_id = 'NFSE_RESTRICTED_XSD_OBSERVED_20261014'
schema = {'version': 'NFSE-RESTRICTED-DPS-XSD-OBSERVED-2026.10.14', 'source_file': archive_name,
          'source_sha256': source['sha256'], 'root_schema': closure['root']}
codes['version'] = V
codes['sources']['dps_xsd'] = {'source_id': source_id, 'file': archive_name,
                              'sha256': source['sha256'], 'version': schema['version'], 'root_schema': closure['root']}
codes['dps_xsd'] = schema
codes['dps_xsd_fields'] = fields
for layout in codes['document_layouts']:
    if layout.get('status') == 'ACTIVE':
        layout['xsd_source_hash'] = source['sha256']
codes['local_xml_projection'] = {
    'contract_version': 'DPS-LOCAL-RESTRICTED-XSD-2026.10.14-74',
    'scope': 'LOCAL_NO_SIGNATURE_OR_TRANSMISSION', 'schema_version': '1.01',
    'source_sha256': source['sha256'], 'requires_deterministic_snapshot_replay': True,
    'source_business_validation': 'NOT_PERFORMED', 'known_source_conflict': None,
    'legacy_production_source_conflict': 'RTC_DPS_OFFICIAL_SERIE_PATTERN_CONFLICT',
    'schema_selection': {'scope': 'LOCAL_STRUCTURAL_VALIDATION_ONLY', 'environment': '2',
        'source_observed_at': source['observed_at'], 'root_sha256': closure['members'][closure['root']],
        'closure_members': closure['members'], 'remote_deployment_verified': False},
}
controls = read(F / bundle['production_controls']['path'])
controls['version'] = V
code_path = 'catalogs/rtc-codes-' + V + '.json'
control_path = 'production/phase4b-controls-' + V + '.json'
save(F / code_path, codes)
save(F / control_path, controls)
bundle.update(predecessor_bundle_id=bundle['bundle_id'], bundle_id='CONSUMPTION-PHASE4B-' + V,
              version=V, knowledge_available_at=datetime.now(timezone.utc).replace(microsecond=0).isoformat())
bundle['resource_paths']['rtc_code_catalog'] = code_path
bundle['content_hashes']['rtc_code_catalog'] = digest(codes)
bundle['catalog_versions']['rtc_codes'] = V
bundle['production_controls'] = {'path': control_path, 'sha256': digest(controls)}
bundle['official_sources'].append({'id': source_id, 'url': source['url'], 'file': archive_name,
    'sha256': source['sha256'], 'version': 'v1.01-20260727-observed-20261014',
    'observed_at': source['observed_at'], 'published_on': None, 'proven_effective_scope':
    'Preserved restricted XSD for local structural validation only;observation is not a deployment or obligation date.'})
save(target_bundle, bundle)
save(E / 'restricted-schema-bundle-installation.json', {'status': 'LOCAL_ONLY_RESTRICTED_SCHEMA74_BUNDLE_CREATED',
    'version': V, 'bundle_sha256': sha(target_bundle), 'schema_sha256': source['sha256'],
    'fields': len(fields), 'nt009_migration_complete': False, 'production_ready': False})
print('PASS:exclusive local-only restricted-schema bundle with exact observed facets; production not approved')
