<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Scenario;

use App\Modules\Fiscal\Core\Decision\FiscalDecisionContext;
use App\Modules\Fiscal\Core\Engine\FiscalDecisionInput;
use App\Modules\Fiscal\Core\Evidence\EvidenceReference;
use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Facts\FactDefinitionRegistry;
use App\Modules\Fiscal\Core\Facts\FactSourceType;
use App\Modules\Fiscal\Core\Facts\FactValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\Infrastructure\Schema\JsonSchemaValidator;
use DateTimeImmutable;
use JsonException;
use RuntimeException;

final readonly class ScenarioFileLoader
{
    public function __construct(
        private JsonSchemaValidator $schemaValidator,
        private FactDefinitionRegistry $facts,
        /** @var array<string|int, mixed> */
        private array $schema,
    ) {}

    public function load(string $path): LoadedScenario
    {
        try {
            $source = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid scenario JSON: {$exception->getMessage()}", previous: $exception);
        }
        if (! is_array($source)) {
            throw new RuntimeException('Scenario must be a JSON object.');
        }

        return $this->fromArray($source);
    }

    /** @param array<string, mixed> $source */
    public function fromArray(array $source): LoadedScenario
    {
        $validation = $this->schemaValidator->validateArray($source, $this->schema);
        if (! $validation->valid) {
            throw new RuntimeException('Scenario schema validation failed: '.json_encode($validation->errors, JSON_UNESCAPED_UNICODE));
        }
        if (! isset($source['facts']) || ! is_array($source['facts'])) {
            throw new RuntimeException('Scenario must be a JSON object with facts.');
        }

        $competence = LocalDate::fromString((string) $source['competence_date']);
        $knowledgeAt = new DateTimeImmutable((string) $source['knowledge_at']);
        $bag = new FactBag($this->facts);
        foreach ($source['facts'] as $key => $value) {
            if (! is_string($key) || ! $this->facts->has($key)) {
                throw new RuntimeException("Unknown fact in scenario: {$key}");
            }
            $envelope = is_array($value) && array_key_exists('value', $value) ? $value : [];
            $factValue = $envelope === [] ? $value : $envelope['value'];
            $evidence = null;
            if (isset($envelope['evidence']) && is_array($envelope['evidence'])) {
                $evidence = new EvidenceReference(
                    (string) ($envelope['evidence']['source_id'] ?? ''),
                    (string) ($envelope['evidence']['locator'] ?? ''),
                    isset($envelope['evidence']['content_hash']) ? (string) $envelope['evidence']['content_hash'] : null,
                );
            }
            $bag->put(new FactValue(
                $key,
                $factValue,
                isset($envelope['source_type']) ? FactSourceType::from((string) $envelope['source_type']) : FactSourceType::FIXTURE,
                isset($envelope['effective_from']) ? LocalDate::fromString((string) $envelope['effective_from']) : $competence,
                isset($envelope['effective_to']) ? LocalDate::fromString((string) $envelope['effective_to']) : null,
                isset($envelope['recorded_at']) ? new DateTimeImmutable((string) $envelope['recorded_at']) : $knowledgeAt,
                $evidence,
                isset($envelope['confidence']) ? (string) $envelope['confidence'] : 'HIGH',
                isset($envelope['invalidated_at']) ? new DateTimeImmutable((string) $envelope['invalidated_at']) : null,
            ));
        }
        $catalogVersions = isset($source['catalog_versions']) && is_array($source['catalog_versions'])
            ? array_map('strval', $source['catalog_versions'])
            : [];

        return new LoadedScenario(
            new FiscalDecisionInput(
                (string) $source['scenario_id'],
                new FiscalDecisionContext(
                    $bag,
                    $competence,
                    $knowledgeAt,
                    (string) $source['rule_bundle_id'],
                    $catalogVersions,
                ),
            ),
            $source,
        );
    }
}
