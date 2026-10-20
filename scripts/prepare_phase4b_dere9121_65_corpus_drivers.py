"""Prepare native active01/03 captures per source-bound case, retaining full monthly reconstruction."""
from pathlib import Path
import json,re
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-65-version-corpus-20261013'
P=ROOT/'storage/app/phase4b-dere9121-63-period-integration-20261013'
manifest=json.loads((E/'source-corpus-manifest.json').read_text(encoding='utf-8'))
prefix=(P/'capture-native-period.php').read_text(encoding='utf-8').split('$captured=json_decode',1)[0]
body=r'''
$template=json_decode(file_get_contents(storage_path('app/phase4b-dere9121-63-period-integration-20261013/native-period-settlement.json')),true,512,JSON_THROW_ON_ERROR)['input']['request']['current_public_offer_return'];
$nativeXml=$projection['debt_yield_projection']['xml'];
$nativeTotal=$projection['debt_yield_projection']['total_vApur'];
$nativeDocument=new DOMDocument;
if (!$nativeDocument->loadXML($nativeXml,LIBXML_NONET)) throw new RuntimeException('Native public XML unavailable');
$business=$nativeDocument->getElementsByTagNameNS('http://www.dere.gov.br/schemas/evtDebOpOfPublic/v0_0_3','infoDebOpOfPublic')->item(0);
if (!$business instanceof DOMElement) throw new RuntimeException('Native public business unavailable');
$events=[];
foreach ($template['events'] as $index=>$event) {
    $origin=new DOMDocument;
    if (!$origin->loadXML($event['origin_xml'],LIBXML_NONET)) throw new RuntimeException('Template origin unavailable');
    if ($index!==2) {
        $old=$origin->getElementsByTagNameNS('http://www.dere.gov.br/schemas/evtDebOpOfPublic/v0_0_3','infoDebOpOfPublic')->item(0);
        if (!$old instanceof DOMElement || $old->parentNode===null) throw new RuntimeException('Template business unavailable');
        $old->parentNode->replaceChild($origin->importNode($business,true),$old);
    }
    $originXml=$origin->saveXML();
    if (!is_string($originXml)) throw new RuntimeException('Origin serialization failed');
    $response=new DOMDocument;
    if (!$response->loadXML($event['return_xml'],LIBXML_NONET)) throw new RuntimeException('Template return unavailable');
    $hash=$response->getElementsByTagNameNS('http://www.dere.gov.br/schemas/evtRetornoTitPub/v0_0_2','hash')->item(0);
    if (!$hash instanceof DOMElement) throw new RuntimeException('Return hash unavailable');
    $hash->textContent=base64_encode(hash('sha256',$originXml,true));
    if ($index!==2) {
        $total=$response->getElementsByTagNameNS('http://www.dere.gov.br/schemas/evtRetornoTitPub/v0_0_2','vApurTot')->item(0);
        if (!$total instanceof DOMElement) throw new RuntimeException('Return total unavailable');
        $total->textContent=$nativeTotal;
    }
    $returnXml=$response->saveXML();
    if (!is_string($returnXml)) throw new RuntimeException('Return serialization failed');
    $events[]=['origin_xml'=>$originXml,'return_xml'=>$returnXml];
}
$sequence=$argv[2]??null;
if (!in_array($sequence,['01','03'],true)) throw new RuntimeException('Explicit active sequence required');
$binding=$template;
$binding['events']=array_slice($events,0,$sequence==='01'?2:4);
$binding['pgcc_source_hash']=$projection['pgcc_qualification']['source_hash'];
$binding['period_history']['monthly_input_hash']=App\Modules\Fiscal\Core\Shared\CanonicalJson::hash($month);
$witness=$binding['period_history']['witness'];
$witness['through']=$known->format('Y-m-d\TH:i:s.u').'0'.$known->format('P');
$witness['history_hash']=App\Modules\Fiscal\Core\Shared\CanonicalJson::hash([
    'supplier_id'=>$witness['supplier_id'],'period'=>$witness['period'],'initial_state'=>$witness['initial_state'],
    'from'=>$witness['from'],'through'=>$witness['through'],'events'=>$binding['period_history']['events']]);
$binding['period_history']['witness']=$witness;
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
if ($result['composition']['active_version_binding']['active_event']['sequence']!==$sequence
    || $result['composition']['calculation_input']['public_offer_debt_return']!==$nativeTotal
    || $result['composition']['active_version_binding']['period_history_qualification']['reopening_count']!==2) {
    throw new RuntimeException('Native public version/economics/history not bound');
}
$record=['input'=>['operation'=>$stages['FINANCIAL_OPERATION_AND_BASE'],'month'=>$month,'gross'=>$stages['FINANCIAL_GROSS_DEBITS'],
    'known_at'=>$known->format(DATE_ATOM),'rules'=>$rules,'request'=>$request,'policy'=>$catalog['dere_public_offer_negative_base_local_settlement']],
    'result'=>$result,'financial_replay_matches'=>true,'production_ready'=>false];
foreach (['CASE-active'.$sequence.'-settlement.json'=>$record,'CASE-month.json'=>$month,'CASE-active'.$sequence.'-request.json'=>$request] as $name=>$value) {
    $path=__DIR__.'/'.$name;
    if ($argv[1]==='capture' && !file_exists($path)) {
        $f=fopen($path,'x');if($f===false) throw new RuntimeException('Refusing overwrite');
        fwrite($f,json_encode($value,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));fclose($f);
    } elseif ($value!==json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR)) {
        throw new RuntimeException('Native version replay differs');
    }
}
echo 'PASS:CASE own65 active'.$sequence.' native titles, complete monthly proofs, period history and BCN proposal; '.$argv[1].PHP_EOL;
} catch (Throwable $error) { fwrite(STDERR,$error->getMessage());exit(1); }
'''
for case in manifest['cases']:
    name=case['name']
    assert re.fullmatch('[a-z-]+',name)
    source=prefix.replace("__DIR__.'/financial-snapshot.json'","__DIR__+'/PLACEHOLDER'")
    source=source.replace("__DIR__+'/PLACEHOLDER'","__DIR__+'/"+name+"-snapshot.json'")
    source=source.replace("__DIR__+'/"+name+"-snapshot.json'","__DIR__.'/"+name+"-snapshot.json'")
    month="resource_path('fiscal/scenarios/dere-variable-yield-2026.10.06-27/constant.json')"
    assert source.count(month)==1
    source=source.replace(month,"resource_path('"+case['source_month']['path'].removeprefix('resources/')+"')")
    marker="$proof['value']['book_input_hash']"
    assert source.count(marker)==1
    source=source.replace(marker,"$proof['value']['operation_hash'] = App\\Modules\\Fiscal\\Core\\Shared\\CanonicalJson::hash($stages['FINANCIAL_OPERATION_AND_BASE']);\n"+marker)
    source=source.replace('.D9121.54.EXPLICIT.ISS.SUCCESSOR','.D9121.65.EXPLICIT.VERSION.CORPUS')
    source+=body.replace('CASE',name)
    target=E/(name+'-capture.php')
    if target.exists():
        assert target.read_text(encoding='utf-8')==source
    else:
        with target.open('x',encoding='utf-8') as f:
            f.write(source)
print('PASS:twenty own65 native financial/monthly drivers prepared for active01 and03')
