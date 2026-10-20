<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;

final readonly class IssTransitionRateEngine
{
    public function __construct(private TransitionCatalog $transitions) {}

    /** @return array<string, mixed> */
    public function resolve(
        LocalDate $operationDate,
        DateTimeImmutable $knownAt,
        IssRateDecision $current,
        MunicipalFiscalParametersResult $frozen,
    ): array {
        $date = $operationDate->value();
        if ($date < '2029-01-01' || $date > '2032-12-31') {
            return ['status' => 'NOT_APPLICABLE'];
        }
        if ($knownAt < new DateTimeImmutable('2029-01-01T00:00:00-03:00')) {
            return $this->require('ISS_2028_REFERENCE_DATE_NOT_REACHED');
        }
        $transition = $this->transitions->resolve($operationDate, $knownAt);
        if ($transition['status'] !== 'RESOLVED' || ! isset($transition['row'])) {
            return ['status' => $transition['status'] === 'CONFLICT' ? 'INVALID' : 'ACTION_REQUIRED',
                'reason' => $transition['reason']];
        }
        if ($current->source !== 'MUNICIPAL_RATE') {
            return $this->require('ISS_SPECIAL_RATE_TRANSITION_NOT_MATERIALIZED');
        }
        if ($frozen->status === MunicipalParameterStatus::CONFLICT) {
            return ['status' => 'INVALID', 'reason' => 'ISS_2028_FROZEN_RATE_CONFLICT'];
        }
        if ($frozen->status !== MunicipalParameterStatus::FOUND || $frozen->rate === null) {
            return $this->require('ISS_2028_FROZEN_RATE_NOT_PROVEN');
        }
        if (! in_array($frozen->sourceType, ['OFFICIAL_FILE', 'OFFICIAL_ACT', 'OFFICIAL_SNAPSHOT'], true)
            || $frozen->sourceId === null || $frozen->sourceId === '') {
            return $this->require('ISS_2028_FROZEN_RATE_SOURCE_NOT_APPROVED');
        }
        $factor = (string) ($transition['row']['iss_factor'] ?? '');
        $expected = ['2029' => '0.90', '2030' => '0.80', '2031' => '0.70', '2032' => '0.60'];
        if ($factor !== $expected[substr($date, 0, 4)]) {
            return ['status' => 'INVALID', 'reason' => 'ISS_TRANSITION_FACTOR_CONFLICT'];
        }
        $effective = $frozen->rate->multipliedBy(DecimalValue::fromString($factor))->rounded(6);

        return ['status' => 'RESOLVED',
            'rate' => new IssRateDecision($effective, 'FROZEN_2028_MUNICIPAL_RATE', 'ISS_TRANSITION_RATE_SELECTED',
                referencePeriod: '2028-12-31'),
            'reference_rate_percent' => $frozen->rate->value(),
            'factor' => $factor,
            'effective_rate_percent' => $effective->value(),
            'source_id' => $frozen->sourceId,
            'source_version' => $frozen->sourceVersion,
            'source_type' => $frozen->sourceType,
            'source_article' => 'LC116 art. 8º-B',
            'law_source' => $this->transitions->issFrozenLawSource(),
            'transition_catalog_version' => $this->transitions->version()];
    }

    /** @return array{status: string, reason: string} */
    private function require(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => $reason];
    }
}
