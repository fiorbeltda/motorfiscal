"""Read-only public classification evidence for the next export-document integration."""
import argparse
import hashlib
import json
import os
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, required=True)
OUT = parser.parse_args().output
OUT.mkdir(exist_ok=False)
records = []


def save(path, raw):
    with path.open('xb') as stream:
        stream.write(raw)
        stream.flush()
        os.fsync(stream.fileno())


for code in ['410027', '000001']:
    url = ('https://calculadora-nfse-rtccalc.rtc.az01-es.bsa.estaleiro.serpro/api/calculadora/'
           'dados-abertos/classificacoes-tributarias/cbs-ibs/NFSE/' + code + '?data=2026-09-25')
    transport_error = None
    try:
        with urllib.request.urlopen(urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0', 'Accept': 'application/json'}), timeout=30) as response:
            raw = response.read()
            status = response.status
            content_type = response.headers.get('Content-Type')
    except urllib.error.HTTPError as error:
        raw = error.read()
        status = error.code
        content_type = error.headers.get('Content-Type')
    except urllib.error.URLError as error:
        raw = None
        status = None
        content_type = None
        transport_error = str(error)
    if raw is not None:
        save(OUT / (code + '.response'), raw)
    records.append({'url': url, 'code': code, 'date_parameter': '2026-09-25', 'observed_at': datetime.now(timezone.utc).isoformat(),
                    'http_status': status, 'content_type': content_type, 'transport_error': transport_error,
                    'bytes': len(raw) if raw is not None else None, 'sha256': hashlib.sha256(raw).hexdigest() if raw is not None else None})
save(OUT / 'capture.json', json.dumps({'records': records, 'policy_installed': False}, indent=2).encode())
print(json.dumps(records))
