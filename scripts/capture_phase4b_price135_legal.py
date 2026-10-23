"""Preserve primary legal bytes and active article12 text; never overwrite observations."""
import argparse
import hashlib
import json
import os
import re
import urllib.request
from datetime import datetime, timezone
from html.parser import HTMLParser
from pathlib import Path


class Paragraphs(HTMLParser):
    def __init__(self):
        super().__init__()
        self.depth = 0
        self.deleted = 0
        self.text = []
        self.paragraphs = []

    def handle_starttag(self, tag, attrs):
        if tag in ['s', 'strike', 'del']:
            self.deleted += 1
        if tag == 'p':
            # Planalto HTML contains implicitly closed paragraphs; HTMLParser
            # does not implement the browser's automatic paragraph closure.
            self.flush()
            self.depth = 1

    def handle_endtag(self, tag):
        if tag in ['s', 'strike', 'del']:
            self.deleted = max(0, self.deleted - 1)
        if tag == 'p' and self.depth:
            self.flush()

    def flush(self):
        value = re.sub(r'\s+', ' ', ''.join(self.text)).strip()
        if self.depth and value:
            self.paragraphs.append(value)
        self.text = []
        self.depth = 0

    def handle_data(self, value):
        if self.depth and not self.deleted:
            self.text.append(value)


parser = argparse.ArgumentParser()
parser.add_argument('--output', required=True)
args = parser.parse_args()
out = Path(args.output)
out.mkdir(parents=True, exist_ok=False)
url = 'https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp214.htm'
request = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
with urllib.request.urlopen(request, timeout=60) as response:
    raw = response.read()
    final_url = response.url
    encoding = response.headers.get_content_charset() or 'windows-1252'
    status = response.status
observed = datetime.now(timezone.utc).isoformat()
with (out / 'lc214.html').open('xb') as handle:
    handle.write(raw)
    handle.flush()
    os.fsync(handle.fileno())
html = Paragraphs()
html.feed(raw.decode(encoding, errors='strict'))
start = next(i for i, p in enumerate(html.paragraphs) if re.match(r'Art\.\s*12\.', p))
end = next(i for i in range(start + 1, len(html.paragraphs)) if re.match(r'Art\.\s*13\.', html.paragraphs[i]))
article = html.paragraphs[start:end]
assert any('2032' in p and '2026' in p for p in article)
assert any('base de cálculo' in p for p in article)
old = Path(__file__).resolve().parents[1] / 'resources/fiscal/sources/official/import129-lc214-compilado-observed20261022.html'
report = {'observed_at': observed, 'url': url, 'final_url': final_url, 'http_status': status,
          'charset': encoding, 'bytes': len(raw), 'sha256': hashlib.sha256(raw).hexdigest(),
          'previous_observation_sha256': hashlib.sha256(old.read_bytes()).hexdigest(),
          'bytes_equal_previous': raw == old.read_bytes(), 'active_article12_paragraphs': article,
          'extraction_omits_struck_text': True, 'legal_production_approval': False}
(out / 'capture.json').write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print(json.dumps({k: v for k, v in report.items() if k != 'active_article12_paragraphs'}))
