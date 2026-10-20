"""Demonstrate no-movement reader path defect and validate separate successor without changing full55 inputs."""
from pathlib import Path
import hashlib
import json
import subprocess
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-57-no-movement-preparation-20261013'
E.mkdir(exist_ok=True)
original = ROOT / 'app/Modules/Fiscal/ConsumptionReform/DerePublicOfferDebtReturnXmlReader.php'
source = original.read_text(encoding='utf-8')
anchor = "$o.'/d:semTitulos)'"
assert source.count(anchor) == 1
source = source.replace('final readonly class DerePublicOfferDebtReturnXmlReader',
    'final readonly class DerePublicOfferDebtNoMovementReturnXmlReaderPrototype57')
source = source.replace(anchor, "$o.'/d:infoDebOpOfPublic/d:semTitulos)'")
prototype = E / 'planned-no-movement-reader.php'
with prototype.open('x', encoding='utf-8') as stream:
    stream.write(source)
driver = E / 'exercise-no-movement-reader.php'
php = r'''<?php
declare(strict_types=1);
try {
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
require __DIR__.'/planned-no-movement-reader.php';
$cases=[];
foreach (['qualified-direct-only', 'qualified-fund-only', 'qualified-both-codes'] as $name) {
    $originPath=storage_path('app/phase4b-d2101-no-movement-20261006-21/'.$name.'-candidate.xml.D2101.xml');
    $origin=file_get_contents($originPath);
    $originDom=new DOMDocument; $originDom->loadXML($origin);
    $dom=new DOMDocument;
    $dom->loadXML(file_get_contents(storage_path('app/phase4b-dere9121-54-preparation-20261013/synthetic-d9121-v2.xml')));
    $path=new DOMXPath($dom); $path->registerNamespace('d','http://www.dere.gov.br/schemas/evtRetornoTitPub/v0_0_2');
    $path->query('/d:DeRE/d:evtRetornoTitPub')->item(0)->setAttribute('id',$originDom->documentElement->firstElementChild->getAttribute('id'));
    $path->query('//d:infoRecEv/d:hash')->item(0)->nodeValue=base64_encode(hash('sha256',$origin,true));
    $scope=['synthetic'=>true,'supplier_id'=>'11111111000191','period'=>'2026-09',
        'origin_xml_sha256'=>hash('sha256',$origin),'expected_sequence'=>'00','pgcc_receipt'=>'1011-202609-'.str_repeat('P',19)];
    $actual=(new App\Modules\Fiscal\ConsumptionReform\DerePublicOfferDebtReturnXmlReader)->read($dom->saveXML(),$origin,$scope,resource_path('fiscal/sources/official'));
    $planned=(new App\Modules\Fiscal\ConsumptionReform\DerePublicOfferDebtNoMovementReturnXmlReaderPrototype57)->read($dom->saveXML(),$origin,$scope,resource_path('fiscal/sources/official'));
    if (($actual['reason']??null)!=='RTC_DERE_D9121_TITLE_COVERAGE_REQUIRED'
        ||$planned['status']!=='LOCAL_D9121_AGGREGATE_RECONCILED'||$planned['title_count']!==0||$planned['total_vApur']!=='0.00') {
        throw new RuntimeException($name.': '.json_encode(['actual'=>$actual,'planned'=>$planned]));
    }
    $path->query('//d:vApurTot')->item(0)->nodeValue='1.00';
    $wrong=(new App\Modules\Fiscal\ConsumptionReform\DerePublicOfferDebtNoMovementReturnXmlReaderPrototype57)->read($dom->saveXML(),$origin,$scope,resource_path('fiscal/sources/official'));
    if (($wrong['reason']??null)!=='RTC_DERE_D9121_ORIGIN_AGGREGATE_AMOUNT_CONFLICT') throw new RuntimeException('Nonzero empty origin accepted');
    $cases[$name]=['origin_path'=>$originPath,'origin_sha256'=>hash('sha256',$origin),
        'installed_reader'=>$actual,'planned_reader'=>$planned,'nonzero_empty_origin'=>$wrong];
}
echo json_encode(['status'=>'NO_MOVEMENT_GUARD_DEFECT_REPRODUCED_SUCCESSOR_VALIDATED','cases'=>$cases,
    'reader_source_changed'=>false,'monthly_no_movement_composition_integrated'=>false,'production_ready'=>false],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
}catch(Throwable $exception){fwrite(STDERR,$exception->getMessage());exit(1);}
'''
with driver.open('x', encoding='utf-8') as stream:
    stream.write(php)
command = ['docker', 'compose', 'exec', '-T', 'app', 'php', driver.relative_to(ROOT).as_posix()]
result = subprocess.run(command, cwd=ROOT, capture_output=True, encoding='utf-8')
with (E / 'native-guard-comparison.log').open('x', encoding='utf-8') as stream:
    stream.write(result.stdout + result.stderr)
assert result.returncode == 0, result.stderr
data = json.loads(result.stdout)
assert data['status'] == 'NO_MOVEMENT_GUARD_DEFECT_REPRODUCED_SUCCESSOR_VALIDATED' and len(data['cases']) == 3
replay = subprocess.run(command, cwd=ROOT, capture_output=True, encoding='utf-8')
assert replay.returncode == 0 and json.loads(replay.stdout) == data
with (E / 'native-guard-comparison.json').open('x', encoding='utf-8') as stream:
    json.dump({'status':data['status'],'native_result':data,'exact_replay':True,
        'installed_reader_sha256':hashlib.sha256(original.read_bytes()).hexdigest(),
        'planned_reader_sha256':hashlib.sha256(prototype.read_bytes()).hexdigest(),
        'log_sha256':hashlib.sha256((E/'native-guard-comparison.log').read_bytes()).hexdigest(),
        'production_ready':False},stream,indent=2)
print('PASS:3 no-movement origins reproduce path defect; separate successor reconciles zero and rejects nonzero total; exact replay')
