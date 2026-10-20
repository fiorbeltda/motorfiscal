"""Prepare exact requests against own52 snapshot and live original monthly projection."""
from pathlib import Path
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base53-command-integration-20261009'
E.mkdir(exist_ok=True)
mode = sys.argv[1]
assert mode in ['capture', 'replay']
php = r'''<?php
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$root=storage_path('app/phase4b-negative-base53-command-integration-20261009');
$snapshotPath=storage_path('app/phase4b-negative-base52-settlement-draft-20261009/command-financial-snapshot.json');
$snapshot=json_decode(file_get_contents($snapshotPath),true,512,JSON_THROW_ON_ERROR);
$capture=json_decode(file_get_contents(storage_path('app/phase4b-negative-base52-settlement-draft-20261009/settlement-native-result.json')),true,512,JSON_THROW_ON_ERROR);
$month=$capture['input']['month'];
$factory=new \App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
$scenario=$factory->scenarioLoaderFromKnowledge($snapshot['knowledge'])->fromArray($snapshot['input']);
$result=(new \App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService($factory))->evaluate($snapshot['knowledge'],$scenario)->toArray();
if (\App\Modules\Fiscal\Core\Shared\CanonicalJson::encode($result)!==\App\Modules\Fiscal\Core\Shared\CanonicalJson::encode($snapshot['result'])) throw new RuntimeException('Own52 financial replay differs');
$stages=array_column($result['trace'],null,'stage');
$catalog=$snapshot['knowledge']['documents']['rtc_code_catalog'];
$rules=['closing'=>$catalog['dere_monthly_closing_projection'],'balance'=>$catalog['dere_trial_balance_projection'],
 'deductions'=>$catalog['dere_deduction_projection'],'pgcc_projection'=>$catalog['dere_pgcc_projection'],
 'contributor_projection'=>$catalog['dere_contributor_projection']];
$projection=(new \App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector)->project($stages['FINANCIAL_OPERATION_AND_BASE'],$month,
 $scenario->input->context->knowledgeAt,$rules);
if ($projection['status']!=='PROJECTED') throw new RuntimeException(json_encode($projection));
$hash=fn($value)=>\App\Modules\Fiscal\Core\Shared\CanonicalJson::hash($value);
$options=json_decode(file_get_contents(storage_path('app/phase4b-negative-base50-closing-draft-20261009/command-negative-base-request.json')),true,512,JSON_THROW_ON_ERROR);
$options['snapshot_hash']=$hash($snapshot);$options['monthly_input_hash']=$hash($month);
$options['baseline_projection_hash']=$hash($projection);
$settlement=$capture['input']['request'];
$settlement['snapshot_hash']=$hash($snapshot);$settlement['monthly_input_hash']=$hash($month);
$settlement['operation_hash']=$hash($stages['FINANCIAL_OPERATION_AND_BASE']);
$settlement['gross_tax_hash']=$hash($stages['FINANCIAL_GROSS_DEBITS']);
$settlement['baseline_projection_hash']=$hash($projection);
if ($options['parameters']!==$settlement['parameters']||$options['prior_closing_events']!==$settlement['prior_closing_events']) throw new RuntimeException('Declaration and settlement requests differ');
$proposal=(new \App\Modules\Fiscal\ConsumptionReform\DereFinancialNegativeBaseSettlementPlanner)->plan(
 $stages['FINANCIAL_OPERATION_AND_BASE'],$month,$stages['FINANCIAL_GROSS_DEBITS'],$scenario->input->context->knowledgeAt,
 $rules,$settlement,$catalog['dere_negative_base_local_settlement'],resource_path('fiscal/sources/official'));
if ($proposal['status']!=='LOCAL_BCN_TAX_SETTLEMENT_PROPOSED') throw new RuntimeException(json_encode($proposal));
$record=['status'=>'OWN52_REPLAYED_MONTHLY_REQUESTS_PREPARED_FOR_COMMAND_INTEGRATION',
 'snapshot_path'=>'storage/app/phase4b-negative-base52-settlement-draft-20261009/command-financial-snapshot.json',
 'snapshot_file_sha256'=>hash_file('sha256',$snapshotPath),'snapshot_hash'=>$hash($snapshot),
 'monthly_input_hash'=>$hash($month),'baseline_projection_hash'=>$hash($projection),
 'options_request_hash'=>$hash($options),'settlement_request_hash'=>$hash($settlement),
 'policy_hash'=>$catalog['dere_negative_base_local_settlement']['source_hash'],
 'parameters_and_prior_chain_match'=>true,'financial_replay_matches'=>true,
 'native_settlement_selection_integrated'=>false,'production_ready'=>false];
$values=['command-month-input.json'=>$month,'command-options-request.json'=>$options,
 'command-settlement-request.json'=>$settlement,'command-input-preparation.json'=>$record,
 'prepared-settlement-proposal.json'=>$proposal];
foreach ($values as $name=>$value) {
 $path=$root.'/'.$name;
 if ($argv[1]==='capture') {
  $stream=fopen($path,'x');if ($stream===false) throw new RuntimeException('Refusing to replace prepared request');
  fwrite($stream,json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);fclose($stream);
 } else {
  $saved=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
  if ($saved!==$value) throw new RuntimeException('Prepared request replay differs: '.$name);
 }
}
echo 'PASS: own52 financial replay, exact original monthly projection and matched declaration/settlement requests; '.$argv[1].PHP_EOL;
'''
driver = E / 'prepare-command-inputs.php'
if driver.exists():
    assert driver.read_text(encoding='utf-8') == php
else:
    with driver.open('x', encoding='utf-8') as stream:
        stream.write(php)
result = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php',
                         driver.relative_to(ROOT).as_posix(), mode], cwd=ROOT, capture_output=True, encoding='utf-8')
print(result.stdout + result.stderr)
if result.returncode == 0:
    assert (E / 'command-input-preparation.json').is_file()
    assert 'PASS: own52 financial replay' in result.stdout
sys.exit(result.returncode)
