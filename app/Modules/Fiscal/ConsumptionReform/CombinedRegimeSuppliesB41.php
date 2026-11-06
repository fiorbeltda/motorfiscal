<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use Throwable;

/**
 * B41 (4B.4.6): combined operation whose independent supplies fall under different IBS/CBS regimes (e.g. a tourism package:
 * lodging under arts. 277-283, agency intermediation under arts. 288-291, a standard service).
 *
 * Each supply is decided by the IBS/CBS engine on its own installed bundle and keeps its own classification, rate, base and
 * NFS-e projection (one document per supply); totals are the sum of per-supply rounded amounts, never a blended rate.
 * Food and beverages bundled with lodging (art. 280, par. único) are refused as a supply of this composition because they
 * follow the bars-and-restaurants regime, documented outside NFS-e. ISS is not assessed here (see CombinedSupplyAssessment145).
 */
final readonly class CombinedRegimeSuppliesB41
{
    public const CONTRACT = 'RTC-COMBINED-REGIME-SUPPLIES-B41-V1';

    public function __construct(private FiscalEngineFactory $factory, private string $resourceRoot) {}

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    public function assess(array $operation): array
    {
        foreach (['operation_id', 'supplier_id', 'acquirer_id', 'allocation_evidence_id', 'independence_evidence_id'] as $key) {
            if (! $this->identifier($operation[$key] ?? null)) {
                return $this->stop('B41_HEADER_EVIDENCE_REQUIRED:'.$key);
            }
        }
        if (($operation['contract'] ?? null) !== self::CONTRACT || ($operation['currency'] ?? null) !== 'BRL'
            || ! $this->money($operation['declared_gross_brl'] ?? null)) {
            return $this->stop('B41_CONTRACT_TOTAL_OR_CURRENCY_INVALID');
        }
        $supplies = $operation['supplies'] ?? null;
        if (! is_array($supplies) || ! array_is_list($supplies) || count($supplies) < 2) {
            return $this->stop('B41_DISTINCT_SUPPLIES_REQUIRED');
        }
        $sum = DecimalValue::fromString('0.00');
        $seen = [];
        foreach ($supplies as $supply) {
            if (! is_array($supply) || ! $this->identifier($supply['id'] ?? null) || isset($seen[$supply['id']])
                || ! $this->identifier($supply['allocation_evidence_id'] ?? null) || ! $this->money($supply['value_brl'] ?? null)) {
                return $this->stop('B41_SUPPLY_ID_VALUE_OR_EVIDENCE_INVALID');
            }
            if (($supply['kind'] ?? null) === 'FOOD_AND_BEVERAGE') {
                return $this->stop('B41_FOOD_FOLLOWS_RESTAURANT_REGIME_OUTSIDE_NFSE');
            }
            $seen[$supply['id']] = true;
            $sum = $sum->plus(DecimalValue::fromString($supply['value_brl']));
        }
        if ($sum->compareTo(DecimalValue::fromString($operation['declared_gross_brl'])) !== 0) {
            return $this->stop('B41_SUPPLY_VALUES_DO_NOT_RECONCILE');
        }
        usort($supplies, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
        $results = [];
        $totals = ['taxable_base_brl' => '0.00', 'cbs_brl' => '0.00', 'ibs_brl' => '0.00'];
        $complete = true;
        $cache = [];
        foreach ($supplies as $supply) {
            $result = $this->supply($supply, $operation, $cache);
            $results[] = ['id' => $supply['id'], 'value_brl' => $supply['value_brl'], ...$result];
            if ($result['status'] !== 'ASSESSED') {
                $complete = false;

                continue;
            }
            foreach (['taxable_base_brl' => $result['taxable_base'], 'cbs_brl' => $result['cbs'], 'ibs_brl' => $result['ibs']] as $key => $amount) {
                $totals[$key] = DecimalValue::fromString($totals[$key])->plus(DecimalValue::fromString($amount))->rounded(2)->value();
            }
        }
        $report = ['contract' => self::CONTRACT, 'status' => $complete ? 'LOCAL_COMBINED_REGIME_SUPPLIES_ASSESSED' : 'ACTION_REQUIRED',
            'reason' => $complete ? 'B41_EACH_SUPPLY_DECIDED_UNDER_ITS_OWN_REGIME' : 'B41_COMPONENT_ASSESSMENT_REQUIRED',
            'operation_input' => $operation, 'supplies' => $results, 'totals' => $complete ? $totals : null,
            'documents' => $complete ? count($results) : null, 'rounding' => 'SUM_OF_PER_SUPPLY_HALF_UP_2; NO_BLENDED_RATE',
            'iss_assessed' => false, 'official' => false, 'posted' => false];

        return [...$report, 'report_hash' => CanonicalJson::hash($report)];
    }

    /**
     * @param  array<string, mixed>  $supply
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $cache
     * @return array<string, mixed>
     */
    private function supply(array $supply, array $operation, array &$cache): array
    {
        $scenario = $supply['scenario'] ?? null;
        $bundle = $supply['bundle'] ?? null;
        if (! is_array($scenario) || ! is_array($scenario['facts'] ?? null) || ! is_string($bundle)
            || preg_match('/\Abundles\/[A-Za-z0-9._-]+\.json\z/', $bundle) !== 1) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'B41_SCENARIO_AND_INSTALLED_BUNDLE_REQUIRED'];
        }
        $facts = $scenario['facts'];
        if (($facts['rtc.supplier_id'] ?? null) !== $operation['supplier_id'] || ($facts['rtc.acquirer_id'] ?? null) !== $operation['acquirer_id']
            || ($facts['rtc.gross_amount'] ?? null) !== $supply['value_brl'] || ($scenario['scenario_id'] ?? null) !== $supply['id']) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'B41_SCENARIO_BINDING_CONFLICT'];
        }
        try {
            $cache[$bundle] ??= $this->factory->captureKnowledge($this->resourceRoot, $bundle);
            $loaded = $this->factory->scenarioLoaderFromKnowledge($cache[$bundle])->fromArray($scenario);
            $rtc = (new RtcScenarioRunService($this->factory))->evaluate($cache[$bundle], $loaded)->toArray();
        } catch (Throwable $exception) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'B41_COMPONENT_INPUT_OR_KNOWLEDGE_INVALID', 'detail' => $exception->getMessage()];
        }
        if ($rtc['status'] !== 'VALID' || ($rtc['decision']['base']['gross'] ?? null) !== $supply['value_brl']) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'B41_COMPONENT_FISCAL_DEPENDENCY', 'rtc_reasons' => $rtc['reason_codes'] ?? []];
        }
        $decision = $rtc['decision'];

        return ['status' => 'ASSESSED', 'bundle' => $bundle, 'regime' => $decision['treatment']['regime'] ?? 'GENERAL',
            'cclasstrib' => $decision['classification']['cclasstrib'], 'cindop' => $decision['classification']['cindop'],
            'rate_reduction_percent' => $decision['treatment']['rate_reduction_percent'] ?? '0.00',
            'taxable_base' => $decision['base']['taxable'], 'cbs' => $decision['cbs']['nominal_amount'], 'ibs' => $decision['ibs']['nominal_amount'],
            'document_issuer_fields' => $decision['document']['issuer_fields'], 'decision_hash' => CanonicalJson::hash($decision)];
    }

    private function money(mixed $value): bool
    {
        return is_string($value) && preg_match('/^(?:0|[1-9][0-9]*)\.[0-9]{2}$/D', $value) === 1;
    }

    private function identifier(mixed $value): bool
    {
        return is_string($value) && $value !== '' && trim($value) === $value && preg_match('/[\x00-\x1f\x7f]/', $value) !== 1;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['contract' => self::CONTRACT, 'status' => 'ACTION_REQUIRED', 'reason' => $reason, 'totals' => null, 'official' => false];
    }
}
