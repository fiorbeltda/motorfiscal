<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Decision\FiscalDecisionContext;
use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Facts\FactDefinition;
use App\Modules\Fiscal\Core\Facts\FactDefinitionRegistry;
use App\Modules\Fiscal\Core\Facts\FactValueType;
use App\Modules\Fiscal\Core\Invariants\FiscalInvariantEngine;
use App\Modules\Fiscal\Core\Invariants\RequiredDecisionPathInvariant;
use App\Modules\Fiscal\Core\Questions\QuestionDefinition;
use App\Modules\Fiscal\Core\Questions\QuestionPlanner;
use App\Modules\Fiscal\Core\Shared\LocalDate;

function emptyDecisionContext(FactDefinitionRegistry $registry): FiscalDecisionContext
{
    return new FiscalDecisionContext(
        new FactBag($registry),
        LocalDate::fromString('2026-01-01'),
        new DateTimeImmutable('2026-01-01T00:00:00Z'),
        'bundle-v1',
    );
}

it('blocks a decision missing a required path', function (): void {
    $registry = new FactDefinitionRegistry;
    $result = (new FiscalInvariantEngine([
        new RequiredDecisionPathInvariant('ISS.LOCATION.REQUIRED', 'taxes.iss.municipality_ibge'),
    ]))->validate(['taxes' => ['iss' => []]], emptyDecisionContext($registry));

    expect($result->hasBlockingFailure())->toBeTrue();
});

it('asks only material missing facts and ranks the questions', function (): void {
    $registry = new FactDefinitionRegistry([
        new FactDefinition('operation.event_municipality_ibge', FactValueType::CODE, true),
        new FactDefinition('operation.optional_note', FactValueType::STRING, false),
        new FactDefinition('provider.municipality_ibge', FactValueType::CODE, true),
    ]);
    $planner = new QuestionPlanner($registry, [
        new QuestionDefinition('operation.event_municipality_ibge', 'Município do evento?', 20, 'CODE'),
        new QuestionDefinition('provider.municipality_ibge', 'Município do prestador?', 10, 'CODE'),
    ]);

    $plan = $planner->plan([
        'operation.event_municipality_ibge',
        'operation.optional_note',
        'provider.municipality_ibge',
    ]);

    expect(array_map(fn (QuestionDefinition $question): string => $question->factKey, $plan->questions))
        ->toBe(['provider.municipality_ibge', 'operation.event_municipality_ibge'])
        ->and($plan->unmappedFacts)->toBe([]);
});
