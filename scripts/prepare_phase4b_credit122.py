"""Preserve accepted119 before installing versioned122 guards;exclusive archive."""
from pathlib import Path
from datetime import datetime,timezone
import hashlib,json,shutil
R=Path(__file__).resolve().parents[1]
E=Path(r'D:\ProjetoSaasExports\phase4b-credit122-preparation-20261021')
A=R/'storage/app/phase4b-material119-development-20261020/material119-development-validation.json'
def sha(p):
    with p.open('rb')as f:return hashlib.file_digest(f,'sha256').hexdigest()
def read(p):return json.loads(p.read_text(encoding='utf-8-sig'))
assert sha(A)=='98cb2e92afe2281cc88d94c3a49f155152cc146071a12a5e70cf7511d303cc19'
accepted=read(A);assert len(accepted['runtime_hashes'])==92
paths=dict(accepted['runtime_hashes'])
for name in ['bundles/consumption-phase4b-2026.10.20-119-draft1.json','catalogs/rtc-codes-2026.10.20-119-draft1.json','production/phase4b-controls-2026.10.20-119-draft1.json']:
    paths['resources/fiscal/'+name]=sha(R/'resources/fiscal'/name)
archive=E/'before-integration';assert not archive.exists()
for path,digest in paths.items():assert sha(R/path)==digest,path
archive.mkdir()
for path,digest in paths.items():
    target=archive/path;target.parent.mkdir(parents=True,exist_ok=True)
    shutil.copy2(R/path,target);assert sha(target)==digest,path
report={'created_at':datetime.now(timezone.utc).isoformat(),'accepted119_sha256':sha(A),'archived_files':paths,'runtime119_hashes_verified':92,'phase4b_complete':False,'production_ready':False}
with(E/'before-integration-archive.json').open('x',encoding='utf-8')as f:json.dump(report,f,indent=2)
print(json.dumps({'archived_files':len(paths),'runtime119_hashes_verified':92}))
