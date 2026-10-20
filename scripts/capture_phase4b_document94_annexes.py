"""Capture current primary annexes into a new exclusive evidence directory."""
from pathlib import Path
from datetime import datetime, timezone
from urllib.request import Request, urlopen
import hashlib
import json
import zipfile
import io

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-document94-base-reconciliation-20261016')
E.mkdir(exist_ok=False)
base = 'https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc/'
names = ['anexovi-leiautesrn_rtc_ibscbs-v1-04-01-nt009.xlsx',
         'anexovii-indop_ibscbs_v1-03-00-nt009.xlsx']
records = []
for name in names:
    row = {'url': base + name, 'file': name, 'observed_at': datetime.now(timezone.utc).isoformat(),
           'effective_from': None, 'environment_deployment_verified': False}
    try:
        with urlopen(Request(base + name, headers={'User-Agent': 'Mozilla/5.0'}), timeout=45) as response:
            data = response.read()
            row.update(final_url=response.geturl(), content_type=response.headers.get('Content-Type'))
        with (E / name).open('xb') as stream:
            stream.write(data)
        valid = zipfile.is_zipfile(io.BytesIO(data))
        if valid:
            with zipfile.ZipFile(io.BytesIO(data)) as archive:
                valid = 'xl/workbook.xml' in archive.namelist()
        row.update(sha256=hashlib.sha256(data).hexdigest(), bytes=len(data), workbook_verified=valid)
    except Exception as error:
        row.update(workbook_verified=False, error=str(error))
    records.append(row)
    print(name, row['workbook_verified'], flush=True)
with (E / 'primary-annex-observation.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'PRIMARY_ANNEXES_OBSERVED_NOT_ACTIVATED', 'sources': records,
               'production_ready': False}, stream, indent=2)
if not all(row['workbook_verified'] for row in records):
    raise RuntimeError('Source capture incomplete; preserved failures require new destination')
