"""Exclusive municipal manual capture and local original comparison before identity103 preparation."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import shutil
import subprocess
from pypdf import PdfReader

ROOT=Path(__file__).resolve().parents[1]
E=Path(r'D:\ProjetoSaasExports\phase4b-identity103-local-preparation-20261016')
E.mkdir(exist_ok=False)
original=Path(r'K:\GERAL\TI\Projeto - SAAS\Motor Fiscal\Pesquisas Definitivas\Manual Municípios Emissor Público API - Sistema Nacional NFS-e v1.2 out21025.pdf')
url='https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/documentacao-atual/manual-municipios-emissor-publico-api-sistema-nacional-nfs-e-v1-2-out21025.pdf'
def sha(p): return hashlib.sha256(p.read_bytes()).hexdigest()
target=E/'municipal-manual.pdf'
command=['powershell','-NoProfile','-File',str(ROOT/'scripts/capture_primary_windows_tls.ps1'),'-Uri',url,'-OutputPath',str(target)]
proc=subprocess.run(command,capture_output=True)
for channel in ['stdout','stderr']:
    with (E/('capture.'+channel)).open('xb') as stream: stream.write(getattr(proc,channel))
assert proc.returncode==0
observed=datetime.now(timezone.utc).isoformat()
assert target.read_bytes().startswith(b'%PDF')
shutil.copy2(original,E/'municipal-original-k.pdf')
pages=[p.extract_text() for p in PdfReader(target).pages]
with (E/'municipal-full-text.txt').open('x',encoding='utf-8') as stream:
    stream.write('\n\n'.join('PHYSICAL PAGE '+str(i+1)+'\n'+t for i,t in enumerate(pages)))
poppler=Path(r'C:\Users\gabriel.oliveira\.cache\codex-runtimes\codex-primary-runtime\dependencies\native\poppler\Library\bin\pdftoppm.exe')
tmp=E/'tmp/pdfs';tmp.mkdir(parents=True)
subprocess.run([str(poppler),'-f','4','-l','4','-scale-to','1400','-png',str(target),str(tmp/'municipal')],check=True)
report={'url':url,'file':target.name,'sha256':sha(target),'observed_at':observed,'tls_validation':'WINDOWS_DEFAULT_ENABLED',
    'original_path':str(original),'original_sha256':sha(original),'copied_original_sha256':sha(E/'municipal-original-k.pdf'),
    'original_equals_official_capture':sha(original)==sha(target),'pages':len(pages),'published_on':None,
    'effective_from':None,'remote_deployment_proven':False}
with (E/'municipal-source-observation.json').open('x',encoding='utf-8') as stream: json.dump(report,stream,ensure_ascii=False,indent=2)
print(json.dumps(report,ensure_ascii=False),flush=True)
print((E/'municipal-full-text.txt').read_text(encoding='utf-8'))
