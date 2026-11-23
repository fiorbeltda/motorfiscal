<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;

/** Typed adapter for freshly executed results, not a substitute for their documentary decisions. */
final readonly class SupplyCalculation165
{
    /** @param array<string, mixed> $facts
     * @param  array<string, mixed>  $rtc
     * @return array<string, mixed>|null
     */
    public function read(array $facts, array $rtc): ?array
    {
        $synthetic = false;
        $status = $rtc['status'];
        $reasons = $rtc['reason_codes'];
        $decision = $rtc['decision'];
        $trace = $rtc['trace'];
        $syntheticRows = array_values(array_filter($trace, static fn (array $r): bool => ($r['stage'] ?? null) === 'SYNTHETIC_ARITHMETIC156'));
        if ($syntheticRows !== []) {
            if (count($syntheticRows) !== 1 || ! in_array('RTC_SYNTHETIC_TEST_ONLY', $reasons, true)) {
                return null;
            }
            $synthetic = true;
            $status = $syntheticRows[0]['candidate_status'];
            $reasons = $syntheticRows[0]['candidate_reasons'];
            $decision = $syntheticRows[0]['candidate_decision'];
        }
        $g = new SplitLegalEvidence162;
        $zero = '0.00';
        $common = ['synthetic' => $synthetic, 'final_component_status' => $rtc['status'],
            'reason_codes' => $rtc['reason_codes'], 'cash_due_derived' => false, 'posting_authorized' => false];
        if ($status === 'VALID' && isset($decision['base'], $decision['ibs']['state_amount'], $decision['cbs']['nominal_amount'])) {
            return $common + ['basis' => 'COMPONENT_DECISION', 'gross_brl' => (string) $g->money($decision['base']['gross']),
                'taxable_base_brl' => (string) $g->money($decision['base']['taxable']), 'foreign_immune_gross_brl' => $zero,
                'ibs_state_brl' => (string) $g->money($decision['ibs']['state_amount']),
                'ibs_municipal_brl' => (string) $g->money($decision['ibs']['municipal_amount']),
                'cbs_brl' => (string) $g->money($decision['cbs']['nominal_amount'])];
        }
        if ($status === 'VALID' && ($facts['rtc.market'] ?? null) === 'EXPORT'
            && ($decision['ibs']['status'] ?? null) === 'IMMUNE_EXPORT' && ($decision['cbs']['status'] ?? null) === 'IMMUNE_EXPORT'
            && ($facts['rtc.gross_currency'] ?? null) === 'BRL'
            && is_string($facts['rtc.export_share_percent'] ?? null) && BigDecimal::of($facts['rtc.export_share_percent'])->compareTo('100') === 0) {
            return $common + ['basis' => 'COMPONENT_EXPORT_IMMUNITY', 'gross_brl' => (string) $g->money($facts['rtc.gross_amount']),
                'taxable_base_brl' => $zero, 'foreign_immune_gross_brl' => (string) $g->money($facts['rtc.gross_amount']),
                'ibs_state_brl' => $zero, 'ibs_municipal_brl' => $zero, 'cbs_brl' => $zero];
        }
        if ($status !== 'ACTION_REQUIRED' || ! in_array($reasons,
            [['RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN'], ['RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN']], true)) {
            return null;
        }
        $single = static function (string $stage) use ($trace): ?array {
            $rows = array_values(array_filter($trace, static fn (array $r): bool => ($r['stage'] ?? null) === $stage));

            return count($rows) === 1 ? $rows[0] : null;
        };
        if (($facts['rtc.market'] ?? null) === 'IMPORT') {
            $base = $single('BASE');
            $nominal = $single('IMPORT_NOMINAL_CALCULATION');
            if ($base === null || $nominal === null) {
                return null;
            }

            return $common + ['basis' => 'IMPORT_DOCUMENT_PENDING_NOMINAL_CHECKPOINT',
                'checkpoint_hash' => CanonicalJson::hash([$base, $nominal]),
                'gross_brl' => (string) $g->money($base['gross']), 'taxable_base_brl' => (string) $g->money($base['taxable_base']),
                'foreign_immune_gross_brl' => $zero, 'ibs_state_brl' => (string) $g->money($nominal['ibs_state']),
                'ibs_municipal_brl' => (string) $g->money($nominal['ibs_municipal']), 'cbs_brl' => (string) $g->money($nominal['cbs'])];
        }
        $nominal = $single('PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION');
        if (($facts['rtc.market'] ?? null) !== 'EXPORT' || $nominal === null) {
            return null;
        }
        $foreign = $g->money($nominal['foreign_immune_gross_brl']);
        $domestic = $g->money($nominal['domestic_taxable_base_brl']);

        return $common + ['basis' => 'PARTIAL_EXPORT_DOCUMENT_PENDING_NOMINAL_CHECKPOINT', 'checkpoint_hash' => CanonicalJson::hash($nominal),
            'gross_brl' => (string) $foreign->plus($domestic), 'taxable_base_brl' => (string) $domestic,
            'foreign_immune_gross_brl' => (string) $foreign, 'ibs_state_brl' => (string) $g->money($nominal['ibs_state_nominal_brl']),
            'ibs_municipal_brl' => (string) $g->money($nominal['ibs_municipal_nominal_brl']), 'cbs_brl' => (string) $g->money($nominal['cbs_nominal_brl'])];
    }
}
