"""Capture BCB CKAN dataset metadata after preserving the invalid HTML response from the first observation."""
from pathlib import Path
from datetime import datetime,timezone
import hashlib,json,urllib.request
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-fx70-origin-preparation-20261014'
url='https://dadosabertos.bcb.gov.br/api/3/action/package_show?id=taxas-de-cambio-todos-os-boletins-diarios'
record={'url':url,'observed_at':datetime.now(timezone.utc).isoformat(),'production_ready':False}
try:
    with urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'Mozilla/5.0'}),timeout=20) as response:data=response.read(2_000_000)
    path=E/'bcb-dataset-metadata.json'
    with path.open('xb') as f:f.write(data)
    decoded=json.loads(data)
    assert decoded.get('success') is True
    notes=decoded['result']['notes']
    assert 'Refinitiv' in notes and 'USDBRL' in notes
    record.update(status='PRIMARY_RATE_ORIGIN_METADATA_CAPTURED',path=path.relative_to(ROOT).as_posix(),
        sha256=hashlib.sha256(data).hexdigest(),non_usd_origin='REFINITIV',usd_brl_origin='BCB',
        non_usd_monetary_authority_origin_qualified=False)
except Exception as error:record.update(status='PRIMARY_RATE_ORIGIN_METADATA_OBSERVATION_FAILED',error=str(error))
with (E/'quote-origin-metadata-observation.json').open('x',encoding='utf-8') as f:json.dump(record,f,ensure_ascii=False,indent=2)
print(record['status'])
if record['status'].endswith('FAILED'):raise SystemExit(1)
