<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DereFinancialPublicOfferNegativeBaseSettlementPlanner;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Tests\TestCase;

uses(TestCase::class);

it('requires policy and complete monthly-bound period history before public settlement', function (string $case): void {
    $input = json_decode((string) file_get_contents(storage_path('app/phase4b-dere9121-63-period-integration-20261013/native-period-settlement.json')), true, 512, JSON_THROW_ON_ERROR)['input'];
    $expected = null;
    if ($case === 'disabled') {
        $input['policy']['require_public_period_history'] = false;
        unset($input['policy']['source_hash']);
        $input['policy']['source_hash'] = CanonicalJson::hash($input['policy']);
        $expected = 'RTC_DERE_BCN_SETTLEMENT_PUBLIC_PERIOD_HISTORY_POLICY_REQUIRED';
    } elseif ($case === 'missing') {
        unset($input['request']['current_public_offer_return']['period_history']);
        $expected = 'RTC_DERE_CURRENT_COMPOSITION_PUBLIC_PERIOD_HISTORY_REQUIRED';
    } elseif ($case === 'month-hash') {
        $input['request']['current_public_offer_return']['period_history']['monthly_input_hash'] = str_repeat('0', 64);
        $expected = 'RTC_DERE_PUBLIC_BINDING_MONTHLY_BOUND_PERIOD_HISTORY_REQUIRED';
    } elseif ($case === 'history-hash') {
        $input['request']['current_public_offer_return']['period_history']['witness']['history_hash'] = str_repeat('0', 64);
        $expected = 'RTC_DERE_PUBLIC_PERIOD_HISTORY_HASH_OR_KNOWLEDGE_CONFLICT';
    } elseif ($case === 'closed') {
        $h = &$input['request']['current_public_offer_return']['period_history'];
        array_pop($h['events']);
        $h['witness']['history_hash'] = CanonicalJson::hash(['supplier_id' => $h['witness']['supplier_id'], 'period' => $h['witness']['period'],
            'initial_state' => $h['witness']['initial_state'], 'from' => $h['witness']['from'], 'through' => $h['witness']['through'], 'events' => $h['events']]);
        unset($h);
        $expected = 'RTC_DERE_PUBLIC_PERIOD_PUBLIC_EVENT_RECEIVED_WHILE_PERIOD_CLOSED';
    } elseif ($case === 'scalar-event') {
        $input['request']['current_public_offer_return']['period_history']['events'] = [1];
        $expected = 'RTC_DERE_PUBLIC_BINDING_PERIOD_EVENT_OBJECT_REQUIRED';
    } elseif ($case === 'single-xml') {
        $input['request']['current_public_offer_return'] = ['synthetic' => true, 'xml' => '<unsupported/>'];
        $expected = 'RTC_DERE_BCN_SETTLEMENT_PUBLIC_EVENT_CHAIN_REQUIRED_BY_PERIOD_HISTORY_POLICY';
    }
    $result = (new DereFinancialPublicOfferNegativeBaseSettlementPlanner)->plan($input['operation'], $input['month'], $input['gross'],
        new DateTimeImmutable($input['known_at']), $input['rules'], $input['request'], $input['policy'], resource_path('fiscal/sources/official'));
    if ($expected === null) {
        expect($result['status'])->toBe('LOCAL_BCN_TAX_SETTLEMENT_PROPOSED')
            ->and($result['composition']['active_version_binding']['period_history_qualification']['reopening_count'])->toBe(2)
            ->and($result['ibs_nominal_proposal'])->toBe('0.74')->and($result['cbs_nominal_proposal'])->toBe('7.02')
            ->and($result['production_ready'])->toBeFalse();
    } else {
        expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe($expected)
            ->and($result['production_ready'])->toBeFalse();
    }
})->with(['valid', 'disabled', 'missing', 'month-hash', 'history-hash', 'closed', 'scalar-event', 'single-xml']);
