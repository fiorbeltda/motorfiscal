"""Capture native synthetic settlement selected under own52 policy and verify exact replay."""
from pathlib import Path
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base52-settlement-draft-20261009'
mode = sys.argv[1]
assert mode in ['capture', 'replay']
fixture = ROOT / 'tests/Fiscal/Unit/DereFinancialNegativeBaseSettlement52Test.php'
prefix = fixture.read_text(encoding='utf-8').split("it('consumes the active prior version")[0]
prefix = prefix.replace('uses(Tests\\TestCase::class);', '').replace('uses(TestCase::class);', '')
prefix = prefix.replace('declare(strict_types=1);', r'''declare(strict_types=1);
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();''')
body = r'''
$snapshotPath=storage_path('app/phase4b-negative-base50-closing-draft-20261009/command-financial-snapshot.json');
$snapshot=json_decode(file_get_contents($snapshotPath),true,512,JSON_THROW_ON_ERROR);
$factory=new \App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
$scenario=$factory->scenarioLoaderFromKnowledge($snapshot['knowledge'])->fromArray($snapshot['input']);
$replay=(new \App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService($factory))->evaluate($snapshot['knowledge'],$scenario)->toArray();
if (CanonicalJson::encode($replay)!==CanonicalJson::encode($snapshot['result'])) throw new RuntimeException('Financial replay differs');
$input=settlement52();
$result=planSettlement52($input);
if ($result['status']!=='LOCAL_BCN_TAX_SETTLEMENT_PROPOSED') throw new RuntimeException(json_encode($result));
$input['known']=$input['known']->format(DateTimeInterface::ATOM);
$record=['input'=>$input,'result'=>$result,'original_financial_snapshot_sha256'=>hash_file('sha256',$snapshotPath),
 'original_financial_replay_matches'=>true,'policy_bundle_version'=>'2026.10.09-28-draft52',
 'native_monthly_command_integration_complete'=>false,'production_ready'=>false];
$path=storage_path('app/phase4b-negative-base52-settlement-draft-20261009/settlement-native-result.json');
if ($argv[1]==='capture') {
 $stream=fopen($path,'x');if ($stream===false) throw new RuntimeException('Refusing to replace capture');
 fwrite($stream,json_encode($record,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);fclose($stream);
} else {
 $saved=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
 if ($record!==$saved) throw new RuntimeException('Settlement replay differs');
}
echo 'PASS: native current composition -> active prior stock -> consumption -> local IBS/CBS tax proposal; '.$argv[1].PHP_EOL;
'''
driver = E / 'capture-settlement.php'
php = prefix + body
if driver.exists():
    assert driver.read_text(encoding='utf-8') == php
else:
    with driver.open('x', encoding='utf-8') as stream:
        stream.write(php)
result = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php',
                         driver.relative_to(ROOT).as_posix(), mode], cwd=ROOT, capture_output=True, encoding='utf-8')
print(result.stdout + result.stderr)
if result.returncode == 0:
    assert (E / 'settlement-native-result.json').is_file(), 'No native result produced'
    assert 'PASS: native current composition' in result.stdout, 'Native checks did not execute'
sys.exit(result.returncode)
