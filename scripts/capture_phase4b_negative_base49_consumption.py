"""Exercise reader -> reconciler -> local consumption planner over preserved synthetic XML."""
from pathlib import Path
import subprocess
import hashlib
import json
import sys

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base49-preparation-20261009'
mode = sys.argv[1]
assert mode in ['capture', 'replay']
driver = E / 'capture-synthetic-consumption.php'
php = r'''<?php
require '/var/www/html/vendor/autoload.php';
$root='/var/www/html/storage/app/phase4b-negative-base49-preparation-20261009';
$saved=json_decode(file_get_contents($root.'/synthetic-return-reader.json'),true,512,JSON_THROW_ON_ERROR);
$xml=file_get_contents($root.'/synthetic-return.xml');
$read=(new \App\Modules\Fiscal\ConsumptionReform\DereNegativeBaseReturnXmlReader)->read($xml,$saved['scope'],'/var/www/html/resources/fiscal/sources/official',true);
if ($read !== $saved['result']) throw new RuntimeException('Prior return replay differs');
$input=['synthetic'=>true,'supplier_id'=>'11111111000191','period'=>'2026-10',
 'prior_xml_sha256'=>hash('sha256',$xml),'prior'=>$read['normalized_return'],
 'parameters'=>['infoBCN'=>[
  ['codBCNRaiz'=>'1010I','usarBCNAcum'=>'1','metodoAproveit'=>'0'],
  ['codBCNRaiz'=>'1010C','usarBCNAcum'=>'1','metodoAproveit'=>'1']],
  'detBCNeg'=>[['codBCN'=>'1010C20260901','vUsarBCN'=>'90.00']]],
 'positive_bases'=>['1010I'=>'50.00','1010C'=>'20.00']];
$planner=new \App\Modules\Fiscal\ConsumptionReform\DereNegativeBaseConsumptionPlanner;
$result=$planner->plan($input['prior'],$input['parameters'],$input['positive_bases'],$input['supplier_id'],$input['period']);
if ($result['status']!=='LOCAL_CONSUMPTION_PLANNED' || $result['plans'][0]['deduction']!=='40.00'
 || $result['plans'][0]['assessed_base']!=='10.00' || $result['plans'][1]['deduction']!=='20.00'
 || $result['plans'][1]['allocations'][0]['remaining']!=='0.00' || count($result['warnings'])!==1
 || $result['warnings'][0]['available']!=='20.00' || $result['eligible_for_posting']!==false
 || $result['production_ready']!==false) throw new RuntimeException('Independent consumption oracle differs');
foreach (['synthetic-consumption-input.json'=>$input,'synthetic-consumption-result.json'=>$result] as $name=>$record) {
 $raw=json_encode($record,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";$path=$root.'/'.$name;
 if (is_file($path)) {if(file_get_contents($path)!==$raw)throw new RuntimeException('Existing artifact differs');}
 else {
  if (($argv[1]??null)==='replay') throw new RuntimeException('Replay artifact missing');
  $f=fopen($path,'x');if($f===false)throw new RuntimeException('Exclusive capture failed');fwrite($f,$raw);fclose($f);
 }
}
$savedInput=json_decode(file_get_contents($root.'/synthetic-consumption-input.json'),true,512,JSON_THROW_ON_ERROR);
$savedResult=json_decode(file_get_contents($root.'/synthetic-consumption-result.json'),true,512,JSON_THROW_ON_ERROR);
if ($planner->plan($savedInput['prior'],$savedInput['parameters'],$savedInput['positive_bases'],$savedInput['supplier_id'],$savedInput['period'])!==$savedResult) {
 throw new RuntimeException('Consumption replay differs');
}
echo "PASS: synthetic XML -> local stock -> separate IBS/CBS consumption; exact replay; posting remains blocked\n";
'''
if driver.exists():
    assert driver.read_text(encoding='utf-8') == php
else:
    with driver.open('x', encoding='utf-8') as stream:
        stream.write(php)
label = 'consumption-native-' + mode
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
               'log_sha256': hashlib.sha256(log.read_bytes()).hexdigest()}, stream, indent=2)
print(result.stdout + result.stderr)
sys.exit(result.returncode)
