<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use DateTimeImmutable;
use InvalidArgumentException;

/** Recomputes own taxes and RTC allocation; never derives contractual prices from RTC shares. */
final readonly class DocumentOwnTaxBridge135
{
    public const AVAILABLE_AT = '2026-10-23T11:11:35+00:00';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => 'DOCUMENT-ALL-OWN-TAXES-RTC-BRIDGE-135-V1', 'available_at' => self::AVAILABLE_AT,
            'own_revenue_policy' => SoftwareOwnRevenue135::policy(),
            'scope' => 'BRL_PARTIAL_EXPORT_RTC_ALREADY_EXCLUDES_LEGACY_TAXES',
            'price_rule' => 'COMPOSED_DOCUMENT_PRICE_LESS_ALL_OWN_INCIDENT_TAXES_EQUALS_RTC_LEG'];
    }

    /** @param array<string, mixed> $iss Fresh134 stage produced from these exact facts.
     * @param  array<array-key, mixed>  $ownProofs
     * @param  array<string, mixed>  $allocation
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function reconcile(FactBag $facts, array $iss, array $ownProofs, array $allocation, array $policy, DateTimeImmutable $known): array
    {
        if (CanonicalJson::encode($policy) !== CanonicalJson::encode(self::policy()) || $known < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return $this->stop('EXACT_KNOWN_POLICY_REQUIRED');
        }
        if ($facts->value('rtc.market') !== 'EXPORT' || $facts->value('rtc.gross_currency') !== 'BRL'
            || $facts->value('rtc.legacy_tax_inclusion') !== 'EXCLUDED' || $facts->value('rtc.base_adjustments') !== []) {
            return $this->stop('EXPLICIT_BRL_RTC_BASE_ALREADY_EXCLUDES_LEGACY_REQUIRED');
        }
        try {
            $context = DocumentPriceFactProjection112::from(DocumentIssIntegration134::withoutProofCarrier($facts));
        } catch (InvalidArgumentException) {
            return $this->stop('FRESH_ISS_MUST_MATCH_CURRENT_FACTS');
        }
        if (($iss['qualification_context_facts_hash'] ?? null) !== CanonicalJson::hash($context['facts'])) {
            return $this->stop('FRESH_ISS_MUST_MATCH_CURRENT_FACTS');
        }
        $grossValue = $facts->value('rtc.gross_amount');
        $shareValue = $facts->value('rtc.export_share_percent');
        if (! is_string($grossValue) || preg_match('/\A(?:0|[1-9][0-9]{0,14})\.[0-9]{2}\z/', $grossValue) !== 1
            || ! is_string($shareValue) || preg_match('/\A(?:0|[1-9][0-9]{0,2})(?:\.[0-9]{1,10})?\z/', $shareValue) !== 1) {
            return $this->stop('EXACT_GROSS_AND_PARTIAL_SHARE_REQUIRED');
        }
        $gross = DecimalValue::fromString($grossValue);
        $share = DecimalValue::fromString($shareValue);
        if ($gross->compareTo(DecimalValue::fromString('0.00')) <= 0 || $share->compareTo(DecimalValue::fromString('0')) <= 0
            || $share->compareTo(DecimalValue::fromString('100')) >= 0) {
            return $this->stop('POSITIVE_GROSS_AND_STRICT_PARTIAL_SHARE_REQUIRED');
        }
        foreach (['rtc.export_allocation_method', 'rtc.export_allocation_evidence_id', 'rtc.domestic_consumption_evidence_id'] as $key) {
            if (! is_string($facts->value($key)) || trim($facts->value($key)) === '') {
                return $this->stop('ALLOCATION_EVIDENCE_REQUIRED');
            }
        }
        $foreign = $gross->multipliedBy($share)->dividedBy(DecimalValue::fromString('100'), 10)->rounded(2);
        $expected = ['stage' => 'PARTIAL_EXPORT_GROSS_ALLOCATION', 'source_article' => 'LC214 art. 80, §5º',
            'total_gross_brl' => $gross->value(), 'foreign_share_percent' => $share->value(),
            'foreign_gross_brl' => $foreign->value(), 'domestic_gross_brl' => $gross->minus($foreign)->rounded(2)->value(),
            'method' => $facts->value('rtc.export_allocation_method'), 'allocation_evidence_id' => $facts->value('rtc.export_allocation_evidence_id'),
            'domestic_consumption_evidence_id' => $facts->value('rtc.domestic_consumption_evidence_id')];
        if (CanonicalJson::encode($allocation) !== CanonicalJson::encode($expected)) {
            return $this->stop('FRESH_RTC_ALLOCATION_CONFLICT');
        }
        $own = (new SoftwareOwnRevenue135)->calculate($iss, $ownProofs, $policy['own_revenue_policy'], $known);
        if ($own['status'] !== 'LOCAL_SOFTWARE_OWN_REVENUE_LINES_CALCULATED') {
            return [...$this->stop('FRESH_OWN_TAX_CALCULATION_REQUIRED'), 'upstream_reason' => $own['reason']];
        }
        // A captured memo cannot depend on monetary proofs observed after it.
        foreach ([DocumentPriceFactProjection112::PRICE_PROOFS, DocumentPriceFactProjection112::COMPOSITION_PROOFS, DocumentIssIntegration134::PROOFS] as $key) {
            $priorProofs = $facts->value($key);
            if (! is_array($priorProofs) || $priorProofs === []) {
                return $this->stop('UPSTREAM_PROOF_OBSERVATIONS_REQUIRED');
            }
            foreach ($priorProofs as $prior) {
                $observed = $this->instant(is_array($prior) ? ($prior['observed_at'] ?? null) : null);
                foreach ($ownProofs as $proof) {
                    $ownObserved = $this->instant(is_array($proof) ? ($proof['observed_at'] ?? null) : null);
                    if ($observed === null || $ownObserved === null || $observed > $ownObserved) {
                        return $this->stop('OWN_MEMO_PREDATES_UPSTREAM_MONETARY_PROOFS');
                    }
                }
            }
        }
        $rows = [];
        $totalPrice = DecimalValue::fromString('0.00');
        $totalExclusions = DecimalValue::fromString('0.00');
        foreach ($own['documents'] as $document) {
            $role = $document['role'];
            $leg = $expected[$role === 'RTC_FOREIGN' ? 'foreign_gross_brl' : 'domestic_gross_brl'];
            $window = $document['legacy_reconciliation'];
            $net = $window['price_after_declared_legacy_exclusions_brl'];
            if ($net !== $leg) {
                return [...$this->stop('ALL_OWN_TAX_PRICE_DIFFERENCE_UNEXPLAINED'), 'role' => $role,
                    'own_net_candidate_brl' => $net, 'rtc_leg_brl' => $leg,
                    'difference_brl' => DecimalValue::fromString($net)->minus(DecimalValue::fromString($leg))->value(),
                    'fresh_own_calculation' => $own];
            }
            $exclusions = DecimalValue::fromString($window['declared_legacy_exclusion_brl']);
            $price = DecimalValue::fromString($net)->plus($exclusions);
            $rows[] = ['role' => $role, 'document_reference' => $document['document_reference'],
                'composed_document_price_brl' => $price->value(), 'own_incident_amounts_brl' => $document['own_incident_amounts_brl'],
                'own_exclusions_brl' => $exclusions->value(), 'rtc_leg_brl' => $leg,
                'own_line_proof_hash' => $document['proof_hash']];
            $totalPrice = $totalPrice->plus($price);
            $totalExclusions = $totalExclusions->plus($exclusions);
        }

        return ['status' => 'DOCUMENT_PRICES_ALL_OWN_TAXES_RTC_BRIDGE_BOUND', 'documents' => $rows,
            'document_price_total_brl' => $totalPrice->value(), 'own_exclusions_total_brl' => $totalExclusions->value(),
            'rtc_total_brl' => $gross->value(), 'fresh_own_calculation' => $own,
            'binding_hash' => CanonicalJson::hash(['rows' => $rows, 'own' => $own['binding_hash'], 'allocation' => $expected,
                'iss' => $iss['binding_hash'], 'facts' => $facts->canonicalValues(), 'policy' => $policy]),
            'contractual_prices_derived_from_rtc_share' => false, 'rtc_base_rewritten' => false, 'iss_base_rewritten' => false,
            'judicial_temporal_effects_approved' => false, 'proofs_authenticated' => false,
            'document_obligation_complete' => false, 'production_ready' => false];
    }

    private function instant(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value)) {
            return null;
        }
        $format = str_contains($value, '.') ? 'Y-m-d\TH:i:s.uP' : 'Y-m-d\TH:i:sP';
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

        return $date !== false && $date->format($format) === $value ? $date : null;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'OWN_BRIDGE135_'.$reason,
            'document_obligation_complete' => false, 'production_ready' => false];
    }
}
