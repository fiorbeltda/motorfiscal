<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DocumentIssIntegration134;
use App\Modules\Fiscal\ConsumptionReform\DocumentOwnTaxBridge135;
use App\Modules\Fiscal\ConsumptionReform\DocumentPriceFactProjection112;
use App\Modules\Fiscal\ConsumptionReform\SoftwareOwnRevenue135;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

require_once dirname(__DIR__, 3).'/scripts/phase4b_own135_pair_fixture.php';

function ownBridge135Fixture(): array
{
    static $case;

    return $case ??= own135PairFixture();
}

function ownBridge135Run(array $case): array
{
    return (new DocumentOwnTaxBridge135)->reconcile($case['facts'], $case['iss'], $case['own_proofs'], $case['allocation'],
        DocumentOwnTaxBridge135::policy(), new DateTimeImmutable($case['known_at']));
}

it('recomputes all own tax lines and reconciles independently declared contractual prices with the RTC legs', function (): void {
    $case = ownBridge135Fixture();
    $before = CanonicalJson::hash([$case['snapshot'], $case['own_proofs'], $case['iss']]);
    $result = ownBridge135Run($case);
    $domestic = array_column($result['documents'], null, 'role')['RTC_DOMESTIC'];
    expect($result['status'])->toBe('DOCUMENT_PRICES_ALL_OWN_TAXES_RTC_BRIDGE_BOUND')
        ->and($result['document_price_total_brl'])->toBe('1041.33')
        ->and($result['own_exclusions_total_brl'])->toBe('41.33')
        ->and($result['rtc_total_brl'])->toBe('1000.00')
        ->and($domestic['own_incident_amounts_brl'])->toBe(['ISS' => '18.60', 'ICMS' => '0.00', 'PIS' => '4.05', 'COFINS' => '18.68'])
        ->and($domestic['rtc_leg_brl'])->toBe('600.00')
        ->and($result['contractual_prices_derived_from_rtc_share'])->toBeFalse()
        ->and($result['iss_base_rewritten'])->toBeFalse()
        ->and($result['judicial_temporal_effects_approved'])->toBeFalse()
        ->and($result['production_ready'])->toBeFalse()
        ->and(CanonicalJson::hash([$case['snapshot'], $case['own_proofs'], $case['iss']]))->toBe($before)
        ->and(ownBridge135Run($case))->toBe($result);
});

it('refuses the former ISS-only bridge and even a one-cent contractual difference', function (string $price, string $gap): void {
    $result = ownBridge135Run(own135PairFixture($price));
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason'])->toBe('OWN_BRIDGE135_ALL_OWN_TAX_PRICE_DIFFERENCE_UNEXPLAINED')
        ->and($result['difference_brl'])->toBe($gap);
})->with([['617.92', '-21.90'], ['641.34', '0.01'], ['1000.00', '335.56']]);

it('refuses contradictions even when an outer facts fingerprint is recomputed', function (string $mutation): void {
    $case = ownBridge135Fixture();
    $scenario = $case['snapshot']['input'];
    match ($mutation) {
        'included RTC' => $scenario['facts']['rtc.legacy_tax_inclusion'] = 'INCLUDED',
        'extra adjustment' => $scenario['facts']['rtc.base_adjustments'] = [['kind' => 'LEGACY_TAX_EXCLUSION', 'amount' => '18.60', 'tax' => 'ISS', 'evidence_id' => 'other']],
        'FX' => $scenario['facts']['rtc.gross_currency'] = 'USD',
        'total' => $scenario['facts']['rtc.gross_amount'] = '1000.01',
        'share' => $scenario['facts']['rtc.export_share_percent'] = '50',
        'zero share' => $scenario['facts']['rtc.export_share_percent'] = '0',
        'full share' => $scenario['facts']['rtc.export_share_percent'] = '100',
        'missing allocation' => $scenario['facts'] = array_diff_key($scenario['facts'], ['rtc.export_allocation_evidence_id' => true]),
    };
    $factory = new FiscalEngineFactory;
    $case['facts'] = $factory->scenarioLoaderFromKnowledge($case['snapshot']['knowledge'])->fromArray($scenario)->input->context->facts;
    $projection = DocumentPriceFactProjection112::from(DocumentIssIntegration134::withoutProofCarrier($case['facts']));
    $case['iss']['qualification_context_facts_hash'] = CanonicalJson::hash($projection['facts']);
    expect(ownBridge135Run($case)['status'])->toBe('ACTION_REQUIRED');
})->with(['included RTC', 'extra adjustment', 'FX', 'total', 'share', 'zero share', 'full share', 'missing allocation']);

