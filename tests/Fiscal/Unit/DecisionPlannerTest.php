<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Decision\DecisionNodeDefinition;
use App\Modules\Fiscal\Core\Decision\DecisionPlanner;
use App\Modules\Fiscal\Core\Decision\DecisionPlanningException;

it('builds a stable topological execution plan', function (): void {
    $plan = (new DecisionPlanner)->plan([
        new DecisionNodeDefinition('calculate', ['parameters', 'responsibility']),
        new DecisionNodeDefinition('location', ['classify']),
        new DecisionNodeDefinition('classify'),
        new DecisionNodeDefinition('parameters', ['location']),
        new DecisionNodeDefinition('responsibility', ['location']),
    ]);

    expect($plan->nodeIds())->toBe(['classify', 'location', 'parameters', 'responsibility', 'calculate']);
});

it('rejects cycles before runtime execution', function (): void {
    (new DecisionPlanner)->plan([
        new DecisionNodeDefinition('a', ['b']),
        new DecisionNodeDefinition('b', ['a']),
    ]);
})->throws(DecisionPlanningException::class, 'contains a cycle');

it('rejects unknown dependencies', function (): void {
    (new DecisionPlanner)->plan([
        new DecisionNodeDefinition('a', ['missing']),
    ]);
})->throws(DecisionPlanningException::class, 'depends on unknown node');
