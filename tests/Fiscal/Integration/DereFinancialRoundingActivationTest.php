<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialTaxEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

it('activates the documentary half-even boundary while the predecessor retains its historical rounding', function (): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $operation = ['status' => 'RESOLVED', 'qualification' => ['regime' => 'SPECIFIC_FINANCIAL_ART181',
        'supplier_id' => 'supplier', 'base_family' => 'CREDIT_FX_SECURITIES_ART192'],
        'base' => ['status' => 'RESOLVED', 'family' => 'CREDIT_FX_SECURITIES_ART192', 'supplier_id' => 'supplier',
            'period_end' => '2026-09-30', 'net_base' => '5.00', 'art188_proportion' => null]];
    $outcomes = [];
    foreach (['13', '14'] as $cut) {
        $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.02-'.$cut.'.json');
        $documents = $knowledge['documents'];
        $outcomes[$cut] = (new FinancialTaxEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']), $documents['rtc_code_catalog']['financial_rate_catalog']))
            ->resolve($operation, LocalDate::fromString('2026-09-30'), new DateTimeImmutable($knowledge['knowledge_available_at']), true, null,
                $documents['rtc_code_catalog']['financial_operation']['tax']);
    }
    expect($outcomes['13']['ibs_nominal'])->toBe('0.01')->and($outcomes['13']['cbs_nominal'])->toBe('0.05')
        ->and($outcomes['14']['ibs_nominal'])->toBe('0.00')->and($outcomes['14']['cbs_nominal'])->toBe('0.04')
        ->and($outcomes['14']['rounding'])->toBe('DERE_HALF_EVEN_8_2');
});
