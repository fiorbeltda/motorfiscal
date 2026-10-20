<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;

/** LC 214 art. 11 III: actual personal performance/enjoyment controls destination. */
final readonly class PresenceDestinationQualifier
{
    /** @return array<string, mixed> */
    public function resolve(FactBag $facts): array
    {
        $missing = $facts->missing(['rtc.service_on_person_or_enjoyed_in_person', 'rtc.service_presence_evidence_id',
            'rtc.performance_municipality_ibge', 'rtc.performance_state', 'rtc.presence_at_supplier_establishment']);
        if ($missing !== []) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_PERSON_PRESENCE_PROOF_MISSING', 'missing_facts' => $missing];
        }
        if ($facts->value('rtc.delivery_mode') !== 'PRESENTIAL' || $facts->value('rtc.service_on_person_or_enjoyed_in_person') !== true
            || $facts->value('rtc.performance_municipality_ibge') !== $facts->value('rtc.destination_municipality_ibge')
            || $facts->value('rtc.performance_state') !== $facts->value('rtc.destination_state')) {
            return ['status' => 'INVALID', 'reason' => 'RTC_PERSON_PRESENCE_DESTINATION_CONFLICT', 'missing_facts' => []];
        }
        if ($facts->value('rtc.presence_at_supplier_establishment') === true) {
            $missing = $facts->missing(['provider.municipality_ibge', 'rtc.supplier_establishment_state']);
            if ($missing !== []) {
                return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_PERSON_PRESENCE_PROOF_MISSING', 'missing_facts' => $missing];
            }
            if ($facts->value('provider.municipality_ibge') !== $facts->value('rtc.performance_municipality_ibge')
                || $facts->value('rtc.supplier_establishment_state') !== $facts->value('rtc.performance_state')) {
                return ['status' => 'INVALID', 'reason' => 'RTC_PERSON_PRESENCE_ESTABLISHMENT_CONFLICT', 'missing_facts' => []];
            }
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_PERSON_PRESENCE_DESTINATION_RESOLVED', 'missing_facts' => [],
            'source_article' => 'LC214 art. 11, III', 'cindop' => $facts->value('rtc.presence_at_supplier_establishment') === true ? '030101' : '030102',
            'municipality_ibge' => $facts->value('rtc.performance_municipality_ibge'),
            'state' => $facts->value('rtc.performance_state'), 'evidence_id' => $facts->value('rtc.service_presence_evidence_id')];
    }
}
