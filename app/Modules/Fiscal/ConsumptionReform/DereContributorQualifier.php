<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

/** Local D1001 qualification; external registration and fiscal classification remain evidence inputs. */
final readonly class DereContributorQualifier
{
    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $catalogs
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function resolve(array $profile, array $catalogs, DateTimeImmutable $knownAt, array $policy = []): array
    {
        if (($policy['financial_evidence_required'] ?? false) === true) {
            $failure = $this->financialEvidence($profile, $knownAt);
            if ($failure !== null) {
                return $this->stop($failure);
            }
        }
        if (($profile['complete'] ?? null) !== true || ($profile['registration_qualified'] ?? null) !== true
            || ($profile['regimes_qualified'] ?? null) !== true || ($profile['tax_nature_qualified'] ?? null) !== true
            || ($profile['dere_start_qualified'] ?? null) !== true
            || ! is_string($profile['supplier_id'] ?? null) || ! preg_match('/^[0-9A-Z]{14}$/', $profile['supplier_id'])
            || ($profile['registered_supplier_id'] ?? null) !== $profile['supplier_id']) {
            return $this->stop('PROFILE_OR_REGISTRATION_UNPROVEN');
        }
        foreach (['source_hash', 'registration_source_hash', 'regimes_source_hash', 'tax_nature_source_hash', 'dere_start_source_hash'] as $key) {
            if (! $this->hash($profile[$key] ?? null)) {
                return $this->stop('EVIDENCE_HASH_INVALID');
            }
        }
        // Only the externally qualified active-registration route is currently supported.
        if (($profile['registration_status'] ?? null) !== 'ACTIVE') {
            return $this->stop('REGISTRATION_STATUS_REVIEW_REQUIRED');
        }
        try {
            $start = $this->date($profile['valid_from'] ?? null);
            $finish = $this->date($profile['period_end'] ?? null);
            $validTo = isset($profile['valid_to']) ? $this->date($profile['valid_to']) : null;
            if ($finish !== (new DateTimeImmutable($finish))->format('Y-m-t') || $start > $finish
                || $finish > $knownAt->format('Y-m-d') || ($validTo !== null && $validTo < $finish)
                || $start < $this->date($profile['dere_start_on'] ?? null)
                || $this->date($profile['observed_on'] ?? null) > $knownAt->format('Y-m-d')
                || ! $this->covers($profile['registration_validity'] ?? null, $start, $finish)) {
                return $this->stop('VALIDITY_OR_START_CONFLICT');
            }
            $principal = $profile['regTribPrinc'] ?? null;
            $secondary = $profile['regTribSecund'] ?? null;
            if (! in_array($principal, ['1', '2', '3', '9'], true) || ! is_array($secondary) || ! array_is_list($secondary)
                || count($secondary) > 3 || count(array_unique($secondary, SORT_REGULAR)) !== count($secondary)
                || ! in_array($profile['indNatTrib'] ?? null, ['0', '1'], true)) {
                return $this->stop('REGIMES_OR_TAX_NATURE_INVALID');
            }
            foreach ($secondary as $regime) {
                if (! in_array($regime, ['1', '2', '3'], true) || $regime === $principal) {
                    return $this->stop('REGIMES_OR_TAX_NATURE_INVALID');
                }
            }
            $regimes = [$principal, ...$secondary];
            $groups = ['1' => 'servFinanc', '2' => 'plAssistSaude', '3' => 'prognosticos'];
            $usedCatalogs = [];
            foreach ($groups as $regime => $group) {
                $required = in_array((string) $regime, $regimes, true);
                if ($required !== isset($profile[$group])) {
                    return $this->stop('REGIME_GROUP_CONFLICT');
                }
                if (! $required) {
                    continue;
                }
                $activities = $profile[$group]['tpAtividades'] ?? null;
                $catalog = $catalogs[$group] ?? null;
                if (! is_array($activities) || ! array_is_list($activities) || $activities === [] || count($activities) > 99
                    || count(array_unique($activities, SORT_REGULAR)) !== count($activities)) {
                    return $this->stop('ACTIVITIES_INVALID');
                }
                if (! $this->catalog($catalog, $start, $finish, $knownAt)) {
                    return $this->stop('ACTIVITY_CATALOG_UNPROVEN');
                }
                foreach ($activities as $code) {
                    if (! is_string($code) || ! preg_match('/^[0-9]{2}[A-Z]$/', $code)
                        || ! $this->activity($catalog, $code, $start, $finish)) {
                        return $this->stop('ACTIVITY_NOT_EFFECTIVE');
                    }
                }
                $usedCatalogs[$group] = $catalog['source_hash'];
            }
            if (isset($profile['prognosticos']['UFsCredenc'])) {
                $ufs = $profile['prognosticos']['UFsCredenc'];
                $catalog = $catalogs['UF'] ?? null;
                if (! is_array($ufs) || ! array_is_list($ufs) || $ufs === [] || count($ufs) > 30
                    || count(array_unique($ufs, SORT_REGULAR)) !== count($ufs) || ($profile['accreditations_qualified'] ?? null) !== true
                    || ! $this->hash($profile['accreditations_source_hash'] ?? null) || ! $this->catalog($catalog, $start, $finish, $knownAt)) {
                    return $this->stop('ACCREDITATIONS_UNPROVEN');
                }
                foreach ($ufs as $uf) {
                    if (! is_string($uf) || ! preg_match('/^[0-9]{2}$/', $uf) || ! $this->covers($catalog['codes'][$uf] ?? null, $start, $finish)) {
                        return $this->stop('ACCREDITATION_UF_NOT_EFFECTIVE');
                    }
                }
                $usedCatalogs['UF'] = $catalog['source_hash'];
            }

            return ['status' => 'RESOLVED', 'reason' => 'RTC_DERE_CONTRIBUTOR_LOCAL_QUALIFIED', 'profile' => $profile,
                'catalog_source_hashes' => $usedCatalogs, 'period_end' => $finish,
                'scope' => 'QUALIFIED_D1001_INPUTS_NO_EXTERNAL_VERACITY_OR_RECEPTION', 'production_ready' => false];
        } catch (InvalidArgumentException) {
            return $this->stop('DATE_INVALID');
        }
    }

    private function date(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Missing date.');
        }

        return LocalDate::fromString($value)->value();
    }

    private function covers(mixed $row, string $start, string $finish): bool
    {
        if (! is_array($row)) {
            return false;
        }
        $from = $this->date($row['valid_from'] ?? null);
        $to = isset($row['valid_to']) ? $this->date($row['valid_to']) : null;

        return $from <= $start && ($to === null || $to >= $finish);
    }

    private function catalog(mixed $catalog, string $start, string $finish, DateTimeImmutable $knownAt): bool
    {
        if (is_array($catalog) && ($catalog['membership_basis'] ?? null) === 'OFFICIAL_UNDATED_TABLE_AS_OF_KNOWLEDGE') {
            return ($catalog['qualified'] ?? null) === true && $this->hash($catalog['source_hash'] ?? null)
                && $this->date($catalog['published_on'] ?? null) <= $knownAt->format('Y-m-d')
                && $this->date($catalog['observed_on'] ?? null) <= $knownAt->format('Y-m-d')
                && ($catalog['fiscal_validity'] ?? null) === 'NOT_SPECIFIED_BY_TABLE'
                && ($catalog['table'] ?? null) === '21';
        }

        return is_array($catalog) && ($catalog['qualified'] ?? null) === true && $this->hash($catalog['source_hash'] ?? null)
            && $this->date($catalog['observed_on'] ?? null) <= $knownAt->format('Y-m-d')
            && $this->covers($catalog, $start, $finish);
    }

    /** @param array<string, mixed> $catalog */
    private function activity(array $catalog, string $code, string $start, string $finish): bool
    {
        $row = $catalog['codes'][$code] ?? null;
        if (($catalog['membership_basis'] ?? null) === 'OFFICIAL_UNDATED_TABLE_AS_OF_KNOWLEDGE') {
            return is_array($row) && ($row['code'] ?? null) === $code
                && ($row['fiscal_validity'] ?? null) === 'NOT_SPECIFIED_BY_TABLE';
        }

        return $this->covers($row, $start, $finish);
    }

    /** Independent proofs concern the contributor, not normative dates invented for Table21.
     * @param  array<string, mixed>  $profile
     */
    private function financialEvidence(array $profile, DateTimeImmutable $knownAt): ?string
    {
        if (($profile['regTribPrinc'] ?? null) !== '1' || ($profile['regTribSecund'] ?? null) !== []) {
            return 'FINANCIAL_ONLY_POLICY_SCOPE_CONFLICT';
        }
        $values = ['registration' => $profile['registration_status'] ?? null,
            'regimes' => ['principal' => $profile['regTribPrinc'], 'secondary' => $profile['regTribSecund']],
            'tax_nature' => $profile['indNatTrib'] ?? null, 'dere_start' => $profile['dere_start_on'] ?? null,
            'activities' => $profile['servFinanc']['tpAtividades'] ?? null];
        try {
            $start = $this->date($profile['valid_from'] ?? null);
            $finish = $this->date($profile['period_end'] ?? null);
            foreach ($values as $name => $value) {
                $prefix = strtoupper($name);
                $proof = $profile['evidence'][$name] ?? null;
                if (! is_array($proof) || ($proof['qualified'] ?? null) !== true
                    || (($profile[$name.'_qualified'] ?? null) !== true && $name !== 'activities')) {
                    return $prefix.'_EVIDENCE_UNPROVEN';
                }
                if (($proof['supplier_id'] ?? null) !== ($profile['supplier_id'] ?? null)
                    || ($proof['value'] ?? null) !== $value) {
                    return $prefix.'_IDENTITY_OR_VALUE_CONFLICT';
                }
                if (! $this->hash($proof['source_hash'] ?? null)
                    || ($proof['source_hash'] ?? null) !== ($profile[$name.'_source_hash'] ?? null)) {
                    return $prefix.'_EVIDENCE_HASH_CONFLICT';
                }
                $observed = $this->date($proof['observed_on'] ?? null);
                if ($observed > $knownAt->format('Y-m-d')) {
                    return $prefix.'_EVIDENCE_NOT_KNOWN';
                }
                if ($observed > $this->date($profile['observed_on'] ?? null)) {
                    return $prefix.'_PROFILE_OBSERVATION_CONFLICT';
                }
                if (! $this->covers($proof, $start, $finish)) {
                    return $prefix.'_EVIDENCE_NOT_EFFECTIVE';
                }
            }
        } catch (InvalidArgumentException) {
            return 'EVIDENCE_DATE_INVALID';
        }

        return null;
    }

    private function hash(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_CONTRIBUTOR_'.$reason, 'production_ready' => false];
    }
}
