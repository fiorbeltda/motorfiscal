"""Record exact observed identity/address choices before implementing a new helper."""
from pathlib import Path
import hashlib
import json
import zipfile
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-document75-foreign-party-preparation-20261014'
S = ROOT / 'resources/fiscal/sources/official/nfse-restricted-xsd-observed-20261014-20260727.zip'
X = '{http://www.w3.org/2001/XMLSchema}'
assert hashlib.sha256(S.read_bytes()).hexdigest() == '6c7e0510d3ecff4454f291f4e10b742d27a4818f23aab181494f96d0ea79f3dc'
E.mkdir(exist_ok=False)
accepted = ROOT / 'storage/app/phase4b-schema74-development-20261014/schema74-development-validation.json'
assert json.loads(accepted.read_text(encoding='utf-8'))['full_suite_complete']
with zipfile.ZipFile(S) as archive:
    documents = [ET.fromstring(archive.read(name)) for name in
                 ['tiposComplexos_v1.01.xsd', 'tiposSimples_v1.01.xsd']]
types = {node.get('name'): node for document in documents for node in document
         if node.tag in {X + 'complexType', X + 'simpleType'}}
selected = {}
for name, node in types.items():
    if name in ['TSNIF', 'TSCodNaoNIF', 'TSCodPaisISO', 'TSString'] or any(
        child.tag == X + 'element' and child.get('name') in ['NIF', 'cNaoNIF', 'endExt']
        for child in node.iter()):
        selected[name] = ET.tostring(node, encoding='unicode')
assert 'TSNIF' in selected and 'TSCodNaoNIF' in selected
report = {'status': 'EXACT_FOREIGN_PARTY_SCHEMA_CHOICES_RECORDED_BEFORE_HELPER',
    'archive_sha256': hashlib.sha256(S.read_bytes()).hexdigest(), 'types_exact_xml': selected,
    'accepted74_sha256': hashlib.sha256(accepted.read_bytes()).hexdigest(),
    'accepted74_runtime': json.loads(accepted.read_text(encoding='utf-8'))['runtime_hashes'],
    'runtime_changed': False, 'nt009_migration_complete': False, 'production_ready': False}
with (E / 'foreign-party-types-inspection.json').open('x', encoding='utf-8') as stream:
    json.dump(report, stream, ensure_ascii=False, indent=2)
for name, xml in selected.items():
    print(name + '\n' + xml + '\n')
