<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ImportFxTransactionQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

require_once __DIR__.'/ImportFxTransaction68Test.php';

it('refuses impossible policy and transaction dates instead of normalizing them', function (string $case): void {
    $input = fxTransactionFixture68();
    $facts = $input['facts'];
    $policy = $input['policy'];
    switch ($case) {
        case 'proof-end':$facts['rtc.import_fx_proof']['valid_to'] = '2026-09-31';
            break;
        case 'proof-start':$facts['rtc.import_fx_proof']['valid_from'] = '2026-00-00';
            break;
        case 'proof-clock':$facts['rtc.import_fx_proof']['observed_at'] = '2026-09-31T00:00:00+00:00';
            break;
        case 'policy-end':$policy['valid_to'] = '2026-09-31';
            break;
        case 'policy-start':$policy['valid_from'] = '2026-00-00';
            break;
        case 'policy-clock':$policy['published_at'] = '2026-09-31T00:00:00+00:00';
            break;
    }
    unset($facts['rtc.import_fx_proof']['source_hash']);
    $facts['rtc.import_fx_proof']['source_hash'] = CanonicalJson::hash($facts['rtc.import_fx_proof']);
    $facts['rtc.import_fx_proof'] = [$facts['rtc.import_fx_proof']];
    unset($policy['source_hash']);
    $policy['source_hash'] = CanonicalJson::hash($policy);
    $result = (new ImportFxTransactionQualifier)->qualify($facts, $input['tax_point'], $input['known'], $policy);
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason'])->toBe('RTC_IMPORT_FX_'.(str_starts_with($case, 'proof') ? 'TRANSACTION_EXACT_DATE_REQUIRED' : 'POLICY_EXACT_DATE_REQUIRED'))
        ->and($result['production_ready'])->toBeFalse();
})->with(['proof-end', 'proof-start', 'proof-clock', 'policy-end', 'policy-start', 'policy-clock']);
