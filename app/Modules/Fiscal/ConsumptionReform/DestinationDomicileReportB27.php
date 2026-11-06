<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

/**
 * B27: binds the destination used by the engine (domicile bases of LC 214 art. 11 §3) to a replayable
 * A151/A152/A153 domicile report (including the B26 natural person of Decreto 12.955 art. 105).
 * The engine never chooses the domicile itself: the report must replay byte-identically and agree with
 * the declared basis, party, supplier, date and locality.
 */
final readonly class DestinationDomicileReportB27
{
    public const KEY = 'destination_domicile_report_b27';

    public const POLICY = ['version' => 'B27', 'bases' => ['ACQUIRER_DOMICILE', 'RECIPIENT_DOMICILE'],
        'contracts' => [EntityDomicile151::CONTRACT, EntityDomicile151::CENTRALIZED_CONTRACT, EntityDomicile151::PARTY_CONTRACT],
        'replay_required' => true];

    private const QUALIFIED = [
        EntityDomicile151::CONTRACT => 'LOCAL_ENTITY_DOMICILE_QUALIFIED',
        EntityDomicile151::CENTRALIZED_CONTRACT => 'LOCAL_CENTRALIZED_ENTITY_DOMICILE_QUALIFIED',
        EntityDomicile151::PARTY_CONTRACT => 'LOCAL_PARTY_DOMICILE_QUALIFIED',
    ];

    /** @param array<string, mixed> $codes */
    public static function enabled(array $codes): bool
    {
        return is_array($codes[self::KEY] ?? null)
            && CanonicalJson::hash($codes[self::KEY]) === CanonicalJson::hash(self::POLICY);
    }

    /**
     * @param  array<string, mixed>  $codes
     * @return array<string, mixed>
     */
    public function resolve(FactBag $facts, string $basis, string $date, array $codes): array
    {
        if (! in_array($basis, self::POLICY['bases'], true)) {
            return ['status' => 'RESOLVED', 'reason' => 'RTC_DESTINATION_DOMICILE_REPORT_NOT_APPLICABLE', 'missing_facts' => []];
        }
        $rows = $facts->value('rtc.destination_domicile_report');
        if (! is_array($rows) || ! array_is_list($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            return $this->stop('ACTION_REQUIRED', 'RTC_DESTINATION_DOMICILE_REPORT_REQUIRED', ['rtc.destination_domicile_report']);
        }
        $report = $rows[0];
        $contract = $report['contract'] ?? null;
        if (! is_string($contract) || ! isset(self::QUALIFIED[$contract]) || ($report['status'] ?? null) !== self::QUALIFIED[$contract]) {
            return $this->stop('ACTION_REQUIRED', 'RTC_DESTINATION_DOMICILE_REPORT_NOT_QUALIFIED', ['rtc.destination_domicile_report']);
        }
        if (! is_array($codes['observed_geographic_reference'] ?? null) || ! is_array($codes['observed_geographic_reference_policy'] ?? null)) {
            return $this->stop('ACTION_REQUIRED', 'RTC_DESTINATION_DOMICILE_GEOGRAPHY_UNAVAILABLE', []);
        }
        $qualifier = new EntityDomicile151($codes['observed_geographic_reference'], $codes['observed_geographic_reference_policy']);
        if (! $qualifier->replay($report)) {
            return $this->stop('INVALID', 'RTC_DESTINATION_DOMICILE_REPORT_REPLAY_FAILED', ['rtc.destination_domicile_report']);
        }
        $input = $report['operation_input'];
        $party = $basis === 'ACQUIRER_DOMICILE' ? $facts->value('rtc.acquirer_id') : $facts->value('rtc.recipient_id');
        $locality = is_array($report['locality'] ?? null) ? $report['locality'] : [];
        if (($report['destination_basis'] ?? null) !== $basis || ($report['selected_party_id'] ?? null) !== $party
            || ($input['supplier_id'] ?? null) !== $facts->value('rtc.supplier_id') || ($input['operation_date'] ?? null) !== $date
            || ($locality['uf'] ?? null) !== $facts->value('rtc.destination_state')
            || ($locality['municipality_ibge'] ?? null) !== $facts->value('rtc.destination_municipality_ibge')) {
            return $this->stop('INVALID', 'RTC_DESTINATION_DOMICILE_REPORT_CONFLICT', []);
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_DESTINATION_DOMICILE_REPORT_BOUND', 'missing_facts' => [],
            'contract' => $contract, 'report_hash' => $report['report_hash'], 'source_article' => $report['source_article'] ?? null,
            'selected_party_id' => $party, 'selected_establishment_id' => $report['selected_establishment_id'] ?? null,
            'natural_person105' => isset($report['natural_person105']), 'locality' => $locality, 'authenticated' => false];
    }

    /** @param list<string> $missing
     * @return array<string, mixed>
     */
    private function stop(string $status, string $reason, array $missing): array
    {
        return ['status' => $status, 'reason' => $reason, 'missing_facts' => $missing];
    }
}
