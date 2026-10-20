"""Capture successor synthetic composition without rebinding historical evidence files."""
from pathlib import Path
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base51-current-bases-preparation-20261009'
mode = sys.argv[1]
assert mode in ['capture', 'replay']
fixture = ROOT / 'tests/Fiscal/Unit/DereFinancialCurrentComposition51Test.php'
prefix = fixture.read_text(encoding='utf-8').split("it('rebuilds monthly proofs")[0]
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
if (CanonicalJson::encode($replay)!==CanonicalJson::encode($snapshot['result'])) throw new RuntimeException('Original financial snapshot replay differs');
$cases=[];
foreach (['explicit-no-iss'=>'0','explicit-iss'=>'1'] as $name=>$iss) {
 $input=currentComposition51(true,$iss);
 $result=qualifyCurrentComposition51($input);
 if ($result['status']!=='LOCAL_MONTHLY_CURRENT_BASE_COMPOSITION_RECONCILED') throw new RuntimeException($result['reason']);
 $projection=(new \App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector)->project($input['operation'],$input['month'],$input['known'],$input['rules']);
 $components=['D1199'=>$projection,'D1101'=>$projection['balance'],'D1011'=>$projection['pgcc_projection'],
  'D1121'=>$projection['deductions'],'D1001'=>$projection['contributor_projection']];
 $schema=$input['rules']['closing']['xsd'];$validations=[];
 foreach ($components as $event=>$component) {
  $validation=(new \App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator)->validate($component['xml'],
   resource_path('fiscal/sources/official/'.$schema['source_file']),$schema['source_sha256'],$component['schema'],
   resource_path('fiscal/sources/official/'.$schema['signature_source_file']),$schema['signature_source_sha256']);
  if ($validation['reason']!=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE') throw new RuntimeException(json_encode($validation));
  $validations[$event]=$validation;
 }
 $input['known']=$input['known']->format(DateTimeInterface::ATOM);
 $cases[$name]=['input'=>$input,'result'=>$result,'event_validations'=>$validations];
}
$missing=qualifyCurrentComposition51(currentComposition51(false));
if ($missing['reason']!=='RTC_DERE_CURRENT_COMPOSITION_EXPLICIT_REVENUE_ISS_CLASSIFICATION_REQUIRED') throw new RuntimeException('Absent ISS classification was accepted');
$capture=['scope'=>'REPLAYED_FINANCIAL_SNAPSHOT_WITH_SUCCESSOR_SYNTHETIC_CLASSIFICATION_WITNESSES',
 'original_snapshot_sha256'=>hash_file('sha256',$snapshotPath),'financial_replay_matches'=>true,
 'cases'=>$cases,'missing_classification'=>$missing,'monthly_consumption_integrated'=>false,
 'classification_external_verified'=>false,'production_ready'=>false];
$path=storage_path('app/phase4b-negative-base51-current-bases-preparation-20261009/composition-native-result.json');
if ($argv[1]==='capture') {
 $stream=fopen($path,'x');if ($stream===false) throw new RuntimeException('Refusing to replace composition capture');
 fwrite($stream,json_encode($capture,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);fclose($stream);
} else {
 $saved=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
 if ($capture!==$saved) throw new RuntimeException('Composition replay differs');
}
echo 'PASS: original financial replay, two forming-code compositions, ten unsigned XSD checks; '.$argv[1].PHP_EOL;
'''
php = prefix + body
driver = E / 'capture-current-composition-v2.php'
if driver.exists():
    assert driver.read_text(encoding='utf-8') == php
else:
    with driver.open('x', encoding='utf-8') as stream:
        stream.write(php)
result = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php',
                         driver.relative_to(ROOT).as_posix(), mode], cwd=ROOT, capture_output=True, encoding='utf-8')
print(result.stdout + result.stderr)
if result.returncode == 0:
    assert (E / 'composition-native-result.json').is_file(), 'No native capture produced'
    assert 'PASS: original financial replay' in result.stdout, 'Native checks did not execute'
sys.exit(result.returncode)
