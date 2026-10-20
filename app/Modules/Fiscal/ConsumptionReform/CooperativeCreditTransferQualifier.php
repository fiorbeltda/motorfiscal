<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class CooperativeCreditTransferQualifier
{
    /** @param array<string, mixed> $rule
     * @param  array<string, mixed>  $qualification
     * @return array<string, mixed>
     */
    public function resolve(FactBag $facts, LocalDate $on, DateTimeImmutable $knownAt, array $rule, array $qualification): array
    {
        if (($rule['strategy'] ?? null) !== 'LC214_ART272_COOPERATIVE_CREDIT_TRANSFER' || ($rule['status'] ?? null) !== 'ACTIVE'
            || ! isset($rule['published_at'], $rule['valid_from']) || $knownAt < new DateTimeImmutable($rule['published_at'])
            || $on->value() < $rule['valid_from'] || (isset($rule['valid_to']) && $on->value() > $rule['valid_to'])
            || (isset($rule['revoked_at']) && $knownAt >= new DateTimeImmutable($rule['revoked_at']))
            || (isset($rule['superseded_at']) && $knownAt >= new DateTimeImmutable($rule['superseded_at']))
            || ! is_string($rule['source_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $rule['source_hash'])) {
            return $this->stop('ACTION_REQUIRED', 'RULE_NOT_AVAILABLE');
        }
        if (($qualification['status'] ?? null) !== 'RESOLVED' || ($qualification['proof']['route'] ?? null) !== 'MEMBER_TO_COOPERATIVE'
            || $facts->value('rtc.supplier_regime') !== 'REGULAR') {
            return $this->stop('INVALID', 'MEMBER_SUPPLY_ROUTE_REQUIRED');
        }
        $input = $facts->value('rtc.cooperative_credit_transfer');
        if ($input === null) {
            return $this->stop('ACTION_REQUIRED', 'PROOF_MISSING');
        }
        if (! is_array($input) || ! array_is_list($input) || count($input) !== 1 || ! is_array($input[0])) {
            return $this->stop('INVALID', 'PROOF_INVALID');
        }
        $transfer = $input[0];
        if (($transfer['member_id'] ?? null) !== $facts->value('rtc.supplier_id')
            || ($transfer['cooperative_id'] ?? null) !== $facts->value('rtc.acquirer_id')
            || ($transfer['supply_document_id'] ?? null) !== $facts->value('rtc.document_id')
            || ($transfer['currency'] ?? null) !== 'BRL') {
            return $this->stop('INVALID', 'PARTIES_OR_DOCUMENT_CONFLICT');
        }
        foreach (['credit_pool_complete', 'credits_qualified_and_net_of_annulments', 'transfer_not_already_recorded'] as $key) {
            if ($key === 'transfer_not_already_recorded' && ($transfer[$key] ?? null) === false) {
                return $this->stop('INVALID', 'TRANSFER_ALREADY_RECORDED');
            }
            if (($transfer[$key] ?? null) !== true) {
                return $this->stop('ACTION_REQUIRED', 'CONDITION_UNPROVEN:'.$key);
            }
        }
        foreach (['credit_pool_evidence_id', 'transfer_regulatory_validation_evidence_id'] as $key) {
            if (! $this->proof($transfer[$key] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'EVIDENCE_MISSING:'.$key);
            }
        }
        if (! is_array($transfer['credits'] ?? null) || ! array_is_list($transfer['credits']) || $transfer['credits'] === []) {
            return $this->stop('ACTION_REQUIRED', 'CREDIT_POOL_MISSING');
        }
        $zero = DecimalValue::fromString('0.00');
        $totals = ['IBS' => $zero, 'CBS' => $zero];
        $availableTotals = $totals;
        $seen = [];
        $seenDocuments = [];
        $trace = [];
        try {
            $observed = LocalDate::fromString((string) ($transfer['observed_on'] ?? ''))->value();
            if ($observed > $knownAt->format('Y-m-d')) {
                return $this->stop('INVALID', 'OBSERVATION_CONFLICT');
            }
            foreach ($transfer['credits'] as $entry) {
                if (! is_array($entry) || ! $this->proof($entry['id'] ?? null) || isset($seen[$entry['id']])
                    || ! $this->proof($entry['acquisition_document_id'] ?? null) || ! in_array($entry['tax'] ?? null, ['IBS', 'CBS'], true)
                    || ($entry['owner_id'] ?? null) !== $transfer['member_id']
                    || ($entry['supply_document_id'] ?? null) !== $transfer['supply_document_id']) {
                    return $this->stop('INVALID', 'CREDIT_ENTRY_CONFLICT');
                }
                $seen[$entry['id']] = true;
                $documentKey = $entry['acquisition_document_id'].':'.$entry['tax'];
                if (isset($seenDocuments[$documentKey])) {
                    return $this->stop('INVALID', 'DUPLICATE_DOCUMENT_COMPONENT');
                }
                $seenDocuments[$documentKey] = true;
                foreach (['appropriation_evidence_id', 'production_link_evidence_id', 'available_balance_evidence_id'] as $key) {
                    if (! $this->proof($entry[$key] ?? null)) {
                        return $this->stop('ACTION_REQUIRED', 'CREDIT_EVIDENCE_MISSING:'.$key);
                    }
                }
                if (($entry['used_to_produce_this_supply'] ?? null) !== true) {
                    return $this->stop('INVALID', 'CREDIT_NOT_RELATED_TO_SUPPLY');
                }
                if (($entry['credit_kind'] ?? null) === 'ORDINARY') {
                    if (! $this->proof($entry['extinction_evidence_id'] ?? null)) {
                        return $this->stop('ACTION_REQUIRED', 'CREDIT_EXTINCTION_UNPROVEN');
                    }
                } elseif (($entry['credit_kind'] ?? null) === 'PRESUMED') {
                    if (! $this->proof($entry['presumed_rule_evidence_id'] ?? null)
                        || ($entry['presumed_appropriation_qualified'] ?? null) !== true) {
                        return $this->stop('ACTION_REQUIRED', 'PRESUMED_CREDIT_NOT_QUALIFIED');
                    }
                } else {
                    return $this->stop('INVALID', 'CREDIT_KIND_CONFLICT');
                }
                $date = LocalDate::fromString((string) ($entry['appropriation_date'] ?? ''))->value();
                if ($date < '2026-01-01' || $date > $on->value() || $date > $observed) {
                    return $this->stop('INVALID', 'APPROPRIATION_DATE_CONFLICT');
                }
                $expires = (new DateTimeImmutable(substr($date, 0, 7).'-01'))->modify('+1 month')->modify('+5 years')->format('Y-m-d');
                if ($on->value() >= $expires) {
                    return $this->stop('INVALID', 'CREDIT_EXPIRED');
                }
                $appropriated = $this->money($entry['appropriated_amount'] ?? null);
                $used = $this->money($entry['already_used_transferred_or_refunded'] ?? null);
                $unavailable = $this->money($entry['annulled_or_refund_reserved'] ?? null);
                $available = $appropriated->minus($used)->minus($unavailable);
                $requested = $this->money($entry['requested_transfer_amount'] ?? null);
                if ($available->isNegative() || $requested->compareTo($available) > 0) {
                    return $this->stop('INVALID', 'TRANSFER_EXCEEDS_AVAILABLE_CREDIT');
                }
                $tax = $entry['tax'];
                $totals[$tax] = $totals[$tax]->plus($requested);
                $availableTotals[$tax] = $availableTotals[$tax]->plus($available);
                $trace[] = [...$entry, 'available_amount' => $available->value(), 'remaining_after_requested_transfer' => $available->minus($requested)->value(), 'expires_on' => $expires];
            }
        } catch (InvalidArgumentException) {
            return $this->stop('INVALID', 'AMOUNT_OR_DATE_INVALID');
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_COOPERATIVE_CREDIT_TRANSFER_QUALIFIED', 'missing_facts' => [],
            'scope' => 'LOCAL_ELIGIBILITY_AND_AMOUNTS_NO_TRANSFER_OR_POSTING', 'source_hash' => $rule['source_hash'],
            'source_article' => 'LC214 art.272; original appropriation preserved art.54',
            'member_id' => $transfer['member_id'], 'cooperative_id' => $transfer['cooperative_id'],
            'supply_document_id' => $transfer['supply_document_id'], 'ibs_requested' => $totals['IBS']->value(),
            'cbs_requested' => $totals['CBS']->value(), 'ibs_available' => $availableTotals['IBS']->value(),
            'cbs_available' => $availableTotals['CBS']->value(), 'credits' => $trace,
            'transfer_performed' => false, 'posting_performed' => false];
    }

    private function proof(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function money(mixed $value): DecimalValue
    {
        if (! is_string($value) || ! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException('Nonnegative BRL amount required.');
        }

        return DecimalValue::fromString($value)->rounded(2);
    }

    /** @return array<string, mixed> */
    private function stop(string $status, string $reason): array
    {
        return ['status' => $status, 'reason' => 'RTC_COOPERATIVE_TRANSFER_'.$reason,
            'missing_facts' => $status === 'ACTION_REQUIRED' ? ['rtc.cooperative_credit_transfer'] : []];
    }
}
