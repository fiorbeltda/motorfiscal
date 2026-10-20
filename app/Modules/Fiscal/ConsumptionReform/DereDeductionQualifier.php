<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

/** Checks qualified source documents and monthly appropriation ceilings, without posting. */
final readonly class DereDeductionQualifier
{
    /** @param array<string, mixed> $operation
     * @param  array<string, mixed>  $proof
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    public function resolve(array $operation, array $proof, DateTimeImmutable $knownAt, array $rule): array
    {
        if (($rule['strategy'] ?? null) !== 'DERE_DOCUMENT_DEDUCTIONS_1_2_0' || ($rule['status'] ?? null) !== 'ACTIVE'
            || ! is_string($rule['published_at'] ?? null) || new DateTimeImmutable($rule['published_at']) > $knownAt
            || ! $this->hash($rule['source_hash'] ?? null)) {
            return $this->stop('RULE_UNAVAILABLE');
        }
        if (($operation['status'] ?? null) !== 'RESOLVED' || ($operation['base']['status'] ?? null) !== 'RESOLVED'
            || ($proof['supplier_id'] ?? null) !== ($operation['qualification']['supplier_id'] ?? null)
            || ($proof['document_id'] ?? null) !== ($operation['qualification']['document_id'] ?? null)
            || ($proof['period_end'] ?? null) !== ($operation['base']['period_end'] ?? null)) {
            return $this->stop('SCOPE_CONFLICT');
        }
        if (($proof['documents_qualified'] ?? null) !== true || ($proof['deduction_allocation_complete'] ?? null) !== true
            || ($proof['monthly_period_closed_previously'] ?? null) !== false || ! $this->hash($proof['source_hash'] ?? null)) {
            return $this->stop('DOCUMENT_QUALIFICATION_OR_ORIGINAL_PERIOD_UNPROVEN');
        }
        if (! in_array($operation['base']['family'] ?? null, ['CREDIT_FX_SECURITIES_ART192', 'SECURITIZATION_FACTORING_ART193'], true)
            || ($operation['base']['art188_reversal'] ?? null) !== '0.00') {
            return $this->stop('FAMILY_OR_COOPERATIVE_ALLOCATION_PENDING');
        }
        $documents = $proof['documents'] ?? null;
        if (! is_array($documents) || ! array_is_list($documents) || $documents === [] || count($documents) > 50000) {
            return $this->stop('DOCUMENTS_INVALID');
        }
        try {
            $end = LocalDate::fromString($proof['period_end'])->value();
            $month = substr($end, 0, 7);
            if ($end !== (new DateTimeImmutable($end))->format('Y-m-t') || $end > $knownAt->format('Y-m-d')
                || LocalDate::fromString($proof['observed_on'] ?? '')->value() > $knownAt->format('Y-m-d')) {
                return $this->stop('PERIOD_OR_OBSERVATION_CONFLICT');
            }
            $entries = [];
            foreach ($operation['base']['entries'] as $entry) {
                if ($entry['bucket'] === 'DEDUCTION') {
                    $entries[$entry['id']] = $this->money($entry['computed_amount']);
                }
            }
            $keys = [];
            $used = [];
            $qualified = [];
            foreach ($documents as $doc) {
                if (! is_array($doc) || ! is_string($doc['chDFe'] ?? null) || ! preg_match('/^[0-9A-Z]{44,53}$/', $doc['chDFe'])
                    || isset($keys[$doc['chDFe']]) || ! in_array($doc['tpDFe'] ?? null, ['01', '02', '03', '04', '05'], true)
                    || ($doc['tpAtiv'] ?? null) !== '01' || ($doc['document_validity_qualified'] ?? null) !== true
                    || ! $this->hash($doc['document_source_hash'] ?? null) || ! is_string($doc['evidence_id'] ?? null) || $doc['evidence_id'] === '') {
                    return $this->stop('DOCUMENT_IDENTITY_OR_EVIDENCE_INVALID');
                }
                $keys[$doc['chDFe']] = true;
                $issued = LocalDate::fromString($doc['dtEmi'] ?? '')->value();
                if ($issued > $end) {
                    return $this->stop('DOCUMENT_ISSUED_AFTER_PERIOD');
                }
                $initial = substr($issued, 0, 7) === $month;
                $operationValue = $this->money($doc['vOper'] ?? null);
                $current = $this->money($doc['vDed'] ?? null);
                $prior = $doc['previous_deduction'] ?? null;
                if ($initial) {
                    if ($prior !== null) {
                        return $this->stop('INITIAL_DOCUMENT_ALREADY_REGISTERED');
                    }
                    $ceiling = $this->money($doc['vDedTotal'] ?? null);
                    $accumulated = $this->money('0.00');
                } else {
                    if (isset($doc['vDedTotal']) || ! is_array($prior) || ! $this->hash($prior['source_hash'] ?? null)
                        || ($prior['chDFe'] ?? null) !== $doc['chDFe'] || ($prior['dtEmi'] ?? null) !== $issued
                        || ($prior['supplier_id'] ?? null) !== $proof['supplier_id'] || ($prior['tpDFe'] ?? null) !== $doc['tpDFe']
                        || ($prior['tpAtiv'] ?? null) !== $doc['tpAtiv'] || ($prior['vOper'] ?? null) !== $doc['vOper']
                        || ($prior['registered_period'] ?? null) !== substr($issued, 0, 7)
                        || ($prior['history_complete'] ?? null) !== true
                        || ($prior['through_period'] ?? null) !== (new DateTimeImmutable($month.'-01'))->modify('-1 month')->format('Y-m')) {
                        return $this->stop('PREVIOUS_DOCUMENT_ACCOUNT_UNPROVEN');
                    }
                    $ceiling = $this->money($prior['vDedTotal'] ?? null);
                    $accumulated = $this->money($prior['accumulated_deduction'] ?? null);
                }
                if ($ceiling->compareTo($operationValue) > 0 || $accumulated->plus($current)->compareTo($ceiling) > 0) {
                    return $this->stop('DOCUMENT_DEDUCTION_CEILING_EXCEEDED');
                }
                $items = $doc['items'] ?? [];
                if (! is_array($items) || ! array_is_list($items) || count($items) > 998
                    || ($initial && in_array($doc['tpDFe'], ['01', '03', '04'], true) && $ceiling->compareTo($operationValue) < 0 && $items === [])
                    || (! $initial && ! is_bool($prior['itemized'] ?? null))
                    || (! $initial && ! $prior['itemized'] && $items !== [])
                    || (! $initial && $prior['itemized'] && $current->compareTo($this->money('0.00')) > 0 && $items === [])) {
                    return $this->stop('ITEM_DETAIL_REQUIRED_OR_INVALID');
                }
                if (! $initial && $prior['itemized']) {
                    $historyItems = $prior['items'] ?? null;
                    if (! is_array($historyItems) || $historyItems === []) {
                        return $this->stop('PREVIOUS_ITEM_ACCOUNT_UNPROVEN');
                    }
                    $historyTotal = $this->money('0.00');
                    $historyUsed = $this->money('0.00');
                    foreach ($historyItems as $historyItem) {
                        if (! is_array($historyItem)) {
                            return $this->stop('PREVIOUS_ITEM_ACCOUNT_UNPROVEN');
                        }
                        $limit = $this->money($historyItem['vItemDedTotal'] ?? null);
                        $taken = $this->money($historyItem['accumulated_deduction'] ?? null);
                        if ($limit->compareTo($this->money($historyItem['vItem'] ?? null)) > 0 || $taken->compareTo($limit) > 0) {
                            return $this->stop('PREVIOUS_ITEM_ACCOUNT_UNPROVEN');
                        }
                        $historyTotal = $historyTotal->plus($limit);
                        $historyUsed = $historyUsed->plus($taken);
                    }
                    if ($historyTotal->compareTo($ceiling) !== 0 || $historyUsed->compareTo($accumulated) !== 0) {
                        return $this->stop('PREVIOUS_ITEM_SUM_CONFLICT');
                    }
                }
                $itemIds = [];
                $itemTotal = $this->money('0.00');
                $itemCurrent = $this->money('0.00');
                foreach ($items as $item) {
                    if (! is_array($item) || ! is_string($item['nItem'] ?? null) || ! preg_match('/^[1-9][0-9]{0,2}$/', $item['nItem'])
                        || isset($itemIds[$item['nItem']]) || ($item['source_item_qualified'] ?? null) !== true) {
                        return $this->stop('ITEM_IDENTITY_INVALID');
                    }
                    $itemIds[$item['nItem']] = true;
                    $value = $this->money($item['vItem'] ?? null);
                    $deducted = $this->money($item['vItemDed'] ?? null);
                    if ($initial) {
                        $limit = $this->money($item['vItemDedTotal'] ?? null);
                        $previous = $this->money('0.00');
                    } else {
                        $history = $prior['items'][$item['nItem']] ?? null;
                        if (isset($item['vItemDedTotal']) || ! is_array($history) || ($history['vItem'] ?? null) !== $item['vItem']) {
                            return $this->stop('PREVIOUS_ITEM_ACCOUNT_UNPROVEN');
                        }
                        $limit = $this->money($history['vItemDedTotal'] ?? null);
                        $previous = $this->money($history['accumulated_deduction'] ?? null);
                    }
                    if ($limit->compareTo($value) > 0 || $previous->plus($deducted)->compareTo($limit) > 0) {
                        return $this->stop('ITEM_DEDUCTION_CEILING_EXCEEDED');
                    }
                    $itemTotal = $itemTotal->plus($limit);
                    $itemCurrent = $itemCurrent->plus($deducted);
                }
                if ($items !== [] && ($itemCurrent->compareTo($current) !== 0 || ($initial && $itemTotal->compareTo($ceiling) !== 0))) {
                    return $this->stop('ITEM_SUM_CONFLICT');
                }
                $links = $doc['financial_entry_ids'] ?? null;
                if (! is_array($links) || ! array_is_list($links)) {
                    return $this->stop('FINANCIAL_LINKS_INVALID');
                }
                $sum = $this->money('0.00');
                foreach ($links as $id) {
                    if (! is_string($id) || ! isset($entries[$id]) || isset($used[$id])) {
                        return $this->stop('FINANCIAL_LINK_UNKNOWN_OR_DUPLICATE');
                    }
                    $used[$id] = true;
                    $sum = $sum->plus($entries[$id]);
                }
                if ($sum->compareTo($current) !== 0) {
                    return $this->stop('FINANCIAL_LINK_AMOUNT_CONFLICT');
                }
                $qualified[] = array_intersect_key($doc, array_flip(['tpDFe', 'chDFe', 'dtEmi', 'tpAtiv', 'vOper', 'vDedTotal', 'vDed', 'items']));
            }
            if (count($used) !== count($entries)) {
                return $this->stop('FINANCIAL_DEDUCTIONS_NOT_COMPLETELY_LINKED');
            }

            return ['status' => 'RESOLVED', 'reason' => 'RTC_DERE_DEDUCTIONS_QUALIFIED', 'period' => $month,
                'documents' => $qualified, 'linked_financial_deductions' => count($used), 'source_hash' => $proof['source_hash'],
                'scope' => 'QUALIFIED_DOCUMENT_DEDUCTIONS_NO_EXTERNAL_DOCUMENT_VERIFICATION', 'posting_performed' => false];
        } catch (InvalidArgumentException) {
            return $this->stop('AMOUNT_OR_DATE_INVALID');
        }
    }

    private function money(mixed $value): DecimalValue
    {
        if (! is_string($value) || ! preg_match('/^(0|[1-9][0-9]{0,14})\.\d{2}$/', $value)) {
            throw new InvalidArgumentException('Nonnegative monetary value required.');
        }

        return DecimalValue::fromString($value);
    }

    private function hash(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_DEDUCTIONS_'.$reason, 'posting_performed' => false];
    }
}
