<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CreditDecisionEngine;
use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Facts\FactDefinition;
use App\Modules\Fiscal\Core\Facts\FactDefinitionRegistry;
use App\Modules\Fiscal\Core\Facts\FactSourceType;
use App\Modules\Fiscal\Core\Facts\FactValue;
use App\Modules\Fiscal\Core\Facts\FactValueType;
use App\Modules\Fiscal\Core\Shared\LocalDate;

// B09: credit invariants (4B.8.11) over reversal/disallowance (4B.5.4/4B.5.5) and the
// document link (4B.5.9). Independent oracle: bcmath, not the engine's DecimalValue.

/** @param array<string, mixed> $values */
function b09Facts(array $values): FactBag
{
    $types = ['rtc.credit_context' => FactValueType::STRING, 'rtc.acquirer_regime' => FactValueType::STRING,
        'rtc.input_document_id' => FactValueType::STRING, 'rtc.input_document_valid' => FactValueType::BOOLEAN,
        'rtc.prior_tax_extinguished' => FactValueType::BOOLEAN, 'rtc.input_ibs_amount' => FactValueType::DECIMAL,
        'rtc.input_cbs_amount' => FactValueType::DECIMAL, 'rtc.credit_adjustments' => FactValueType::ARRAY];
    $bag = new FactBag(new FactDefinitionRegistry(array_map(fn (string $k): FactDefinition => new FactDefinition($k, $types[$k]), array_keys($types))));
    foreach ($values as $key => $value) {
        $bag->put(new FactValue(key: $key, value: $value, sourceType: FactSourceType::USER,
            effectiveFrom: LocalDate::fromString('2026-01-01'), effectiveTo: null,
            recordedAt: new DateTimeImmutable('2026-01-01T00:00:00-03:00')));
    }

    return $bag;
}

/** @param list<array<string, string>> $adjustments */
function b09Input(string $ibs, string $cbs, array $adjustments, string $document = 'NFSE-IN-1'): array
{
    return ['rtc.credit_context' => 'INPUT', 'rtc.acquirer_regime' => 'REGULAR', 'rtc.input_document_id' => $document,
        'rtc.input_document_valid' => true, 'rtc.prior_tax_extinguished' => true, 'rtc.input_ibs_amount' => $ibs,
        'rtc.input_cbs_amount' => $cbs, 'rtc.credit_adjustments' => $adjustments];
}

/** Half-up rounding to 2 decimals with bcmath (independent oracle). */
function b09Round(string $value): string
{
    $shift = bccomp($value, '0', 10) < 0 ? '-0.005' : '0.005';

    return bcadd(bcadd($value, $shift, 10), '0', 2);
}

