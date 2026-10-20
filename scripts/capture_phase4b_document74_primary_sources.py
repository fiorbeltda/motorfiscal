"""Capture current official documentary sources separately from activated schemas."""
from pathlib import Path
from datetime import datetime, timezone
from html.parser import HTMLParser
from urllib.request import Request, urlopen
from urllib.parse import urljoin
import hashlib, json, zipfile, io

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-document74-primary-research-20261014'
E.mkdir(exist_ok=False)
sources = [
    ('rtc-portal.html', 'https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc'),
    ('production-portal.html', 'https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/documentacao-atual'),
    ('restricted-portal.html', 'https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita'),
    ('nt009-v1-01.pdf', 'https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc/nota-tecnica-009-se-cgnfs-e-v-1-01.pdf'),
    ('technical-act7.pdf', 'https://www.cgibs.gov.br/upload/arquivos/202610/01120218-nfse-e-nfsevia-atc-rfb-sufis-cgibs-diretoria-executiva-n-7-de-28-de-setembro-de-2026.pdf'),
    ('schedule-nt009.jpeg', 'https://www.gov.br/nfse/pt-br/noticias/publicado-o-ato-tecnico-conjunto-rfb-cgibs-no-7-aprovando-a-versao-1-01-da-nota-tecnica-009-nfs-e-e-a-nota-tecnica-010-da-nfs-e-via/cronograma-nt-009.jpg/@@images/7c071620-4685-4850-b797-85fa83a00764.jpeg'),
]
records = []
def capture(name, url):
    record = {'name': name, 'url': url, 'observed_at': datetime.now(timezone.utc).isoformat()}
    try:
        with urlopen(Request(url, headers={'User-Agent': 'Mozilla/5.0'}), timeout=45) as response:
            data = response.read()
            record.update(final_url=response.geturl(), content_type=response.headers.get('Content-Type'))
        with (E / name).open('xb') as stream: stream.write(data)
        valid = data.startswith(b'%PDF') if name.endswith('.pdf') else zipfile.is_zipfile(io.BytesIO(data)) if name.endswith('.zip') else data.startswith(b'\xff\xd8') if name.endswith('.jpeg') else b'<html' in data.lower()
        record.update(sha256=hashlib.sha256(data).hexdigest(), bytes=len(data), content_verified=valid)
    except Exception as error:
        record.update(content_verified=False, error=str(error))
        data = None
    records.append(record)
    print(('PASS:' if record['content_verified'] else 'UNAVAILABLE:') + name, flush=True)
    return data

class Links(HTMLParser):
    def __init__(self): super().__init__(); self.links = []
    def handle_starttag(self, tag, attrs):
        if tag == 'a':
            href = dict(attrs).get('href')
            if href: self.links.append(href)

for name, url in sources: capture(name, url)
zip_inventory = []
for portal in ['production', 'restricted']:
    path = E / (portal + '-portal.html')
    if not path.exists(): continue
    parser = Links(); parser.feed(path.read_text(encoding='utf-8'))
    candidates = [urljoin(dict(sources)[path.name], link) for link in parser.links if 'esquemas' in link.lower() and link.lower().endswith('.zip')]
    for i, url in enumerate(dict.fromkeys(candidates)):
        name = portal + '-xsd-' + str(i) + '.zip'
        data = capture(name, url)
        if data is None or not zipfile.is_zipfile(io.BytesIO(data)): continue
        with zipfile.ZipFile(io.BytesIO(data)) as archive:
            for member in archive.namelist():
                if member.lower().endswith('.xsd'):
                    raw = archive.read(member)
                    zip_inventory.append({'archive': name, 'member': member, 'sha256': hashlib.sha256(raw).hexdigest(),
                                          'contains_series_type': b'TSSerieDPS' in raw})
with (E / 'primary-source-observation.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'CURRENT_OFFICIAL_DOCUMENT_SOURCES_CAPTURED_NO_SCHEMA_ACTIVATION', 'sources': records,
               'schema_member_inventory': zip_inventory, 'source_failures_preserved': True,
               'deployment_dates_qualified': False, 'runtime_changed': False, 'production_ready': False}, stream, indent=2)
print('PASS:primary captures and limitations preserved; no activated catalog changed')
