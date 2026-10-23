"""Capture original primary sources for own software revenue contributions."""
import argparse
import hashlib
import json
import os
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--output', required=True, type=Path)
args = parser.parse_args()
args.output.mkdir(parents=True, exist_ok=False)
sources = {'law10833.html': 'https://www.planalto.gov.br/ccivil_03/leis/2003/l10.833compilado.htm',
           'law9718.html': 'https://www.planalto.gov.br/ccivil_03/leis/l9718.htm',
           'law9715.html': 'https://www.planalto.gov.br/ccivil_03/leis/l9715.htm',
           'mp2158.html': 'https://www.planalto.gov.br/ccivil_03/mpv/2158-35.htm',
           'lc116.html': 'https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp116.htm'}
rows = []
for name, url in sources.items():
    request = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
    with urllib.request.urlopen(request, timeout=45) as response:
        raw = response.read()
        row = {'file': name, 'url': url, 'final_url': response.url, 'http_status': response.status,
               'observed_at': datetime.now(timezone.utc).isoformat(), 'sha256': hashlib.sha256(raw).hexdigest(), 'bytes': len(raw)}
    with (args.output / name).open('xb') as handle:
        handle.write(raw)
        handle.flush()
        os.fsync(handle.fileno())
    rows.append(row)
    (args.output / 'capture.json').write_text(json.dumps({'sources': rows}, indent=2) + '\n', encoding='utf-8')
print(json.dumps({'sources': rows, 'complete': len(rows) == 5}))
