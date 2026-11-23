<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use DateTimeZone;
use InvalidArgumentException;

/** LC214 art33 calculation, separate from the phase-one technical standard split procedure. */
final readonly class SimplifiedSplitCalculation162
{
    public const CONTRACT = 'RTC-SIMPLIFIED-SPLIT-CALCULATION-A162-V1';

    /** @param list<array<string, mixed>> $parameters
     * @param  list<array<string, mixed>>  $mandatoryActs
     */
    public function __construct(private array $parameters = [], private array $mandatoryActs = []) {}

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function assess(array $input): array
    {
        try {
            return $this->calculate($input);
        } catch (InvalidArgumentException $exception) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => $exception->getMessage(),
                'official' => false, 'posted' => false, 'credit_generated' => false, 'payment_performed' => false];
        }
    }

    /** @param array<string, mixed> $report */
    public function replay(array $report): bool
    {
        return is_array($report['input'] ?? null) && CanonicalJson::hash($this->assess($report['input'])) === CanonicalJson::hash($report);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function calculate(array $input): array
    {
        $g = new SplitLegalEvidence162;
        $g->envelope($input, ['contract', 'currency', 'procedure', 'source_kind', 'source_hash', 'evidence_id', 'input_hash',
            'supplier_id', 'acquirer_id', 'acquirer_regime', 'sector', 'operation_id', 'document_id', 'payment_transaction_id',
            'operation_on', 'known_at', 'originated_at', 'settled_at', 'nominal_values_identified', 'option', 'option_evidence_id',
            'operation_value', 'operation_basis', 'allocation_evidence_id', 'parameter_id', 'synthetic_parameter',
            'mandatory_act_id', 'synthetic_mandatory_act'], self::CONTRACT);
        $g->must($input['procedure'] === 'SIMPLIFIED', 'A162_SIMPLIFIED_PROCEDURE_REQUIRED');
        foreach (['supplier_id', 'acquirer_id', 'sector', 'operation_id', 'document_id', 'payment_transaction_id', 'allocation_evidence_id', 'parameter_id'] as $key) {
            $g->id($input[$key]);
        }
        $on = $g->date($input['operation_on']);
        $known = $g->instant($input['known_at']);
        $settled = $g->instant($input['settled_at']);
        $originated = $g->instant($input['originated_at']);
        $g->must($originated <= $settled && $settled <= $known && $on <= $settled->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d'), 'A162_SETTLEMENT_DATE_CONFLICT');
        $g->must(in_array($input['acquirer_regime'], ['REGULAR', 'NON_REGULAR'], true)
            && is_bool($input['nominal_values_identified']), 'A162_PARTICIPANT_OR_INFORMATION_STATE_REQUIRED');
        $option = $input['option'];
        $g->must(in_array($option, ['EXPLICIT', 'OMITTED_TAX_IDENTIFICATION', 'MANDATORY_JOINT_ACT'], true), 'A162_OPTION_REQUIRED');
        if ($option === 'OMITTED_TAX_IDENTIFICATION') {
            $g->must($input['nominal_values_identified'] === false, 'A162_IMPLICIT_OPTION_CONFLICT');
        } else {
            $g->id($input['option_evidence_id']);
        }
        if ($option === 'MANDATORY_JOINT_ACT') {
            $g->must($input['acquirer_regime'] === 'NON_REGULAR', 'A162_MANDATORY_REGIME_CONFLICT');
            $act = $this->select($input, $this->mandatoryActs, 'mandatory_act_id', 'synthetic_mandatory_act');
            $g->must(($act['authority'] ?? null) === 'CGIBS_RFB_JOINT_ACT', 'A162_JOINT_ACT_REQUIRED');
            $g->lifecycle($act, $this->selectionDate($act, $input), $known);
            $this->scope($act, $input);
        } else {
            $g->must($input['mandatory_act_id'] === null && $input['synthetic_mandatory_act'] === null, 'A162_UNRELATED_MANDATE_CONFLICT');
        }
        $parameter = $this->select($input, $this->parameters, 'parameter_id', 'synthetic_parameter');
        $selectionDate = $this->selectionDate($parameter, $input);
        $g->lifecycle($parameter, $selectionDate, $known);
        $this->scope($parameter, $input);
        $g->must(CanonicalJson::hash($parameter['authorities'] ?? null) === CanonicalJson::hash(['IBS' => 'CGIBS', 'CBS' => 'RFB']), 'A162_BOTH_AUTHORITIES_REQUIRED');
        $percentages = $parameter['percentages'] ?? null;
        $g->must(is_array($percentages) && count($percentages) === 2 && isset($percentages['IBS'], $percentages['CBS']), 'A162_BOTH_PERCENTAGES_REQUIRED');
        $basis = $g->money($input['operation_basis']);
        $operation = $g->money($input['operation_value']);
        $g->must($basis->isPositive() && $basis->compareTo($operation) <= 0, 'A162_OPERATION_ALLOCATION_CONFLICT');
        $rounding = match ($parameter['rounding'] ?? null) {
            'TRUNCATE_TO_CENT' => RoundingMode::Down,
            'HALF_UP_TO_CENT' => RoundingMode::HalfUp,
            default => throw new InvalidArgumentException('A162_QUALIFIED_ROUNDING_REQUIRED'),
        };
        $taxes = [];
        $sum = BigDecimal::of('0.00');
        $percentSum = BigDecimal::of('0');
        foreach (['IBS', 'CBS'] as $tax) {
            $value = $percentages[$tax];
            $g->must(is_string($value) && preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,8})?$/D', $value) === 1, 'A162_PERCENTAGE_REQUIRED');
            $percent = BigDecimal::of($value);
            $g->must($percent->compareTo('100') <= 0, 'A162_PERCENTAGE_RANGE_CONFLICT');
            $percentSum = $percentSum->plus($percent);
            $exact = BigRational::of($basis)->multipliedBy($percent)->dividedBy('100');
            $amount = $exact->toScale(2, $rounding);
            $sum = $sum->plus($amount);
            $taxes[$tax] = ['percentage' => $value, 'exact' => (string) $exact, 'segregated' => (string) $amount];
        }
        $g->must($percentSum->compareTo('100') <= 0 && $sum->compareTo($basis) <= 0, 'A162_SEGREGATION_EXCEEDS_BASIS');
        $report = ['status' => 'LOCAL_SIMPLIFIED_SPLIT_CALCULATED', 'input' => $input, 'law_hash' => SplitLegalEvidence162::LAW_HASH,
            'parameter' => $parameter, 'parameter_selection_basis' => $parameter['selection_basis'], 'parameter_selection_date' => $selectionDate,
            'option' => $option, 'taxes' => $taxes, 'rounding' => $parameter['rounding'],
            'nominal_debit_used_as_cap' => false, 'appropriable_credit_from_segregation' => false,
            'allocation_status' => 'PERIOD_LEDGER_REQUIRED_ART33_PAR3', 'extinction_proven' => false,
            'deadline_status' => 'QUALIFIED_CALENDAR_AND_PERIOD_CLOSURE_REQUIRED', 'official' => false,
            'authenticated' => false, 'deployment_proven' => false, 'posted' => false, 'payment_performed' => false];
        $report['report_hash'] = CanonicalJson::hash($report);

        return $report;
    }

    /** @param array<string, mixed> $input
     * @param  list<array<string, mixed>>  $catalog
     * @return array<string, mixed>
     */
    private function select(array $input, array $catalog, string $idKey, string $syntheticKey): array
    {
        $g = new SplitLegalEvidence162;
        $id = $g->id($input[$idKey]);
        $rows = array_values(array_filter($catalog, static fn (array $row): bool => ($row['id'] ?? null) === $id));
        if ($input[$syntheticKey] !== null) {
            $g->must($input['source_kind'] === 'SYNTHETIC_TEST' && is_array($input[$syntheticKey])
                && ($input[$syntheticKey]['source_kind'] ?? null) === 'SYNTHETIC_TEST'
                && ($input[$syntheticKey]['id'] ?? null) === $id, 'A162_SYNTHETIC_PARAMETER_SCOPE_CONFLICT');
            $rows[] = $input[$syntheticKey];
        }
        $g->must(count($rows) === 1, 'A162_QUALIFIED_PARAMETER_REQUIRED');
        $fields = ['id', 'version', 'environment', 'source_kind', 'status', 'published_at', 'valid_from', 'valid_until',
            'revoked_at', 'superseded_at', 'supplier_id', 'sector', 'selection_basis', 'source_hash', 'evidence_id'];
        $g->fields($rows[0], [...$fields, ...($idKey === 'parameter_id' ? ['authorities', 'percentages', 'rounding'] : ['authority'])]);
        $g->id($rows[0]['version']);
        $g->must($rows[0]['environment'] === 'SANDBOX', 'A162_PARAMETER_ENVIRONMENT_CONFLICT');
        $g->must(($rows[0]['source_kind'] ?? null) === 'OFFICIAL_PUBLISHED'
            || ($input['source_kind'] === 'SYNTHETIC_TEST' && ($rows[0]['source_kind'] ?? null) === 'SYNTHETIC_TEST'), 'A162_PARAMETER_PROVENANCE_CONFLICT');

        return $rows[0];
    }

    /** @param array<string, mixed> $record
     * @param  array<string, mixed>  $input
     */
    private function scope(array $record, array $input): void
    {
        (new SplitLegalEvidence162)->must(in_array($record['supplier_id'] ?? null, ['*', $input['supplier_id']], true)
            && in_array($record['sector'] ?? null, ['*', $input['sector']], true), 'A162_PARAMETER_TAXPAYER_OR_SECTOR_CONFLICT');
    }

    /** @param array<string, mixed> $record
     * @param  array<string, mixed>  $input
     */
    private function selectionDate(array $record, array $input): string
    {
        $g = new SplitLegalEvidence162;
        $zone = new DateTimeZone('America/Sao_Paulo');

        return match ($record['selection_basis'] ?? null) {
            'OPERATION_DATE' => $g->date($input['operation_on']),
            'FINANCIAL_SETTLEMENT' => $g->instant($input['settled_at'])->setTimezone($zone)->format('Y-m-d'),
            'ORIGINATION_DATE' => $g->instant($input['originated_at'])->setTimezone($zone)->format('Y-m-d'),
            default => throw new InvalidArgumentException('A162_QUALIFIED_TEMPORAL_BASIS_REQUIRED'),
        };
    }
}
