"""Supplement56 source binding with accepted55 runtime and archived pre-fix reader."""
from pathlib import Path
import hashlib
import json
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-56-corpus-preparation-20261013'
accepted_path=ROOT/'storage/app/phase4b-dere9121-55-command-integration-20261013/monthly-development-validation.json'
accepted=json.loads(accepted_path.read_text(encoding='utf-8'))
bindings=[]
for item in accepted['implementation']:
    logical=ROOT/item['path']
    actual=(ROOT/'storage/app/phase4b-dere9121-57-no-movement-preparation-20261013/before-no-movement-reader.php'
        if item['path']=='app/Modules/Fiscal/ConsumptionReform/DerePublicOfferDebtReturnXmlReader.php' else logical)
    assert hashlib.sha256(actual.read_bytes()).hexdigest()==item['sha256']
    bindings.append({'logical_path':item['path'],'preserved_path':actual.relative_to(ROOT).as_posix(),'sha256':item['sha256']})
with (E/'corpus-accepted55-generation-binding.json').open('x',encoding='utf-8') as stream:
    json.dump({'status':'AUDITED56_CORPUS_BOUND_TO_ACCEPTED55_GENERATION_WITH_ARCHIVED_READER',
        'accepted55_report_sha256':hashlib.sha256(accepted_path.read_bytes()).hexdigest(),'generation_sources':bindings,
        'current57_reader_is_successor':True,'scope54_complete':False,'production_ready':False},stream,indent=2)
print('PASS:56 corpus generation bound to accepted55 source set, including archived reader; prior binding preserved')
