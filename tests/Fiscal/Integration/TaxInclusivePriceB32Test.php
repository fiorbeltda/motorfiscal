<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TaxInclusivePriceB32;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** SaaS 2026 scenario (bundle 09.28-4) with the B32 gate only in memory. */
function b32Case(array $facts = [], bool $enabled = true, mixed $policy = null): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.09.28-4.json');
    if ($enabled) {
        $knowledge['documents']['rtc_code_catalog'][TaxInclusivePriceB32::KEY] = $policy ?? TaxInclusivePriceB32::POLICY;
    }
    $knowledge['documents']['fact_definitions'][] = ['key' => 'rtc.price_includes_ibs_cbs', 'type' => 'BOOLEAN', 'material' => true];
    $scenario = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b/regular-saas-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    $scenario['scenario_id'] = 'tax-inclusive-b32';
    $scenario['knowledge_at'] = '2026-11-05T12:00:00-03:00';
    $scenario['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $scenario['catalog_versions'] = $knowledge['catalog_versions'];
    $scenario['facts'] = array_merge($scenario['facts'], $facts);
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($scenario);
    $documents = $knowledge['documents'];

    return (new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog'])))
        ->evaluate($loaded->input->context->facts, $loaded->input->context->competenceDate, $loaded->input->context->knowledgeAt);
}

function b32HalfUp(DecimalValue $base, string $percent): string
{
    return $base->multipliedBy(DecimalValue::fromString($percent))->dividedBy(DecimalValue::fromString('100'), 10)->rounded(2)->value();
}

it('removes the embedded IBS/CBS from a tax-inclusive price so that base plus taxes equals the price', function (): void {
    $result = b32Case(['rtc.price_includes_ibs_cbs' => true]);
    expect($result['status'])->toBe('VALID');
    $decision = $result['decision'];
    $sum = bcadd(bcadd($decision['base']['taxable'], $decision['ibs']['nominal_amount'], 2), $decision['cbs']['nominal_amount'], 2);
    expect($sum)->toBe('1000.00')->and($decision['base']['taxable'])->toBe('990.10')
        ->and(array_column($result['trace'], null, 'stage')['TAX_INCLUSIVE_PRICE_B32']['embedded_taxes'])->toBe('9.90');
});

it('keeps the price as the base when it does not include the taxes, and without the gate', function (): void {
    expect(b32Case(['rtc.price_includes_ibs_cbs' => false])['decision']['base']['taxable'])->toBe('1000.00')
        ->and(b32Case(['rtc.price_includes_ibs_cbs' => true], false)['decision']['base']['taxable'])->toBe('1000.00')
        ->and(b32Case([], true, ['version' => 'B32'])['reason_codes'])->toBe(['RTC_TAX_INCLUSIVE_B32_POLICY_INVALID']);
});

it('either splits each price exactly or reports a rounding gap, never an approximate base', function (): void {
    $component = new TaxInclusivePriceB32;
    $tax = fn (DecimalValue $base, string $percent): string => b32HalfUp($base, $percent);
    $gaps = 0;
    for ($cents = 10000; $cents <= 11000; $cents++) {
        $charged = bcdiv((string) $cents, '100', 2);
        $result = $component->resolve($charged, ['0.10', '0.00', '0.90'], $tax);
        if ($result['status'] === 'RESOLVED') {
            $base = DecimalValue::fromString($result['taxable_base']);
            $total = bcadd(bcadd($result['taxable_base'], b32HalfUp($base, '0.10'), 2), b32HalfUp($base, '0.90'), 2);
            expect($total)->toBe($charged);
        } else {
            expect($result['reason'])->toBe('RTC_TAX_INCLUSIVE_PRICE_ROUNDING_GAP');
            $gaps++;
        }
    }
    expect($gaps)->toBeLessThan(1001);
});
