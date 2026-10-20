"""Read all manual pages and exact preserved XSD types; no official-source edits."""
from pathlib import Path
from zipfile import ZipFile
from pypdf import PdfReader
import xml.etree.ElementTree as ET
import hashlib
import json
import subprocess

E = Path(r'D:\ProjetoSaasExports\phase4b-identity102-primary-preparation-20261016')
def save(p, value):
    with p.open('x', encoding='utf-8') as stream: json.dump(value, stream, ensure_ascii=False, indent=2)
pages = [p.extract_text() for p in PdfReader(E / 'manual-api.pdf').pages]
with (E / 'manual-full-text.txt').open('x', encoding='utf-8') as stream:
    stream.write('\n\n'.join('PHYSICAL PAGE '+str(i+1)+'\n'+t for i,t in enumerate(pages)))
report = {'manual_pages': len(pages), 'types': [], 'portal_links': {}}
ns = {'x': 'http://www.w3.org/2001/XMLSchema'}
with ZipFile(E / 'restricted-xsd.zip') as z:
    for name in z.namelist():
        if not name.endswith('.xsd'): continue
        raw = z.read(name); root = ET.fromstring(raw)
        for tag in ['simpleType', 'complexType']:
            for node in root.findall('x:'+tag, ns):
                if node.get('name') in ['TSIdDPS', 'TSSerieDPS', 'TSNumDPS', 'TCInfDPS']:
                    report['types'].append({'member': name, 'member_sha256': hashlib.sha256(raw).hexdigest(),
                        'type': node.get('name'), 'xml': ET.tostring(node, encoding='unicode')})
from html.parser import HTMLParser
class Links(HTMLParser):
    def __init__(self): super().__init__(); self.links=[]
    def handle_starttag(self, tag, attrs):
        if tag == 'a':
            href = dict(attrs).get('href', '')
            if any(s in href.lower() for s in ['.zip', '.xlsx', 'manual-']): self.links.append(href)
for name in ['production-portal.html', 'restricted-portal.html']:
    parser=Links(); parser.feed((E/name).read_text(encoding='utf-8')); report['portal_links'][name]=parser.links
save(E / 'source-inspection.json', report)
tmp=E/'tmp/pdfs'; tmp.mkdir(parents=True, exist_ok=False)
poppler=Path(r'C:\Users\gabriel.oliveira\.cache\codex-runtimes\codex-primary-runtime\dependencies\native\poppler\Library\bin\pdftoppm.exe')
subprocess.run([str(poppler), '-f', '4', '-l', '4', '-scale-to', '1400', '-png', str(E/'manual-api.pdf'), str(tmp/'manual')], check=True)
print((E/'manual-full-text.txt').read_text(encoding='utf-8'))
print(json.dumps(report, ensure_ascii=False, indent=2))
