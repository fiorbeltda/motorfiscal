<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/** Local arithmetic contract. Does not establish deployment, settlement or extinction. */
final readonly class SplitPayment158
{
    public const CONTRACT = 'RTC-SPLIT-CALCULATION-A158-V1';

    public const OPERATIONS_MANUAL_HASH = 'f5b03d9484fbbe98edec24cd0531d49fb08be6652026c311a051f78b890ec24a';

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function calculateSegregation(array $input): array
    {
        try {
            return $this->calculate($input);
        } catch (InvalidArgumentException $exception) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => $exception->getMessage(),
                'official' => false, 'posted' => false, 'payment_performed' => false];
        }
    }

    /** @param array<string, mixed> $report */
    public function replay(array $report): bool
    {
        return is_array($report['input'] ?? null)
            && CanonicalJson::hash($this->calculateSegregation($report['input'])) === CanonicalJson::hash($report);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function calculate(array $input): array
    {
        $this->require(($input['contract'] ?? null) === self::CONTRACT, 'A158_CALCULATION_CONTRACT_REQUIRED');
        $this->require(($input['manual_hash'] ?? null) === self::OPERATIONS_MANUAL_HASH, 'A158_MANUAL_VERSION_REQUIRED');
        $this->require(($input['procedure'] ?? null) === 'STANDARD', 'A158_PHASE1_DOES_NOT_IMPLEMENT_SIMPLIFIED');
        $this->require(($input['currency'] ?? null) === 'BRL', 'A158_BRL_REQUIRED');
        $this->require(in_array($input['source_kind'] ?? null, ['SYNTHETIC_TEST', 'DOCUMENTARY_INPUT'], true), 'A158_INPUT_PROVENANCE_REQUIRED');
        $this->require(is_string($input['transaction_id'] ?? null) && trim($input['transaction_id']) !== ''
            && trim($input['transaction_id']) === $input['transaction_id'], 'A158_TRANSACTION_ID_REQUIRED');
        $body = $input;
        unset($body['input_hash']);
        $this->require(($input['input_hash'] ?? null) === CanonicalJson::hash($body), 'A158_INPUT_HASH_CONFLICT');
        $arrangement = $input['arrangement'] ?? null;
        $receiver = in_array($arrangement, ['BOLETO', 'PIX_DYNAMIC', 'PIX_AUTOMATIC'], true);
        $this->require($receiver || in_array($arrangement, ['PIX_STATIC', 'TED', 'TEF'], true), 'A158_PHASE1_ARRANGEMENT_UNSUPPORTED');
        $this->require(($input['originator'] ?? null) === ($receiver ? 'RECEIVER' : 'PAYER'), 'A158_ORIGINATOR_CONFLICT');
        $original = $this->money($input['original_amount'] ?? null);
        $paid = $this->money($input['paid_amount'] ?? null);
        $this->require($original->isPositive(), 'A158_POSITIVE_ORIGINAL_REQUIRED');
        $this->require(is_array($input['taxes'] ?? null) && count($input['taxes']) === 2
            && array_key_exists('IBS', $input['taxes']) && array_key_exists('CBS', $input['taxes']), 'A158_TWO_TAX_COMPONENTS_REQUIRED');
        $informedSum = BigDecimal::of('0.00');
        foreach ($input['taxes'] as $record) {
            $this->require(is_array($record), 'A158_TAX_RECORD_REQUIRED');
            $informedSum = $informedSum->plus($this->money($record['informed'] ?? null));
        }
        $this->require($informedSum->compareTo($original) <= 0, 'A158_INFORMED_TAXES_EXCEED_ORIGINAL');
        $taxes = [];
        foreach (['IBS', 'CBS'] as $tax) {
            $record = $input['taxes'][$tax];
            $this->require(is_array($record), 'A158_TAX_RECORD_REQUIRED');
            $informed = $this->money($record['informed'] ?? null);
            $corrected = array_key_exists('corrected', $record) ? $this->money($record['corrected']) : null;
            $outstanding = array_key_exists('outstanding', $record) ? $this->money($record['outstanding']) : null;
            $this->require($receiver || ($corrected === null && $outstanding === null), 'A158_PAYER_HAS_NO_SUPER_INTELLIGENT_WINDOW');
            $reference = $corrected ?? $informed;
            $candidate = $receiver
                ? BigRational::of($paid)->multipliedBy($reference)->dividedBy($original)
                : BigRational::of($informed);
            if ($receiver && $candidate->compareTo($reference) > 0) {
                $candidate = BigRational::of($reference);
            }
            if ($outstanding !== null && $candidate->compareTo($outstanding) > 0) {
                $candidate = BigRational::of($outstanding);
            }
            $taxes[$tax] = ['informed' => (string) $informed, 'corrected' => $corrected === null ? null : (string) $corrected,
                'outstanding' => $outstanding === null ? null : (string) $outstanding,
                'reference' => (string) $reference,
                'segregated' => (string) $candidate->toScale(2, RoundingMode::Down),
                'formula' => $receiver ? 'MIN(PAID/ORIGINAL*REFERENCE,REFERENCE,OUTSTANDING_IF_PRESENT)' : 'INFORMED'];
        }
        $report = ['status' => 'LOCAL_SPLIT_ARITHMETIC_CALCULATED', 'contract' => self::CONTRACT,
            'manual_hash' => self::OPERATIONS_MANUAL_HASH, 'manual_sections' => ['5.1', '5.2', '5.3', '5.4', '7.1'],
            'input' => $input, 'taxes' => $taxes, 'proportionality' => $receiver,
            'calculation_actor' => $arrangement === 'BOLETO' ? 'NUCLEA' : ($receiver ? 'DIRECT_RECEIVER_PSP' : 'NO_PROPORTIONALITY'),
            'rounding' => 'TRUNCATE_TO_CENT', 'official' => false, 'authenticated' => false,
            'posted' => false, 'payment_performed' => false, 'settlement_proven' => false,
            'extinction_proven' => false, 'deployment_proven' => false];
        $report['report_hash'] = CanonicalJson::hash($report);

        return $report;
    }

    private function money(mixed $value): BigDecimal
    {
        if (! is_string($value) || preg_match('/^(?:0|[1-9][0-9]*)\.[0-9]{2}$/D', $value) !== 1) {
            throw new InvalidArgumentException('A158_CANONICAL_NONNEGATIVE_MONEY_REQUIRED');
        }

        return BigDecimal::of($value);
    }

    private function require(bool $condition, string $reason): void
    {
        if (! $condition) {
            throw new InvalidArgumentException($reason);
        }
    }
}
