"""Exercise invalid offsets through the transaction qualifier rather than only testing the PHP parser."""
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-fx70-origin-preparation-20261014'
prefix=(E/'exercise-origin-transaction.php').read_text(encoding='utf-8').split('try {',1)[0]
prefix=prefix.replace("require __DIR__.'/planned-origin-transaction.php';","require __DIR__.'/planned-origin-transaction-offset-v2.php';")
body=r'''
try {
    $cases=[];
    foreach (['zero'=>'+00:00','positive-boundary'=>'+14:00','negative-boundary'=>'-14:00',
        'positive-minute-over'=>'+14:01','negative-minute-over'=>'-14:01','hour-over'=>'+15:00',
        'minute-over'=>'+00:60','impossible-hour'=>'+99:00'] as $name=>$offset) {
        $input=fxTransactionFixture68();$facts=$input['facts'];$policy=$input['policy'];
        $facts['rtc.import_fx_proof']['observed_at']='2026-09-24T00:00:00'.$offset;
        unset($facts['rtc.import_fx_proof']['source_hash']);
        $facts['rtc.import_fx_proof']['source_hash']=CanonicalJson::hash($facts['rtc.import_fx_proof']);
        $facts['rtc.import_fx_proof']=[$facts['rtc.import_fx_proof']];
        $result=(new ImportFxQuoteOriginPrototype70)->qualify($facts,$input['tax_point'],$input['known'],$policy);
        $valid=in_array($name,['zero','positive-boundary','negative-boundary'],true);
        if ($valid) {
            if ($result['status']!=='LOCAL_IMPORT_FX_TRANSACTION_BOUND' || $result['converted_gross_brl']!=='5179.50') throw new RuntimeException($name.':'.json_encode($result));
        } elseif (($result['reason']??null)!=='RTC_IMPORT_FX_TRANSACTION_EXACT_DATE_REQUIRED') throw new RuntimeException($name.':'.json_encode($result));
        $cases[$name]=['offset'=>$offset,'valid'=>$valid,'result'=>$result];
        echo 'PASS:'.$name.PHP_EOL;
    }
    $record=['status'=>'CIVIL_OFFSET_BOUNDARIES_VERIFIED_IN_TRANSACTION_CONTEXT','cases'=>$cases,'runtime_changed'=>false,'production_ready'=>false];
    $path=__DIR__.'/offset-native-result.json';
    if ($argv[1]==='capture') {
        $f=fopen($path,'x');if($f===false) throw new RuntimeException('Refusing overwrite');fwrite($f,json_encode($record,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));fclose($f);
    } elseif ($record!==json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR)) throw new RuntimeException('Offset replay differs');
} catch (Throwable $error) {fwrite(STDERR,$error->getMessage());exit(1);}
'''
with (E/'exercise-origin-offset-v2.php').open('x',encoding='utf-8') as f:f.write(prefix+body)
print('PASS:eight transaction-context offset boundary cases prepared')
