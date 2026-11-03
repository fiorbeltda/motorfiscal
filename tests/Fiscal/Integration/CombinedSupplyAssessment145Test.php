<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CombinedSupplyAssessment145;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

require_once dirname(__DIR__, 3).'/scripts/agent_a/combined145_fixture.php';

function combined145Assessor(): CombinedSupplyAssessment145
{
    return new CombinedSupplyAssessment145(new FiscalEngineFactory, resource_path('fiscal'));
}

it('assesses different supplies with distinct legal treatments and locations without blending rates', function (): void {
    $report = combined145Assessor()->assess(combined145Fixture());
    expect($report['status'])->toBe('LOCAL_COMBINED_SUPPLIES_ASSESSED')
        ->and($report['specified_gross_brl'])->toBe('2000.00')
        ->and($report['totals'])->toBe(['taxable_base_brl' => '2000.00', 'ibs_state_brl' => '1.40',
            'ibs_municipal_brl' => '0.00', 'cbs_brl' => '12.60', 'ibs_cash_due_brl' => '0.00', 'cbs_cash_due_brl' => '0.00'])
        ->and($report['tax_groups'])->toHaveCount(2)
        ->and($report['document_obligation_complete'])->toBeFalse()
        ->and($report['extinction_proven'])->toBeFalse()
        ->and($report['production_ready'])->toBeFalse();
    $byId = array_column($report['supplies'], null, 'id');
    expect($byId['software']['rtc_snapshot']['result']['decision']['destination']['basis'])->toBe('ACQUIRER_DOMICILE')
        ->and($byId['education']['rtc_snapshot']['result']['decision']['destination']['basis'])->toBe('PERSON_PRESENCE')
        ->and($byId['education']['rtc_snapshot']['result']['decision']['cbs']['federal_rate_percent'])->toBe('0.36')
        ->and($byId['education']['integrated_snapshot']['result']['decision']['iss']['calculation']['tax_amount'])->toBe('50.00');
});

it('replays full captured reports and rejects tampered amounts or rehashed snapshots', function (): void {
    $assessor = combined145Assessor();
    $report = $assessor->assess(combined145Fixture());
    expect($assessor->replay($report))->toBeTrue();
    $report['supplies'][0]['rtc_snapshot']['result']['decision']['cbs']['nominal_amount'] = '999.00';
    expect($assessor->replay($report))->toBeFalse();
    unset($report['report_hash']);
    $report['report_hash'] = CanonicalJson::hash($report);
    expect($assessor->replay($report))->toBeFalse();
});

it('canonicalizes supply order while preserving numeric textual identifiers', function (): void {
    $input = combined145Fixture();
    foreach (['01', '1'] as $index => $id) {
        $input['supplies'][$index]['id'] = $id;
        $input['supplies'][$index]['scenario']['scenario_id'] = $id;
    }
    $first = combined145Assessor()->assess($input);
    $input['supplies'] = array_reverse($input['supplies']);
    $second = combined145Assessor()->assess($input);
    expect($first['report_hash'])->toBe($second['report_hash'])
        ->and(array_column($first['supplies'], 'id'))->toBe(['01', '1']);
});

it('sums already rounded supply amounts instead of recalculating tax on a merged base', function (): void {
    $input = combined145Fixture();
    $input['declared_gross_brl'] = '3.34';
    $second = $input['supplies'][0];
    $second['id'] = 'software-second';
    $second['scenario']['scenario_id'] = 'software-second';
    $second['allocation_evidence_id'] = 'FIXTURE.SECOND.SUPPLY.LINE';
    $input['supplies'][1] = $second;
    foreach ($input['supplies'] as &$supply) {
        $supply['value_brl'] = '1.67';
        $supply['scenario']['facts']['rtc.gross_amount'] = '1.67';
        $supply['scenario']['facts']['operation.amount'] = '1.67';
    }
    unset($supply);
    $report = combined145Assessor()->assess($input);
    expect($report['status'])->toBe('LOCAL_COMBINED_SUPPLIES_ASSESSED')
        ->and($report['totals']['taxable_base_brl'])->toBe('3.34')
        ->and($report['totals']['cbs_brl'])->toBe('0.04');
});

it('respects the exact source-availability boundary and equal civil-time offsets', function (): void {
    $input = combined145Fixture();
    $input['knowledge_at'] = '2026-11-03T08:18:57-03:00';
    foreach ($input['supplies'] as &$supply) {
        $supply['scenario']['knowledge_at'] = CombinedSupplyAssessment145::SOURCE_AVAILABLE_AT;
    }
    unset($supply);
    expect(combined145Assessor()->assess($input)['status'])->toBe('LOCAL_COMBINED_SUPPLIES_ASSESSED');
});

