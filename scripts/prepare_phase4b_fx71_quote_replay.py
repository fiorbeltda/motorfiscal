"""Replay all23 captured converter cases against exact token parser with separate result evidence."""
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-fx71-exact-quote-preparation-20261014'
source=r'''<?php
declare(strict_types=1);
use App\Modules\Fiscal\ConsumptionReform\ImportFxExactQuotePrototype71;
require __DIR__.'/../../../vendor/autoload.php';
require __DIR__.'/planned-exact-quote-converter.php';
try {
    $native=json_decode(file_get_contents(__DIR__.'/../phase4b-import-fx68-integration-20261013/fx-permanent-inputs.json'),true,512,JSON_THROW_ON_ERROR);
    $results=[];
    foreach ($native['cases'] as $name=>$case) {
        $result=(new ImportFxExactQuotePrototype71)->convert($case['amount'],$case['currency'],$case['tax_point'],
            new DateTimeImmutable($case['known_at']),$case['context'],$case['trusted_source_inputs']);
        if ($result!==$case['result']) throw new RuntimeException($name.':'.json_encode($result));
        $results[$name]=$result;
        echo 'PASS:'.$name.PHP_EOL;
    }
    $record=['status'=>'EXACT_RATE_TOKEN_PARSER_PRESERVES_ALL23_CURRENT_CASES','cases'=>$results,'production_ready'=>false];
    $path=__DIR__.'/exact-token-replay-result.json';
    if ($argv[1]==='capture') {
        $f=fopen($path,'x');if($f===false) throw new RuntimeException('Refusing overwrite');fwrite($f,json_encode($record,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));fclose($f);
    } elseif ($record!==json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR)) throw new RuntimeException('Exact-token replay differs');
} catch (Throwable $error) {fwrite(STDERR,$error->getMessage());exit(1);}
'''
with (E/'exercise-exact-token-replay.php').open('x',encoding='utf-8') as f:f.write(source)
print('PASS:23-case exact token replay driver prepared')
