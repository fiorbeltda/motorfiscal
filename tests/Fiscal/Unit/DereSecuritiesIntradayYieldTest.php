<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmPublicFairFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

function intradayYieldFixture(string $case): array
{
    return json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-intraday-yield-2026.10.08-28-draft16/'.$case.'.json'), true, 512, JSON_THROW_ON_ERROR);
}

it('recognizes independently evidenced produced yield before a same-day sale and projects it once in D2101', function (string $case): void {
    $fixture = intradayYieldFixture($case);
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmPublicFairFixture($case, $fixture, '2026.10.08-28-draft16');
    expect($operation['base']['net_base'])->toBe($fixture['expected']['base']);
    $binding = $operation['base']['realization_bindings']['tvm1'];
    expect($binding['analysis']['component_totals']['current_produced_return_already_in_curve'])->toBe('50.00000000')
        ->and($binding['public_yield_binding']['xml_title']['vVarMensal'])->toBe('50.00')
        ->and($binding['public_yield_binding']['sale_recognition_binding']['additional_sale_taxable_amount'])->toBe('0.00');
    $result = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($result['status'])->toBe('PROJECTED', $result['reason'])->and($result['debt_yield_projection']['total_vApur'])->toBe($fixture['expected']['auxiliary_base'])
        ->and($result['production_ready'])->toBeFalse();
    if (getenv('TVM_INTRADAY_CAPTURE') === '1') {
        $root = dirname(__DIR__, 3);
        $scenario = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
        $scenario['scenario_id'] = 'SYNTHETIC-intraday-public-yield-'.$case;
        $scenario['knowledge_at'] = $known->format(DATE_ATOM);
        $scenario['rule_bundle_id'] = $manifest['bundle_id'];
        $scenario['catalog_versions'] = $manifest['catalog_versions'];
        $scenario['facts']['rtc.financial_ledger'] = [$ledger];
        foreach (['financial' => $scenario, 'month' => $month] as $suffix => $data) {
            $path = $root.'/resources/fiscal/scenarios/tvm-intraday-yield-2026.10.08-28-draft16/'.$case.'-'.$suffix.'.json';
            if (is_file($path)) {
                expect(CanonicalJson::encode(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($data));
            } else {
                $stream = fopen($path, 'x');
                expect($stream)->not->toBeFalse();
                fwrite($stream, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
                fclose($stream);
            }
        }
    }
})->with(['partial', 'complete', 'buy-sell-same-day', 'exclusive-fund', 'with-fair-value']);

it('refuses journals that recognize the proved intraday yield after the linked disposal', function (): void {
    $fixture = intradayYieldFixture('complete');
    [$operation, $month, $known, $rules] = tvmPublicFairFixture('complete', $fixture, '2026.10.08-28-draft16');
    $journals = &$month['securities_realization_book'][0]['journals'];
    $index = array_search('CURVE:2026-09-15', array_column($journals, 'event_id'), true);
    $curve = $journals[$index];
    array_splice($journals, $index, 1);
    $journals[] = $curve;
    unset($journals);
    $result = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('RTC_DERE_TVM_BOOK_BOOK_JOURNAL_COMPONENT_OR_BALANCE_CONFLICT');
});

it('refuses intraday income with an unknown target or missing independent pre-sale recognition', function (string $mutation, string $reason): void {
    $fixture = intradayYieldFixture('complete');
    $position = $fixture['position'];
    $catalog = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.08-28-draft16.json'), true, 512, JSON_THROW_ON_ERROR);
    $policy = $catalog['dere_debt_daily_memory_calculation'];
    foreach ($position['contract_curve'] as &$day) {
        if (! isset($day['appropriation_before_operation_id'])) {
            continue;
        }
        switch ($mutation) {
            case 'target': $day['appropriation_before_operation_id'] = 'unknown';
                break;
            case 'buy': $day['appropriation_before_operation_id'] = 'buy1';
                break;
            case 'proof': $day['appropriation_sequence_evidence']['qualified'] = false;
                break;
            case 'balance': $day['appropriation_sequence_evidence']['value']['book_balance_before'] = '9999.00000000';
                $day['appropriation_sequence_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($day['appropriation_sequence_evidence'], ['source_hash' => true]));
                break;
            case 'classification': $day['appropriation_sequence_evidence']['value']['produced_yield_not_fair_value'] = false;
                $day['appropriation_sequence_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($day['appropriation_sequence_evidence'], ['source_hash' => true]));
                break;
            case 'policy': unset($policy['appropriation_sequence_policy']);
                break;
        }
    }
    unset($day);
    $position['memory_evidence']['value'] = array_diff_key($position, ['memory_evidence' => true]);
    $position['memory_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($position['memory_evidence'], ['source_hash' => true]));
    $result = (new FinancialDebtDailyMemoryCalculator)->calculate($position, $policy['series'], new DateTimeImmutable($catalog['financial_operation']['base']['public_offer_debt']['published_at']), $policy);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('RTC_DERE_D2101_DAILY_'.$reason)->and($result['production_ready'])->toBeFalse();
})->with([
    ['target', 'APPROPRIATION_SEQUENCE_TARGET_UNPROVEN'], ['buy', 'APPROPRIATION_SEQUENCE_TARGET_UNPROVEN'],
    ['proof', 'AMOUNT_DATE_OR_STOCK_INVALID'], ['balance', 'AMOUNT_DATE_OR_STOCK_INVALID'], ['classification', 'AMOUNT_DATE_OR_STOCK_INVALID'],
    ['policy', 'APPROPRIATION_SEQUENCE_POLICY_UNAVAILABLE'],
]);
