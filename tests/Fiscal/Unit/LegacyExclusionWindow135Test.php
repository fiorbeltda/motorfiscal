<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\LegacyExclusionWindow135;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

function legacy135Context(string $day = '2026-10-22'): array
{
    return ['document_reference' => 'DPS-DOMESTIC-1352', 'supply_on' => $day,
        'composed_price_brl' => '617.92', 'fresh_iss_binding_hash' => str_repeat('a', 64)];
}

function legacy135Assessments(array $context): array
{
    return array_map(fn (string $tax): array => ['tax' => $tax, 'basis' => 'OWN_OPERATION_INCIDENT_AMOUNT',
        'document_context_hash' => CanonicalJson::hash($context), 'amount_brl' => $tax === 'ISS' ? '17.92' : '0.00',
        'evidence_reference' => 'own-assessment-'.$tax, 'assessment_source_hash' => hash('sha256', $tax)],
        ['ISS', 'ICMS', 'PIS', 'COFINS']);
}

function legacy135Result(array $context, array $assessments): array
{
    return (new LegacyExclusionWindow135)->reconcile(LegacyExclusionWindow135::policy(), $context, $assessments,
        new DateTimeImmutable(LegacyExclusionWindow135::AVAILABLE_AT));
}

it('reconciles explicit own incident amounts without claiming they were reexecuted or legally approved', function (): void {
    $context = legacy135Context();
    $result = legacy135Result($context, legacy135Assessments($context));
    expect($result['status'])->toBe('ARTICLE12_PAR2_V_DECLARED_INCIDENT_AMOUNTS_RECONCILED')
        ->and($result['declared_legacy_exclusion_brl'])->toBe('17.92')
        ->and($result['price_after_declared_legacy_exclusions_brl'])->toBe('600.00')
        ->and($result['own_tax_assessments_reexecuted'])->toBeFalse()
        ->and($result['legacy_exclusion_legally_approved'])->toBeFalse()
        ->and($result['other_article12_exclusions_assessed'])->toBeFalse()
        ->and($result['production_ready'])->toBeFalse();
});

it('does not silently treat nonzero own PIS and Cofins as zero', function (): void {
    $context = legacy135Context();
    $rows = legacy135Assessments($context);
    $rows[2]['amount_brl'] = '4.02';
    $rows[3]['amount_brl'] = '18.54';
    $result = legacy135Result($context, $rows);
    expect($result['declared_legacy_exclusion_brl'])->toBe('40.48')
        ->and($result['price_after_declared_legacy_exclusions_brl'])->toBe('577.44');
});

it('checks both legal window boundaries independently from knowledge time', function (string $day, bool $valid): void {
    $context = legacy135Context($day);
    $result = legacy135Result($context, legacy135Assessments($context));
    expect($result['status'])->toBe($valid ? 'ARTICLE12_PAR2_V_DECLARED_INCIDENT_AMOUNTS_RECONCILED' : 'ACTION_REQUIRED');
})->with([['2025-12-31', false], ['2026-01-01', true], ['2032-12-31', true], ['2033-01-01', false], ['2026-02-30', false]]);

it('refuses missing taxes and mixed withholding provenance even when the amount is zero', function (string $case): void {
    $context = legacy135Context();
    $rows = legacy135Assessments($context);
    match ($case) {
        'missing' => array_pop($rows),
        'duplicate tax' => $rows[3]['tax'] = 'PIS',
        'withholding zero' => $rows[2]['basis'] = 'FEDERAL_WITHHOLDING_NOT_REQUESTED',
        'withholding positive' => $rows[0]['basis'] = 'WITHHELD_TAX_AMOUNT',
        'unknown own' => $rows[2]['basis'] = 'NOT_ASSESSED',
        'wrong document' => $rows[2]['document_context_hash'] = str_repeat('0', 64),
        'extra field' => $rows[2]['assumed_zero'] = true,
        'empty reference' => $rows[2]['evidence_reference'] = '',
        'reused reference' => $rows[3]['evidence_reference'] = $rows[2]['evidence_reference'],
        'missing hash' => $rows[2]['assessment_source_hash'] = '',
        'negative' => $rows[2]['amount_brl'] = '-0.01',
        'float' => $rows[2]['amount_brl'] = 0.0,
        'precision' => $rows[2]['amount_brl'] = '0.001',
        'too much' => $rows[2]['amount_brl'] = '617.92',
        'unrelated tax' => $rows[2]['tax'] = 'CSLL',
        'IPI other clause' => $rows[2]['tax'] = 'IPI',
        'COSIP other clause' => $rows[2]['tax'] = 'COSIP',
    };
    expect(legacy135Result($context, $rows)['status'])->toBe('ACTION_REQUIRED');
})->with(['missing', 'duplicate tax', 'withholding zero', 'withholding positive', 'unknown own', 'wrong document', 'extra field',
    'empty reference', 'reused reference', 'missing hash', 'negative', 'float', 'precision', 'too much', 'unrelated tax', 'IPI other clause', 'COSIP other clause']);

it('binds the primary observation and never backdates new knowledge', function (string $case): void {
    $context = legacy135Context();
    $policy = LegacyExclusionWindow135::policy();
    $known = new DateTimeImmutable(LegacyExclusionWindow135::AVAILABLE_AT);
    match ($case) {
        'earlier knowledge' => $known = $known->modify('-1 microsecond'),
        'source' => $policy['source_sha256'] = str_repeat('0', 64),
        'start' => $policy['valid_from'] = '2025-01-01',
        'end' => $policy['valid_to'] = '2033-12-31',
        'scope' => $policy['scope'] = 'PRODUCTION',
    };
    $result = (new LegacyExclusionWindow135)->reconcile($policy, $context, legacy135Assessments($context), $known);
    expect($result['status'])->toBe('ACTION_REQUIRED');
})->with(['earlier knowledge', 'source', 'start', 'end', 'scope']);
