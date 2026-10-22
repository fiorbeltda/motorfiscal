"""Fork127 and materialize scalar paths from the unchanged observed XSD archive."""
import argparse
import hashlib
import json
import shutil
import xml.etree.ElementTree as ET
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FISCAL = ROOT / 'resources/fiscal'
VERSION = '2026.10.22-127-draft1'
parser = argparse.ArgumentParser()
parser.add_argument('--capture', required=True, type=Path)
args = parser.parse_args()
def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))
def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()
def canonical(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
def write(path, value):
    with path.open('x', encoding='utf-8', newline='\n') as handle:
        handle.write(json.dumps(value, ensure_ascii=False, indent=2) + '\n')

capture = read(args.capture / 'capture.json')
source = next(item for item in capture['records'] if item['file'].endswith('.zip'))
archive_path = args.capture / source['file']
assert sha(archive_path) == source['sha256'] == '25fdcecc651a67a216496ab10d7566631e6ed10f721e37d62c7845dadb0ea267'
X = '{http://www.w3.org/2001/XMLSchema}'
with zipfile.ZipFile(archive_path) as archive:
    documents = [ET.fromstring(archive.read(name)) for name in archive.namelist() if name.endswith('.xsd')]
types = {}
elements = {}
for document in documents:
    for node in document:
        name = node.get('name')
        if name and node.tag in [X+'complexType', X+'simpleType']:
            assert name not in types, name
            types[name] = node
        elif name and node.tag == X+'element':
            elements[name] = node
def children(node):
    for child in node:
        if child.tag == X+'element':
            yield child
        elif child.tag in [X+tag for tag in ['sequence', 'choice', 'all', 'group', 'complexContent', 'extension']]:
            yield from children(child)
fields = {}
def walk(element, path, visited):
    name = element.get('type', '').split(':')[-1]
    inline = element.find(X+'complexType')
    if inline is None:
        inline = element.find(X+'simpleType')
    node = inline if inline is not None else types.get(name)
    if node is None:
        return
    if node.tag == X+'simpleType':
        restriction = node.find(X+'restriction')
        data = {'xsd_type': name, 'base': restriction.get('base', '')} if restriction is not None else {'xsd_type': name}
        if restriction is not None:
            enums = [child.get('value') for child in restriction.findall(X+'enumeration')]
            if enums:
                data['enum'] = enums
            for facet in ['pattern', 'length', 'minLength', 'maxLength']:
                item = restriction.find(X+facet)
                if item is not None:
                    data[facet] = item.get('value')
        fields[path] = data
        return
    if name in visited:
        return
    for child in children(node):
        if child.get('name'):
            walk(child, path+'.'+child.get('name'), visited | {name})
walk(elements['DPS'], 'DPS', set())
fields = dict(sorted(fields.items()))
mapping = {'IBSCBS.finNFSe': 'finNFSe', 'IBSCBS.indDest': 'IBSCBS.indDest', 'IBSCBS.cIndOp': 'IBSCBS.cIndOp',
    'IBSCBS.valores.trib.gIBSCBS.CST': 'IBSCBS.valores.trib.CST',
    'IBSCBS.valores.trib.gIBSCBS.cClassTrib': 'IBSCBS.valores.trib.cClassTrib'}
assert all('DPS.infDPS.'+target in fields for target in mapping.values())
assert all('DPS.infDPS.'+old not in fields for old, new in mapping.items() if old != new)
catalog = read(FISCAL / 'catalogs/rtc-codes-2026.10.22-126-draft1.json')
bundle = read(FISCAL / 'bundles/consumption-phase4b-2026.10.22-126-draft1.json')
controls = read(FISCAL / bundle['production_controls']['path'])
source_file = 'dps127-restricted-xsd-observed20261022-20261020.zip'
source_id = 'DPS127_RESTRICTED_XSD_OBSERVED20261022'
dest = FISCAL / 'sources/official' / source_file
assert not dest.exists()
shutil.copyfile(archive_path, dest)
closure = {item['file']: item['sha256'] for item in capture['archive_members']
           if item['file'] in ['DPS_v1.01.xsd', 'tiposComplexos_v1.01.xsd', 'tiposSimples_v1.01.xsd', 'xmldsig-core-schema.xsd']}
policy = {'contract': 'DPS-LOCAL-RESTRICTED-2026.10.22-127', 'scope': 'LOCAL_STRUCTURAL_VALIDATION_ONLY',
    'environment': '2', 'source_observed_at': source['observed_at'], 'source_sha256': source['sha256'],
    'root_schema': 'DPS_v1.01.xsd', 'root_sha256': closure['DPS_v1.01.xsd'],
    'closure_members': closure, 'fields_sha256': canonical(fields), 'field_mapping': mapping,
    'remote_deployment_verified': False, 'document_obligation_qualified': False}
catalog['version'] = VERSION
catalog['dps_layout_policy127'] = policy
catalog['dps_xsd'] = {'version': 'NFSE-RESTRICTED-DPS-XSD-OBSERVED-2026.10.22-20261020',
    'source_file': source_file, 'source_sha256': source['sha256'], 'root_schema': 'DPS_v1.01.xsd'}
catalog['dps_xsd_fields'] = fields
catalog['sources']['dps_xsd'] = {'source_id': source_id, 'file': source_file, 'sha256': source['sha256'],
    'url': source['url'], 'observed_at': source['observed_at'], 'effective_from': None}
catalog['local_xml_projection'] = {'contract_version': policy['contract'],
    'scope': 'LOCAL_NO_SIGNATURE_OR_TRANSMISSION', 'schema_version': '1.01',
    'source_sha256': source['sha256'], 'requires_deterministic_snapshot_replay': True,
    'source_business_validation': 'NOT_PERFORMED', 'schema_selection': policy}
for layout in catalog['document_layouts']:
    if layout['status'] == 'ACTIVE':
        layout.update(version='RESTRICTED-LOCAL-OBSERVED-2026.10.22-127',
            published_at=source['observed_at'], xsd_source_hash=source['sha256'],
            source_hash=source['sha256'],
            note='Explicit local structural profile;coverage dates inherited,not official deployment or legal validity.')
catalog_path = FISCAL / f'catalogs/rtc-codes-{VERSION}.json'
write(catalog_path, catalog)
controls['version'] = VERSION
controls_path = FISCAL / f'production/phase4b-controls-{VERSION}.json'
write(controls_path, controls)
bundle.update(predecessor_bundle_id=bundle['bundle_id'], bundle_id='CONSUMPTION-PHASE4B-'+VERSION,
    version=VERSION, knowledge_available_at=source['observed_at'],
    notice='Local restricted XSD20/10 projection127;historical resources preserved,no official deployment or complete Phase4B acceptance.')
bundle['catalog_versions']['rtc_codes'] = VERSION
bundle['resource_paths']['rtc_code_catalog'] = 'catalogs/' + catalog_path.name
bundle['content_hashes']['rtc_code_catalog'] = canonical(catalog)
bundle['production_controls'] = {'path': 'production/'+controls_path.name, 'sha256': canonical(controls)}
bundle['official_sources'].append({'id': source_id, 'file': source_file, 'url': source['url'],
    'sha256': source['sha256'], 'observed_at': source['observed_at'], 'version': 'OBSERVED20261022-RESTRICTED20261020',
    'published_on': None, 'effective_from': None, 'proven_effective_scope': 'Local structural validation only;not official activation or obligation.'})
write(FISCAL / f'bundles/consumption-phase4b-{VERSION}.json', bundle)
report = {'policy_sha256': canonical(policy), 'fields': len(fields), 'fields_sha256': canonical(fields),
    'source_sha256': source['sha256'], 'root_sha256': closure['DPS_v1.01.xsd'], 'observed_at': source['observed_at'],
    'mapping': mapping, 'official_activation_proven': False}
write(args.capture.parent / 'preparation.json', report)
print(json.dumps(report))
