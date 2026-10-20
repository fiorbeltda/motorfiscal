<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereDebtNoMovementXmlProjector;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;

/** Rebuild monthly proofs before reconciling the homogeneous initial financial composition. */
final readonly class DereFinancialPublicOfferCurrentBaseCompositionQualifier
{
    /** @param array<string, mixed> $operation
     * @param  array<string, mixed>  $month
     * @param  array<string, mixed>  $grossTaxes
     * @param  array<string, mixed>  $returnBinding
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public function qualify(array $operation, array $month, array $grossTaxes, DateTimeImmutable $knownAt, array $rules, string $annexPath, array $returnBinding, string $sources, bool $allowNoMovement = false, bool $allowEventVersions = false, bool $requirePeriodHistory = false): array
    {
        if (! is_file($annexPath) || hash_file('sha256', $annexPath) !== '7296b225e15fb4b395a41a9413ada1190cc2229d64334bf78d7b69f0fb8019f9') {
            return $this->stop('SEMANTIC_TABLE_SOURCE_UNPROVEN');
        }
        if (! is_string($month['evidence_scope'] ?? null) || ! str_starts_with($month['evidence_scope'], 'SYNTHETIC')
            || ($operation['status'] ?? null) !== 'RESOLVED' || ($grossTaxes['status'] ?? null) !== 'RESOLVED'
            || ($grossTaxes['supplier_id'] ?? null) !== ($operation['qualification']['supplier_id'] ?? null)
            || ($grossTaxes['period_end'] ?? null) !== ($operation['base']['period_end'] ?? null)
            || ($grossTaxes['base'] ?? null) !== ($operation['base']['net_base'] ?? null)) {
            return $this->stop('SYNTHETIC_OPERATION_AND_RATE_SCOPE_REQUIRED');
        }
        $projection = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $knownAt, $rules);
        if (($projection['status'] ?? null) !== 'PROJECTED') {
            return $projection;
        }
        if (($month['prerequisites']['D2101_required'] ?? null) !== true) {
            return $this->stop('D9121_CURRENT_RETURN_BINDING_REQUIRED');
        }
        $pgcc = $projection['pgcc_qualification'] ?? null;
        if (! is_array($pgcc) || ($pgcc['status'] ?? null) !== 'RESOLVED') {
            return $this->stop('QUALIFIED_PGCC_REQUIRED');
        }
        $debtProjection = $projection['debt_yield_projection'] ?? null;
        if ($debtProjection === null && $allowNoMovement) {
            $empty = $projection['debt_no_movement_projection'] ?? null;
            if (! is_array($empty) || ($empty['contract'] ?? null) !== DereDebtNoMovementXmlProjector::CONTRACT) {
                return $this->stop('NATIVE_NO_MOVEMENT_PROJECTION_REQUIRED');
            }
            // Native book/projection checks prove empty unmoved public accounts; explicit D9121 must still reconcile zero.
            $debtProjection = [...$empty, 'total_vApur' => '0.00'];
        }
        if ($requirePeriodHistory && ! array_key_exists('events', $returnBinding)) {
            return $this->stop('PUBLIC_EVENT_CHAIN_REQUIRED_BY_PERIOD_HISTORY_POLICY');
        }
        $versionBinding = null;
        if (array_key_exists('events', $returnBinding)) {
            if (! $allowEventVersions || ! is_array($returnBinding['events']) || array_key_exists('xml', $returnBinding)) {
                return $this->stop('PUBLIC_EVENT_VERSION_POLICY_OR_SCOPE_REQUIRED');
            }
            $periodHistory = null;
            if (array_key_exists('period_history', $returnBinding) && ! $requirePeriodHistory) {
                return $this->stop('PUBLIC_PERIOD_HISTORY_POLICY_REQUIRED');
            }
            if ($requirePeriodHistory) {
                $periodHistory = $returnBinding['period_history'] ?? null;
                if (! is_array($periodHistory)) {
                    return $this->stop('PUBLIC_PERIOD_HISTORY_REQUIRED');
                }
            }
            $versionBinding = (new DerePublicOfferActiveEventBusinessBinder)->bind($operation, $month, $knownAt,
                $rules, $returnBinding['events'], $returnBinding, $sources, $allowNoMovement, $periodHistory);
            if (($versionBinding['status'] ?? null) !== 'LOCAL_ACTIVE_PUBLIC_BUSINESS_BOUND') {
                return $versionBinding;
            }
            $debtReturn = $versionBinding['active_event']['reconciliation'];
            if (isset($returnBinding['expected_sequence'])
                && $returnBinding['expected_sequence'] !== $versionBinding['active_event']['sequence']) {
                return $this->stop('ACTIVE_PUBLIC_SEQUENCE_EXPECTATION_CONFLICT');
            }
        } else {
            if (! is_array($debtProjection) || ! is_string($debtProjection['xml'] ?? null)
                || ! is_string($returnBinding['xml'] ?? null) || ($returnBinding['synthetic'] ?? null) !== true
                || ($returnBinding['pgcc_source_hash'] ?? null) !== $pgcc['source_hash']
                || ($returnBinding['expected_sequence'] ?? null) !== '00') {
                return $this->stop('INITIAL_SYNTHETIC_D9121_AND_PGCC_BINDING_REQUIRED');
            }
            $origin = new \DOMDocument;
            $origin->loadXML($debtProjection['xml'], LIBXML_NONET);
            $originPath = new \DOMXPath($origin);
            $originPath->registerNamespace('d', 'http://www.dere.gov.br/schemas/evtDebOpOfPublic/v0_0_3');
            if ($originPath->evaluate('string(/d:DeRE/d:evtDebOpOfPublic/d:ideEvento/d:tpOper)') !== '1') {
                return $this->stop('D2101_VERSION_CHAIN_QUALIFICATION_REQUIRED');
            }
            $scope = ['synthetic' => true, 'supplier_id' => $operation['qualification']['supplier_id'],
                'period' => substr($operation['base']['period_end'], 0, 7),
                'origin_xml_sha256' => hash('sha256', $debtProjection['xml']), 'expected_sequence' => '00',
                'pgcc_receipt' => $returnBinding['pgcc_receipt'] ?? null];
            $debtReturn = (new DerePublicOfferDebtReturnXmlReader)->read($returnBinding['xml'], $debtProjection['xml'], $scope, $sources);
            if (($debtReturn['status'] ?? null) !== 'LOCAL_D9121_AGGREGATE_RECONCILED'
                || ($debtReturn['total_vApur'] ?? null) !== ($debtProjection['total_vApur'] ?? null)) {
                return $this->stop('D9121_NATIVE_PROJECTION_TOTAL_CONFLICT');
            }
        }
        // Exact observed Table11 rows, not a tax-code prefix classifier.
        $roles = ['311011101' => 'general_revenue', '311011103' => 'general_revenue',
            '411011105' => 'other_deductible_expenses', '411011201' => 'allocated_funding_expenses'];
        $excluded = ['110112001', '118915099', '110113001', '110113002'];
        $plan = array_column($pgcc['accounts'], null, 'cCta');
        $book = array_column($month['book']['accounts'], null, 'cCta');
        $entries = array_column($operation['base']['entries'], null, 'id');
        $totals = array_fill_keys(['general_revenue', 'other_deductible_expenses', 'allocated_funding_expenses'], BigDecimal::of('0'));
        $classification = null;
        $bindings = [];
        foreach ($projection['balance']['balance']['accounts'] as $account) {
            $id = $account['cCta'];
            $row = $plan[$id] ?? null;
            if (! is_array($row)) {
                return $this->stop('ACCOUNT_CLASSIFICATION_MISSING');
            }
            $code = $row['codTrib'];
            $value = BigDecimal::of($account['vApur']);
            if (in_array($code, $excluded, true)) {
                if (($row['codNat'] ?? null) !== '1') {
                    return $this->stop('EXCLUDED_ASSET_CLASSIFICATION_CONFLICT');
                }

                continue;
            }
            $role = $roles[$code] ?? null;
            if ($role === null) {
                return $this->stop('SEMANTIC_CODE_OR_MULTI_ACTIVITY_ALLOCATION_REQUIRED');
            }
            $revenue = $role === 'general_revenue';
            if (($account['natVApur'] ?? null) !== ($revenue ? 'C' : 'D') || $value->isNegative()) {
                return $this->stop('ACCOUNT_SIGN_OR_REVERSAL_COMPOSITION_REQUIRED');
            }
            if ($revenue) {
                $iss = $row['indTribISS'] ?? null;
                if (! in_array($iss, ['0', '1'], true)) {
                    return $this->stop('EXPLICIT_REVENUE_ISS_CLASSIFICATION_REQUIRED');
                }
                if ($classification !== null && $classification !== $iss) {
                    return $this->stop('MIXED_FORMING_CODE_ALLOCATION_REQUIRED');
                }
                $classification = $iss;
            }
            $linked = BigDecimal::of('0');
            $entryIds = $book[$id]['financial_entry_ids'] ?? [];
            foreach ($entryIds as $entryId) {
                $entry = $entries[$entryId] ?? null;
                if (is_array($entry) && ($entry['fiscal_origin'] ?? null) === 'D2101') {
                    $qualifiedDebt = $debtProjection['qualified_entries'][$entryId] ?? null;
                    if (! $revenue || ! is_array($qualifiedDebt)
                        || ($qualifiedDebt['result_account_code'] ?? null) !== $id
                        || ($entry['computed_amount'] ?? null) !== ($qualifiedDebt['taxable_amount'] ?? null)
                        || ($entry['book_apur_amount'] ?? null) !== '0.00') {
                        return $this->stop('D2101_ENTRY_NATIVE_RESULT_BINDING_CONFLICT');
                    }

                    continue;
                }
                if (! is_array($entry) || ($entry['bucket'] ?? null) !== ($revenue ? 'REVENUE' : 'DEDUCTION')
                    || ($entry['fiscal_origin'] ?? 'D1101') !== 'D1101'
                    || ($role === 'allocated_funding_expenses' && ($entry['kind'] ?? null) !== 'FUNDING_EXPENSE')) {
                    return $this->stop('ENTRY_ROLE_OR_DOCUMENT_ORIGIN_CONFLICT');
                }
                $linked = $linked->plus($entry['computed_amount']);
            }
            if (! $linked->isEqualTo($value)) {
                return $this->stop('ACCOUNT_TO_ENTRY_AMOUNT_CONFLICT');
            }
            $totals[$role] = $totals[$role]->plus($value);
            $bindings[] = ['cCta' => $id, 'codTrib' => $code, 'role' => $role,
                'amount' => (string) $value->toScale(2, RoundingMode::HalfEven), 'financial_entry_ids' => $entryIds];
        }
        if ($classification === null || ($operation['base']['art188_reversal'] ?? null) !== '0.00'
            || ($operation['base']['deferred'] ?? null) !== '0.00') {
            return $this->stop('CLASSIFICATION_OR_REVERSAL_SCOPE_REQUIRED');
        }
        $net = $totals['general_revenue']->plus($debtReturn['total_vApur'])->minus($totals['other_deductible_expenses'])->minus($totals['allocated_funding_expenses']);
        if ($classification !== '0' || ! $net->isEqualTo($operation['base']['net_base'])) {
            return $this->stop('FINANCIAL_NET_BASE_COMPOSITION_CONFLICT');
        }
        $rates = [];
        foreach (['IBS_STATE', 'IBS_MUNICIPAL', 'CBS'] as $tax) {
            $component = $grossTaxes['components'][$tax] ?? null;
            if (! is_array($component) || ! is_string($component['percent'] ?? null)
                || ! is_string($component['source_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $component['source_hash'])) {
                return $this->stop('RATE_COMPONENT_SOURCE_REQUIRED');
            }
            $rates[$tax] = $component['percent'];
        }
        $input = ['synthetic' => true, 'codBC' => '1010',
            'iss_incident' => false, 'transition_present' => false, 'selective_tax_incident' => false,
            'transition_revenue' => '0.00', 'export_reversal' => '0.00', 'cooperative_reversal' => '0.00',
            'public_offer_debt_return' => $debtReturn['total_vApur'], 'rates' => $rates];
        foreach ($totals as $field => $amount) {
            $input[$field] = (string) $amount->toScale(2, RoundingMode::HalfEven);
        }
        $calculation = (new DereFinancialCurrentBaseCalculator)->calculate($input);
        if (($calculation['status'] ?? null) !== 'LOCAL_CURRENT_BASE_ARITHMETIC_CALCULATED') {
            return $calculation;
        }

        return ['status' => 'LOCAL_MONTHLY_CURRENT_BASE_COMPOSITION_RECONCILED',
            'contract' => 'DERE-FINANCIAL-PUBLIC-OFFER-COMPOSITION-LOCAL-1.3.0-54', 'd9121_reconciliation' => $debtReturn,
            ...($versionBinding === null ? [] : ['active_version_binding' => $versionBinding]),
            'supplier_id' => $operation['qualification']['supplier_id'], 'period' => substr($operation['base']['period_end'], 0, 7),
            'operation_hash' => CanonicalJson::hash($operation), 'monthly_input_hash' => CanonicalJson::hash($month),
            'projection_hash' => CanonicalJson::hash($projection), 'gross_tax_hash' => CanonicalJson::hash($grossTaxes),
            'semantic_table_sha256' => hash_file('sha256', $annexPath), 'bindings' => $bindings,
            'calculation_input' => $input, 'calculation' => $calculation,
            'composition_locally_reconciled' => true, 'classification_external_verified' => false,
            'negative_base_consumption_applied' => false, 'eligible_for_posting' => false, 'production_ready' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_CURRENT_COMPOSITION_'.$reason,
            'eligible_for_posting' => false, 'production_ready' => false];
    }
}
