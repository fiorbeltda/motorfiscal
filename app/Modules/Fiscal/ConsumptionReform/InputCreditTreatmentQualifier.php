<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class InputCreditTreatmentQualifier
{
    /** @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    public function resolve(FactBag $facts, LocalDate $on, DateTimeImmutable $knownAt, array $rule): array
    {
        if (($rule['strategy'] ?? null) !== 'LC214_ART49_50' || ($rule['status'] ?? null) !== 'ACTIVE'
            || ! is_string($rule['source_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $rule['source_hash'])
            || ! isset($rule['valid_from'], $rule['published_at']) || $on->value() < $rule['valid_from']
            || (isset($rule['valid_to']) && $on->value() > $rule['valid_to'])
            || $knownAt < new DateTimeImmutable($rule['published_at'])
            || (isset($rule['revoked_at']) && $knownAt >= new DateTimeImmutable($rule['revoked_at']))
            || (isset($rule['superseded_at']) && $knownAt >= new DateTimeImmutable($rule['superseded_at']))) {
            return $this->stop('ACTION_REQUIRED', 'RTC_INPUT_CREDIT_TREATMENT_RULE_NOT_AVAILABLE');
        }
        $missing = $facts->missing(['rtc.input_document_id', 'rtc.input_tax_treatments', 'rtc.presumed_credit_requested']);
        if ($missing !== []) {
            return $this->stop('ACTION_REQUIRED', 'RTC_INPUT_CREDIT_TREATMENT_PROOF_MISSING', $missing);
        }
        if ($facts->value('rtc.presumed_credit_requested') === true) {
            return $this->stop('ACTION_REQUIRED', 'RTC_PRESUMED_CREDIT_RULE_NOT_PROVEN');
        }
        $rows = $facts->value('rtc.input_tax_treatments');
        if (! is_array($rows) || ! array_is_list($rows) || count($rows) !== 2) {
            return $this->stop('INVALID', 'RTC_INPUT_CREDIT_TREATMENT_COMPONENTS_INVALID');
        }
        $components = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! in_array($row['tax'] ?? null, ['IBS', 'CBS'], true)
                || isset($components[$row['tax']]) || ! is_string($row['evidence_id'] ?? null) || trim($row['evidence_id']) === ''
                || ($row['document_id'] ?? null) !== $facts->value('rtc.input_document_id')
                || ! in_array($row['treatment'] ?? null, ['STANDARD', 'REDUCED_30', 'REDUCED_60', 'IMMUNITY', 'EXEMPT', 'ZERO_RATE', 'DEFERRAL', 'SUSPENSION'], true)) {
                return $this->stop('INVALID', 'RTC_INPUT_CREDIT_TREATMENT_COMPONENT_INVALID');
            }
            $tax = $row['tax'];
            $component = ['treatment' => $row['treatment'], 'status' => 'ORDINARY', 'source_article' => 'LC214 art. 47',
                'document_id' => $row['document_id'], 'evidence_id' => $row['evidence_id']];
            if (in_array($row['treatment'], ['IMMUNITY', 'EXEMPT', 'ZERO_RATE', 'DEFERRAL', 'SUSPENSION'], true)) {
                $component['status'] = 'DENIED';
                $component['source_article'] = 'LC214 art. 49';
                $component['maximum_principal_credit'] = '0.00';
            }
            if ($row['treatment'] === 'SUSPENSION') {
                if (! is_bool($row['suspension_demanded'] ?? null)) {
                    return $this->stop('ACTION_REQUIRED', 'RTC_SUSPENDED_INPUT_DEMAND_PROOF_MISSING', ['rtc.input_tax_treatments']);
                }
                if ($row['suspension_demanded'] === true) {
                    foreach (['demand_evidence_id'] as $key) {
                        if (! is_string($row[$key] ?? null) || trim($row[$key]) === '') {
                            return $this->stop('ACTION_REQUIRED', 'RTC_SUSPENDED_INPUT_PRINCIPAL_PROOF_MISSING', ['rtc.input_tax_treatments']);
                        }
                    }
                    try {
                        $demanded = $this->money($row['demanded_principal'] ?? null);
                        $extinguished = $this->money($row['extinguished_principal'] ?? null);
                        $additions = $this->money($row['legal_additions'] ?? null);
                    } catch (InvalidArgumentException) {
                        return $this->stop('INVALID', 'RTC_SUSPENDED_INPUT_PRINCIPAL_INVALID');
                    }
                    if ($demanded->compareTo(DecimalValue::fromString('0')) <= 0 || $extinguished->compareTo($demanded) > 0) {
                        return $this->stop('INVALID', 'RTC_SUSPENDED_INPUT_EXTINCTION_CONFLICT');
                    }
                    $hasExtinction = $extinguished->compareTo(DecimalValue::fromString('0')) > 0;
                    if ($hasExtinction) {
                        foreach (['extinction_evidence_id', 'extinction_date'] as $key) {
                            if (! is_string($row[$key] ?? null) || trim($row[$key]) === '') {
                                return $this->stop('ACTION_REQUIRED', 'RTC_SUSPENDED_INPUT_PRINCIPAL_PROOF_MISSING', ['rtc.input_tax_treatments']);
                            }
                        }
                        try {
                            $extinction = LocalDate::fromString($row['extinction_date']);
                        } catch (InvalidArgumentException) {
                            return $this->stop('INVALID', 'RTC_SUSPENDED_INPUT_PRINCIPAL_INVALID');
                        }
                        if ($extinction->value() > $on->value() || $extinction->value() > $knownAt->format('Y-m-d')
                            || $facts->value('rtc.prior_tax_extinguished') === false) {
                            return $this->stop('INVALID', 'RTC_SUSPENDED_INPUT_EXTINCTION_CONFLICT');
                        }
                    }
                    $component = [...$component, 'status' => $hasExtinction ? 'PRINCIPAL_ONLY' : 'CONDITIONAL', 'source_article' => 'LC214 art. 50',
                        'maximum_principal_credit' => $extinguished->value(), 'demanded_principal' => $demanded->value(),
                        'legal_additions_excluded' => $additions->value(), 'demand_evidence_id' => $row['demand_evidence_id'],
                        'extinction_evidence_id' => $hasExtinction ? $row['extinction_evidence_id'] : null,
                        'extinction_date' => $hasExtinction ? $row['extinction_date'] : null];
                }
            }
            if (isset($component['maximum_principal_credit'])) {
                $amountKey = $tax === 'IBS' ? 'rtc.input_ibs_amount' : 'rtc.input_cbs_amount';
                if (! $facts->has($amountKey)) {
                    return $this->stop('ACTION_REQUIRED', 'RTC_CREDIT_EVIDENCE_MISSING', [$amountKey]);
                }
                try {
                    $claim = $this->money($facts->value($amountKey));
                } catch (InvalidArgumentException) {
                    return $this->stop('INVALID', 'RTC_INPUT_CREDIT_AMOUNT_INVALID');
                }
                if ($claim->compareTo(DecimalValue::fromString($component['maximum_principal_credit'])) > 0) {
                    return $this->stop('INVALID', 'RTC_INPUT_CREDIT_EXCEEDS_TREATMENT_LIMIT:'.$tax);
                }
            }
            $components[$tax] = $component;
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_INPUT_CREDIT_TREATMENTS_QUALIFIED', 'missing_facts' => [],
            'source_hash' => $rule['source_hash'], 'components' => $components];
    }

    private function money(mixed $value): DecimalValue
    {
        if (! is_string($value) || ! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException('A nonnegative monetary amount with at most two decimals is required.');
        }

        return DecimalValue::fromString($value)->rounded(2);
    }

    /** @param list<string> $missing
     * @return array<string, mixed>
     */
    private function stop(string $status, string $reason, array $missing = []): array
    {
        return ['status' => $status, 'reason' => $reason, 'missing_facts' => $missing];
    }
}