it('refuses changed allocation fields or a detached ISS result', function (string $mutation): void {
    $case = ownBridge135Fixture();
    match ($mutation) {
        'allocation leg' => $case['allocation']['domestic_gross_brl'] = '599.99',
        'allocation total' => $case['allocation']['total_gross_brl'] = '999.99',
        'allocation evidence' => $case['allocation']['allocation_evidence_id'] = 'OTHER',
        'allocation method' => $case['allocation']['method'] = 'UNQUALIFIED',
        'ISS facts' => $case['iss']['qualification_context_facts_hash'] = str_repeat('0', 64),
    };
    expect(ownBridge135Run($case)['status'])->toBe('ACTION_REQUIRED');
})->with(['allocation leg', 'allocation total', 'allocation evidence', 'allocation method', 'ISS facts']);

it('keeps provided amounts bound even when their keys can also be resolved as derived presentation facts', function (): void {
    $case = ownBridge135Fixture();
    $scenario = $case['snapshot']['input'];
    $scenario['facts']['invoice.gross_amount'] = '999.99';
    $case['facts'] = (new FiscalEngineFactory)->scenarioLoaderFromKnowledge($case['snapshot']['knowledge'])->fromArray($scenario)->input->context->facts;
    expect(ownBridge135Run($case)['reason'])->toBe('OWN_BRIDGE135_FRESH_ISS_MUST_MATCH_CURRENT_FACTS');
});

it('does not accept an own memo captured before the upstream monetary proofs', function (): void {
    $case = ownBridge135Fixture();
    $case['own_proofs'][1]['observed_at'] = SoftwareOwnRevenue135::AVAILABLE_AT;
    $case['own_proofs'][1] = own135Capture($case['own_proofs'][1]);
    $result = ownBridge135Run($case);
    expect($result['reason'])->toBe('OWN_BRIDGE135_OWN_MEMO_PREDATES_UPSTREAM_MONETARY_PROOFS');
});

it('rejects altered own treatment and original content instead of trusting cached calculated flags', function (string $mutation): void {
    $case = ownBridge135Fixture();
    if ($mutation === 'contents') {
        $case['own_proofs'][1]['origin']['content'] .= ' ';
    } else {
        $case['own_proofs'][1]['value']['revenue']['treatment'] = 'NOT_REQUESTED';
        $case['own_proofs'][1] = own135Capture($case['own_proofs'][1]);
    }
    expect(ownBridge135Run($case)['reason'])->toBe('OWN_BRIDGE135_FRESH_OWN_TAX_CALCULATION_REQUIRED');
})->with(['contents', 'treatment']);

it('pins its policy and availability before any monetary reconciliation', function (string $mutation): void {
    $case = ownBridge135Fixture();
    $policy = DocumentOwnTaxBridge135::policy();
    $known = new DateTimeImmutable($case['known_at']);
    if ($mutation === 'early') {
        $known = $known->modify('-1 microsecond');
    } else {
        $policy['own_revenue_policy']['court']['source_sha256'] = str_repeat('0', 64);
    }
    $result = (new DocumentOwnTaxBridge135)->reconcile($case['facts'], $case['iss'], $case['own_proofs'], $case['allocation'], $policy, $known);
    expect($result['reason'])->toBe('OWN_BRIDGE135_EXACT_KNOWN_POLICY_REQUIRED');
})->with(['early', 'source policy']);
