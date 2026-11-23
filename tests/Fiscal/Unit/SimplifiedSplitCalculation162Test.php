<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SimplifiedSplitCalculation162;

require_once dirname(__DIR__, 3).'/scripts/agent_a/split162_fixture.php';

it('calculates both synthetic percentages independently without using the nominal tax debit', function (): void {
    $service = new SimplifiedSplitCalculation162;
    $report = $service->assess(split162Calculation());
    expect($report['status'])->toBe('LOCAL_SIMPLIFIED_SPLIT_CALCULATED')
        ->and($report['taxes']['IBS']['segregated'])->toBe('8.33')
        ->and($report['taxes']['CBS']['segregated'])->toBe('24.99')
        ->and($report['nominal_debit_used_as_cap'])->toBeFalse()
        ->and($report['appropriable_credit_from_segregation'])->toBeFalse()
        ->and($report['extinction_proven'])->toBeFalse()
        ->and($service->replay($report))->toBeTrue();
    $report['taxes']['CBS']['segregated'] = '25.00';
    expect($service->replay($report))->toBeFalse();
});

it('uses an explicitly supplied rounding qualification instead of assuming the phase-one rule', function (): void {
    $input = split162Calculation();
    $input['synthetic_parameter']['rounding'] = 'HALF_UP_TO_CENT';
    $report = (new SimplifiedSplitCalculation162)->assess(seal158($input));
    expect($report['status'])->toBe('LOCAL_SIMPLIFIED_SPLIT_CALCULATED')
        ->and($report['taxes']['CBS']['segregated'])->toBe('25.00');
});

it('admits an explicit option for a regular acquirer but requires option evidence', function (): void {
    $input = split162Calculation();
    $input['option'] = 'EXPLICIT';
    $input['nominal_values_identified'] = true;
    $input['option_evidence_id'] = 'SYNTHETIC.A162.OPTION.PROOF';
    $report = (new SimplifiedSplitCalculation162)->assess(seal158($input));
    expect($report['status'])->toBe('LOCAL_SIMPLIFIED_SPLIT_CALCULATED')
        ->and($report['appropriable_credit_from_segregation'])->toBeFalse();
});

it('requires a qualified joint act and a nonregular acquirer for a mandatory option', function (): void {
    $input = split162Calculation();
    $input['option'] = 'MANDATORY_JOINT_ACT';
    $input['option_evidence_id'] = 'SYNTHETIC.A162.OPTION.PROOF';
    $input['acquirer_regime'] = 'NON_REGULAR';
    $input['mandatory_act_id'] = 'SYNTHETIC.A162.JOINT.ACT';
    $input['synthetic_mandatory_act'] = array_replace(split162Parameter(), ['id' => $input['mandatory_act_id'], 'authority' => 'CGIBS_RFB_JOINT_ACT']);
    unset($input['synthetic_mandatory_act']['authorities'], $input['synthetic_mandatory_act']['percentages'], $input['synthetic_mandatory_act']['rounding']);
    expect((new SimplifiedSplitCalculation162)->assess(seal158($input))['status'])->toBe('LOCAL_SIMPLIFIED_SPLIT_CALCULATED');
    $input['acquirer_regime'] = 'REGULAR';
    expect((new SimplifiedSplitCalculation162)->assess(seal158($input))['reason'])->toBe('A162_MANDATORY_REGIME_CONFLICT');
});

it('accepts documentary parameters only from the constructor catalog and never from a user synthetic field', function (): void {
    $input = split162Calculation();
    $parameter = $input['synthetic_parameter'];
    $parameter['source_kind'] = 'OFFICIAL_PUBLISHED';
    $input['synthetic_parameter'] = null;
    $input['source_kind'] = 'DOCUMENTARY_INPUT';
    $service = new SimplifiedSplitCalculation162([$parameter]);
    expect($service->assess(seal158($input))['status'])->toBe('LOCAL_SIMPLIFIED_SPLIT_CALCULATED')
        ->and((new SimplifiedSplitCalculation162)->assess(seal158($input))['reason'])->toBe('A162_QUALIFIED_PARAMETER_REQUIRED');
    $input['synthetic_parameter'] = $parameter;
    expect($service->assess(seal158($input))['reason'])->toBe('A162_SYNTHETIC_PARAMETER_SCOPE_CONFLICT');
});

