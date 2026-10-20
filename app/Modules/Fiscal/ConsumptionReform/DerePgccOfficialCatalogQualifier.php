<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

/** Official membership is separate from dated contributor applicability and mapping evidence. */
final readonly class DerePgccOfficialCatalogQualifier
{
    /** @param array<string, mixed> $plan
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>  $tax
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function qualify(array $plan, array $reference, array $tax, DateTimeImmutable $knownAt, bool $history, array $policy): array
    {
        if (($policy['reference_catalog_sha256'] ?? null) !== CanonicalJson::hash($reference)
            || ($policy['tax_catalog_sha256'] ?? null) !== CanonicalJson::hash($tax)) {
            return $this->stop('OFFICIAL_CATALOG_HASH_CONFLICT');
        }
        try {
            foreach ([$reference, $tax] as $catalog) {
                if (($catalog['qualified'] ?? null) !== true || ($catalog['membership_basis'] ?? null) !== 'OFFICIAL_UNDATED_TABLE_AS_OF_KNOWLEDGE'
                    || ($catalog['fiscal_validity'] ?? null) !== 'NOT_PROVEN_BY_OBSERVED_DOCUMENT'
                    || ! is_string($catalog['source_hash'] ?? null) || preg_match('/^[a-f0-9]{64}$/', $catalog['source_hash']) !== 1) {
                    return $this->stop('OFFICIAL_CATALOG_UNQUALIFIED');
                }
                if (LocalDate::fromString($catalog['observed_on'] ?? '')->value() > $knownAt->format('Y-m-d')) {
                    return $this->stop('OFFICIAL_CATALOG_NOT_KNOWN');
                }
                if (isset($catalog['published_on']) && LocalDate::fromString($catalog['published_on'])->value() > $knownAt->format('Y-m-d')) {
                    return $this->stop('OFFICIAL_CATALOG_NOT_KNOWN');
                }
            }
            if (($reference['planoCtaRef'] ?? null) !== '1' || ($plan['planoCtaRef'] ?? null) !== '1'
                || ($tax['table'] ?? null) !== '11' || ($tax['auxiliary_lists_complete'] ?? null) !== true
                || ! is_array($tax['auxiliary_codes'] ?? null)) {
                return $this->stop('OFFICIAL_CATALOG_SCOPE_UNPROVEN');
            }
            foreach (['D1106', 'D1121', 'D2101'] as $event) {
                $list = $tax['auxiliary_codes'][$event] ?? null;
                if (! is_array($list) || ! array_is_list($list) || $list === [] || count(array_unique($list)) !== count($list)) {
                    return $this->stop('OFFICIAL_AUXILIARY_LIST_UNPROVEN');
                }
                foreach ($list as $code) {
                    if (! is_string($code) || preg_match('/^[1-4][0-9]{8}$/', $code) !== 1) {
                        return $this->stop('OFFICIAL_AUXILIARY_LIST_UNPROVEN');
                    }
                }
            }
            $end = LocalDate::fromString($plan['period_end'] ?? '')->value();
            $start = LocalDate::fromString($plan['valid_from'] ?? '')->value();
            $observed = LocalDate::fromString($plan['observed_on'] ?? '')->value();
            $evidenceKnownAt = new DateTimeImmutable(min($observed, $knownAt->format('Y-m-d')));
            $supplier = $plan['supplier_id'] ?? null;
            $institution = $plan['institution_type'] ?? null;
            if (! is_string($supplier) || preg_match('/^[0-9A-Z]{14}$/', $supplier) !== 1
                || ! is_string($institution) || $institution === '') {
                return $this->stop('INSTITUTION_UNPROVEN');
            }
            if (! $this->proof($plan['institution_evidence'] ?? null, $supplier, $institution, $start, $end, $evidenceKnownAt)) {
                return $this->stop('INSTITUTION_EVIDENCE_UNPROVEN');
            }
            $normalizedReference = [...$reference, 'valid_from' => $start, 'accounts' => []];
            $normalizedTax = [...$tax, 'valid_from' => $start, 'codes' => []];
            $rows = $plan['accounts'] ?? null;
            if (! is_array($rows) || ! array_is_list($rows)) {
                return $this->stop('ACCOUNTS_INVALID');
            }
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    return $this->stop('ACCOUNT_IDENTITY_OR_FIELDS_INVALID');
                }
                $rowStart = LocalDate::fromString($row['valid_from'] ?? '')->value();
                $rowEnd = isset($row['valid_to']) ? LocalDate::fromString($row['valid_to'])->value() : $end;
                if (! $history && ($rowStart > $end || $rowEnd < $end)) {
                    continue;
                }
                $intervalStart = $history ? $rowStart : $end;
                $intervalEnd = min($rowEnd, $end);
                $code = $row['cCtaRef'] ?? null;
                $ref = is_string($code) ? ($reference['accounts'][$code] ?? null) : null;
                if (! is_array($ref) || ($ref['code'] ?? null) !== $code) {
                    return $this->stop('OFFICIAL_REFERENCE_ACCOUNT_ABSENT');
                }
                $referenceNature = match (substr($code, 0, 1)) {
                    '1' => '1', '2' => '2', '6' => '3', '7' => '4', '8' => '5', default => null
                };
                if (($row['codNat'] ?? null) !== $referenceNature) {
                    return $this->stop('REFERENCE_ACCOUNT_NATURE_CONFLICT');
                }
                $mapping = array_intersect_key($row, array_flip(['cCta', 'cCtaRef', 'codTrib', 'natCta', 'codNat']));
                $value = ['institution_type' => $institution, 'mapping' => $mapping];
                if (! $this->proof($row['mapping_evidence'] ?? null, $supplier, $value, $intervalStart, $intervalEnd, $evidenceKnownAt)) {
                    return $this->stop('ACCOUNT_MAPPING_EVIDENCE_UNPROVEN');
                }
                // Dates originate in the independent proof, never in an undated official listing.
                $normalizedReference['accounts'][$code] = ['valid_from' => $start];
                if (($row['indCta'] ?? null) === 'A') {
                    $taxCode = $row['codTrib'] ?? null;
                    $member = is_string($taxCode) ? ($tax['codes'][$taxCode] ?? null) : null;
                    if (! is_array($member) || ($member['code'] ?? null) !== $taxCode) {
                        return $this->stop('OFFICIAL_TAX_CODE_ABSENT');
                    }
                    $nature = match (substr($taxCode, 0, 1)) {
                        '1' => '1', '2' => '2', '3' => '4', '4' => '5', default => null
                    };
                    if (($row['codNat'] ?? null) !== $nature) {
                        return $this->stop('TAX_CODE_ACCOUNT_NATURE_CONFLICT');
                    }
                    $flags = [];
                    foreach (['D1106', 'D1121', 'D2101'] as $event) {
                        $flags[$event] = in_array($taxCode, $tax['auxiliary_codes'][$event], true);
                    }
                    $normalizedTax['codes'][$taxCode] = ['valid_from' => $start, 'auxiliary_requirements' => $flags];
                }
            }

            return ['status' => 'RESOLVED', 'reference' => $normalizedReference, 'tax_codes' => $normalizedTax, 'production_ready' => false];
        } catch (InvalidArgumentException) {
            return $this->stop('DATE_INVALID');
        }
    }

    private function proof(mixed $proof, string $supplier, mixed $value, string $start, string $end, DateTimeImmutable $knownAt): bool
    {
        if (! is_array($proof) || ($proof['qualified'] ?? null) !== true || ($proof['supplier_id'] ?? null) !== $supplier
            || CanonicalJson::encode(['value' => $proof['value'] ?? null]) !== CanonicalJson::encode(['value' => $value])
            || ! is_string($proof['evidence_id'] ?? null) || trim($proof['evidence_id']) === '') {
            return false;
        }
        $hash = $proof['source_hash'] ?? null;
        unset($proof['source_hash']);

        return is_string($hash) && hash_equals(CanonicalJson::hash($proof), $hash)
            && LocalDate::fromString($proof['observed_on'] ?? '')->value() <= $knownAt->format('Y-m-d')
            && LocalDate::fromString($proof['valid_from'] ?? '')->value() <= $start
            && LocalDate::fromString($proof['valid_to'] ?? '')->value() >= $end
            && $start <= $end;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_PGCC_'.$reason, 'production_ready' => false];
    }
}
