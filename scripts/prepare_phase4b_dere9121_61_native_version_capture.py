"""Prepare own61 native version request preserving baseline and selecting matched active03 bytes."""
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-61-version-integration-20261013'
P=ROOT/'storage/app/phase4b-dere9121-54-preparation-20261013'
source=(P/'capture-public-settlement-v3.php').read_text(encoding='utf-8').split('$originXml = ',1)[0]
body=r'''
$captured=json_decode(file_get_contents(storage_path('app/phase4b-dere9121-60-version-binding-20261013/business-binding-native-result.json')),true,512,JSON_THROW_ON_ERROR);
$fixture=$captured['cases']['matching-native-business']['fixture'];
$binding=['synthetic'=>true,'events'=>$fixture['events'],
    'pgcc_source_hash'=>$projection['pgcc_qualification']['source_hash'],'pgcc_receipt'=>$fixture['pgcc']['pgcc_receipt']];
$request=json_decode(file_get_contents(storage_path('app/phase4b-negative-base50-closing-draft-20261009/command-negative-base-request.json')),true,512,JSON_THROW_ON_ERROR);
$request['snapshot_hash']=App\Modules\Fiscal\Core\Shared\CanonicalJson::hash($snapshot);
$request['monthly_input_hash']=App\Modules\Fiscal\Core\Shared\CanonicalJson::hash($month);
$request['operation_hash']=App\Modules\Fiscal\Core\Shared\CanonicalJson::hash($stages['FINANCIAL_OPERATION_AND_BASE']);
$request['gross_tax_hash']=App\Modules\Fiscal\Core\Shared\CanonicalJson::hash($stages['FINANCIAL_GROSS_DEBITS']);
$request['baseline_projection_hash']=App\Modules\Fiscal\Core\Shared\CanonicalJson::hash($projection);
$request['prior_period']='2026-08';
$request['current_public_offer_return']=$binding;
$result=(new App\Modules\Fiscal\ConsumptionReform\DereFinancialPublicOfferNegativeBaseSettlementPlanner)->plan(
    $stages['FINANCIAL_OPERATION_AND_BASE'],$month,$stages['FINANCIAL_GROSS_DEBITS'],$known,$rules,$request,
    $catalog['dere_public_offer_negative_base_local_settlement'],resource_path('fiscal/sources/official'));
if ($result['status']!=='LOCAL_BCN_TAX_SETTLEMENT_PROPOSED') throw new RuntimeException(json_encode($result));
if (($result['composition']['active_version_binding']['active_event']['sequence']??null)!=='03') throw new RuntimeException('Active version not selected');
$record=['input'=>['operation'=>$stages['FINANCIAL_OPERATION_AND_BASE'],'month'=>$month,'gross'=>$stages['FINANCIAL_GROSS_DEBITS'],
    'known_at'=>$known->format(DATE_ATOM),'rules'=>$rules,'request'=>$request,'policy'=>$catalog['dere_public_offer_negative_base_local_settlement']],
    'result'=>$result,'financial_replay_matches'=>true,'production_ready'=>false];
foreach (['native-version-settlement.json'=>$record,'command-month.json'=>$month,'command-request.json'=>$request] as $name=>$value) {
    $path=__DIR__.'/'.$name;
    if ($argv[1]==='capture') {
        $stream=fopen($path,'x');if($stream===false) throw new RuntimeException('Refusing overwrite');
        fwrite($stream,json_encode($value,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));fclose($stream);
    } elseif ($value!==json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR)) throw new RuntimeException('Version replay differs');
}
echo 'PASS:own61 version chain -> native business -> active03 -> BCN/tax proposal; '.$argv[1].PHP_EOL;
} catch (Throwable $exception) { fwrite(STDERR,$exception->getMessage());exit(1); }
'''
with (E/'capture-native-version.php').open('x',encoding='utf-8') as stream:
    stream.write(source+body)
print('PASS:own61 capture driver prepared with native baseline and active03 chain')
