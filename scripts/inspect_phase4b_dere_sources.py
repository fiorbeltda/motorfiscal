"""Inspect preserved official schemas without changing or activating their contracts."""
import hashlib
import json
import zipfile
from pathlib import Path
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
index = json.loads((ROOT/'resources/fiscal/sources/official/dere-source-observation-20261002.json').read_text(encoding='utf-8'))
artifacts = []
for source in index['artifacts']:
    path = ROOT/source['path']
    assert hashlib.sha256(path.read_bytes()).hexdigest() == source['sha256'], path
    if path.suffix != '.zip':
        continue
    with zipfile.ZipFile(path) as archive:
        names = archive.namelist()
        for name in sorted(names):
            if not name.endswith('.xsd'):
                continue
            data = archive.read(name)
            root = ET.fromstring(data)
            dependencies = []
            for node in root:
                if node.tag.rsplit('}', 1)[-1] not in ['import', 'include']:
                    continue
                location = node.get('schemaLocation')
                dependencies.append({'location': location, 'included_in_archive': location in names})
            artifacts.append({'archive_path': source['path'], 'member': name,
                'sha256': hashlib.sha256(data).hexdigest(), 'target_namespace': root.get('targetNamespace'),
                'dependencies': dependencies})
result = {'status': 'SOURCE_INVENTORY_ONLY_NOT_SCHEMA_OR_DOCUMENT_APPROVAL', 'source_artifacts_checked': len(index['artifacts']),
    'schemas': artifacts, 'missing_archive_dependencies': sorted(set(d['location'] for a in artifacts for d in a['dependencies'] if not d['included_in_archive'])),
    'production_ready': False}
target = ROOT/'storage/app/phase4b-dere-source-inspection-20261002.json'
payload = json.dumps(result, ensure_ascii=False, indent=2)+'\n'
if target.exists() and target.read_text(encoding='utf-8') != payload:
    raise RuntimeError(f'Refusing to overwrite {target}')
target.parent.mkdir(parents=True, exist_ok=True)
target.write_text(payload, encoding='utf-8')
print('Checked', len(index['artifacts']), 'preserved artifacts;', len(artifacts), 'schemas; missing package dependencies:', result['missing_archive_dependencies'])
