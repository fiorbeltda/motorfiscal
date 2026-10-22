"""Preserve the currently linked restricted DPS archive; never edit official XSDs."""
import argparse
import hashlib
import json
import urllib.request
import zipfile
from datetime import datetime, timezone
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--output', required=True, type=Path)
args = parser.parse_args()
args.output.mkdir(parents=True, exist_ok=False)
base = 'https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita'
records = []
for name, url in [('restricted.html', base), ('restricted-20261020.zip', base + '/esquemas_prod_restrita_20261020.zip')]:
    request = urllib.request.Request(url, headers={'User-Agent': 'MotorFiscal-source-capture/127'})
    with urllib.request.urlopen(request, timeout=30) as response:
        raw = response.read()
        record = {'file': name, 'url': url, 'final_url': response.url, 'status': response.status,
                  'observed_at': datetime.now(timezone.utc).isoformat(), 'bytes': len(raw),
                  'sha256': hashlib.sha256(raw).hexdigest(), 'activated_officially': False}
    with (args.output / name).open('xb') as handle:
        handle.write(raw)
    records.append(record)
    print(json.dumps(record), flush=True)
with zipfile.ZipFile(args.output / 'restricted-20261020.zip') as archive:
    members = [{'file': name, 'bytes': archive.getinfo(name).file_size,
                'sha256': hashlib.sha256(archive.read(name)).hexdigest()} for name in archive.namelist() if not name.endswith('/')]
with (args.output / 'capture.json').open('x', encoding='utf-8') as handle:
    handle.write(json.dumps({'records': records, 'archive_members': members}, indent=2) + '\n')
print(json.dumps({'members': members}))
