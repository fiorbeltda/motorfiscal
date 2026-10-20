<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\DecisionDiffService;

it('produces explainable deterministic decision diffs', function (): void {
    $changes = (new DecisionDiffService)->diff(
        ['iss' => ['rate' => '2.00', 'amount' => '20.00']],
        ['iss' => ['rate' => '3.00', 'amount' => '30.00']],
    );

    expect($changes)->toHaveCount(2)
        ->and($changes[0]['path'])->toBe('$.iss.amount')
        ->and($changes[1]['path'])->toBe('$.iss.rate');
});
