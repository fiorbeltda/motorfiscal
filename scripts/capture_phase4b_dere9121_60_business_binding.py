"""Capture permanent binder against native month and version lifecycle, including economic mismatch."""
from pathlib import Path
import hashlib,json,subprocess,sys
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-60-version-binding-20261013'
mode=sys.argv[1]
assert mode in ['capture','replay']
fixture=ROOT/'tests/Fiscal/Unit/DerePublicOfferActiveBusiness60Test.php'
prefix=fixture.read_text(encoding='utf-8').split("it('binds only active public business")[0]
prefix=prefix.replace('uses(TestCase::class);','')
prefix=prefix.replace('declare(strict_types=1);',r'''declare(strict_types=1);
require __DIR__.'/../../../vendor/autoload.php';
$app=require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();''')
body=r'''
try {
$results=[];
foreach ([true,false] as $matching) {
    $fixture=activeBusinessFixture60($matching);
    $input=$fixture['input'];
    $result=(new \App\Modules\Fiscal\ConsumptionReform\DerePublicOfferActiveEventBusinessBinder)->bind(
        $input['operation'],$input['month'],new DateTimeImmutable($input['known_at']),$input['rules'],
        $fixture['events'],$fixture['pgcc'],resource_path('fiscal/sources/official'));
    if ($matching && ($result['status']!=='LOCAL_ACTIVE_PUBLIC_BUSINESS_BOUND'||$result['total_vApur']!=='98.17')) throw new RuntimeException('Native binding failed');
    if (!$matching && ($result['reason']??null)!=='RTC_DERE_PUBLIC_BINDING_ACTIVE_PUBLIC_BUSINESS_DIFFERS_FROM_REBUILT_MONTH') throw new RuntimeException('Changed economics accepted');
    $results[$matching?'matching-native-business':'changed-economics']=['fixture'=>$fixture,'result'=>$result];
}
$record=['status'=>'NATIVE_MONTHLY_BUSINESS_AND_ACTIVE_VERSION_BINDING_CAPTURED','cases'=>$results,
    'monthly_version_selection_integrated'=>false,'government_return_authenticated'=>false,'production_ready'=>false];
$path=__DIR__.'/business-binding-native-result.json';
if ($argv[1]==='capture') {
    $stream=fopen($path,'x');if($stream===false) throw new RuntimeException('Refusing overwrite');
    fwrite($stream,json_encode($record,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));fclose($stream);
} else {
    if ($record!==json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR)) throw new RuntimeException('Binding replay differs');
}
echo 'PASS:native monthly business matches active03 exactly; changed economic version refused; '.$argv[1].PHP_EOL;
}catch(Throwable $exception){fwrite(STDERR,$exception->getMessage());exit(1);}
'''
driver=E/'capture-business-binding.php'
php=prefix+body
if driver.exists():
    assert driver.read_text(encoding='utf-8')==php
else:
    with driver.open('x',encoding='utf-8') as stream:
        stream.write(php)
command=['docker','compose','exec','-T','app','php','-d','memory_limit=512M',driver.relative_to(ROOT).as_posix(),mode]
result=subprocess.run(command,cwd=ROOT,capture_output=True,encoding='utf-8')
log=E/('native-business-'+mode+'.log')
with log.open('x',encoding='utf-8') as stream:
    stream.write(result.stdout+result.stderr)
assert result.returncode==0 and 'PASS:native monthly business' in result.stdout,result.stdout+result.stderr
assert (E/'business-binding-native-result.json').is_file()
with (E/('native-business-'+mode+'.json')).open('x',encoding='utf-8') as stream:
    json.dump({'command':command,'exit_code':result.returncode,'log_sha256':hashlib.sha256(log.read_bytes()).hexdigest()},stream,indent=2)
print(result.stdout)
