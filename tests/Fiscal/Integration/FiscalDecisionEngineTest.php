<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Engine\DecisionStatus;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\CorpusRunService;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Schema\JsonSchemaValidator;

it('evaluates the three ISS MVP families deterministically', function (string $relative, string $base, string $tax): void {
    $runner = new ScenarioRunService(new FiscalEngineFactory, resource_path('fiscal'));
    $first = $runner->run(resource_path("fiscal/scenarios/{$relative}"));
    $second = $runner->run(resource_path("fiscal/scenarios/{$relative}"));
    $startedNodes = array_values(array_map(
        static fn (array $event): string => (string) $event['payload']['node_id'],
        array_filter($first->trace, static fn (array $event): bool => $event['type'] === 'DECISION_NODE_STARTED'),
    ));
    $completedNodes = array_values(array_map(
        static fn (array $event): string => (string) $event['payload']['node_id'],
        array_filter($first->trace, static fn (array $event): bool => $event['type'] === 'DECISION_NODE_COMPLETED'),
    ));
    $expectedNodes = [
        'facts',
        'classification',
        'rules',
        'incidence',
        'location',
        'municipal_parameters',
        'responsibility',
        'rate',
        'treatment',
        'calculation',
        'federal_withholding',
        'projection',
        'invariants',
    ];

    expect($first->status)->toBe(DecisionStatus::VALID_WITH_WARNINGS)
        ->and($first->decision['iss']['calculation']['taxable_base'])->toBe($base)
        ->and($first->decision['iss']['calculation']['tax_amount'])->toBe($tax)
        ->and(array_column($first->decision['facts_used'], 'key'))->toContain(
            'service.lc116_code',
            'service.ctribnac_code',
            'resolved.iss.location_strategy',
        )
        ->and($startedNodes)->toBe($expectedNodes)
        ->and($completedNodes)->toBe($expectedNodes)
        ->and($first->hash())->toBe($second->hash());
})->with([
    ['software/valid.json', '1000.00', '20.00'],
    ['event/valid.json', '2500.00', '75.00'],
    ['construction/valid.json', '8749.75', '437.49'],
]);

it('asks an objective question when a material location fact is absent', function (): void {
    $runner = new ScenarioRunService(new FiscalEngineFactory, resource_path('fiscal'));
    $result = $runner->run(resource_path('fiscal/scenarios/event/missing-location.json'));

    expect($result->status)->toBe(DecisionStatus::ACTION_REQUIRED)
        ->and($result->questions[0]['fact_key'])->toBe('operation.event_municipality_ibge');
});

it('blocks inconsistent construction deductions', function (): void {
    $runner = new ScenarioRunService(new FiscalEngineFactory, resource_path('fiscal'));
    $result = $runner->run(resource_path('fiscal/scenarios/construction/invalid-deduction.json'));

    expect($result->status)->toBe(DecisionStatus::INVALID)
        ->and($result->reasonCodes)->toContain('DEDUCTION_OUT_OF_RANGE');
});

it('passes the complete golden corpus', function (): void {
    $total = 0;
    foreach (['software', 'event', 'construction', 'federal-phase4a'] as $family) {
        $manifest = $family === 'federal-phase4a'
            ? resource_path('fiscal/bundles/federal-phase4a-2026.09.24-2.json')
            : null;
        $runner = new ScenarioRunService(new FiscalEngineFactory, resource_path('fiscal'), null, null, $manifest);
        $report = (new CorpusRunService(
            $runner,
            new JsonSchemaValidator,
            resource_path('fiscal/schemas/expected.schema.json'),
        ))->run(
            resource_path("fiscal/scenarios/{$family}"),
            resource_path("fiscal/expected/{$family}"),
        );
        expect($report['valid'])->toBeTrue()
            ->and($report['failed'])->toBe(0);
        $total += $report['total'];
    }

    expect($total)->toBe(21);
});

it('rejects a bundle before its recorded knowledge time', function (): void {
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'));
    $source = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/software/valid.json')), true, 512, JSON_THROW_ON_ERROR);
    $source['knowledge_at'] = '2026-09-16T07:59:59-03:00';
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);

    $result = $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input);

    expect($result->status)->toBe(DecisionStatus::INVALID)
        ->and($result->reasonCodes)->toBe(['RULE_BUNDLE_NOT_KNOWN_AT_EXECUTION']);
});