/** @return list<array{0: string, 1: string, 2: list<array<string, string>>}> */
function b09Space(): array
{
    $amounts = ['0.00', '0.004', '0.005', '10.00', '10.005', '999999.99'];
    $adjust = ['0.00', '0.004', '5.00', '10.00', '10.01'];
    $space = [];
    foreach ($amounts as $ibs) {
        foreach (['0.00', '7.50'] as $cbs) {
            foreach ($adjust as $a) {
                foreach (['REVERSAL', 'DISALLOWANCE'] as $kind) {
                    foreach ([[], [['tax' => 'IBS', 'kind' => $kind, 'amount' => $a, 'evidence_id' => 'EV-1']],
                        [['tax' => 'IBS', 'kind' => $kind, 'amount' => $a, 'evidence_id' => 'EV-1'], ['tax' => 'CBS', 'kind' => $kind, 'amount' => '1.00', 'evidence_id' => 'EV-2']],
                        [['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => $a, 'evidence_id' => 'EV-1'], ['tax' => 'IBS', 'kind' => 'DISALLOWANCE', 'amount' => $a, 'evidence_id' => 'EV-3']]] as $adjs) {
                        $space[] = [$ibs, $cbs, $adjs];
                    }
                }
            }
        }
    }

    return $space;
}

it('allows exactly input minus same-tax adjustments, rounded half-up, never negative, and refuses excess', function (): void {
    $engine = new CreditDecisionEngine;
    $checked = 0;
    foreach (b09Space() as [$ibs, $cbs, $adjs]) {
        $result = $engine->resolve(b09Facts(b09Input($ibs, $cbs, $adjs)), 'DOMESTIC');
        $remaining = ['IBS' => $ibs, 'CBS' => $cbs];
        foreach ($adjs as $adj) {
            $remaining[$adj['tax']] = bcsub($remaining[$adj['tax']], $adj['amount'], 10);
        }
        $label = json_encode([$ibs, $cbs, $adjs]);
        if (bccomp($remaining['IBS'], '0', 10) < 0 || bccomp($remaining['CBS'], '0', 10) < 0) {
            expect($result['status'])->toBe('ACTION_REQUIRED', $label)
                ->and($result['reason'])->toBe('RTC_CREDIT_ADJUSTMENT_EXCEEDS_AVAILABLE', $label);
        } else {
            expect($result['status'])->toBe('RESOLVED', $label)
                ->and($result['ibs']['status'])->toBe('ALLOWED', $label)
                ->and($result['ibs']['amount'])->toBe(b09Round($remaining['IBS']), $label)
                ->and($result['cbs']['amount'])->toBe(b09Round($remaining['CBS']), $label)
                ->and(bccomp($result['ibs']['amount'], '0', 2))->toBeGreaterThanOrEqual(0, $label)
                ->and($result['ibs']['document_id'])->toBe('NFSE-IN-1', $label)
                ->and($result['cbs']['document_id'])->toBe('NFSE-IN-1', $label);
        }
        $checked++;
    }
    expect($checked)->toBe(480);
});

it('is monotone: a larger adjustment never yields a larger allowed credit', function (string $kind): void {
    $engine = new CreditDecisionEngine;
    $previous = null;
    foreach (['0.00', '0.01', '2.50', '5.00', '9.99', '10.00'] as $amount) {
        $result = $engine->resolve(b09Facts(b09Input('10.00', '5.00', [['tax' => 'IBS', 'kind' => $kind, 'amount' => $amount, 'evidence_id' => 'EV']])), 'DOMESTIC');
        $value = $result['ibs']['amount'];
        if ($previous !== null) {
            expect(bccomp($value, $previous, 2))->toBeLessThanOrEqual(0);
        }
        $previous = $value;
    }
    expect($previous)->toBe('0.00');
})->with(['REVERSAL', 'DISALLOWANCE']);

it('keeps IBS and CBS independent: a CBS adjustment never changes the IBS credit', function (): void {
    $engine = new CreditDecisionEngine;
    $base = $engine->resolve(b09Facts(b09Input('10.00', '5.00', [])), 'DOMESTIC');
    foreach (['0.01', '2.00', '5.00'] as $amount) {
        $with = $engine->resolve(b09Facts(b09Input('10.00', '5.00', [['tax' => 'CBS', 'kind' => 'DISALLOWANCE', 'amount' => $amount, 'evidence_id' => 'EV']])), 'DOMESTIC');
        expect($with['ibs'])->toBe($base['ibs'])->and($with['cbs']['amount'])->toBe(bcsub('5.00', $amount, 2));
    }
});

it('treats reversal and disallowance as the same arithmetic but never accepts other kinds, taxes or negatives', function (array $adjustment, string $reason): void {
    $result = (new CreditDecisionEngine)->resolve(b09Facts(b09Input('10.00', '5.00', [$adjustment])), 'DOMESTIC');
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe($reason)->and($result)->not->toHaveKey('ibs');
})->with([
    'unknown kind' => [['tax' => 'IBS', 'kind' => 'REFUND', 'amount' => '1.00', 'evidence_id' => 'EV'], 'RTC_CREDIT_ADJUSTMENT_UNPROVEN'],
    'missing evidence key' => [['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '1.00'], 'RTC_CREDIT_ADJUSTMENT_UNPROVEN'],
    'unknown tax' => [['tax' => 'ISS', 'kind' => 'REVERSAL', 'amount' => '1.00', 'evidence_id' => 'EV'], 'RTC_CREDIT_TAX_UNKNOWN'],
    'negative amount' => [['tax' => 'IBS', 'kind' => 'DISALLOWANCE', 'amount' => '-1.00', 'evidence_id' => 'EV'], 'RTC_NEGATIVE_CREDIT_ADJUSTMENT'],
]);

it('carries the input document into every allowed component and is deterministic', function (): void {
    $engine = new CreditDecisionEngine;
    foreach (['NFSE-IN-1', 'DOC/2026/000123', 'chave-50-posicoes-'.str_repeat('9', 32)] as $document) {
        $first = $engine->resolve(b09Facts(b09Input('10.00', '5.00', [], $document)), 'DOMESTIC');
        $second = $engine->resolve(b09Facts(b09Input('10.00', '5.00', [], $document)), 'DOMESTIC');
        expect($first)->toBe($second)->and($first['ibs']['document_id'])->toBe($document)->and($first['cbs']['document_id'])->toBe($document);
    }
});
