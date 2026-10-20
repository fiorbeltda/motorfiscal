"""Prepare own54 native capture using installed classes and captured temporal policy."""
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'
source = (E / 'exercise-planned-composition-v2.php').read_text(encoding='utf-8').split('$input = ', 1)[0]
source = source.replace("require __DIR__.'/planned-DerePublicOfferDebtReturnXmlReader.php';\n", '')
source = source.replace("require __DIR__.'/planned-DereFinancialPublicOfferCurrentBaseCompositionQualifier.php';\n", '')
source = source.replace("storage_path('app/phase4b-variable-yield-20261006-27/constant-snapshot.json')", "__DIR__.'/financial-snapshot.json'")
body = '''
$request = json_decode(file_get_contents(storage_path('app/phase4b-negative-base50-closing-draft-20261009/command-negative-base-request.json')), true, 512, JSON_THROW_ON_ERROR);
$request['snapshot_hash'] = App\\Modules\\Fiscal\\Core\\Shared\\CanonicalJson::hash($snapshot);
$request['monthly_input_hash'] = App\\Modules\\Fiscal\\Core\\Shared\\CanonicalJson::hash($month);
$request['operation_hash'] = App\\Modules\\Fiscal\\Core\\Shared\\CanonicalJson::hash($stages['FINANCIAL_OPERATION_AND_BASE']);
$request['gross_tax_hash'] = App\\Modules\\Fiscal\\Core\\Shared\\CanonicalJson::hash($stages['FINANCIAL_GROSS_DEBITS']);
$request['baseline_projection_hash'] = App\\Modules\\Fiscal\\Core\\Shared\\CanonicalJson::hash($projection);
$request['current_public_offer_return'] = $binding;
$policy = $catalog['dere_public_offer_negative_base_local_settlement'];
$result = (new App\\Modules\\Fiscal\\ConsumptionReform\\DereFinancialPublicOfferNegativeBaseSettlementPlanner)->plan(
    $stages['FINANCIAL_OPERATION_AND_BASE'], $month, $stages['FINANCIAL_GROSS_DEBITS'], $known, $rules,
    $request, $policy, resource_path('fiscal/sources/official'));
if ($result['status'] !== 'LOCAL_BCN_TAX_SETTLEMENT_PROPOSED') {
    throw new RuntimeException(json_encode($result, JSON_THROW_ON_ERROR));
}
$record = ['input' => ['operation' => $stages['FINANCIAL_OPERATION_AND_BASE'], 'gross' => $stages['FINANCIAL_GROSS_DEBITS'],
    'month' => $month, 'known_at' => $known->format(DATE_ATOM), 'rules' => $rules, 'request' => $request, 'policy' => $policy],
    'result' => $result, 'financial_snapshot_sha256' => hash_file('sha256', __DIR__.'/financial-snapshot.json'),
    'financial_replay_matches' => true, 'production_ready' => false];
$path = __DIR__.'/public-settlement-native-result.json';
if (($argv[1] ?? '') === 'capture') {
    $stream = fopen($path, 'x');
    if ($stream === false) throw new RuntimeException('Refusing to overwrite public settlement.');
    fwrite($stream, json_encode($record, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    fclose($stream);
} else {
    if ($record !== json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)) {
        throw new RuntimeException('Public settlement replay differs.');
    }
}
echo 'PASS: own54 public D9121 -> current composition -> BCN -> IBS/CBS proposal; '.$argv[1].PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage());
    exit(1);
}
'''
with (E / 'capture-public-settlement.php').open('x', encoding='utf-8') as stream:
    stream.write(source + body)
print('PASS: own54 capture driver prepared; installed classes, live captured policy, structured success checks')
