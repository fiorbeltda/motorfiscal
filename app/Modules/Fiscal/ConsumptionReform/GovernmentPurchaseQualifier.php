<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;

final readonly class GovernmentPurchaseQualifier
{
    private const UNIFORM_RATE_ARTICLES = ['174', '175', '189', '212', '236', '237', '243', '246',
        '293_4_II', '293_4_III', '294_I', '294_II', '485_I', '485_II', '486_1', '487_2'];

    /** @return array{status: string, reason: string, missing_facts: list<string>, treatment?: array<string, mixed>} */
    public function resolve(FactBag $facts, string $date): array
    {
        $year = (int) substr($date, 0, 4);
        if ($year === 2026) {
            return ['status' => 'RESOLVED', 'reason' => 'RTC_GOVERNMENT_2026_ORDINARY_TREATMENT',
                'missing_facts' => [], 'treatment' => [
                    'article_472_reductor' => 'NOT_APPLICABLE', 'article_473_destination' => 'NOT_APPLICABLE',
                    'source_article' => 'LC214 arts. 372, I, a, e 472, I',
                ]];
        }

        $missing = $facts->missing(['rtc.government_entity_type', 'rtc.government_entity_evidence_id',
            'rtc.government_public_law_person', 'rtc.government_in_person',
            'rtc.government_licitation_waived', 'rtc.government_national_uniform_rate']);
        if ($missing !== []) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_GOVERNMENT_PURCHASE_EVIDENCE_MISSING',
                'missing_facts' => $missing];
        }
        $entity = $facts->value('rtc.government_entity_type');
        $publicLaw = $facts->value('rtc.government_public_law_person') === true;
        if (in_array($entity, ['DIRECT', 'AUTARCHY', 'PUBLIC_FOUNDATION'], true) && ! $publicLaw) {
            return ['status' => 'INVALID', 'reason' => 'RTC_GOVERNMENT_ENTITY_PUBLIC_LAW_CONFLICT', 'missing_facts' => []];
        }
        if (! $publicLaw) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_GOVERNMENT_ENTITY_OUTSIDE_PUBLIC_LAW_SCOPE',
                'missing_facts' => []];
        }

        $exception = $facts->value('rtc.government_in_person') === true
            && $facts->value('rtc.government_licitation_waived') === true;
        if ($exception && ! $facts->has('rtc.government_procurement_evidence_id')) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_GOVERNMENT_PROCUREMENT_EXCEPTION_UNPROVEN',
                'missing_facts' => ['rtc.government_procurement_evidence_id']];
        }
        if ($facts->value('rtc.government_national_uniform_rate') === true
            && ! $facts->has('rtc.government_uniform_rate_rule_id')) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_GOVERNMENT_UNIFORM_RATE_EXCEPTION_UNPROVEN',
                'missing_facts' => ['rtc.government_uniform_rate_rule_id']];
        }
        if ($facts->value('rtc.government_national_uniform_rate') === true) {
            $rule = (string) $facts->value('rtc.government_uniform_rate_rule_id');
            if (! str_starts_with($rule, 'LC214_ART')
                || ! in_array(substr($rule, strlen('LC214_ART')), self::UNIFORM_RATE_ARTICLES, true)
                || $facts->value('service.canonical_id') === 'SOFTWARE_DEVELOPMENT') {
                return ['status' => 'INVALID', 'reason' => 'RTC_GOVERNMENT_UNIFORM_RATE_CLASSIFICATION_CONFLICT',
                    'missing_facts' => []];
            }
        }
        $entityDestination = in_array($entity, ['DIRECT', 'AUTARCHY', 'PUBLIC_FOUNDATION'], true);
        $destination = $entityDestination && ! $exception;
        if ($destination) {
            $federationMissing = $facts->missing(['rtc.government_federation']);
            if ($federationMissing !== []) {
                return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_GOVERNMENT_FEDERATION_MISSING',
                    'missing_facts' => $federationMissing];
            }
        }
        $regime = $facts->value('rtc.supplier_regime');
        $reductor = ! $exception && $facts->value('rtc.government_national_uniform_rate') === false
            && ! in_array($regime, ['SIMPLES_NACIONAL', 'MEI'], true);
        $cbsDestinationShare = match (true) {
            $year <= 2028 => '0',
            $year === 2029 => '10',
            $year === 2030 => '20',
            $year === 2031 => '30',
            $year === 2032 => '40',
            default => '100',
        };

        return ['status' => 'RESOLVED', 'reason' => 'RTC_GOVERNMENT_PURCHASE_QUALIFIED', 'missing_facts' => [],
            'treatment' => [
                'article_472_reductor' => $reductor ? 'APPLIES_RATE_PENDING' : 'NOT_APPLICABLE',
                'article_473_destination' => $destination ? 'APPLIES' : 'NOT_APPLICABLE',
                'ibs_destination_share_percent' => $destination ? '100' : '0',
                'cbs_destination_share_percent' => $destination ? $cbsDestinationShare : '0',
                'taxable_event_at_payment' => $destination,
                'federation' => $destination ? $facts->value('rtc.government_federation') : null,
                'exception_in_person_without_licitation' => $exception,
                'exception_national_uniform_rate' => $facts->value('rtc.government_national_uniform_rate'),
                'exception_simples_mei' => in_array($regime, ['SIMPLES_NACIONAL', 'MEI'], true),
                'entity_evidence_id' => $facts->value('rtc.government_entity_evidence_id'),
                'procurement_evidence_id' => $facts->value('rtc.government_procurement_evidence_id'),
                'uniform_rate_rule_id' => $facts->value('rtc.government_uniform_rate_rule_id'),
                'source_article' => 'LC214 arts. 10, §2º, 370, 372, 472 e 473',
            ]];
    }
}
