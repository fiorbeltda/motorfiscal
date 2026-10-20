<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Scenario;

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Core\Engine\DecisionStatus;
use App\Modules\Fiscal\Core\Engine\FiscalEvaluationResult;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;
use RuntimeException;

final readonly class RtcScenarioRunService
{
    public function __construct(private FiscalEngineFactory $factory) {}

    public function execute(string $resourceRoot, string $manifestPath, string $scenarioPath): ScenarioExecution
    {
        $knowledge = $this->factory->captureKnowledge($resourceRoot, $manifestPath);
        $scenario = $this->factory->scenarioLoaderFromKnowledge($knowledge)->load($scenarioPath);

        return new ScenarioExecution($scenario, $this->evaluate($knowledge, $scenario), $knowledge);
    }

    /** @param array<string, mixed> $snapshot */
    public function replay(array $snapshot): bool
    {
        if (! isset($snapshot['input'], $snapshot['knowledge'], $snapshot['result'])
            || ! is_array($snapshot['input']) || ! is_array($snapshot['knowledge']) || ! is_array($snapshot['result'])) {
            throw new RuntimeException('RTC snapshot structure is invalid.');
        }
        $savedPayload = $snapshot['result'];
        $savedHash = $savedPayload['decision_hash'] ?? null;
        unset($savedPayload['decision_hash']);
        if (! is_string($savedHash) || ! hash_equals($savedHash, CanonicalJson::hash($savedPayload))) {
            return false;
        }
        $scenario = $this->factory->scenarioLoaderFromKnowledge($snapshot['knowledge'])->fromArray($snapshot['input']);
        $result = $this->evaluate($snapshot['knowledge'], $scenario);
        $originalHash = $snapshot['result']['decision_hash'] ?? null;

        return is_string($originalHash) && hash_equals($originalHash, $result->hash());
    }

    /** @param array<string, mixed> $knowledge */
    public function evaluate(array $knowledge, LoadedScenario $scenario): FiscalEvaluationResult
    {
        $input = $scenario->input;
        if ($input->context->ruleBundleId !== ($knowledge['rule_bundle_id'] ?? null)
            || CanonicalJson::encode($input->context->catalogVersions) !== CanonicalJson::encode($knowledge['catalog_versions'] ?? [])) {
            throw new RuntimeException('RTC scenario bundle or catalog versions do not match.');
        }
        if ($input->context->knowledgeAt < new DateTimeImmutable((string) ($knowledge['knowledge_available_at'] ?? ''))) {
            throw new RuntimeException('RTC bundle was not known at the scenario knowledge date.');
        }
        $documents = $knowledge['documents'] ?? null;
        if (! is_array($documents)) {
            throw new RuntimeException('RTC knowledge documents are absent.');
        }
        // Revalidate all captured knowledge hashes before replaying a saved snapshot.
        foreach (['rtc_rate_catalog', 'rtc_code_catalog', 'rtc_transition_catalog', 'questions', 'fact_definitions'] as $key) {
            if (! isset($documents[$key]) || ! is_array($documents[$key])
                || ($knowledge['content_hashes'][$key] ?? null) !== CanonicalJson::hash($documents[$key])) {
                throw new RuntimeException("RTC knowledge document hash mismatch: {$key}");
            }
        }
        $engine = new ConsumptionReformEngine(
            new TemporalRateCatalog($documents['rtc_rate_catalog']),
            $documents['rtc_code_catalog'],
            new TransitionCatalog($documents['rtc_transition_catalog']),
        );
        $assessment = $engine->evaluate($input->context->facts, $input->context->competenceDate, $input->context->knowledgeAt);
        $questions = [];
        foreach ($assessment['missing_facts'] as $missing) {
            foreach ($documents['questions'] as $question) {
                if (($question['fact_key'] ?? null) === $missing) {
                    $questions[] = $question;
                    break;
                }
            }
        }

        return new FiscalEvaluationResult(
            DecisionStatus::from($assessment['status']),
            $input->scenarioId,
            $assessment['decision'],
            $questions,
            $assessment['reason_codes'],
            $assessment['trace'],
            $assessment['missing_facts'],
        );
    }
}
