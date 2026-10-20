"""Successor origin prototype adds explicit civil offset bounds without changing audited predecessor or live69."""
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-fx70-origin-preparation-20261014'
old=E/'planned-origin-transaction.php'
report=json.loads((E/'origin-preparation-validation.json').read_text(encoding='utf-8'))
assert hashlib.sha256(old.read_bytes()).hexdigest()==next(i['sha256'] for i in report['implementation'] if ROOT/i['path']==old)
source=old.read_text(encoding='utf-8')
anchor="        $fraction = str_pad($parts[3], 6, '0');"
assert source.count(anchor)==1
source=source.replace(anchor,"""        $offsetHour = (int) substr($parts[4], 1, 2);
        $offsetMinute = (int) substr($parts[4], 4, 2);
        if ($offsetHour > 14 || $offsetMinute > 59 || ($offsetHour === 14 && $offsetMinute !== 0)) {
            return false;
        }
"""+anchor)
target=E/'planned-origin-transaction-offset-v2.php'
with target.open('x',encoding='utf-8') as f:f.write(source)
with (E/'offset-successor-preparation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'EXPLICIT_CIVIL_OFFSET_SUCCESSOR_PREPARED_VALIDATION_PENDING','predecessor_sha256':hashlib.sha256(old.read_bytes()).hexdigest(),
        'successor_sha256':hashlib.sha256(target.read_bytes()).hexdigest(),'offset_contract':'PLUS_MINUS_14_HOURS_MAX_MINUTE_ZERO_AT_14',
        'runtime_changed':False,'production_ready':False},f,indent=2)
print('PASS:offset-bounded origin successor prepared; audited prototype and current69 preserved')
