<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DereFinancialPublicOfferNegativeBaseSettlementPlanner;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Tests\TestCase;

uses(TestCase::class);

it('requires version policy and matched active economics before proposing public taxes', function (string $case): void {
    $input = json_decode((string) file_get_contents(storage_path('app/phase4b-dere9121-61-version-integration-20261013/native-version-settlement.json')), true, 512, JSON_THROW_ON_ERROR)['input'];
    if ($case === 'disabled') {
        $input['policy']['allow_public_event_versions'] = false;
        unset($input['policy']['source_hash']);
        $input['policy']['source_hash'] = CanonicalJson::hash($input['policy']);
    } elseif ($case === 'old-policy') {
        $catalog = json_decode((string) file_get_contents(resource_path('fiscal/catalogs/rtc-codes-2026.10.13-28-draft58.json')), true, 512, JSON_THROW_ON_ERROR);
        $input['policy'] = $catalog['dere_public_offer_negative_base_local_settlement'];
    } elseif ($case === 'expectation') {
        $input['request']['current_public_offer_return']['expected_sequence'] = '00';
    } elseif ($case === 'excluded') {
        $input['request']['current_public_offer_return']['events'] = array_slice($input['request']['current_public_offer_return']['events'], 0, 3);
    } elseif ($case === 'economics') {
        $capture = json_decode((string) file_get_contents(storage_path('app/phase4b-dere9121-59-version-preparation-20261013/version-native-v2-result.json')), true, 512, JSON_THROW_ON_ERROR);
        $input['request']['current_public_offer_return']['events'] = $capture['events'];
    } elseif ($case === 'dual-xml') {
        $input['request']['current_public_offer_return']['xml'] = '<ignored/>';
    } elseif ($case === 'closed') {
        $input['month']['prerequisites']['existing_closing'] = true;
        $input['request']['monthly_input_hash'] = CanonicalJson::hash($input['month']);
    }
    $result = (new DereFinancialPublicOfferNegativeBaseSettlementPlanner)->plan($input['operation'], $input['month'], $input['gross'],
        new DateTimeImmutable($input['known_at']), $input['rules'], $input['request'], $input['policy'], resource_path('fiscal/sources/official'));
    if ($case === 'valid') {
        expect($result['status'])->toBe('LOCAL_BCN_TAX_SETTLEMENT_PROPOSED')
            ->and($result['composition']['active_version_binding']['active_event']['sequence'])->toBe('03')
            ->and($result['composition']['calculation_input']['public_offer_debt_return'])->toBe('98.17')
            ->and($result['ibs_nominal_proposal'])->toBe('0.74')->and($result['cbs_nominal_proposal'])->toBe('7.02')
            ->and($result['original_gross_debits'])->toBe($input['gross']);
    } else {
        expect($result['status'])->not->toBe('LOCAL_BCN_TAX_SETTLEMENT_PROPOSED');
        if (in_array($case, ['disabled', 'old-policy'], true)) {
            expect($result['reason'])->toBe('RTC_DERE_BCN_SETTLEMENT_PUBLIC_EVENT_VERSION_POLICY_REQUIRED');
        }
    }
})->with(['valid', 'disabled', 'old-policy', 'expectation', 'excluded', 'economics', 'dual-xml', 'closed']);
