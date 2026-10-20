<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SimplesRegularElectionQualifier78;

it('qualifies the source-bound H1 election or refuses incomplete contradictory or future evidence', function (string $name): void {
    $path = dirname(__DIR__, 3).'/storage/app/phase4b-simples78-integration-preparation-20261015/component-cases.json';
    $cases = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $case = current(array_filter($cases, static fn (array $row): bool => $row['case'] === $name));
    $qualifier = new SimplesRegularElectionQualifier78;
    $result = $qualifier->qualify($case['facts'], $case['on'], new DateTimeImmutable($case['known_at']), $case['policy']);
    expect($result['status'])->toBe($case['expected_status'])
        ->and($result['reason'])->toBe($case['expected_reason'])
        ->and($result['government_receipt_authenticated'])->toBeFalse()
        ->and($result['production_ready'])->toBeFalse();
    $policy = $case['policy'];
    $policy['source_hashes'] = array_reverse($policy['source_hashes'], true);
    expect($qualifier->qualify($case['facts'], $case['on'], new DateTimeImmutable($case['known_at']), $policy))->toBe($result);
})->with(function (): array {
    $path = dirname(__DIR__, 3).'/storage/app/phase4b-simples78-integration-preparation-20261015/component-cases.json';

    return array_column(json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR), 'case');
});
