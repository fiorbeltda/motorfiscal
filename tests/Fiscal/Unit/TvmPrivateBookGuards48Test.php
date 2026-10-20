<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmPrivateConsolidation48Fixtures.php';

use App\Modules\Fiscal\ConsumptionReform\DerePgccQualifier;
use App\Modules\Fiscal\ConsumptionReform\DereSecuritiesRealizationBindingQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

it('rejects private books with missing coverage or independently sealed conflicting claims', function (string $case, string $mutation, string $reason): void {
    $root = dirname(__DIR__, 3);
    $index = json_decode(file_get_contents($root.'/storage/app/phase4b-private-consolidation48-draft-20261009-28/private-cases.json'), true, 512, JSON_THROW_ON_ERROR);
    $metadata = array_values(array_filter($index['cases'], static fn (array $row): bool => $row['case'] === $case))[0];
    [$operation, $month, $known, $rules] = privateConsolidation48Fixture($metadata);
    $closing = $rules['closing'];
    $rule = $closing['securities_realization_binding'];
    $pgcc = (new DerePgccQualifier)->resolve($month['pgcc'], $closing['pgcc_catalogs']['reference'], $closing['pgcc_catalogs']['tax_codes'], $known, false, $closing['pgcc_qualification_policy']);
    if ($mutation === 'missing') {
        $month['securities_realization_book'] = [];
    } elseif ($mutation === 'duplicate') {
        $month['securities_realization_book'][1] = $month['securities_realization_book'][0];
    } elseif ($mutation === 'supplier') {
        $month['book']['supplier_id'] = '22222222000191';
    } elseif ($mutation === 'rounding-contract') {
        $rule['daily_journal_rounding_mode'] = 'UNPROVEN';
    } else {
        $row = &$month['securities_realization_book'][0];
        if ($mutation === 'cash-asset-collision') {
            $row['cash_account'] = $row['asset_account'];
            $row['independent_evidence']['value']['cash_account'] = $row['cash_account'];
        } elseif ($mutation === 'missing-subledger') {
            unset($row['fair_value_bridge'], $row['independent_evidence']['value']['fair_value_bridge']);
        } elseif ($mutation === 'independent-proof') {
            $row['independent_evidence']['value']['qualification_hash'] = str_repeat('0', 64);
        }
        $row['independent_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($row['independent_evidence'], ['source_hash' => true]));
        unset($row);
    }
    $result = (new DereSecuritiesRealizationBindingQualifier)->resolve($operation, $month, $pgcc, $known, $rule);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('RTC_DERE_TVM_BOOK_'.$reason)->and($result['production_ready'])->toBeFalse();
})->with([
    ['first-gain', 'missing', 'PARENT_BOOK_COVERAGE_INCOMPLETE'],
    ['two-titles', 'duplicate', 'PARENT_UNKNOWN_OR_DUPLICATE'],
    ['first-gain', 'supplier', 'QUALIFIED_CONTEXT_UNPROVEN'],
    ['first-gain', 'cash-asset-collision', 'ASSET_CASH_OR_COMPONENT_MAPPING_INVALID'],
    ['first-gain', 'independent-proof', 'INDEPENDENT_BOOK_BRIDGE_UNPROVEN'],
    ['negative-history', 'missing-subledger', 'FAIR_VALUE_SUBLEDGER_AND_METHOD_UNPROVEN'],
    ['positive-fractions', 'rounding-contract', 'DAILY_JOURNAL_ROUNDING_POLICY_UNPROVEN'],
]);
