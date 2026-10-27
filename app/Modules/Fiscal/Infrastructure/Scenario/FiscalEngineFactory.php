<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Scenario;

use App\Modules\Fiscal\Classification\CanonicalServiceCatalog;
use App\Modules\Fiscal\Classification\CanonicalServiceClassifier;
use App\Modules\Fiscal\Classification\CanonicalServiceFactResolver;
use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Core\Engine\DefaultFiscalDecisionEngine;
use App\Modules\Fiscal\Core\Formula\FormulaRegistry;
use App\Modules\Fiscal\Core\Formula\PercentageOfBaseFormula;
use App\Modules\Fiscal\Core\Invariants\FiscalInvariantEngine;
use App\Modules\Fiscal\Core\Precedence\RulePrecedenceResolver;
use App\Modules\Fiscal\Core\Questions\QuestionPlanner;
use App\Modules\Fiscal\Core\Rules\ConditionEvaluator;
use App\Modules\Fiscal\Core\Rules\InMemoryCatalogMembership;
use App\Modules\Fiscal\Core\Rules\RuleEvaluator;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\FederalWithholding\FederalBankingCalendar;
use App\Modules\Fiscal\FederalWithholding\FederalObligationProjector;
use App\Modules\Fiscal\FederalWithholding\FederalWithholdingEngine;
use App\Modules\Fiscal\FederalWithholding\InvoiceGrossAmountFactResolver;
use App\Modules\Fiscal\FederalWithholding\PaymentGrossAmountFactResolver;
use App\Modules\Fiscal\FederalWithholding\PublicAdministrationRuleSet;
use App\Modules\Fiscal\FederalWithholding\PublicAnnexCatalog;
use App\Modules\Fiscal\Infrastructure\Files\CompiledRuleFileLoader;
use App\Modules\Fiscal\Infrastructure\Files\FactDefinitionFileLoader;
use App\Modules\Fiscal\Infrastructure\Files\InvariantDefinitionFileLoader;
use App\Modules\Fiscal\Infrastructure\Files\QuestionDefinitionFileLoader;
use App\Modules\Fiscal\Infrastructure\MunicipalParameters\JsonMunicipalParameterProvider;
use App\Modules\Fiscal\Infrastructure\Schema\JsonSchemaValidator;
use App\Modules\Fiscal\ISS\IssCalculationEngine;
use App\Modules\Fiscal\ISS\IssDocumentCurrency140;
use App\Modules\Fiscal\ISS\IssLocationEngine;
use App\Modules\Fiscal\ISS\IssRateEngine;
use App\Modules\Fiscal\ISS\IssResponsibilityEngine;
use App\Modules\Fiscal\ISS\IssTransitionRateEngine;
use App\Modules\Fiscal\ISS\MunicipalParameterProvider;
use DateTimeImmutable;
use JsonException;
use RuntimeException;

final class FiscalEngineFactory
{
    public function make(string $resourceRoot, ?string $bundleManifestPath = null): DefaultFiscalDecisionEngine
    {
        return $this->makeFromKnowledge($this->captureKnowledge($resourceRoot, $bundleManifestPath));
    }

