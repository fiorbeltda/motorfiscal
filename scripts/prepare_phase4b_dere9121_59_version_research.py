"""Extract primary version-operation schemas and manual pages without changing58 runtime."""
from pathlib import Path
import hashlib,json,zipfile
from pypdf import PdfReader
from xml.etree import ElementTree as ET
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'resources/fiscal/sources/official'
E=ROOT/'storage/app/phase4b-dere9121-59-version-preparation-20261013'
E.mkdir(exist_ok=True)

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

sources=[]
for filename,pages in [('dere-observed-20261005-events.pdf',[37,38,39,126,127,128]),
    ('dere-observed-20261005-mod.pdf',[88,143,144,145])]:
    path=F/filename
    reader=PdfReader(path)
    excerpt=E/(filename+'.excerpt.txt')
    with excerpt.open('x',encoding='utf-8') as stream:
        stream.write('\n\n'.join('PHYSICAL PAGE '+str(p)+'\n'+reader.pages[p-1].extract_text() for p in pages))
    sources.append({'source_path':path.relative_to(ROOT).as_posix(),'source_sha256':sha(path),
        'physical_pages':pages,'excerpt_path':excerpt.relative_to(ROOT).as_posix(),'excerpt_sha256':sha(excerpt)})
archive=F/'dere-observed-20261006-cgibs-xsd-v1-3-0.zip'
schema=[]
with zipfile.ZipFile(archive) as package:
    for name in ['evtDebOpOfPublic-v0_0_3.xsd','evtRetornoTitPub-v0_0_2.xsd']:
        data=package.read(name)
        path=E/name
        with path.open('xb') as stream:
            stream.write(data)
        tree=ET.fromstring(data)
        ns={'x':'http://www.w3.org/2001/XMLSchema'}
        selected=[]
        for node in tree.findall('.//x:element',ns):
            if node.get('name') in ['tpOper','seqEvento','infoEvento','infoAdic','infoTotTitOfPub','vApurTot']:
                selected.append({'name':node.get('name'),'minOccurs':node.get('minOccurs'),'maxOccurs':node.get('maxOccurs'),
                    'documentation':' '.join(' '.join(node.itertext()).split())[:2400]})
        schema.append({'path':path.relative_to(ROOT).as_posix(),'sha256':sha(path),'elements':selected})
with (E/'version-research-contract.json').open('x',encoding='utf-8') as stream:
    json.dump({'status':'PUBLIC_EVENT_VERSION_PRIMARY_PREPARATION_ONLY','sources':sources,'schemas':schema,
        'schema_archive_sha256':sha(archive),'initial_sequence':'00','sequence_continues_after_exclusion':True,
        'alteration_replaces_prior_event':True,'version_selector_implemented':False,
        'active_event_business_reconciled':False,'government_return_authenticated':False,'production_ready':False},stream,ensure_ascii=False,indent=2)
print('PASS:primary version-operation schema/manual excerpts preserved; no runtime or policy activation')
