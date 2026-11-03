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

const B02_BASE_FIXTURE = __DIR__.'/../Support/b02/CreditDecisionEngine.base-87d22e7.php.txt';
const B02_BASE_SHA256 = 'da1f00e2d212cadd180a221b6fce2ca0e63d095cbb4f4d2b0ea04bdf2d43725a';

/** Loads the frozen HEAD 87d22e7 implementation under a test-only namespace. */
function b02BaseEngine(): object
{
    if (! class_exists('Tests\\B02Base\\CreditDecisionEngine', false)) {
        $code = (string) file_get_contents(B02_BASE_FIXTURE);
        $code = str_replace(['<?php', 'namespace App\\Modules\\Fiscal\\ConsumptionReform;'],
            ['', "namespace Tests\\B02Base;\nuse App\\Modules\\Fiscal\\Core\\Facts\\FactBag;\nuse App\\Modules\\Fiscal\\Core\\Shared\\DecimalValue;"], $code);
        $code = preg_replace('/^use App\\\\Modules\\\\Fiscal\\\\Core\\\\(Facts\\\\FactBag|Shared\\\\DecimalValue);\R/m', '', $code, 2);
        eval($code);
    }
    $class = 'Tests\\B02Base\\CreditDecisionEngine';

    return new $class;
}

/** @param array<string, mixed> $values */
function b02Facts(array $values): FactBag
{
    $types = ['rtc.credit_context' => FactValueType::STRING, 'rtc.acquirer_country' => FactValueType::STRING,
        'rtc.importer_id' => FactValueType::STRING, 'rtc.acquirer_id' => FactValueType::STRING,
        'rtc.acquirer_regime' => FactValueType::STRING, 'rtc.input_document_id' => FactValueType::STRING,
        'rtc.input_document_valid' => FactValueType::BOOLEAN, 'rtc.prior_tax_extinguished' => FactValueType::BOOLEAN,
        'rtc.input_ibs_amount' => FactValueType::DECIMAL, 'rtc.input_cbs_amount' => FactValueType::DECIMAL,
        'rtc.credit_adjustments' => FactValueType::ARRAY, 'rtc.import_document_id' => FactValueType::STRING,
        'rtc.import_credit_fiscal_document_id' => FactValueType::STRING, 'rtc.import_credit_extinction_evidence_id' => FactValueType::STRING];
    $bag = new FactBag(new FactDefinitionRegistry(array_map(fn (string $k): FactDefinition => new FactDefinition($k, $types[$k]), array_keys($types))));
    foreach ($values as $key => $value) {
        if ($value !== null) {
            $bag->put(new FactValue(key: $key, value: $value, sourceType: FactSourceType::USER,
                effectiveFrom: LocalDate::fromString('2026-01-01'), effectiveTo: null,
                recordedAt: new DateTimeImmutable('2026-01-01T00:00:00-03:00')));
        }
    }

    return $bag;
}

