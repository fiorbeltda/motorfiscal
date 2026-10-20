"""Freeze BCN source excerpts and actual schema topology; never replace evidence."""
from pathlib import Path
from hashlib import sha256
import json
import zipfile
import xml.etree.ElementTree as ET
from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base49-preparation-20261009'
E.mkdir(exist_ok=True)
S = ROOT / 'resources/fiscal/sources/official'
NS = {'xs': 'http://www.w3.org/2001/XMLSchema'}


def preserve(name, content):
    path = E / name
    raw = content.encode('utf-8')
    if path.exists():
        assert path.read_bytes() == raw, f'Existing evidence differs: {path}'
    else:
        with path.open('xb') as stream:
            stream.write(raw)
    return {'path': str(path.relative_to(ROOT)).replace('\\', '/'),
            'sha256': sha256(raw).hexdigest()}


sources = []
for filename, pages in [
    ('dere-observed-20261005-mod.pdf', [48, 49, 81, 82, 150, 151]),
    ('dere-observed-20261005-events.pdf', [31, 33, 34, 144, 145]),
    ('dere-observed-20261005-annex-i.pdf', [63]),
]:
    path = S / filename
    reader = PdfReader(path)
    row = {'path': str(path.relative_to(ROOT)).replace('\\', '/'),
           'sha256': sha256(path.read_bytes()).hexdigest(), 'physical_pages': pages}
    row['excerpt'] = preserve(filename + '.excerpt.txt', '\n\n'.join(
        f'PHYSICAL PAGE {page}\n{reader.pages[page - 1].extract_text()}' for page in pages))
    sources.append(row)
for filename in ['lc214-camara-observed-20261001.html',
                 'lc214-planalto-observed-20261009.html',
                 'decreto-12955-camara-observed-20261009.html']:
    path = S / filename
    sources.append({'path': str(path.relative_to(ROOT)).replace('\\', '/'),
                    'sha256': sha256(path.read_bytes()).hexdigest()})
zip_path = S / 'dere-observed-20261006-cgibs-xsd-v1-3-0.zip'
schemas = []
with zipfile.ZipFile(zip_path) as archive:
    for suffix in ['evtFechMensal-v0_0_2.xsd', 'evtRetornoMensal-v0_0_2.xsd']:
        names = [name for name in archive.namelist() if name.endswith(suffix)]
        assert len(names) == 1
        raw = archive.read(names[0])
        root = ET.fromstring(raw)
        elements = root.findall('.//xs:element', NS)
        relevant = []
        for element in elements:
            if element.get('name') in ['gUtilizBCN', 'infoBCN', 'detBCNeg', 'codBCNRaiz',
                                       'usarBCNAcum', 'metodoAproveit', 'codBCN', 'vUsarBCN',
                                       'gBCNIBS', 'gBCNCBS', 'detBCN', 'vBCNIBS', 'vBCNCBS',
                                       'vDedBCN', 'vSaldoAnt', 'vUtilPer', 'vSaldoFinal', 'perOrigem']:
                relevant.append(ET.tostring(element, encoding='unicode'))
        if suffix.startswith('evtFechMensal'):
            group = next(element for element in elements if element.get('name') == 'gUtilizBCN')
            children = [element.get('name') for element in
                        group.findall('./xs:complexType/xs:sequence/xs:element', NS)]
            assert children == ['infoBCN', 'detBCNeg'], children
        schemas.append({'zip_member': names[0], 'sha256': sha256(raw).hexdigest(),
                        'excerpt': preserve(suffix + '.excerpt.xml.txt', '\n\n'.join(relevant))})
sources.append({'path': str(zip_path.relative_to(ROOT)).replace('\\', '/'),
                'sha256': sha256(zip_path.read_bytes()).hexdigest(), 'schemas': schemas})
record = {
    'status': 'OFFICIAL_SOURCE_AND_SCHEMA_PREPARATION_ONLY',
    'sources': sources,
    'requirements': {
        'law': 'LC214 arts300/301/303; CBS Decree12955 arts273paragraph3/274/278/280',
        'tax_pools': ['IBS', 'CBS'],
        'initial_table12_scope': ['1005I', '1005C', '1010I', '1010C'],
        'same_forming_codBC_only': True,
        'future_period_only': True,
        'indexation_permitted': False,
        'expiry': '5 years from qualified last business day of origin period',
        'origin_key': 'Exact codBCN supplied by original successful D9199 return; no invented sequence',
        'closing_topology': 'gUtilizBCN contains infoBCN then sibling detBCNeg',
        'methods': {'0': 'automatic PEPS', '1': 'manual'},
        'manual_cap': 'Optional vUsarBCN; omission means available balance; excess limited with alert',
        'consumption_cap': 'Current positive base of same codBCNRaiz',
        'generated_negative': 'Separate from positive base and from prior carryforward consumption',
    },
    'unverified': ['Return reception/authenticity contract', 'Business calendar and expiry boundary',
                   'Reopening/successor closing stock reconciliation', 'Native monthly integration',
                   'Own bundle/catalog/temporal selection', 'Positive and semantic negative corpus',
                   'Snapshots/replays/XML audit and complete regression'],
    'production_ready': False, 'scope28_complete': False, 'phase4b_complete': False,
}
preserve('research-contract.json', json.dumps(record, ensure_ascii=False, indent=2) + '\n')
print('PASS: immutable BCN preparation, official page excerpts and D1199/D9199 schema topology')
