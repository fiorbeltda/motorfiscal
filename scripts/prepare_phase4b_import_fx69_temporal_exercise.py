"""Compare current and strict prototypes on exact temporal counterexamples using a preserved transaction fixture."""
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-import-fx69-temporal-preparation-20261014'
test=(ROOT/'tests/Fiscal/Unit/ImportFxTransaction68Test.php').read_text(encoding='utf-8')
fixture=test.split("it('binds local FX",1)[0]
fixture=fixture.replace("<?php\n",'',1).replace('declare(strict_types=1);','',1)
body=r'''
try {
    $cases=[];
    foreach (['valid','proof-invalid-month-end','proof-malformed-start','proof-rolled-observation',
        'policy-invalid-month-end','policy-malformed-start','policy-rolled-publication'] as $name) {
        $input=fxTransactionFixture68();
        $facts=$input['facts'];$policy=$input['policy'];
        switch ($name) {
            case 'proof-invalid-month-end':$facts['rtc.import_fx_proof']['valid_to']='2026-09-31';break;
            case 'proof-malformed-start':$facts['rtc.import_fx_proof']['valid_from']='2026-00-00';break;
            case 'proof-rolled-observation':$facts['rtc.import_fx_proof']['observed_at']='2026-09-31T00:00:00+00:00';break;
            case 'policy-invalid-month-end':$policy['valid_to']='2026-09-31';break;
            case 'policy-malformed-start':$policy['valid_from']='2026-00-00';break;
            case 'policy-rolled-publication':$policy['published_at']='2026-09-31T00:00:00+00:00';break;
        }
        unset($facts['rtc.import_fx_proof']['source_hash']);$facts['rtc.import_fx_proof']['source_hash']=CanonicalJson::hash($facts['rtc.import_fx_proof']);
        $facts['rtc.import_fx_proof']=[$facts['rtc.import_fx_proof']];
        unset($policy['source_hash']);$policy['source_hash']=CanonicalJson::hash($policy);
        $current=(new ImportFxTransactionQualifier)->qualify($facts,$input['tax_point'],$input['known'],$policy);
        $planned=(new App\Modules\Fiscal\ConsumptionReform\ImportFxTransactionTemporalPrototype69)->qualify($facts,$input['tax_point'],$input['known'],$policy);
        if ($name==='valid') {
            if ($current!==$planned || $planned['status']!=='LOCAL_IMPORT_FX_TRANSACTION_BOUND') throw new RuntimeException('Valid transaction changed');
        } else {
            if ($current['status']!=='LOCAL_IMPORT_FX_TRANSACTION_BOUND' || $planned['status']!=='ACTION_REQUIRED') throw new RuntimeException($name.':'.json_encode([$current,$planned]));
        }
        $cases[$name]=['current'=>$current,'planned'=>$planned,'synthetic'=>true];
        echo 'PASS:'.$name.PHP_EOL;
    }
    $record=['status'=>'SIX_INVALID_CONTEXT_DATES_ACCEPTED_BY68_REFUSED_BY_STRICT_PROTOTYPE','cases'=>$cases,
        'runtime_changed'=>false,'production_ready'=>false];
    $path=__DIR__.'/temporal-native-result.json';
    if ($argv[1]==='capture') {
        $f=fopen($path,'x');if($f===false) throw new RuntimeException('Refusing overwrite');fwrite($f,json_encode($record,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));fclose($f);
    } elseif ($record!==json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR)) throw new RuntimeException('Temporal replay differs');
} catch (Throwable $error) {fwrite(STDERR,$error->getMessage());exit(1);}
'''
bootstrap="""<?php
declare(strict_types=1);
require __DIR__.'/../../../vendor/autoload.php';
$app=require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
require __DIR__.'/planned-temporal-transaction.php';
"""
with (E/'exercise-temporal-transaction-v2.php').open('x',encoding='utf-8') as f:f.write(bootstrap+fixture+body)
print('PASS:exact policy/proof date counterexample driver prepared')
