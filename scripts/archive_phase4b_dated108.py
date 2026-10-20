"""Verify accepted104 and isolated105-107; archive exact predecessors before108 writes."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import shutil

ROOT=Path(__file__).resolve().parents[1]
E=Path(r'D:\ProjetoSaasExports\phase4b-dated108-integration-20261019')
F=Path(r'D:\ProjetoSaasExports\phase4b-identity104-full-suite-20261019')
A=ROOT/'storage/app/phase4b-identity104-development-20261019/identity104-development-validation.json'
def sha(p):
    with p.open('rb') as f:return hashlib.file_digest(f,'sha256').hexdigest()
def read(p):return json.loads(p.read_text(encoding='utf-8'))
assert not E.exists()
assert sha(A)=='8f3d57986e7fea2f87ca317914bc1ecac90e7cfd719607839e88fa5128262bd1'
accepted=read(A);pre=read(F/'preflight.json')
for p,h in pre['input_hashes'].items():assert sha(Path(p))==h,p
for p,h in accepted['runtime_hashes'].items():assert sha(ROOT/p)==h,p
assert len(pre['input_hashes'])==11133 and len(accepted['runtime_hashes'])==79
prepared={}
for directory,report in [
    ('phase4b-iss-date105-local-preparation-20261019','date105-local-component-validation.json'),
    ('phase4b-iss-date106-result-composition-20261019','dated106-local-composition-validation.json'),
    ('phase4b-identity107-dated-preparation-20261019','identity107-local-dated-validation.json')]:
    base=Path(r'D:\ProjetoSaasExports')/directory
    prep=read(base/report)
    assert prep['runtime_integrated'] is False and prep['production_ready'] is False
    prepared[str(base/report)]=sha(base/report)
for name,directory,digest in [
    ('PartialExportIssMaterialDate105.php','phase4b-iss-date105-local-preparation-20261019','2a94bad8d281fda0b80131f83dd8f49ff409311000c2469663a69a3e4a13124e'),
    ('PartialExportIssDatedResults106.php','phase4b-iss-date106-result-composition-20261019','38d731f9c12ee759a559735a9f23d69086e72a12f24da76d206a887f78b42585'),
    ('PartialExportDatedDpsIdentity107.php','phase4b-identity107-dated-preparation-20261019','07c927896509d0649dca30de03bb598599b61b6db46b6a2213fda53ea5f7b539')]:
    assert sha(Path(r'D:\ProjetoSaasExports')/directory/name)==digest
E.mkdir()
archived={}
for path in ['app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
    'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php',
    'resources/monitoring/task-progress.json','resources/monitoring/objective-review.json','resources/monitoring/roadmap.json']:
    target=E/'before-integration'/path;target.parent.mkdir(parents=True,exist_ok=True)
    shutil.copy2(ROOT/path,target)
    assert sha(ROOT/path)==sha(target);archived[path]=sha(target)
with (E/'before-integration-archive.json').open('x',encoding='utf-8') as f:
    json.dump({'created_at':datetime.now(timezone.utc).isoformat(),'acceptance104_sha256':sha(A),
        'frozen104_inputs_unchanged':11133,'runtime104_hashes_unchanged':79,'prepared_reports':prepared,'archived':archived},f,indent=2)
print('PASS:11,133 accepted104 inputs/79 hashes and105-107 verified;predecessors archived',flush=True)
