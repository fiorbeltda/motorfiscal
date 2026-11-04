<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Facts\FactSourceType;
use App\Modules\Fiscal\Core\Facts\FactValue;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;

/** A-owned caller of B contracts; activation belongs to a new B catalog. */
final readonly class CreditIntegration155
{
    public const KEY = 'credit_integration_a155';

    public const POLICY = ['version' => 'A155', 'prohibition' => 'B13', 'presumed' => 'B17',
        'period_cycle' => 'SEPARATE_B11_B14', 'automatic_appropriation' => false];

    /** @param array<string, mixed> $codes */
    public static function required(array $codes): bool
    {
        return is_array($codes[self::KEY] ?? null)
            && CanonicalJson::hash($codes[self::KEY]) === CanonicalJson::hash(self::POLICY);
    }

    /** The ordinary-treatment qualifier does not assess the separately requested B17 claim.
     * @param  array<string, mixed>  $codes
     */
    public function ordinaryFacts(FactBag $facts, array $codes): FactBag
    {
        $request = $facts->get('rtc.presumed_credit_requested');
        if (! self::required($codes) || $request === null || $request->value !== true) {
            return $facts;
        }

        return $facts->withReplaced(new FactValue($request->key, false, FactSourceType::DERIVED,
            $request->effectiveFrom, $request->effectiveTo, $request->recordedAt,
            confidence: $request->confidence, invalidatedAt: $request->invalidatedAt));
    }

    /**
     * @param  array<string, mixed>  $codes
     * @param  array<string, mixed>|null  $treatment
     * @return array<string, mixed>
     */
    public function resolve(FactBag $facts, string $market, bool $qualifyImport, ?array $treatment, array $codes, LocalDate $on): array
    {
        $explain = CreditZeroSemanticsPolicyB02::required($codes);
        foreach ([self::KEY => self::required($codes), CreditZeroSemanticsPolicyB02::KEY => $explain] as $key => $valid) {
            if (array_key_exists($key, $codes) && ! $valid) {
                return $this->stop('INVALID', 'RTC_CREDIT_INTEGRATION_POLICY_INVALID');
            }
        }
        $qualification = null;
        $specificDebits = null;
        $presumed = null;
        if (self::required($codes) && $facts->value('rtc.credit_context') === 'INPUT') {
            if ($facts->value('rtc.acquired_regime') === null) {
                return $this->stop('ACTION_REQUIRED', 'RTC_ACQUIRED_REGIME_REQUIRED', ['rtc.acquired_regime']);
            }
            $input = ['acquired_regime' => $facts->value('rtc.acquired_regime'), 'role' => 'ACQUIRER',
                'fuel_destination' => $facts->value('rtc.fuel_destination'),
                'exception_evidence_id' => $facts->value('rtc.credit_exception_evidence_id'),
                'acquirer_regime' => $facts->value('rtc.acquirer_regime')];
            foreach (['employee_collective_agreement_plan', 'saf_sports_rights_acquisition'] as $key) {
                $value = $facts->value('rtc.'.$key);
                if ($value !== null && ! is_bool($value)) {
                    return $this->stop('INVALID', 'RTC_CREDIT_EXCEPTION_FACT_TYPE_INVALID');
                }
                $input[$key] = $value;
            }
            if ($facts->value('rtc.financial_permission_article') !== null) {
                $input['expressly_permitted_article'] = $facts->value('rtc.financial_permission_article');
            }
            $qualification = (new AcquirerCreditProhibitionCatalogB13)->qualify($input);
            if (in_array($qualification['status'], ['INVALID', 'ACTION_REQUIRED'], true)) {
                $missing = array_map(fn (string $key): string => 'rtc.'.$key,
                    $qualification['missing_facts'] ?? []);

                return $this->stop($qualification['status'], $qualification['reason'], array_values($missing)) + ['integration155' => ['prohibition' => $qualification]];
            }
            if ($qualification['status'] === 'CREDIT_PROHIBITED') {
                $component = ['status' => 'DENIED', 'amount' => '0.00', 'reason' => $qualification['reason'],
                    'legal_basis' => $qualification['legal_basis']];
                if ($explain) {
                    $component += ['zero_semantics_version' => 'B02', 'zero_causes' => [$qualification['reason']],
                        'amount_semantics' => 'FINAL_DENIAL_ZERO'];
                }

                return ['status' => 'RESOLVED', 'ibs' => $component, 'cbs' => $component,
                    'integration155' => ['prohibition' => $qualification, 'presumed' => null]];
            }
            if ($qualification['status'] === 'CREDIT_LIMITED_TO_SUPPLIER_EXTINGUISHED_DEBITS') {
                $debits = $facts->value('rtc.supplier_specific_credit_debits');
                if (! is_array($debits) || ! array_is_list($debits) || count($debits) !== 2) {
                    return $this->stop('ACTION_REQUIRED', 'RTC_SUPPLIER_SPECIFIC_EXTINGUISHED_DEBITS_REQUIRED', ['rtc.supplier_specific_credit_debits']);
                }
                $seen = [];
                foreach ($debits as $debit) {
                    if (! is_array($debit) || ! in_array($debit['tax'] ?? null, ['IBS', 'CBS'], true)
                        || isset($seen[$debit['tax']]) || ($debit['document_id'] ?? null) !== $facts->value('rtc.input_document_id')
                        || ($debit['supplier_id'] ?? null) !== $facts->value('rtc.supplier_id')
                        || ($debit['extinguished'] ?? null) !== true || ! is_string($debit['evidence_id'] ?? null)
                        || preg_match('/\A[^\p{Z}\p{C}\s]+\z/u', $debit['evidence_id']) !== 1
                        || ! is_string($debit['amount'] ?? null) || preg_match('/\A\d{1,15}(?:\.\d{1,2})?\z/', $debit['amount']) !== 1
                        || $debit['amount'] !== $facts->value($debit['tax'] === 'IBS' ? 'rtc.input_ibs_amount' : 'rtc.input_cbs_amount')) {
                        return $this->stop('INVALID', 'RTC_SUPPLIER_SPECIFIC_CREDIT_DEBIT_CONFLICT', ['rtc.supplier_specific_credit_debits']);
                    }
                    $seen[$debit['tax']] = true;
                }
                $specificDebits = $debits;
            }
            $claims = $facts->value('rtc.presumed_credit_claims');
            if ($facts->value('rtc.presumed_credit_requested') === true && (! is_array($claims) || count($claims) !== 1)) {
                return $this->stop('ACTION_REQUIRED', 'RTC_PRESUMED_CLAIM_REQUIRED', ['rtc.presumed_credit_claims']);
            }
            if ($claims !== null) {
                if (! is_array($claims) || ! array_is_list($claims) || count($claims) > 1) {
                    return $this->stop('INVALID', 'RTC_PRESUMED_SINGLE_CLAIM_REQUIRED');
                }
                if ($claims !== []) {
                    if ($facts->value('rtc.presumed_credit_requested') !== true) {
                        return $this->stop('INVALID', 'RTC_PRESUMED_REQUEST_CONFLICT');
                    }
                    $claim = $claims[0];
                    if (! is_array($claim) || array_is_list($claim)
                        || ($claim['acquisition_date'] ?? null) !== $on->value()
                        || ($claim['document_id'] ?? null) !== $facts->value('rtc.input_document_id')
                        || ($claim['acquirer_id'] ?? null) !== $facts->value('rtc.acquirer_id')
                        || ($claim['acquirer_regime'] ?? null) !== $facts->value('rtc.acquirer_regime')) {
                        return $this->stop('INVALID', 'RTC_PRESUMED_CLAIM_SCOPE_CONFLICT');
                    }
                    $presumed = (new PresumedCreditCatalogB17)->qualify($claim);
                    if (in_array($presumed['status'], ['INVALID', 'ACTION_REQUIRED'], true)) {
                        return $this->stop($presumed['status'], $presumed['reason'], ['rtc.presumed_credit_claims'])
                            + ['integration155' => ['prohibition' => $qualification, 'presumed' => $presumed]];
                    }
                }
            }
        }
        $result = (new CreditDecisionEngine)->resolve($facts, $market, $qualifyImport, $treatment, $explain);
        if ($qualification !== null) {
            $result['integration155'] = ['prohibition' => $qualification, 'presumed' => $presumed,
                ...($specificDebits === null ? [] : ['qualified_supplier_debits' => $specificDebits]),
                'presumed_appropriated' => false, 'ordinary_and_presumed_added' => false];
        }

        return $result;
    }

    /**
     * @param  list<string>  $missing
     * @return array<string, mixed>
     */
    private function stop(string $status, string $reason, array $missing = []): array
    {
        return ['status' => $status, 'reason' => $reason, 'missing_facts' => $missing];
    }
}
