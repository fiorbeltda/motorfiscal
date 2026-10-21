<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CreditInput122;

dataset('credit122-amounts', [
    'decimal' => ['0.25', 'RESOLVED'], 'integer' => [1, 'RESOLVED'], 'zero' => [0, 'RESOLVED'],
    'exact negative left to existing arithmetic guard' => ['-0.25', 'RESOLVED'],
    'true' => [true, 'INVALID'], 'false' => [false, 'INVALID'], 'binary fraction' => [1.1, 'INVALID'],
    'binary integral' => [1.0, 'INVALID'], 'empty' => ['', 'INVALID'], 'blank' => [' ', 'INVALID'],
    'exponent' => ['1e-2', 'INVALID'], 'plus' => ['+1', 'INVALID'], 'leading blank' => [' 1', 'INVALID'],
    'trailing blank' => ['1 ', 'INVALID'], 'array' => [[], 'INVALID'], 'trailing token' => ['1.0x', 'INVALID'],
]);

it('requires exact nested credit amounts without granting or proving credit', function (mixed $amount, string $status): void {
    $facts = ['rtc.input_document_id' => 'DOC', 'rtc.credit_adjustments' => [
        ['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => $amount, 'evidence_id' => 'E'],
    ]];
    $before = serialize($facts);
    $result = (new CreditInput122)->qualify($facts, 'DOMESTIC');
    expect($result['status'])->toBe($status)->and($result['credit_granted'])->toBeFalse()
        ->and($result['document_authenticated'])->toBeFalse()->and($result['extinction_proven'])->toBeFalse()
        ->and(serialize($facts))->toBe($before);
})->with('credit122-amounts');

it('requires meaningful input and import identifiers without trimming or inventing values', function (string $key, mixed $value): void {
    $facts = ['rtc.input_document_id' => 'DOC', 'rtc.credit_adjustments' => [], $key => $value];
    $result = (new CreditInput122)->qualify($facts, 'IMPORT');
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['missing_facts'])->toBe([$key]);
})->with(['rtc.input_document_id', 'rtc.import_document_id', 'rtc.import_credit_fiscal_document_id', 'rtc.import_credit_extinction_evidence_id'])
    ->with(['', ' ', "\t\n", 1, true]);

it('keeps missing import extinction evidence conditional on existing treatment rules', function (): void {
    $result = (new CreditInput122)->qualify(['rtc.input_document_id' => 'DOC', 'rtc.credit_adjustments' => []], 'IMPORT');
    expect($result['status'])->toBe('RESOLVED')->and($result['extinction_proven'])->toBeFalse();
});

it('rejects blank evidence and invalid explicit line references', function (string $key, mixed $value): void {
    $row = ['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '0.25', 'evidence_id' => 'E', $key => $value];
    $result = (new CreditInput122)->qualify(['rtc.input_document_id' => 'DOC', 'rtc.credit_adjustments' => [$row]], 'DOMESTIC');
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['missing_facts'])->toBe(['rtc.credit_adjustments'])
        ->and($result['row_index'])->toBe(0);
})->with(['evidence_id', 'line_reference'])->with([[''], [' '], ["\t"], [true], [1], [null], [[]]]);

