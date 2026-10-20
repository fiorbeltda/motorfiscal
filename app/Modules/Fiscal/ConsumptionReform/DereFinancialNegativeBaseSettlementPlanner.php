<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereRounding;
use Brick\Math\BigDecimal;
use DateTimeImmutable;

/** Local tax proposal over rebuilt current composition and active prior stock; never posts or closes. */
final readonly class DereFinancialNegativeBaseSettlementPlanner
{
    public const CONTRACT = 'DERE-BCN-LOCAL-SETTLEMENT-1.3.0-52';

    /** @param array<string, mixed> $operation
     * @param  array<string, mixed>  $month
     * @param  array<string, mixed>  $grossTaxes
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function plan(array $operation, array $month, array $grossTaxes, DateTimeImmutable $knownAt, array $rules, array $request, array $policy, string $sources): array
    {
        if (($policy['contract'] ?? null) !== self::CONTRACT || ($policy['status'] ?? null) !== 'SANDBOX_CANDIDATE'
            || ($policy['mode'] ?? null) !== 'EXPLICIT_SYNTHETIC_LOCAL_PROPOSAL_ONLY'
            || ($policy['production_ready'] ?? null) !== false || ($policy['rounding'] ?? null) !== 'DERE_HALF_EVEN_8_2'
            || ($policy['source_hash'] ?? null) !== CanonicalJson::hash(array_diff_key($policy, ['source_hash' => true]))) {
            return $this->stop('POLICY_UNPROVEN');
        }
        $published = is_string($policy['published_at'] ?? null)
            ? DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $policy['published_at']) : false;
        $end = $operation['base']['period_end'] ?? null;
        $start = $operation['base']['period_start'] ?? null;
        if ($published === false || $published > $knownAt || ! is_string($end) || ! is_string($start)
            || ! is_string($policy['valid_from'] ?? null) || $start < $policy['valid_from']
            || (isset($policy['valid_to']) && $end > $policy['valid_to'])
            || (isset($policy['revoked_at']) && $knownAt >= new DateTimeImmutable($policy['revoked_at']))
            || (isset($policy['superseded_at']) && $knownAt >= new DateTimeImmutable($policy['superseded_at']))) {
            return $this->stop('POLICY_NOT_AVAILABLE');
        }
        foreach (['schema_sha256' => 'dere-observed-20261006-cgibs-xsd-v1-3-0.zip',
            'mod_sha256' => 'dere-observed-20261005-mod.pdf', 'annex_i_sha256' => 'dere-observed-20261005-annex-i.pdf',
            'rounding_sha256' => 'dere-observed-20261005-validation.pdf'] as $field => $file) {
            if (! is_file($sources.'/'.$file) || ($policy[$field] ?? null) !== hash_file('sha256', $sources.'/'.$file)) {
                return $this->stop('OFFICIAL_SOURCE_CONFLICT');
            }
        }
        $supplier = $operation['qualification']['supplier_id'] ?? null;
        $period = substr($end, 0, 7);
        if (($request['synthetic'] ?? null) !== true || ! is_string($supplier)
            || ($request['supplier_id'] ?? null) !== $supplier || ($request['period'] ?? null) !== $period
            || ($request['operation_hash'] ?? null) !== CanonicalJson::hash($operation)
            || ($request['monthly_input_hash'] ?? null) !== CanonicalJson::hash($month)
            || ($request['gross_tax_hash'] ?? null) !== CanonicalJson::hash($grossTaxes)
            || ! is_array($request['parameters'] ?? null) || ! is_array($request['prior_closing_events'] ?? null)) {
            return $this->stop('REQUEST_BINDING_OR_SCOPE_UNPROVEN');
        }
        $composition = (new DereFinancialCurrentBaseCompositionQualifier)->qualify($operation, $month, $grossTaxes, $knownAt,
            $rules, $sources.'/dere-observed-20261005-annex-i.pdf');
        if (($composition['status'] ?? null) !== 'LOCAL_MONTHLY_CURRENT_BASE_COMPOSITION_RECONCILED') {
            return $composition;
        }
        $priorPeriod = (new DateTimeImmutable($period.'-01'))->modify('-1 month')->format('Y-m');
        if (($request['prior_period'] ?? null) !== $priorPeriod) {
            return $this->stop('IMMEDIATELY_PRIOR_PERIOD_REQUIRED');
        }
        $events = $request['prior_closing_events'];
        if (! array_is_list($events)) {
            return $this->stop('PRIOR_EVENT_LIST_REQUIRED');
        }
        foreach ($events as $event) {
            if (! is_array($event) || array_any(array_keys($event), static fn (mixed $key): bool => ! is_string($key))) {
                return $this->stop('PRIOR_EVENT_LIST_REQUIRED');
            }
        }
        /** @var list<array<string, mixed>> $events */
        $chain = (new DereNegativeBaseClosingVersionReconciler)->reconcile($events, $supplier, $priorPeriod, $sources, true);
        if (($chain['status'] ?? null) !== 'LOCAL_CLOSING_VERSION_CHAIN_RECONCILED') {
            return $chain;
        }
        $pools = $composition['calculation']['pools'];
        $bases = array_map(static fn (array $pool): string => $pool['positive_base'], $pools);
        $consumption = (new DereNegativeBaseConsumptionPlanner)->plan($chain['active_return']['normalized_return'],
            $request['parameters'], $bases, $supplier, $period);
        if (($consumption['status'] ?? null) !== 'LOCAL_CONSUMPTION_PLANNED') {
            return $consumption;
        }
        $plans = array_column($consumption['plans'], null, 'codBCNRaiz');
        $assessed = [];
        foreach ($pools as $root => $pool) {
            $plan = $plans[$root] ?? null;
            // Official D1199/D9199 contract: omitted option means no prior BCN use for that root.
            $assessed[$root] = ['positive_base' => $pool['positive_base'], 'generated_negative' => $pool['generated_negative'],
                'deduction' => $plan['deduction'] ?? '0.00', 'assessed_base' => $plan['assessed_base'] ?? $pool['positive_base'],
                'allocations' => $plan['allocations'] ?? [], 'option_omitted' => $plan === null];
        }
        $uses = [];
        foreach ($consumption['plans'] as $plan) {
            foreach ($plan['allocations'] as $allocation) {
                $uses[$allocation['codBCN']] = $allocation['used'];
            }
        }
        $proposedStock = [];
        foreach ($chain['active_return']['stock_after_return'] as $row) {
            $used = $uses[$row['codBCN']] ?? '0.00';
            $proposedStock[] = [...$row, 'proposed_current_use' => $used,
                'available_after_proposed_consumption' => (string) BigDecimal::of($row['available_after_return'])->minus($used)];
        }
        $code = $composition['calculation_input']['codBC'];
        $before = $after = [];
        $rounding = new DereRounding;
        foreach (['IBS_STATE', 'IBS_MUNICIPAL', 'CBS'] as $tax) {
            $root = $code.($tax === 'CBS' ? 'C' : 'I');
            $rate = $composition['calculation_input']['rates'][$tax];
            $before[$tax] = $rounding->taxAmount($assessed[$root]['positive_base'], $rate);
            $after[$tax] = ['percent' => $rate, 'assessed_base' => $assessed[$root]['assessed_base'],
                'nominal_amount' => $rounding->taxAmount($assessed[$root]['assessed_base'], $rate),
                'rate_source_id' => $grossTaxes['components'][$tax]['source_id'],
                'rate_source_hash' => $grossTaxes['components'][$tax]['source_hash']];
        }
        $ibs = (string) BigDecimal::of($after['IBS_STATE']['nominal_amount'])->plus($after['IBS_MUNICIPAL']['nominal_amount']);
        $dispensed = ($grossTaxes['collection_status'] ?? null) === 'DISPENSED_ART348_PARAGRAPH1';
        if (! in_array($grossTaxes['collection_status'] ?? null, ['DISPENSED_ART348_PARAGRAPH1', 'GROSS_DEBIT_BEFORE_PERIOD_SETTLEMENT'], true)) {
            return $this->stop('COLLECTION_CONDITION_REQUIRED');
        }

        return ['status' => 'LOCAL_BCN_TAX_SETTLEMENT_PROPOSED', 'contract' => self::CONTRACT,
            'supplier_id' => $supplier, 'period' => $period, 'request_hash' => CanonicalJson::hash($request),
            'policy_hash' => $policy['source_hash'], 'composition' => $composition, 'prior_chain' => $chain,
            'consumption' => $consumption, 'assessed_pools' => $assessed,
            'stock_after_proposed_consumption' => $proposedStock,
            'original_gross_debits' => $grossTaxes, 'original_gross_debits_hash' => CanonicalJson::hash($grossTaxes),
            'taxes_after_gross_down_before_bcn' => $before, 'proposed_tax_components' => $after,
            'ibs_nominal_proposal' => $ibs, 'cbs_nominal_proposal' => $after['CBS']['nominal_amount'],
            'collection_before_credit_offset_proposal' => ['IBS' => $dispensed ? '0.00' : $ibs,
                'CBS' => $dispensed ? '0.00' : $after['CBS']['nominal_amount']],
            'collection_status_preserved' => $grossTaxes['collection_status'], 'legacy_tax_effects_recalculated' => false,
            'scope' => 'SYNTHETIC_LOCAL_BCN_APURATION_PROPOSAL_NO_POSTING_OR_GOVERNMENT_RETURN',
            'received_return_verified' => false, 'expiry_boundary_qualified' => false,
            'dependent_periods_reprocessed' => false, 'eligible_for_posting' => false, 'production_ready' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_BCN_SETTLEMENT_'.$reason,
            'eligible_for_posting' => false, 'production_ready' => false];
    }
}
