<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CombinedRegimeSuppliesB41;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

require_once dirname(__DIR__, 3).'/scripts/agent_b/hospitality_b35_fixture.php';
require_once dirname(__DIR__, 3).'/scripts/agent_b/travel_agency_b36_fixture.php';

// B41 (4B.4.6): pacote turístico com fornecimentos independentes em regimes diferentes (hotelaria B35 + agência B36).

const B41_BUNDLE = 'bundles/consumption-phase4b-2026.11.06-b38-draft1.json';

/** @return array<string, mixed> */
function b41Supply(string $id, array $scenario): array
{
    $manifest = json_decode((string) file_get_contents(resource_path('fiscal/'.B41_BUNDLE)), true, 512, JSON_THROW_ON_ERROR);
    $scenario['scenario_id'] = $id;
    $scenario['rule_bundle_id'] = $manifest['bundle_id'];
    $scenario['catalog_versions'] = $manifest['catalog_versions'];
    $scenario['knowledge_at'] = $manifest['knowledge_available_at'];

    return ['id' => $id, 'value_brl' => $scenario['facts']['rtc.gross_amount'], 'allocation_evidence_id' => 'FIXTURE.PACKAGE.LINE.'.$id,
        'bundle' => B41_BUNDLE, 'scenario' => $scenario];
}

/** @return array<string, mixed> */
function b41Operation(array $changes = []): array
{
    return array_replace(['contract' => CombinedRegimeSuppliesB41::CONTRACT, 'operation_id' => 'FIXTURE.PACKAGE', 'supplier_id' => '11111111000191',
        'acquirer_id' => '22222222000191', 'allocation_evidence_id' => 'FIXTURE.PACKAGE.PRICES', 'independence_evidence_id' => 'FIXTURE.PACKAGE.TERMS',
        'currency' => 'BRL', 'declared_gross_brl' => '2000.00',
        'supplies' => [b41Supply('lodging', hospitalityB35Scenario()), b41Supply('agency', travelB36Scenario())]], $changes);
}

function b41Assessor(): CombinedRegimeSuppliesB41
{
    return new CombinedRegimeSuppliesB41(new FiscalEngineFactory, resource_path('fiscal'));
}

it('decides each supply under its own regime and sums rounded amounts without a blended rate', function (): void {
    $report = b41Assessor()->assess(b41Operation());
    expect($report['status'])->toBe('LOCAL_COMBINED_REGIME_SUPPLIES_ASSESSED')->and($report['documents'])->toBe(2);
    $byId = array_column($report['supplies'], null, 'id');
    expect($byId['lodging']['regime'])->toBe('HOSPITALITY_SPECIFIC')->and($byId['lodging']['cclasstrib'])->toBe('200048')
        ->and($byId['agency']['regime'])->toBe('TRAVEL_AGENCY_SPECIFIC')->and($byId['agency']['cclasstrib'])->toBe('200051')
        ->and($byId['agency']['taxable_base'])->toBe('350.00')
        ->and($report['totals'])->toBe(['taxable_base_brl' => '1350.00', 'cbs_brl' => '7.29', 'ibs_brl' => '0.81']);
});

it('refuses food bundled into the package, unreconciled values and too few supplies', function (): void {
    $op = b41Operation();
    $op['supplies'][] = ['id' => 'breakfast', 'kind' => 'FOOD_AND_BEVERAGE', 'value_brl' => '50.00', 'allocation_evidence_id' => 'X'];
    $op['declared_gross_brl'] = '2050.00';
    expect(b41Assessor()->assess($op)['reason'])->toBe('B41_FOOD_FOLLOWS_RESTAURANT_REGIME_OUTSIDE_NFSE')
        ->and(b41Assessor()->assess(b41Operation(['declared_gross_brl' => '1999.99']))['reason'])->toBe('B41_SUPPLY_VALUES_DO_NOT_RECONCILE');
    $single = b41Operation();
    $single['supplies'] = [$single['supplies'][0]];
    expect(b41Assessor()->assess($single)['reason'])->toBe('B41_DISTINCT_SUPPLIES_REQUIRED');
});

it('keeps the whole operation pending when one supply is not decidable', function (): void {
    $op = b41Operation();
    $op['supplies'][1]['scenario']['facts']['rtc.travel_agency_settlement'][0]['total_charged_to_user'] = '999.00';
    $report = b41Assessor()->assess($op);
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report['totals'])->toBeNull()
        ->and(array_column($report['supplies'], 'status', 'id'))->toBe(['agency' => 'ACTION_REQUIRED', 'lodging' => 'ASSESSED']);
});