it('refuses missing, temporal, scope or monetary contradictions after resealing', function (string $case): void {
    $input = split162Calculation();
    match ($case) {
        'wrong contract' => $input['contract'] = 'OTHER',
        'currency' => $input['currency'] = 'USD',
        'standard' => $input['procedure'] = 'STANDARD',
        'source' => $input['source_kind'] = 'OFFICIAL',
        'missing parameter' => $input['synthetic_parameter'] = null,
        'synthetic as real' => $input['source_kind'] = 'DOCUMENTARY_INPUT',
        'wrong id' => $input['synthetic_parameter']['id'] = 'OTHER',
        'one tax' => $input['synthetic_parameter']['percentages'] = ['IBS' => '2.5'],
        'one authority' => $input['synthetic_parameter']['authorities'] = ['IBS' => 'CGIBS'],
        'numeric percentage' => $input['synthetic_parameter']['percentages']['CBS'] = 7.5,
        'negative' => $input['synthetic_parameter']['percentages']['CBS'] = '-1',
        'over 100' => $input['synthetic_parameter']['percentages']['CBS'] = '101',
        'sum over 100' => $input['synthetic_parameter']['percentages'] = ['IBS' => '60', 'CBS' => '60'],
        'unqualified rounding' => $input['synthetic_parameter']['rounding'] = null,
        'unpublished' => $input['synthetic_parameter']['published_at'] = '2026-12-01T00:00:00Z',
        'future valid' => $input['synthetic_parameter']['valid_from'] = '2026-11-01',
        'expired' => $input['synthetic_parameter']['valid_until'] = '2026-10-01',
        'revoked' => $input['synthetic_parameter']['revoked_at'] = $input['known_at'],
        'superseded' => $input['synthetic_parameter']['superseded_at'] = $input['known_at'],
        'inactive' => $input['synthetic_parameter']['status'] = 'INACTIVE',
        'sector' => $input['synthetic_parameter']['sector'] = 'OTHER',
        'supplier' => $input['synthetic_parameter']['supplier_id'] = 'OTHER',
        'unknown regime' => $input['acquirer_regime'] = 'UNKNOWN',
        'implicit with tax values' => $input['nominal_values_identified'] = true,
        'no explicit proof' => $input['option'] = 'EXPLICIT',
        'future settlement' => $input['settled_at'] = '2026-12-01T12:00:00Z',
        'operation after payment' => $input['operation_on'] = '2026-10-03',
        'excess allocation' => $input['operation_basis'] = '1000.01',
        'zero basis' => $input['operation_basis'] = '0.00',
        'missing allocation proof' => $input['allocation_evidence_id'] = '',
        'unrelated mandate' => $input['mandatory_act_id'] = 'OTHER',
    };
    expect((new SimplifiedSplitCalculation162)->assess(seal158($input))['status'])->toBe('ACTION_REQUIRED');
})->with(['wrong contract', 'currency', 'standard', 'source', 'missing parameter', 'synthetic as real', 'wrong id',
    'one tax', 'one authority', 'numeric percentage', 'negative', 'over 100', 'sum over 100', 'unqualified rounding',
    'unpublished', 'future valid', 'expired', 'revoked', 'superseded', 'inactive', 'sector', 'supplier', 'unknown regime',
    'implicit with tax values', 'no explicit proof', 'future settlement', 'operation after payment', 'excess allocation',
    'zero basis', 'missing allocation proof', 'unrelated mandate']);

it('does not round two tributes beyond the value of the supplied basis', function (): void {
    $input = split162Calculation();
    $input['operation_basis'] = '0.01';
    $input['synthetic_parameter']['rounding'] = 'HALF_UP_TO_CENT';
    $input['synthetic_parameter']['percentages'] = ['IBS' => '50', 'CBS' => '50'];
    expect((new SimplifiedSplitCalculation162)->assess(seal158($input))['reason'])->toBe('A162_SEGREGATION_EXCEEDS_BASIS');
});

it('selects the qualified financial date across years rather than reusing the operation year', function (): void {
    $input = split162Calculation();
    $input['operation_on'] = '2026-12-31';
    $input['originated_at'] = '2026-12-31T12:00:00Z';
    $input['settled_at'] = '2027-01-01T04:00:00Z';
    $input['known_at'] = '2027-01-02T12:00:00Z';
    $service = new SimplifiedSplitCalculation162;
    expect($service->assess(seal158($input))['reason'])->toBe('A162_PARAMETER_NOT_APPLICABLE');
    $input['synthetic_parameter']['selection_basis'] = 'OPERATION_DATE';
    $report = $service->assess(seal158($input));
    expect($report['status'])->toBe('LOCAL_SIMPLIFIED_SPLIT_CALCULATED')
        ->and($report['parameter_selection_date'])->toBe('2026-12-31');
});

it('requires explicit temporal qualification and respects the target parameter environment', function (): void {
    $input = split162Calculation();
    $input['synthetic_parameter']['selection_basis'] = 'YEAR_ONLY';
    expect((new SimplifiedSplitCalculation162)->assess(seal158($input))['reason'])->toBe('A162_QUALIFIED_TEMPORAL_BASIS_REQUIRED');
    $input['synthetic_parameter']['selection_basis'] = 'FINANCIAL_SETTLEMENT';
    $input['synthetic_parameter']['environment'] = 'PRODUCTION';
    expect((new SimplifiedSplitCalculation162)->assess(seal158($input))['reason'])->toBe('A162_PARAMETER_ENVIRONMENT_CONFLICT');
});

it('keeps zero percentage distinct from an absent percentage and accepts reordered authority keys', function (): void {
    $input = split162Calculation();
    $input['synthetic_parameter']['percentages']['IBS'] = '0';
    $input['synthetic_parameter']['authorities'] = ['CBS' => 'RFB', 'IBS' => 'CGIBS'];
    $report = (new SimplifiedSplitCalculation162)->assess(seal158($input));
    expect($report['status'])->toBe('LOCAL_SIMPLIFIED_SPLIT_CALCULATED')
        ->and($report['taxes']['IBS']['segregated'])->toBe('0.00')
        ->and($report['taxes']['CBS']['segregated'])->toBe('24.99');
});
