<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Local binding of independently supplied booked-memory evidence, not authentication or posting. */
final readonly class PriorAdvanceRecordSet89
{
    public function __construct(private RtcScenarioRunService $replayer) {}

    /**
     * @param  array<string, mixed>  $final
     * @param  list<array<string, mixed>>  $captures
     * @param  array<string, mixed>  $book
     * @return array<string, mixed>
     */
    public function bind(array $final, array $captures, array $book, DateTimeImmutable $knownAt): array
    {
        $today = $knownAt->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
        foreach (['operation_id', 'agreement_id', 'supplier_id', 'book_id', 'book_hash', 'destination_state', 'destination_municipality'] as $key) {
            if (! $this->id($final[$key] ?? null)) {
                return $this->stop('FINAL_IDENTITY_REQUIRED');
            }
        }
        if (! $this->date($final['supply_date'] ?? null) || $final['supply_date'] > $today
            || ! $this->money($final['total_base'] ?? null) || $final['total_base'] === '0.00'
            || ($final['currency'] ?? null) !== 'BRL' || ($final['final_registered'] ?? null) !== false) {
            return $this->stop('FINAL_SUPPLY_CONTEXT_UNPROVEN');
        }
        if (! hash_equals($final['book_hash'], CanonicalJson::hash($book))
            || ($book['book_id'] ?? null) !== $final['book_id']) {
            return $this->stop('INDEPENDENT_BOOK_HASH_OR_ID_MISMATCH');
        }
        foreach (['operation_id', 'agreement_id', 'supplier_id'] as $key) {
            if (($book[$key] ?? null) !== $final[$key]) {
                return $this->stop('BOOK_OWNER_OPERATION_BINDING_INVALID');
            }
        }
        if (($book['contract'] ?? null) !== 'LOCAL-PRIOR-ADVANCE-BOOK-89'
            || ($book['state'] ?? null) !== 'ACTIVE' || ($book['currency'] ?? null) !== 'BRL'
            || ! $this->id($book['qualification_id'] ?? null) || ! $this->id($book['evidence_id'] ?? null)
            || ! $this->hash($book['source_hash'] ?? null)
            || ! $this->date($book['qualified_on'] ?? null) || $book['qualified_on'] > $today) {
            return $this->stop('BOOK_QUALIFICATION_UNPROVEN');
        }
        $expected = $book['complete_advance_installment_ids'] ?? null;
        $rows = $book['entries'] ?? null;
        if (! is_array($expected) || ! array_is_list($expected) || $expected === []
            || ! is_array($rows) || ! array_is_list($rows) || count($rows) !== count($expected)
            || ! array_is_list($captures) || count($captures) !== count($rows)) {
            return $this->stop('COMPLETE_RECORD_AND_SNAPSHOT_SET_REQUIRED');
        }
        $ids = [];
        foreach ($expected as $id) {
            if (! $this->id($id) || isset($ids['ID:'.$id])) {
                return $this->stop('EXPECTED_INSTALLMENT_ID_INVALID_OR_DUPLICATE');
            }
            $ids['ID:'.$id] = true;
        }
        $records = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! $this->id($row['installment_id'] ?? null)
                || ! isset($ids['ID:'.$row['installment_id']]) || isset($records['ID:'.$row['installment_id']])) {
                return $this->stop('BOOK_INSTALLMENT_COVERAGE_INVALID');
            }
            $records['ID:'.$row['installment_id']] = $row;
        }
        $covered = $snapshots = $registrationIds = $payments = [];
        $qualified = [];
        $totals = ['IBS_STATE' => DecimalValue::fromString('0.00'), 'IBS_MUNICIPAL' => DecimalValue::fromString('0.00'), 'CBS' => DecimalValue::fromString('0.00')];
        $advancedBase = DecimalValue::fromString('0.00');
        foreach ($captures as $capture) {
            $snapshot = $capture['snapshot'] ?? null;
            $digest = $capture['snapshot_hash'] ?? null;
            if (! is_array($snapshot) || ! $this->hash($digest) || ! hash_equals($digest, CanonicalJson::hash($snapshot))
                || isset($snapshots['SNAP:'.$digest])) {
                return $this->stop('SNAPSHOT_HASH_OR_UNIQUENESS_INVALID');
            }
            $snapshots['SNAP:'.$digest] = true;
            $savedResult = $snapshot['result'] ?? null;
            if (! is_array($savedResult) || ! $this->hash($savedResult['decision_hash'] ?? null)) {
                return $this->stop('SNAPSHOT_RESULT_BODY_HASH_MISMATCH');
            }
            $savedHash = $savedResult['decision_hash'];
            unset($savedResult['decision_hash']);
            if (! hash_equals($savedHash, CanonicalJson::hash($savedResult))) {
                return $this->stop('SNAPSHOT_RESULT_BODY_HASH_MISMATCH');
            }
            try {
                if (! $this->replayer->replay($snapshot)) {
                    return $this->stop('SNAPSHOT_NATIVE_REPLAY_MISMATCH');
                }
            } catch (Throwable) {
                return $this->stop('SNAPSHOT_NATIVE_REPLAY_MISMATCH');
            }
            $stages = $snapshot['result']['trace'] ?? [];
            $nominals = array_values(array_filter($stages, static fn ($r): bool => is_array($r) && ($r['stage'] ?? null) === 'ADVANCE_ORIGIN_NOMINAL'));
            if (count($nominals) !== 1 || ($nominals[0]['status'] ?? null) !== 'LOCAL_ADVANCE_NOMINAL_ONLY'
                || ($snapshot['result']['status'] ?? null) !== 'ACTION_REQUIRED'
                || ($snapshot['result']['reason_codes'] ?? null) !== ['RTC_ADVANCE_REGISTRATION_AND_DOCUMENT_OBLIGATION_NOT_PROVEN']) {
                return $this->stop('QUALIFIED_ADVANCE_SNAPSHOT_REQUIRED');
            }
            $nominal = $nominals[0];
            $event = $nominal['origin']['event'];
            foreach (['operation_id', 'agreement_id', 'supplier_id', 'currency'] as $key) {
                if (($event[$key] ?? null) !== $final[$key]) {
                    return $this->stop('ADVANCE_FINAL_OWNER_BINDING_INVALID');
                }
            }
            $facts = $snapshot['input']['facts'];
            if (($facts['rtc.destination_state'] ?? null) !== $final['destination_state']
                || ($facts['rtc.destination_municipality_ibge'] ?? null) !== $final['destination_municipality']) {
                return $this->stop('ADVANCE_FINAL_DESTINATION_CONFLICT');
            }
            $id = $event['installment_id'];
            if (! isset($records['ID:'.$id]) || isset($covered['ID:'.$id])
                || isset($payments['PAY:'.$event['payment_reference_id']])) {
                return $this->stop('SNAPSHOT_INSTALLMENT_OR_PAYMENT_DUPLICATE_OR_EXTRA');
            }
            $row = $records['ID:'.$id];
            if (($row['snapshot_hash'] ?? null) !== $digest || ($row['decision_hash'] ?? null) !== $snapshot['result']['decision_hash']
                || ($row['kind'] ?? null) !== 'BOOKED_ADVANCE_DEBIT_MEMORY' || ($row['state'] ?? null) !== 'ACTIVE'
                || ! $this->id($row['registration_id'] ?? null) || isset($registrationIds['REG:'.$row['registration_id']])
                || ! $this->id($row['entry_evidence_id'] ?? null)) {
                return $this->stop('PRIOR_RECORD_ORIGIN_OR_IDENTITY_UNPROVEN');
            }
            foreach (['revoked_at', 'replaced_by', 'consumed_by_final_id'] as $field) {
                if (! array_key_exists($field, $row) || $row[$field] !== null) {
                    return $this->stop('PRIOR_RECORD_LIFECYCLE_NOT_OPEN');
                }
            }
            $registered = $this->instant($row['registered_at'] ?? null);
            $snapshotKnown = $this->instant($snapshot['input']['knowledge_at'] ?? null);
            $registeredDate = $registered?->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
            if ($registered === null || $snapshotKnown === null || $registered < $snapshotKnown || $registered > $knownAt
                || $event['event_date'] >= $final['supply_date']
                || $registeredDate >= $final['supply_date'] || $registeredDate > $book['qualified_on']
                || ($row['assessment_period'] ?? null) !== substr($event['event_date'], 0, 7)) {
                return $this->stop('PRIOR_RECORD_DATE_OR_PERIOD_UNPROVEN');
            }
            if (! is_array($row['event'] ?? null) || CanonicalJson::hash($row['event']) !== CanonicalJson::hash($event)) {
                return $this->stop('PRIOR_RECORD_EVENT_CONFLICT');
            }
            if (! is_array($row['taxes'] ?? null) || count($row['taxes']) !== 3) {
                return $this->stop('PRIOR_RECORD_COMPONENT_SET_INVALID');
            }
            foreach ($totals as $tax => $total) {
                $captured = $nominal['taxes'][$tax];
                $booked = $row['taxes'][$tax] ?? null;
                if (! is_array($booked) || CanonicalJson::hash($booked) !== CanonicalJson::hash($captured)
                    || ! $this->money($booked['nominal_amount'] ?? null)) {
                    return $this->stop('PRIOR_RECORD_COMPONENT_VALUE_OR_SOURCE_CONFLICT');
                }
                $totals[$tax] = $total->plus(DecimalValue::fromString($booked['nominal_amount']));
            }
            $covered['ID:'.$id] = true;
            $registrationIds['REG:'.$row['registration_id']] = true;
            $payments['PAY:'.$event['payment_reference_id']] = true;
            $advancedBase = $advancedBase->plus(DecimalValue::fromString($event['base_amount']));
            $qualified[] = ['installment_id' => $id, 'snapshot_hash' => $digest, 'registration_id' => $row['registration_id'],
                'registered_at' => $row['registered_at'], 'assessment_period' => $row['assessment_period'], 'event' => $event,
                'captured_taxes' => $row['taxes']];
        }
        if (count($covered) !== count($ids) || $advancedBase->compareTo(DecimalValue::fromString($final['total_base'])) > 0) {
            return $this->stop('FINAL_TOTAL_OR_ADVANCE_COVERAGE_CONFLICT');
        }
        usort($qualified, static fn (array $a, array $b): int => strcmp($a['installment_id'], $b['installment_id']));

        return ['status' => 'LOCAL_PRIOR_ADVANCE_RECORD_SET_ONLY', 'records' => $qualified,
            'captured_advance_totals' => array_map(static fn (DecimalValue $v): string => $v->value(), $totals),
            'advanced_base' => $advancedBase->value(), 'prior_registration_locally_bound' => true,
            'prior_advance_debits_proven' => false, 'government_evidence_authenticated' => false,
            'extinction_proven' => false, 'credit_appropriation_authorized' => false, 'posting_authorized' => false,
            'assessment_period_legally_qualified' => false, 'document_settlement_complete' => false, 'production_ready' => false];
    }

    private function id(mixed $v): bool
    {
        return is_string($v) && trim($v) !== '' && ! str_contains($v, "\0");
    }

    private function hash(mixed $v): bool
    {
        return is_string($v) && preg_match('/\A[0-9a-f]{64}\z/', $v) === 1;
    }

    private function money(mixed $v): bool
    {
        return is_string($v) && preg_match('/\A(?:0|[1-9][0-9]{0,15})\.[0-9]{2}\z/', $v) === 1;
    }

    private function date(mixed $v): bool
    {
        if (! is_string($v)) {
            return false;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $v);

        return $parsed !== false && $parsed->format('Y-m-d') === $v && $v >= '2026-01-01';
    }

    private function instant(mixed $v): ?DateTimeImmutable
    {
        if (! is_string($v)) {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $v);

        return $parsed !== false && $parsed->format('Y-m-d\TH:i:sP') === $v ? $parsed : null;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => $reason, 'prior_registration_locally_bound' => false,
            'prior_advance_debits_proven' => false, 'posting_authorized' => false, 'credit_appropriation_authorized' => false,
            'government_evidence_authenticated' => false, 'extinction_proven' => false, 'production_ready' => false];
    }
}
