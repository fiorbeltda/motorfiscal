"""Extract primary FX legal clauses and normalize observed PTAX closing rows without rate activation."""
from pathlib import Path
from decimal import Decimal
from html.parser import HTMLParser
from pypdf import PdfReader
import hashlib,json,re
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-fx67-preparation-20261013'

def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

class Text(HTMLParser):
    def __init__(self):
        super().__init__();self.parts=[]
    def handle_data(self,value):
        if value.strip(): self.parts.append(value.strip())

observation=json.loads((E/'primary-source-observation.json').read_text(encoding='utf-8'))
for item in observation['sources']:
    assert item['status']=='CAPTURED' and sha(ROOT/item['path'])==item['sha256']
excerpts=[]
for name in ['cbs-regulation.html','lc214-current.html']:
    parser=Text();parser.feed((E/name).read_text(encoding='utf-8'))
    text=re.sub(r'\s+',' ',' '.join(parser.parts))
    passages=[]
    for match in re.finditer(r'moeda estrangeira|moeda nacional|Art\. 64\.|Art\. 70\.',text,flags=re.IGNORECASE):
        passages.append(text[max(0,match.start()-550):match.end()+1400])
    path=E/(name+'.fx-excerpt.txt')
    with path.open('x',encoding='utf-8') as f:
        f.write('\n\n'.join(passages))
    excerpts.append({'source':name,'source_sha256':sha(E/name),'path':path.relative_to(ROOT).as_posix(),'sha256':sha(path)})
for name in ['ibs-regulation.pdf','bcb-ptax-api.pdf']:
    reader=PdfReader(E/name)
    selected=[]
    for index,page in enumerate(reader.pages):
        text=page.extract_text()
        if name=='bcb-ptax-api.pdf' or ('câmbio' in text.lower() and ('dia imediatamente anterior' in text or 'moeda nacional' in text)):
            selected.append((index+1,text))
    path=E/(name+'.fx-excerpt.txt')
    with path.open('x',encoding='utf-8') as f:
        f.write('\n\n'.join('PHYSICAL PAGE '+str(index)+'\n'+text for index,text in selected))
    excerpts.append({'source':name,'source_sha256':sha(E/name),'physical_pages':[index for index,_ in selected],
        'path':path.relative_to(ROOT).as_posix(),'sha256':sha(path)})
registry=json.loads((E/'bcb-currencies.json').read_text(encoding='utf-8'))['value']
packets=[]
for currency in ['USD','EUR','AUD']:
    p=E/('bcb-'+currency.lower()+'-20260920-28.json')
    raw=json.loads(p.read_text(encoding='utf-8'),parse_float=Decimal)
    assert not raw.get('@odata.nextLink') and len(raw['value'])==30
    rows=[]
    for row in raw['value']:
        if row['tipoBoletim']!='Fechamento':continue
        rows.append({'quote_date':row['dataHoraCotacao'][:10],'published_local_datetime':row['dataHoraCotacao'],
            'sale_brl_per_unit':str(row['cotacaoVenda']),'purchase_brl_per_unit':str(row['cotacaoCompra']),
            'sale_parity_raw':str(row['paridadeVenda']),'bulletin':'Fechamento'})
    assert len(rows)==6 and len({r['quote_date'] for r in rows})==6
    packet={'currency':currency,'registry_entry':next(r for r in registry if r['simbolo']==currency),
        'query_from':'2026-09-20','query_through':'2026-09-28','complete_response_no_pagination':True,
        'raw_path':p.relative_to(ROOT).as_posix(),'raw_sha256':sha(p),'rows':rows,
        'fx_policy_activated':False,'production_ready':False}
    target=E/(currency.lower()+'-closing-packet.json')
    with target.open('x',encoding='utf-8') as f:
        json.dump(packet,f,ensure_ascii=False,indent=2)
    packets.append({'path':target.relative_to(ROOT).as_posix(),'sha256':sha(target)})
with (E/'fx-research-contract.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'OFFICIAL_FX_LEGAL_AND_PTAX_PREPARATION_NO_ENGINE_ACTIVATION','excerpts':excerpts,'closing_packets':packets,
        'currency_registry_count':len(registry),'ordinary_service_import_rule':'CBS_REG_ART13_PAR4_AND_MATCHING_IBS_RULE',
        'material_goods_rule_distinct':'CBS_REG_ART81_LC214_ART70',
        'sale_side_required':True,'tax_point_relevant_date_required':True,'previous_calendar_day_required':True,
        'usd_missing_previous_day_last_available_rule':True,'non_usd_bcb_disclosed_quote_rule':True,
        'unquoted_currency_usd_cross_proof_required':True,'source_observation_and_knowledge_required':True,
        'currency_adjustment_units_must_be_explicit':True,'old_unconverted_import_behavior_preserved':True,
        'engine_activated':False,'phase4b_complete':False,'production_ready':False},f,ensure_ascii=False,indent=2)
print('PASS:primary legal/BCB excerpts and18 closing quotations normalized with raw hashes; no rate or engine activation')
