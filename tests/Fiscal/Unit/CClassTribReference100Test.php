<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CClassTribReference100;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;

function cclass100Fixture(): array
{
    static $fixture;
    if ($fixture === null) {
        $root = dirname(__DIR__, 3);
        $fixture = [json_decode((string) file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-2026.10.16-101-draft1.json'), true, 512, JSON_THROW_ON_ERROR),
            json_decode((string) file_get_contents($root.'/tests/Fixtures/cclass100-reference-cases.json'), true, 512, JSON_THROW_ON_ERROR)];
    }

    return $fixture;
}

dataset('cclass100-reference-cases', function (): array {
    [, $fixture] = cclass100Fixture();
    $data = [];
    foreach ($fixture['cases'] as $case) {
        $data[$case['name']] = [$case];
    }

    return $data;
});

it('validates reference source,time,row and document model without selecting tax treatment', function (array $case): void {
    [$catalog, $manifest] = cclass100Fixture();
    $policy = $manifest['policy'];
    $mutation = $case['mutation'];
    if ($mutation !== null) {
        switch ($mutation['kind']) {
            case 'policy-remove': unset($policy[$mutation['field']]);
                break;
            case 'policy-extra': $policy['extra'] = true;
                break;
            case 'policy-deployed': $policy['remote_deployment_proven'] = true;
                break;
            case 'source-remove': unset($catalog['sources']['cclasstrib'][$mutation['field']]);
                break;
            case 'remove-class-row': unset($catalog['cclasstrib']['000001']);
                break;
            case 'change-model-permission': $catalog['cclasstrib']['410028']['document_and_group_indicators']['indNFSe'] = 1;
                break;
            case 'change-donation-type': $catalog['cclasstrib']['000001']['tpDoacao'] = 0;
                break;
            case 'remove-cst-row': unset($catalog['cst_reference_rows']['000']);
                break;
            case 'change-cst-row': $catalog['cst_reference_rows']['000']['ind_gIBSCBS'] = 0;
                break;
            case 'resigned-table-policy':
                $catalog['cclasstrib']['000001']['tpDoacao'] = 0;
                $policy['rows_sha256'] = CanonicalJson::hash($catalog['cclasstrib']);
                break;
            case 'map-order':
                $catalog['cclasstrib'] = array_reverse($catalog['cclasstrib'], true);
                $policy = array_reverse($policy, true);
                break;
            default: throw new RuntimeException('Unknown fixture mutation');
        }
    }
    $result = (new CClassTribReference100)->resolve($catalog, $case['code'], $case['model'],
        LocalDate::fromString($case['on']), new DateTimeImmutable($case['known_at']), $policy);
    expect($result['status'])->toBe($case['expected_status'])->and($result['reason'])->toBe($case['expected_reason'])
        ->and($result['decision_authority'])->toBeFalse();
    foreach (['tax_treatment_selected', 'rate_determined', 'document_indicator_emitted', 'legal_applicability_approved',
        'government_evidence_authenticated', 'remote_deployment_proven', 'xml_generated', 'posting_authorized', 'production_ready'] as $flag) {
        expect($result[$flag])->toBeFalse();
    }
    if ($result['status'] === 'REFERENCE_ROW_AVAILABLE') {
        $row = $manifest['rows'][$case['code']];
        expect($result['code'])->toBe($case['code'])->and($result['cst'])->toBe($row['cst'])
            ->and($result['tpDoacao_reference'])->toBe($row['tpDoacao'])->and($result['tpRBSN_reference'])->toBe($row['tpRBSN'])
            ->and($result['engine_decision_required'])->toBeTrue();
    }
})->with('cclass100-reference-cases');
