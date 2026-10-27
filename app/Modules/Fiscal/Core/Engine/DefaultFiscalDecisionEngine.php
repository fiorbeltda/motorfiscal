<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Engine;

use App\Modules\Fiscal\Classification\CanonicalService;
use App\Modules\Fiscal\Classification\CanonicalServiceClassifier;
use App\Modules\Fiscal\Classification\MappingStatus;
use App\Modules\Fiscal\Classification\ServiceFamily;
use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\Core\Decision\DecisionNodeDefinition;
use App\Modules\Fiscal\Core\Decision\DecisionPlanner;
use App\Modules\Fiscal\Core\Decision\ExecutionPlanCursor;
use App\Modules\Fiscal\Core\Decision\FiscalDecisionContext;
use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Facts\FactResolver;
use App\Modules\Fiscal\Core\Facts\FactResolverPipeline;
use App\Modules\Fiscal\Core\Facts\FactSourceType;
use App\Modules\Fiscal\Core\Facts\FactValue;
use App\Modules\Fiscal\Core\Invariants\FiscalInvariantEngine;
use App\Modules\Fiscal\Core\Invariants\InvariantSeverity;
use App\Modules\Fiscal\Core\Precedence\CandidateDecision;
use App\Modules\Fiscal\Core\Precedence\PrecedenceStatus;
use App\Modules\Fiscal\Core\Precedence\RulePrecedenceResolver;
use App\Modules\Fiscal\Core\Questions\QuestionPlanner;
use App\Modules\Fiscal\Core\Rules\CompiledRule;
use App\Modules\Fiscal\Core\Rules\EffectType;
use App\Modules\Fiscal\Core\Rules\RuleEffect;
use App\Modules\Fiscal\Core\Rules\RuleEvaluator;
use App\Modules\Fiscal\Core\Rules\RuleMatchStatus;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\Core\Trace\DecisionTrace;
use App\Modules\Fiscal\FederalWithholding\FederalWithholdingEngine;
use App\Modules\Fiscal\ISS\InstitutionalIssImmunityQualifier;
use App\Modules\Fiscal\ISS\IssCalculationEngine;
use App\Modules\Fiscal\ISS\IssDocumentCurrency140;
use App\Modules\Fiscal\ISS\IssIncidenceDecision;
use App\Modules\Fiscal\ISS\IssLocationEngine;
use App\Modules\Fiscal\ISS\IssLocationStrategy;
use App\Modules\Fiscal\ISS\IssRateEngine;
use App\Modules\Fiscal\ISS\IssResponsibilityEngine;
use App\Modules\Fiscal\ISS\IssTransitionRateEngine;
use App\Modules\Fiscal\ISS\IssTreatmentDecision;
use App\Modules\Fiscal\ISS\MunicipalParameterProvider;
use App\Modules\Fiscal\ISS\MunicipalParameterStatus;
use App\Modules\Fiscal\ISS\ServiceCode;
use App\Modules\Fiscal\NFSe\DpsProjection;
use DateTimeImmutable;

