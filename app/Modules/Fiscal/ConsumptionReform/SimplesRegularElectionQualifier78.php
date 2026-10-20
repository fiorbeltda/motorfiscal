<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use DateTimeImmutable;
use DateTimeZone;

/** Source-bound H1 election qualification; activation requires its own temporal catalog policy. */
final readonly class SimplesRegularElectionQualifier78
{
    public const CONTRACT_VERSION = 'SIMPLES-REGULAR-ELECTION-CGSN194-2026.10.15-78';

    /**
     * @param  array<string, mixed>  $facts
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function qualify(array $facts, string $on, DateTimeImmutable $knownAt, array $policy): array
    {
        if (! $this->date($on)) {
            return $this->stop('INVALID', 'RTC_SIMPLES_OPERATION_DATE_INVALID');
        }
        if ($on < '2027-01-01' || $on > '2027-06-30') {
            return $this->stop('NOT_APPLICABLE', 'RTC_SIMPLES_194_H1_OUTSIDE_SCOPE');
        }
        if (($facts['rtc.supplier_regime'] ?? null) === 'MEI') {
            return $this->stop('INVALID', 'RTC_MEI_REGULAR_ELECTION_FORBIDDEN');
        }
        if (($facts['rtc.supplier_regime'] ?? null) !== 'SIMPLES_NACIONAL'
            || ($facts['rtc.simples_collection_option'] ?? null) !== 'REGULAR') {
            return $this->stop('NOT_APPLICABLE', 'RTC_SIMPLES_194_REGULAR_ELECTION_NOT_REQUESTED');
        }
        if (($facts['provider.tax_regime'] ?? null) !== 'SIMPLES_NACIONAL') {
            return $this->stop('INVALID', 'RTC_SIMPLES_PROVIDER_REGIME_CONFLICT');
        }
        $sourceHashes = $policy['source_hashes'] ?? null;
        if (($policy['contract_version'] ?? null) !== self::CONTRACT_VERSION
            || ! is_array($sourceHashes) || count($sourceHashes) !== 2
            || ($sourceHashes['CGSN194'] ?? null) !== '038e3259963c9cf492e3dec1f4ddbff50cbf83ccabd3a94ea191b0f56fe82ef7'
            || ($sourceHashes['CGSN186_VIGENTE'] ?? null) !== '31dc79137f5e4d3f67289bd0cd05f7ef72992037bf28dde216fd1085f8453f1d'
            || ($policy['effective_from'] ?? null) !== '2027-01-01'
            || ($policy['effective_through'] ?? null) !== '2027-06-30'
            || ($policy['filing_from'] ?? null) !== '2026-09-01'
            || ($policy['filing_through'] ?? null) !== '2026-10-30'
            || ($policy['cancellation_from'] ?? null) !== '2026-11-03'
            || ($policy['cancellation_through'] ?? null) !== '2026-12-20'
            || ($policy['registration_exception_from'] ?? null) !== '2026-10-31'
            || ($policy['registration_exception_through'] ?? null) !== '2026-12-31') {
            return $this->stop('ACTION_REQUIRED', 'RTC_SIMPLES_194_POLICY_UNPROVEN');
        }
        $available = $policy['available_at'] ?? null;
        if ($available !== '2026-10-15T11:40:27.560996+00:00') {
            return $this->stop('ACTION_REQUIRED', 'RTC_SIMPLES_194_SOURCE_OBSERVATION_UNPROVEN');
        }
        $observed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', $available);
        if ($observed === false || $observed->format('Y-m-d\TH:i:s.uP') !== $available) {
            return $this->stop('ACTION_REQUIRED', 'RTC_SIMPLES_194_SOURCE_OBSERVATION_UNPROVEN');
        }
        if ($knownAt < $observed) {
            return $this->stop('ACTION_REQUIRED', 'RTC_SIMPLES_194_SOURCE_NOT_YET_AVAILABLE');
        }
        $required = ['provider.cnpj_registered_on', 'provider.cnpj_registration_evidence_id',
            'rtc.simples_election_route', 'rtc.simples_option_route_evidence_id',
            'rtc.simples_option_filed_on', 'rtc.simples_option_receipt_id',
            'rtc.simples_option_status', 'rtc.simples_option_status_evidence_id',
            'rtc.simples_option_status_observed_on'];
        $missing = array_values(array_filter($required, static fn (string $key): bool => ! is_string($facts[$key] ?? null) || trim($facts[$key]) === ''));
        if (! is_bool($facts['provider.initial_activity'] ?? null)) {
            $missing[] = 'provider.initial_activity';
        }
        if ($missing !== []) {
            return $this->stop('ACTION_REQUIRED', 'RTC_SIMPLES_194_ELECTION_EVIDENCE_REQUIRED', $missing);
        }
        $registered = $facts['provider.cnpj_registered_on'];
        $filed = $facts['rtc.simples_option_filed_on'];
        $statusOn = $facts['rtc.simples_option_status_observed_on'];
        if (! $this->date($registered) || ! $this->date($filed) || ! $this->date($statusOn)) {
            return $this->stop('INVALID', 'RTC_SIMPLES_194_EVIDENCE_DATE_INVALID');
        }
        $civilKnown = $knownAt->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
        if ($registered > $civilKnown || $filed > $civilKnown || $statusOn > $civilKnown) {
            return $this->stop('INVALID', 'RTC_SIMPLES_OPTION_AFTER_KNOWLEDGE_DATE');
        }
        if ($filed < $registered || $statusOn < $filed) {
            return $this->stop('INVALID', 'RTC_SIMPLES_194_EVIDENCE_CHRONOLOGY_CONFLICT');
        }
        $route = $facts['rtc.simples_election_route'];
        if ($route === 'STANDARD_WINDOW') {
            if ($registered > $policy['filing_through']) {
                return $this->stop('INVALID', 'RTC_SIMPLES_194_REGISTRATION_ROUTE_REQUIRED');
            }
            if ($filed < $policy['filing_from'] || $filed > $policy['filing_through']) {
                return $this->stop('ACTION_REQUIRED', 'RTC_SIMPLES_OPTION_OUTSIDE_STANDARD_WINDOW');
            }
            // Before the cancellation opening the election is still revocable later.
            if ($civilKnown <= $policy['cancellation_through']) {
                return $this->stop('ACTION_REQUIRED', 'RTC_SIMPLES_OPTION_REVOCATION_WINDOW_OPEN');
            }
        } elseif ($route === 'CNPJ_REGISTRATION') {
            if ($facts['provider.initial_activity'] !== true
                || $registered < $policy['registration_exception_from']
                || $registered > $policy['registration_exception_through']) {
                return $this->stop('INVALID', 'RTC_SIMPLES_194_REGISTRATION_EXCEPTION_CONFLICT');
            }
            if ($filed !== $registered) {
                return $this->stop('ACTION_REQUIRED', 'RTC_SIMPLES_194_ELECTION_AT_REGISTRATION_UNPROVEN');
            }
        } else {
            return $this->stop('INVALID', 'RTC_SIMPLES_194_ELECTION_ROUTE_UNKNOWN');
        }
        if ($facts['rtc.simples_option_status'] !== 'CONFIRMED') {
            return $this->stop('ACTION_REQUIRED', 'RTC_SIMPLES_REGULAR_OPTION_NOT_CONFIRMED');
        }
        if ($statusOn < $on) {
            return $this->stop('ACTION_REQUIRED', 'RTC_SIMPLES_194_STATUS_AT_OPERATION_UNPROVEN');
        }

        return ['status' => 'LOCAL_ELECTION_QUALIFIED', 'reason' => 'RTC_SIMPLES_194_H1_ELECTION_BOUND',
            'contract_version' => self::CONTRACT_VERSION, 'route' => $route,
            'effective_from' => $policy['effective_from'], 'effective_through' => $policy['effective_through'],
            'registered_on' => $registered, 'filed_on' => $filed, 'status_observed_on' => $statusOn,
            'receipt_id' => $facts['rtc.simples_option_receipt_id'],
            'registration_evidence_id' => $facts['provider.cnpj_registration_evidence_id'],
            'route_evidence_id' => $facts['rtc.simples_option_route_evidence_id'],
            'status_evidence_id' => $facts['rtc.simples_option_status_evidence_id'],
            'source_hashes' => ['CGSN194' => $sourceHashes['CGSN194'], 'CGSN186_VIGENTE' => $sourceHashes['CGSN186_VIGENTE']],
            'missing_facts' => [],
            'government_receipt_authenticated' => false, 'production_ready' => false];
    }

    private function date(string $value): bool
    {
        if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value) !== 1) {
            return false;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed !== false && $parsed->format('Y-m-d') === $value;
    }

    /**
     * @param  list<string>  $missing
     * @return array<string, mixed>
     */
    private function stop(string $status, string $reason, array $missing = []): array
    {
        return ['status' => $status, 'reason' => $reason, 'missing_facts' => $missing,
            'government_receipt_authenticated' => false, 'production_ready' => false];
    }
}
