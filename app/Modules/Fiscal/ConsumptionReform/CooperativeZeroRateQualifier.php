<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class CooperativeZeroRateQualifier
{
    /** @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    public function resolve(FactBag $facts, LocalDate $on, DateTimeImmutable $knownAt, array $rule): array
    {
        if (($rule['strategy'] ?? null) !== 'LC214_ART271_COOPERATIVE_SERVICE_ZERO'
            || ($rule['status'] ?? null) !== 'ACTIVE' || ! isset($rule['published_at'], $rule['valid_from'])
            || ! is_string($rule['source_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $rule['source_hash'])
            || $knownAt < new DateTimeImmutable($rule['published_at']) || $on->value() < $rule['valid_from']
            || (isset($rule['valid_to']) && $on->value() > $rule['valid_to'])
            || (isset($rule['revoked_at']) && $knownAt >= new DateTimeImmutable($rule['revoked_at']))
            || (isset($rule['superseded_at']) && $knownAt >= new DateTimeImmutable($rule['superseded_at']))) {
            return $this->stop('ACTION_REQUIRED', 'RULE_NOT_AVAILABLE');
        }
        $input = $facts->value('rtc.cooperative_zero_proof');
        if ($input === null) {
            return $this->stop('ACTION_REQUIRED', 'PROOF_MISSING');
        }
        if (! is_array($input) || ! array_is_list($input) || count($input) !== 1 || ! is_array($input[0])) {
            return $this->stop('INVALID', 'PROOF_INVALID');
        }
        $proof = $input[0];
        if (($proof['supplier_id'] ?? null) !== $facts->value('rtc.supplier_id')
            || ($proof['acquirer_id'] ?? null) !== $facts->value('rtc.acquirer_id')
            || ($proof['document_id'] ?? null) !== $facts->value('rtc.document_id')) {
            return $this->stop('INVALID', 'PARTIES_OR_DOCUMENT_CONFLICT');
        }
        if ($facts->value('rtc.operation_kind') !== 'SERVICE' || $facts->value('rtc.market') !== 'DOMESTIC'
            || $facts->value('rtc.consideration') !== 'ONEROUS' || $facts->value('rtc.supplier_regime') !== 'REGULAR'
            || ($proof['service_category'] ?? null) !== 'NON_FINANCIAL') {
            return $this->stop('ACTION_REQUIRED', 'OTHER_OPERATION_RULE_REQUIRED');
        }
        try {
            $from = LocalDate::fromString((string) ($proof['valid_from'] ?? ''))->value();
            $to = LocalDate::fromString((string) ($proof['valid_to'] ?? ''))->value();
            $observed = LocalDate::fromString((string) ($proof['observed_on'] ?? ''))->value();
            $exercise = LocalDate::fromString((string) ($proof['option_exercised_on'] ?? ''))->value();
        } catch (InvalidArgumentException) {
            return $this->stop('INVALID', 'DATES_INVALID');
        }
        if ($from > $to || $exercise > $from || $observed > $knownAt->format('Y-m-d') || $observed < $exercise) {
            return $this->stop('INVALID', 'OPTION_PERIOD_CONFLICT');
        }
        if ($on->value() < $from || $on->value() > $to) {
            return $this->stop('ACTION_REQUIRED', 'PROOF_OUTSIDE_PERIOD');
        }
        if (($proof['option_at_start_of_operations'] ?? null) === true) {
            try {
                $start = LocalDate::fromString((string) ($proof['operations_started_on'] ?? ''))->value();
            } catch (InvalidArgumentException) {
                return $this->stop('INVALID', 'START_DATE_INVALID');
            }
            if ($exercise !== $start || $from < $start) {
                return $this->stop('INVALID', 'START_OPTION_CONFLICT');
            }
        } elseif (($proof['option_at_start_of_operations'] ?? null) === false) {
            if ((int) substr($exercise, 0, 4) !== (int) substr($from, 0, 4) - 1) {
                return $this->stop('INVALID', 'PRIOR_YEAR_OPTION_CONFLICT');
            }
        } else {
            return $this->stop('ACTION_REQUIRED', 'OPTION_TIMING_UNPROVEN');
        }
        foreach (['option_registered_and_effective', 'membership_or_participation_valid'] as $condition) {
            if (! is_bool($proof[$condition] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'CONDITION_UNPROVEN:'.$condition);
            }
            if ($proof[$condition] !== true) {
                return $this->stop('INVALID', 'CONDITION_NOT_MET:'.$condition);
            }
        }
        foreach (['cooperative_registry_evidence_id', 'option_registration_evidence_id', 'option_regulatory_validation_evidence_id',
            'membership_evidence_id', 'service_scope_evidence_id'] as $key) {
            if (! is_string($proof[$key] ?? null) || trim($proof[$key]) === '') {
                return $this->stop('ACTION_REQUIRED', 'EVIDENCE_MISSING:'.$key);
            }
        }
        $route = $proof['route'] ?? null;
        $supplier = $facts->value('rtc.supplier_id');
        $acquirer = $facts->value('rtc.acquirer_id');
        if ($route === 'MEMBER_TO_COOPERATIVE') {
            if (($proof['cooperative_id'] ?? null) !== $acquirer || ($proof['member_id'] ?? null) !== $supplier) {
                return $this->stop('INVALID', 'ROUTE_PARTIES_CONFLICT');
            }
            $article = 'LC214 art.271 I';
        } elseif ($route === 'COOPERATIVE_TO_REGULAR_MEMBER') {
            if (($proof['cooperative_id'] ?? null) !== $supplier || ($proof['member_id'] ?? null) !== $acquirer) {
                return $this->stop('INVALID', 'ROUTE_PARTIES_CONFLICT');
            }
            if ($facts->value('rtc.acquirer_regime') !== 'REGULAR') {
                return $this->stop('INVALID', 'MEMBER_REGULAR_REGIME_REQUIRED');
            }
            $article = 'LC214 art.271 II';
        } elseif (($rule['network_routes'] ?? false) === true && $route === 'COOPERATIVE_TO_COOPERATIVE') {
            $types = ['SINGLE', 'CENTRAL', 'FEDERATION', 'CONFEDERATION'];
            if (($proof['cooperative_id'] ?? null) !== $supplier
                || ! in_array($proof['supplier_cooperative_type'] ?? null, $types, true)
                || ! in_array($proof['acquirer_cooperative_type'] ?? null, $types, true)) {
                return $this->stop('INVALID', 'NETWORK_PARTIES_CONFLICT');
            }
            foreach (['supplier_registry_evidence_id', 'acquirer_registry_evidence_id', 'network_operation_evidence_id'] as $key) {
                if (! is_string($proof[$key] ?? null) || trim($proof[$key]) === '') {
                    return $this->stop('ACTION_REQUIRED', 'NETWORK_EVIDENCE_MISSING:'.$key);
                }
            }
            $article = 'LC214 art.271 paragraph1 I: cooperative network';
        } elseif (($rule['network_routes'] ?? false) === true && $route === 'BANK_TO_PARTICIPATING_COOPERATIVE') {
            if (($proof['cooperative_id'] ?? null) !== $acquirer || ($proof['cooperative_bank_id'] ?? null) !== $supplier
                || ($proof['participating_cooperative_id'] ?? null) !== $acquirer
                || ! in_array($proof['acquirer_cooperative_type'] ?? null, ['SINGLE', 'CENTRAL', 'FEDERATION', 'CONFEDERATION'], true)) {
                return $this->stop('INVALID', 'BANK_PARTICIPANT_CONFLICT');
            }
            foreach (['cooperative_bank_registry_evidence_id', 'bank_participation_evidence_id', 'bank_originated_operation_evidence_id'] as $key) {
                if (! is_string($proof[$key] ?? null) || trim($proof[$key]) === '') {
                    return $this->stop('ACTION_REQUIRED', 'NETWORK_EVIDENCE_MISSING:'.$key);
                }
            }
            $article = 'LC214 art.271 paragraph1 I: originating cooperative bank';
        } else {
            return $this->stop('ACTION_REQUIRED', 'ROUTE_NOT_MATERIALIZED');
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_COOPERATIVE_ZERO_QUALIFIED', 'missing_facts' => [],
            'cst' => '200', 'cclasstrib' => '200020', 'rate_reduction_percent' => '100.00',
            'incidence' => 'TAXABLE', 'mechanism' => 'ZERO_RATE', 'regime' => 'COOPERATIVE_ART271',
            'source_id' => $rule['source_id'], 'source_hash' => $rule['source_hash'], 'source_article' => $article,
            'supplier_previous_credit_treatment' => 'PRESERVED_ART52_SUBJECT_TO_PROOF',
            'acquirer_ordinary_credit_treatment' => 'DENIED_ART49', 'credit_transfer' => 'NOT_REQUESTED_NO_AUTOMATIC_ART272_TRANSFER',
            'proof' => $proof];
    }

    /** @return array<string, mixed> */
    private function stop(string $status, string $reason): array
    {
        return ['status' => $status, 'reason' => 'RTC_COOPERATIVE_ZERO_'.$reason,
            'missing_facts' => $status === 'ACTION_REQUIRED' ? ['rtc.cooperative_zero_proof'] : []];
    }
}
