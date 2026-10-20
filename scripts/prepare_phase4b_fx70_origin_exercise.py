"""Exercise origin-policy gates with the existing source-bound transaction fixture."""
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-fx70-origin-preparation-20261014'
fixture=(ROOT/'tests/Fiscal/Unit/ImportFxTransaction68Test.php').read_text(encoding='utf-8').split("it('binds local FX",1)[0]
fixture=fixture.replace('<?php','',1).replace('declare(strict_types=1);','',1)
bootstrap="""<?php
declare(strict_types=1);
require __DIR__.'/../../../vendor/autoload.php';
$app=require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
require __DIR__.'/planned-origin-transaction.php';
"""
body=r'''
try {
    $cases=[];
    foreach (['old-policy','usd-qualified','origin-missing','origin-source-hash','origin-future','origin-hash',
        'wrong-strategy','eur-refinitiv','aud-no-authority'] as $name) {
        $input=fxTransactionFixture68();$facts=$input['facts'];$policy=$input['policy'];$expected=null;
        $policy['require_material_quote_origin']=true;
        $observation=json_decode(file_get_contents(storage_path('app/phase4b-fx67-preparation-20261013/primary-source-observation.json')),true,512,JSON_THROW_ON_ERROR);
        $primary=current(array_filter($observation['sources'],static fn(array $row):bool=>str_ends_with($row['path'],'bcb-ptax-api.pdf')));
        $origin=['status'=>'QUALIFIED_LOCAL_SOURCE_SELECTION','currency'=>'USD','strategy'=>'BCB_USD_BRL_PTAX',
            'quote_source_sha256'=>$policy['quote_sources']['USD']['sha256'],'primary_evidence_sha256'=>$primary['sha256'],
            'known_from'=>$policy['published_at']];
        if ($name==='origin-missing') {$expected='MATERIAL_QUOTE_ORIGIN_UNQUALIFIED';}
        elseif ($name==='origin-source-hash') {$origin['quote_source_sha256']=str_repeat('0',64);$expected='MATERIAL_QUOTE_ORIGIN_UNQUALIFIED';}
        elseif ($name==='origin-future') {$origin['known_from']=$input['known']->modify('+1 second')->format(DATE_ATOM);$expected='MATERIAL_QUOTE_ORIGIN_HASH_OR_KNOWLEDGE_CONFLICT';}
        elseif ($name==='origin-hash') {$expected='MATERIAL_QUOTE_ORIGIN_HASH_OR_KNOWLEDGE_CONFLICT';}
        elseif ($name==='wrong-strategy') {$origin['strategy']='REFINITIV';$expected='MATERIAL_QUOTE_ORIGIN_STRATEGY_OR_AUTHORITY_UNQUALIFIED';}
        elseif (in_array($name,['eur-refinitiv','aud-no-authority'],true)) {
            $currency=$name==='eur-refinitiv'?'EUR':'AUD';
            $facts['rtc.gross_currency']=$currency;$facts['rtc.import_fx_source_id']=$currency;
            $origin['currency']=$currency;$origin['quote_source_sha256']=$policy['quote_sources'][$currency]['sha256'];
            $origin['strategy']=$name==='eur-refinitiv'?'REFINITIV':'FOREIGN_MONETARY_AUTHORITY_RATE_DISCLOSED_BY_BCB';
            $expected='MATERIAL_QUOTE_ORIGIN_STRATEGY_OR_AUTHORITY_UNQUALIFIED';
        }
        $origin['source_hash']=CanonicalJson::hash($origin);
        if ($name==='origin-hash') $origin['source_hash']=str_repeat('0',64);
        if ($name!=='origin-missing') $policy['qualified_quote_origins']=[$facts['rtc.import_fx_source_id']=>$origin];
        if ($name==='old-policy') {unset($policy['require_material_quote_origin'],$policy['qualified_quote_origins']);}
        unset($policy['source_hash']);$policy['source_hash']=CanonicalJson::hash($policy);
        $facts['rtc.import_fx_proof']=[$facts['rtc.import_fx_proof']];
        $result=(new AppModulesFiscalConsumptionReformImportFxQuoteOriginPrototype70)->qualify($facts,$input['tax_point'],$input['known'],$policy);
        if ($expected===null) {
            if ($result['status']!=='LOCAL_IMPORT_FX_TRANSACTION_BOUND' || $result['converted_gross_brl']!=='5179.50') throw new RuntimeException($name.':'.json_encode($result));
        } elseif (($result['reason']??null)!=='RTC_IMPORT_FX_'.$expected) throw new RuntimeException($name.':'.json_encode($result));
        $cases[$name]=['expected_reason'=>$expected,'result'=>$result,'synthetic'=>true];
        echo 'PASS:'.$name.PHP_EOL;
    }
    $record=['status'=>'MATERIAL_QUOTE_ORIGIN_PROTOTYPE_VALIDATED_USD_AND_EXPLICIT_NON_USD_REFUSALS','cases'=>$cases,
        'non_usd_authority_evidence_available'=>false,'runtime_changed'=>false,'production_ready'=>false];
    $path=__DIR__.'/origin-native-result.json';
    if ($argv[1]==='capture') {
        $f=fopen($path,'x');if($f===false) throw new RuntimeException('Refusing overwrite');fwrite($f,json_encode($record,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));fclose($f);
    } elseif ($record!==json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR)) throw new RuntimeException('Origin replay differs');
} catch (Throwable $error) {fwrite(STDERR,$error->getMessage());exit(1);}
'''
body=body.replace('AppModulesFiscalConsumptionReformImportFxQuoteOriginPrototype70','App'+chr(92)+'Modules'+chr(92)+'Fiscal'+chr(92)+'ConsumptionReform'+chr(92)+'ImportFxQuoteOriginPrototype70')
with (E/'exercise-origin-transaction.php').open('x',encoding='utf-8') as f:f.write(bootstrap+fixture+body)
print('PASS:nine material-origin policy cases prepared')