final readonly class DefaultFiscalDecisionEngine implements FiscalDecisionEngine
{
    /**
     * @param  list<CompiledRule>  $rules
     * @param  array<string, string>  $catalogVersions
     * @param  array<string, string>  $knowledgeHashes
     * @param  list<FactResolver>  $factResolvers
     */
    public function __construct(
        private CanonicalServiceClassifier $classifier,
        private IssLocationEngine $locationEngine,
        private MunicipalParameterProvider $parameterProvider,
        private IssResponsibilityEngine $responsibilityEngine,
        private IssRateEngine $rateEngine,
        private IssCalculationEngine $calculationEngine,
        private FederalWithholdingEngine $federalWithholdingEngine,
        private QuestionPlanner $questionPlanner,
        private RuleEvaluator $ruleEvaluator,
        private array $rules,
        private string $ruleBundleId,
        private array $catalogVersions,
        private array $knowledgeHashes,
        private FiscalInvariantEngine $invariantEngine,
        private RulePrecedenceResolver $precedenceResolver,
        private DateTimeImmutable $knowledgeAvailableAt,
        private string $bundleStatus,
        private array $factResolvers = [],
        private ?ConsumptionReformEngine $consumptionReformEngine = null,
        private ?IssTransitionRateEngine $issTransitionRateEngine = null,
        private bool $postIssIntegrated = false,
        private bool $postIssFederalWithholdings = false,
        /** @var array<string, mixed>|null */
        private ?array $institutionalIssImmunity = null,
        private ?IssDocumentCurrency140 $issDocumentCurrency = null,
    ) {}

    public function evaluate(FiscalDecisionInput $input): FiscalEvaluationResult
    {
        $trace = new DecisionTrace;
        $providedFacts = $input->context->facts;
        if ($input->context->ruleBundleId !== $this->ruleBundleId) {
            return new FiscalEvaluationResult(
                DecisionStatus::INVALID,
                $input->scenarioId,
                [],
                [],
                ['RULE_BUNDLE_VERSION_MISMATCH'],
                [],
            );
        }
        if (CanonicalJson::encode($input->context->catalogVersions) !== CanonicalJson::encode($this->catalogVersions)) {
            return new FiscalEvaluationResult(
                DecisionStatus::INVALID,
                $input->scenarioId,
                [],
                [],
                ['CATALOG_VERSION_MISMATCH'],
                [],
            );
        }
        if ($input->context->knowledgeAt < $this->knowledgeAvailableAt) {
            return new FiscalEvaluationResult(
                DecisionStatus::INVALID,
                $input->scenarioId,
                [],
                [],
                ['RULE_BUNDLE_NOT_KNOWN_AT_EXECUTION'],
                [],
            );
        }
        if ($this->issDocumentCurrency !== null && $providedFacts->value('provider.tax_regime') === IssDocumentCurrency140::FOREIGN_REGIME
            && ($providedFacts->value('operation.service_origin') !== 'FOREIGN' || $providedFacts->value('provider.person_type') !== 'FOREIGN_LEGAL_ENTITY')) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['ISS_CURRENCY140_FOREIGN_REGIME_PROFILE_CONFLICT'], [], $trace);
        }
        $postIss = $this->postIssIntegrated && $input->context->competenceDate->value() >= '2033-01-01';
        $immuneIss = ! $postIss && $providedFacts->value('iss.evaluate_institutional_immunity') === true;
        $nodes = $postIss ? [
            new DecisionNodeDefinition('facts'),
            new DecisionNodeDefinition('classification', ['facts']),
            new DecisionNodeDefinition('consumption_reform', ['classification']),
            new DecisionNodeDefinition('federal_withholding', ['consumption_reform']),
            new DecisionNodeDefinition('projection', ['federal_withholding']),
            new DecisionNodeDefinition('invariants', ['projection']),
        ] : ($immuneIss ? [
            new DecisionNodeDefinition('facts'),
            new DecisionNodeDefinition('classification', ['facts']),
            new DecisionNodeDefinition('iss_immunity', ['classification']),
            new DecisionNodeDefinition('location', ['iss_immunity']),
            new DecisionNodeDefinition('federal_withholding', ['location']),
        ] : [
            new DecisionNodeDefinition('facts'),
            new DecisionNodeDefinition('classification', ['facts']),
            new DecisionNodeDefinition('rules', ['classification']),
            new DecisionNodeDefinition('incidence', ['rules']),
            new DecisionNodeDefinition('location', ['incidence']),
            new DecisionNodeDefinition('municipal_parameters', ['location']),
            new DecisionNodeDefinition('responsibility', ['municipal_parameters']),
            new DecisionNodeDefinition('rate', ['responsibility']),
            new DecisionNodeDefinition('treatment', ['rate']),
            new DecisionNodeDefinition('calculation', ['treatment']),
            new DecisionNodeDefinition('federal_withholding', ['calculation']),
        ]);
        if (! $postIss && $this->consumptionReformEngine !== null) {
            $nodes[] = new DecisionNodeDefinition('consumption_reform', ['federal_withholding']);
        }
        if (! $postIss) {
            $nodes[] = new DecisionNodeDefinition('projection', [$this->consumptionReformEngine === null ? 'federal_withholding' : 'consumption_reform']);
            $nodes[] = new DecisionNodeDefinition('invariants', ['projection']);
        }
        $plan = (new DecisionPlanner)->plan($nodes);
        $cursor = $plan->cursor();
        $trace->add('EXECUTION_PLAN_CREATED', ['nodes' => $plan->nodeIds()]);
        $trace->add('INPUT_VALIDATED', [
            'competence_date' => $input->context->competenceDate->value(),
            'knowledge_at' => $input->context->knowledgeAt->format(DATE_ATOM),
            'rule_bundle_id' => $input->context->ruleBundleId,
        ]);
        if (! $postIss && $this->issTransitionRateEngine !== null && $input->context->competenceDate->value() >= '2033-01-01') {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED,
                ['ISS_EXTINCT_INTEGRATED_FLOW_NOT_MATERIALIZED'], [], $trace);
        }
        $temporallyUnavailable = [];
        $ignoredOptionalFacts = [];
        foreach ($providedFacts->all() as $fact) {
            if (! $fact->wasAvailableAt($input->context->competenceDate, $input->context->knowledgeAt)) {
                if ($providedFacts->definition($fact->key)->material) {
                    $temporallyUnavailable[] = $fact->key;
                } else {
                    $ignoredOptionalFacts[] = $fact->key;
                }
            }
        }
        if ($temporallyUnavailable !== []) {
            return $this->nonFinalResult(
                $input,
                DecisionStatus::ACTION_REQUIRED,
                ['FACTS_TEMPORALLY_UNAVAILABLE'],
                array_values(array_unique($temporallyUnavailable)),
                $trace,
            );
        }
        $this->startNode($cursor, 'facts', $trace);
        $facts = $providedFacts->effectiveAt($input->context->competenceDate, $input->context->knowledgeAt);
        $initialContext = new FiscalDecisionContext(
            $facts,
            $input->context->competenceDate,
            $input->context->knowledgeAt,
            $input->context->ruleBundleId,
            $input->context->catalogVersions,
        );
        $factResolution = (new FactResolverPipeline($this->factResolvers))->resolveAvailable($initialContext);
        $trace->add('FACTS_RESOLVED', [
            'fact_keys' => array_map(static fn ($fact): string => $fact->key, $factResolution->facts),
            'warnings' => $factResolution->warnings,
            'errors' => $factResolution->errors,
        ]);
        if ($factResolution->errors !== []) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['FACT_RESOLUTION_ERROR', ...$factResolution->errors], [], $trace);
        }
        try {
            $facts = $facts->withAdditional($factResolution->facts);
        } catch (\InvalidArgumentException) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['FACT_RESOLUTION_INVALID'], [], $trace);
        }
        $context = new FiscalDecisionContext(
            $facts,
            $input->context->competenceDate,
            $input->context->knowledgeAt,
            $input->context->ruleBundleId,
            $input->context->catalogVersions,
        );
        $this->completeNode($cursor, 'facts', $trace);
        if ($ignoredOptionalFacts !== []) {
            sort($ignoredOptionalFacts, SORT_STRING);
            $trace->add('OPTIONAL_FACTS_IGNORED', ['fact_keys' => $ignoredOptionalFacts]);
        }

        $this->startNode($cursor, 'classification', $trace);
        $classification = $this->classifier->classify(
            is_string($facts->value('service.canonical_id')) ? $facts->value('service.canonical_id') : null,
            is_string($facts->value('service.ctribnac_code')) ? $facts->value('service.ctribnac_code') : null,
        );
        $trace->add('SERVICE_CLASSIFICATION', ['reason_code' => $classification->reasonCode]);
        if (! $classification->isClassified()) {
            $missing = $facts->has('service.canonical_id') || $facts->has('service.ctribnac_code')
                ? []
                : ['service.ctribnac_code'];

            return $this->nonFinalResult(
                $input,
                $missing === [] ? DecisionStatus::INVALID : DecisionStatus::ACTION_REQUIRED,
                [$classification->reasonCode],
                $missing,
                $trace,
            );
        }

        $service = $classification->service;
        if ($service === null) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['SERVICE_UNSUPPORTED'], [], $trace);
        }
        if ($service->mappingStatus !== MappingStatus::CONFIRMED) {
            return $this->nonFinalResult(
                $input,
                $service->mappingStatus === MappingStatus::UNSUPPORTED ? DecisionStatus::INVALID : DecisionStatus::ACTION_REQUIRED,
                ["SERVICE_MAPPING_{$service->mappingStatus->value}"],
                [],
                $trace,
            );
        }
        if ($service->sourceId === '') {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['SERVICE_MAPPING_SOURCE_MISSING'], [], $trace);
        }
        if ($facts->has('service.lc116_code') && $facts->value('service.lc116_code') !== $service->lc116Code) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['SERVICE_LC116_CODE_CONFLICT'], [], $trace);
        }
        if ($facts->has('service.ctribnac_code') && $facts->value('service.ctribnac_code') !== $service->ctribnacCode) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['SERVICE_CTRIBNAC_CODE_CONFLICT'], [], $trace);
        }
        if ($facts->has('resolved.iss.location_strategy')
            && $facts->value('resolved.iss.location_strategy') !== $service->locationStrategy->value) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['SERVICE_LOCATION_STRATEGY_CONFLICT'], [], $trace);
        }
        $this->completeNode($cursor, 'classification', $trace);

        if ($postIss) {
            return $this->evaluatePostIss($input, $facts, $context, $service, $cursor, $trace);
        }
        if ($immuneIss) {
            return $this->evaluateImmuneIss($input, $facts, $context, $service, $cursor, $trace);
        }

        $this->startNode($cursor, 'rules', $trace);
        /** @var array<string, list<array{rule_id: string, value: mixed, reason_code: string}>> $decisionCandidates */
        $decisionCandidates = [];
        $warnings = $factResolution->warnings;
        if ($ignoredOptionalFacts !== []) {
            $warnings[] = 'OPTIONAL_FACTS_TEMPORALLY_IGNORED';
        }
        if ($this->bundleStatus !== 'APPROVED') {
            $warnings[] = 'RULE_BUNDLE_NOT_PRODUCTION_APPROVED';
        }
        /** @var list<array{rule: CompiledRule, effects: list<RuleEffect>}> $matchedRules */
        $matchedRules = [];
        $ruleErrors = [];
        $ruleMissingFacts = [];
        foreach ($this->rules as $rule) {
            $evaluation = $this->ruleEvaluator->evaluate($rule, $context);
            $trace->add('RULE_EVALUATED', [
                'rule_id' => $rule->id,
                'rule_version' => $rule->version,
                'source_id' => $rule->sourceId,
                'source_version' => $rule->sourceVersion,
                'status' => $evaluation->status->value,
                'truth' => $evaluation->truth->value,
                'missing_facts' => $evaluation->missingFacts,
                'errors' => $evaluation->errors,
                'reason_codes' => $rule->reasonCodes,
            ]);
            if ($evaluation->status === RuleMatchStatus::ERROR) {
                array_push($ruleErrors, ...$evaluation->errors);

                continue;
            }
            if ($evaluation->status === RuleMatchStatus::NEEDS_FACTS) {
                array_push($ruleMissingFacts, ...$evaluation->missingFacts);

                continue;
            }
            if ($evaluation->status === RuleMatchStatus::MATCHED) {
                $matchedRules[] = ['rule' => $rule, 'effects' => $evaluation->effects];
            }
        }
        if ($ruleErrors !== []) {
            return $this->nonFinalResult(
                $input,
                DecisionStatus::INVALID,
                ['RULE_EVALUATION_ERROR', ...array_values(array_unique($ruleErrors))],
                [],
                $trace,
            );
        }
        if ($ruleMissingFacts !== []) {
            return $this->nonFinalResult(
                $input,
                DecisionStatus::ACTION_REQUIRED,
                ['RULE_FACTS_MISSING'],
                array_values(array_unique($ruleMissingFacts)),
                $trace,
            );
        }
        $manualReviewReasons = [];
        foreach ($matchedRules as $matchedRule) {
            $rule = $matchedRule['rule'];
            foreach ($matchedRule['effects'] as $effect) {
                if (! $effect->type->isSupportedByDecisionEngine()) {
                    return $this->nonFinalResult($input, DecisionStatus::INVALID, ['RULE_EFFECT_UNSUPPORTED:'.$effect->type->value], [], $trace);
                }
                if ($effect->type === EffectType::SELECT_STRATEGY && isset($effect->payload['strategy'])) {
                    $decisionCandidates['engine.location_strategy'][] = [
                        'rule_id' => $rule->id,
                        'value' => (string) $effect->payload['strategy'],
                        'reason_code' => $rule->reasonCodes[0],
                    ];
                }
                if ($effect->type === EffectType::SELECT_FORMULA && isset($effect->payload['formula'])) {
                    $arguments = $effect->payload['arguments'] ?? null;
                    if (! is_array($arguments)) {
                        return $this->nonFinalResult($input, DecisionStatus::INVALID, ['FORMULA_ARGUMENT_BINDINGS_MISSING'], [], $trace);
                    }
                    $decisionCandidates['engine.formula'][] = [
                        'rule_id' => $rule->id,
                        'value' => [
                            'formula' => (string) $effect->payload['formula'],
                            'arguments' => array_map('strval', $arguments),
                        ],
                        'reason_code' => $rule->reasonCodes[0],
                    ];
                }
                if ($effect->type === EffectType::SET_DECISION_VALUE && isset($effect->payload['path']) && array_key_exists('value', $effect->payload)) {
                    $path = (string) $effect->payload['path'];
                    if (! in_array($path, ['iss.incidence', 'iss.treatment'], true)) {
                        return $this->nonFinalResult($input, DecisionStatus::INVALID, ["RULE_DECISION_PATH_UNSUPPORTED:{$path}"], [], $trace);
                    }
                    $decisionCandidates[$path][] = [
                        'rule_id' => $rule->id,
                        'value' => $effect->payload['value'],
                        'reason_code' => $rule->reasonCodes[0],
                    ];
                }
                if ($effect->type === EffectType::REQUIRE_DETAIL_GROUP && isset($effect->payload['group'])) {
                    $decisionCandidates['dps.detail_group'][] = [
                        'rule_id' => $rule->id,
                        'value' => (string) $effect->payload['group'],
                        'reason_code' => $rule->reasonCodes[0],
                    ];
                }
                if ($effect->type === EffectType::ADD_WARNING && isset($effect->payload['warning'])) {
                    $warnings[] = (string) $effect->payload['warning'];
                }
                if ($effect->type === EffectType::REQUEST_MANUAL_REVIEW && isset($effect->payload['reason'])) {
                    $manualReviewReasons[] = (string) $effect->payload['reason'];
                }
            }
        }
        if ($manualReviewReasons !== []) {
            return $this->nonFinalResult(
                $input,
                DecisionStatus::ACTION_REQUIRED,
                array_values(array_unique($manualReviewReasons)),
                [],
                $trace,
            );
        }
        $this->completeNode($cursor, 'rules', $trace);
        $this->startNode($cursor, 'incidence', $trace);
        $incidenceCandidate = $this->singleDecisionCandidate($decisionCandidates, 'iss.incidence');
        if ($incidenceCandidate === null) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['RULESET_INCIDENCE_CONFLICT'], [], $trace);
        }
        if (! is_string($incidenceCandidate['value']) || $incidenceCandidate['value'] !== 'TAXABLE') {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['ISS_INCIDENCE_UNSUPPORTED'], [], $trace);
        }
        $incidence = new IssIncidenceDecision($incidenceCandidate['value'], $incidenceCandidate['reason_code']);
        $this->completeNode($cursor, 'incidence', $trace);

        $this->startNode($cursor, 'location', $trace);
        $strategyCandidates = $decisionCandidates['engine.location_strategy'] ?? [];
        $strategyCandidate = $this->singleDecisionCandidate($decisionCandidates, 'engine.location_strategy');
        if ($strategyCandidates !== [] && ($strategyCandidate === null || $strategyCandidate['value'] !== $service->locationStrategy->value)) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['RULESET_LOCATION_STRATEGY_CONFLICT'], [], $trace);
        }
        $resolvedStrategy = $strategyCandidate === null
            ? $service->locationStrategy
            : IssLocationStrategy::from((string) $strategyCandidate['value']);
        if ($strategyCandidate === null) {
            $trace->add('ISS_LOCATION_STRATEGY_FROM_CATALOG', [
                'strategy' => $resolvedStrategy->value,
                'source_id' => $service->sourceId,
                'ctribnac_code' => $service->ctribnacCode,
            ]);
        }
        if ($facts->value('operation.illegal_below_minimum_tax_burden') === true) {
            if (! $facts->has('operation.responsible_role')) {
                return $this->nonFinalResult(
                    $input,
                    DecisionStatus::ACTION_REQUIRED,
                    ['ISS_NATIONAL_RESPONSIBLE_ROLE_MISSING'],
                    ['operation.responsible_role'],
                    $trace,
                );
            }
            $resolvedStrategy = match ($facts->value('operation.responsible_role')) {
                'CUSTOMER' => IssLocationStrategy::CUSTOMER_ESTABLISHMENT,
                'INTERMEDIARY' => IssLocationStrategy::INTERMEDIARY_ESTABLISHMENT,
                default => $resolvedStrategy,
            };
            $trace->add('ISS_LOCATION_STRATEGY_OVERRIDDEN_BY_MINIMUM_RATE_RULE', [
                'strategy' => $resolvedStrategy->value,
                'legal_basis' => 'LC 116/2003 art. 3º, §4º',
            ]);
        }
        $detailGroups = [];
        if ($service->dpsDetailGroup === null) {
            if (($decisionCandidates['dps.detail_group'] ?? []) !== []) {
                return $this->nonFinalResult($input, DecisionStatus::INVALID, ['RULESET_DETAIL_GROUP_CONFLICT'], [], $trace);
            }
        } else {
            $detailCandidates = $decisionCandidates['dps.detail_group'] ?? [];
            $detailCandidate = $this->singleDecisionCandidate($decisionCandidates, 'dps.detail_group');
            if ($detailCandidates !== [] && ($detailCandidate === null || $detailCandidate['value'] !== $service->dpsDetailGroup)) {
                return $this->nonFinalResult($input, DecisionStatus::INVALID, ['RULESET_DETAIL_GROUP_CONFLICT'], [], $trace);
            }
            $detailGroups[] = $service->dpsDetailGroup;
        }

        $location = $this->locationEngine->resolve(
            $resolvedStrategy,
            $facts,
            $service->importLocationOverride,
            $service->ctribnacCode,
        );
        $trace->add('ISS_LOCATION', $location->toArray());
        if ($location->missingFacts !== []) {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED, [$location->reasonCode], $location->missingFacts, $trace);
        }
        if ($location->location === null) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, [$location->reasonCode], [], $trace);
        }

        $familyFacts = match ($service->family) {
            ServiceFamily::GENERAL => [],
            ServiceFamily::SOFTWARE => [],
            ServiceFamily::EVENT => ['operation.event_activity_confirmed'],
            ServiceFamily::CONSTRUCTION => $service->ctribnacCode === '070202'
                ? [
                    'customer.person_type',
                    'operation.construction_object_type',
                    'operation.construction_contract_type',
                ]
                : [],
        };
        $missingFamilyFacts = $facts->missing($familyFacts);
        if ($missingFamilyFacts !== []) {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED, ['SERVICE_FAMILY_FACTS_MISSING'], $missingFamilyFacts, $trace);
        }
        if ($service->family === ServiceFamily::EVENT && $facts->value('operation.event_activity_confirmed') !== true) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['EVENT_ACTIVITY_INCOMPATIBLE'], [], $trace);
        }
        if ($service->ctribnacCode === '070202' && $facts->value('operation.construction_object_type') !== 'WORK') {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['CONSTRUCTION_OBJECT_UNSUPPORTED_FOR_MVP'], [], $trace);
        }
        if ($service->ctribnacCode === '070202'
            && in_array($facts->value('operation.construction_contract_type'), ['DIRECT_ADMINISTRATION', 'ISOLATED_SERVICE'], true)) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['CONSTRUCTION_CONTRACT_INCOMPATIBLE_WITH_070202'], [], $trace);
        }
        $this->completeNode($cursor, 'location', $trace);

        $this->startNode($cursor, 'municipal_parameters', $trace);
        $parameters = $this->parameterProvider->resolve(
            $location->location,
            new ServiceCode($service->ctribnacCode),
            $input->context->competenceDate,
            $context,
        );
        $trace->add('MUNICIPAL_PARAMETERS', $parameters->toArray());
        if ($parameters->status !== MunicipalParameterStatus::FOUND) {
            return $this->nonFinalResult(
                $input,
                $parameters->status === MunicipalParameterStatus::CONFLICT ? DecisionStatus::INVALID : DecisionStatus::ACTION_REQUIRED,
                $parameters->reasonCodes,
                [],
                $trace,
            );
        }
        if ($parameters->rate === null) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['MUNICIPAL_PARAMETERS_CORRUPT'], [], $trace);
        }
        if ($parameters->sourceType === 'FIXTURE') {
            $warnings[] = 'MUNICIPAL_PARAMETERS_ARE_FIXTURES';
        }
        $this->completeNode($cursor, 'municipal_parameters', $trace);

        $this->startNode($cursor, 'responsibility', $trace);
        $responsibility = $this->responsibilityEngine->resolve($service, $facts, $parameters, $location->location);
        $trace->add('ISS_RESPONSIBILITY', $responsibility->toArray());
        if ($responsibility->missingFacts !== []) {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED, [$responsibility->reasonCode], $responsibility->missingFacts, $trace);
        }
        if ($responsibility->responsibleParty === null || $responsibility->tpRetIssqn === null) {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED, [$responsibility->reasonCode], [], $trace);
        }
        if ($responsibility->withholdingPerformed === true && ! $responsibility->withholdingRequired) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['WITHHOLDING_PERFORMED_WITHOUT_REQUIREMENT'], [], $trace);
        }
        if ($responsibility->withholdingRequired && $responsibility->withholdingPerformed === false) {
            $warnings[] = 'REQUIRED_WITHHOLDING_NOT_PERFORMED';
        }
        $this->completeNode($cursor, 'responsibility', $trace);

        $this->startNode($cursor, 'rate', $trace);
        $rate = $this->rateEngine->resolve($facts, $input->context->competenceDate, $responsibility, $parameters->rate);
        $trace->add('ISS_RATE', $rate->toArray());
        if ($rate->missingFacts !== []) {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED, [$rate->reasonCode], $rate->missingFacts, $trace);
        }
        if ($rate->rate === null) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, [$rate->reasonCode], [], $trace);
        }
        $issTransition = null;
        $operationDate = $input->context->competenceDate->value();
        if ($this->issTransitionRateEngine !== null && $operationDate >= '2029-01-01' && $operationDate <= '2032-12-31') {
            $referenceDate = LocalDate::fromString('2028-12-31');
            $referenceContext = new FiscalDecisionContext($facts, $referenceDate, $input->context->knowledgeAt,
                $input->context->ruleBundleId, $input->context->catalogVersions);
            $frozen = $this->parameterProvider->resolve($location->location, new ServiceCode($service->ctribnacCode),
                $referenceDate, $referenceContext);
            $trace->add('ISS_2028_REFERENCE_PARAMETERS', $frozen->toArray());
            $issTransition = $this->issTransitionRateEngine->resolve($input->context->competenceDate,
                $input->context->knowledgeAt, $rate, $frozen);
            if ($issTransition['status'] !== 'RESOLVED') {
                return $this->nonFinalResult($input,
                    $issTransition['status'] === 'INVALID' ? DecisionStatus::INVALID : DecisionStatus::ACTION_REQUIRED,
                    [$issTransition['reason']], [], $trace);
            }
            $rate = $issTransition['rate'];
            $trace->add('ISS_TRANSITION_RATE', array_diff_key($issTransition, ['rate' => true]));
        }
        $this->completeNode($cursor, 'rate', $trace);

        $this->startNode($cursor, 'treatment', $trace);
        $treatmentCandidate = $this->singleDecisionCandidate($decisionCandidates, 'iss.treatment');
        if ($treatmentCandidate === null) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['RULESET_TREATMENT_CONFLICT'], [], $trace);
        }
        if (! is_string($treatmentCandidate['value']) || $treatmentCandidate['value'] !== 'STANDARD') {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['ISS_TREATMENT_UNSUPPORTED'], [], $trace);
        }
        $treatment = new IssTreatmentDecision($treatmentCandidate['value'], $treatmentCandidate['reason_code']);
        $this->completeNode($cursor, 'treatment', $trace);

        $this->startNode($cursor, 'calculation', $trace);
        $missingOperationFacts = $facts->missing(['operation.amount']);
        if ($missingOperationFacts !== []) {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED, ['OPERATION_FACTS_MISSING'], $missingOperationFacts, $trace);
        }
        $formulaCandidate = $this->singleDecisionCandidate($decisionCandidates, 'engine.formula');
        if ($formulaCandidate === null || ! is_array($formulaCandidate['value'])) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['RULESET_FORMULA_CONFLICT'], [], $trace);
        }
        $formulaId = $formulaCandidate['value']['formula'] ?? null;
        $formulaArguments = $formulaCandidate['value']['arguments'] ?? null;
        if (! is_string($formulaId) || ! is_array($formulaArguments)) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['RULESET_FORMULA_INVALID'], [], $trace);
        }
        $trace->add('RULE_EFFECTS_RESOLVED', [
            'location_strategy' => $resolvedStrategy->value,
            'formula' => $formulaId,
            'formula_arguments' => $formulaArguments,
            'incidence' => $incidence->toArray(),
            'treatment' => $treatment->toArray(),
            'detail_groups' => $detailGroups,
        ]);
        $issCalculationFacts = $facts;
        if ($this->issDocumentCurrency !== null) {
            $conversion = $this->issDocumentCurrency->qualify($facts, $input->context->competenceDate->value(),
                $input->context->knowledgeAt, $location->location->ibgeCode, $service->lc116Code);
            $trace->add('ISS_DOCUMENT_CURRENCY140', $conversion);
            if ($conversion['status'] === 'ACTION_REQUIRED') {
                return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED, [$conversion['reason']], $conversion['missing_facts'], $trace);
            }
            if ($conversion['status'] === 'LOCAL_ISS_DOCUMENT_PRICE_CONVERTED') {
                $issCalculationFacts = $facts->withReplaced(new FactValue('operation.amount', $conversion['converted_price_brl'],
                    FactSourceType::DERIVED, $input->context->competenceDate, null, $input->context->knowledgeAt));
            }
        }
        $calculation = $this->calculationEngine->calculate(
            $service->family,
            $issCalculationFacts,
            $rate->rate,
            $formulaId,
            array_map('strval', $formulaArguments),
        );
        $trace->add('ISS_CALCULATION', $calculation->toArray());
        if (! $calculation->valid) {
            return $this->nonFinalResult(
                $input,
                ($calculation->actionRequired || $calculation->missingFacts !== []) ? DecisionStatus::ACTION_REQUIRED : DecisionStatus::INVALID,
                $calculation->reasonCodes,
                $calculation->missingFacts,
                $trace,
            );
        }
        $this->completeNode($cursor, 'calculation', $trace);

        $this->startNode($cursor, 'federal_withholding', $trace);
        $federalWithholding = ['status' => 'NOT_REQUESTED'];
        $supportsFederalWithholding = $facts->hasDefinition('federal.evaluate_withholdings');
        if ($supportsFederalWithholding && ! $facts->has('federal.evaluate_withholdings')) {
            return $this->nonFinalResult(
                $input,
                DecisionStatus::ACTION_REQUIRED,
                ['FEDERAL_WITHHOLDING_EVALUATION_CHOICE_REQUIRED'],
                ['federal.evaluate_withholdings'],
                $trace,
            );
        }
        if ($facts->value('federal.evaluate_withholdings') === true) {
            $federalEvaluation = $this->federalWithholdingEngine->evaluate($facts);
            $federalWithholding = $federalEvaluation->toArray();
            $trace->add('FEDERAL_WITHHOLDINGS', $federalWithholding);
            if ($federalEvaluation->status === 'ACTION_REQUIRED') {
                return $this->nonFinalResult(
                    $input,
                    DecisionStatus::ACTION_REQUIRED,
                    $federalEvaluation->reasonCodes,
                    $federalEvaluation->missingFacts,
                    $trace,
                );
            }
        } else {
            if ($supportsFederalWithholding) {
                $federalWithholding = [
                    'status' => 'NOT_REQUESTED',
                    'irrf' => ['status' => 'NOT_REQUESTED'],
                    'pcc' => ['status' => 'NOT_REQUESTED'],
                    'inss' => ['status' => 'NOT_REQUESTED'],
                ];
            }
            $trace->add('FEDERAL_WITHHOLDINGS_SKIPPED', ['reason' => 'NOT_REQUESTED']);
        }
        $this->completeNode($cursor, 'federal_withholding', $trace);

        $consumptionReform = ['status' => 'NOT_REQUESTED'];
        if ($this->consumptionReformEngine !== null) {
            $this->startNode($cursor, 'consumption_reform', $trace);
        }
        if ($this->consumptionReformEngine !== null && ! $facts->has('rtc.evaluate')) {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED, ['RTC_EVALUATION_CHOICE_REQUIRED'], ['rtc.evaluate'], $trace);
        }
        if ($facts->value('rtc.evaluate') === true) {
            if ($this->consumptionReformEngine === null) {
                return $this->nonFinalResult($input, DecisionStatus::INVALID, ['RTC_BUNDLE_NOT_CONFIGURED'], [], $trace);
            }
            $consumptionReform = $this->consumptionReformEngine->evaluate($facts, $input->context->competenceDate, $input->context->knowledgeAt);
            $trace->add('CONSUMPTION_REFORM', $consumptionReform);
            if ($consumptionReform['status'] !== 'VALID') {
                return $this->nonFinalResult(
                    $input,
                    $consumptionReform['status'] === 'INVALID' ? DecisionStatus::INVALID : DecisionStatus::ACTION_REQUIRED,
                    $consumptionReform['reason_codes'],
                    $consumptionReform['missing_facts'],
                    $trace,
                );
            }
        } elseif ($this->consumptionReformEngine !== null) {
            $trace->add('CONSUMPTION_REFORM_SKIPPED', ['reason' => 'NOT_REQUESTED']);
        }
        if ($this->consumptionReformEngine !== null) {
            $this->completeNode($cursor, 'consumption_reform', $trace);
        }

        $this->startNode($cursor, 'projection', $trace);
        $projection = new DpsProjection($service, $location, $responsibility, $calculation, $facts,
            $this->consumptionReformEngine !== null ? $consumptionReform : null);
        $decision = [
            'classification' => $service->toArray(),
            'iss' => [
                'incidence' => $incidence->toArray(),
                'location' => $location->toArray(),
                'municipal_parameters' => $parameters->toArray(),
                'responsibility' => $responsibility->toArray(),
                'rate' => $rate->toArray(),
                'treatment' => $treatment->toArray(),
                'calculation' => $calculation->toArray(),
            ],
            'dps_projection' => $projection->toArray(),
            'federal_withholdings' => $federalWithholding,
            'versions' => [
                'rule_bundle_id' => $this->ruleBundleId,
                'catalogs' => $this->catalogVersions,
                'content_hashes' => $this->knowledgeHashes,
            ],
            'facts_used' => array_map(static fn ($fact): array => $fact->toArray(), $facts->all()),
        ];
        if ($this->consumptionReformEngine !== null) {
            $decision['consumption_reform'] = $consumptionReform;
        }
        if ($issTransition !== null) {
            $decision['iss']['transition_rate'] = array_diff_key($issTransition, ['rate' => true]);
        }
        $this->completeNode($cursor, 'projection', $trace);

        $this->startNode($cursor, 'invariants', $trace);
        $invariantResult = $this->invariantEngine->validate($decision, $context);
        $trace->add('INVARIANTS_EVALUATED', [
            'results' => array_map(static fn ($result): array => [
                'invariant_id' => $result->invariantId,
                'valid' => $result->valid,
                'severity' => $result->severity->value,
                'message' => $result->message,
            ], $invariantResult->results),
        ]);
        if ($invariantResult->hasBlockingFailure()) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['INVARIANT_BLOCKING_FAILURE'], [], $trace);
        }
        if ($invariantResult->requiresAction()) {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED, ['INVARIANT_ACTION_REQUIRED'], [], $trace);
        }
        foreach ($invariantResult->results as $invariant) {
            if (! $invariant->valid && $invariant->severity === InvariantSeverity::WARNING) {
                $warnings[] = 'INVARIANT_WARNING:'.$invariant->invariantId;
            }
        }
        $this->completeNode($cursor, 'invariants', $trace);
        if (! $cursor->isComplete()) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['EXECUTION_PLAN_INCOMPLETE'], [], $trace);
        }
        $warnings = array_values(array_unique($warnings));
        $status = $warnings === [] ? DecisionStatus::VALID : DecisionStatus::VALID_WITH_WARNINGS;
        if ($warnings !== []) {
            $decision['warnings'] = $warnings;
        }
        $trace->add('DECISION_COMPLETED', ['status' => $status->value, 'warnings' => $warnings]);

        return new FiscalEvaluationResult(
            $status,
            $input->scenarioId,
            $decision,
            [],
            ['ISS_DECISION_VALID', ...$warnings],
            $trace->toArray(),
        );
    }

    private function evaluateImmuneIss(FiscalDecisionInput $input, FactBag $facts, FiscalDecisionContext $context,
        CanonicalService $service, ExecutionPlanCursor $cursor, DecisionTrace $trace): FiscalEvaluationResult
    {
        $this->startNode($cursor, 'iss_immunity', $trace);
        $immunity = (new InstitutionalIssImmunityQualifier)->resolve($facts, $context->competenceDate,
            $context->knowledgeAt, $this->institutionalIssImmunity ?? []);
        $trace->add('ISS_INSTITUTIONAL_IMMUNITY', $immunity);
        if ($immunity['status'] !== 'RESOLVED') {
            return $this->nonFinalResult($input, $immunity['status'] === 'INVALID' ? DecisionStatus::INVALID : DecisionStatus::ACTION_REQUIRED,
                [$immunity['reason']], $immunity['missing_facts'], $trace);
        }
        if ($facts->value('rtc.evaluate') !== true || $this->consumptionReformEngine === null) {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED, ['IMMUNE_INTEGRATED_RTC_EVALUATION_REQUIRED'], ['rtc.evaluate'], $trace);
        }
        if (! $facts->has('operation.amount')) {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED, ['OPERATION_FACTS_MISSING'], ['operation.amount'], $trace);
        }
        $gross = DecimalValue::fromString((string) $facts->value('operation.amount'));
        if ($gross->isNegative()) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['ISS_NEGATIVE_GROSS_AMOUNT'], [], $trace);
        }
        if ($facts->value('operation.withholding_performed') === true) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['ISS_IMMUNITY_WITHHOLDING_CONFLICT'], [], $trace);
        }
        $this->completeNode($cursor, 'iss_immunity', $trace);
        $this->startNode($cursor, 'location', $trace);
        $location = $this->locationEngine->resolve($service->locationStrategy, $facts, $service->ctribnacCode);
        if ($location->location === null) {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED, [$location->reasonCode], $location->missingFacts, $trace);
        }
        $this->completeNode($cursor, 'location', $trace);
        $this->startNode($cursor, 'federal_withholding', $trace);
        if (! $facts->has('federal.evaluate_withholdings')) {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED, ['FEDERAL_WITHHOLDING_EVALUATION_CHOICE_REQUIRED'], ['federal.evaluate_withholdings'], $trace);
        }
        if ($facts->value('federal.evaluate_withholdings') === true) {
            $evaluation = $this->federalWithholdingEngine->evaluate($facts);
            $federal = $evaluation->toArray();
            $trace->add('FEDERAL_WITHHOLDINGS', $federal);
            if ($evaluation->status === 'ACTION_REQUIRED') {
                return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED, $evaluation->reasonCodes, $evaluation->missingFacts, $trace);
            }
        } else {
            $federal = ['status' => 'NOT_REQUESTED', 'irrf' => ['status' => 'NOT_REQUESTED'],
                'pcc' => ['status' => 'NOT_REQUESTED'], 'inss' => ['status' => 'NOT_REQUESTED']];
            $trace->add('FEDERAL_WITHHOLDINGS_SKIPPED', ['reason' => 'NOT_REQUESTED']);
        }
        $this->completeNode($cursor, 'federal_withholding', $trace);
        $this->startNode($cursor, 'consumption_reform', $trace);
        $rtc = $this->consumptionReformEngine->evaluate($facts, $context->competenceDate, $context->knowledgeAt);
        $trace->add('CONSUMPTION_REFORM', $rtc);
        if ($rtc['status'] !== 'VALID') {
            return $this->nonFinalResult($input, $rtc['status'] === 'INVALID' ? DecisionStatus::INVALID : DecisionStatus::ACTION_REQUIRED,
                $rtc['reason_codes'], $rtc['missing_facts'], $trace);
        }
        $this->completeNode($cursor, 'consumption_reform', $trace);
        $this->startNode($cursor, 'projection', $trace);
        $document = $rtc['decision']['document'];
        $projection = ['projection_scope' => 'LOCAL_NO_SIGNATURE_OR_TRANSMISSION',
            'service' => ['cTribNac' => $service->ctribnacCode, 'xDescServ' => $service->description, 'detail' => []],
            'values' => ['vServ' => $gross->rounded(2)->value()],
            'iss' => ['tribISSQN' => $immunity['tribISSQN'], 'tpImunidade' => $immunity['tpImunidade'],
                'tpRetISSQN' => '1', 'pAliq' => null],
            'expected_sefin_enrichment' => [],
            'rtc' => ['schema_version' => $document['schema_version'], 'status' => 'VALID',
                'issuer_supplied' => $document['issuer_fields'], 'expected_national_enrichment' => $document['national_enrichment']]];
        if (isset($document['service_nbs'])) {
            $projection['service']['cNBS'] = $document['service_nbs'];
        }
        $decision = ['classification' => $service->toArray(),
            'iss' => ['incidence' => ['value' => 'IMMUNE', 'reason_code' => 'ISS_INSTITUTIONAL_IMMUNITY'],
                'treatment' => ['value' => 'IMMUNE', 'reason_code' => 'ISS_INSTITUTIONAL_IMMUNITY'],
                'immunity_qualification' => $immunity, 'location' => $location->toArray(),
                'municipal_parameters' => ['status' => 'NOT_APPLICABLE', 'reason_code' => 'ISS_IMMUNITY_NO_RATE_REQUIRED'],
                'responsibility' => ['responsible_party' => 'NOT_APPLICABLE', 'withholding_required' => false,
                    'withholding_performed' => false, 'tpRetISSQN' => '1'],
                'rate' => ['rate' => null, 'applicability' => 'NOT_APPLICABLE_IMMUNITY'],
                'calculation' => ['valid' => true, 'gross_amount' => $gross->rounded(2)->value(), 'deduction_amount' => '0.00',
                    'taxable_base' => '0.00', 'rate' => null, 'tax_amount' => '0.00', 'currency' => 'BRL']],
            'consumption_reform' => $rtc, 'federal_withholdings' => $federal, 'dps_projection' => $projection,
            'versions' => ['rule_bundle_id' => $this->ruleBundleId, 'catalogs' => $this->catalogVersions, 'content_hashes' => $this->knowledgeHashes],
            'facts_used' => array_map(static fn ($fact): array => $fact->toArray(), $facts->all())];
        $this->completeNode($cursor, 'projection', $trace);
        $this->startNode($cursor, 'invariants', $trace);
        $invariants = $this->invariantEngine->validate($decision, $context);
        $trace->add('INVARIANTS_EVALUATED', ['results' => array_map(static fn ($result): array => [
            'invariant_id' => $result->invariantId, 'valid' => $result->valid,
            'severity' => $result->severity->value, 'message' => $result->message], $invariants->results)]);
        if ($invariants->hasBlockingFailure() || $invariants->requiresAction()) {
            return $this->nonFinalResult($input, $invariants->hasBlockingFailure() ? DecisionStatus::INVALID : DecisionStatus::ACTION_REQUIRED,
                [$invariants->hasBlockingFailure() ? 'INVARIANT_BLOCKING_FAILURE' : 'INVARIANT_ACTION_REQUIRED'], [], $trace);
        }
        $this->completeNode($cursor, 'invariants', $trace);
        if (! $cursor->isComplete()) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['EXECUTION_PLAN_INCOMPLETE'], [], $trace);
        }
        $warnings = $this->bundleStatus === 'APPROVED' ? [] : ['RULE_BUNDLE_NOT_PRODUCTION_APPROVED'];
        $status = $warnings === [] ? DecisionStatus::VALID : DecisionStatus::VALID_WITH_WARNINGS;
        if ($warnings !== []) {
            $decision['warnings'] = $warnings;
        }
        $trace->add('DECISION_COMPLETED', ['status' => $status->value, 'warnings' => $warnings]);

        return new FiscalEvaluationResult($status, $input->scenarioId, $decision, [], ['ISS_IMMUNE_DECISION_VALID', ...$warnings], $trace->toArray());
    }

    private function evaluatePostIss(FiscalDecisionInput $input, FactBag $facts, FiscalDecisionContext $context,
        CanonicalService $service, ExecutionPlanCursor $cursor, DecisionTrace $trace): FiscalEvaluationResult
    {
        $date = $input->context->competenceDate->value();
        $operationDate = $facts->value('rtc.operation_date');
        if (! is_string($operationDate) || $operationDate < '2033-01-01') {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED,
                ['RTC_RELEVANT_DATE_PRE_2033_LEGACY_REVIEW_REQUIRED'],
                $operationDate === null ? ['rtc.operation_date'] : [], $trace);
        }
        if (! $facts->has('rtc.legacy_event_spans_transition')) {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED,
                ['RTC_LEGACY_EVENT_TIMING_UNPROVEN'], ['rtc.legacy_event_spans_transition'], $trace);
        }
        if ($facts->value('rtc.legacy_event_spans_transition') === true) {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED,
                ['RTC_CROSS_YEAR_LEGACY_TAX_RULE_NOT_MATERIALIZED'], [], $trace);
        }
        if ($facts->value('rtc.evaluate') !== true) {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED,
                ['RTC_EVALUATION_REQUIRED_AFTER_ISS_EXTINCTION'], $facts->has('rtc.evaluate') ? [] : ['rtc.evaluate'], $trace);
        }
        if ($this->consumptionReformEngine === null) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['RTC_BUNDLE_NOT_CONFIGURED'], [], $trace);
        }
        $trace->add('POST_ISS_TRANSITION', ['competence_date' => $date, 'operation_date' => $operationDate,
            'iss_status' => 'EXTINCT', 'source_article' => 'LC214 art. 408, §4º; EC132 ADCT art. 130',
            'source_id' => 'LC214-CONSOLIDATED-CAMARA-2026']);
        $this->startNode($cursor, 'consumption_reform', $trace);
        $consumptionReform = $this->consumptionReformEngine->evaluate($facts,
            $input->context->competenceDate, $input->context->knowledgeAt);
        $trace->add('CONSUMPTION_REFORM', $consumptionReform);
        if ($consumptionReform['status'] !== 'VALID') {
            return $this->nonFinalResult($input,
                $consumptionReform['status'] === 'INVALID' ? DecisionStatus::INVALID : DecisionStatus::ACTION_REQUIRED,
                $consumptionReform['reason_codes'], $consumptionReform['missing_facts'], $trace);
        }
        if (($consumptionReform['decision']['legacy_taxes']['iss_status'] ?? null) !== 'EXTINCT') {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['RTC_POST_ISS_TRANSITION_CONFLICT'], [], $trace);
        }
        $this->completeNode($cursor, 'consumption_reform', $trace);

        $this->startNode($cursor, 'federal_withholding', $trace);
        if (! $facts->has('federal.evaluate_withholdings')) {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED,
                ['FEDERAL_WITHHOLDING_EVALUATION_CHOICE_REQUIRED'], ['federal.evaluate_withholdings'], $trace);
        }
        if ($facts->value('federal.evaluate_withholdings') === true) {
            if (! $this->postIssFederalWithholdings) {
                return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED,
                    ['FEDERAL_POST_2032_WITHHOLDING_PROFILE_NOT_MATERIALIZED'], [], $trace);
            }
            $paymentDate = $facts->value('payment.date');
            if ($facts->value('payment.occurred') === true
                && (! is_string($paymentDate) || $paymentDate < '2033-01-01')) {
                return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED,
                    ['FEDERAL_POST_2032_PAYMENT_DATE_UNPROVEN'],
                    $paymentDate === null ? ['payment.date'] : [], $trace);
            }
            $creditDate = $facts->value('payment.accounting_credit_date');
            if (is_string($creditDate) && $creditDate < '2033-01-01') {
                return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED,
                    ['FEDERAL_POST_2032_CREDIT_DATE_REQUIRES_LEGACY_REVIEW'], [], $trace);
            }
            $trace->add('FEDERAL_POST_ISS_TEMPORAL_PROFILE', [
                'payment_date' => $paymentDate,
                'accounting_credit_date' => $creditDate,
                'pis_cofins_status' => 'EXTINCT_FOR_CONSUMPTION',
                'source_id' => 'LC214-CONSOLIDATED-CAMARA-2026',
            ]);
            $federalEvaluation = $this->federalWithholdingEngine->evaluate($facts);
            $federalWithholdings = $federalEvaluation->toArray();
            $trace->add('FEDERAL_WITHHOLDINGS', $federalWithholdings);
            if ($facts->value('payment.occurred') === true) {
                foreach ([$federalEvaluation->pcc->decisions, $federalEvaluation->additionalDecisions] as $decisions) {
                    foreach ($decisions as $decision) {
                        if (in_array($decision->tax->value, ['PIS', 'COFINS'], true)
                            && ! in_array($decision->status->value, ['NOT_APPLICABLE', 'ACTION_REQUIRED'], true)) {
                            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED,
                                ['FEDERAL_POST_2026_LEGACY_PIS_COFINS_PROFILE_CONFLICT'], [], $trace);
                        }
                    }
                }
            }
            if ($federalEvaluation->status === 'ACTION_REQUIRED') {
                return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED,
                    $federalEvaluation->reasonCodes, $federalEvaluation->missingFacts, $trace);
            }
            $this->completeNode($cursor, 'federal_withholding', $trace);
        } else {
            $federalWithholdings = ['status' => 'NOT_REQUESTED', 'irrf' => ['status' => 'NOT_REQUESTED'],
                'pcc' => ['status' => 'NOT_REQUESTED'], 'inss' => ['status' => 'NOT_REQUESTED']];
            $trace->add('FEDERAL_WITHHOLDINGS_SKIPPED', ['reason' => 'NOT_REQUESTED']);
            $this->completeNode($cursor, 'federal_withholding', $trace);
        }

        $this->startNode($cursor, 'projection', $trace);
        $document = $consumptionReform['decision']['document'] ?? null;
        if (! is_array($document) || ! isset($document['schema_version'], $document['issuer_fields'], $document['national_enrichment'])) {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED,
                ['RTC_POST_ISS_DOCUMENT_PROJECTION_NOT_PROVEN'], [], $trace);
        }
        $decision = [
            'classification' => $service->toArray(),
            'iss' => ['status' => 'NOT_APPLICABLE', 'reason_code' => 'ISS_EXTINCT_2033'],
            'consumption_reform' => $consumptionReform,
            'federal_withholdings' => $federalWithholdings,
            'dps_projection' => [
                'projection_scope' => 'LOCAL_NO_SIGNATURE_OR_TRANSMISSION',
                'service' => ['cTribNac' => $service->ctribnacCode, 'xDescServ' => $service->description],
                'values' => ['vServ' => $consumptionReform['decision']['base']['gross'] ?? null],
                'iss' => ['status' => 'NOT_APPLICABLE'],
                'rtc' => ['schema_version' => $document['schema_version'], 'status' => 'VALID',
                    'issuer_supplied' => $document['issuer_fields'],
                    'expected_national_enrichment' => $document['national_enrichment']],
            ],
            'versions' => ['rule_bundle_id' => $this->ruleBundleId,
                'catalogs' => $this->catalogVersions, 'content_hashes' => $this->knowledgeHashes],
            'facts_used' => array_map(static fn ($fact): array => $fact->toArray(), $facts->all()),
        ];
        $this->completeNode($cursor, 'projection', $trace);

        $this->startNode($cursor, 'invariants', $trace);
        $invariantResult = $this->invariantEngine->validate($decision, $context);
        $trace->add('INVARIANTS_EVALUATED', ['results' => array_map(static fn ($result): array => [
            'invariant_id' => $result->invariantId, 'valid' => $result->valid,
            'severity' => $result->severity->value, 'message' => $result->message,
        ], $invariantResult->results)]);
        if ($invariantResult->hasBlockingFailure()) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['INVARIANT_BLOCKING_FAILURE'], [], $trace);
        }
        if ($invariantResult->requiresAction()) {
            return $this->nonFinalResult($input, DecisionStatus::ACTION_REQUIRED, ['INVARIANT_ACTION_REQUIRED'], [], $trace);
        }
        $this->completeNode($cursor, 'invariants', $trace);
        if (! $cursor->isComplete()) {
            return $this->nonFinalResult($input, DecisionStatus::INVALID, ['EXECUTION_PLAN_INCOMPLETE'], [], $trace);
        }
        $warnings = $this->bundleStatus === 'APPROVED' ? [] : ['RULE_BUNDLE_NOT_PRODUCTION_APPROVED'];
        $status = $warnings === [] ? DecisionStatus::VALID : DecisionStatus::VALID_WITH_WARNINGS;
        if ($warnings !== []) {
            $decision['warnings'] = $warnings;
        }
        $trace->add('DECISION_COMPLETED', ['status' => $status->value, 'warnings' => $warnings]);

        return new FiscalEvaluationResult($status, $input->scenarioId, $decision, [],
            ['POST_ISS_INTEGRATED_DECISION_VALID', ...$warnings], $trace->toArray());
    }

    private function startNode(ExecutionPlanCursor $cursor, string $nodeId, DecisionTrace $trace): void
    {
        $cursor->start($nodeId);
        $trace->add('DECISION_NODE_STARTED', ['node_id' => $nodeId]);
    }

    private function completeNode(ExecutionPlanCursor $cursor, string $nodeId, DecisionTrace $trace): void
    {
        $cursor->complete($nodeId);
        $trace->add('DECISION_NODE_COMPLETED', ['node_id' => $nodeId]);
    }

    /**
     * @param  list<string>  $reasonCodes
     * @param  list<string>  $missingFacts
     */
    private function nonFinalResult(
        FiscalDecisionInput $input,
        DecisionStatus $status,
        array $reasonCodes,
        array $missingFacts,
        DecisionTrace $trace,
    ): FiscalEvaluationResult {
        $questionPlan = $this->questionPlanner->plan($missingFacts);
        $questions = array_map(
            static fn ($question): array => [
                'fact_key' => $question->factKey,
                'prompt' => $question->prompt,
                'rank' => $question->rank,
                'answer_type' => $question->answerType,
                'options' => $question->options,
            ],
            $questionPlan->questions,
        );
        foreach ($questionPlan->unmappedFacts as $unmapped) {
            $reasonCodes[] = "QUESTION_UNMAPPED:{$unmapped}";
        }
        $trace->add('DECISION_STOPPED', ['status' => $status->value, 'reason_codes' => $reasonCodes]);

        return new FiscalEvaluationResult(
            $status,
            $input->scenarioId,
            [],
            $questions,
            $reasonCodes,
            $trace->toArray(),
            array_values(array_unique($missingFacts)),
        );
    }

    /**
     * @param  array<string, list<array{rule_id: string, value: mixed, reason_code: string}>>  $candidates
     * @return array{rule_id: string, value: mixed, reason_code: string}|null
     */
    private function singleDecisionCandidate(array $candidates, string $path): ?array
    {
        $matches = $candidates[$path] ?? [];
        $relations = [];
        foreach ($this->rules as $rule) {
            array_push($relations, ...$rule->relations);
        }
        $result = $this->precedenceResolver->resolve(
            array_map(
                static fn (array $candidate): CandidateDecision => new CandidateDecision($candidate['rule_id'], $path, $candidate['value']),
                $matches,
            ),
            $relations,
        );
        if ($result->status !== PrecedenceStatus::RESOLVED || $result->decision === null) {
            return null;
        }
        foreach ($matches as $candidate) {
            if ($candidate['rule_id'] === $result->decision->ruleId) {
                return $candidate;
            }
        }

        return null;
    }
}
