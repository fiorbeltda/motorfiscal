<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\ISS\IssRateDecision;
use App\Modules\Fiscal\ISS\IssResponsibility;
use App\Modules\Fiscal\ISS\IssTransitionRateEngine;
use App\Modules\Fiscal\ISS\MunicipalFiscalParametersResult;
use App\Modules\Fiscal\ISS\MunicipalParameterStatus;

function transitionRateEngine(): IssTransitionRateEngine
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $catalog = json_decode((string) file_get_contents($root.'/catalogs/rtc-transition-2026.09.28-5.json'),
        true, 512, JSON_THROW_ON_ERROR);

    return new IssTransitionRateEngine(new TransitionCatalog($catalog));
}

function frozenMunicipalRate(string $sourceType = 'OFFICIAL_FILE'): MunicipalFiscalParametersResult
{
    return new MunicipalFiscalParametersResult(MunicipalParameterStatus::FOUND,
        DecimalValue::fromString('2.00'), IssResponsibility::PROVIDER, 'municipal-law-2028', $sourceType,
        ['MUNICIPAL_PARAMETERS_RESOLVED'], 'MUNICIPAL-LAW-2028');
}

it('applies each published annual factor to the official rate effective on 2028-12-31', function (): void {
    $engine = transitionRateEngine();
    $current = new IssRateDecision(DecimalValue::fromString('4.00'), 'MUNICIPAL_RATE', 'ISS_MUNICIPAL_RATE_SELECTED');
    foreach (['2029' => '1.80', '2030' => '1.60', '2031' => '1.40', '2032' => '1.20'] as $year => $expected) {
        $result = $engine->resolve(LocalDate::fromString($year.'-06-15'),
            new DateTimeImmutable($year.'-06-16T10:00:00-03:00'), $current, frozenMunicipalRate());
        expect($result['status'])->toBe('RESOLVED')
            ->and($result['rate']->rate->rounded(2)->value())->toBe($expected)
            ->and($result['reference_rate_percent'])->toBe('2.00')
            ->and($result['source_id'])->toBe('MUNICIPAL-LAW-2028')
            ->and($result['law_source']['source_hash'])->toMatch('/^[a-f0-9]{64}$/');
    }
});

it('requires the future official reference instead of applying a fixture or current rate', function (): void {
    $engine = transitionRateEngine();
    $current = new IssRateDecision(DecimalValue::fromString('4.00'), 'MUNICIPAL_RATE', 'ISS_MUNICIPAL_RATE_SELECTED');
    $date = LocalDate::fromString('2029-04-01');
    $known = new DateTimeImmutable('2029-04-02T10:00:00-03:00');
    expect($engine->resolve($date, $known, $current, frozenMunicipalRate('FIXTURE'))['reason'])
        ->toBe('ISS_2028_FROZEN_RATE_SOURCE_NOT_APPROVED');

    $missing = new MunicipalFiscalParametersResult(MunicipalParameterStatus::NOT_FOUND, null, null, null, null);
    expect($engine->resolve($date, $known, $current, $missing)['reason'])
        ->toBe('ISS_2028_FROZEN_RATE_NOT_PROVEN');

    $unreached = $engine->resolve($date, new DateTimeImmutable('2028-12-31T10:00:00-03:00'),
        $current, frozenMunicipalRate());
    expect($unreached['reason'])->toBe('ISS_2028_REFERENCE_DATE_NOT_REACHED');
});

it('does not apply the municipal transition formula to a Simples effective rate', function (): void {
    $current = new IssRateDecision(DecimalValue::fromString('3.00'), 'SIMPLES_EFFECTIVE_RATE', 'ISS_SIMPLES_EFFECTIVE_RATE_SELECTED');
    $result = transitionRateEngine()->resolve(LocalDate::fromString('2030-07-01'),
        new DateTimeImmutable('2030-07-02T10:00:00-03:00'), $current, frozenMunicipalRate());
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason'])->toBe('ISS_SPECIAL_RATE_TRANSITION_NOT_MATERIALIZED');
});
