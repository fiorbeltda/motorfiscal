"""Promote audited quote converter and capture complete permanent-test inputs without editing67 evidence."""
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
P=ROOT/'storage/app/phase4b-fx67-preparation-20261013'
E=ROOT/'storage/app/phase4b-import-fx68-integration-20261013'

def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()

accepted=read(P/'fx-preparation-validation.json')
assert accepted['exact_replay'] and (accepted['positive_cases'],accepted['negative_cases'])==(6,17)
for item in accepted['implementation']:
    assert sha(ROOT/item['path'])==item['sha256'],item['path']
E.mkdir(exist_ok=True)
source=(P/'planned-fx-quote-converter.php').read_text(encoding='utf-8')
source=source.replace('ImportFxQuoteConverterPrototype67','ImportFxQuoteConverter')
source=source.replace('/** Prototype: source-bound ordinary-import conversion;','/** Source-bound ordinary-import conversion;')
target=ROOT/'app/Modules/Fiscal/ConsumptionReform/ImportFxQuoteConverter.php'
with target.open('x',encoding='utf-8') as f:f.write(source)
driver=(P/'exercise-fx-converter.php').read_text(encoding='utf-8')
driver=driver.replace('ImportFxQuoteConverterPrototype67','ImportFxQuoteConverter')
driver=driver.replace("require __DIR__.'/planned-fx-quote-converter.php';",'')
driver=driver.replace("__DIR__.'/'.$name","storage_path('app/phase4b-fx67-preparation-20261013/').$name")
anchor="'context' => $context,"
assert driver.count(anchor)==1
driver=driver.replace(anchor,anchor+" 'trusted_source_inputs' => $trusted,")
driver=driver.replace("__DIR__.'/fx-native-v2-result.json'","__DIR__.'/fx-permanent-inputs.json'")
driver=driver.replace("'app_installed' => false","'app_installed' => true")
with (E/'capture-fx-inputs.php').open('x',encoding='utf-8') as f:f.write(driver)
with (E/'converter-source-installation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'AUDITED_FX_CONVERTER_PROMOTED_ENGINE_INTEGRATION_PENDING','prototype_sha256':sha(P/'planned-fx-quote-converter.php'),
        'implementation':[{'path':p.relative_to(ROOT).as_posix(),'sha256':sha(p)} for p in [target,E/'capture-fx-inputs.php']],
        'engine_integrated':False,'policy_activated':False,'production_ready':False},f,indent=2)
print('PASS:audited FX converter promoted with own68 permanent-input driver; old67 evidence preserved')
