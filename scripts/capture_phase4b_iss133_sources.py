"""Capture the current municipal source chain without modifying preserved observations."""
import hashlib
import json
import os
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

OUTPUT = Path('D:/ProjetoSaasExports/phase4b-iss133-20261022-v1/primary-v1')
OUTPUT.mkdir(parents=True, exist_ok=False)
urls = {
    'sp-law13701.html': 'https://legislacao.prefeitura.sp.gov.br/lei-13701-de-24-de-dezembro-de-2003/consolidado',
    'sp-decree53151.html': 'https://legislacao.prefeitura.sp.gov.br/decreto-53151-de-17-de-maio-de-2012/consolidado',
    'sp-in8.html': 'https://legislacao.prefeitura.sp.gov.br/instrucao-normativa-secret-mun-de-financas-e-desenv-economico-surem-8-de-21-de-julho-de-2011/consolidado',
    'sp-annex1-2026.pdf': 'https://prefeitura.sp.gov.br/documents/d/fazenda/in-sf-surem_08_2011_anexo_1_com-in-03-26-pdf',
    'sp-law18066.html': 'https://legislacao.prefeitura.sp.gov.br/lei-18066-de-28-de-dezembro-de-2023/consolidado',
}
records = []


def durable(path, raw):
    with path.open('xb') as stream:
        stream.write(raw)
        stream.flush()
        os.fsync(stream.fileno())
    assert hashlib.sha256(path.read_bytes()).digest() == hashlib.sha256(raw).digest()


for name, url in urls.items():
    with urllib.request.urlopen(urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'}), timeout=30) as response:
        body = response.read()
        record = {'file': name, 'url': url, 'final_url': response.url, 'content_type': response.headers.get('Content-Type'),
                  'observed_at': datetime.now(timezone.utc).isoformat(), 'bytes': len(body), 'sha256': hashlib.sha256(body).hexdigest()}
    assert (body.startswith(b'%PDF') if name.endswith('.pdf') else b'<html' in body.lower())
    durable(OUTPUT / name, body)
    records.append(record)
    durable(OUTPUT / (name + '.receipt.json'), json.dumps(record, indent=2).encode())
    print(json.dumps(record), flush=True)
durable(OUTPUT / 'capture.json', json.dumps({'records': records}, indent=2).encode())
