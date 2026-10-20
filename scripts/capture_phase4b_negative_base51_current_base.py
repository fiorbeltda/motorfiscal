"""Capture and exactly replay native current-base arithmetic over declared synthetic inputs."""
from pathlib import Path
import json
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base51-current-bases-preparation-20261009'
mode = sys.argv[1]
assert mode in ['capture', 'replay']
driver = E / 'capture-current-base.php'
php = r'''<?php
require '/var/www/html/vendor/autoload.php';
$root='/var/www/html/storage/app/phase4b-negative-base51-current-bases-preparation-20261009';
$base=['codBC'=>'1010','iss_incident'=>false,'synthetic'=>true,'transition_present'=>false,
 'selective_tax_incident'=>false,'general_revenue'=>'1200.00','transition_revenue'=>'0.00',
 'other_deductible_expenses'=>'100.00','allocated_funding_expenses'=>'200.00',
 'export_reversal'=>'0.00','cooperative_reversal'=>'0.00','public_offer_debt_return'=>'0.00',
 'rates'=>['IBS_STATE'=>'0.10','IBS_MUNICIPAL'=>'0.00','CBS'=>'0.90']];
$inputs=['general-only'=>$base];
$transition=$base;
$transition['general_revenue']='1100.00';
$transition['transition_revenue']='540.00';
$transition['transition_present']=true;
$transition['other_deductible_expenses']=$transition['allocated_funding_expenses']='0.00';
$transition['rates']=['IBS_STATE'=>'8.00','IBS_MUNICIPAL'=>'0.00','CBS'=>'2.00'];
$inputs['general-and-transition']=$transition;
$negative=$base;
$negative['general_revenue']='0.00';
$inputs['negative-current-base']=$negative;
$calculator=new \App\Modules\Fiscal\ConsumptionReform\DereFinancialCurrentBaseCalculator;
$results=[];
foreach ($inputs as $name=>$input) {
 $result=$calculator->calculate($input);
 if ($result['status']!=='LOCAL_CURRENT_BASE_ARITHMETIC_CALCULATED') throw new RuntimeException($name);
 $results[$name]=$result;
}
$capture=['scope'=>'DECLARED_SYNTHETIC_CURRENT_BASE_ARITHMETIC_ONLY','inputs'=>$inputs,'results'=>$results,
 'composition_source_qualified'=>false,'rate_source_qualified'=>false,'monthly_engine_integration_complete'=>false,
 'negative_base_consumption_applied'=>false,'production_ready'=>false];
$path=$root.'/current-base-native-capture.json';
if ($argv[1]==='capture') {
 $stream=fopen($path,'x');
 if ($stream===false) throw new RuntimeException('Refusing to replace capture');
 fwrite($stream,json_encode($capture,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
 fclose($stream);
} else {
 $saved=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
 if ($capture!==$saved) throw new RuntimeException('Current-base native replay differs');
}
echo 'PASS: three current-base cases, native '.$argv[1].', source qualification and monthly integration remain pending'.PHP_EOL;
'''
if driver.exists():
    assert driver.read_text(encoding='utf-8') == php
else:
    with driver.open('x', encoding='utf-8') as stream:
        stream.write(php)
result = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php',
                         str(driver.relative_to(ROOT)).replace('\\', '/'), mode],
                        cwd=ROOT, capture_output=True, encoding='utf-8')
print(result.stdout + result.stderr)
sys.exit(result.returncode)
