"""Preserve primary D1021 process requirements and actual package inventory, without inventing a schema."""
from pathlib import Path
import hashlib,json,zipfile
from pypdf import PdfReader
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'resources/fiscal/sources/official'
E=ROOT/'storage/app/phase4b-dere9121-64-process-preparation-20261013'
E.mkdir(exist_ok=True)

def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

sources=[]
for filename in ['dere-observed-20261005-mod.pdf','dere-observed-20261005-events.pdf','dere-observed-20261005-validation-rules.pdf']:
    p=F/filename
    if not p.exists():
        matches=list(F.glob('*validation*.pdf'))
        assert len(matches)==1,matches
        p=matches[0]
    reader=PdfReader(p)
    texts=[page.extract_text() for page in reader.pages]
    selected=set()
    for index,body in enumerate(texts):
        compact=body.replace(' ','').replace('–','-')
        if 'D-1021' in compact or 'EXIGIR_NUMERO_PROCESSO' in body or 'EXISTE_PROCESSO' in body:
            selected.update(range(max(0,index-1),min(len(texts),index+2)))
    excerpt=E/(p.name+'.process-excerpt.txt')
    with excerpt.open('x',encoding='utf-8') as f:
        f.write('\n\n'.join('PHYSICAL PAGE '+str(index+1)+'\n'+texts[index] for index in sorted(selected)))
    sources.append({'path':p.relative_to(ROOT).as_posix(),'sha256':sha(p),'physical_pages':[i+1 for i in sorted(selected)],
        'excerpt_path':excerpt.relative_to(ROOT).as_posix(),'excerpt_sha256':sha(excerpt)})
archive=F/'dere-observed-20261006-cgibs-xsd-v1-3-0.zip'
with zipfile.ZipFile(archive) as z:
    inventory=[{'name':name,'sha256':hashlib.sha256(z.read(name)).hexdigest()} for name in z.namelist() if not name.endswith('/')]
    table=z.read('evtRetornoTabela-v1_0_1.xsd')
with (E/'evtRetornoTabela-v1_0_1.xsd').open('xb') as f:
    f.write(table)
with (E/'process-research-sources.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'PRIMARY_PROCESS_REQUIREMENTS_AND_ACTUAL_XSD_INVENTORY_PRESERVED',
        'sources':sources,'schema_archive_sha256':sha(archive),'schema_inventory':inventory,
        'process_origin_schema_qualified':False,'process_exclusion_activated':False,'production_ready':False},f,ensure_ascii=False,indent=2)
print('PASS:primary process rules and full actual schema inventory preserved; no process activation')
