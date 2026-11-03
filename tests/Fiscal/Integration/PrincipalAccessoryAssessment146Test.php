<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CombinedSupplyAssessment145;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

require_once dirname(__DIR__, 3).'/scripts/agent_a/combined146_fixture.php';

function combined146Assessor(): CombinedSupplyAssessment145
{
    return new CombinedSupplyAssessment145(new FiscalEngineFactory, resource_path('fiscal'));
}

it('assesses the full consideration once under the principal without fabricating accessory values', function (): void {
    $input = combined146Fixture();
    $report = combined146Assessor()->assess($input);
    expect($report['contract'])->toBe(CombinedSupplyAssessment145::PRINCIPAL_CONTRACT)
        ->and($report['status'])->toBe('LOCAL_COMBINED_SUPPLIES_ASSESSED')
        ->and($report['fiscal_supply_count'])->toBe(1)->and($report['supplies'])->toHaveCount(1)
        ->and($report['accessories'])->toHaveCount(2)
        ->and($report['totals']['taxable_base_brl'])->toBe('1000.00')
        ->and($report['totals']['ibs_state_brl'])->toBe('1.00')
        ->and($report['totals']['cbs_brl'])->toBe('9.00')
        ->and($report['accessories'][0])->not->toHaveKey('value_brl')
        ->and($report['document_obligation_complete'])->toBeFalse();
    expect(combined146Assessor()->replay($report))->toBeTrue();
});

it('does not require allocation amounts or change the report when accessories are reordered', function (): void {
    $input = combined146Fixture();
    $first = combined146Assessor()->assess($input);
    $input['accessories'] = array_reverse($input['accessories']);
    $second = combined146Assessor()->assess($input);
    expect($first['report_hash'])->toBe($second['report_hash'])
        ->and($second['operation_input'])->not->toHaveKey('allocation_evidence_id');
});

it('requires a source-bound relationship instead of treating a declared separate supply as accessory', function (string $mutation, string $reason): void {
    $input = combined146Fixture();
    switch ($mutation) {
        case 'independent': $input['accessories'][0]['independent_supply'] = true;
            break;
        case 'unknown-independence': unset($input['accessories'][0]['independent_supply']);
            break;
        case 'own-treatment': $input['accessories'][0]['tax_treatment'] = 'REDUCED_60';
            break;
        case 'own-scenario': $input['accessories'][0]['scenario'] = $input['principal']['scenario'];
            break;
        case 'unproved-role': $input['accessories'][0]['role'] = 'CONVENIENT_BUNDLE';
            break;
        case 'wrong-principal': $input['accessories'][0]['principal_id'] = 'OTHER';
            break;
        case 'blank-proof': $input['accessories'][0]['relationship_evidence_id'] = ' ';
            break;
        case 'duplicate-id': $input['accessories'][1]['id'] = $input['accessories'][0]['id'];
            break;
        case 'principal-id': $input['accessories'][0]['id'] = $input['principal']['id'];
            break;
        case 'two-contexts': $input['supplies'] = combined145Fixture()['supplies'];
            break;
        case 'principal-price': $input['principal']['value_brl'] = '800.00';
            break;
        case 'no-accessories': $input['accessories'] = [];
            break;
        case 'no-principal-proof': unset($input['principal']['specification_evidence_id']);
            break;
    }
    $report = combined146Assessor()->assess($input);
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report['reason'])->toBe($reason)
        ->and($report['totals'])->toBeNull()->and($report['production_ready'])->toBeFalse();
})->with([
    ['independent', 'COMBINED146_ACCESSORY_INDEPENDENCE_OR_TREATMENT_CONFLICT'],
    ['unknown-independence', 'COMBINED146_ACCESSORY_INDEPENDENCE_OR_TREATMENT_CONFLICT'],
    ['own-treatment', 'COMBINED146_ACCESSORY_INDEPENDENCE_OR_TREATMENT_CONFLICT'],
    ['own-scenario', 'COMBINED146_ACCESSORY_INDEPENDENCE_OR_TREATMENT_CONFLICT'],
    ['unproved-role', 'COMBINED146_ACCESSORY_INDEPENDENCE_OR_TREATMENT_CONFLICT'],
    ['wrong-principal', 'COMBINED146_ACCESSORY_IDENTITY_OR_RELATION_UNPROVEN'],
    ['blank-proof', 'COMBINED146_ACCESSORY_IDENTITY_OR_RELATION_UNPROVEN'],
    ['duplicate-id', 'COMBINED146_ACCESSORY_IDENTITY_OR_RELATION_UNPROVEN'],
    ['principal-id', 'COMBINED146_ACCESSORY_IDENTITY_OR_RELATION_UNPROVEN'],
    ['two-contexts', 'COMBINED146_INDEPENDENT_AND_ACCESSORY_CONTEXT_CONFLICT'],
    ['principal-price', 'COMBINED146_SINGLE_SUPPLY_TOTAL_CONFLICT'],
    ['no-accessories', 'COMBINED146_SPECIFIED_PRINCIPAL_AND_ACCESSORIES_REQUIRED'],
    ['no-principal-proof', 'COMBINED146_SPECIFIED_PRINCIPAL_AND_ACCESSORIES_REQUIRED'],
]);

it('preserves a principal fiscal dependency instead of emitting totals for its accessories', function (): void {
    $input = combined146Fixture();
    $input['principal']['scenario']['facts']['rtc.operation_date'] = '2033-01-01';
    $report = combined146Assessor()->assess($input);
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report['totals'])->toBeNull()
        ->and($report['reason'])->toBe('COMBINED146_PRINCIPAL_FISCAL_DEPENDENCY')
        ->and($report['supplies'][0]['status'])->toBe('ACTION_REQUIRED');
});
