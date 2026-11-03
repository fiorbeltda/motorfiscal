<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use DateTimeImmutable;
use Throwable;

/** Compose specified independent supplies; classifications and documentary contracts remain per supply. */
final readonly class CombinedSupplyAssessment145
{
    public const CONTRACT = 'RTC-COMBINED-INDEPENDENT-SUPPLIES-2026.11.03-A145';

    public const PRINCIPAL_CONTRACT = 'RTC-COMBINED-PRINCIPAL-ACCESSORIES-2026.11.03-A146';

    public const SOURCE_AVAILABLE_AT = '2026-11-03T11:18:57+00:00';

    public function __construct(private FiscalEngineFactory $factory, private string $resourceRoot) {}

    /** @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    public function assess(array $operation): array
    {
        if (($operation['relationship'] ?? null) === 'PRINCIPAL_ACCESSORIES') {
            return $this->principal($operation);
        }
        $knownAt = $this->instant($operation['knowledge_at'] ?? null);
        if ($knownAt === null) {
            return $this->stop('COMBINED145_KNOWLEDGE_INSTANT_INVALID');
        }
        if ($knownAt < new DateTimeImmutable(self::SOURCE_AVAILABLE_AT)) {
            return $this->stop('COMBINED145_POLICY_NOT_YET_AVAILABLE');
        }
        foreach (['operation_id', 'contract_id', 'supplier_id', 'acquirer_id', 'allocation_evidence_id',
            'relationship_evidence_id'] as $key) {
            if (! $this->identifier($operation[$key] ?? null)) {
                return $this->stop('COMBINED145_HEADER_EVIDENCE_REQUIRED:'.$key);
            }
        }
        if (($operation['relationship'] ?? null) !== 'INDEPENDENT_SUPPLIES') {
            return $this->stop('COMBINED145_PRINCIPAL_ACCESSORY_QUALIFICATION_REQUIRED');
        }
        if (($operation['currency'] ?? null) !== 'BRL') {
            return $this->stop('COMBINED145_CURRENCY_CONVERSION_REQUIRED');
        }
        if (! $this->money($operation['declared_gross_brl'] ?? null)) {
            return $this->stop('COMBINED145_SPECIFIED_TOTAL_REQUIRED');
        }
        $supplies = $operation['supplies'] ?? null;
        if (! is_array($supplies) || ! array_is_list($supplies) || count($supplies) < 2) {
            return $this->stop('COMBINED145_DISTINCT_SPECIFIED_SUPPLIES_REQUIRED');
        }
        $sum = DecimalValue::fromString('0.00');
        $seen = [];
        foreach ($supplies as $supply) {
            if (! is_array($supply) || ! $this->identifier($supply['id'] ?? null)
                || isset($seen['ID:'.$supply['id']]) || ! $this->identifier($supply['description'] ?? null)
                || ! $this->identifier($supply['allocation_evidence_id'] ?? null)
                || ! $this->money($supply['value_brl'] ?? null)) {
                return $this->stop('COMBINED145_SUPPLY_ID_VALUE_OR_EVIDENCE_INVALID');
            }
            $seen['ID:'.$supply['id']] = true;
            $sum = $sum->plus(DecimalValue::fromString($supply['value_brl']));
        }
        if ($sum->compareTo(DecimalValue::fromString($operation['declared_gross_brl'])) !== 0) {
            return $this->stop('COMBINED145_SPECIFIED_VALUES_DO_NOT_RECONCILE');
        }
        usort($supplies, static fn (array $left, array $right): int => strcmp($left['id'], $right['id']));
        $operation['supplies'] = $supplies;
        $results = $knowledgeCache = [];
        $totals = $this->zeroTotals();
        $groups = [];
        $complete = true;
        foreach ($supplies as $supply) {
            $result = $this->supply($supply, $operation, $knownAt, $knowledgeCache);
            $results[] = ['id' => $supply['id'], 'description' => $supply['description'],
                'specified_gross_brl' => $supply['value_brl'],
                'allocation_evidence_id' => $supply['allocation_evidence_id'], ...$result];
            if ($result['status'] !== 'ASSESSED') {
                $complete = false;

                continue;
            }
            $decision = $result['rtc_snapshot']['result']['decision'];
            $amounts = ['taxable_base_brl' => $decision['base']['taxable'],
                'ibs_state_brl' => $decision['ibs']['state_amount'],
                'ibs_municipal_brl' => $decision['ibs']['municipal_amount'],
                'cbs_brl' => $decision['cbs']['nominal_amount'],
                'ibs_cash_due_brl' => $decision['ibs']['cash_due'], 'cbs_cash_due_brl' => $decision['cbs']['cash_due']];
            $group = ['operation_date' => $decision['operation_date'],
                'state' => $decision['destination']['state'],
                'municipality_ibge' => $decision['destination']['municipality_ibge']];
            $groupId = CanonicalJson::hash($group);
            $groups[$groupId] ??= [...$group, 'amounts' => $this->zeroTotals(), 'supply_ids' => []];
            $groups[$groupId]['supply_ids'][] = $supply['id'];
            foreach ($amounts as $key => $amount) {
                $totals[$key] = DecimalValue::fromString($totals[$key])->plus(DecimalValue::fromString($amount))->rounded(2)->value();
                $groups[$groupId]['amounts'][$key] = DecimalValue::fromString($groups[$groupId]['amounts'][$key])
                    ->plus(DecimalValue::fromString($amount))->rounded(2)->value();
            }
        }
        ksort($groups, SORT_STRING);
        $report = ['contract' => self::CONTRACT,
            'status' => $complete ? 'LOCAL_COMBINED_SUPPLIES_ASSESSED' : 'ACTION_REQUIRED',
            'reason' => $complete ? 'COMBINED145_SPECIFIED_INDEPENDENT_SUPPLIES_RECONCILED' : 'COMBINED145_COMPONENT_ASSESSMENT_REQUIRED',
            'source' => $this->source(), 'operation_input' => $operation,
            'declaration_hash' => CanonicalJson::hash($operation), 'supplies' => $results,
            'specified_gross_brl' => $sum->rounded(2)->value(),
            'totals' => $complete ? $totals : null, 'tax_groups' => $complete ? array_values($groups) : [],
            'rounding' => 'SUM_OF_PER_SUPPLY_HALF_UP_2; NO_BLENDED_RATE',
            ...$this->limits()];

        return [...$report, 'report_hash' => CanonicalJson::hash($report)];
    }

    /** Qualify a single supply under art.7 II; accessory amounts are neither allocated nor taxed twice.
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function principal(array $operation): array
    {
        $knownAt = $this->instant($operation['knowledge_at'] ?? null);
        if ($knownAt === null || $knownAt < new DateTimeImmutable(self::SOURCE_AVAILABLE_AT)) {
            return $this->principalStop('COMBINED146_SOURCE_KNOWLEDGE_UNPROVEN');
        }
        foreach (['operation_id', 'contract_id', 'supplier_id', 'acquirer_id', 'relationship_evidence_id'] as $key) {
            if (! $this->identifier($operation[$key] ?? null)) {
                return $this->principalStop('COMBINED146_HEADER_EVIDENCE_REQUIRED:'.$key);
            }
        }
        if (array_key_exists('supplies', $operation)) {
            return $this->principalStop('COMBINED146_INDEPENDENT_AND_ACCESSORY_CONTEXT_CONFLICT');
        }
        if (($operation['currency'] ?? null) !== 'BRL' || ! $this->money($operation['declared_gross_brl'] ?? null)) {
            return $this->principalStop('COMBINED146_TOTAL_OR_CURRENCY_UNPROVEN');
        }
        $principal = $operation['principal'] ?? null;
        $accessories = $operation['accessories'] ?? null;
        if (! is_array($principal) || ! $this->identifier($principal['id'] ?? null)
            || ! $this->identifier($principal['description'] ?? null)
            || ! $this->identifier($principal['specification_evidence_id'] ?? null)
            || ! is_array($accessories) || ! array_is_list($accessories) || $accessories === []) {
            return $this->principalStop('COMBINED146_SPECIFIED_PRINCIPAL_AND_ACCESSORIES_REQUIRED');
        }
        if (array_key_exists('value_brl', $principal) && $principal['value_brl'] !== $operation['declared_gross_brl']) {
            return $this->principalStop('COMBINED146_SINGLE_SUPPLY_TOTAL_CONFLICT');
        }
        $seen = ['ID:'.$principal['id'] => true];
        foreach ($accessories as $accessory) {
            if (! is_array($accessory) || ! $this->identifier($accessory['id'] ?? null)
                || isset($seen['ID:'.$accessory['id']]) || ! $this->identifier($accessory['description'] ?? null)
                || ($accessory['principal_id'] ?? null) !== $principal['id']
                || ! $this->identifier($accessory['relationship_evidence_id'] ?? null)) {
                return $this->principalStop('COMBINED146_ACCESSORY_IDENTITY_OR_RELATION_UNPROVEN');
            }
            $seen['ID:'.$accessory['id']] = true;
            if (! in_array($accessory['role'] ?? null, ['CONDITION', 'MEANS'], true)
                || ($accessory['independent_supply'] ?? null) !== false
                || array_key_exists('scenario', $accessory) || array_key_exists('tax_treatment', $accessory)) {
                return $this->principalStop('COMBINED146_ACCESSORY_INDEPENDENCE_OR_TREATMENT_CONFLICT');
            }
        }
        usort($accessories, static fn (array $left, array $right): int => strcmp($left['id'], $right['id']));
        $operation['accessories'] = $accessories;
        $principal['value_brl'] = $operation['declared_gross_brl'];
        $knowledgeCache = [];
        $result = $this->supply($principal, $operation, $knownAt, $knowledgeCache);
        $complete = $result['status'] === 'ASSESSED';
        $totals = null;
        $groups = [];
        if ($complete) {
            $decision = $result['rtc_snapshot']['result']['decision'];
            $totals = ['taxable_base_brl' => $decision['base']['taxable'],
                'ibs_state_brl' => $decision['ibs']['state_amount'], 'ibs_municipal_brl' => $decision['ibs']['municipal_amount'],
                'cbs_brl' => $decision['cbs']['nominal_amount'], 'ibs_cash_due_brl' => $decision['ibs']['cash_due'],
                'cbs_cash_due_brl' => $decision['cbs']['cash_due']];
            $groups[] = ['operation_date' => $decision['operation_date'], 'state' => $decision['destination']['state'],
                'municipality_ibge' => $decision['destination']['municipality_ibge'],
                'amounts' => $totals, 'supply_ids' => [$principal['id']]];
        }
        $report = ['contract' => self::PRINCIPAL_CONTRACT,
            'status' => $complete ? 'LOCAL_COMBINED_SUPPLIES_ASSESSED' : 'ACTION_REQUIRED',
            'reason' => $complete ? 'COMBINED146_SINGLE_PRINCIPAL_SUPPLY_ASSESSED' : 'COMBINED146_PRINCIPAL_FISCAL_DEPENDENCY',
            'source' => $this->source(), 'operation_input' => $operation, 'declaration_hash' => CanonicalJson::hash($operation),
            'fiscal_supply_count' => 1, 'accessories' => $accessories,
            'total_consideration_brl' => $operation['declared_gross_brl'],
            'supplies' => [['id' => $principal['id'], 'description' => $principal['description'],
                'specified_gross_brl' => $principal['value_brl'], 'specification_evidence_id' => $principal['specification_evidence_id'],
                ...$result]],
            'totals' => $totals, 'tax_groups' => $groups,
            'rounding' => 'SINGLE_SUPPLY_HALF_UP_2; NO_ACCESSORY_PRICE_ALLOCATION', ...$this->limits()];

        return [...$report, 'report_hash' => CanonicalJson::hash($report)];
    }

    /** @return array<string, mixed> */
    private function principalStop(string $reason): array
    {
        return [...$this->stop($reason), 'contract' => self::PRINCIPAL_CONTRACT];
    }

    /** Re-evaluate every component from its installed immutable bundle, then compare the whole report.
     * @param  array<string, mixed>  $report
     */
    public function replay(array $report): bool
    {
        if (! is_array($report['operation_input'] ?? null) || ! is_string($report['report_hash'] ?? null)) {
            return false;
        }
        $saved = $report;
        unset($saved['report_hash']);
        if (! hash_equals($report['report_hash'], CanonicalJson::hash($saved))) {
            return false;
        }
        $fresh = $this->assess($report['operation_input']);

        return CanonicalJson::hash($fresh) === CanonicalJson::hash($report);
    }

    /** @param array<string, mixed> $supply
     * @param  array<string, mixed>  $operation
     * @param  array<string, array<string, mixed>>  $knowledgeCache
     * @return array<string, mixed>
     */
    private function supply(array $supply, array $operation, DateTimeImmutable $knownAt, array &$knowledgeCache): array
    {
        $scenario = $supply['scenario'] ?? null;
        $manifest = $supply['bundle'] ?? null;
        if (! is_array($scenario) || ! is_array($scenario['facts'] ?? null)
            || ! is_string($manifest) || preg_match('/\Abundles\/[A-Za-z0-9._-]+\.json\z/', $manifest) !== 1) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'COMBINED145_SCENARIO_AND_INSTALLED_BUNDLE_REQUIRED'];
        }
        $facts = $scenario['facts'];
        if (($facts['rtc.supplier_id'] ?? null) !== $operation['supplier_id']
            || ($facts['rtc.acquirer_id'] ?? null) !== $operation['acquirer_id']
            || ($facts['rtc.gross_amount'] ?? null) !== $supply['value_brl']
            || ($facts['operation.amount'] ?? null) !== $supply['value_brl']
            || ($scenario['scenario_id'] ?? null) !== $supply['id']
            || $this->instant($scenario['knowledge_at'] ?? null) != $knownAt) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'COMBINED145_SCENARIO_BINDING_CONFLICT'];
        }
        // This additive route does not select a new regime or turn eligibility into a paid/credited amount.
        foreach (['rtc.gross_currency' => 'BRL', 'rtc.market' => 'DOMESTIC', 'rtc.consideration' => 'ONEROUS',
            'rtc.supplier_regime' => 'REGULAR', 'rtc.credit_context' => 'OUTPUT', 'rtc.collection_method' => 'SUPPLIER'] as $key => $value) {
            if (($facts[$key] ?? null) !== $value) {
                return ['status' => 'ACTION_REQUIRED', 'reason' => 'COMBINED145_SEPARATE_COMPONENT_CONTEXT_REQUIRED:'.$key];
            }
        }
        try {
            $knowledgeCache[$manifest] ??= $this->factory->captureKnowledge($this->resourceRoot, $manifest);
            $knowledge = $knowledgeCache[$manifest];
            $loaded = $this->factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($scenario);
            $rtc = (new RtcScenarioRunService($this->factory))->evaluate($knowledge, $loaded)->toArray();
            $full = $this->factory->makeFromKnowledge($knowledge)->evaluate($loaded->input)->toArray();
        } catch (Throwable $exception) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'COMBINED145_COMPONENT_INPUT_OR_KNOWLEDGE_INVALID',
                'detail' => $exception->getMessage()];
        }
        $snapshots = ['bundle' => $manifest, 'rtc_snapshot' => ['input' => $scenario, 'knowledge' => $knowledge, 'result' => $rtc],
            'integrated_snapshot' => ['input' => $scenario, 'knowledge' => $knowledge, 'result' => $full]];
        if ($rtc['status'] !== 'VALID' || ! in_array($full['status'], ['VALID', 'VALID_WITH_WARNINGS'], true)) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'COMBINED145_COMPONENT_FISCAL_DEPENDENCY', ...$snapshots];
        }
        $integratedRtc = $full['decision']['consumption_reform']['decision'] ?? null;
        if (CanonicalJson::hash($integratedRtc) !== CanonicalJson::hash($rtc['decision'])
            || ($rtc['decision']['base']['gross'] ?? null) !== $supply['value_brl']) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'COMBINED145_RUNNERS_OR_GROSS_CONFLICT', ...$snapshots];
        }

        return ['status' => 'ASSESSED', ...$snapshots];
    }

    /** @return array<string, string> */
    private function zeroTotals(): array
    {
        return array_fill_keys(['taxable_base_brl', 'ibs_state_brl', 'ibs_municipal_brl', 'cbs_brl',
            'ibs_cash_due_brl', 'cbs_cash_due_brl'], '0.00');
    }

    private function identifier(mixed $value): bool
    {
        return is_string($value) && $value !== '' && trim($value) === $value && ! str_contains($value, "\0");
    }

    private function money(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,15})\.[0-9]{2}\z/', $value) === 1 && $value !== '0.00';
    }

    private function instant(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})\z/', $value) !== 1) {
            return null;
        }
        try {
            $instant = new DateTimeImmutable($value);
            $errors = DateTimeImmutable::getLastErrors();

            return $errors === false ? $instant : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string, string> */
    private function source(): array
    {
        return ['article' => 'LC214 art.7 caput e §§1–3',
            'url' => 'https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html',
            'sha256' => 'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde',
            'available_at' => self::SOURCE_AVAILABLE_AT];
    }

    /** @return array<string, bool|string> */
    private function limits(): array
    {
        return ['document_obligation_complete' => false, 'combined_document_projection' => 'PENDING_AGENT_B_INTEGRATION',
            'government_evidence_authenticated' => false, 'extinction_proven' => false,
            'credit_appropriation_authorized' => false, 'posting_authorized' => false, 'production_ready' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['contract' => self::CONTRACT, 'status' => 'ACTION_REQUIRED', 'reason' => $reason,
            'totals' => null, 'tax_groups' => [], ...$this->limits()];
    }
}
