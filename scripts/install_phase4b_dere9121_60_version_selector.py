"""Install exact audited lifecycle selector under a production-neutral permanent class name."""
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
P=ROOT/'storage/app/phase4b-dere9121-59-version-preparation-20261013'
E=ROOT/'storage/app/phase4b-dere9121-60-version-binding-20261013'
E.mkdir(exist_ok=True)
accepted=json.loads((P/'version-preparation-validation.json').read_text(encoding='utf-8'))
for item in accepted['implementation']:
    assert hashlib.sha256((ROOT/item['path']).read_bytes()).hexdigest()==item['sha256']
source=(P/'planned-version-selector.php').read_text(encoding='utf-8')
assert source.count('DerePublicOfferVersionChainPrototype59')==1
source=source.replace('DerePublicOfferVersionChainPrototype59','DerePublicOfferEventVersionReconciler')
target=ROOT/'app/Modules/Fiscal/ConsumptionReform/DerePublicOfferEventVersionReconciler.php'
assert not target.exists()
with target.open('x',encoding='utf-8') as stream:
    stream.write(source)
with (E/'version-selector-installation.json').open('x',encoding='utf-8') as stream:
    json.dump({'status':'AUDITED_LIFECYCLE_SELECTOR_INSTALLED_BUSINESS_BINDING_PENDING',
        'installed_source_sha256':hashlib.sha256(target.read_bytes()).hexdigest(),
        'prototype_source_sha256':hashlib.sha256((P/'planned-version-selector.php').read_bytes()).hexdigest(),
        'native_monthly_version_selection_integrated':False,'production_ready':False},stream,indent=2)
print('PASS:audited lifecycle selector installed; monthly business binding and policy activation pending')