    /** @return array<string, mixed> */
    public function captureKnowledge(string $resourceRoot, ?string $bundleManifestPath = null): array
    {
        $manifestPath = $this->resolveResourcePath(
            $resourceRoot,
            $bundleManifestPath ?? 'bundles/mvp-iss-2026.09.16-1.json',
        );
        $manifest = $this->decode($manifestPath);
        $resourcePaths = $this->resourcePaths($manifest);

        $schemaValidator = new JsonSchemaValidator;
        foreach (['fact-definitions' => 'fact_definitions', 'questions' => 'questions', 'invariants' => 'invariants'] as $schema => $documentKey) {
            $document = $resourcePaths[$documentKey];
            $validation = $schemaValidator->validateFile(
                $this->resolveResourcePath($resourceRoot, $document),
                $this->resolveResourcePath($resourceRoot, 'schemas/'.$schema.'.schema.json'),
            );
            if (! $validation->valid) {
                throw new RuntimeException("Knowledge schema validation failed for {$document}.");
            }
        }
        $rulesPath = $this->resolveResourcePath($resourceRoot, $resourcePaths['rules']);
        $rulePaths = is_file($rulesPath) ? [$rulesPath] : (glob($rulesPath.DIRECTORY_SEPARATOR.'*.json') ?: []);
        sort($rulePaths, SORT_STRING);
        if ($rulePaths === []) {
            throw new RuntimeException("Knowledge rules not found: {$rulesPath}");
        }
        $rules = [];
        foreach ($rulePaths as $rulePath) {
            $rule = $this->decode($rulePath);
            $rules[] = $rule;
        }
        usort($rules, static fn (array $left, array $right): int => [
            (string) ($left['rule_id'] ?? ''),
            (string) ($left['version'] ?? ''),
        ] <=> [
            (string) ($right['rule_id'] ?? ''),
            (string) ($right['version'] ?? ''),
        ]);
        $documents = [
            'bundle_manifest' => $manifest,
            'fact_definitions' => $this->decode($this->resolveResourcePath($resourceRoot, $resourcePaths['fact_definitions'])),
            'questions' => $this->questionDocuments($resourceRoot, $resourcePaths['questions'], $manifest),
            'service_catalog' => $this->decode($this->resolveResourcePath($resourceRoot, $resourcePaths['service_catalog'])),
            'municipal_parameters' => $this->decode($this->resolveResourcePath($resourceRoot, $resourcePaths['municipal_parameters'])),
            'invariants' => $this->decode($this->resolveResourcePath($resourceRoot, $resourcePaths['invariants'])),
            'scenario_schema' => $this->decode($this->resolveResourcePath($resourceRoot, $resourcePaths['scenario_schema'])),
            'rules' => $rules,
        ];
        if (isset($resourcePaths['public_annex_catalog'])) {
            $documents['public_annex_catalog'] = $this->decode($this->resolveResourcePath($resourceRoot, $resourcePaths['public_annex_catalog']));
        }
        if (isset($resourcePaths['iss_currency_catalog'])) {
            $documents['iss_currency_catalog'] = $this->decode($this->resolveResourcePath($resourceRoot, $resourcePaths['iss_currency_catalog']));
            foreach (IssDocumentCurrency140::policy()['sources'] as $file => $hash) {
                if (hash_file('sha256', $this->resolveResourcePath($resourceRoot, 'sources/official/'.$file)) !== $hash) {
                    throw new RuntimeException('ISS currency primary source hash mismatch.');
                }
            }
        }
        if (isset($resourcePaths['federal_obligation_catalog'])) {
            $documents['federal_obligation_catalog'] = $this->decode($this->resolveResourcePath($resourceRoot, $resourcePaths['federal_obligation_catalog']));
        }
        if (isset($resourcePaths['federal_banking_calendar'])) {
            $documents['federal_banking_calendar'] = $this->decode($this->resolveResourcePath($resourceRoot, $resourcePaths['federal_banking_calendar']));
        }
        foreach (['rtc_rate_catalog', 'rtc_code_catalog', 'rtc_transition_catalog'] as $key) {
            if (isset($resourcePaths[$key])) {
                $documents[$key] = $this->decode($this->resolveResourcePath($resourceRoot, $resourcePaths[$key]));
            }
        }
        $knowledgeHashes = [
            'bundle_manifest' => CanonicalJson::hash($documents['bundle_manifest']),
            'fact_definitions' => CanonicalJson::hash($documents['fact_definitions']),
            'questions' => CanonicalJson::hash($documents['questions']),
            'service_catalog' => CanonicalJson::hash($documents['service_catalog']),
            'municipal_parameters' => CanonicalJson::hash($documents['municipal_parameters']),
            'invariants' => CanonicalJson::hash($documents['invariants']),
            'scenario_schema' => CanonicalJson::hash($documents['scenario_schema']),
            'rules' => CanonicalJson::hash($documents['rules']),
        ];
        if (isset($documents['public_annex_catalog'])) {
            $knowledgeHashes['public_annex_catalog'] = CanonicalJson::hash($documents['public_annex_catalog']);
        }
        if (isset($documents['federal_obligation_catalog'])) {
            $knowledgeHashes['federal_obligation_catalog'] = CanonicalJson::hash($documents['federal_obligation_catalog']);
        }
        if (isset($documents['federal_banking_calendar'])) {
            $knowledgeHashes['federal_banking_calendar'] = CanonicalJson::hash($documents['federal_banking_calendar']);
        }
        foreach (['rtc_rate_catalog', 'rtc_code_catalog', 'rtc_transition_catalog'] as $key) {
            if (isset($documents[$key])) {
                $knowledgeHashes[$key] = CanonicalJson::hash($documents[$key]);
            }
        }

        $bundleId = (string) ($documents['bundle_manifest']['bundle_id'] ?? '');
        if (isset($documents['iss_currency_catalog'])) {
            $knowledgeHashes['iss_currency_catalog'] = CanonicalJson::hash($documents['iss_currency_catalog']);
        }
        $catalogVersions = $documents['bundle_manifest']['catalog_versions'] ?? null;
        if ($bundleId === '' || ! is_array($catalogVersions)) {
            throw new RuntimeException('Bundle manifest is invalid.');
        }
        $bundleStatus = $documents['bundle_manifest']['status'] ?? null;
        $knowledgeAvailableAt = $documents['bundle_manifest']['knowledge_available_at'] ?? null;
        if (! is_string($bundleStatus) || $bundleStatus === '' || ! is_string($knowledgeAvailableAt) || $knowledgeAvailableAt === '') {
            throw new RuntimeException('Bundle manifest status or knowledge availability is invalid.');
        }
        try {
            new DateTimeImmutable($knowledgeAvailableAt);
        } catch (\Exception $exception) {
            throw new RuntimeException('Bundle manifest knowledge availability is invalid.', previous: $exception);
        }
        if (($documents['service_catalog']['version'] ?? null) !== ($catalogVersions['services'] ?? null)
            || ($documents['municipal_parameters']['version'] ?? null) !== ($catalogVersions['municipal_parameters'] ?? null)) {
            throw new RuntimeException('Bundle manifest catalog versions do not match the documents.');
        }
        if (isset($documents['public_annex_catalog'])
            && ($documents['public_annex_catalog']['version'] ?? null) !== ($catalogVersions['public_annex_i'] ?? null)) {
            throw new RuntimeException('Public Annex I catalog version does not match the bundle manifest.');
        }
        if (isset($documents['federal_obligation_catalog'])
            && ($documents['federal_obligation_catalog']['version'] ?? null) !== ($catalogVersions['federal_obligations'] ?? null)) {
            throw new RuntimeException('Federal obligation catalog version does not match the bundle manifest.');
        }
        foreach (['rtc_rate_catalog' => 'rtc_rates', 'rtc_code_catalog' => 'rtc_codes', 'rtc_transition_catalog' => 'rtc_transition'] as $documentKey => $versionKey) {
            if (isset($documents[$documentKey]) && ($documents[$documentKey]['version'] ?? null) !== ($catalogVersions[$versionKey] ?? null)) {
                throw new RuntimeException("RTC catalog version mismatch: {$versionKey}");
            }
        }
        $this->assertManifestContentHashes($documents['bundle_manifest'], $documents, $knowledgeHashes);

        return [
            'format_version' => '1',
            'engine_version' => (string) ($documents['bundle_manifest']['engine_version'] ?? '1.0.0-mvp'),
            'rule_bundle_id' => $bundleId,
            'catalog_versions' => array_map('strval', $catalogVersions),
            'content_hashes' => $knowledgeHashes,
            'bundle_status' => $bundleStatus,
            'knowledge_available_at' => $knowledgeAvailableAt,
            'documents' => $documents,
        ];
    }

