"""Capture/replay source-bound D1199 option projection over an immutable native monthly baseline."""
from pathlib import Path
import hashlib
import json
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base50-closing-draft-20261009'
mode = sys.argv[1]
assert mode in ['capture', 'replay']
test = ROOT / 'tests/Fiscal/Unit/DereNegativeBaseClosingOptions50Test.php'
source = test.read_text(encoding='utf-8')
fixture = source[source.index('function bcnOptions50()'):source.index("it('adds source-bound")]
php = "<?php\nuse App\\Modules\\Fiscal\\Core\\Shared\\CanonicalJson;\nrequire '/var/www/html/vendor/autoload.php';\n" + fixture + r'''
$root='/var/www/html/storage/app/phase4b-negative-base50-closing-draft-20261009';
$fixture=bcnOptions50();
$projector=new \App\Modules\Fiscal\NFSe\DereNegativeBaseClosingOptionsXmlProjector;
$result=$projector->project(...$fixture);
if($result['status']!=='PROJECTED' || $result['schema_validation']['reason']!=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE'
 || count($result['schema_validation']['errors'])!==1 || $result['gross_debits_recalculated']!==false
 || $result['production_ready']!==false)throw new RuntimeException('Closing request projection failed');
$input=['synthetic'=>true,'baseline'=>$fixture[0],'request'=>$fixture[1],
 'known_at'=>$fixture[2]->format(DATE_ATOM),'policy'=>$fixture[3]];
$xml=$result['xml'];unset($result['xml']);
$artifacts=['synthetic-d1199-options.xml'=>$xml,
 'synthetic-options-input.json'=>json_encode($input,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n",
 'synthetic-options-result.json'=>json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n"];
foreach($artifacts as $name=>$raw){
 $path=$root.'/'.$name;
 if(is_file($path)){if(file_get_contents($path)!==$raw)throw new RuntimeException('Existing capture differs');}
 else {
  if(($argv[1]??null)==='replay')throw new RuntimeException('Replay capture missing');
  $f=fopen($path,'x');if($f===false)throw new RuntimeException('Exclusive capture failed');fwrite($f,$raw);fclose($f);
 }
}
$saved=json_decode(file_get_contents($root.'/synthetic-options-input.json'),true,512,JSON_THROW_ON_ERROR);
$replayed=$projector->project($saved['baseline'],$saved['request'],new DateTimeImmutable($saved['known_at']),$saved['policy'],$fixture[4]);
if($replayed['xml']!==file_get_contents($root.'/synthetic-d1199-options.xml'))throw new RuntimeException('XML replay differs');
unset($replayed['xml']);if($replayed!==$result)throw new RuntimeException('Projection replay differs');
echo "PASS: native preserved D1199 -> source-bound synthetic BCN options -> official XSD and exact replay; no tax recalculation\n";
'''
driver = E / 'capture-synthetic-options.php'
if driver.exists():
    assert driver.read_text(encoding='utf-8') == php
else:
    with driver.open('x', encoding='utf-8') as stream:
        stream.write(php)
label = 'options-native-' + mode
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
