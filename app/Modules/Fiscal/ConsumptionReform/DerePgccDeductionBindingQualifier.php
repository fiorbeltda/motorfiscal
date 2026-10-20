<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

/** Select D1121 entries from a qualified PGCC and prove their document/account bindings. */
final readonly class DerePgccDeductionBindingQualifier
{
    /** @param array<string, mixed> $operation
     * @param  array<string, mixed>  $month
     * @param  array<string, mixed>  $pgcc
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    public function resolve(array $operation, array $month, array $pgcc, DateTimeImmutable $knownAt, array $rule): array
    {
        if (($pgcc['status'] ?? null) !== 'RESOLVED' || ($operation['base']['status'] ?? null) !== 'RESOLVED'
            || ($rule['pgcc_qualification_policy']['official_catalogs_required'] ?? false) !== true) {
            return $this->stop('QUALIFIED_CONTEXT_UNPROVEN');
        }
        if (($operation['base']['family'] ?? null) !== 'CREDIT_FX_SECURITIES_ART192' || ($operation['base']['art188_reversal'] ?? null) !== '0.00') {
            return $this->stop('FINANCIAL_FAMILY_UNSUPPORTED');
        }
        $codes = $rule['pgcc_catalogs']['tax_codes']['auxiliary_codes']['D1121'] ?? null;
        $mappings = $rule['deduction_entry_mappings'] ?? null;
        if (! is_array($codes) || ! array_is_list($codes) || ! is_array($mappings)) {
            return $this->stop('CODE_POLICY_UNPROVEN');
        }
        try {
            $accounts = [];
            foreach ($pgcc['accounts'] as $row) {
                if ($row['indCta'] !== 'A') {
                    continue;
                }
                $required = in_array($row['codTrib'], $codes, true);
                if ($required && ! is_array($mappings[$row['codTrib']] ?? null)) {
                    return $this->stop('CODE_MAPPING_NOT_SUPPORTED');
                }
                $accounts[$row['cCta']] = ['code' => $row['codTrib'], 'required' => $required];
            }
            $entries = [];
            foreach ($operation['base']['entries'] as $entry) {
                if (! is_string($entry['id'] ?? null) || isset($entries[$entry['id']])) {
                    return $this->stop('ENTRY_IDENTITY_INVALID');
                }
                $entries[$entry['id']] = $entry;
            }
            $eligible = $excluded = $seen = [];
            $total = $otherTotal = DecimalValue::fromString('0.00');
            foreach ($month['book']['accounts'] as $account) {
                $plan = $accounts[$account['cCta']] ?? null;
                $links = $account['financial_entry_ids'] ?? null;
                if (! is_array($plan) || ! is_array($links) || ! array_is_list($links)) {
                    return $this->stop('BOOK_ACCOUNT_OR_LINKS_INVALID');
                }
                $accountTotal = DecimalValue::fromString('0.00');
                foreach ($links as $id) {
                    if (! is_string($id) || ! isset($entries[$id]) || isset($seen[$id])) {
                        return $this->stop('ENTRY_LINK_UNKNOWN_OR_DUPLICATE');
                    }
                    $seen[$id] = true;
                    $entry = $entries[$id];
                    $amount = $this->money($entry['computed_amount']);
                    if ($plan['required']) {
                        $mapping = $mappings[$plan['code']];
                        if (($entry['bucket'] ?? null) !== 'DEDUCTION' || ($entry['kind'] ?? null) !== ($mapping['kind'] ?? null)
                            || ($entry['advisor_category'] ?? null) !== ($mapping['advisor_category'] ?? null)) {
                            return $this->stop('ENTRY_CODE_CLASSIFICATION_CONFLICT');
                        }
                        $eligible[$id] = ['account' => $account['cCta'], 'codTrib' => $plan['code'], 'entry' => $entry];
                        $total = $total->plus($amount);
                        $accountTotal = $accountTotal->plus($amount);
                    } elseif (($entry['bucket'] ?? null) === 'DEDUCTION') {
                        $excluded[] = $id;
                        $otherTotal = $otherTotal->plus($amount);
                    }
                }
                if ($plan['required']) {
                    if ($accountTotal->compareTo($this->money($account['vApur'] ?? null)) !== 0) {
                        return $this->stop('REPORTABLE_ACCOUNT_AMOUNT_CONFLICT');
                    }
                    if ($accountTotal->compareTo(DecimalValue::fromString('0.00')) > 0 && ($account['natVApur'] ?? null) !== 'D') {
                        return $this->stop('REPORTABLE_ACCOUNT_NATURE_CONFLICT');
                    }
                }
            }
            if (count($seen) !== count($entries)) {
                return $this->stop('LEDGER_LINKS_INCOMPLETE');
            }
            $proof = $month['deductions'] ?? null;
            if ($total->compareTo(DecimalValue::fromString('0.00')) === 0) {
                if ($proof !== null) {
                    return $this->stop('UNEXPECTED_DOCUMENT_DETAIL');
                }
            } else {
                if (! is_array($proof) || ! is_array($proof['documents'] ?? null) || ! array_is_list($proof['documents']) || $proof['documents'] === []) {
                    return $this->stop('DOCUMENTS_REQUIRED');
                }
                $used = [];
                if (! is_string($proof['observed_on'] ?? null)) {
                    return $this->stop('DOCUMENT_ACCOUNT_EVIDENCE_UNPROVEN');
                }
                foreach ($proof['documents'] as $doc) {
                    if (! is_array($doc) || ! is_array($doc['financial_entry_ids'] ?? null) || ! array_is_list($doc['financial_entry_ids'])
                        || $doc['financial_entry_ids'] === [] || ! is_array($doc['pgcc_account_codes'] ?? null) || ! array_is_list($doc['pgcc_account_codes'])) {
                        return $this->stop('DOCUMENT_LINKS_UNPROVEN');
                    }
                    $linkedAccounts = [];
                    foreach ($doc['financial_entry_ids'] as $id) {
                        if (! is_string($id) || ! isset($eligible[$id])) {
                            return $this->stop('DOCUMENT_ENTRY_NOT_REPORTABLE');
                        }
                        if (isset($used[$id])) {
                            return $this->stop('DOCUMENT_ENTRY_DUPLICATE');
                        }
                        $used[$id] = true;
                        $linkedAccounts[$eligible[$id]['account']] = true;
                    }
                    $expected = array_keys($linkedAccounts);
                    $declared = $doc['pgcc_account_codes'];
                    sort($expected);
                    sort($declared);
                    if ($expected !== $declared) {
                        return $this->stop('DOCUMENT_PGCC_ACCOUNT_CONFLICT');
                    }
                    $evidence = $doc['account_binding_evidence'] ?? null;
                    $value = array_intersect_key($doc, array_flip(['chDFe', 'tpDFe', 'dtEmi', 'tpAtiv', 'vDed', 'financial_entry_ids', 'pgcc_account_codes']));
                    $value['period_end'] = $month['book']['period_end'];
                    if (! $this->proof($evidence, $month['book']['supplier_id'], $value, $operation['base']['period_start'],
                        $month['book']['period_end'], min($knownAt->format('Y-m-d'), $proof['observed_on'] ?? ''))) {
                        return $this->stop('DOCUMENT_ACCOUNT_EVIDENCE_UNPROVEN');
                    }
                }
                if (count($used) !== count($eligible)) {
                    return $this->stop('REPORTABLE_ENTRIES_NOT_COMPLETELY_LINKED');
                }
            }
            $scoped = $operation;
            $scoped['base']['entries'] = array_values(array_map(static fn (array $row): array => $row['entry'], $eligible));

            return ['status' => 'RESOLVED', 'reason' => 'RTC_DERE_D1121_PGCC_BINDING_QUALIFIED',
                'operation' => $scoped, 'eligible_entry_ids' => array_keys($eligible), 'excluded_deduction_entry_ids' => $excluded,
                'reportable_amount' => $total->value(), 'other_financial_deductions' => $otherTotal->value(),
                'pgcc_source_hash' => $pgcc['source_hash'], 'book_source_hash' => $month['book']['book_source_hash'],
                'operation_hash' => CanonicalJson::hash($operation), 'scoped_entries_hash' => CanonicalJson::hash($scoped['base']['entries']),
                'scope' => 'LOCAL_D1121_RECONCILIATION_WITH_QUALIFIED_PGCC_AND_INDEPENDENT_DOCUMENT_ACCOUNT_PROOFS', 'production_ready' => false];
        } catch (InvalidArgumentException) {
            return $this->stop('AMOUNT_OR_DATE_INVALID');
        }
    }

    /** @param array<string, mixed> $value */
    private function proof(mixed $proof, string $supplier, array $value, string $start, string $end, string $knownOn): bool
    {
        if (! is_array($proof) || ($proof['qualified'] ?? null) !== true || ($proof['supplier_id'] ?? null) !== $supplier
            || ! is_string($proof['evidence_id'] ?? null) || trim($proof['evidence_id']) === ''
            || CanonicalJson::encode(['value' => $proof['value'] ?? null]) !== CanonicalJson::encode(['value' => $value])) {
            return false;
        }
        $hash = $proof['source_hash'] ?? null;
        unset($proof['source_hash']);

        return is_string($hash) && hash_equals(CanonicalJson::hash($proof), $hash)
            && LocalDate::fromString($proof['observed_on'] ?? '')->value() <= LocalDate::fromString($knownOn)->value()
            && LocalDate::fromString($proof['valid_from'] ?? '')->value() <= $start
            && LocalDate::fromString($proof['valid_to'] ?? '')->value() >= $end;
    }

    private function money(mixed $value): DecimalValue
    {
        if (! is_string($value) || preg_match('/^(0|[1-9][0-9]{0,14})\.\d{2}$/', $value) !== 1) {
            throw new InvalidArgumentException('Nonnegative monetary value required.');
        }

        return DecimalValue::fromString($value);
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_D1121_PGCC_'.$reason, 'production_ready' => false];
    }
}
