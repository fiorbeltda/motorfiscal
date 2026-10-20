<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmGrossRoundingFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\ConsumptionReform\FinancialPublicGrossRoundingQualifier;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRealizationAnalyzer;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

function grossRoundingSave(string $name, array $data): void
{
    if (getenv('TVM_GROSS_ROUNDING_CAPTURE') !== '1') {
        return;
    }
    $path = dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-public-gross-rounding-2026.10.08-28-draft30/'.$name.'.json';
    if (is_file($path)) {
        expect(CanonicalJson::encode(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($data));

        return;
    }
    $stream = fopen($path, 'x');
    expect($stream)->not->toBeFalse();
    fwrite($stream, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    fclose($stream);
}

it('reconciles supplied gross rounding journals and partial carrying release rounding without new fiscal income', function (string $case): void {
    [$operation, $month, $known, $rules, $ledger, $manifest, $fixture] = tvmGrossRoundingFixture($case);
    expect($operation['base']['net_base'])->toBe($fixture['expected']['base']);
    $projected = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($projected['status'])->toBe('PROJECTED', $projected['reason'])
        ->and($projected['debt_yield_projection']['total_vApur'])->toBe($fixture['expected']['auxiliary_base']);
    expect($projected['production_ready'])->toBeFalse();
    $parent = $operation['base']['realization_bindings']['tvm1'];
    expect($parent['financial_entries'])->toBe([])->and($parent['deductions'])->toBe('0.00');
    $binding = $parent['public_yield_binding'];
    foreach ($fixture['independent_oracle'] as $field => $expected) {
        if ($field !== 'daily_memory') {
            expect($binding['calculation'][$field])->toBe($expected);

            continue;
        }
        expect(count($binding['calculation']['daily_memory']))->toBe(count($expected));
        foreach ($expected as $i => $day) {
            foreach ($day as $key => $value) {
                expect($binding['calculation']['daily_memory'][$i][$key])->toBe($value);
            }
        }
    }
    if (isset($fixture['sale_rounding_oracle'])) {
        $release = $parent['analysis']['realizations'][0]['book_release_rounding'];
        foreach ($fixture['sale_rounding_oracle'] as $field => $value) {
            expect($release[$field])->toBe($value);
        }
        expect($release['posting_performed'])->toBeFalse();
    }
    $oracle = $fixture['gross_rounding_oracle'];
    if ($oracle['signed_adjustment'] !== '0.00') {
        expect($binding['gross_rounding_reconciliation']['signed_adjustment'])->toBe($oracle['signed_adjustment'])
            ->and($binding['gross_rounding_reconciliation']['posting_performed'])->toBeFalse();
    } else {
        expect($binding)->not->toHaveKey('gross_rounding_reconciliation');
    }
    $scenario = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
    $scenario['scenario_id'] = 'SYNTHETIC-public-gross-rounding-'.$case;
    $scenario['knowledge_at'] = $known->format(DATE_ATOM);
    $scenario['rule_bundle_id'] = $manifest['bundle_id'];
    $scenario['catalog_versions'] = $manifest['catalog_versions'];
    $scenario['facts']['rtc.financial_ledger'] = [$ledger];
    grossRoundingSave($case.'-financial', $scenario);
    grossRoundingSave($case.'-month', $month);
})->with(['negative-partial', 'negative-complete', 'positive-partial', 'positive-complete', 'zero-partial', 'zero-complete', 'exact-partial', 'exact-complete']);

it('rejects supplied public gross rounding with missing policy, tampered journal or unproved independent origin', function (string $mutation, string $reason): void {
    [$operation, , $known, , $ledger] = tvmGrossRoundingFixture('negative-complete');
    $entry = $ledger['entries'][5];
    $bridge = $entry['public_offer_debt']['gross_rounding_reconciliation'];
    $catalog = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.08-28-draft30.json'), true, 512, JSON_THROW_ON_ERROR);
    $rule = $catalog['financial_operation']['base']['public_offer_debt'];
    switch ($mutation) {
        case 'policy': unset($rule['gross_rounding_reconciliation_policy']);
            break;
        case 'missing': $bridge = null;
            break;
        case 'normative': $bridge['normative_homologation_claimed'] = true;
            break;
        case 'amount': $bridge['journal']['lines'][0]['amount'] = '0.02';
            break;
        case 'date': $bridge['journal']['date'] = '2026-09-12';
            break;
        case 'duplicate': $bridge['journal']['lines'][] = $bridge['journal']['lines'][0];
            break;
        case 'origin': $bridge['journal']['lines'][0]['financial_entry_id'] = 'invented';
            break;
        case 'proof': unset($bridge['independent_evidence']);
            break;
        case 'hash': $bridge['independent_evidence']['source_hash'] = str_repeat('0', 64);
            break;
        case 'fiscal':
            $bridge['independent_evidence']['value']['tax_origin'] = 'D1101';
            $bridge['independent_evidence']['source_hash'] = CanonicalJson::hash(array_diff_key($bridge['independent_evidence'], ['source_hash' => true]));
            break;
    }
    $memory = (new FinancialDebtDailyMemoryCalculator)->calculate($entry['public_offer_debt']['position'], $rule['daily_memory_calculation']['series'], $known, $rule['daily_memory_calculation']);
    $result = (new FinancialPublicGrossRoundingQualifier)->resolve($bridge, $memory, $entry['public_offer_debt']['title'], $ledger['supplier_id'], 'curve000', 'yield1', 'tvm1', $known, $rule);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('RTC_PUBLIC_GROSS_ROUNDING_'.$reason);
})->with([
    ['policy', 'POLICY_OR_MEMORY_UNPROVEN'], ['missing', 'POLICY_OR_MEMORY_UNPROVEN'], ['normative', 'POLICY_OR_MEMORY_UNPROVEN'],
    ['amount', 'JOURNAL_CONFLICT'], ['date', 'JOURNAL_CONFLICT'], ['duplicate', 'JOURNAL_CONFLICT'], ['origin', 'JOURNAL_CONFLICT'],
    ['proof', 'INDEPENDENT_JOURNAL_UNPROVEN'], ['hash', 'INDEPENDENT_JOURNAL_UNPROVEN'], ['fiscal', 'INDEPENDENT_JOURNAL_UNPROVEN'],
]);

it('requires an independent proof for rounding generated by a partial carrying release', function (string $mutation): void {
    [, , $known, , $ledger] = tvmGrossRoundingFixture('negative-partial');
    $input = $ledger['entries'][4]['securities_realization']['analysis_input'];
    if ($mutation === 'missing') {
        unset($input['realizations'][0]['book_release_rounding_evidence']);
    } else {
        $proof = &$input['realizations'][0]['book_release_rounding_evidence'];
        $proof['value']['signed_adjustment'] = '-0.00500000';
        $proof['source_hash'] = CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]));
        unset($proof);
    }
    $catalog = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.08-28-draft30.json'), true, 512, JSON_THROW_ON_ERROR);
    $result = (new FinancialSecuritiesRealizationAnalyzer)->analyze($input, $catalog['financial_operation']['base']['securities_realization']['analysis'], $known);
    expect($result['reason'])->toBe('RTC_TVM_REALIZATION_INDEPENDENT_BOOK_RELEASE_ROUNDING_UNPROVEN');
})->with(['missing', 'forged']);