    /** @param array<string, mixed> $knowledge */
    public function makeFromKnowledge(array $knowledge, ?MunicipalParameterProvider $municipalProvider = null): DefaultFiscalDecisionEngine
    {
        $documents = $knowledge['documents'] ?? null;
        $hashes = $knowledge['content_hashes'] ?? null;
        $catalogVersions = $knowledge['catalog_versions'] ?? null;
        if (! is_array($documents) || ! is_array($hashes) || ! is_array($catalogVersions)) {
            throw new RuntimeException('Knowledge bundle structure is invalid.');
        }
        foreach (['bundle_manifest', 'fact_definitions', 'questions', 'service_catalog', 'municipal_parameters', 'invariants', 'scenario_schema', 'rules'] as $key) {
            if (! isset($documents[$key]) || ! is_array($documents[$key])) {
                throw new RuntimeException("Knowledge document is missing: {$key}");
            }
            $actual = CanonicalJson::hash($documents[$key]);
            if (! isset($hashes[$key]) || ! is_string($hashes[$key]) || ! hash_equals($hashes[$key], $actual)) {
                throw new RuntimeException("Knowledge document hash mismatch: {$key}");
            }
        }
        if (isset($documents['public_annex_catalog'])) {
            $actual = CanonicalJson::hash($documents['public_annex_catalog']);
            if (($hashes['public_annex_catalog'] ?? null) !== $actual) {
                throw new RuntimeException('Knowledge document hash mismatch: public_annex_catalog');
            }
        }
        if (isset($documents['iss_currency_catalog'])
            && ($hashes['iss_currency_catalog'] ?? null) !== CanonicalJson::hash($documents['iss_currency_catalog'])) {
            throw new RuntimeException('Knowledge document hash mismatch: iss_currency_catalog');
        }
        if (isset($documents['federal_obligation_catalog'])) {
            $actual = CanonicalJson::hash($documents['federal_obligation_catalog']);
            if (($hashes['federal_obligation_catalog'] ?? null) !== $actual) {
                throw new RuntimeException('Knowledge document hash mismatch: federal_obligation_catalog');
            }
        }
        if (isset($documents['federal_banking_calendar'])) {
            $actual = CanonicalJson::hash($documents['federal_banking_calendar']);
            if (($hashes['federal_banking_calendar'] ?? null) !== $actual) {
                throw new RuntimeException('Knowledge document hash mismatch: federal_banking_calendar');
            }
        }
        foreach (['rtc_rate_catalog', 'rtc_code_catalog', 'rtc_transition_catalog'] as $key) {
            if (isset($documents[$key]) && ($hashes[$key] ?? null) !== CanonicalJson::hash($documents[$key])) {
                throw new RuntimeException("Knowledge document hash mismatch: {$key}");
            }
        }
        $manifest = $documents['bundle_manifest'];
        if (($knowledge['rule_bundle_id'] ?? null) !== ($manifest['bundle_id'] ?? null)) {
            throw new RuntimeException('Knowledge rule bundle id does not match its manifest.');
        }
        $manifestCatalogVersions = $manifest['catalog_versions'] ?? null;
        if (! is_array($manifestCatalogVersions) || CanonicalJson::encode(array_map('strval', $catalogVersions)) !== CanonicalJson::encode(array_map('strval', $manifestCatalogVersions))) {
            throw new RuntimeException('Knowledge catalog versions do not match the bundle manifest.');
        }
        if (($documents['service_catalog']['version'] ?? null) !== ($catalogVersions['services'] ?? null)
            || ($documents['municipal_parameters']['version'] ?? null) !== ($catalogVersions['municipal_parameters'] ?? null)) {
            throw new RuntimeException('Knowledge catalog documents do not match the selected versions.');
        }
        if (isset($documents['public_annex_catalog'])
            && ($documents['public_annex_catalog']['version'] ?? null) !== ($catalogVersions['public_annex_i'] ?? null)) {
            throw new RuntimeException('Knowledge public Annex I catalog does not match the selected version.');
        }
        if (isset($documents['federal_obligation_catalog'])
            && ($documents['federal_obligation_catalog']['version'] ?? null) !== ($catalogVersions['federal_obligations'] ?? null)) {
            throw new RuntimeException('Knowledge federal obligation catalog does not match the selected version.');
        }
        if (isset($documents['federal_banking_calendar'])
            && ($documents['federal_banking_calendar']['version'] ?? null) !== ($catalogVersions['federal_banking_calendar'] ?? null)) {
            throw new RuntimeException('Knowledge banking calendar does not match the selected version.');
        }
        foreach (['rtc_rate_catalog' => 'rtc_rates', 'rtc_code_catalog' => 'rtc_codes', 'rtc_transition_catalog' => 'rtc_transition'] as $documentKey => $versionKey) {
            if (isset($documents[$documentKey]) && ($documents[$documentKey]['version'] ?? null) !== ($catalogVersions[$versionKey] ?? null)) {
                throw new RuntimeException("RTC catalog version mismatch: {$versionKey}");
            }
        }
        $this->assertManifestContentHashes($manifest, $documents, $hashes);

        $facts = (new FactDefinitionFileLoader)->fromArray($documents['fact_definitions']);
        $questions = (new QuestionDefinitionFileLoader)->fromArray($documents['questions'], $facts);
        $services = CanonicalServiceCatalog::fromArray($documents['service_catalog']);
        $parameters = $municipalProvider ?? JsonMunicipalParameterProvider::fromArray($documents['municipal_parameters']);
        $rules = (new CompiledRuleFileLoader)->fromPayloads($this->rulePayloads($documents['rules']), $facts);
        $invariants = (new InvariantDefinitionFileLoader)->fromArray($documents['invariants']);
        $bundleStatus = $manifest['status'] ?? null;
        $knowledgeAvailableAt = $manifest['knowledge_available_at'] ?? null;
        if (! is_string($bundleStatus) || $bundleStatus === '' || ! is_string($knowledgeAvailableAt) || $knowledgeAvailableAt === '') {
            throw new RuntimeException('Knowledge bundle manifest metadata is invalid.');
        }

        $publicAnnex = $documents['public_annex_catalog'] ?? null;
        $issCurrencyCatalog = $documents['iss_currency_catalog'] ?? null;
        $selectIssCurrency = match ($manifest['iss_currency_contract'] ?? null) {
            null => false,
            IssDocumentCurrency140::CONTRACT => true,
            default => throw new RuntimeException('Unknown ISS currency contract.'),
        };
        if ($selectIssCurrency !== is_array($issCurrencyCatalog)
            || ($selectIssCurrency && (($issCurrencyCatalog['version'] ?? null) !== ($catalogVersions['iss_currency'] ?? null)
                || ($manifest['content_hashes']['iss_currency_catalog'] ?? null) !== ($hashes['iss_currency_catalog'] ?? null)))) {
            throw new RuntimeException('Exact selected ISS currency catalog version and hash required.');
        }
        $strictPublicPayment = match ($manifest['public_payment_contract'] ?? null) {
            null => false,
            PublicAdministrationRuleSet::DOCUMENT_PAYMENT_CONTRACT => true,
            default => throw new RuntimeException('Unknown public document payment contract.'),
        };
        if ($strictPublicPayment && ! is_array($publicAnnex)) {
            throw new RuntimeException('Public document payment contract requires the public Annex I catalog.');
        }
        $publicRuleSet = is_array($publicAnnex) ? new PublicAdministrationRuleSet(new PublicAnnexCatalog($publicAnnex),
            strictDocumentPaymentContract: $strictPublicPayment) : null;
        $obligationCatalog = $documents['federal_obligation_catalog'] ?? null;
        $bankingCalendar = $documents['federal_banking_calendar'] ?? null;
        $obligationProjector = is_array($obligationCatalog) ? new FederalObligationProjector($obligationCatalog,
            is_array($bankingCalendar) ? new FederalBankingCalendar($bankingCalendar) : null) : null;
        $rtcRates = $documents['rtc_rate_catalog'] ?? null;
        $rtcCodes = $documents['rtc_code_catalog'] ?? null;
        $rtcTransition = $documents['rtc_transition_catalog'] ?? null;
        $rtcCatalogCount = count(array_filter([$rtcRates, $rtcCodes, $rtcTransition], 'is_array'));
        if ($rtcCatalogCount !== 0 && $rtcCatalogCount !== 3) {
            throw new RuntimeException('RTC rate, code and transition catalogs must be provided together.');
        }
        $transitionCatalog = $rtcCatalogCount === 3 ? new TransitionCatalog($rtcTransition) : null;
        $rtcEngine = $transitionCatalog === null
            ? null
            : new ConsumptionReformEngine(new TemporalRateCatalog($rtcRates), $rtcCodes, $transitionCatalog);

        return new DefaultFiscalDecisionEngine(
            new CanonicalServiceClassifier($services),
            new IssLocationEngine,
            $parameters,
            new IssResponsibilityEngine,
            new IssRateEngine,
            new IssCalculationEngine(new FormulaRegistry([new PercentageOfBaseFormula])),
            new FederalWithholdingEngine(public: $publicRuleSet, obligations: $obligationProjector),
            new QuestionPlanner($facts, $questions),
            new RuleEvaluator(new ConditionEvaluator(new InMemoryCatalogMembership($this->ruleCatalogs($services)))),
            $rules,
            (string) ($knowledge['rule_bundle_id'] ?? ''),
            array_map('strval', $catalogVersions),
            array_map('strval', $hashes),
            new FiscalInvariantEngine($invariants),
            new RulePrecedenceResolver,
            new DateTimeImmutable($knowledgeAvailableAt),
            $bundleStatus,
            [
                new CanonicalServiceFactResolver($services),
                new PaymentGrossAmountFactResolver,
                new InvoiceGrossAmountFactResolver,
            ],
            $rtcEngine,
            $transitionCatalog !== null && ($rtcTransition['iss_frozen_transition'] ?? false) === true
                ? new IssTransitionRateEngine($transitionCatalog) : null,
            $transitionCatalog?->integratesPostIssPeriod() ?? false,
            $transitionCatalog?->evaluatesPostIssFederalWithholdings() ?? false,
            is_array($rtcCodes['iss_institutional_immunity'] ?? null) ? $rtcCodes['iss_institutional_immunity'] : null,
            $selectIssCurrency ? new IssDocumentCurrency140($issCurrencyCatalog) : null,
        );
    }

