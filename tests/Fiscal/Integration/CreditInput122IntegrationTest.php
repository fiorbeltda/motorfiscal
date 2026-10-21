<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function credit122Evaluation(array $overrides, string $runner, bool $import = false, bool $historical = false, string $inputCase = 'standard'): array
{
    $factory = new FiscalEngineFactory;
    $manifest = $historical ? 'bundles/consumption-phase4b-2026.10.20-119-draft1.json' : 'bundles/consumption-phase4b-2026.10.21-122-draft1.json';
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), $manifest);
    $path = $import ? 'scenarios/consumption-phase4b-2026.09.28-15/import-credit-proven-2026.json'
        : 'scenarios/consumption-phase4b-2026.10.02-1/input-credit-'.$inputCase.'-2026.json';
    $source = json_decode((string) file_get_contents(resource_path('fiscal/'.$path)), true, 512, JSON_THROW_ON_ERROR);
    $source['scenario_id'] = 'CREDIT122-SYNTHETIC';
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
    // New synthetic knowledge,not a rewritten historical source or monetary fact.
    $source['knowledge_at'] = '2026-10-21T11:10:00.000000+00:00';
    if ($import) {
        $document = $source['facts']['rtc.input_document_id'];
        $source['facts'] = [...$source['facts'], 'operation.service_origin' => 'FOREIGN', 'operation.issuer_role' => 'CUSTOMER',
            'customer.municipality_ibge' => '3550308', 'rtc.presumed_credit_requested' => false,
            'rtc.input_tax_treatments' => array_map(static fn (string $tax): array => ['tax' => $tax, 'treatment' => 'STANDARD',
                'document_id' => $document, 'evidence_id' => 'SYNTHETIC-CREDIT122-'.$tax], ['IBS', 'CBS'])];
    }
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($source['facts'][$key]);
        } else {
            $source['facts'][$key] = $value;
        }
    }
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);

    return $runner === 'rtc' ? (new RtcScenarioRunService($factory))->evaluate($knowledge, $scenario)->toArray()
        : $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input)->toArray();
}

