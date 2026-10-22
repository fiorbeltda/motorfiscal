"""Durably capture fresh documentary sources and verify them after every write."""
import argparse
import hashlib
import json
import os
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
args.output.mkdir(parents=True, exist_ok=False)
sources = [
    ('decreto12955.html', 'https://www2.camara.leg.br/legin/fed/decret/2026/decreto-12955-29-abril-2026-799019-normaatualizada-pe.html'),
    ('nt009-notice.html', 'https://www.gov.br/nfse/pt-br/noticias/publicado-o-ato-tecnico-conjunto-rfb-cgibs-no-7-aprovando-a-versao-1-01-da-nota-tecnica-009-nfs-e-e-a-nota-tecnica-010-da-nfs-e-via'),
    ('rtc-schedule.html', 'https://www.gov.br/receitafederal/pt-br/acesso-a-informacao/acoes-e-programas/programas-e-atividades/reforma-tributaria-do-consumo/orientacoes-da-reforma-tributaria'),
]

def durable(path, raw):
    with path.open('xb') as handle:
        handle.write(raw)
        handle.flush()
        os.fsync(handle.fileno())
    assert path.read_bytes() == raw

records = []
for name, url in sources:
    with urllib.request.urlopen(urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0 MotorFiscal-source-capture/130'}), timeout=30) as response:
        raw = response.read()
        final_url = response.url
    assert b'\x00' not in raw, 'Refuse NUL bytes in original source'
    record = {'file': name, 'url': url, 'final_url': final_url, 'bytes': len(raw),
        'sha256': hashlib.sha256(raw).hexdigest(), 'observed_at': datetime.now(timezone.utc).isoformat(), 'status': 'CAPTURED'}
    durable(args.output / name, raw)
    durable(args.output / (name+'.receipt.json'), json.dumps(record, indent=2).encode())
    records.append(record)
    print(json.dumps(record), flush=True)
durable(args.output / 'capture.json', json.dumps({'records': records}, indent=2).encode())
for record in records:
    assert hashlib.sha256((args.output / record['file']).read_bytes()).hexdigest() == record['sha256']
