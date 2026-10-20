"""Capture SP primary sources without transposing imported-service FX consultations to software exports."""
from pathlib import Path
from datetime import datetime, timezone
from urllib.request import Request, urlopen
from html.parser import HTMLParser
import hashlib
import json
import re
import subprocess
import argparse
from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[1]
args = argparse.ArgumentParser()
args.add_argument('--windows-tls-v2', action='store_true')
options = args.parse_args()
E = Path(r'D:\ProjetoSaasExports\phase4b-document97-sp-primary-research-20261016' + ('-v2' if options.windows_tls_v2 else ''))
POPPLER = Path(r'C:\Users\gabriel.oliveira\.cache\codex-runtimes\codex-primary-runtime\dependencies\native\poppler\Library\bin\pdftoppm.exe')
assert not E.exists(), 'Exclusive research directory'
E.mkdir()
(E / 'tmp/pdfs').mkdir(parents=True)

class Text(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.parts = []
        self.skip = 0
    def handle_starttag(self, tag, attrs):
        if tag in ['script', 'style']: self.skip += 1
    def handle_endtag(self, tag):
        if tag in ['script', 'style']: self.skip -= 1
    def handle_data(self, data):
        if not self.skip: self.parts.append(data)

def sha(p):
    with p.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
def save(p, data):
    with p.open('x', encoding='utf-8') as stream: json.dump(data, stream, ensure_ascii=False, indent=2)

specs = [
    ('sp-decreto53151-consolidado.html', 'https://legislacao.prefeitura.sp.gov.br/decreto-53151-de-17-de-maio-de-2012/consolidado', 'html', '53.151'),
    ('sp-lei13701-consolidada.html', 'https://legislacao.prefeitura.sp.gov.br/lei-13701-de-24-de-dezembro-de-2003/consolidado', 'html', '13.701'),
    ('sp-pn4-2016-consolidado.html', 'https://legislacao.prefeitura.sp.gov.br/parecer-normativo-secretaria-municipal-da-fazenda-4-de-10-de-novembro-de-2016/consolidado', 'html', 'programa de computador'),
    ('sp-pn4-2016-detalhes.html', 'https://legislacao.prefeitura.sp.gov.br/parecer-normativo-secretaria-municipal-da-fazenda-4-de-10-de-novembro-de-2016/detalhe/5dd7ea491411926001a56893', 'html', 'SEM REVOGAÇÃO EXPRESSA'),
    ('sp-sc051-2012.pdf', 'https://www.prefeitura.sp.gov.br/cidade/secretarias/upload/arquivos/secretarias/financas/consultas/SC051-2012.pdf', 'pdf', 'último dia útil'),
    ('sp-sc008-2015.pdf', 'https://drive.prefeitura.sp.gov.br/cidade/upload/SC008-2015_1456839085.pdf', 'pdf', 'conclusão da prestação'),
    ('ctn-current.html', 'https://www.planalto.gov.br/ccivil_03/leis/l5172compilado.htm', 'html', 'moeda estrangeira')]
sources = []
for name, url, kind, expected in specs:
    record = {'file': name, 'url': url, 'observed_at': datetime.now(timezone.utc).isoformat(),
              'effective_from': None, 'operation_applicability_proven': False, 'rules_activated': False}
    try:
        if options.windows_tls_v2:
            capture = subprocess.run(['pwsh', '-NoProfile', '-File', str(ROOT / 'scripts/capture_primary_windows_tls.ps1'),
                '-Uri', url, '-OutputPath', str(E / name)], check=True, capture_output=True, encoding='utf-8')
            record.update(json.loads(capture.stdout))
            raw = (E / name).read_bytes()
        else:
            with urlopen(Request(url, headers={'User-Agent': 'Mozilla/5.0 FiscalSourceResearch/1.0'}), timeout=40) as response:
                raw = response.read()
                record.update(final_url=response.url, content_type=response.headers.get('Content-Type'),
                              encoding=response.headers.get_content_charset())
            with (E / name).open('xb') as stream: stream.write(raw)
        record.update(bytes=len(raw), sha256=hashlib.sha256(raw).hexdigest())
        if kind == 'pdf':
            assert raw.startswith(b'%PDF'), 'PDF URL did not return a PDF'
            reader = PdfReader(E / name)
            texts = [p.extract_text() or '' for p in reader.pages]
            text = '\n\n'.join(texts)
            record['page_count'] = len(reader.pages)
            assert record['page_count'] == 2
            with (E / (name + '.txt')).open('x', encoding='utf-8') as stream: stream.write(text)
            prefix = E / 'tmp/pdfs' / name.removesuffix('.pdf')
            subprocess.run([str(POPPLER), '-png', '-r', '110', str(E / name), str(prefix)], check=True, capture_output=True)
            record['rendered_pages'] = [p.relative_to(E).as_posix() for p in sorted(prefix.parent.glob(prefix.name + '-*.png'))]
            assert len(record['rendered_pages']) == 2
        else:
            encoding = record['encoding'] or ('utf-8' if b'charset="utf-8"' in raw.lower() or b'charset=utf-8' in raw.lower() else 'windows-1252')
            parser = Text()
            parser.feed(raw.decode(encoding))
            text = re.sub(r'\s+', ' ', ' '.join(parser.parts)).strip()
            with (E / (name + '.txt')).open('x', encoding='utf-8') as stream: stream.write(text)
        assert expected.casefold() in text.casefold(), 'Expected source clause not found'
        record['status'] = 'PRIMARY_CONTENT_VERIFIED_RENDERED_WHERE_PDF'
    except Exception as exc:
        record.update(status='UNVERIFIED_CAPTURE_PRESERVED', error=type(exc).__name__ + ':' + str(exc))
    sources.append(record)
    print(record['status'] + ':' + name, flush=True)
save(E / 'primary-source-observation.json', {'created_at': datetime.now(timezone.utc).isoformat(), 'sources': sources,
    'legal_applicability_approved': False, 'rules_activated': False, 'production_ready': False})
assert all(s['status'] == 'PRIMARY_CONTENT_VERIFIED_RENDERED_WHERE_PDF' for s in sources), 'Preserve failed observations; do not reuse this directory'
save(E / 'research-preflight.json', {'status': 'PRIMARY_TEXT_AND_PDF_RENDERS_CAPTURED_VISUAL_REVIEW_PENDING',
    'created_at': datetime.now(timezone.utc).isoformat(), 'source_count': len(sources),
    'source_hashes': {s['file']: s['sha256'] for s in sources}, 'visual_review_complete': False,
    'export_fx_rule_qualified': False, 'municipal_export_result_qualified': False, 'production_ready': False})
print('PASS:seven primary captures/four rendered PDF pages;visual review and applicability audit pending', flush=True)
