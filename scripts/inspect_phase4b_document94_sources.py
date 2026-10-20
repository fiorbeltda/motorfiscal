"""Read-only exact official cells, styles and formulas; no workbook authoring or recalculation."""
from pathlib import Path
from datetime import datetime, timezone
from urllib.request import Request, urlopen
import hashlib
import json
import posixpath
import zipfile
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-document94-base-reconciliation-20261016')
NS = {'s': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
def sha(p):
    with p.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
source = json.loads((E / 'primary-annex-observation.json').read_text(encoding='utf-8'))
record = source['sources'][0]
assert record['sha256'] == '103a150dd6f56ec8edcaa3a453b59a1b0e096d7cd5025d7bd06a387f141ddf39'
path = E / record['file']; assert sha(path) == record['sha256']
selected = []
with zipfile.ZipFile(path) as archive:
    shared = ET.fromstring(archive.read('xl/sharedStrings.xml'))
    strings = [''.join(t.text or '' for t in item.findall('.//s:t', NS)) for item in shared]
    styles = ET.fromstring(archive.read('xl/styles.xml'))
    fonts = styles.find('s:fonts', NS); formats = styles.find('s:cellXfs', NS)
    relationships = {r.get('Id'): r.get('Target') for r in ET.fromstring(archive.read('xl/_rels/workbook.xml.rels'))}
    workbook = ET.fromstring(archive.read('xl/workbook.xml'))
    for sheet in workbook.findall('s:sheets/s:sheet', NS):
        title = sheet.get('name')
        if title not in ['LEIAUTE DPS_NFS-e - RT', 'RN DPS_NFS-e']: continue
        rid = sheet.get('{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id')
        target = relationships[rid]
        member = target.lstrip('/') if target.startswith('/') else posixpath.normpath('xl/' + target)
        for row in ET.fromstring(archive.read(member)).findall('s:sheetData/s:row', NS):
            cells = []
            for c in row:
                raw = c.find('s:v', NS)
                value = raw.text if raw is not None else None
                if c.get('t') == 's' and value is not None: value = strings[int(value)]
                if c.get('t') == 'inlineStr': value = ''.join(t.text or '' for t in c.findall('.//s:t', NS))
                if value is None: continue
                font = fonts[int(formats[int(c.get('s', '0'))].get('fontId', '0'))]
                strike = font.find('s:strike', NS)
                formula = c.find('s:f', NS)
                cells.append({'cell': c.get('r'), 'value': value, 'font_strike': strike is not None and strike.get('val', '1') != '0',
                    'excel_formula': formula.text if formula is not None else None})
            text = ' '.join(str(c['value']) for c in cells)
            if any(term in text for term in ['vAjusteBCIBSCBSComExt', 'vBC =', 'CST', 'cClassTrib']) or (title == 'RN DPS_NFS-e' and row.get('r') in ['599','600','601','602','603']):
                selected.append({'sheet': title, 'row': row.get('r'), 'cells': cells})
url = 'https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc/nota-tecnica-009-se-cgnfs-e-v-1-01.pdf'
with urlopen(Request(url, headers={'User-Agent':'Mozilla/5.0'}), timeout=45) as response:
    data = response.read(); assert data.startswith(b'%PDF')
    nt = {'url': url, 'observed_at': datetime.now(timezone.utc).isoformat(), 'sha256': hashlib.sha256(data).hexdigest(), 'bytes':len(data)}
with (E / 'nt009-v1-01.pdf').open('xb') as stream: stream.write(data)
assert nt['sha256'] == 'cc86c9402e317e84eb3a9f48bbd5e0b8e3dcd84f2645700190c84d45bf1ea58e'
with (E / 'exact-source-cells.json').open('x', encoding='utf-8') as stream:
    json.dump({'status':'EXACT_SOURCE_CELLS_READ_WITHOUT_EDIT_OR_RECALCULATION','annex':record,'nt009':nt,
        'selected_rows':selected,'runtime_changed':False,'production_ready':False}, stream, ensure_ascii=False, indent=2)
for entry in selected:
    if entry['sheet'] == 'RN DPS_NFS-e' and entry['row'] in ['599','600','601','602','603']:
        print(json.dumps(entry, ensure_ascii=False))
print('PASS:exact cells/styles and fresh unchanged NT009 preserved;no source or runtime changed')
