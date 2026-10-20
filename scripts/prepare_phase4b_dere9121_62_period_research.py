"""Preserve exact primary closure/reopening sections and schemas for contextual period qualification."""
from pathlib import Path
import hashlib,json,zipfile
from pypdf import PdfReader
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'resources/fiscal/sources/official'
E=ROOT/'storage/app/phase4b-dere9121-62-period-preparation-20261013'
E.mkdir(exist_ok=True)

def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

sources=[]
for filename in ['dere-observed-20261005-mod.pdf','dere-observed-20261005-events.pdf']:
    p=F/filename
    reader=PdfReader(p)
    texts=[page.extract_text() for page in reader.pages]
    selected=set()
    for index,body in enumerate(texts):
        normalized=body.replace(' ', '').replace('–','-')
        if 'D-1198' in normalized or 'D-9198' in normalized or 'PERAPUR_FECHADO' in body:
            selected.update(range(max(0,index-1),min(len(texts),index+2)))
    excerpt=E/(filename+'.period-excerpt.txt')
    body='\n\n'.join('PHYSICAL PAGE '+str(index+1)+'\n'+texts[index] for index in sorted(selected))
    if excerpt.exists():
        assert excerpt.read_text(encoding='utf-8')==body
    else:
        with excerpt.open('x',encoding='utf-8') as f:
            f.write(body)
    sources.append({'path':p.relative_to(ROOT).as_posix(),'sha256':sha(p),'physical_pages':[i+1 for i in sorted(selected)],
        'excerpt_path':excerpt.relative_to(ROOT).as_posix(),'excerpt_sha256':sha(excerpt)})
archive=F/'dere-observed-20261006-cgibs-xsd-v1-3-0.zip'
schemas=[]
with zipfile.ZipFile(archive) as z:
    for name in ['evtFechMensal-v0_0_2.xsd','evtRetornoMensal-v0_0_2.xsd','evtReabertMensal-v0_0_1.xsd','evtRetornoReabert-v0_0_1.xsd']:
        data=z.read(name)
        p=E/name
        with p.open('xb') as f:
            f.write(data)
        schemas.append({'path':p.relative_to(ROOT).as_posix(),'sha256':sha(p)})
with (E/'period-research-sources.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'PRIMARY_PERIOD_STATE_RESEARCH_PRESERVED_NO_ACTIVATION','sources':sources,'schemas':schemas,
        'schema_archive_sha256':sha(archive),'production_ready':False},f,ensure_ascii=False,indent=2)
print('PASS:exact closure/reopening primary pages and four schemas preserved')
