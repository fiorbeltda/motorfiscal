<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DereFinancialCurrentBaseCompositionQualifier;
use App\Modules\Fiscal\ConsumptionReform\DerePgccQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Tests\TestCase;

uses(TestCase::class);

/** Replayed50 inputs with explicit synthetic ISS classification added to the successor input. @return array<string, mixed> */
function currentComposition51(bool $explicitIss = true, string $iss = '0'): array
{
    $root = storage_path('app/phase4b-negative-base50-closing-draft-20261009');
    $snapshot = json_decode(file_get_contents($root.'/command-financial-snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
    $month = json_decode(file_get_contents($root.'/command-month-input.json'), true, 512, JSON_THROW_ON_ERROR);
    if ($explicitIss) {
        foreach ($month['pgcc']['accounts'] as &$account) {
            if (in_array($account['codTrib'] ?? null, ['311011101', '311011103'], true)) {
                $account['indTribISS'] = $iss;
            }
        }
        unset($account);
        $pgcc = $month['pgcc'];
        unset($pgcc['source_hash']);
        $month['pgcc']['source_hash'] = CanonicalJson::hash($pgcc);
        $month['book']['pgcc_source_hash'] = $month['prerequisites']['pgcc_source_hash'] = $month['pgcc']['source_hash'];
    }
    $stages = array_column($snapshot['result']['trace'], null, 'stage');
    $catalog = $snapshot['knowledge']['documents']['rtc_code_catalog'];
    if ($explicitIss) {
        $closing = $catalog['dere_monthly_closing_projection'];
        $pgcc = (new DerePgccQualifier)->resolve($month['pgcc'],
            $closing['pgcc_catalogs']['reference'], $closing['pgcc_catalogs']['tax_codes'],
            new DateTimeImmutable($snapshot['input']['knowledge_at']), false, $closing['pgcc_qualification_policy']);
        foreach ($month['securities_realization_book'] as &$bridge) {
            // A new synthetic witness for the successor classification; historical evidence stays unchanged.
            $proof = $bridge['independent_evidence'];
            $proof['evidence_id'] .= '.BCN51.EXPLICIT.ISS';
            $proof['value']['pgcc_source_hash'] = $pgcc['source_hash'];
            $proof['value']['book_content_hash'] = CanonicalJson::hash($month['book']);
            $proof['value']['pgcc_content_hash'] = CanonicalJson::hash($pgcc);
            unset($proof['source_hash']);
            $proof['source_hash'] = CanonicalJson::hash($proof);
            $bridge['independent_evidence'] = $proof;
        }
        unset($bridge);
    }

    return ['operation' => $stages['FINANCIAL_OPERATION_AND_BASE'], 'month' => $month,
        'gross' => $stages['FINANCIAL_GROSS_DEBITS'], 'known' => new DateTimeImmutable($snapshot['input']['knowledge_at']),
        'source' => resource_path('fiscal/sources/official/dere-observed-20261005-annex-i.pdf'),
        'rules' => ['closing' => $catalog['dere_monthly_closing_projection'],
            'balance' => $catalog['dere_trial_balance_projection'], 'deductions' => $catalog['dere_deduction_projection'],
            'pgcc_projection' => $catalog['dere_pgcc_projection'], 'contributor_projection' => $catalog['dere_contributor_projection']]];
}

/** @param array<string, mixed> $input @return array<string, mixed> */
function qualifyCurrentComposition51(array $input): array
{
    return (new DereFinancialCurrentBaseCompositionQualifier)->qualify($input['operation'], $input['month'],
        $input['gross'], $input['known'], $input['rules'], $input['source']);
}

it('rebuilds monthly proofs and derives declared composition from exact PGCC and book bindings', function (): void {
    $input = currentComposition51();
    $result = qualifyCurrentComposition51($input);
    expect($result['status'])->toBe('LOCAL_MONTHLY_CURRENT_BASE_COMPOSITION_RECONCILED')
        ->and($result['calculation_input']['general_revenue'])->toBe('1200.00')
        ->and($result['calculation_input']['other_deductible_expenses'])->toBe('100.00')
        ->and($result['calculation_input']['allocated_funding_expenses'])->toBe('200.00')
        ->and($result['calculation']['pools']['1010I']['positive_base'])->toBe('891.09')
        ->and($result['monthly_input_hash'])->toBe(CanonicalJson::hash($input['month']))
        ->and($result['classification_external_verified'])->toBeFalse()->and($result['production_ready'])->toBeFalse();
});

it('requires explicit ISS classification instead of interpreting an omitted field as no ISS', function (): void {
    $result = qualifyCurrentComposition51(currentComposition51(false));
    expect($result['reason'])->toBe('RTC_DERE_CURRENT_COMPOSITION_EXPLICIT_REVENUE_ISS_CLASSIFICATION_REQUIRED');
});

it('selects the distinct forming roots when the successor PGCC explicitly declares ISS', function (): void {
    $result = qualifyCurrentComposition51(currentComposition51(true, '1'));
    expect($result['status'])->toBe('LOCAL_MONTHLY_CURRENT_BASE_COMPOSITION_RECONCILED')
        ->and($result['calculation_input']['codBC'])->toBe('1005')
        ->and(array_keys($result['calculation']['pools']))->toBe(['1005I', '1005C']);
});

it('rechecks the native monthly book instead of trusting a supplied projection status', function (): void {
    $input = currentComposition51();
    $input['month']['book']['accounts'][0]['vApur'] = '999.00';
    $result = qualifyCurrentComposition51($input);
    expect($result['status'])->not->toBe('LOCAL_MONTHLY_CURRENT_BASE_COMPOSITION_RECONCILED');
});

it('refuses rates copied from another period or supplier', function (): void {
    $input = currentComposition51();
    $input['gross']['period_end'] = '2026-08-31';
    expect(qualifyCurrentComposition51($input)['reason'])->toBe('RTC_DERE_CURRENT_COMPOSITION_SYNTHETIC_OPERATION_AND_RATE_SCOPE_REQUIRED');
});

it('refuses arithmetic over a replacement semantic table source', function (): void {
    $input = currentComposition51();
    $input['source'] = resource_path('fiscal/sources/official/dere-observed-20261005-mod.pdf');
    expect(qualifyCurrentComposition51($input)['reason'])->toBe('RTC_DERE_CURRENT_COMPOSITION_SEMANTIC_TABLE_SOURCE_UNPROVEN');
});

it('does not allow the local synthetic path to qualify real taxpayer evidence', function (): void {
    $input = currentComposition51();
    $input['month']['evidence_scope'] = 'REAL_TAXPAYER';
    expect(qualifyCurrentComposition51($input)['reason'])->toBe('RTC_DERE_CURRENT_COMPOSITION_SYNTHETIC_OPERATION_AND_RATE_SCOPE_REQUIRED');
});