    public function scenarioLoader(string $resourceRoot, ?string $bundleManifestPath = null): ScenarioFileLoader
    {
        return $this->scenarioLoaderFromKnowledge($this->captureKnowledge($resourceRoot, $bundleManifestPath));
    }

    /** @param array<string, mixed> $knowledge */
    public function scenarioLoaderFromKnowledge(array $knowledge): ScenarioFileLoader
    {
        $documents = $knowledge['documents'] ?? null;
        if (! is_array($documents)
            || ! isset($documents['fact_definitions'], $documents['scenario_schema'])
            || ! is_array($documents['fact_definitions'])
            || ! is_array($documents['scenario_schema'])) {
            throw new RuntimeException('Knowledge fact definitions or scenario schema are missing.');
        }

        return new ScenarioFileLoader(
            new JsonSchemaValidator,
            (new FactDefinitionFileLoader)->fromArray($documents['fact_definitions']),
            $documents['scenario_schema'],
        );
    }

    /** @return array<string, mixed> */
    private function decode(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Knowledge document not found: {$path}");
        }
        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid knowledge JSON: {$path}", previous: $exception);
        }
        if (! is_array($data)) {
            throw new RuntimeException("Knowledge document must be an object or list: {$path}");
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, string>
     */
    private function resourcePaths(array $manifest): array
    {
        $paths = [
            'fact_definitions' => 'facts/core.json',
            'questions' => 'questions/core.json',
            'service_catalog' => 'catalogs/mvp-services.json',
            'municipal_parameters' => 'catalogs/municipal-parameters-mvp.json',
            'invariants' => 'invariants/core.json',
            'scenario_schema' => 'schemas/scenario.schema.json',
            'rules' => 'rules/mvp',
        ];
        $configured = $manifest['resource_paths'] ?? [];
        if (! is_array($configured)) {
            throw new RuntimeException('Bundle manifest resource paths must be an object.');
        }
        foreach ($configured as $key => $value) {
            if (! is_string($key) || (! array_key_exists($key, $paths) && ! in_array($key, ['public_annex_catalog', 'federal_obligation_catalog', 'federal_banking_calendar', 'rtc_rate_catalog', 'rtc_code_catalog', 'rtc_transition_catalog', 'iss_currency_catalog'], true)) || ! is_string($value) || $value === '') {
                throw new RuntimeException('Bundle manifest contains an invalid resource path.');
            }
            $paths[$key] = $value;
        }

        return $paths;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<array<string, mixed>>
     */
    private function questionDocuments(string $resourceRoot, string $basePath, array $manifest): array
    {
        $documents = [$basePath];
        $overlays = $manifest['question_overlays'] ?? [];
        if (! is_array($overlays) || ! array_is_list($overlays)) {
            throw new RuntimeException('Bundle manifest question overlays must be a list.');
        }
        foreach ($overlays as $overlay) {
            if (! is_string($overlay) || $overlay === '') {
                throw new RuntimeException('Bundle manifest question overlay path is invalid.');
            }
            $documents[] = $overlay;
        }

        $questions = [];
        $validator = new JsonSchemaValidator;
        foreach ($documents as $document) {
            $path = $this->resolveResourcePath($resourceRoot, $document);
            $validation = $validator->validateFile(
                $path,
                $this->resolveResourcePath($resourceRoot, 'schemas/questions.schema.json'),
            );
            if (! $validation->valid) {
                throw new RuntimeException("Knowledge schema validation failed for {$document}.");
            }
            $payload = $this->decode($path);
            foreach ($payload as $question) {
                if (! is_array($question) || ! isset($question['fact_key'])) {
                    throw new RuntimeException("Knowledge question is invalid in {$document}.");
                }
                $questions[] = $question;
            }
        }

        return $questions;
    }

    private function resolveResourcePath(string $resourceRoot, string $path): string
    {
        $root = realpath($resourceRoot);
        if ($root === false) {
            throw new RuntimeException("Fiscal resource root not found: {$resourceRoot}");
        }
        $candidate = $this->isAbsolutePath($path)
            ? $path
            : $root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $resolved = realpath($candidate);
        if ($resolved === false) {
            throw new RuntimeException("Knowledge document not found: {$candidate}");
        }

        $rootNormalized = rtrim(str_replace('\\', '/', $root), '/');
        $resolvedNormalized = str_replace('\\', '/', $resolved);
        if ($resolvedNormalized !== $rootNormalized && ! str_starts_with($resolvedNormalized, $rootNormalized.'/')) {
            throw new RuntimeException("Knowledge path must stay inside the fiscal resource root: {$path}");
        }

        return $resolved;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function rulePayloads(array $data): array
    {
        if (! array_is_list($data)) {
            throw new RuntimeException('Knowledge rules must be a JSON list.');
        }
        $payloads = [];
        foreach ($data as $row) {
            if (! is_array($row) || array_is_list($row)) {
                throw new RuntimeException('Each knowledge rule must be a JSON object.');
            }
            $payload = [];
            foreach ($row as $key => $value) {
                if (! is_string($key)) {
                    throw new RuntimeException('Knowledge rule keys must be strings.');
                }
                $payload[$key] = $value;
            }
            $payloads[] = $payload;
        }

        return $payloads;
    }

    /** @return array<string, list<string>> */
    private function ruleCatalogs(CanonicalServiceCatalog $services): array
    {
        $canonicalIds = [];
        $lc116Codes = [];
        $ctribnacCodes = [];
        foreach ($services->all() as $service) {
            $canonicalIds[] = $service->id;
            $lc116Codes[] = $service->lc116Code;
            $ctribnacCodes[] = $service->ctribnacCode;
        }

        return [
            $services->catalogId => array_values(array_unique($canonicalIds)),
            $services->catalogId.'.LC116' => array_values(array_unique($lc116Codes)),
            $services->catalogId.'.CTRIBNAC' => array_values(array_unique($ctribnacCodes)),
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $documents
     * @param  array<string, mixed>  $actualHashes
     */
    private function assertManifestContentHashes(array $manifest, array $documents, array $actualHashes): void
    {
        $declaredHashes = $manifest['content_hashes'] ?? null;
        if (! is_array($declaredHashes)) {
            throw new RuntimeException('Bundle manifest content hashes are missing.');
        }

        foreach (['fact_definitions', 'questions', 'service_catalog', 'municipal_parameters', 'invariants', 'scenario_schema', 'rules'] as $key) {
            $declared = $declaredHashes[$key] ?? null;
            $actual = $actualHashes[$key] ?? null;
            if (! is_string($declared) || ! preg_match('/^[a-f0-9]{64}$/', $declared)) {
                throw new RuntimeException("Bundle manifest content hash is invalid: {$key}");
            }
            if (! is_string($actual)) {
                throw new RuntimeException("Knowledge content hash is missing: {$key}");
            }

            if ($key === 'municipal_parameters'
                && ($documents[$key]['source_type'] ?? null) === 'SNAPSHOT') {
                $baseCatalogHash = $documents[$key]['base_catalog_hash'] ?? null;
                if (! is_string($baseCatalogHash) || ! hash_equals($declared, $baseCatalogHash)) {
                    throw new RuntimeException('Municipal execution snapshot is not bound to the manifest base catalog.');
                }

                continue;
            }

            if (! hash_equals($declared, $actual)) {
                throw new RuntimeException("Bundle manifest content hash mismatch: {$key}");
            }
        }
        if (isset($documents['public_annex_catalog'])) {
            $declared = $declaredHashes['public_annex_catalog'] ?? null;
            $actual = $actualHashes['public_annex_catalog'] ?? null;
            if (! is_string($declared) || ! is_string($actual) || ! hash_equals($declared, $actual)) {
                throw new RuntimeException('Bundle manifest content hash mismatch: public_annex_catalog');
            }
        }
        if (isset($documents['federal_obligation_catalog'])) {
            $declared = $declaredHashes['federal_obligation_catalog'] ?? null;
            $actual = $actualHashes['federal_obligation_catalog'] ?? null;
            if (! is_string($declared) || ! is_string($actual) || ! hash_equals($declared, $actual)) {
                throw new RuntimeException('Bundle manifest content hash mismatch: federal_obligation_catalog');
            }
        }
        if (isset($documents['federal_banking_calendar'])) {
            $declared = $declaredHashes['federal_banking_calendar'] ?? null;
            $actual = $actualHashes['federal_banking_calendar'] ?? null;
            if (! is_string($declared) || ! is_string($actual) || ! hash_equals($declared, $actual)) {
                throw new RuntimeException('Bundle manifest content hash mismatch: federal_banking_calendar');
            }
        }
        foreach (['rtc_rate_catalog', 'rtc_code_catalog', 'rtc_transition_catalog', 'iss_currency_catalog'] as $key) {
            if (isset($documents[$key])) {
                $declared = $declaredHashes[$key] ?? null;
                $actual = $actualHashes[$key] ?? null;
                if (! is_string($declared) || ! is_string($actual) || ! hash_equals($declared, $actual)) {
                    throw new RuntimeException("Bundle manifest content hash mismatch: {$key}");
                }
            }
        }
    }
}
