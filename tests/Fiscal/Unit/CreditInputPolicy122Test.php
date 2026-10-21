<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CreditInputPolicy122;
use App\Modules\Fiscal\ConsumptionReform\MaterialInputPolicy119;

it('requires the new credit guard by version even if its field was removed', function (): void {
    expect(CreditInputPolicy122::required(['version' => CreditInputPolicy122::VERSION]))->toBeTrue()
        ->and(CreditInputPolicy122::required(['version' => MaterialInputPolicy119::VERSION]))->toBeFalse()
        ->and(CreditInputPolicy122::required(['credit_input_policy122' => null]))->toBeTrue();
});

it('binds the exact preserved source contract without claiming credit entitlement', function (): void {
    $catalog = ['version' => CreditInputPolicy122::VERSION, 'credit_input_policy122' => CreditInputPolicy122::policy()];
    $result = (new CreditInputPolicy122)->qualify($catalog, new DateTimeImmutable(MaterialInputPolicy119::AVAILABLE_AT));
    expect($result['status'])->toBe('RESOLVED')->and($result['credit_granted'])->toBeFalse()
        ->and($result['document_authenticated'])->toBeFalse()->and($result['extinction_proven'])->toBeFalse();
});

it('rejects missing changed or expanded policy data and early knowledge', function (string $mutation): void {
    $policy = CreditInputPolicy122::policy();
    $known = new DateTimeImmutable('2026-10-21T11:10:00+00:00');
    $catalog = ['version' => CreditInputPolicy122::VERSION, 'credit_input_policy122' => $policy];
    switch ($mutation) {
        case 'absent': unset($catalog['credit_input_policy122']);
            break;
        case 'null': $catalog['credit_input_policy122'] = null;
            break;
        case 'source hash': $catalog['credit_input_policy122']['source']['sha256'] = str_repeat('0', 64);
            break;
        case 'source time': $catalog['credit_input_policy122']['source']['observed_at_raw'] = '2026-01-01T00:00:00+00:00';
            break;
        case 'amount types': $catalog['credit_input_policy122']['amount_types'][] = 'JSON_FLOAT';
            break;
        case 'extra': $catalog['credit_input_policy122']['credit_granted'] = true;
            break;
        case 'early': $known = new DateTimeImmutable('2026-10-20T12:49:04.592625+00:00');
            break;
        default: unset($catalog['credit_input_policy122'][$mutation]);
    }
    $result = (new CreditInputPolicy122)->qualify($catalog, $known);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['credit_granted'])->toBeFalse();
})->with(['absent', 'null', 'contract', 'available_at', 'source', 'source_articles', 'amount_types', 'line_identity', 'scope', 'source hash', 'source time', 'amount types', 'extra', 'early']);
