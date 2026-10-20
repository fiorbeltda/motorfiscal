<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\PartialExportDatedDpsIdentity107;
use App\Modules\Fiscal\ConsumptionReform\PartialExportIssDatedResults106;
use App\Modules\Fiscal\ConsumptionReform\PartialExportIssMaterialDate105;

function dated108PreparedCases(string $fixture): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/'.$fixture), true, 512, JSON_THROW_ON_ERROR)['cases'];
}

dataset('dated108-material105', fn (): array => array_combine(
    array_column(dated108PreparedCases('dated105-cases.json'), 'name'),
    array_map(fn (array $case): array => [$case], dated108PreparedCases('dated105-cases.json'))));
dataset('dated108-results106', fn (): array => array_combine(
    array_column(dated108PreparedCases('dated106-cases.json'), 'name'),
    array_map(fn (array $case): array => [$case], dated108PreparedCases('dated106-cases.json'))));
dataset('dated108-identity107', fn (): array => array_combine(
    array_column(dated108PreparedCases('dated107-cases.json'), 'name'),
    array_map(fn (array $case): array => [$case], dated108PreparedCases('dated107-cases.json'))));

function dated108CheckPreparedLimits(array $result, array $case): void
{
    expect($result['status'])->toBe($case['expected_status'])->and($result['reason'])->toBe($case['expected_reason']);
    foreach (['iss_supply_date_materially_qualified', 'legal_applicability_approved', 'government_evidence_authenticated',
        'documentary_currency_conversion_qualified', 'per_document_tax_amount_calculated', 'xml_generated',
        'document_obligation_complete', 'posting_authorized', 'production_ready'] as $flag) {
        expect($result[$flag])->toBeFalse();
    }
}

it('preserves prepared105 material evidence and refusal contracts after namespace-only installation', function (array $case): void {
    $result = (new PartialExportIssMaterialDate105)->qualify($case['facts'], $case['records'], $case['known_at'], $case['policy']);
    dated108CheckPreparedLimits($result, $case);
})->with('dated108-material105');

it('preserves prepared106 independent-date result composition after namespace-only installation', function (array $case): void {
    $result = (new PartialExportIssDatedResults106)->qualify($case['facts'], $case['material_records'],
        $case['result_records'], $case['known_at'], $case['policy']);
    dated108CheckPreparedLimits($result, $case);
    expect($result['rtc_facts_rewritten'])->toBeFalse()->and($result['cached_qualification_used'])->toBeFalse();
})->with('dated108-results106');

it('preserves prepared107 per-document competence identity and strict refusals after namespace-only installation', function (array $case): void {
    $result = (new PartialExportDatedDpsIdentity107)->qualify($case['facts'], $case['material_records'],
        $case['result_records'], $case['identity_records'], $case['known_at'], $case['policy']);
    dated108CheckPreparedLimits($result, $case);
    expect($result['rtc_facts_rewritten'])->toBeFalse()->and($result['dps_competence_legally_approved'])->toBeFalse();
})->with('dated108-identity107');
