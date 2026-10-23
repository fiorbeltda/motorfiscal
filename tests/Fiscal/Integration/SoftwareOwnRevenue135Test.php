<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SoftwareOwnRevenue135;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\ISS\IssDocumentBase133;

require_once dirname(__DIR__, 3).'/scripts/phase4b_iss133_fixture.php';
require_once dirname(__DIR__, 3).'/scripts/phase4b_own135_fixture.php';

function own135Iss(): array
{
    static $iss;
    if ($iss === null) {
        $fixture = phase4bIss133Fixture();
        $iss = (new IssDocumentBase133)->calculate($fixture['input111'], $fixture['proofs'], $fixture['knowledge_at'], $fixture['policy']);
    }

    return $iss;
}

function own135Run(array $proofs, ?array $iss = null): array
{
    return (new SoftwareOwnRevenue135)->calculate($iss ?? own135Iss(), $proofs, SoftwareOwnRevenue135::policy(),
        new DateTimeImmutable(SoftwareOwnRevenue135::AVAILABLE_AT));
}

it('calculates own software line contributions with captured revenue and inflow proofs', function (): void {
    $iss = own135Iss();
    $proofs = own135Proofs($iss);
    $before = CanonicalJson::hash([$iss, $proofs]);
    $result = own135Run($proofs);
    $docs = array_column($result['documents'], null, 'role');
    expect($result['status'])->toBe('LOCAL_SOFTWARE_OWN_REVENUE_LINES_CALCULATED')
        ->and($docs['RTC_DOMESTIC']['own_incident_amounts_brl'])->toBe(['ISS' => '29.00', 'ICMS' => '0.00', 'PIS' => '6.31', 'COFINS' => '29.13'])
        ->and($docs['RTC_DOMESTIC']['legacy_reconciliation']['price_after_declared_legacy_exclusions_brl'])->toBe('935.56')
        ->and($docs['RTC_FOREIGN']['own_contribution_base_brl'])->toBeNull()
        ->and($docs['RTC_FOREIGN']['own_incident_amounts_brl']['PIS'])->toBe('0.00')
        ->and($result['withholding_used_as_own_incidence'])->toBeFalse()
        ->and($result['monthly_liability_calculated'])->toBeFalse()
        ->and($result['bank_or_taxpayer_evidence_authenticated'])->toBeFalse()
        ->and($result['production_ready'])->toBeFalse()
        ->and(CanonicalJson::hash([$iss, $proofs]))->toBe($before)
        ->and(own135Run(array_reverse($proofs)))->toBe($result);
});

it('does not infer contribution export exemption from the RTC role or municipal non-incidence', function (): void {
    $proofs = own135Proofs(own135Iss());
    $proofs[0]['value']['revenue']['treatment'] = 'ORDINARY_CUMULATIVE';
    $proofs[0]['value']['revenue']['bank_inflow'] = null;
    $proofs[0] = own135Capture($proofs[0]);
    $result = own135Run($proofs);
    $foreign = array_column($result['documents'], null, 'role')['RTC_FOREIGN'];
    expect($foreign['fresh_iss_tax_brl'])->toBeNull()
        ->and($foreign['own_incident_amounts_brl']['PIS'])->toBe('6.50')
        ->and($foreign['own_incident_amounts_brl']['COFINS'])->toBe('30.00');
});

it('keeps revenue recognition and competence separate from the ISS supply day', function (): void {
    $proofs = own135Proofs(own135Iss());
    $proofs[1]['value']['revenue']['recognized_on'] = '2026-10-01';
    $proofs[1]['value']['revenue']['competence'] = '2026-10';
    $proofs[1] = own135Capture($proofs[1]);
    $result = own135Run($proofs);
    expect($result['status'])->toBe('LOCAL_SOFTWARE_OWN_REVENUE_LINES_CALCULATED')
        ->and(array_column($result['documents'], null, 'role')['RTC_DOMESTIC']['competence'])->toBe('2026-10');
});

