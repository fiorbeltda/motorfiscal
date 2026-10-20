<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DereFinancialPublicOfferNegativeBaseSettlementPlanner;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Tests\TestCase;

uses(TestCase::class);

it('requires explicit successor policy and zero return for empty public accounts', function (string $profile, string $case): void {
    $input = json_decode((string) file_get_contents(storage_path('app/phase4b-dere9121-58-no-movement-integration-20261013/'.$profile.'-no-movement-settlement.json')), true, 512, JSON_THROW_ON_ERROR)['input'];
    if ($case === 'disabled') {
        $input['policy']['allow_native_no_movement'] = false;
        unset($input['policy']['source_hash']);
        $input['policy']['source_hash'] = CanonicalJson::hash($input['policy']);
    } elseif ($case === 'old-policy') {
        $catalog = json_decode((string) file_get_contents(resource_path('fiscal/catalogs/rtc-codes-2026.10.13-28-draft54.json')), true, 512, JSON_THROW_ON_ERROR);
        $input['policy'] = $catalog['dere_public_offer_negative_base_local_settlement'];
    } elseif ($case === 'future-policy') {
        $input['known_at'] = (new DateTimeImmutable($input['known_at']))->modify('-1 second')->format(DATE_ATOM);
    } elseif ($case === 'nonzero') {
        $input['request']['current_public_offer_return']['xml'] = str_replace('<vApurTot>0.00</vApurTot>', '<vApurTot>1.00</vApurTot>', $input['request']['current_public_offer_return']['xml']);
    } elseif ($case === 'missing-xml') {
        unset($input['request']['current_public_offer_return']['xml']);
    } elseif ($case === 'pgcc') {
        $input['request']['current_public_offer_return']['pgcc_source_hash'] = str_repeat('0', 64);
    } elseif ($case === 'sequence') {
        $input['request']['current_public_offer_return']['expected_sequence'] = '01';
    } elseif ($case === 'book') {
        $input['month']['book']['accounts'][0]['vApur'] = '9999.00';
        $input['request']['monthly_input_hash'] = CanonicalJson::hash($input['month']);
    }
    $result = (new DereFinancialPublicOfferNegativeBaseSettlementPlanner)->plan($input['operation'], $input['month'], $input['gross'],
        new DateTimeImmutable($input['known_at']), $input['rules'], $input['request'], $input['policy'], resource_path('fiscal/sources/official'));
    if ($case === 'valid') {
        expect($result['status'])->toBe('LOCAL_BCN_TAX_SETTLEMENT_PROPOSED')
            ->and($result['composition']['calculation_input']['public_offer_debt_return'])->toBe('0.00')
            ->and($result['composition']['d9121_reconciliation']['title_count'])->toBe(0)
            ->and($result['assessed_pools']['1010I']['positive_base'])->toBe('693.07')
            ->and($result['ibs_nominal_proposal'])->toBe('0.64')->and($result['cbs_nominal_proposal'])->toBe('6.15')
            ->and($result['original_gross_debits'])->toBe($input['gross']);
    } else {
        expect($result['status'])->not->toBe('LOCAL_BCN_TAX_SETTLEMENT_PROPOSED');
        if (in_array($case, ['disabled', 'old-policy'], true)) {
            expect($result['reason'])->toBe('RTC_DERE_CURRENT_COMPOSITION_INITIAL_SYNTHETIC_D9121_AND_PGCC_BINDING_REQUIRED');
        }
    }
    if ($case === 'book') {
        expect($result['status'])->toBe('INVALID')
            ->and($result['reason'])->toBe('RTC_DERE_BALANCE_ASSESSMENT_MOVEMENT_CONFLICT');
    } else {
        expect($result['production_ready'])->toBeFalse();
    }
})->with(['qualified-direct-only', 'qualified-fund-only', 'qualified-both-codes'])
    ->with(['valid', 'disabled', 'old-policy', 'future-policy', 'nonzero', 'missing-xml', 'pgcc', 'sequence', 'book']);
