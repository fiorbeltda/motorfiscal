<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;

final class IssRateEngine
{
    public function resolve(
        FactBag $facts,
        LocalDate $competence,
        IssResponsibilityDecision $responsibility,
        DecimalValue $municipalRate,
    ): IssRateDecision {
        if ($facts->value('provider.tax_regime') !== 'SIMPLES_NACIONAL' || ! $responsibility->withholdingRequired) {
            return new IssRateDecision($municipalRate, 'MUNICIPAL_RATE', 'ISS_MUNICIPAL_RATE_SELECTED');
        }

        $required = ['provider.activity_start_date', 'provider.simples_rate_informed'];
        $missing = $facts->missing($required);
        if ($missing !== []) {
            return new IssRateDecision(null, 'SIMPLES_EFFECTIVE_RATE', 'ISS_SIMPLES_RATE_FACTS_MISSING', $missing);
        }

        $competenceMonth = substr($competence->value(), 0, 7);
        $startMonth = substr((string) $facts->value('provider.activity_start_date'), 0, 7);
        $referencePeriod = $competence->value() < '2027-01-01' ? 'PREVIOUS_MONTH' : 'SERVICE_MONTH';
        $startupMonths = [$startMonth];
        if ($competence->value() >= '2027-01-01') {
            $startupMonths[] = (new \DateTimeImmutable($startMonth.'-01'))->modify('+1 month')->format('Y-m');
        }
        if (in_array($competenceMonth, $startupMonths, true)) {
            return new IssRateDecision(
                DecimalValue::fromString('2.00'),
                'FIXED_2_PERCENT',
                'ISS_SIMPLES_STARTUP_RATE_SELECTED',
                referencePeriod: $referencePeriod,
            );
        }

        if ($facts->value('provider.simples_rate_informed') !== true) {
            return new IssRateDecision(
                DecimalValue::fromString('5.00'),
                'FALLBACK_5_PERCENT',
                'ISS_SIMPLES_UNINFORMED_RATE_FALLBACK',
                referencePeriod: $referencePeriod,
            );
        }

        if (! $facts->has('provider.simples_effective_iss_rate')) {
            return new IssRateDecision(
                null,
                'SIMPLES_EFFECTIVE_RATE',
                'ISS_SIMPLES_EFFECTIVE_RATE_MISSING',
                ['provider.simples_effective_iss_rate'],
                $referencePeriod,
            );
        }
        $rate = DecimalValue::fromString((string) $facts->value('provider.simples_effective_iss_rate'));
        if ($rate->compareTo(DecimalValue::fromString('0')) <= 0 || $rate->compareTo(DecimalValue::fromString('5')) > 0) {
            return new IssRateDecision(null, 'SIMPLES_EFFECTIVE_RATE', 'ISS_SIMPLES_EFFECTIVE_RATE_INVALID', referencePeriod: $referencePeriod);
        }

        return new IssRateDecision(
            $rate->rounded(6),
            'SIMPLES_EFFECTIVE_RATE',
            'ISS_SIMPLES_EFFECTIVE_RATE_SELECTED',
            referencePeriod: $referencePeriod,
        );
    }
}
