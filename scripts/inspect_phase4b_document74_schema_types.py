"""Record exact official simple-type facets and comExt sequence without schema normalization."""
from pathlib import Path
import hashlib
import json
import zipfile
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-document74-primary-research-20261014'
NS = {'xs': 'http://www.w3.org/2001/XMLSchema'}
source = json.loads((E / 'primary-source-observation.json').read_text(encoding='utf-8'))
sources = {row['name']: row for row in source['sources']}
records = []
for name, prefix in [('restricted-xsd-0.zip', ''), ('production-xsd-0.zip', 'Schemas/1.01/')]:
    path = E / name
    assert hashlib.sha256(path.read_bytes()).hexdigest() == sources[name]['sha256']
    with zipfile.ZipFile(path) as archive:
        simple_name = prefix + 'tiposSimples_v1.01.xsd'
        complex_name = prefix + 'tiposComplexos_v1.01.xsd'
        simple = ET.fromstring(archive.read(simple_name))
        complex_tree = ET.fromstring(archive.read(complex_name))
        facets = {}
        for node in simple.findall('xs:simpleType', NS):
            if node.get('name') not in ['TSSerieDPS', 'TSModoPrestacao', 'TSCNPJ', 'TSRTCFinNFSe', 'TSRTCIndDest']:
                continue
            facets[node.get('name')] = ET.tostring(node, encoding='unicode')
        exterior = next(node for node in complex_tree.findall('xs:complexType', NS)
                        if node.get('name') == 'TCComExterior')
        sequence = [dict(node.attrib) for node in exterior.findall('xs:sequence/xs:element', NS)]
        record = {'archive': name, 'archive_sha256': sources[name]['sha256'],
                  'simple_member': simple_name, 'simple_member_sha256': hashlib.sha256(archive.read(simple_name)).hexdigest(),
                  'complex_member': complex_name, 'complex_member_sha256': hashlib.sha256(archive.read(complex_name)).hexdigest(),
                  'selected_simple_types_exact_xml': facets, 'comExt_sequence': sequence}
        records.append(record)
        print(name, 'comExt=', [node['name'] for node in sequence])
        print('TSModoPrestacao=', facets.get('TSModoPrestacao'))
with (E / 'schema-types-inspection.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'EXACT_OFFICIAL_TYPE_FACETS_AND_COMEXT_SEQUENCE_RECORDED',
               'schemas': records, 'runtime_changed': False, 'production_ready': False}, stream, indent=2)
