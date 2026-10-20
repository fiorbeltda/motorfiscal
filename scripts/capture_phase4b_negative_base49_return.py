"""Capture/replay a declared synthetic return through the native PHP reader, without replacement."""
from pathlib import Path
import hashlib
import json
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base49-preparation-20261009'
test = ROOT / 'tests/Fiscal/Unit/DereNegativeBaseReturnXml49Test.php'
text = test.read_text(encoding='utf-8')
fixture = text[text.index('function bcnXml49()'):text.index('/** @return array<string, mixed> */')]
php = "<?php\nrequire '/var/www/html/vendor/autoload.php';\n" + fixture + r'''
$root = '/var/www/html/storage/app/phase4b-negative-base49-preparation-20261009';
$scope = ['supplier_id'=>'11111111000191','period'=>'2026-09','closing_event_hash'=>str_repeat('A',43).'='];
$reader = new \App\Modules\Fiscal\ConsumptionReform\DereNegativeBaseReturnXmlReader;
$xml = bcnXml49();
$result = $reader->read($xml,$scope,'/var/www/html/resources/fiscal/sources/official',true);
if ($result['status'] !== 'LOCAL_RETURN_RECONCILED' || $result['received_return_verified'] !== false
    || $result['eligible_for_consumption'] !== false || $result['production_ready'] !== false) {
    throw new RuntimeException('Native local reconciliation failed');
}
$record = ['synthetic'=>true,'scope'=>$scope,'result'=>$result];
foreach (['synthetic-return.xml'=>$xml,'synthetic-return-reader.json'=>json_encode($record,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n"] as $name=>$content) {
    $path = $root.'/'.$name;
    if (is_file($path)) {
        if (file_get_contents($path) !== $content) throw new RuntimeException('Existing capture differs: '.$name);
    } else {
        $stream = fopen($path,'x');
        if ($stream === false) throw new RuntimeException('Exclusive capture failed');
        fwrite($stream,$content);fclose($stream);
    }
}
$savedXml = file_get_contents($root.'/synthetic-return.xml');
$saved = json_decode(file_get_contents($root.'/synthetic-return-reader.json'),true,512,JSON_THROW_ON_ERROR);
$replayed = $reader->read($savedXml,$saved['scope'],'/var/www/html/resources/fiscal/sources/official',true);
if ($replayed !== $saved['result'] || hash('sha256',$savedXml) !== $saved['result']['xml_sha256']) {
    throw new RuntimeException('Return replay differs');
}
echo "PASS: native synthetic return captured and exactly replayed; no reception, signature or consumption approval\n";
'''
driver = E / 'capture-synthetic-return.php'
if driver.exists():
    assert driver.read_text(encoding='utf-8') == php
else:
    with driver.open('x', encoding='utf-8') as stream:
        stream.write(php)
label = sys.argv[1]
assert label in ['return-native-capture', 'return-native-replay']
log = E / (label + '.log')
record = E / (label + '.json')
assert not log.exists() and not record.exists()
command = ['docker', 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M',
           str(driver.relative_to(ROOT)).replace('\\', '/')]
result = subprocess.run(command, cwd=ROOT, capture_output=True, encoding='utf-8')
with log.open('x', encoding='utf-8') as stream:
    stream.write(result.stdout + result.stderr)
with record.open('x', encoding='utf-8') as stream:
    json.dump({'command': command, 'exit_code': result.returncode,
               'log_sha256': hashlib.sha256(log.read_bytes()).hexdigest(),
               'fixture_source_sha256': hashlib.sha256(test.read_bytes()).hexdigest()}, stream, indent=2)
print(result.stdout + result.stderr)
sys.exit(result.returncode)
