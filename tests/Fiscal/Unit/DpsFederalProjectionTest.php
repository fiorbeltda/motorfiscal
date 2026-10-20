<?php

declare(strict_types=1);

use App\Modules\Fiscal\NFSe\DpsFederalProjection;

it('maps each PIS Cofins CSLL retention combination independently of own tax assessment', function (int $mask, string $expected): void {
    $taxes = [];
    foreach (['PIS' => 1, 'COFINS' => 2, 'CSLL' => 4] as $tax => $bit) {
        $taxes[] = ['tax' => $tax, 'status' => ($mask & $bit) !== 0 ? 'WITHHOLD' : 'NOT_APPLICABLE', 'amount' => '10.00'];
    }
    $report = (new DpsFederalProjection)->project([
        'status' => 'VALID', 'pcc' => ['decisions' => $taxes],
        'irrf' => ['status' => 'WITHHOLD', 'amount' => '15.00'],
        'inss' => ['status' => 'NOT_APPLICABLE', 'amount' => '0.00'],
    ], ['pis_cofins_cst' => '01', 'pis_cofins_evidence_id' => 'own-tax-ledger']);
    expect($report['status'])->toBe('PROJECTED')
        ->and($report['fields']['piscofins.tpRetPisCofins'])->toBe($expected)
        ->and($report['fields']['vRetIRRF'])->toBe('15.00')
        ->and(array_key_exists('piscofins.vPis', $report['fields']))->toBeFalse()
        ->and(array_key_exists('vRetCSLL', $report['fields']))->toBe(($mask & 4) !== 0);
})->with([[0, '0'], [1, '5'], [2, '6'], [3, '4'], [4, '8'], [5, '9'], [6, '7'], [7, '3']]);

it('does not invent an own PIS Cofins classification or treat future withholding as final', function (): void {
    $federal = ['status' => 'VALID', 'pcc' => ['decisions' => []],
        'irrf' => ['status' => 'PENDING_TRIGGER'], 'inss' => ['status' => 'NOT_APPLICABLE']];
    $projector = new DpsFederalProjection;
    expect($projector->project($federal, [])['reason'])->toBe('DPS_PIS_COFINS_OWN_CLASSIFICATION_REQUIRED')
        ->and($projector->project($federal, ['pis_cofins_cst' => '01', 'pis_cofins_evidence_id' => 'ledger'])['reason'])->toBe('DPS_FEDERAL_WITHHOLDING_NOT_FINAL');
});

it('consolidates additional federal components and checks the total without dropping amounts', function (): void {
    $federal = ['status' => 'VALID', 'pcc' => ['decisions' => []],
        'irrf' => ['status' => 'WITHHOLD', 'amount' => '15.00'], 'inss' => ['status' => 'NOT_APPLICABLE'],
        'additional_decisions' => [['tax' => 'IRRF', 'status' => 'WITHHOLD', 'amount' => '4.00']],
        'total_withholding' => '19.00'];
    $identity = ['pis_cofins_cst' => '01', 'pis_cofins_evidence_id' => 'ledger'];
    $projector = new DpsFederalProjection;
    expect($projector->project($federal, $identity)['fields']['vRetIRRF'])->toBe('19.00');
    $federal['total_withholding'] = '15.00';
    expect($projector->project($federal, $identity)['reason'])->toBe('DPS_FEDERAL_TOTAL_RECONCILIATION_FAILED');
});