it('rejects recaptured material contradictions rather than accepting their new hashes', function (string $case): void {
    $proofs = own135Proofs(own135Iss());
    switch ($case) {
        case 'imported software': $proofs[1]['value']['profile']['imported_software'] = true;
            break;
        case 'special regime': $proofs[1]['value']['profile']['simples_mei_cooperative_financial_or_special_regime'] = true;
            break;
        case 'ISS still included': $proofs[1]['value']['profile']['iss_in_revenue_base'] = true;
            break;
        case 'mixed goods': $proofs[1]['value']['profile']['mixed_goods'] = true;
            break;
        case 'canceled': $proofs[1]['value']['profile']['canceled_revenue'] = true;
            break;
        case 'other deductions': $proofs[1]['value']['profile']['other_revenue_base_adjustments'] = ['unproven'];
            break;
        case 'rtc price substituted': $proofs[1]['value']['revenue']['service_revenue_brl'] = '600.00';
            break;
        case 'withholding': $proofs[1]['value']['revenue']['recognition_basis'] = 'FEDERAL_WITHHOLDING_NOT_REQUESTED';
            break;
        case 'cash regime': $proofs[1]['value']['revenue']['recognition_basis'] = 'CASH';
            break;
        case 'competence': $proofs[1]['value']['revenue']['competence'] = '2026-10';
            break;
        case 'future recognition': $proofs[1]['value']['revenue']['recognized_on'] = '2026-11-01';
            break;
        case '2033': $proofs[1]['value']['revenue']['recognized_on'] = '2033-01-01';
            break;
        case 'date invalid': $proofs[1]['value']['revenue']['recognized_on'] = '2026-02-30';
            break;
        case 'uncovered': $proofs[1]['valid_from'] = '2026-09-26';
            break;
        case 'inflow uncovered': $proofs[0]['valid_to'] = '2026-09-25';
            break;
        case 'unknown treatment': $proofs[1]['value']['revenue']['treatment'] = 'NOT_REQUESTED';
            break;
        case 'revoked': $proofs[1]['revoked_at'] = SoftwareOwnRevenue135::AVAILABLE_AT;
            break;
        case 'superseded': $proofs[1]['superseded_at'] = SoftwareOwnRevenue135::AVAILABLE_AT;
            break;
        case 'future observed': $proofs[1]['observed_at'] = '2026-10-24T10:00:00+00:00';
            break;
        case 'duplicate id': $proofs[1]['evidence_id'] = $proofs[0]['evidence_id'];
            break;
        case 'wrong document': $proofs[1]['value']['document']['document_reference'] = 'OTHER';
            break;
        case 'old iss': $proofs[1]['value']['document']['fresh_iss_binding_hash'] = str_repeat('0', 64);
            break;
        case 'empty proof id': $proofs[1]['evidence_id'] = '';
            break;
        case 'payer BR': $proofs[0]['value']['revenue']['customer_country'] = 'BR';
            break;
        case 'missing inflow': $proofs[0]['value']['revenue']['bank_inflow'] = null;
            break;
        default:
            $record = json_decode($proofs[0]['value']['revenue']['bank_inflow']['content'], true, flags: JSON_THROW_ON_ERROR);
            match ($case) {
                'no divisas' => $record['foreign_exchange_inflow'] = false,
                'wrong payer' => $record['payer_id'] = 'OTHER',
                'wrong payee' => $record['payee_id'] = 'OTHER',
                'wrong bank document' => $record['document_reference'] = 'OTHER',
                'partial inflow' => $record['credited_brl'] = '999.99',
                'USD amount' => $record['currency'] = 'USD',
                'future settlement' => $record['settled_on'] = '2026-12-31',
                'bank extra' => $record['unqualified'] = true,
            };
            $proofs[0]['value']['revenue']['bank_inflow'] = own135BankCapture($record);
    }
    $proofs = array_map('own135Capture', $proofs);
    expect(own135Run($proofs)['status'])->toBe('ACTION_REQUIRED');
})->with(['imported software', 'special regime', 'ISS still included', 'mixed goods', 'canceled', 'other deductions',
    'rtc price substituted', 'withholding', 'cash regime', 'competence', 'future recognition', '2033', 'date invalid',
    'uncovered', 'inflow uncovered', 'unknown treatment', 'revoked', 'superseded', 'future observed', 'duplicate id', 'wrong document',
    'old iss', 'empty proof id', 'payer BR', 'missing inflow', 'no divisas', 'wrong payer', 'wrong payee', 'wrong bank document',
    'partial inflow', 'USD amount', 'future settlement', 'bank extra']);

