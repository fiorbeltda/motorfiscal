<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\SupplierCreditPeriodEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @param array<string, mixed>|null $period
 * @return array{0: array<string, mixed>, 1: mixed}
 */
function supplierPeriodCase(string $name = 'mixed', ?array $period = null): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.02-3.json');
    $input = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b-2026.10.02-3/supplier-credit-period-'.$name.'-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    if ($period !== null) {
        $input['facts']['rtc.supplier_credit_period'] = [$period];
    }

    return [$knowledge, $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input)];
}

/** @param array<string, mixed>|null $period
 * @return array<string, mixed>
 */
function supplierPeriodResult(string $name = 'mixed', ?array $period = null): array
{
    [$knowledge, $scenario] = supplierPeriodCase($name, $period);
    $documents = $knowledge['documents'];
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog']));

    return $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
}

it('annuls only the proportion of immune and exempt outputs excluding statutory exceptions', function (): void {
    $result = supplierPeriodResult();
    $period = $result['decision']['supplier_credit_period'];
    expect($result['status'])->toBe('VALID')->and($period['taxes']['IBS']['annulment_numerator'])->toBe('300.00')
        ->and($period['taxes']['IBS']['annulled_credit'])->toBe('30.00')
        ->and($period['taxes']['IBS']['maintained_credit'])->toBe('70.00')
        ->and($period['taxes']['CBS']['annulled_credit'])->toBe('270.00')
        ->and($result['decision']['ibs']['credit']['status'])->toBe('NOT_ASSESSED_ON_OUTPUT')
        ->and($result['decision']['ibs']['nominal_amount'])->toBe('0.40');
});

it('preserves supplier credits on documented zero reduced and excepted immune outputs', function (string $name): void {
    $period = supplierPeriodResult($name)['decision']['supplier_credit_period'];
    expect($period['taxes']['IBS']['annulled_credit'])->toBe('0.00')
        ->and($period['taxes']['CBS']['maintained_credit'])->toBe('900.00');
})->with(['zero-preserved', 'reduced-preserved', 'export-preserved', 'books-preserved', 'broadcast-preserved']);

it('handles full annulment asymmetric treatment and previous adjustments independently', function (): void {
    $immune = supplierPeriodResult('fully-immune')['decision']['supplier_credit_period'];
    expect($immune['taxes']['IBS']['maintained_credit'])->toBe('0.00');
    $asymmetric = supplierPeriodResult('asymmetric')['decision']['supplier_credit_period'];
    expect($asymmetric['taxes']['IBS']['annulled_credit'])->toBe('40.00')
        ->and($asymmetric['taxes']['CBS']['annulled_credit'])->toBe('0.00');
    $adjusted = supplierPeriodResult('adjusted')['decision']['supplier_credit_period'];
    expect($adjusted['taxes']['IBS']['credit_pool'])->toBe('90.00')
        ->and($adjusted['taxes']['IBS']['annulled_credit'])->toBe('27.00')
        ->and($adjusted['taxes']['IBS']['maintained_credit'])->toBe('63.00');
});

it('rounds monetary annulment once and keeps exact ratio operands', function (): void {
    $result = supplierPeriodResult('rational-rounding')['decision']['supplier_credit_period'];
    expect($result['taxes']['IBS']['annulled_credit'])->toBe('0.02')
        ->and($result['taxes']['IBS']['maintained_credit'])->toBe('0.03')
        ->and($result['taxes']['CBS']['annulled_credit'])->toBe('0.03')
        ->and($result['taxes']['CBS']['annulment_ratio'])->toBe(['numerator' => '1.00', 'denominator' => '3.00']);
    [, $scenario] = supplierPeriodCase('rational-rounding');
    $period = $scenario->input->context->facts->value('rtc.supplier_credit_period')[0];
    $period['total_output_value_brl'] = '1000000000000.00';
    $period['outputs'][0]['value_brl'] = '499999999999.00';
    $period['outputs'][1]['value_brl'] = '500000000001.00';
    $period['credit_pool'][0]['ibs_amount'] = '0.01';
    $edge = supplierPeriodResult('rational-rounding', $period)['decision']['supplier_credit_period'];
    expect($edge['taxes']['IBS']['annulled_credit'])->toBe('0.00');
});

