"""Preserve live legal and BCB artifacts for foreign-currency import scope, without activating rates."""
from pathlib import Path
from datetime import datetime,timezone
from concurrent.futures import ThreadPoolExecutor
import hashlib,json,urllib.request,urllib.parse
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-fx67-preparation-20261013'
E.mkdir(exist_ok=True)
base='https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/'
targets=[
    ('cbs-regulation.html','https://www2.camara.leg.br/legin/fed/decret/2026/decreto-12955-29-abril-2026-799019-normaatualizada-pe.html'),
    ('lc214-current.html','https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html'),
    ('ibs-regulation.pdf','https://www.cgibs.gov.br/upload/arquivos/202604/30084927-res-cgibs-n-6-30-abr-2026-regulamenta-o-ibs.pdf'),
    ('bcb-ptax-api.pdf','https://www.bcb.gov.br/conteudo/dadosabertos/BCBDepin/gnastportal-dados-abertostaxas-de-cambio---todos-os-boletins-diarios.pdf'),
    ('bcb-currencies.json',base+'Moedas?'+urllib.parse.urlencode({'$format':'json'})),
]
for currency in ['USD','EUR','AUD']:
    url=base+'CotacaoMoedaPeriodo(moeda=@moeda,dataInicial=@dataInicial,dataFinalCotacao=@dataFinalCotacao)?'+urllib.parse.urlencode({
        '@moeda':"'"+currency+"'",'@dataInicial':"'09-20-2026'",'@dataFinalCotacao':"'09-28-2026'",'$format':'json'})
    targets.append(('bcb-'+currency.lower()+'-20260920-28.json',url))

def capture(target):
    name,url=target
    observed=datetime.now(timezone.utc).isoformat()
    path=E/name
    marker=E/(name+'.observation.json')
    assert not marker.exists() and not path.exists()
    record={'url':url,'observed_at':observed,'path':path.relative_to(ROOT).as_posix()}
    try:
        with urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'Mozilla/5.0'}),timeout=25) as response:
            data=response.read(25_000_001)
            content_type=response.headers.get('Content-Type')
        assert len(data)<=25_000_000
        with path.open('xb') as f:
            f.write(data)
        record.update(status='CAPTURED',sha256=hashlib.sha256(data).hexdigest(),bytes=len(data),content_type=content_type)
        if name.endswith('.json'):
            decoded=json.loads(data)
            record.update(row_count=len(decoded.get('value',[])),next_link=decoded.get('@odata.nextLink'))
    except Exception as error:
        record.update(status='OBSERVATION_FAILED',error=str(error))
    with marker.open('x',encoding='utf-8') as f:
        json.dump(record,f,ensure_ascii=False,indent=2)
    return record

with ThreadPoolExecutor(max_workers=4) as pool:
    records=list(pool.map(capture,targets))
with (E/'primary-source-observation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'PRIMARY_FX_SOURCE_OBSERVATION_NO_RATE_ACTIVATION','sources':records,'production_ready':False},f,ensure_ascii=False,indent=2)
for record in records:
    print(Path(record['path']).name+':'+record['status']+(':'+str(record['row_count'])+' rows' if 'row_count' in record else ''))