it('rejects tampered original content, proof source, policy and earlier knowledge', function (string $case): void {
    $proofs = own135Proofs(own135Iss());
    $policy = SoftwareOwnRevenue135::policy();
    $known = new DateTimeImmutable(SoftwareOwnRevenue135::AVAILABLE_AT);
    match ($case) {
        'raw content' => $proofs[1]['origin']['content'] .= ' ',
        'source hash' => $proofs[1]['source_hash'] = str_repeat('0', 64),
        'policy rate' => $policy['pis_percent'] = '1.65',
        'policy source' => $policy['sources']['law9718.html'] = str_repeat('0', 64),
        'known before' => $known = $known->modify('-1 microsecond'),
        'missing proof' => array_pop($proofs),
    };
    expect((new SoftwareOwnRevenue135)->calculate(own135Iss(), $proofs, $policy, $known)['status'])->toBe('ACTION_REQUIRED');
})->with(['raw content', 'source hash', 'policy rate', 'policy source', 'known before', 'missing proof']);

it('calculates own line rounding from document revenue instead of the RTC share', function (string $price, string $pis, string $cofins): void {
    $fixture = $price === '0.00' ? phase4bIss133Fixture('021-zero-iss-price-gross-before-all-discounts') : phase4bIss133Fixture(amount: $price);
    $iss = (new IssDocumentBase133)->calculate($fixture['input111'], $fixture['proofs'], $fixture['knowledge_at'], $fixture['policy']);
    $proofs = own135Proofs($iss);
    $proofs[0]['value']['revenue']['treatment'] = 'ORDINARY_CUMULATIVE';
    $proofs[0]['value']['revenue']['bank_inflow'] = null;
    $proofs[0] = own135Capture($proofs[0]);
    $result = own135Run($proofs, $iss);
    $domestic = array_column($result['documents'], null, 'role')['RTC_DOMESTIC'];
    expect($domestic['own_incident_amounts_brl']['PIS'])->toBe($pis)
        ->and($domestic['own_incident_amounts_brl']['COFINS'])->toBe($cofins)
        ->and($domestic['own_contribution_base_brl'])->toBe(DecimalValue::fromString($price)->minus(DecimalValue::fromString($domestic['fresh_iss_tax_brl']))->value());
})->with([['0.00', '0.00', '0.00'], ['4.31', '0.03', '0.13'], ['617.92', '3.90', '18.00'], ['642.05', '4.05', '18.70']]);

it('excludes unconditional discounts while keeping conditional discounts in own contribution revenue', function (string $seed, string $base): void {
    $fixture = phase4bIss133Fixture($seed);
    $iss = (new IssDocumentBase133)->calculate($fixture['input111'], $fixture['proofs'], $fixture['knowledge_at'], $fixture['policy']);
    $result = own135Run(own135Proofs($iss), $iss);
    $domestic = array_column($result['documents'], null, 'role')['RTC_DOMESTIC'];
    expect($domestic['own_contribution_base_brl'])->toBe($base);
})->with([['003-unconditional-gross-before-all-discounts', '873.90'],
    ['006-conditional-gross-before-all-discounts', '971.00'], ['009-both-gross-before-all-discounts', '873.90']]);
