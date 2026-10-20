<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use Throwable;

/** Local nominal computation; the event year is never replaced by the rate year for cash relief. */
final readonly class AdvanceOnlyNominal87
{
    public function __construct(private TemporalRateCatalog $rates) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function calculate(array $context, string $installmentId, string $state, string $municipality, DateTimeImmutable $knownAt): array
    {
        $origin = (new AdvanceOnlyOrigin87)->select($context, $installmentId, $knownAt);
        if ($origin['status'] !== 'LOCAL_ADVANCE_ORIGIN_ONLY') {
            return $origin;
        }
        $event = $origin['event'];
        $taxes = [];
        foreach (['IBS_STATE', 'IBS_MUNICIPAL', 'CBS'] as $tax) {
            $jurisdiction = $tax === 'CBS' || $event['rate_date'] < '2029-01-01' ? 'BR'
                : ($tax === 'IBS_STATE' ? $state : $municipality);
            try {
                $selected = $this->rates->resolve($tax, $jurisdiction, LocalDate::fromString($event['rate_date']), $knownAt);
            } catch (Throwable) {
                return $this->stop('RATE_CATALOG_INVALID');
            }
            if ($selected['status'] !== 'RESOLVED' || ! isset($selected['row'])) {
                return $this->stop($selected['reason']);
            }
            $row = $selected['row'];
            $percent = $row['percent'] ?? null;
            if (! is_string($percent) || preg_match('/\A[0-9]+(?:\.[0-9]{1,6})?\z/', $percent) !== 1
                || DecimalValue::fromString($percent)->compareTo(DecimalValue::fromString('100')) > 0) {
                return $this->stop('RATE_PRECISION_OR_RANGE_INVALID');
            }
            $nominal = DecimalValue::fromString($event['base_amount'])->multipliedBy(DecimalValue::fromString($percent))
                ->dividedBy(DecimalValue::fromString('100'), 10)->rounded(2)->value();
            $taxes[$tax] = ['base_amount' => $event['base_amount'], 'percent' => $percent, 'nominal_amount' => $nominal,
                'source_id' => $row['source_id'], 'source_hash' => $row['source_hash'], 'jurisdiction' => $jurisdiction,
                'cash_due' => null, 'cash_relief_qualified' => false];
        }

        return ['status' => 'LOCAL_ADVANCE_NOMINAL_ONLY', 'origin' => $origin, 'taxes' => $taxes,
            'source_policy_qualified' => false, 'incidence_and_destination_qualified' => false,
            'registration_proven' => false, 'extinction_proven' => false, 'assessment_period_qualified' => false,
            'cash_relief_qualified' => false, 'posting_authorized' => false,
            'credit_appropriation_authorized' => false, 'production_ready' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => $reason, 'taxes' => [],
            'posting_authorized' => false, 'credit_appropriation_authorized' => false, 'production_ready' => false];
    }
}
