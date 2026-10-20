"""Install audited origin policy guard only after accepted69 corrected full-suite generation."""
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-fx70-origin-preparation-20261014'
P=ROOT/'storage/app/phase4b-import-fx69-temporal-preparation-20261014'

def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
accepted=read(P/'temporal-integration-development-validation.json')
assert accepted['full_suite_complete'] and accepted['local_direct_fx_with_strict_dates_complete']
for item in accepted['implementation']:assert sha(ROOT/item['path'])==item['sha256'],item['path']
prototype=read(E/'origin-preparation-validation.json')
for item in prototype['implementation']:assert sha(ROOT/item['path'])==item['sha256'],item['path']
path=ROOT/'app/Modules/Fiscal/ConsumptionReform/ImportFxTransactionQualifier.php'
assert sha(path)==prototype['current69_transaction_sha256']
archive=E/'before-origin-policy-install-transaction.php'
with archive.open('xb') as f:f.write(path.read_bytes())
source=(E/'planned-origin-transaction.php').read_text(encoding='utf-8').replace('ImportFxQuoteOriginPrototype70','ImportFxTransactionQualifier')
path.write_text(source,encoding='utf-8')
with (E/'material-origin-installation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'MATERIAL_ORIGIN_GUARD_INSTALLED_VALIDATION_AND_POLICY_PENDING','before_sha256':sha(archive),
        'applied_sha256':sha(path),'predecessor69_acceptance_sha256':sha(P/'temporal-integration-development-validation.json'),
        'policy_activated':False,'production_ready':False},f,indent=2)
print('PASS:origin guard installed after accepted69, predecessor archived; policy and validation pending')
