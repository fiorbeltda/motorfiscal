<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

/** Validate declared PGCC hierarchy against separately qualified, dated reference rows. */
final readonly class DerePgccQualifier
{
    /** @param array<string, mixed> $plan
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>  $taxCodes
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function resolve(array $plan, array $reference, array $taxCodes, DateTimeImmutable $knownAt, bool $fullDeclaration = false, array $policy = []): array
    {
        if (($policy['official_catalogs_required'] ?? false) === true) {
            $official = (new DerePgccOfficialCatalogQualifier)->qualify($plan, $reference, $taxCodes, $knownAt, $fullDeclaration, $policy);
            if ($official['status'] !== 'RESOLVED') {
                return $official;
            }
            $reference = $official['reference'];
            $taxCodes = $official['tax_codes'];
        }
        if (($plan['complete'] ?? null) !== true || ! $this->hash($plan['source_hash'] ?? null)
            || ($reference['qualified'] ?? null) !== true || ($taxCodes['qualified'] ?? null) !== true
            || ! $this->hash($reference['source_hash'] ?? null) || ! $this->hash($taxCodes['source_hash'] ?? null)
            || ! in_array($plan['planoCtaRef'] ?? null, ['1', '2', '3', '4', '5'], true)
            || ($reference['planoCtaRef'] ?? null) !== $plan['planoCtaRef']
            || ! in_array($plan['freqEncerr'] ?? null, ['A', 'S', 'Q', 'T', 'B', 'M'], true)) {
            return $this->stop('SOURCES_OR_PLAN_UNQUALIFIED');
        }
        $rows = $plan['accounts'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows) || $rows === [] || count($rows) > 150000) {
            return $this->stop('ACCOUNTS_INVALID');
        }
        try {
            $end = LocalDate::fromString($plan['period_end'] ?? '')->value();
            if ($end !== (new DateTimeImmutable($end))->format('Y-m-t') || $end > $knownAt->format('Y-m-d')
                || ! $this->effective($plan, $end) || ! $this->effective($reference, $end) || ! $this->effective($taxCodes, $end)) {
                return $this->stop('PERIOD_OR_CATALOG_NOT_EFFECTIVE');
            }
            foreach ([$plan, $reference, $taxCodes] as $source) {
                if (LocalDate::fromString($source['observed_on'] ?? '')->value() > $knownAt->format('Y-m-d')) {
                    return $this->stop('SOURCE_OBSERVED_IN_FUTURE');
                }
            }
            $accounts = [];
            $seen = [];
            $requiredAuxiliary = [];
            foreach ($rows as $row) {
                if (! is_array($row) || ! is_string($row['cCtaInterna'] ?? null) || ! preg_match('/^[A-Za-z0-9]{1,50}$/', $row['cCtaInterna'])
                    || ! is_string($row['cDbrMista'] ?? null) || ! preg_match('/^[0-9]{3}$/', $row['cDbrMista'])
                    || ($row['cCta'] ?? null) !== $row['cCtaInterna'].$row['cDbrMista'] || isset($seen[$row['cCta']])
                    || ! in_array($row['indCta'] ?? null, ['S', 'A'], true) || ! in_array($row['natCta'] ?? null, ['C', 'D', 'V'], true)
                    || ! in_array($row['codNat'] ?? null, ['1', '2', '3', '4', '5'], true)
                    || ! is_int($row['nivelCta'] ?? null) || $row['nivelCta'] < 1 || $row['nivelCta'] > 99
                    || ! is_string($row['nomeCta'] ?? null) || trim($row['nomeCta']) === '' || mb_strlen($row['nomeCta']) > 100
                    || ! is_string($row['cCtaRef'] ?? null) || ! preg_match('/^[A-Za-z0-9]{1,13}$/', $row['cCtaRef'])) {
                    return $this->stop('ACCOUNT_IDENTITY_OR_FIELDS_INVALID');
                }
                $seen[$row['cCta']] = true;
                if (! $fullDeclaration && ! $this->effective($row, $end)) {
                    continue;
                }
                $accountStart = LocalDate::fromString($row['valid_from'] ?? '')->value();
                $accountEnd = isset($row['valid_to']) ? LocalDate::fromString($row['valid_to'])->value() : $end;
                $accountEnd = min($accountEnd, $end);
                if ($fullDeclaration && $accountStart > $accountEnd) {
                    return $this->stop('DECLARATION_ACCOUNT_PERIOD_INVALID_OR_FUTURE');
                }
                $ref = $reference['accounts'][$row['cCtaRef']] ?? null;
                if (! is_array($ref) || ($fullDeclaration ? ! $this->covers($ref, $accountStart, $accountEnd) : ! $this->effective($ref, $end))) {
                    return $this->stop('REFERENCE_ACCOUNT_NOT_EFFECTIVE');
                }
                if ($row['indCta'] === 'A') {
                    if ($row['nivelCta'] === 1 || ! is_string($row['descCta'] ?? null) || trim($row['descCta']) === '' || mb_strlen($row['descCta']) > 600
                        || ! is_string($row['codTrib'] ?? null) || ! preg_match('/^[1-9][0-9]{8}$/', $row['codTrib'])
                        || (isset($row['indTribISS']) && ! in_array($row['indTribISS'], ['0', '1'], true))) {
                        return $this->stop('ANALYTICAL_DESCRIPTION_OR_TAX_CODE_INVALID');
                    }
                    $tax = $taxCodes['codes'][$row['codTrib']] ?? null;
                    if (! is_array($tax) || ($fullDeclaration ? ! $this->covers($tax, $accountStart, $accountEnd) : ! $this->effective($tax, $end))) {
                        return $this->stop('TAX_CODE_NOT_EFFECTIVE');
                    }
                    foreach (['D1106', 'D1121', 'D2101'] as $event) {
                        if (! is_bool($tax['auxiliary_requirements'][$event] ?? null)) {
                            return $this->stop('AUXILIARY_REQUIREMENTS_UNPROVEN');
                        }
                        if ($tax['auxiliary_requirements'][$event] && $this->effective($row, $end)) {
                            $requiredAuxiliary[$event] = true;
                        }
                    }
                } elseif (isset($row['codTrib']) || isset($row['indTribISS'])) {
                    return $this->stop('SYNTHETIC_TAX_FIELDS_FORBIDDEN');
                }
                $accounts[$row['cCta']] = $row;
            }
            if ($accounts === []) {
                return $this->stop('NO_EFFECTIVE_ACCOUNTS');
            }
            foreach ($accounts as $row) {
                if ($row['nivelCta'] === 1) {
                    if (isset($row['cCtaSup']) || $row['indCta'] !== 'S') {
                        return $this->stop('ROOT_ACCOUNT_INVALID');
                    }
                } else {
                    $parent = $accounts[$row['cCtaSup'] ?? ''] ?? null;
                    if (! is_array($parent) || $parent['indCta'] !== 'S' || $parent['nivelCta'] >= $row['nivelCta']) {
                        return $this->stop('PARENT_ACCOUNT_INVALID');
                    }
                    if ($fullDeclaration && ! $this->covers($parent, $row['valid_from'], min($row['valid_to'] ?? $end, $end))) {
                        return $this->stop('PARENT_ACCOUNT_HISTORY_GAP');
                    }
                }
                if ($row['cDbrMista'] !== '000') {
                    $original = $accounts[$row['cCtaInterna'].'000'] ?? null;
                    if ($row['indCta'] !== 'A' || ! is_array($original) || $original['indCta'] !== 'S'
                        || $row['cCtaRef'] !== $original['cCtaRef'] || $row['nivelCta'] !== $original['nivelCta'] + 1
                        || ($row['cCtaSup'] ?? null) !== ($original['cCtaSup'] ?? null)) {
                        return $this->stop('MIXED_ACCOUNT_SPLIT_CONFLICT');
                    }
                    if ($fullDeclaration && ! $this->covers($original, $row['valid_from'], min($row['valid_to'] ?? $end, $end))) {
                        return $this->stop('MIXED_ACCOUNT_HISTORY_GAP');
                    }
                }
            }

            $events = array_keys($requiredAuxiliary);
            sort($events);

            return ['status' => 'RESOLVED', 'reason' => 'RTC_DERE_PGCC_LOCAL_QUALIFIED', 'accounts' => array_values($accounts), 'required_auxiliary_events' => $events,
                'source_hash' => $plan['source_hash'], 'reference_source_hash' => $reference['source_hash'], 'tax_codes_source_hash' => $taxCodes['source_hash'],
                'period_end' => $end, 'planoCtaRef' => $plan['planoCtaRef'], 'freqEncerr' => $plan['freqEncerr'],
                'scope' => $fullDeclaration ? 'QUALIFIED_FULL_PGCC_THROUGH_PERIOD_END_NO_EXTERNAL_SOURCE_VERACITY_OR_RECEIPT' : 'QUALIFIED_REFERENCE_ROWS_AND_HIERARCHY_NO_EXTERNAL_SOURCE_VERACITY_OR_RECEIPT', 'production_ready' => false];
        } catch (InvalidArgumentException) {
            return $this->stop('DATE_INVALID');
        }
    }

    /** @param array<string, mixed> $row */
    private function effective(array $row, string $end): bool
    {
        $start = LocalDate::fromString($row['valid_from'] ?? '')->value();
        $finish = isset($row['valid_to']) ? LocalDate::fromString($row['valid_to'])->value() : null;
        if ($finish !== null && $finish < $start) {
            throw new InvalidArgumentException('Account validity ends before its start.');
        }

        return $start <= $end && ($finish === null || ($finish >= $start && $finish >= $end));
    }

    /** @param array<string, mixed> $row */
    private function covers(array $row, string $start, string $finish): bool
    {
        return $this->effective($row, $start) && $this->effective($row, $finish);
    }

    private function hash(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_PGCC_'.$reason, 'production_ready' => false];
    }
}
