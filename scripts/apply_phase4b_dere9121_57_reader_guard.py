"""Archive accepted55 reader and correct observed nested semTitulos path only."""
from pathlib import Path
import hashlib
import json
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-57-no-movement-preparation-20261013'
accepted = json.loads((ROOT / 'storage/app/phase4b-dere9121-55-command-integration-20261013/monthly-development-validation.json').read_text(encoding='utf-8'))
target = ROOT / 'app/Modules/Fiscal/ConsumptionReform/DerePublicOfferDebtReturnXmlReader.php'

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

for item in accepted['implementation']:
    assert sha(ROOT / item['path']) == item['sha256']
archive = E / 'before-no-movement-reader.php'
with archive.open('xb') as stream:
    stream.write(target.read_bytes())
source = target.read_text(encoding='utf-8')
anchor = "$o.'/d:semTitulos)'"
assert source.count(anchor) == 1
source = source.replace(anchor, "$o.'/d:infoDebOpOfPublic/d:semTitulos)'")
target.write_text(source, encoding='utf-8')
with (E / 'reader-source-evolution.json').open('x', encoding='utf-8') as stream:
    json.dump({'status':'OBSERVED_NO_MOVEMENT_READER_PATH_CORRECTED_PERMANENT_TESTS_PENDING',
        'accepted55_reader_archive_sha256':sha(archive),'successor_reader_sha256':sha(target),
        'public_composition_source_changed':False,'no_movement_monthly_selection_activated':False,
        'production_ready':False},stream,indent=2)
print('PASS: accepted55 reader archived; nested semTitulos XPath corrected; composition activation pending')
