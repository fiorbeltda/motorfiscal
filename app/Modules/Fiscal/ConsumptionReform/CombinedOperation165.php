<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use Brick\Math\BigDecimal;
use InvalidArgumentException;
use Throwable;

/** Execute every specified supply, including its independent monetary and event context. */
final readonly class CombinedOperation165
{
    public const CONTRACT = 'RTC-COMBINED-OPERATION-A165-V1';

    public const KEY = 'combined_operation_a165';

    public const FACT = 'rtc.combined_supply_binding';

    public const POLICY = ['version' => 'A165', 'contract' => self::CONTRACT, 'source_hash' => SplitLegalEvidence162::LAW_HASH,
        'articles' => ['LC214 art7', 'LC214 art10', 'LC214 art64', 'LC214 art80'], 'runner' => 'RTC_AND_INTEGRATED',
        'scope' => 'ONE_CURRENT_SNAPSHOT_PER_FISCAL_SUPPLY', 'rounding' => 'SUM_OF_ALREADY_ROUNDED_PER_SUPPLY_AMOUNTS',
        'document_currency' => 'RECONCILE_NATIVE_DOCUMENT_LINES_BEFORE_BRL_TOTAL', 'posting' => false];

    public function __construct(private FiscalEngineFactory $factory, private string $resourceRoot, private string $manifest) {}

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function assess(array $input): array
    {
        try {
            return $this->calculate($input);
        } catch (InvalidArgumentException $exception) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => $exception->getMessage(), 'local_nominal_totals' => null,
                'fiscal_decision_totals' => null, 'official' => false, 'posting_authorized' => false];
        }
    }

    /** @param array<string, mixed> $report */
    public function replay(array $report): bool
    {
        return is_array($report['input'] ?? null) && CanonicalJson::hash($this->assess($report['input'])) === CanonicalJson::hash($report);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function calculate(array $input): array
    {
        $g = new SplitLegalEvidence162;
        $g->envelope($input, ['contract', 'currency', 'source_kind', 'source_hash', 'evidence_id', 'input_hash', 'known_at',
            'operation_id', 'commercial_contract_id', 'supplier_id', 'acquirer_id', 'independence_evidence_id', 'independence_source_hash',
            'declared_gross_brl', 'documents', 'supplies'], self::CONTRACT);
        foreach (['operation_id', 'commercial_contract_id', 'supplier_id', 'acquirer_id'] as $key) {
            $g->id($input[$key]);
        }
        $g->proof(['evidence_id' => $input['independence_evidence_id'], 'source_hash' => $input['independence_source_hash']]);
        $known = $g->instant($input['known_at']);
        $g->must(preg_match('/\Abundles\/[A-Za-z0-9._-]+\.json\z/', $this->manifest) === 1, 'A165_INSTALLED_MANIFEST_REQUIRED');
        $knowledge = $this->factory->captureKnowledge($this->resourceRoot, $this->manifest);
        $codes = $knowledge['documents']['rtc_code_catalog'];
        $g->must(is_array($codes[self::KEY] ?? null) && CanonicalJson::hash($codes[self::KEY]) === CanonicalJson::hash(self::POLICY)
            && is_array($codes[CrossBorderTaxPoint165::KEY] ?? null) && CrossBorderTaxPoint165::valid($codes[CrossBorderTaxPoint165::KEY]), 'A165_POLICY_NOT_ENABLED');
        $g->must($known >= $g->instant($knowledge['knowledge_available_at']), 'A165_BUNDLE_NOT_KNOWN');
        $g->must(is_array($input['documents']) && array_is_list($input['documents']) && count($input['documents']) >= 1, 'A165_DOCUMENT_ALLOCATIONS_REQUIRED');
        $documents = [];
        $zero = BigDecimal::of('0.00');
        foreach ($input['documents'] as $document) {
            $g->must(is_array($document), 'A165_DOCUMENT_RECORD_REQUIRED');
            $g->fields($document, ['document_id', 'currency', 'declared_native_total', 'evidence_id', 'source_hash']);
            $id = '#'.$g->id($document['document_id']);
            $g->must(! isset($documents[$id]) && is_string($document['currency']) && preg_match('/\A[A-Z]{3}\z/', $document['currency']) === 1, 'A165_DOCUMENT_ID_OR_CURRENCY_CONFLICT');
            $g->proof($document);
            $nativeTotal = $g->money($document['declared_native_total']);
            $g->must($nativeTotal->isPositive(), 'A165_POSITIVE_DOCUMENT_TOTAL_REQUIRED');
            $documents[$id] = ['input' => $document, 'total' => $nativeTotal, 'allocated' => $zero, 'lines' => []];
        }
        $supplies = $input['supplies'];
        $g->must(is_array($supplies) && array_is_list($supplies) && count($supplies) >= 2 && count($supplies) <= 100, 'A165_DISTINCT_SUPPLIES_REQUIRED');
        $seen = [];
        $gross = $zero;
        foreach ($supplies as $supply) {
            $g->must(is_array($supply), 'A165_SUPPLY_REQUIRED');
            $g->fields($supply, ['id', 'fiscal_supply_id', 'description', 'document_line_id', 'value_brl', 'original_amount', 'original_currency',
                'allocation_evidence_id', 'allocation_source_hash', 'accessories', 'scenario']);
            foreach (['id', 'fiscal_supply_id', 'description', 'document_line_id'] as $key) {
                $g->id($supply[$key]);
            }
            foreach (['id', 'fiscal_supply_id'] as $key) {
                $mark = $key.':'.$supply[$key];
                $g->must(! in_array($mark, $seen, true), 'A165_DUPLICATE_SUPPLY_OR_EVENT_SNAPSHOT');
                $seen[] = $mark;
            }
            $value = $g->money($supply['value_brl']);
            $native = $g->money($supply['original_amount']);
            $g->must($value->isPositive() && $native->isPositive(), 'A165_POSITIVE_SPECIFIED_PRICES_REQUIRED');
            $gross = $gross->plus($value);
            $g->proof(['evidence_id' => $supply['allocation_evidence_id'], 'source_hash' => $supply['allocation_source_hash']]);
            $scenario = $supply['scenario'];
            $g->must(is_array($scenario) && is_array($scenario['facts'] ?? null), 'A165_SCENARIO_REQUIRED');
            $facts = $scenario['facts'];
            $g->must(($scenario['scenario_id'] ?? null) === $supply['id'] && $g->instant($scenario['knowledge_at'] ?? null) == $known
                && ($facts['rtc.supplier_id'] ?? null) === $input['supplier_id'] && ($facts['rtc.acquirer_id'] ?? null) === $input['acquirer_id']
                && ($facts['rtc.gross_amount'] ?? null) === $supply['original_amount']
                && ($facts['rtc.gross_currency'] ?? null) === $supply['original_currency'], 'A165_SCENARIO_PARTIES_KNOWLEDGE_OR_PRICE_CONFLICT');
            $binding = $facts[self::FACT] ?? null;
            $expectedBinding = ['operation_id' => $input['operation_id'], 'commercial_contract_id' => $input['commercial_contract_id'],
                'supply_id' => $supply['id'], 'fiscal_supply_id' => $supply['fiscal_supply_id'], 'document_line_id' => $supply['document_line_id'],
                'value_brl' => $supply['value_brl'], 'allocation_evidence_id' => $supply['allocation_evidence_id'], 'allocation_source_hash' => $supply['allocation_source_hash']];
            $g->must(is_array($binding) && CanonicalJson::hash($binding) === CanonicalJson::hash([$expectedBinding]), 'A165_COMMERCIAL_ALLOCATION_BINDING_CONFLICT');
            $documentKey = '#'.$g->id($facts['rtc.document_id'] ?? null);
            $g->must(isset($documents[$documentKey]) && $documents[$documentKey]['input']['currency'] === $supply['original_currency']
                && ! in_array($supply['document_line_id'], $documents[$documentKey]['lines'], true), 'A165_DOCUMENT_LINE_OR_CURRENCY_CONFLICT');
            $documents[$documentKey]['lines'][] = $supply['document_line_id'];
            $documents[$documentKey]['allocated'] = $documents[$documentKey]['allocated']->plus($native);
            $g->must(is_array($supply['accessories']) && array_is_list($supply['accessories']), 'A165_ACCESSORIES_LIST_REQUIRED');
            foreach ($supply['accessories'] as $accessory) {
                $g->must(is_array($accessory), 'A165_ACCESSORY_REQUIRED');
                $g->fields($accessory, ['id', 'description', 'role', 'independent_supply', 'evidence_id', 'source_hash']);
                $accessoryId = $g->id($accessory['id']);
                $g->id($accessory['description']);
                $g->proof($accessory);
                $g->must(in_array($accessory['role'], ['CONDITION', 'MEANS'], true) && $accessory['independent_supply'] === false
                    && ! in_array('accessory:'.$accessoryId, $seen, true)
                    && ! in_array($accessoryId, array_column($supplies, 'id'), true), 'A165_ACCESSORY_INDEPENDENCE_OR_ID_CONFLICT');
                $seen[] = 'accessory:'.$accessoryId;
            }
        }
        $g->must($gross->compareTo($g->money($input['declared_gross_brl'])) === 0, 'A165_BRL_ALLOCATION_TOTAL_CONFLICT');
        foreach ($documents as $document) {
            $g->must($document['allocated']->compareTo($document['total']) === 0, 'A165_NATIVE_DOCUMENT_TOTAL_CONFLICT');
        }
        usort($supplies, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
        $results = [];
        $totals = array_fill_keys(['gross_brl', 'taxable_base_brl', 'foreign_immune_gross_brl', 'ibs_state_brl', 'ibs_municipal_brl', 'cbs_brl'], '0.00');
        $complete = true;
        $fiscalComplete = $input['source_kind'] !== 'SYNTHETIC_TEST';
        foreach ($supplies as $supply) {
            try {
                $scenario = $supply['scenario'];
                $loaded = $this->factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($scenario);
                $rtc = (new RtcScenarioRunService($this->factory))->evaluate($knowledge, $loaded)->toArray();
                $integrated = $this->factory->makeFromKnowledge($knowledge)->evaluate($loaded->input)->toArray();
                $payloads = array_values(array_filter($integrated['trace'], static fn (array $row): bool => ($row['type'] ?? null) === 'CONSUMPTION_REFORM'));
                $g->must(count($payloads) === 1, 'A165_INTEGRATED_RTC_RESULT_REQUIRED');
                foreach (['status', 'reason_codes', 'trace', 'decision'] as $key) {
                    $g->must(CanonicalJson::hash($rtc[$key]) === CanonicalJson::hash($payloads[0]['payload'][$key]), 'A165_RUNNER_RESULT_CONFLICT');
                }
                $calculation = (new SupplyCalculation165)->read($scenario['facts'], $rtc);
                $g->must($calculation !== null, 'A165_COMPONENT_CALCULATION_UNAVAILABLE');
                $g->must(! $calculation['synthetic'] || $input['source_kind'] === 'SYNTHETIC_TEST', 'A165_SYNTHETIC_COMPONENT_NOT_DOCUMENTARY');
                $g->must($calculation['gross_brl'] === $supply['value_brl'], 'A165_CONVERTED_SUPPLY_PRICE_CONFLICT');
                foreach ($totals as $key => $value) {
                    $totals[$key] = (string) BigDecimal::of($value)->plus($calculation[$key]);
                }
                $fiscalComplete = $fiscalComplete && $rtc['status'] === 'VALID' && in_array($integrated['status'], ['VALID', 'VALID_WITH_WARNINGS'], true);
                $results[] = ['id' => $supply['id'], 'status' => 'LOCAL_SUPPLY_CALCULATED', 'calculation' => $calculation,
                    'rtc_result' => $rtc, 'integrated_result' => $integrated];
            } catch (Throwable $exception) {
                $complete = $fiscalComplete = false;
                $results[] = ['id' => $supply['id'], 'status' => 'ACTION_REQUIRED', 'reason' => $exception->getMessage()];
            }
        }
        $report = ['contract' => self::CONTRACT, 'status' => $complete ? 'LOCAL_COMBINED_OPERATION_CALCULATED' : 'ACTION_REQUIRED',
            'fiscal_status' => $fiscalComplete ? 'COMPONENT_DECISIONS_AVAILABLE' : 'ACTION_REQUIRED',
            'input' => $input, 'bundle_manifest' => $this->manifest, 'bundle_id' => $knowledge['rule_bundle_id'],
            'knowledge_hashes' => $knowledge['content_hashes'], 'supplies' => $results,
            'local_nominal_totals' => $complete ? $totals : null, 'fiscal_decision_totals' => $fiscalComplete ? $totals : null,
            'scope' => 'ONE_CURRENT_SNAPSHOT_PER_FISCAL_SUPPLY;ADVANCES_NOT_ADDED_TO_FINAL_BASE',
            'official' => false, 'combined_document_generated' => false, 'document_obligation_complete' => false,
            'posting_authorized' => false, 'credit_appropriation_authorized' => false];
        $report['report_hash'] = CanonicalJson::hash($report);

        return $report;
    }
}
