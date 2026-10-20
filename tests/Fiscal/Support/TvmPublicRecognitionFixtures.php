<?php

declare(strict_types=1);

require_once __DIR__.'/TvmCurrentFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use Brick\Math\BigDecimal;

function tvmPublicRecognitionFixture(string $case): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $read = static fn (string $path): array => json_decode(file_get_contents($root.$path), true, 512, JSON_THROW_ON_ERROR);
    $bundle = 'bundles/consumption-phase4b-2026.10.07-28-draft10.json';
    $manifest = $read($bundle);
    $catalog = $read($manifest['resource_paths']['rtc_code_catalog']);
    $known = new DateTimeImmutable($manifest['knowledge_available_at']);
    $fixture = $read('scenarios/tvm-public-recognition-2026.10.07-28-draft10/'.$case.'.json');
    [$temporary] = tvmCurrentFirstFixture($case, $fixture, $catalog['financial_operation']['base']);
    $input = $temporary['entries'][4]['securities_realization']['analysis_input'];
    $scenario = $read('scenarios/dere-variable-yield-2026.10.06-27/first-purchase-before-rate-financial.json');
    $scenario['scenario_id'] = 'SYNTHETIC-public-recognized-yield-'.$case;
    $scenario['knowledge_at'] = $known->format(DATE_ATOM);
    $scenario['rule_bundle_id'] = $manifest['bundle_id'];
    $scenario['catalog_versions'] = $manifest['catalog_versions'];
    $ledger = &$scenario['facts']['rtc.financial_ledger'][0];
    $entry = &$ledger['entries'][4];
    $entry['amount'] = $fixture['position']['declared_monthly']['vVarMensal'];
    $detail = &$entry['public_offer_debt'];
    $detail['position'] = $fixture['position'];
    $detail['title'] = $fixture['title'];
    $policy = $catalog['financial_operation']['base']['public_offer_debt'];
    $calc = (new FinancialDebtDailyMemoryCalculator)->calculate($detail['position'], $policy['daily_memory_calculation']['series'], $known, $policy['daily_memory_calculation']);
    $detail['eligibility_evidence'] = tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'period_end' => $ledger['period_end'],
        'title' => $detail['title'], 'financial_entry_id' => $entry['id'], 'document_id' => $entry['document_id'], 'amount' => $entry['amount'],
        'result_account_code' => $detail['result_account_code'], 'position_input_hash' => CanonicalJson::hash($detail['position']), 'position_mode' => 'DAILY_MEMORY'], 'PUBLIC.ELIGIBILITY');
    $detail['sale_recognition'] = ['analysis_input' => $input,
        'independent_evidence' => tvmCurrentProof(['supplier_id' => $ledger['supplier_id'], 'period_start' => $ledger['period_start'], 'period_end' => $ledger['period_end'],
            'financial_entry_id' => $entry['id'], 'document_id' => $entry['document_id'], 'analysis_input_hash' => CanonicalJson::hash($input),
            'calculation_hash' => CanonicalJson::hash($calc), 'policy_hash' => CanonicalJson::hash($policy['sale_recognition_policy']),
            'appropriation_excludes_fair_value' => true, 'previously_recognized_returns_not_reincluded' => true], 'PUBLIC.SALE')];
    unset($detail, $entry, $ledger);

    return [$scenario, $catalog, $known, $fixture, $manifest];
}

function tvmPublicRecognitionOperation(array $scenario): array
{
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(dirname(__DIR__, 3).'/resources/fiscal/', 'bundles/consumption-phase4b-2026.10.07-28-draft10.json');
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($scenario);
    $result = (new RtcScenarioRunService($factory))->evaluate($knowledge, $loaded)->toArray();
    expect($result['reason_codes'])->toBe(['RTC_FINANCIAL_DOCUMENT_PENDING']);

    return array_column($result['trace'], null, 'stage')['FINANCIAL_OPERATION_AND_BASE'];
}

function tvmPublicRecognitionMonth(array $scenario, array $operation, array $fixture): array
{
    $path = dirname(__DIR__, 3).'/resources/fiscal/scenarios/dere-variable-yield-2026.10.06-27/first-purchase-before-rate.json';
    $month = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $entry = $scenario['facts']['rtc.financial_ledger'][0]['entries'][4];
    $amounts = $fixture['position']['declared_monthly'];
    foreach ($month['book']['accounts'] as &$account) {
        if ($account['cCta'] === 'interest000') {
            $account['vMovCred'] = '1100.00';
            $account['vMovDebt'] = '0.00';
            $account['vAjusteCred'] = '100.00';
            $account['vAjusteDebt'] = '0.00';
            $account['vSaldoFinal'] = '1100.00';
        } elseif (in_array($account['cCta'], ['debt000', 'debtfund000'], true)) {
            $used = $account['cCta'] === $fixture['position']['cCta'];
            $account['vSaldoInic'] = '0.00';
            $account['vMovDebt'] = $used ? (string) BigDecimal::of($amounts['vEntradas'])->plus($amounts['vVarMensal'])->toScale(2) : '0.00';
            $account['vMovCred'] = $used ? $amounts['vSaidas'] : '0.00';
            $account['vSaldoFinal'] = $used ? $amounts['vSaldoContFin'] : '0.00';
            $account['vAjusteDebt'] = $account['vMovDebt'];
            $account['vAjusteCred'] = '0.00';
            $account['previous_balance']['amount'] = '0.00';
        }
    }
    unset($account);
    $month['debt_yield']['financial_entry_ids'] = [$entry['id']];
    $month['debt_yield']['previous_title_balances'] = [];
    $detail = &$month['debt_yield'];
    $detail['evidence'] = tvmCurrentProof(['supplier_id' => $month['book']['supplier_id'], 'period_end' => $month['book']['period_end'],
        'detail' => array_diff_key($detail, ['evidence' => true]), 'operation_hash' => CanonicalJson::hash($operation),
        'book_input_hash' => CanonicalJson::hash($month['book']), 'pgcc_source_hash' => $month['book']['pgcc_source_hash']], 'PUBLIC.MONTH');
    unset($detail);
    $month['corpus_expectation'] = ['status' => 'PROJECTED', 'base' => $fixture['expected']['base'], 'auxiliary_base' => $fixture['expected']['auxiliary_base']];

    return $month;
}
