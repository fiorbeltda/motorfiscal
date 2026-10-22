"""Capture primary catalog sources into an exclusive D: evidence directory."""
import argparse
import hashlib
import json
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
args.output.mkdir(parents=True, exist_ok=False)
base = 'https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/'
sources = {
    'rtc.html': base + 'rtc',
    'restricted.html': base + 'producao-restrita',
    'production.html': base + 'documentacao-atual',
    'cindop103.xlsx': base + 'rtc/anexovii-indop_ibscbs_v1-03-00-nt009.xlsx',
    'restricted-cindop.xlsx': base + 'producao-restrita/anexo_c-indop_ibscbs-snnfse-prodrest-v1-01-20260122.xlsx',
    'nt009.pdf': base + 'rtc/nota-tecnica-009-se-cgnfs-e-v-1-01.pdf',
    'restricted-layout.xlsx': base + 'producao-restrita/anexo_i-sefin_adn-dps_nfse-snnfse_prodrest-v1-01-20261020.xlsx',
}
records = []
for name, url in sources.items():
    started = datetime.now(timezone.utc).isoformat()
    try:
        request = urllib.request.Request(url, headers={'User-Agent': 'MotorFiscal-source-capture/126'})
        with urllib.request.urlopen(request, timeout=25) as response:
            raw = response.read()
            record = {'file': name, 'url': url, 'final_url': response.url, 'http_status': response.status,
                      'content_type': response.headers.get('Content-Type'), 'started_at': started,
                      'observed_at': datetime.now(timezone.utc).isoformat(), 'bytes': len(raw),
                      'sha256': hashlib.sha256(raw).hexdigest(), 'activated': False}
        with (args.output / name).open('xb') as handle:
            handle.write(raw)
        records.append(record)
        print(json.dumps(record), flush=True)
    except Exception as error:
        records.append({'file': name, 'url': url, 'started_at': started, 'error': str(error), 'activated': False})
        print(json.dumps(records[-1]), flush=True)
    (args.output / 'capture.json').write_text(json.dumps({'records': records, 'activated': False}, indent=2) + '\n', encoding='utf-8')
if any('error' in record for record in records):
    raise SystemExit(1)
