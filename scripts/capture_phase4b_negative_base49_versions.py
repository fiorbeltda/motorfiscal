"""Preserve a synthetic full close/reopen/successor chain and replay its active stock into consumption."""
from pathlib import Path
import hashlib
import subprocess
import json
import sys

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base49-preparation-20261009'
mode = sys.argv[1]
assert mode in ['capture', 'replay']
test = ROOT / 'tests/Fiscal/Unit/DereNegativeBaseClosingVersions49Test.php'
source = test.read_text(encoding='utf-8')
fixture = source[source.index('function bcnVersions49()'):source.index('/** @param list<array<string, mixed>>')]
php = "<?php\nrequire '/var/www/html/vendor/autoload.php';\n" + fixture + r'''
$root='/var/www/html/storage/app/phase4b-negative-base49-preparation-20261009';
$events=bcnVersions49();
$reconciler=new \App\Modules\Fiscal\ConsumptionReform\DereNegativeBaseClosingVersionReconciler;
$result=$reconciler->reconcile($events,'11111111000191','2026-09','/var/www/html/resources/fiscal/sources/official',true);
if ($result['status']!=='LOCAL_CLOSING_VERSION_CHAIN_RECONCILED'
 || $result['declared_timestamp_order_verified']!==true || count($result['superseded'])!==1
 || array_column($result['active_return']['stock_after_return'],'available_after_return','codBCN')!==[
 '1010I20260600'=>'50.00','1010C20260600'=>'80.00','1010C20260901'=>'10.00']) {
 throw new RuntimeException('Version replacement oracle differs');
}
$previous=json_decode(file_get_contents($root.'/synthetic-consumption-input.json'),true,512,JSON_THROW_ON_ERROR);
$input=['synthetic'=>true,'supplier_id'=>'11111111000191','period'=>'2026-10',
 'prior'=>$result['active_return']['normalized_return'],'parameters'=>$previous['parameters'],'positive_bases'=>$previous['positive_bases'],
 'active_return_xml_sha256'=>$result['active_return']['xml_sha256'],'closing_chain_hash'=>$result['chain_hash']];
$planner=new \App\Modules\Fiscal\ConsumptionReform\DereNegativeBaseConsumptionPlanner;
$consumed=$planner->plan($input['prior'],$input['parameters'],$input['positive_bases'],$input['supplier_id'],$input['period']);
if ($consumed['status']!=='LOCAL_CONSUMPTION_PLANNED' || $consumed['plans'][0]['deduction']!=='50.00'
 || $consumed['plans'][0]['assessed_base']!=='0.00' || $consumed['plans'][1]['deduction']!=='10.00'
 || $consumed['warnings'][0]['available']!=='10.00' || $consumed['eligible_for_posting']!==false) {
 throw new RuntimeException('Successor stock consumption oracle differs');
}
$artifacts=['synthetic-version-first-close.xml'=>$events[0]['xml'],'synthetic-version-reopen-request.xml'=>$events[1]['request_xml'],
 'synthetic-version-reopen-return.xml'=>$events[1]['return_xml'],'synthetic-version-successor-close.xml'=>$events[2]['xml']];
foreach (['synthetic-version-chain-input.json'=>['synthetic'=>true,'events'=>$events],
 'synthetic-version-chain-result.json'=>$result,'synthetic-version-consumption-input.json'=>$input,
 'synthetic-version-consumption-result.json'=>$consumed] as $name=>$record) {
 $artifacts[$name]=json_encode($record,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
}
foreach ($artifacts as $name=>$raw) {
 $path=$root.'/'.$name;
 if(is_file($path)){if(file_get_contents($path)!==$raw)throw new RuntimeException('Existing capture differs');}
 else {
  if(($argv[1]??null)==='replay')throw new RuntimeException('Replay artifact missing');
  $f=fopen($path,'x');if($f===false)throw new RuntimeException('Exclusive capture failed');fwrite($f,$raw);fclose($f);
 }
}
$saved=json_decode(file_get_contents($root.'/synthetic-version-chain-input.json'),true,512,JSON_THROW_ON_ERROR);
if($reconciler->reconcile($saved['events'],'11111111000191','2026-09','/var/www/html/resources/fiscal/sources/official',true)!==$result){
 throw new RuntimeException('Version chain replay differs');
}
echo "PASS: synthetic close -> reopen -> successor replaces stock -> independent next-month consumption; exact replay\n";
'''
driver = E / 'capture-synthetic-versions.php'
if driver.exists():
    assert driver.read_text(encoding='utf-8') == php
else:
    with driver.open('x', encoding='utf-8') as stream:
        stream.write(php)
label = 'versions-native-' + mode
log = E / (label + '.log')
record = E / (label + '.json')
assert not log.exists() and not record.exists()
command = ['docker', 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M',
           str(driver.relative_to(ROOT)).replace('\\', '/'), mode]
result = subprocess.run(command, cwd=ROOT, capture_output=True, encoding='utf-8')
with log.open('x', encoding='utf-8') as stream:
    stream.write(result.stdout + result.stderr)
with record.open('x', encoding='utf-8') as stream:
    json.dump({'command': command, 'exit_code': result.returncode,
               'log_sha256': hashlib.sha256(log.read_bytes()).hexdigest(),
               'fixture_source_sha256': hashlib.sha256(test.read_bytes()).hexdigest()}, stream, indent=2)
print(result.stdout + result.stderr)
sys.exit(result.returncode)
