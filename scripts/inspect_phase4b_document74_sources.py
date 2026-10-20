"""Preserve source clauses, schema series facets and manually reviewed official schedule."""
from pathlib import Path
from pypdf import PdfReader
import hashlib, json, zipfile, xml.etree.ElementTree as ET
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-document74-primary-research-20261014'
def sha(path): return hashlib.sha256(path.read_bytes()).hexdigest()
observed = json.loads((E / 'primary-source-observation.json').read_text(encoding='utf-8'))
for item in observed['sources']:
    assert item['content_verified'] and sha(E / item['name']) == item['sha256']
nt = PdfReader(E / 'nt009-v1-01.pdf')
clauses = {n: nt.pages[n - 1].extract_text() for n in [15, 16, 17]}
joined = ' '.join(' '.join(clauses.values()).split())
assert 'parcela exportada' in joined and 'outra NFS-e' in joined
with (E / 'nt009-external-commerce-clauses.txt').open('x', encoding='utf-8') as stream:
    for page, text in clauses.items(): stream.write(f'PHYSICAL PAGE {page}\n{text}\n\n')
act = PdfReader(E / 'technical-act7.pdf')
with (E / 'technical-act7-full-text.txt').open('x', encoding='utf-8') as stream:
    for i, page in enumerate(act.pages, 1): stream.write(f'PHYSICAL PAGE {i}\n{page.extract_text()}\n\n')
facets = []
ns = {'xs': 'http://www.w3.org/2001/XMLSchema'}
for archive_name in ['production-xsd-0.zip', 'restricted-xsd-0.zip']:
    with zipfile.ZipFile(E / archive_name) as archive:
        for member in archive.namelist():
            if not member.lower().endswith('.xsd'): continue
            raw = archive.read(member)
            if b'TSSerieDPS' not in raw: continue
            tree = ET.fromstring(raw)
            for simple in tree.findall('.//xs:simpleType', ns):
                if simple.get('name') != 'TSSerieDPS': continue
                facets.append({'archive': archive_name, 'archive_sha256': sha(E / archive_name), 'member': member,
                    'member_sha256': hashlib.sha256(raw).hexdigest(),
                    'facets': [{'kind': node.tag.rsplit('}', 1)[-1], 'value': node.get('value')} for node in simple.findall('./xs:restriction/*', ns)]})
assert len(facets) >= 2
record = {'status': 'PRIMARY_EXTERNAL_COMMERCE_RULE_AND_ENVIRONMENT_SCHEDULE_REVIEWED_NOT_ACTIVATED',
    'nt_sha256': sha(E / 'nt009-v1-01.pdf'), 'act_sha256': sha(E / 'technical-act7.pdf'),
    'schedule_image_sha256': sha(E / 'schedule-nt009.jpeg'), 'schedule_review_method': 'DIRECT_VISUAL_REVIEW_OF_PRESERVED_OFFICIAL_IMAGE',
    'announced_schedule': [
        {'environment': 'RESTRICTED', 'date': '2026-10-01', 'package': 1, 'scope': 'NEW_GENERATING_FACTS_CST_CCLASSTRIB_POSITION_AND_VAJUSTEBC'},
        {'environment': 'RESTRICTED', 'date': '2026-10-15', 'package': 2, 'scope': 'COMEXT_MIXED_CONSUMPTION_FX_BASE_ADJUSTMENT_IMPORT_ACQUIRER_NOTE_AND_OTHER_FIELDS'},
        {'environment': 'PRODUCTION', 'date': '2026-11-03', 'package': '1_AND_2', 'scope': 'FULL_MIGRATION'},
    ],
    'export_mixed_consumption_rule': 'EXPORT_DPS_VSERV_FOREIGN_SHARE_AND_SEPARATE_DOMESTIC_NFSE',
    'import_mixed_consumption_rule': 'IMPORT_DPS_VSERV_DOMESTIC_SHARE',
    'observed_series_facets': facets, 'remote_deployment_verified': False,
    'current_scenario_dates_do_not_qualify_future_layout_automatically': True,
    'runtime_changed': False, 'production_ready': False}
with (E / 'document-research-validation.json').open('x', encoding='utf-8') as stream: json.dump(record, stream, indent=2)
print('PASS:official mixed-consumption document rule and announced environment dates preserved; series facets inventoried')
for row in facets: print(row['archive'] + ':' + json.dumps(row['facets']))