it('blocks canonical code contradictions instead of silently correcting them', function (): void {
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'));
    $source = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/software/valid.json')), true, 512, JSON_THROW_ON_ERROR);
    $source['facts']['service.ctribnac_code'] = '070202';
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);

    $result = $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input);

    expect($result->status)->toBe(DecisionStatus::INVALID)
        ->and($result->reasonCodes)->toContain('SERVICE_CTRIBNAC_CODE_CONFLICT');
});

it('blocks a supplied derived location strategy that contradicts the canonical service', function (): void {
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'));
    $source = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/software/valid.json')), true, 512, JSON_THROW_ON_ERROR);
    $source['facts']['resolved.iss.location_strategy'] = 'EVENT_LOCATION';
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);

    $result = $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input);

    expect($result->status)->toBe(DecisionStatus::INVALID)
        ->and($result->reasonCodes)->toContain('SERVICE_LOCATION_STRATEGY_CONFLICT');
});

it('prioritizes a rule evaluation error over missing facts and manual review', function (): void {
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'));
    $baseRule = [
        'version' => '1.0.0',
        'kind' => 'INVARIANT',
        'effective_from' => '2026-01-01',
        'effective_to' => null,
        'source_id' => 'SOURCE.TEST',
        'source_version' => '1',
        'reason_codes' => ['TEST_RULE'],
        'precedence' => [],
    ];
    $knowledge['documents']['rules'][] = $baseRule + [
        'rule_id' => 'TEST.MISSING.FACT',
        'when' => ['fact' => 'operation.event_municipality_ibge', 'operator' => 'EQ', 'value' => '3550308'],
        'effects' => [['type' => 'ADD_WARNING', 'warning' => 'SHOULD_NOT_WIN']],
    ];
    $knowledge['documents']['rules'][] = $baseRule + [
        'rule_id' => 'TEST.MANUAL.REVIEW',
        'when' => ['fact' => 'service.canonical_id', 'operator' => 'EQ', 'value' => 'SOFTWARE_DEVELOPMENT'],
        'effects' => [['type' => 'REQUEST_MANUAL_REVIEW', 'reason' => 'SHOULD_NOT_WIN']],
    ];
    $knowledge['documents']['rules'][] = $baseRule + [
        'rule_id' => 'TEST.RUNTIME.ERROR',
        'when' => [
            'fact' => 'service.canonical_id',
            'operator' => 'IN_CATALOG',
            'catalog' => 'UNKNOWN.TEST.CATALOG',
        ],
        'effects' => [['type' => 'ADD_WARNING', 'warning' => 'NEVER_APPLIED']],
    ];
    $rulesHash = CanonicalJson::hash($knowledge['documents']['rules']);
    $knowledge['content_hashes']['rules'] = $rulesHash;
    $knowledge['documents']['bundle_manifest']['content_hashes']['rules'] = $rulesHash;
    $knowledge['content_hashes']['bundle_manifest'] = CanonicalJson::hash($knowledge['documents']['bundle_manifest']);
    $source = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/software/valid.json')), true, 512, JSON_THROW_ON_ERROR);
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);

    $result = $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input);

    expect($result->status)->toBe(DecisionStatus::INVALID)
        ->and($result->reasonCodes)->toContain('RULE_EVALUATION_ERROR')
        ->and($result->reasonCodes)->not->toContain('RULE_FACTS_MISSING', 'SHOULD_NOT_WIN');
});

