"""Install fully checked material-origin and offset successor only after accepted69."""
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-fx70-origin-preparation-20261014'
P=ROOT/'storage/app/phase4b-import-fx69-temporal-preparation-20261014'

def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
accepted=read(P/'temporal-integration-development-validation.json')
assert accepted['full_suite_complete']
for item in accepted['implementation']:assert sha(ROOT/item['path'])==item['sha256']
predecessor=read(E/'origin-preparation-validation.json')
for item in predecessor['implementation']:assert sha(ROOT/item['path'])==item['sha256']
for label in ['offset70-capture','offset70-replay','origin70-offset-successor-replay']:
    record=read(E/(label+'.json'))
    assert record['exit_code']==0 and record['log_sha256']==sha(E/(label+'.log'))
assert len(read(E/'offset-native-result.json')['cases'])==8
source=E/'planned-origin-transaction-offset-v2.php'
assert sha(source)==read(E/'offset-successor-preparation.json')['successor_sha256']
path=ROOT/'app/Modules/Fiscal/ConsumptionReform/ImportFxTransactionQualifier.php'
assert sha(path)==predecessor['current69_transaction_sha256']
archive=E/'before-origin-policy-install-transaction.php'
with archive.open('xb') as f:f.write(path.read_bytes())
text=source.read_text(encoding='utf-8').replace('ImportFxQuoteOriginPrototype70','ImportFxTransactionQualifier')
path.write_text(text,encoding='utf-8')
with (E/'material-origin-installation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'MATERIAL_ORIGIN_AND_OFFSET_SUCCESSOR_INSTALLED_VALIDATION_AND_POLICY_PENDING',
        'before_sha256':sha(archive),'applied_sha256':sha(path),'offset_successor_sha256':sha(source),
        'predecessor69_acceptance_sha256':sha(P/'temporal-integration-development-validation.json'),
        'policy_activated':False,'production_ready':False},f,indent=2)
print('PASS:origin and offset guard installed after accepted69; previous source archived')