it('requires distinct explicit identities only within the same document tax kind and evidence', function (string $mutation, string $status): void {
    $a = ['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '0.25', 'evidence_id' => 'E'];
    $b = $a;
    switch ($mutation) {
        case 'identical': break;
        case 'unrelated fields': $a['document_id'] = 'A';
            $b['document_id'] = 'B';
            break;
        case 'evidence whitespace': $b['evidence_id'] = ' E ';
            break;
        case 'different amounts': $b['amount'] = '0.50';
            break;
        case 'one line only': $a['line_reference'] = '1';
            break;
        case 'same line': $a['line_reference'] = '1';
            $b['line_reference'] = '1';
            break;
        case 'same trimmed line': $a['line_reference'] = '1';
            $b['line_reference'] = ' 1 ';
            break;
        case 'different lines': $a['line_reference'] = '1';
            $b['line_reference'] = '2';
            break;
        case 'different tax': $b['tax'] = 'CBS';
            break;
        case 'different kind': $b['kind'] = 'DISALLOWANCE';
            break;
        case 'different evidence': $b['evidence_id'] = 'E2';
            break;
        default: throw new RuntimeException('Unknown test');
    }
    expect((new CreditInput122)->qualify(['rtc.input_document_id' => 'DOC', 'rtc.credit_adjustments' => [$a, $b]], 'DOMESTIC')['status'])->toBe($status);
})->with([
    ['identical', 'ACTION_REQUIRED'], ['unrelated fields', 'ACTION_REQUIRED'], ['evidence whitespace', 'ACTION_REQUIRED'],
    ['different amounts', 'ACTION_REQUIRED'], ['one line only', 'ACTION_REQUIRED'], ['same line', 'ACTION_REQUIRED'],
    ['same trimmed line', 'ACTION_REQUIRED'], ['different lines', 'RESOLVED'], ['different tax', 'RESOLVED'],
    ['different kind', 'RESOLVED'], ['different evidence', 'RESOLVED'],
]);

it('maps existing credit arithmetic refusals to correction facts without inventing parameter evidence', function (string $reason, array $missing, array $expected): void {
    expect((new CreditInput122)->correctionFacts($reason, $missing))->toBe($expected);
})->with([
    ['RTC_NEGATIVE_INPUT_CREDIT', [], ['rtc.input_ibs_amount', 'rtc.input_cbs_amount']],
    ['RTC_NEGATIVE_CREDIT_ADJUSTMENT', [], ['rtc.credit_adjustments']],
    ['RTC_CREDIT_ADJUSTMENT_EXCEEDS_AVAILABLE', [], ['rtc.credit_adjustments']],
    ['RTC_CREDIT_ADJUSTMENT_UNPROVEN', [], ['rtc.credit_adjustments']],
    ['RTC_CREDIT_TAX_UNKNOWN', [], ['rtc.credit_adjustments']],
    ['RTC_IMPORT_CREDIT_PROOF_MISSING', ['rtc.import_credit_fiscal_document_id'], ['rtc.import_credit_fiscal_document_id']],
    ['RTC_NEGATIVE_INPUT_CREDIT', ['rtc.input_ibs_amount'], ['rtc.input_ibs_amount']],
    ['RTC_RATE_UNAVAILABLE', [], []],
]);

it('rejects malformed list and rows using controlled results', function (mixed $adjustments, string $status, string $reason): void {
    $result = (new CreditInput122)->qualify(['rtc.input_document_id' => 'DOC', 'rtc.credit_adjustments' => $adjustments], 'DOMESTIC');
    expect($result['status'])->toBe($status)->and($result['reason'])->toBe($reason);
})->with([
    [null, 'ACTION_REQUIRED', 'RTC_CREDIT_EVIDENCE_MISSING'],
    [true, 'INVALID', 'RTC_CREDIT122_ADJUSTMENTS_NOT_LIST'],
    [['x' => []], 'INVALID', 'RTC_CREDIT122_ADJUSTMENTS_NOT_LIST'],
    [[null], 'ACTION_REQUIRED', 'RTC_CREDIT_ADJUSTMENT_UNPROVEN'],
    [[[]], 'ACTION_REQUIRED', 'RTC_CREDIT_ADJUSTMENT_UNPROVEN'],
    [[['tax' => 'ISS', 'kind' => 'REVERSAL', 'amount' => '1', 'evidence_id' => 'E']], 'ACTION_REQUIRED', 'RTC_CREDIT_TAX_UNKNOWN'],
    [[['tax' => 'IBS', 'kind' => 'INVENTED', 'amount' => '1', 'evidence_id' => 'E']], 'ACTION_REQUIRED', 'RTC_CREDIT_ADJUSTMENT_UNPROVEN'],
]);
