"""Extract preserved primary D9121 schema and physical manual pages without policy activation."""
from pathlib import Path
import hashlib
import json
import zipfile
from xml.etree import ElementTree as ET
from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal/sources/official'
E = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'
E.mkdir(exist_ok=True)

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def write(path, value):
    with path.open('x', encoding='utf-8') as stream:
        stream.write(value)

observations = []
for name in ['dere-observed-20261005-mod.pdf', 'dere-observed-20261005-events.pdf', 'dere-observed-20261005-annex-i.pdf']:
    path = F / name
    reader = PdfReader(path)
    matched = []
    excerpts = []
    for index, page in enumerate(reader.pages):
        text = page.extract_text()
        if any(term in text.replace(' ', '') for term in ['D-9121', 'D9121', 'vApurTot']):
            matched.append(index + 1)
            excerpts.append('PHYSICAL PAGE ' + str(index + 1) + '\n' + text)
    excerpt = E / (name + '.excerpt.txt')
    write(excerpt, '\n\n'.join(excerpts))
    observations.append({'source_path': path.relative_to(ROOT).as_posix(), 'source_sha256': sha(path),
        'physical_pages': matched, 'excerpt_path': excerpt.relative_to(ROOT).as_posix(), 'excerpt_sha256': sha(excerpt)})
archive = F / 'dere-observed-20261006-cgibs-xsd-v1-3-0.zip'
with zipfile.ZipFile(archive) as package:
    raw = package.read('evtRetornoTitPub-v0_0_2.xsd')
schema_path = E / 'evtRetornoTitPub-v0_0_2.xsd'
with schema_path.open('xb') as stream:
    stream.write(raw)
tree = ET.fromstring(raw)
ns = {'xs': 'http://www.w3.org/2001/XMLSchema'}
elements = [{key: element.get(key) for key in ['name', 'type', 'minOccurs', 'maxOccurs'] if element.get(key) is not None}
    for element in tree.findall('.//xs:element', ns)]
record = {'status': 'D9121_PRIMARY_SOURCE_PREPARATION_ONLY', 'sources': observations,
    'schema_archive_sha256': sha(archive), 'schema_sha256': sha(schema_path),
    'schema_target_namespace': tree.get('targetNamespace'), 'schema_elements': elements,
    'native_return_reader_implemented': False, 'current_month_composition_integrated': False,
    'government_return_authenticated': False, 'production_ready': False}
write(E / 'research-contract.json', json.dumps(record, ensure_ascii=False, indent=2))
print(json.dumps({'status': record['status'], 'pages': {o['source_path']: o['physical_pages'] for o in observations},
    'schema_elements': elements}, ensure_ascii=False))
