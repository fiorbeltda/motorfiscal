"""Capture primary ISS/CTN text for the independently qualified documentary bridge; no rule activation."""
from pathlib import Path
from datetime import datetime, timezone
from html.parser import HTMLParser
from urllib.request import Request, urlopen
import hashlib
import json
import re

E = Path(r'D:\ProjetoSaasExports\phase4b-document94-iss-bridge-sources-20261016')
assert not E.exists(), 'Exclusive legal source observation'
E.mkdir()

class Text(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.parts = []
        self.skip = 0

    def handle_starttag(self, tag, attrs):
        if tag in ['script', 'style']:
            self.skip += 1

    def handle_endtag(self, tag):
        if tag in ['script', 'style']:
            self.skip -= 1

    def handle_data(self, data):
        if not self.skip:
            self.parts.append(data)

sources = []
for name, url, articles in [
    ('lc116-current', 'https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp116.htm', [(2, 3), (7, 8)]),
    ('ctn-current', 'https://www.planalto.gov.br/ccivil_03/leis/l5172compilado.htm', [(143, 144)])
]:
    with urlopen(Request(url, headers={'User-Agent': 'Mozilla/5.0 FiscalResearch/1.0'}), timeout=45) as response:
        raw = response.read()
        content_type = response.headers.get('Content-Type')
        encoding = response.headers.get_content_charset() or 'windows-1252'
        final_url = response.url
    with (E / (name + '.html')).open('xb') as stream:
        stream.write(raw)
    parser = Text()
    parser.feed(raw.decode(encoding, errors='strict'))
    text = re.sub(r'\s+', ' ', ' '.join(parser.parts)).strip()
    selected = []
    for number, next_number in articles:
        match = re.search(r'Art\.\s*' + str(number) + r'\b', text)
        assert match, (name, number)
        end = re.search(r'Art\.\s*' + str(next_number) + r'\b', text[match.end():])
        assert end, (name, next_number)
        selected.append({'article': str(number), 'text': text[match.start():match.end() + end.start()].strip()})
    if name == 'lc116-current':
        assert 'exportações de serviços' in selected[0]['text'] and 'resultado aqui se verifique' in selected[0]['text']
        assert 'preço do serviço' in selected[1]['text']
    else:
        assert 'Salvo disposição de lei em contrário' in selected[0]['text'] and 'dia da ocorrência do fato gerador' in selected[0]['text']
    sources.append({'url': url, 'final_url': final_url, 'observed_at': datetime.now(timezone.utc).isoformat(),
                    'file': name + '.html', 'sha256': hashlib.sha256(raw).hexdigest(), 'bytes': len(raw),
                    'content_type': content_type, 'encoding': encoding, 'selected_articles': selected,
                    'operation_municipal_applicability_proven': False, 'engine_rule_activated': False})
report = {'status': 'ISS_EXPORT_AND_CURRENCY_PRIMARY_TEXT_OBSERVED_NOT_OPERATIONALLY_QUALIFIED',
          'created_at': datetime.now(timezone.utc).isoformat(), 'sources': sources,
          'implementation_constraints': [
              'ISS export qualification needs its own result-location evidence; RTC foreign-consumption evidence is not an automatic substitute.',
              'Do not silently use the RTC prior-business-day quote as ISS/documentary conversion. CTN143 preserves contrary-law exceptions and references the taxable event day.',
              'A source observation is not municipal applicability, transaction evidence, historical wording validation or government authentication.',
              'Do not allocate the full-operation ISS tax to export documents using the RTC share without a qualified ISS allocation contract.'
          ], 'rules_activated': False, 'phase4b_complete': False, 'production_ready': False}
with (E / 'iss-bridge-primary-observation.json').open('x', encoding='utf-8') as stream:
    json.dump(report, stream, ensure_ascii=False, indent=2)
print(json.dumps({'status': report['status'], 'sources': [{k: s[k] for k in ['file', 'sha256', 'bytes']} for s in sources]}))
