<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

require_once __DIR__.'/../Support/TvmFairFixtures.php';

function tvmRoundingFixture(string $case): array
{
    $root = dirname(__DIR__, 3);
    $version = '2026.10.07-28-draft9';
    $catalog = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-'.$version.'.json'), true, 512, JSON_THROW_ON_ERROR);
    $fixture = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-rounding-'.$version.'/'.$case.'.json'), true, 512, JSON_THROW_ON_ERROR);
    $rule = $catalog['financial_operation']['base'];
    [$ledger, , $known] = tvmCurrentFirstFixture($case, $fixture, $rule);
    $policy = $rule['securities_realization']['analysis']['daily_memory_calculation'];
    $memory = (new FinancialDebtDailyMemoryCalculator)->calculate($fixture['position'], $policy['series'], $known, $policy);
    $bridge = $fixture['daily_journal_rounding'];
    $bridge['independent_evidence'] = tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'period' => '2026-09',
        'title' => $ledger['entries'][4]['securities_realization']['analysis_input']['title'], 'daily_memory_hash' => CanonicalJson::hash($memory),
        'method' => $bridge['method'], 'allocations' => $bridge['allocations'], 'gross_credit' => $fixture['expected']['gross_curve_credit'],
        'gross_debit' => $fixture['expected']['gross_curve_debit'], 'normative_homologation_claimed' => false], 'DAILY_ROUNDING');

    return tvmFairFixture($case, $ledger, $version, $bridge);
}

it('reconciles independently supplied cumulative daily fractions to separate monthly gross movements', function (string $case, string $base): void {
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmRoundingFixture($case);
    $result = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($operation['base']['net_base'])->toBe($base)->and($result['status'])->toBe('PROJECTED');
    $bridge = $result['securities_realization_binding']['parents']['tvm1']['daily_journal_rounding'];
    expect($bridge['normative_homologation_claimed'])->toBeFalse()->and($bridge['posting_performed'])->toBeFalse();
    if ($case === 'opposing-fractions') {
        expect($bridge['gross_credit'])->toBe('0.01')->and($bridge['gross_debit'])->toBe('0.01');
    }
    if (getenv('TVM_ROUNDING_CAPTURE') === '1') {
        $root = dirname(__DIR__, 3);
        $scenario = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
        $scenario['scenario_id'] = 'SYNTHETIC-daily-rounding-'.$case;
        $scenario['knowledge_at'] = $known->format(DATE_ATOM);
        $scenario['rule_bundle_id'] = $manifest['bundle_id'];
        $scenario['catalog_versions'] = $manifest['catalog_versions'];
        $scenario['facts']['rtc.financial_ledger'] = [$ledger];
        foreach (['financial' => $scenario, 'month' => $month] as $suffix => $data) {
            $path = $root.'/resources/fiscal/scenarios/tvm-rounding-2026.10.07-28-draft9/'.$case.'-'.$suffix.'.json';
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
})->with([['positive-fractions', '900.01'], ['negative-fractions', '899.99'], ['opposing-fractions', '900.00']]);

it('rejects unproven sub-cent allocation and alterations of its daily residuals', function (string $tamper, string $reason): void {
    [$operation, $month, $known, $rules] = tvmRoundingFixture('positive-fractions');
    $bridge = &$month['securities_realization_book'][0]['daily_journal_rounding'];
    switch ($tamper) {
        case 'missing': unset($month['securities_realization_book'][0]['daily_journal_rounding']);
            break;
        case 'policy': unset($rules['closing']['securities_realization_binding']['daily_journal_rounding_mode']);
            break;
        case 'residual': $bridge['allocations'][1]['rounding_residual'] = '0.00000000';
            break;
        case 'amount': $bridge['allocations'][0]['booked_amount'] = '0.01';
            break;
        case 'coverage': array_pop($bridge['allocations']);
            break;
        case 'proof': unset($bridge['independent_evidence']);
            break;
        case 'homologation': $bridge['normative_homologation_claimed'] = true;
            break;
    }
    unset($bridge);
    $result = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($result['reason'])->toBe('RTC_DERE_TVM_BOOK_'.$reason);
})->with([['missing', 'DAILY_JOURNAL_ROUNDING_BRIDGE_PENDING'], ['policy', 'DAILY_JOURNAL_ROUNDING_POLICY_UNPROVEN'],
    ['residual', 'DAILY_JOURNAL_ROUNDING_ALLOCATION_CONFLICT'], ['amount', 'DAILY_JOURNAL_ROUNDING_ALLOCATION_CONFLICT'],
    ['coverage', 'DAILY_JOURNAL_ROUNDING_ALLOCATION_CONFLICT'], ['proof', 'INDEPENDENT_DAILY_JOURNAL_ROUNDING_UNPROVEN'], ['homologation', 'DAILY_JOURNAL_ROUNDING_POLICY_UNPROVEN']]);
