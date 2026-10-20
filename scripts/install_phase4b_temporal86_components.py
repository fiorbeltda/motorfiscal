"""Mechanical namespace installation of verified components; no semantic changes to their archived sources."""
from pathlib import Path
import hashlib
import json

ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-temporal86-integration-preparation-20261015'
archive=json.loads((E/'before-integration-archive.json').read_text(encoding='utf-8'))
targets=[]
for name,digest in archive['staged_components'].items():
    source=E/name
    assert hashlib.sha256(source.read_bytes()).hexdigest()==digest
    target=ROOT/'app/Modules/Fiscal/ConsumptionReform'/name
    assert not target.exists()
    text=source.read_text(encoding='utf-8')
    imports='use DateTimeImmutable;\nuse DateTimeZone;\n'
    if name=='AdvanceTaxReconciler84.php':imports+='use Throwable;\n'
    text=text.replace('declare(strict_types=1);','declare(strict_types=1);\n\nnamespace App\\Modules\\Fiscal\\ConsumptionReform;\n\n'+imports,1)
    targets.append((target,text))
for target,text in targets:
    with target.open('x',encoding='utf-8') as stream:stream.write(text)
with (E/'component-installation.json').open('x',encoding='utf-8') as stream:
    json.dump({'status':'THREE_VERIFIED_COMPONENTS_NAMESPACED_RUNTIME_INTEGRATION_PENDING',
        'files':{p.relative_to(ROOT).as_posix():hashlib.sha256(p.read_bytes()).hexdigest() for p,_ in targets},
        'production_ready':False},stream,indent=2)
print('PASS:three components installed by mechanical namespace/import transformation')
