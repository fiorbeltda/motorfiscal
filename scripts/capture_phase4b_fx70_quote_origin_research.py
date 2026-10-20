"""Preserve primary BCB rate-origin disclosure separately from earlier observed API numerical outputs."""
from pathlib import Path
from datetime import datetime,timezone
import hashlib,json,urllib.request
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-fx70-origin-preparation-20261014';E.mkdir(exist_ok=True)
url='https://dadosabertos.bcb.gov.br/en/dataset/taxas-de-cambio-todos-os-boletins-diarios'
with urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'Mozilla/5.0'}),timeout=25) as response:
    data=response.read(2_000_000)
path=E/'bcb-rate-origin.html'
with path.open('xb') as f:f.write(data)
text=data.decode('utf-8')
assert 'Refinitiv' in text and 'USDBRL' in text
with (E/'quote-origin-observation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'PRIMARY_BCB_NON_USD_PARITY_ORIGIN_REQUIRES_FURTHER_LEGAL_QUALIFICATION',
        'url':url,'observed_at':datetime.now(timezone.utc).isoformat(),'path':path.relative_to(ROOT).as_posix(),
        'sha256':hashlib.sha256(data).hexdigest(),'non_usd_origin_disclosed':'REFINITIV',
        'usd_brl_origin':'BCB','non_usd_foreign_monetary_authority_origin_qualified':False,
        'absence_from_ten_currency_api_proves_no_domestic_quote':False,'runtime_changed':False,'production_ready':False},f,indent=2)
print('PASS:primary BCB rate-origin disclosure preserved; non-USD legal origin remains unqualified')