/** @return list<array{0: array<string, mixed>, 1: string, 2: bool, 3: array<string, mixed>|null, 4: string}> */
function b02Space(): array
{
    $treatments = ['none' => null,
        'ibs-denied' => ['components' => ['IBS' => ['status' => 'DENIED'], 'CBS' => ['status' => 'ORDINARY']]],
        'ibs-conditional' => ['components' => ['IBS' => ['status' => 'CONDITIONAL'], 'CBS' => ['status' => 'ZERO_RATE']]]];
    $adjustments = ['none' => [], 'ibs-reversal' => [['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '10.00', 'evidence_id' => 'EV-1']],
        'cbs-disallowance' => [['tax' => 'CBS', 'kind' => 'DISALLOWANCE', 'amount' => '1.00', 'evidence_id' => 'EV-2']]];
    $space = [];
    foreach (['OUTPUT', 'PERSONAL_USE', 'INPUT'] as $context) {
        foreach ([['DOMESTIC', false], ['IMPORT', false], ['IMPORT', true]] as [$market, $qualify]) {
            foreach ([null, 'REGULAR', 'SIMPLES'] as $regime) {
                foreach ([true, false] as $valid) {
                    foreach ([true, false] as $extinguished) {
                        foreach (['0.00', '10.00', '0.004'] as $ibs) {
                            foreach ($adjustments as $adjName => $adj) {
                                foreach ($treatments as $tName => $treatment) {
                                    foreach (['BR', 'US'] as $country) {
                                        $facts = ['rtc.credit_context' => $context, 'rtc.acquirer_country' => $country,
                                            'rtc.importer_id' => 'ACQ', 'rtc.acquirer_id' => 'ACQ', 'rtc.acquirer_regime' => $regime,
                                            'rtc.input_document_id' => 'DOC-1', 'rtc.input_document_valid' => $valid,
                                            'rtc.prior_tax_extinguished' => $extinguished, 'rtc.input_ibs_amount' => $ibs,
                                            'rtc.input_cbs_amount' => '5.00', 'rtc.credit_adjustments' => $adj,
                                            'rtc.import_document_id' => 'DI-1', 'rtc.import_credit_fiscal_document_id' => 'DOC-1',
                                            'rtc.import_credit_extinction_evidence_id' => 'EXT-1'];
                                        $label = implode('/', [$context, $market, $qualify ? 'q' : 'nq', $regime ?? 'no-regime',
                                            $valid ? 'valid' : 'invalid', $extinguished ? 'ext' : 'not-ext', $ibs, $adjName, $tName, $country]);
                                        $space[] = [$facts, $market, $qualify, $treatment, $label];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    return $space;
}

it('pins the frozen HEAD implementation used as the non-regression oracle', function (): void {
    expect(hash_file('sha256', B02_BASE_FIXTURE))->toBe(B02_BASE_SHA256);
});

it('keeps the default output byte-identical to HEAD across the whole combination space', function (): void {
    $base = b02BaseEngine();
    $current = new CreditDecisionEngine;
    $checked = 0;
    foreach (b02Space() as [$facts, $market, $qualify, $treatment, $label]) {
        $expected = $base->resolve(b02Facts($facts), $market, $qualify, $treatment);
        $actual = $current->resolve(b02Facts($facts), $market, $qualify, $treatment);
        expect(json_encode($actual))->toBe(json_encode($expected), $label);
        $checked++;
    }
    expect($checked)->toBe(5832);
});

it('only adds annotations when explaining zero results, never changing status or amount', function (): void {
    $current = new CreditDecisionEngine;
    foreach (b02Space() as [$facts, $market, $qualify, $treatment, $label]) {
        $plain = $current->resolve(b02Facts($facts), $market, $qualify, $treatment);
        $explained = $current->resolve(b02Facts($facts), $market, $qualify, $treatment, explainZero: true);
        if ($plain['status'] !== 'RESOLVED') {
            expect($explained)->toBe($plain, $label);

            continue;
        }
        foreach (['ibs', 'cbs'] as $key) {
            $added = array_diff_key($explained[$key], $plain[$key]);
            expect(array_intersect_key($explained[$key], $plain[$key]))->toBe($plain[$key], $label)
                ->and(array_keys($added))->toBe(['zero_semantics_version', 'zero_causes', 'amount_semantics'], $label);
        }
        expect(array_diff_key($explained, ['ibs' => 1, 'cbs' => 1]))->toBe(array_diff_key($plain, ['ibs' => 1, 'cbs' => 1]), $label);
    }
});

it('never leaves a zero outcome unexplained and never collapses distinct causes', function (): void {
    $current = new CreditDecisionEngine;
    $signatures = [];
    $observed = [];
    foreach (b02Space() as [$facts, $market, $qualify, $treatment, $label]) {
        $explained = $current->resolve(b02Facts($facts), $market, $qualify, $treatment, explainZero: true);
        if ($explained['status'] !== 'RESOLVED') {
            continue;
        }
        foreach (['ibs', 'cbs'] as $key) {
            $c = $explained[$key];
            $zero = ! isset($c['amount']) || $c['amount'] === '0.00';
            if ($zero) {
                expect($c['zero_causes'])->not->toBe([], $label);
            }
            foreach ($c['zero_causes'] as $cause) {
                expect($cause)->not->toEndWith('_UNSPECIFIED', $label);
                $observed[$cause] = true;
            }
            $signature = $c['status'].'|'.implode('+', $c['zero_causes']).'|'.$c['amount_semantics'];
            $signatures[$signature] = true;
        }
    }
    ksort($observed);
    expect(array_keys($observed))->toBe(['CLAIMANT_NOT_DOMESTIC_ACQUIRER', 'CLAIMANT_NOT_REGULAR', 'FULLY_REVERSED_OR_DISALLOWED',
        'INPUT_DOCUMENT_NOT_VALID', 'INPUT_TAX_AMOUNT_ZERO', 'INPUT_TREATMENT_CONDITIONAL', 'INPUT_TREATMENT_PRECLUDES_CREDIT',
        'OUTPUT_CONTEXT_NO_INPUT_CREDIT', 'PERSONAL_USE_OR_CONSUMPTION', 'PRIOR_TAX_NOT_EXTINGUISHED', 'ROUNDED_TO_ZERO'])
        ->and(count($signatures))->toBeGreaterThanOrEqual(12);
});

it('distinguishes the two conditional causes that the historical output reports identically', function (): void {
    $current = new CreditDecisionEngine;
    $facts = ['rtc.credit_context' => 'INPUT', 'rtc.acquirer_regime' => 'REGULAR', 'rtc.input_document_id' => 'DOC-1',
        'rtc.input_ibs_amount' => '10.00', 'rtc.input_cbs_amount' => '5.00', 'rtc.credit_adjustments' => []];
    $invalid = [...$facts, 'rtc.input_document_valid' => false, 'rtc.prior_tax_extinguished' => true];
    $unpaid = [...$facts, 'rtc.input_document_valid' => true, 'rtc.prior_tax_extinguished' => false];
    $historicInvalid = $current->resolve(b02Facts($invalid), 'DOMESTIC');
    $historicUnpaid = $current->resolve(b02Facts($unpaid), 'DOMESTIC');
    $explainedInvalid = $current->resolve(b02Facts($invalid), 'DOMESTIC', explainZero: true);
    $explainedUnpaid = $current->resolve(b02Facts($unpaid), 'DOMESTIC', explainZero: true);
    expect($historicInvalid)->toBe($historicUnpaid)
        ->and($explainedInvalid['ibs']['zero_causes'])->toBe(['INPUT_DOCUMENT_NOT_VALID'])
        ->and($explainedUnpaid['ibs']['zero_causes'])->toBe(['PRIOR_TAX_NOT_EXTINGUISHED'])
        ->and($explainedInvalid['ibs']['amount_semantics'])->toBe('CONTINGENT_NOT_YET_APPROPRIABLE')
        ->and($explainedInvalid)->not->toBe($explainedUnpaid);
});

it('separates final denial, allowed zero, rounded zero and positive credit', function (string $case, array $override, string $status, array $causes, string $semantics): void {
    $facts = ['rtc.credit_context' => 'INPUT', 'rtc.acquirer_regime' => 'REGULAR', 'rtc.input_document_id' => 'DOC-1',
        'rtc.input_document_valid' => true, 'rtc.prior_tax_extinguished' => true, 'rtc.input_ibs_amount' => '10.00',
        'rtc.input_cbs_amount' => '5.00', 'rtc.credit_adjustments' => [], ...$override];
    $result = (new CreditDecisionEngine)->resolve(b02Facts($facts), 'DOMESTIC', explainZero: true);
    expect($result['ibs']['status'])->toBe($status, $case)->and($result['ibs']['zero_causes'])->toBe($causes, $case)
        ->and($result['ibs']['amount_semantics'])->toBe($semantics, $case);
})->with([
    ['positive', [], 'ALLOWED', [], 'ALLOWED_AMOUNT'],
    ['input-zero', ['rtc.input_ibs_amount' => '0.00'], 'ALLOWED', ['INPUT_TAX_AMOUNT_ZERO'], 'ALLOWED_ZERO_AMOUNT'],
    ['fully-reversed', ['rtc.credit_adjustments' => [['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '10.00', 'evidence_id' => 'EV']]], 'ALLOWED', ['FULLY_REVERSED_OR_DISALLOWED'], 'ALLOWED_ZERO_AMOUNT'],
    ['rounded', ['rtc.input_ibs_amount' => '0.004'], 'ALLOWED', ['ROUNDED_TO_ZERO'], 'ALLOWED_ZERO_AMOUNT'],
    ['personal-use', ['rtc.credit_context' => 'PERSONAL_USE'], 'DENIED', ['PERSONAL_USE_OR_CONSUMPTION'], 'FINAL_DENIAL_ZERO'],
    ['not-regular', ['rtc.acquirer_regime' => 'SIMPLES'], 'DENIED', ['CLAIMANT_NOT_REGULAR'], 'FINAL_DENIAL_ZERO'],
    ['output', ['rtc.credit_context' => 'OUTPUT'], 'NOT_ASSESSED_ON_OUTPUT', ['OUTPUT_CONTEXT_NO_INPUT_CREDIT'], 'NOT_ASSESSED_NO_AMOUNT'],
]);
