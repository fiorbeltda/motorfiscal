<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;

final readonly class TransitionCatalog
{
    /** @param array<string, mixed> $catalog */
    public function __construct(private array $catalog) {}

    /** @return array{status: string, row?: array<string, mixed>, reason: string} */
    public function resolve(LocalDate $on, DateTimeImmutable $knownAt): array
    {
        $matches = [];
        foreach ($this->catalog['rows'] ?? [] as $row) {
            if (! isset($row['valid_from'], $row['valid_to'], $row['published_at'], $row['source_article'])) {
                continue;
            }
            if ($on->value() < $row['valid_from'] || $on->value() > $row['valid_to'] || $knownAt < new DateTimeImmutable($row['published_at'])) {
                continue;
            }
            if (isset($row['revoked_at']) && $knownAt >= new DateTimeImmutable($row['revoked_at'])) {
                continue;
            }
            if (isset($row['superseded_at']) && $knownAt >= new DateTimeImmutable($row['superseded_at'])) {
                continue;
            }
            if (($row['status'] ?? null) === 'ACTIVE') {
                $matches[] = $row;
            }
        }
        if (count($matches) !== 1) {
            return ['status' => count($matches) > 1 ? 'CONFLICT' : 'NOT_FOUND',
                'reason' => count($matches) > 1 ? 'RTC_TRANSITION_CONFLICT' : 'RTC_TRANSITION_NOT_PROVEN'];
        }

        return ['status' => 'RESOLVED', 'row' => $matches[0], 'reason' => 'RTC_TRANSITION_RESOLVED'];
    }

    public function version(): string
    {
        return (string) ($this->catalog['version'] ?? '');
    }

    /** @return array<string, mixed>|null */
    public function taxPointPolicy(): ?array
    {
        $policy = $this->catalog['tax_point_event_policy'] ?? null;

        return is_array($policy) ? $policy : null;
    }

    public function sourceHash(): string
    {
        return (string) ($this->catalog['source_hash'] ?? '');
    }

    /** @return array{source_id: string, source_hash: string} */
    public function issFrozenLawSource(): array
    {
        return ['source_id' => (string) ($this->catalog['iss_frozen_law_source_id'] ?? ''),
            'source_hash' => (string) ($this->catalog['iss_frozen_law_source_hash'] ?? '')];
    }

    public function qualifiesCollectionMethods(): bool
    {
        return ($this->catalog['collection_qualification'] ?? false) === true;
    }

    public function qualifiesPlatformResponsibility(): bool
    {
        return ($this->catalog['platform_responsibility_qualification'] ?? false) === true;
    }

    public function qualifiesPartialExport(): bool
    {
        return ($this->catalog['partial_export_domestic_leg'] ?? false) === true;
    }

    public function integratesPostIssPeriod(): bool
    {
        return ($this->catalog['post_iss_integrated'] ?? false) === true;
    }

    public function evaluatesPostIssFederalWithholdings(): bool
    {
        return ($this->catalog['post_iss_federal_withholdings'] ?? false) === true;
    }

    public function qualifiesEmployeeBelowMarket(): bool
    {
        return ($this->catalog['employee_below_market'] ?? false) === true;
    }

    public function qualifiesPersonalUseRecipients(): bool
    {
        return ($this->catalog['personal_use_recipients'] ?? false) === true;
    }

    public function requiresPersonalUseSupplyOrigin(): bool
    {
        return ($this->catalog['personal_use_supply_origin'] ?? false) === true;
    }

    public function qualifiesAcquiredPersonalUse(): bool
    {
        return ($this->catalog['acquired_personal_use'] ?? false) === true;
    }

    public function qualifiesImportCredits(): bool
    {
        return ($this->catalog['import_credit_qualification'] ?? false) === true;
    }

    public function qualifiesDonations(): bool
    {
        return ($this->catalog['donation_qualification'] ?? false) === true;
    }

    public function calculatesDonationNominal(): bool
    {
        return ($this->catalog['donation_nominal_calculation'] ?? false) === true;
    }

    public function qualifiesGovernmentPurchases(): bool
    {
        return ($this->catalog['government_purchase_qualification'] ?? false) === true;
    }

    public function splitOperational(LocalDate $on, DateTimeImmutable $knownAt): bool
    {
        foreach ($this->catalog['split_deployments'] ?? [] as $row) {
            if (($row['status'] ?? null) === 'ACTIVE' && isset($row['valid_from'], $row['published_at'], $row['source_hash'])
                && $on->value() >= $row['valid_from']
                && (! isset($row['valid_to']) || $on->value() <= $row['valid_to'])
                && $knownAt >= new DateTimeImmutable($row['published_at'])
                && (! isset($row['revoked_at']) || $knownAt < new DateTimeImmutable($row['revoked_at']))
                && (! isset($row['superseded_at']) || $knownAt < new DateTimeImmutable($row['superseded_at']))) {
                return true;
            }
        }

        return false;
    }

    /** @return array{status: string, row?: array<string, mixed>, reason: string} */
    public function resolveSimplesOption(LocalDate $on, DateTimeImmutable $knownAt): array
    {
        foreach ($this->catalog['simples_option_supersession_notices'] ?? [] as $notice) {
            $fullActVerified = ($notice['full_act_verified'] ?? false) === true
                && (! isset($notice['full_act_verified_at']) || $knownAt >= new DateTimeImmutable($notice['full_act_verified_at']));
            if (is_array($notice) && isset($notice['valid_from'], $notice['valid_to'], $notice['observed_at'])
                && $on->value() >= $notice['valid_from'] && $on->value() <= $notice['valid_to']
                && $knownAt >= new DateTimeImmutable($notice['observed_at'])
                && ! $fullActVerified) {
                return ['status' => 'NOT_FOUND', 'reason' => 'RTC_SIMPLES_SUPERSEDING_ACT_REVIEW_REQUIRED'];
            }
        }
        $matches = [];
        foreach ($this->catalog['simples_option_windows'] ?? [] as $row) {
            if (! isset($row['valid_from'], $row['valid_to'], $row['published_at'], $row['source_hash'])
                || $on->value() < $row['valid_from'] || $on->value() > $row['valid_to']
                || $knownAt < new DateTimeImmutable($row['published_at'])
                || (isset($row['available_at']) && $knownAt < new DateTimeImmutable($row['available_at']))) {
                continue;
            }
            if ((isset($row['revoked_at']) && $knownAt >= new DateTimeImmutable($row['revoked_at']))
                || (isset($row['superseded_at']) && $knownAt >= new DateTimeImmutable($row['superseded_at']))) {
                continue;
            }
            if (($row['status'] ?? null) === 'ACTIVE') {
                $matches[] = $row;
            }
        }
        if (count($matches) !== 1) {
            return ['status' => count($matches) > 1 ? 'CONFLICT' : 'NOT_FOUND',
                'reason' => count($matches) > 1 ? 'RTC_SIMPLES_OPTION_WINDOW_CONFLICT' : 'RTC_SIMPLES_OPTION_WINDOW_NOT_PROVEN'];
        }

        return ['status' => 'RESOLVED', 'row' => $matches[0], 'reason' => 'RTC_SIMPLES_OPTION_WINDOW_RESOLVED'];
    }
}