it('refuses previously coercible or crashing credit amounts in both native runners', function (mixed $amount, string $runner): void {
    $result = credit122Evaluation(['rtc.credit_adjustments' => [['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => $amount, 'evidence_id' => 'E']]], $runner);
    expect($result['status'])->toBe('INVALID')->and($result['reason_codes'])->toContain('RTC_CREDIT122_AMOUNT_NOT_EXACT');
})->with([[true], [false], [1.1], [1.0], [''], [' '], ['1e-2'], [[]]])->with(['rtc', 'integrated']);

it('preserves exact arithmetic for meaningful credit evidence and identities', function (array $rows, string $ibs, string $cbs, string $runner): void {
    $result = credit122Evaluation(['rtc.credit_adjustments' => $rows], $runner);
    expect($result['status'])->toBe($runner === 'rtc' ? 'VALID' : 'VALID_WITH_WARNINGS');
    $rtc = $runner === 'rtc' ? $result : array_column($result['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    expect($rtc['decision']['ibs']['credit']['amount'])->toBe($ibs)->and($rtc['decision']['cbs']['credit']['amount'])->toBe($cbs);
    $stages = array_column($rtc['trace'], null, 'stage');
    expect($stages['CREDIT_INPUT122']['status'])->toBe('RESOLVED')
        ->and($stages['CREDIT_INPUT122']['credit_granted'])->toBeFalse()
        ->and($stages['CREDIT_INPUT_POLICY122']['status'])->toBe('RESOLVED');
})->with([
    [[], '2.00', '18.00'],
    [[['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => 1, 'evidence_id' => 'E']], '1.00', '18.00'],
    [[['tax' => 'IBS', 'kind' => 'DISALLOWANCE', 'amount' => '0.25', 'evidence_id' => 'E']], '1.75', '18.00'],
    [[['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '0.25', 'evidence_id' => 'E', 'line_reference' => '1'],
        ['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '0.25', 'evidence_id' => 'E', 'line_reference' => '2']], '1.50', '18.00'],
    [[['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '0.25', 'evidence_id' => 'E'],
        ['tax' => 'CBS', 'kind' => 'REVERSAL', 'amount' => '0.25', 'evidence_id' => 'E']], '1.75', '17.75'],
])->with(['rtc', 'integrated']);

it('requires meaningful evidence and disambiguates repeated credit lines', function (string $mutation, string $reason, string $runner): void {
    $row = ['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '0.25', 'evidence_id' => 'E'];
    $rows = [$row];
    if ($mutation === 'duplicate') {
        $rows[] = $row;
    } else {
        $rows[0]['evidence_id'] = $mutation;
    }
    $result = credit122Evaluation(['rtc.credit_adjustments' => $rows], $runner);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toContain($reason);
})->with([['', 'RTC_CREDIT122_EVIDENCE_REQUIRED'], [' ', 'RTC_CREDIT122_EVIDENCE_REQUIRED'], ['duplicate', 'RTC_CREDIT122_LINE_IDENTITY_AMBIGUOUS']])
    ->with(['rtc', 'integrated']);

it('refuses whitespace document identities even when treatment rows agree', function (string $runner): void {
    $treatments = array_map(static fn (string $tax): array => ['tax' => $tax, 'treatment' => 'STANDARD', 'document_id' => ' ', 'evidence_id' => 'E-'.$tax], ['IBS', 'CBS']);
    $result = credit122Evaluation(['rtc.input_document_id' => ' ', 'rtc.input_tax_treatments' => $treatments], $runner);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toContain('RTC_CREDIT122_DOCUMENT_ID_REQUIRED');
})->with(['rtc', 'integrated']);

it('keeps negative excess and unknown tax guards', function (mixed $amount, string $tax, string $reason, string $runner): void {
    $result = credit122Evaluation(['rtc.credit_adjustments' => [['tax' => $tax, 'kind' => 'REVERSAL', 'amount' => $amount, 'evidence_id' => 'E']]], $runner);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toContain($reason);
})->with([['-0.25', 'IBS', 'RTC_NEGATIVE_CREDIT_ADJUSTMENT'], ['3.00', 'IBS', 'RTC_CREDIT_ADJUSTMENT_EXCEEDS_AVAILABLE'], ['0.25', 'ISS', 'RTC_CREDIT_TAX_UNKNOWN']])
    ->with(['rtc', 'integrated']);

it('does not retroactively change the captured119 coercion witness', function (string $runner): void {
    $result = credit122Evaluation(['rtc.credit_adjustments' => [['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => true, 'evidence_id' => 'E']]], $runner, historical: true);
    expect($result['status'])->toBe($runner === 'rtc' ? 'VALID' : 'VALID_WITH_WARNINGS');
    $rtc = $runner === 'rtc' ? $result : array_column($result['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    expect($rtc['decision']['ibs']['credit']['amount'])->toBe('1.00')
        ->and(array_column($rtc['trace'], 'stage'))->not->toContain('CREDIT_INPUT122');
})->with(['rtc', 'integrated']);

it('guards import credit inputs without removing import document obligations', function (mixed $amount, string $expected, string $runner): void {
    $result = credit122Evaluation(['rtc.credit_adjustments' => [['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => $amount, 'evidence_id' => 'E']]], $runner, import: true);
    expect($result['status'])->toBe($expected);
    if ($expected === 'INVALID') {
        expect($result['reason_codes'])->toContain('RTC_CREDIT122_AMOUNT_NOT_EXACT');
    } else {
        $rtc = $runner === 'rtc' ? $result : array_column($result['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
        $credit = array_column($rtc['trace'], null, 'stage')['IMPORT_CREDIT'];
        expect($credit['ibs']['amount'])->toBe('0.75')->and($credit['cbs']['amount'])->toBe('9.00');
    }
})->with([[true, 'INVALID'], [false, 'INVALID'], [1.1, 'INVALID'], ['0.25', 'ACTION_REQUIRED']])->with(['rtc', 'integrated']);

it('rejects blank import identifiers through both actual runners', function (string $key, string $runner): void {
    $result = credit122Evaluation([$key => ' '], $runner, import: true);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toContain('RTC_CREDIT122_IMPORT_IDENTIFIER_INVALID');
})->with(['rtc.import_document_id', 'rtc.import_credit_fiscal_document_id', 'rtc.import_credit_extinction_evidence_id'])->with(['rtc', 'integrated']);

it('preserves denied and conditional acquisition treatments instead of treating zero as a grant', function (string $inputCase, string $expected, string $runner): void {
    $result = credit122Evaluation([], $runner, inputCase: $inputCase);
    expect($result['status'])->toBe($runner === 'rtc' ? 'VALID' : 'VALID_WITH_WARNINGS');
    $rtc = $runner === 'rtc' ? $result : array_column($result['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    foreach (['ibs', 'cbs'] as $tax) {
        expect($rtc['decision'][$tax]['credit']['status'])->toBe($expected)
            ->and($rtc['decision'][$tax]['credit']['amount'])->toBe('0.00');
    }
})->with([['zero-rate', 'DENIED'], ['immunity', 'DENIED'], ['exempt', 'DENIED'], ['deferral', 'DENIED'], ['suspension', 'DENIED'], ['suspension-pending', 'CONDITIONAL']])
    ->with(['rtc', 'integrated']);

it('does not consume an ordinary adjustment as a granted credit for an output or denied claimant', function (string $context, string $regime, string $expected, string $runner): void {
    $result = credit122Evaluation(['rtc.credit_context' => $context, 'rtc.acquirer_regime' => $regime,
        'rtc.credit_adjustments' => [['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => false, 'evidence_id' => 'E']]], $runner);
    expect($result['status'])->toBe($runner === 'rtc' ? 'VALID' : 'VALID_WITH_WARNINGS');
    $rtc = $runner === 'rtc' ? $result : array_column($result['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    foreach (['ibs', 'cbs'] as $tax) {
        expect($rtc['decision'][$tax]['credit']['status'])->toBe($expected);
    }
    expect(array_column($rtc['trace'], 'stage'))->not->toContain('CREDIT_INPUT122');
})->with([['OUTPUT', 'REGULAR', 'NOT_ASSESSED_ON_OUTPUT'], ['PERSONAL_USE', 'REGULAR', 'DENIED'], ['INPUT', 'SIMPLES_NACIONAL', 'DENIED']])
    ->with(['rtc', 'integrated']);

it('preserves asymmetric credit semantics and extinction conditions', function (bool $extinguished, string $runner): void {
    $result = credit122Evaluation(['rtc.prior_tax_extinguished' => $extinguished], $runner, inputCase: 'asymmetric');
    expect($result['status'])->toBe($runner === 'rtc' ? 'VALID' : 'VALID_WITH_WARNINGS');
    $rtc = $runner === 'rtc' ? $result : array_column($result['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    expect($rtc['decision']['ibs']['credit']['status'])->toBe($extinguished ? 'ALLOWED' : 'CONDITIONAL')
        ->and($rtc['decision']['cbs']['credit']['status'])->toBe('DENIED')
        ->and($rtc['decision']['cbs']['credit']['amount'])->toBe('0.00');
})->with([true, false])->with(['rtc', 'integrated']);

it('asks to correct the adjustment collection and retains the exact structural row', function (array $rows, string $reason, int $index, string $runner): void {
    $result = credit122Evaluation(['rtc.credit_adjustments' => $rows], $runner);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toContain($reason)
        ->and($result['missing_facts'])->toBe(['rtc.credit_adjustments']);
    $questions = array_column($result['questions'], null, 'fact_key');
    expect($questions)->toHaveKey('rtc.credit_adjustments')
        ->and($questions['rtc.credit_adjustments']['prompt'])->toContain('rtc.credit_adjustments')
        ->and($questions['rtc.credit_adjustments']['answer_type'])->toBe('ARRAY');
    $rtc = $runner === 'rtc' ? $result : array_column($result['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    $guard = array_column($rtc['trace'], null, 'stage')['CREDIT_INPUT122'];
    expect($guard['row_index'])->toBe($index)->and($guard['missing_facts'])->toBe(['rtc.credit_adjustments'])
        ->and($guard['credit_granted'])->toBeFalse()->and($guard['document_authenticated'])->toBeFalse()
        ->and($guard['extinction_proven'])->toBeFalse();
})->with([
    [[['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '0.25', 'evidence_id' => '']], 'RTC_CREDIT122_EVIDENCE_REQUIRED', 0],
    [[['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '0.25', 'evidence_id' => ' ']], 'RTC_CREDIT122_EVIDENCE_REQUIRED', 0],
    [[['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '0.25', 'evidence_id' => 'E'],
        ['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '0.25', 'evidence_id' => 'E']], 'RTC_CREDIT122_LINE_IDENTITY_AMBIGUOUS', 0],
    [[['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '0.25', 'evidence_id' => 'E', 'line_reference' => 'L1'],
        ['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '0.25', 'evidence_id' => 'E', 'line_reference' => ' ']], 'RTC_CREDIT122_LINE_REFERENCE_INVALID', 1],
    [[['tax' => 'ISS', 'kind' => 'REVERSAL', 'amount' => '0.25', 'evidence_id' => 'E']], 'RTC_CREDIT_TAX_UNKNOWN', 0],
    [[[]], 'RTC_CREDIT_ADJUSTMENT_UNPROVEN', 0],
])->with(['rtc', 'integrated']);

it('asks to correct negative and excessive adjustments in domestic and import credit without changing the refusal', function (string $amount, string $reason, string $runner, bool $import): void {
    $result = credit122Evaluation(['rtc.credit_adjustments' => [['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => $amount, 'evidence_id' => 'E']]], $runner, import: $import);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toContain($reason)
        ->and($result['missing_facts'])->toBe(['rtc.credit_adjustments'])
        ->and(array_column($result['questions'], 'fact_key'))->toContain('rtc.credit_adjustments');
    $rtc = $runner === 'rtc' ? $result : array_column($result['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    $guard = array_column($rtc['trace'], null, 'stage')['CREDIT_CORRECTION122'];
    expect($guard['reason'])->toBe($reason)->and($guard['credit_granted'])->toBeFalse();
})->with([['-0.25', 'RTC_NEGATIVE_CREDIT_ADJUSTMENT'], ['3.00', 'RTC_CREDIT_ADJUSTMENT_EXCEEDS_AVAILABLE']])
    ->with(['rtc', 'integrated'])->with([false, true]);

it('asks for input credit amounts on a negative input instead of granting a credit', function (string $runner): void {
    $result = credit122Evaluation(['rtc.input_ibs_amount' => '-2.00', 'rtc.input_cbs_amount' => '-18.00'], $runner);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toContain('RTC_NEGATIVE_INPUT_CREDIT')
        ->and($result['missing_facts'])->toBe(['rtc.input_ibs_amount', 'rtc.input_cbs_amount'])
        ->and(array_column($result['questions'], 'fact_key'))->toContain('rtc.input_ibs_amount', 'rtc.input_cbs_amount');
})->with(['rtc', 'integrated']);

it('preserves the119 missing-question witnesses and arithmetic refusal traces', function (string $amount, string $reason, string $runner): void {
    $result = credit122Evaluation(['rtc.credit_adjustments' => [['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => $amount, 'evidence_id' => 'E']]], $runner, historical: true);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toContain($reason)
        ->and($result['missing_facts'])->toBe([])->and($result['questions'])->toBe([]);
    $rtc = $runner === 'rtc' ? $result : array_column($result['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    expect(array_column($rtc['trace'], 'stage'))->not->toContain('CREDIT_CORRECTION122');
})->with([['-0.25', 'RTC_NEGATIVE_CREDIT_ADJUSTMENT'], ['3.00', 'RTC_CREDIT_ADJUSTMENT_EXCEEDS_AVAILABLE']])->with(['rtc', 'integrated']);
