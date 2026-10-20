<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialPublicOfferDebtQualifier;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;

it('rejects authentic private historical sources outside their proven title period and opening', function (string $mutation, string $reason): void {
    $root = dirname(__DIR__, 3);
    $index = json_decode(file_get_contents($root.'/storage/app/phase4b-private-consolidation48-draft-20261009-28/private-cases.json'), true, 512, JSON_THROW_ON_ERROR);
    $cases = array_column($index['cases'], null, 'case');
    $loadInput = static function (string $case) use ($root, $cases): array {
        $scenario = json_decode(file_get_contents($root.'/'.$cases[$case]['financial_input']), true, 512, JSON_THROW_ON_ERROR);
        $parent = array_values(array_filter($scenario['facts']['rtc.financial_ledger'][0]['entries'], static fn (array $row): bool => $row['kind'] === 'QUALIFIED_SECURITIES_REALIZATION'))[0];

        return $parent['securities_realization']['analysis_input'];
    };
    $input = $loadInput('negative-history');
    $catalog = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-2026.10.09-28-draft48.json'), true, 512, JSON_THROW_ON_ERROR);
    $rule = $catalog['financial_operation']['base']['securities_realization']['analysis']['history_policy'];
    $scope = ['supplier_id' => $input['position']['supplier_id'], 'period' => $input['position']['period'], 'title' => $input['title'], 'opening' => $input['position']['opening']];
    $history = $input['recognition_history'];
    switch ($mutation) {
        case 'period': $scope['period'] = '2026-10';
            break;
        case 'isin': $scope['title']['idTitulo'] = 'BRFIXTDEB027';
            expect((new FinancialPublicOfferDebtQualifier)->validTitleIdentity($scope['title']))->toBeTrue();
            break;
        case 'issuer': $scope['title']['CNPJDevedor'] = '22222222000191';
            break;
        case 'empty-sources': $history['fiscal_sources'] = [];
            break;
        case 'mixed-private-sources': $history['fiscal_sources'][] = $loadInput('mixed-history-fair-value')['recognition_history']['fiscal_sources'][0];
            break;
        case 'duplicate-private-source': $history['fiscal_sources'][] = $history['fiscal_sources'][0];
            break;
        case 'opening-unit-cost': $scope['opening']['unit_cost'] = '101.00000000';
            break;
        case 'opening-book': $scope['opening']['book_balance'] = '0.00';
            break;
    }
    $result = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($scope, $history, new DateTimeImmutable($rule['published_at']), $rule);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('RTC_TVM_HISTORY_'.$reason)->and($result['production_ready'])->toBeFalse();
})->with([
    ['period', 'CLOSING_MEMORY_SCOPE_CONFLICT'],
    ['isin', 'CLOSING_MEMORY_SCOPE_CONFLICT'],
    ['issuer', 'PRIVATE_ENTRY_TITLE_UNPROVEN'],
    ['empty-sources', 'SOURCE_COVERAGE_INVALID'],
    ['mixed-private-sources', 'SOURCE_POLICY_PROFILE_UNPROVEN'],
    ['duplicate-private-source', 'DUPLICATE_FISCAL_SOURCE'],
    ['opening-unit-cost', 'CURRENT_OPENING_POSITION_CONFLICT'],
    ['opening-book', 'CURRENT_OPENING_POSITION_CONFLICT'],
]);