it('refuses ambiguous allocation and unsupported combined contexts before calculating a total', function (string $mutation, string $reason): void {
    $input = combined145Fixture();
    switch ($mutation) {
        case 'sum': $input['declared_gross_brl'] = '1999.99';
            break;
        case 'float': $input['supplies'][0]['value_brl'] = 1000.0;
            break;
        case 'zero': $input['supplies'][0]['value_brl'] = '0.00';
            break;
        case 'duplicate': $input['supplies'][1]['id'] = $input['supplies'][0]['id'];
            break;
        case 'no-value': unset($input['supplies'][0]['value_brl']);
            break;
        case 'one': array_pop($input['supplies']);
            break;
        case 'blank-evidence': $input['supplies'][0]['allocation_evidence_id'] = ' ';
            break;
        case 'accessory': $input['relationship'] = 'PRINCIPAL_ACCESSORY';
            break;
        case 'currency': $input['currency'] = 'USD';
            break;
        case 'future-source': $input['knowledge_at'] = '2026-11-03T11:18:56.999999+00:00';
            break;
        case 'invalid-date': $input['knowledge_at'] = '2026-02-30T12:00:00Z';
            break;
        case 'no-header-proof': unset($input['relationship_evidence_id']);
            break;
    }
    $report = combined145Assessor()->assess($input);
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report['reason'])->toBe($reason)
        ->and($report['totals'])->toBeNull()->and($report['document_obligation_complete'])->toBeFalse();
})->with([
    ['sum', 'COMBINED145_SPECIFIED_VALUES_DO_NOT_RECONCILE'],
    ['float', 'COMBINED145_SUPPLY_ID_VALUE_OR_EVIDENCE_INVALID'],
    ['zero', 'COMBINED145_SUPPLY_ID_VALUE_OR_EVIDENCE_INVALID'],
    ['duplicate', 'COMBINED145_SUPPLY_ID_VALUE_OR_EVIDENCE_INVALID'],
    ['no-value', 'COMBINED145_SUPPLY_ID_VALUE_OR_EVIDENCE_INVALID'],
    ['one', 'COMBINED145_DISTINCT_SPECIFIED_SUPPLIES_REQUIRED'],
    ['blank-evidence', 'COMBINED145_SUPPLY_ID_VALUE_OR_EVIDENCE_INVALID'],
    ['accessory', 'COMBINED145_PRINCIPAL_ACCESSORY_QUALIFICATION_REQUIRED'],
    ['currency', 'COMBINED145_CURRENCY_CONVERSION_REQUIRED'],
    ['future-source', 'COMBINED145_POLICY_NOT_YET_AVAILABLE'],
    ['invalid-date', 'COMBINED145_KNOWLEDGE_INSTANT_INVALID'],
    ['no-header-proof', 'COMBINED145_HEADER_EVIDENCE_REQUIRED:relationship_evidence_id'],
]);

it('keeps every successful line but withholds the aggregate if any supplied scenario is inconsistent', function (string $mutation): void {
    $input = combined145Fixture();
    $scenario = &$input['supplies'][0]['scenario'];
    switch ($mutation) {
        case 'supplier': $scenario['facts']['rtc.supplier_id'] = 'OTHER';
            break;
        case 'acquirer': $scenario['facts']['rtc.acquirer_id'] = 'OTHER';
            break;
        case 'amount': $scenario['facts']['rtc.gross_amount'] = '999.00';
            break;
        case 'knowledge': $scenario['knowledge_at'] = '2026-11-03T13:00:00Z';
            break;
        case 'input-credit': $scenario['facts']['rtc.credit_context'] = 'INPUT';
            break;
        case 'path': $input['supplies'][0]['bundle'] = '../outside.json';
            break;
        case 'future-rate': $scenario['facts']['rtc.operation_date'] = '2033-01-01';
            break;
    }
    $report = combined145Assessor()->assess($input);
    $byId = array_column($report['supplies'], null, 'id');
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report['totals'])->toBeNull()
        ->and($byId['education']['status'])->toBe('ASSESSED')
        ->and($byId['software']['status'])->toBe('ACTION_REQUIRED');
})->with(['supplier', 'acquirer', 'amount', 'knowledge', 'input-credit', 'path', 'future-rate']);
