<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRealizationQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

require_once __DIR__.'/../Support/TvmCurrentFixtures.php';

it('qualifies first-month gains losses and opposite sales individually', function (string $case): void {
    [$ledger, $rule, $known, $fixture] = tvmCurrentFirstFixture($case);
    $result = tvmCurrentBase($ledger, $rule, $known);
    expect($result['status'])->toBe('RESOLVED');
    $binding = $result['realization_bindings']['tvm1'];
    expect($binding['revenue'])->toBe($fixture['expected']['revenue'])->and($binding['deductions'])->toBe($fixture['expected']['deductions'])
        ->and($binding['historical_fiscal_snapshot_replay_performed'])->toBeFalse()->and($binding['production_ready'])->toBeFalse();
    if (getenv('TVM_CURRENT_CAPTURE') === '1') {
        $root = dirname(__DIR__, 3);
        $scenario = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
        $scenario['scenario_id'] = 'SYNTHETIC-current-'.$case;
        $scenario['facts']['rtc.financial_ledger'] = [$ledger];
        $path = $root.'/resources/fiscal/scenarios/tvm-base-2026.10.06-28-draft/'.$case.'-financial.json';
        if (is_file($path)) {
            expect(CanonicalJson::encode(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($scenario));
        } else {
            $stream = fopen($path, 'x');
            expect($stream)->not->toBeFalse();
            fwrite($stream, json_encode($scenario, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
            fclose($stream);
        }
    }
})->with(['first-gain', 'first-loss', 'first-offsetting']);

it('requires qualified losses even when another sale offsets them', function (string $case): void {
    [$ledger, $rule, $known] = tvmCurrentFirstFixture($case);
    $ledger['entries'][4]['securities_realization']['deduction_evidence'] = [];
    expect(tvmCurrentBase(sealTvmCurrentLedger($ledger), $rule, $known)['reason'])->toBe('RTC_TVM_CURRENT_LOSS_AUTHORIZATION_UNPROVEN');
})->with(['first-loss', 'first-offsetting']);

it('rejects a loss outside the qualified operational limits', function (): void {
    [$ledger, $rule, $known] = tvmCurrentFirstFixture('first-loss');
    $ledger['entries'][4]['securities_realization']['deduction_evidence'][0]['conditions']['within_operational_limits'] = false;
    expect(tvmCurrentBase(sealTvmCurrentLedger($ledger), $rule, $known)['reason'])->toBe('RTC_TVM_CURRENT_LOSS_AUTHORIZATION_UNPROVEN');
});

it('requires independent loss qualification in addition to authorization flags', function (): void {
    [$ledger, $rule, $known] = tvmCurrentFirstFixture('first-loss');
    unset($ledger['entries'][4]['securities_realization']['deduction_evidence'][0]['independent_evidence']);
    expect(tvmCurrentBase(sealTvmCurrentLedger($ledger), $rule, $known)['reason'])->toBe('RTC_TVM_CURRENT_INDEPENDENT_LOSS_QUALIFICATION_UNPROVEN');
});

it('requires the explicit first-acquisition policy before accepting a missing prior snapshot', function (): void {
    [$ledger, $rule, $known] = tvmCurrentFirstFixture('first-gain');
    unset($rule['securities_realization']['analysis']['first_acquisition_without_history_allowed']);
    expect(tvmCurrentBase($ledger, $rule, $known)['reason'])->toBe('RTC_TVM_REALIZATION_REPLAYED_FISCAL_HISTORY_MISSING');
});

it('rejects a previous fiscal snapshot supplied alongside first acquisition', function (): void {
    [$ledger, $rule, $known] = tvmCurrentFirstFixture('first-gain');
    $ledger['entries'][4]['securities_realization']['analysis_input']['recognition_history'] = ['unexpected' => true];
    expect(tvmCurrentBase($ledger, $rule, $known)['reason'])->toBe('RTC_TVM_REALIZATION_FIRST_ACQUISITION_UNEXPECTED_FISCAL_HISTORY');
});

it('derives current TVM components without taxing principal or the prior assessed return again', function (): void {
    [$ledger, $rule, $known] = tvmCurrentFixture();
    $result = tvmCurrentBase($ledger, $rule, $known);
    expect($result['status'])->toBe('RESOLVED')->and($result['revenue'])->toBe('1310.00')
        ->and($result['deductions_before_art188'])->toBe('300.00')->and($result['excluded'])->toBe('10000.00')
        ->and($result['net_base'])->toBe('1010.00')->and($result['posting_performed'])->toBeFalse();
    $binding = $result['realization_bindings']['tvm1'];
    expect($binding['revenue'])->toBe('310.00')->and($binding['deductions'])->toBe('0.00')
        ->and($binding['historical_fiscal_snapshot_replay_performed'])->toBeTrue()->and($binding['production_ready'])->toBeFalse();
    $amounts = array_column($binding['components'], 'amount', 'component_kind');
    expect($amounts)->toBe(['CURRENT_PRODUCED_RETURN' => '150.00', 'DEFERRED_FAIR_VALUE_REALIZED' => '80.00',
        'NEW_FAIR_VALUE_REALIZED' => '50.00', 'DISPOSAL_NEW_PRODUCED_RETURN' => '30.00']);
    expect(array_column($binding['financial_entries'], 'id'))->toBe(['tvm1:TVM:0001', 'tvm1:TVM:0002', 'tvm1:TVM:0003', 'tvm1:TVM:0004']);
    expect(array_column($binding['components'], 'expected_codTrib'))->toBe(['311011104', '311011107', '311011107', '311011103']);
});

it('requires the independent current qualification to bind the entire ledger', function (): void {
    [$ledger, $rule, $known] = tvmCurrentFixture();
    $ledger['entries'][0]['amount'] = '1001.00';
    expect(tvmCurrentBase($ledger, $rule, $known)['reason'])->toBe('RTC_TVM_CURRENT_INDEPENDENT_CURRENT_QUALIFICATION_UNPROVEN');
});

it('rejects treating the sale proceeds as the component gross amount even with a resealed proof', function (): void {
    [$ledger, $rule, $known] = tvmCurrentFixture();
    $ledger['entries'][4]['amount'] = '4200.00';
    expect(tvmCurrentBase(sealTvmCurrentLedger($ledger), $rule, $known)['reason'])->toBe('RTC_TVM_CURRENT_DECLARED_COMPONENT_GROSS_AMOUNT_CONFLICT');
});

it('requires an independent classification of the produced curve', function (): void {
    [$ledger, $rule, $known] = tvmCurrentFixture();
    unset($ledger['entries'][4]['securities_realization']['produced_curve_evidence']);
    expect(tvmCurrentBase(sealTvmCurrentLedger($ledger), $rule, $known)['reason'])->toBe('RTC_TVM_CURRENT_PRODUCED_CURVE_CLASSIFICATION_UNPROVEN');
});

it('does not accept a deduction proof for a positive component', function (): void {
    [$ledger, $rule, $known] = tvmCurrentFixture();
    $ledger['entries'][4]['securities_realization']['deduction_evidence'] = [['component_id' => 'CURVE_CREDIT']];
    expect(tvmCurrentBase(sealTvmCurrentLedger($ledger), $rule, $known)['reason'])->toBe('RTC_TVM_CURRENT_LOSS_QUALIFICATION_UNKNOWN_OR_DUPLICATE');
});

it('rejects duplicate deduction qualification envelopes', function (): void {
    [$ledger, $rule, $known] = tvmCurrentFixture();
    $ledger['entries'][4]['securities_realization']['deduction_evidence'] = [['component_id' => 'bogus'], ['component_id' => 'bogus']];
    expect(tvmCurrentBase(sealTvmCurrentLedger($ledger), $rule, $known)['reason'])->toBe('RTC_TVM_CURRENT_LOSS_QUALIFICATION_UNKNOWN_OR_DUPLICATE');
});

it('rejects other raw revenue for the same title and account regardless of order', function (bool $before): void {
    [$ledger, $rule, $known] = tvmCurrentFixture();
    $other = $ledger['entries'][0];
    $other['id'] = 'other-security-revenue';
    $other['document_id'] = 'other-document';
    $other['security_identity'] = $ledger['entries'][4]['securities_realization']['analysis_input']['title'];
    array_splice($ledger['entries'], $before ? 0 : count($ledger['entries']), 0, [$other]);
    expect(tvmCurrentBase(sealTvmCurrentLedger($ledger), $rule, $known)['reason'])->toBe('RTC_FINANCIAL_BASE_SECURITIES_REALIZATION_OVERLAPPING_RAW_REVENUE');
})->with([true, false]);

it('rejects two realization parents for the same title and account', function (): void {
    [$ledger, $rule, $known] = tvmCurrentFixture();
    $other = $ledger['entries'][4];
    $other['id'] = 'tvm2';
    $other['document_id'] = 'second-document';
    $ledger['entries'][] = $other;
    expect(tvmCurrentBase(sealTvmCurrentLedger($ledger), $rule, $known)['reason'])->toBe('RTC_FINANCIAL_BASE_SECURITIES_REALIZATION_DUPLICATE_TITLE_ACCOUNT');
});

it('rejects a collision with a derived component id in either input order', function (bool $before): void {
    [$ledger, $rule, $known] = tvmCurrentFixture();
    $other = $ledger['entries'][0];
    $other['id'] = 'tvm1:TVM:0001';
    $other['document_id'] = 'other-document';
    array_splice($ledger['entries'], $before ? 0 : count($ledger['entries']), 0, [$other]);
    $result = tvmCurrentBase(sealTvmCurrentLedger($ledger), $rule, $known);
    expect($result['status'])->toBe('INVALID')->and($result['reason'])->toBe($before
        ? 'RTC_FINANCIAL_BASE_SECURITIES_REALIZATION_DERIVED_ID_CONFLICT' : 'RTC_FINANCIAL_BASE_ENTRY_IDENTITY_CONFLICT');
})->with([true, false]);

it('rejects caller supplied derived realization metadata', function (string $key): void {
    [$ledger, $rule, $known] = tvmCurrentFixture();
    $ledger['entries'][4][$key] = [];
    expect(tvmCurrentBase($ledger, $rule, $known)['reason'])->toBe('RTC_FINANCIAL_BASE_RESERVED_DERIVED_ENTRY_METADATA');
})->with(['realization_binding', 'realization_parent_id', 'realization_component']);

it('does not enable the new qualification under the accepted predecessor policy', function (): void {
    [$ledger, $rule, $known] = tvmCurrentFixture();
    unset($rule['securities_realization']);
    expect(tvmCurrentBase($ledger, $rule, $known)['reason'])->toBe('RTC_TVM_CURRENT_RULE_UNAVAILABLE');
});

it('returns a scoped pending decision for a malformed direct qualification ledger', function (): void {
    [$ledger, $rule, $known] = tvmCurrentFixture();
    $entry = $ledger['entries'][4];
    $ledger['entries'] = null;
    expect((new FinancialSecuritiesRealizationQualifier)->resolve($entry, $ledger, $known, $rule['securities_realization'])['reason'])
        ->toBe('RTC_TVM_CURRENT_QUALIFIED_ENTRY_SCOPE_UNPROVEN');
});
