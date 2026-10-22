<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;

/** The RTC declaration schedule is independent of layout deployment and existing ISS obligations. */
final readonly class DocumentObligationEngine
{
    /**
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>
     */
    public function resolve(string $serviceCode, string $regime, LocalDate $on, DateTimeImmutable $knownAt, array $catalog, bool $platformQualified = false): array
    {
        if (DocumentSchedule131::required($catalog)) {
            return (new DocumentSchedule131)->nfse($serviceCode, $regime, $on, $knownAt, $catalog, $platformQualified);
        }
        if (! preg_match('/^[0-9]{6}$/', $serviceCode)) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DOCUMENT_SERVICE_CLASSIFICATION_REQUIRED'];
        }
        $matches = [];
        foreach ($catalog['document_obligations'] ?? [] as $row) {
            if (! is_array($row) || ($row['status'] ?? null) !== 'OFFICIAL_GUIDANCE'
                || ! isset($row['valid_from'], $row['valid_to'], $row['published_at'], $row['source_id'], $row['source_hash'], $row['mandatory_from'])
                || $on->value() < $row['valid_from'] || $on->value() > $row['valid_to']
                || $knownAt < new DateTimeImmutable($row['published_at'])
                || ! in_array($regime, $row['regimes'] ?? [], true)) {
                continue;
            }
            $subitem = substr($serviceCode, 0, 4);
            if ((isset($row['subitems']) && ! in_array($subitem, $row['subitems'], true))
                || in_array($subitem, $row['excluded_subitems'] ?? [], true)) {
                continue;
            }
            $matches[] = $row;
        }
        if (count($matches) !== 1) {
            return ['status' => count($matches) > 1 ? 'INVALID' : 'ACTION_REQUIRED',
                'reason' => count($matches) > 1 ? 'RTC_DOCUMENT_OBLIGATION_CONFLICT' : 'RTC_DOCUMENT_OBLIGATION_NOT_PROVEN'];
        }
        $row = $matches[0];

        return ['status' => 'RESOLVED', 'scope' => 'RTC_DECLARATION_SCHEDULE_ONLY',
            'document' => $row['document'], 'required_by_schedule' => $on->value() >= $row['mandatory_from'],
            'mandatory_from' => $row['mandatory_from'], 'rule_id' => $row['id'],
            'source_id' => $row['source_id'], 'source_hash' => $row['source_hash'],
            'proof_kind' => 'OFFICIAL_GUIDANCE', 'full_act_review_required' => true,
            'iss_document_obligations' => 'INDEPENDENT', 'layout_deployment' => 'INDEPENDENT'];
    }
}