it('requires a complete closed ledger and a policy for zero turnover or specific regimes', function (string $name, string $reason): void {
    $result = supplierPeriodResult($name);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toBe([$reason])
        ->and($result['missing_facts'])->toBe(['rtc.supplier_credit_period']);
})->with([
    ['incomplete', 'RTC_SUPPLIER_CREDIT_PERIOD_LEDGER_INCOMPLETE'],
    ['future-period', 'RTC_SUPPLIER_CREDIT_PERIOD_NOT_CLOSED'],
    ['zero-turnover', 'RTC_SUPPLIER_CREDIT_PERIOD_ZERO_TURNOVER_POLICY_REQUIRED'],
    ['special-policy', 'RTC_SUPPLIER_CREDIT_OUTPUT_POLICY_NOT_PROVEN:IBS'],
]);

it('rejects duplicate conflicting or overdrawn period evidence', function (string $name, string $reason): void {
    $result = supplierPeriodResult($name);
    expect($result['status'])->toBe('INVALID')->and($result['reason_codes'])->toBe([$reason]);
})->with([
    ['wrong-total', 'RTC_SUPPLIER_CREDIT_PERIOD_OUTPUT_TOTAL_CONFLICT'],
    ['duplicate-output', 'RTC_SUPPLIER_CREDIT_PERIOD_OUTPUT_INVALID'],
    ['wrong-owner', 'RTC_SUPPLIER_CREDIT_PERIOD_OWNER_OR_CURRENCY_CONFLICT'],
    ['adjustment-excess', 'RTC_SUPPLIER_CREDIT_PERIOD_ADJUSTMENT_EXCEEDS_ENTRY'],
    ['adjustment-future', 'RTC_SUPPLIER_CREDIT_PERIOD_ADJUSTMENT_DATE_CONFLICT'],
    ['already-annulled', 'RTC_SUPPLIER_CREDIT_PERIOD_ALREADY_ANNULLED'],
]);

it('requires appropriation proof and refuses duplicate credit documents', function (): void {
    [, $scenario] = supplierPeriodCase();
    $period = $scenario->input->context->facts->value('rtc.supplier_credit_period')[0];
    unset($period['credit_pool'][0]['appropriation_evidence_id']);
    expect(supplierPeriodResult('mixed', $period)['reason_codes'])->toBe(['RTC_SUPPLIER_CREDIT_PERIOD_POOL_ENTRY_INVALID']);
    $period = $scenario->input->context->facts->value('rtc.supplier_credit_period')[0];
    $period['credit_pool'][] = [...$period['credit_pool'][0], 'id' => 'other-id-same-document'];
    expect(supplierPeriodResult('mixed', $period)['reason_codes'])->toBe(['RTC_SUPPLIER_CREDIT_PERIOD_POOL_ENTRY_INVALID']);
});

it('selects normative availability and the five year credit expiry boundary', function (): void {
    [$knowledge, $scenario] = supplierPeriodCase();
    $rule = $knowledge['documents']['rtc_code_catalog']['supplier_credit_period'];
    $changed = [...$rule, 'revoked_at' => $scenario->input->context->knowledgeAt->format(DATE_ATOM)];
    $engine = new SupplierCreditPeriodEngine;
    expect($engine->resolve($scenario->input->context->facts, $scenario->input->context->competenceDate,
        $scenario->input->context->knowledgeAt, $changed)['reason'])->toBe('RTC_SUPPLIER_CREDIT_PERIOD_RULE_NOT_AVAILABLE');
    $period = $scenario->input->context->facts->value('rtc.supplier_credit_period')[0];
    $period['credit_pool'][0]['appropriation_date'] = '2026-01-10';
    foreach (['2031-01' => 'RESOLVED', '2031-02' => 'INVALID'] as $month => $expected) {
        $period['month'] = $month;
        foreach ($period['outputs'] as &$output) {
            $output['date'] = $month.'-20';
        }
        unset($output);
        [, $changedScenario] = supplierPeriodCase('mixed', $period);
        $date = (new DateTimeImmutable($month.'-01'))->modify('+1 month');
        $result = $engine->resolve($changedScenario->input->context->facts, LocalDate::fromString($date->format('Y-m-d')), $date, $rule);
        expect($result['status'])->toBe($expected);
    }
});