it('does not suppress a warning emitted by a subordinate matched rule', function (): void {
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'));
    $subordinateId = 'TEST.SUBORDINATE.WARNING';
    foreach ($knowledge['documents']['rules'] as &$rule) {
        if ($rule['rule_id'] === 'ISS.INCIDENCE.MVP.TAXABLE') {
            $rule['precedence'] = [[
                'type' => 'OVERRIDES',
                'subordinate_rule_id' => $subordinateId,
                'source_id' => 'SOURCE.TEST.PRECEDENCE',
                'source_version' => '1',
            ]];
        }
    }
    unset($rule);
    $knowledge['documents']['rules'][] = [
        'rule_id' => $subordinateId,
        'version' => '1.0.0',
        'kind' => 'INVARIANT',
        'effective_from' => '2026-01-01',
        'effective_to' => null,
        'when' => ['fact' => 'service.canonical_id', 'operator' => 'EQ', 'value' => 'SOFTWARE_DEVELOPMENT'],
        'effects' => [['type' => 'ADD_WARNING', 'warning' => 'SUBORDINATE_WARNING_RETAINED']],
        'source_id' => 'SOURCE.TEST',
        'source_version' => '1',
        'reason_codes' => ['TEST_SUBORDINATE_WARNING'],
        'precedence' => [],
    ];
    $rulesHash = CanonicalJson::hash($knowledge['documents']['rules']);
    $knowledge['content_hashes']['rules'] = $rulesHash;
    $knowledge['documents']['bundle_manifest']['content_hashes']['rules'] = $rulesHash;
    $knowledge['content_hashes']['bundle_manifest'] = CanonicalJson::hash($knowledge['documents']['bundle_manifest']);
    $source = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/software/valid.json')), true, 512, JSON_THROW_ON_ERROR);
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);

    $result = $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input);

    expect($result->status)->toBe(DecisionStatus::VALID_WITH_WARNINGS)
        ->and($result->decision['warnings'])->toContain('SUBORDINATE_WARNING_RETAINED');
});

it('does not suppress manual review requested by a subordinate matched rule', function (): void {
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'));
    $subordinateId = 'TEST.SUBORDINATE.MANUAL_REVIEW';
    foreach ($knowledge['documents']['rules'] as &$rule) {
        if ($rule['rule_id'] === 'ISS.INCIDENCE.MVP.TAXABLE') {
            $rule['precedence'] = [[
                'type' => 'EXCEPTION_OF',
                'subordinate_rule_id' => $subordinateId,
                'source_id' => 'SOURCE.TEST.PRECEDENCE',
                'source_version' => '1',
            ]];
        }
    }
    unset($rule);
    $knowledge['documents']['rules'][] = [
        'rule_id' => $subordinateId,
        'version' => '1.0.0',
        'kind' => 'INVARIANT',
        'effective_from' => '2026-01-01',
        'effective_to' => null,
        'when' => ['fact' => 'service.canonical_id', 'operator' => 'EQ', 'value' => 'SOFTWARE_DEVELOPMENT'],
        'effects' => [['type' => 'REQUEST_MANUAL_REVIEW', 'reason' => 'SUBORDINATE_REVIEW_RETAINED']],
        'source_id' => 'SOURCE.TEST',
        'source_version' => '1',
        'reason_codes' => ['TEST_SUBORDINATE_REVIEW'],
        'precedence' => [],
    ];
    $rulesHash = CanonicalJson::hash($knowledge['documents']['rules']);
    $knowledge['content_hashes']['rules'] = $rulesHash;
    $knowledge['documents']['bundle_manifest']['content_hashes']['rules'] = $rulesHash;
    $knowledge['content_hashes']['bundle_manifest'] = CanonicalJson::hash($knowledge['documents']['bundle_manifest']);
    $source = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/software/valid.json')), true, 512, JSON_THROW_ON_ERROR);
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);

    $result = $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input);

    expect($result->status)->toBe(DecisionStatus::ACTION_REQUIRED)
        ->and($result->reasonCodes)->toContain('SUBORDINATE_REVIEW_RETAINED');
});

it('rejects knowledge changed outside the immutable bundle manifest', function (): void {
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'));
    $knowledge['documents']['service_catalog']['services'][0]['description'] = 'Altered after bundle publication';
    $knowledge['content_hashes']['service_catalog'] = CanonicalJson::hash($knowledge['documents']['service_catalog']);

    expect(fn () => $factory->makeFromKnowledge($knowledge))
        ->toThrow(RuntimeException::class, 'Bundle manifest content hash mismatch: service_catalog');
});
