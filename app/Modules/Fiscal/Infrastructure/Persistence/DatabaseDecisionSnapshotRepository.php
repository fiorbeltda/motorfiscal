<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Persistence;

use App\Modules\Fiscal\Core\Engine\FiscalEvaluationResult;
use App\Modules\Fiscal\Core\Facts\FactDefinitionRegistry;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\LoadedScenario;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class DatabaseDecisionSnapshotRepository
{
    public function __construct(
        private ConnectionInterface $database,
        private FactDefinitionRegistry $definitions,
    ) {}

    /** @param array<string, mixed> $knowledge */
    public function persist(LoadedScenario $scenario, FiscalEvaluationResult $result, array $knowledge): string
    {
        return $this->database->transaction(function () use ($scenario, $result, $knowledge): string {
            $runId = Str::uuid7()->toString();
            $now = now();
            $inputHash = CanonicalJson::hash($scenario->source);
            $scenarioRow = $this->database->table('fiscal_scenarios')
                ->where('scenario_key', $scenario->input->scenarioId)
                ->first();
            if ($scenarioRow !== null && $scenarioRow->input_hash !== $inputHash) {
                throw new \RuntimeException("Persisted scenario cannot be overwritten: {$scenario->input->scenarioId}");
            }
            $scenarioId = is_string($scenarioRow?->id) ? $scenarioRow->id : Str::uuid7()->toString();
            if ($scenarioRow === null) {
                $family = $result->decision['classification']['family'] ?? 'UNKNOWN';
                $this->database->table('fiscal_scenarios')->insert([
                    'id' => $scenarioId,
                    'scenario_key' => $scenario->input->scenarioId,
                    'name' => $scenario->input->scenarioId,
                    'family' => is_string($family) ? $family : 'UNKNOWN',
                    'input' => CanonicalJson::encode($scenario->source),
                    'expected' => null,
                    'input_hash' => $inputHash,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $manifest = $knowledge['documents']['bundle_manifest'] ?? null;
            $bundleDatabaseId = null;
            if (is_array($manifest) && isset($manifest['bundle_key'], $manifest['version'])) {
                $candidate = $this->database->table('fiscal_rule_bundles')
                    ->where('bundle_key', (string) $manifest['bundle_key'])
                    ->where('version', (string) $manifest['version'])
                    ->first(['id', 'content_hash']);
                if ($candidate instanceof \stdClass) {
                    $knowledgeRuleHash = $knowledge['content_hashes']['rules'] ?? null;
                    if (! is_string($knowledgeRuleHash) || ! is_string($candidate->content_hash) || ! hash_equals($candidate->content_hash, $knowledgeRuleHash)) {
                        throw new \RuntimeException('Persisted rule bundle content does not match execution knowledge.');
                    }
                    $bundleDatabaseId = is_string($candidate->id) ? $candidate->id : null;
                }
            }
            $engineVersion = $knowledge['engine_version'] ?? null;
            if (! is_string($engineVersion) || $engineVersion === '') {
                throw new \RuntimeException('Knowledge engine version is missing.');
            }
            $this->database->table('fiscal_decision_runs')->insert([
                'id' => $runId,
                'scenario_id' => $scenarioId,
                'rule_bundle_id' => $bundleDatabaseId,
                'status' => $result->status->value,
                'competence_date' => $scenario->input->context->competenceDate->value(),
                'knowledge_at' => $scenario->input->context->knowledgeAt,
                'engine_version' => $engineVersion,
                'input_hash' => $inputHash,
                'decision_hash' => $result->hash(),
                'started_at' => $now,
                'finished_at' => $now,
                'metadata' => CanonicalJson::encode([
                    'scenario_id' => $scenario->input->scenarioId,
                    'rule_bundle_key' => $scenario->input->context->ruleBundleId,
                    'rule_bundle_content_hash' => $knowledge['content_hashes']['rules'] ?? null,
                    'catalog_versions' => $scenario->input->context->catalogVersions,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $factRows = [];
            foreach ($scenario->input->context->facts->all() as $fact) {
                $factRows[$fact->key] = $fact->toArray();
            }
            $usedFacts = $result->decision['facts_used'] ?? [];
            if (is_array($usedFacts)) {
                foreach ($usedFacts as $usedFact) {
                    if (is_array($usedFact) && isset($usedFact['key']) && is_string($usedFact['key'])) {
                        $factRows[$usedFact['key']] = $usedFact;
                    }
                }
            }
            ksort($factRows, SORT_STRING);
            foreach ($factRows as $fact) {
                $key = (string) ($fact['key'] ?? '');
                $evidence = $fact['evidence'] ?? null;
                $this->database->table('fiscal_fact_snapshots')->insert([
                    'decision_run_id' => $runId,
                    'fact_key' => $key,
                    'value_type' => $this->definitions->get($key)->type->value,
                    'value' => json_encode($fact['value'] ?? null, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'source_type' => (string) ($fact['source_type'] ?? ''),
                    'effective_from' => (string) ($fact['effective_from'] ?? ''),
                    'effective_to' => isset($fact['effective_to']) ? (string) $fact['effective_to'] : null,
                    'recorded_at' => (string) ($fact['recorded_at'] ?? ''),
                    'invalidated_at' => isset($fact['invalidated_at']) ? (string) $fact['invalidated_at'] : null,
                    'confidence' => (string) ($fact['confidence'] ?? ''),
                    'evidence' => is_array($evidence) ? CanonicalJson::encode($evidence) : null,
                ]);
            }

            $decisionId = Str::uuid7()->toString();
            $payload = $result->toArray();
            $this->database->table('fiscal_decisions')->insert([
                'id' => $decisionId,
                'decision_run_id' => $runId,
                'status' => $result->status->value,
                'payload' => CanonicalJson::encode($payload),
                'payload_hash' => $result->hash(),
                'finalized_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($result->trace as $event) {
                $this->database->table('fiscal_decision_traces')->insert([
                    'decision_run_id' => $runId,
                    'sequence' => (int) ($event['sequence'] ?? 0),
                    'event_type' => (string) ($event['type'] ?? 'UNKNOWN'),
                    'payload' => CanonicalJson::encode(is_array($event['payload'] ?? null) ? $event['payload'] : []),
                ]);
            }

            $snapshot = [
                'snapshot_version' => '1',
                'input' => $scenario->source,
                'knowledge' => $knowledge,
                'result' => $result->toArray(),
            ];
            $this->database->table('fiscal_execution_snapshots')->insert([
                'decision_run_id' => $runId,
                'payload' => CanonicalJson::encode($snapshot),
                'payload_hash' => CanonicalJson::hash($snapshot),
                'created_at' => $now,
            ]);

            return $runId;
        });
    }
}
