"""Inspect immutable official XSD closure and fields without activating a layout."""
from pathlib import Path, PurePosixPath
import hashlib
import json
import zipfile
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-document74-primary-research-20261014'
NS = {'xs': 'http://www.w3.org/2001/XMLSchema'}
observed = json.loads((E / 'primary-source-observation.json').read_text(encoding='utf-8'))
sources = {item['name']: item for item in observed['sources']}
reports = []
for name, root in [('restricted-xsd-0.zip', 'DPS_v1.01.xsd'),
                   ('production-xsd-0.zip', 'Schemas/1.01/DPS_v1.01.xsd')]:
    path = E / name
    assert hashlib.sha256(path.read_bytes()).hexdigest() == sources[name]['sha256']
    with zipfile.ZipFile(path) as archive:
        seen, pending, dependencies, fields = {}, [root], [], []
        while pending:
            member = pending.pop()
            if member in seen:
                continue
            raw = archive.read(member)
            seen[member] = hashlib.sha256(raw).hexdigest()
            tree = ET.fromstring(raw)
            for kind in ['include', 'import', 'redefine']:
                for node in tree.findall('xs:' + kind, NS):
                    location = node.get('schemaLocation')
                    assert location and ':' not in location and not location.startswith('/')
                    target = str(PurePosixPath(member).parent / location)
                    assert '..' not in PurePosixPath(target).parts
                    assert target in archive.namelist(), target
                    dependencies.append({'from': member, 'kind': kind, 'to': target})
                    pending.append(target)
            for node in tree.findall('.//xs:element', NS):
                if node.get('name') in ['comExt', 'mdPrestacao', 'nFatura', 'vFatura',
                                       'nContCambio', 'nDUIMP', 'nDUE',
                                       'vAjusteBCIBSCBSComExt', 'indDest', 'dest']:
                    fields.append({'member': member, 'attributes': dict(node.attrib)})
        reports.append({'archive': name, 'archive_sha256': sources[name]['sha256'],
                        'root': root, 'members': seen, 'dependencies': dependencies,
                        'observed_fields': fields})
report = {'status': 'OFFICIAL_DPS_SCHEMA_CLOSURES_INSPECTED_NO_ACTIVATION',
          'schemas': reports, 'remote_deployment_verified': False,
          'runtime_changed': False, 'production_ready': False}
with (E / 'schema-closure-inspection.json').open('x', encoding='utf-8') as stream:
    json.dump(report, stream, indent=2)
for item in reports:
    print(item['archive'], 'closure_members=', len(item['members']),
          'fields=', [field['attributes']['name'] for field in item['observed_fields']])
