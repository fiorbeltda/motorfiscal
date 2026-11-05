<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\DecimalValue;
use DateTimeImmutable;

/** LC214 art.12 §8 only; no reduction of the tax rate or general travel benefit. */
final readonly class LegalBaseReduction156
{
    public const KEY = 'legal_base_reduction_a156';

    public const POLICY = ['version' => 'A156', 'rule' => 'LC214_ART12_PAR8', 'factor' => '0.50',
        'source_hash' => 'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde'];

    /** @param array<string, mixed> $facts
     * @return array<string, mixed>
     */
    public function resolve(array $facts, string $base, DateTimeImmutable $knownAt): array
    {
        $guard = new CreditEvidence156;
        $records = $facts['rtc.legal_base_reduction_records'] ?? null;
        if (! is_array($records) || ! array_is_list($records) || count($records) !== 1) {
            return $this->stop('RTC_LEGAL_BASE_REDUCTION_RECORD_REQUIRED');
        }
        $record = $guard->record($records[0], $knownAt);
        if ($record === null || ($record['contract'] ?? null) !== 'LEGAL-BASE-ART12-PAR8-A156'
            || ($record['rule'] ?? null) !== 'LC214_ART12_PAR8'
            || ($record['service_family'] ?? null) !== 'INTERNATIONAL_PASSENGER_TRANSPORT'
            || ($record['scope_confirmed'] ?? null) !== true || ($record['sold_together'] ?? null) !== true
            || ! $guard->reference($record['service_id'] ?? null) || $record['service_id'] !== ($facts['service.canonical_id'] ?? null)
            || ($record['currency'] ?? null) !== 'BRL' || ! $guard->money($record['charged_amount'] ?? null)
            || ! $guard->reference($record['joint_sale_id'] ?? null) || ! $guard->date($record['sold_on'] ?? null)
            || $record['sold_on'] > $knownAt->format('Y-m-d')
            || ($record['charged_amount'] ?? null) !== $base
            || ($facts['rtc.base_adjustments'] ?? null) !== []
            || ($facts['rtc.consideration'] ?? null) !== 'ONEROUS'
            || ($facts['rtc.tax_treatment'] ?? null) !== 'STANDARD'
            || ($facts['rtc.credit_context'] ?? null) !== 'OUTPUT') {
            return $this->stop('RTC_LEGAL_BASE_REDUCTION_SCOPE_UNPROVEN');
        }
        foreach (['document_id', 'supplier_id', 'acquirer_id'] as $key) {
            if (! $guard->reference($record[$key] ?? null) || $record[$key] !== ($facts['rtc.'.$key] ?? null)) {
                return $this->stop('RTC_LEGAL_BASE_REDUCTION_DOCUMENT_CONFLICT');
            }
        }
        $legs = $record['legs'] ?? null;
        if (! is_array($legs) || ! array_is_list($legs) || count($legs) !== 2) {
            return $this->stop('RTC_LEGAL_BASE_REDUCTION_LEGS_UNPROVEN');
        }
        $ids = [];
        foreach ($legs as $leg) {
            if (! is_array($leg) || ! $guard->reference($leg['id'] ?? null) || isset($ids[$leg['id']])
                || ! is_string($leg['from_country'] ?? null) || ! preg_match('/^[A-Z]{2}$/D', $leg['from_country'])
                || ! is_string($leg['to_country'] ?? null) || ! preg_match('/^[A-Z]{2}$/D', $leg['to_country'])
                || $leg['from_country'] === $leg['to_country'] || ! $guard->date($leg['departure_on'] ?? null)
                || $leg['departure_on'] < $record['sold_on'] || ($leg['joint_sale_id'] ?? null) !== $record['joint_sale_id']) {
                return $this->stop('RTC_LEGAL_BASE_REDUCTION_LEGS_UNPROVEN');
            }
            $ids[$leg['id']] = true;
        }
        if ($legs[0]['from_country'] !== $legs[1]['to_country'] || $legs[0]['to_country'] !== $legs[1]['from_country']
            || ! in_array('BR', [$legs[0]['from_country'], $legs[0]['to_country']], true)
            || $legs[0]['departure_on'] > $legs[1]['departure_on']) {
            return $this->stop('RTC_LEGAL_BASE_REDUCTION_RETURN_CONFLICT');
        }
        $reduced = DecimalValue::fromString($base)->multipliedBy(DecimalValue::fromString('0.50'))->rounded(2);

        return ['status' => 'RESOLVED', 'reason' => 'RTC_LEGAL_BASE_REDUCED_ART12_PAR8', 'missing_facts' => [],
            'source_article' => 'LC214 art.12 §8', 'source_hash' => self::POLICY['source_hash'],
            'charged_amount' => $base, 'base_factor' => '0.50', 'taxable_base' => $reduced->value(),
            'excluded_base' => DecimalValue::fromString($base)->minus($reduced)->value(), 'rate_reduction_applied' => false,
            'evidence' => $record, 'document_approved' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => $reason, 'missing_facts' => ['rtc.legal_base_reduction_records']];
    }
}
