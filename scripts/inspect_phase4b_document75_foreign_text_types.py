"""Inspect exact text inheritance; do not assume every name derives from TSString."""
from pathlib import Path
import json
import hashlib
import zipfile
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-document75-foreign-party-preparation-20261014'
source = ROOT / 'resources/fiscal/sources/official/nfse-restricted-xsd-observed-20261014-20260727.zip'
with zipfile.ZipFile(source) as archive:
    raw = archive.read('tiposSimples_v1.01.xsd')
document = ET.fromstring(raw)
names = ['TSNomeRazaoSocial', 'TSDesc150', 'TSString', 'TSLogradouro', 'TSComplementoEndereco',
         'TSNumeroEndereco', 'TSBairro', 'TSCidade', 'TSEstadoProvRegiao', 'TSEmail']
selected = {node.get('name'): ET.tostring(node, encoding='unicode') for node in document if node.get('name') in names}
assert set(selected) == set(names)
with (E / 'foreign-text-types-inspection.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'EXACT_TEXT_TYPE_INHERITANCE_INSPECTED',
        'member_sha256': hashlib.sha256(raw).hexdigest(), 'types_exact_xml': selected}, stream, ensure_ascii=False, indent=2)
for name, xml in selected.items():
    print(name + '\n' + xml)
