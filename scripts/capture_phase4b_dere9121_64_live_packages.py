"""Observe current officially linked archives separately from immutable earlier source packages."""
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime,timezone
import hashlib,io,json,urllib.request,zipfile
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-64-process-preparation-20261013'
targets=[('production','https://www.cgibs.gov.br/upload/arquivos/202610/05144622-06-arquivos-xsd-producao.zip'),
    ('pilot','https://www.cgibs.gov.br/upload/arquivos/202609/21141947-06-arquivos-xsd-producao-restrita-empresas-piloto-1.zip'),
    ('documentation13','https://www.cgibs.gov.br/upload/arquivos/202610/05144621-06-arquivos-xsd-v-1-3-0.zip')]

def fetch(item):
    name,url=item
    record={'name':name,'url':url,'observed_at':datetime.now(timezone.utc).isoformat()}
    try:
        with urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'Mozilla/5.0'}),timeout=20) as response:
            data=response.read(2_000_001)
        assert len(data)<=2_000_000
        with zipfile.ZipFile(io.BytesIO(data)) as z:
            names=z.namelist()
            inventory=[{'name':n,'sha256':hashlib.sha256(z.read(n)).hexdigest()} for n in names if not n.endswith('/')]
        path=E/(name+'-observed-20261013.zip')
        with path.open('xb') as f:
            f.write(data)
        record.update(status='CAPTURED',path=path.relative_to(ROOT).as_posix(),sha256=hashlib.sha256(data).hexdigest(),inventory=inventory)
    except Exception as error:
        record.update(status='OBSERVATION_FAILED',error=str(error))
    return record

with ThreadPoolExecutor(max_workers=3) as pool:
    records=list(pool.map(fetch,targets))
with (E/'live-package-observation.json').open('x',encoding='utf-8') as f:
    json.dump({'portal_url':'https://www.cgibs.gov.br/declaracao-de-regimes-especificos-dere',
        'packages':records,'process_schema_qualified':False,'production_ready':False},f,ensure_ascii=False,indent=2)
for record in records:
    print(record['name']+':'+record['status']+(':'+str(len(record['inventory']))+' files' if 'inventory' in record else ':'+record['error']))
