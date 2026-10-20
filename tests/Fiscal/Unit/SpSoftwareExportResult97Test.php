<?php

declare(strict_types=1);

use App\Modules\Fiscal\ISS\SpSoftwareExportResult97;

dataset('sp97-documentary-cases', function (): array {
    $cases = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/sp97-cases.json'), true, 512, JSON_THROW_ON_ERROR);
    $data = [];
    foreach ($cases as $case) {
        $data[$case['name']] = [$case];
    }

    return $data;
});

it('qualifies SP documentary result conditions without importing an FX or tax policy', function (array $case): void {
    $component = new SpSoftwareExportResult97;
    $before = $case;
    $result = $component->qualify($case['context'], $case['national_policy'], $case['proof'], $case['policy'], new DateTimeImmutable($case['known_at']));
    expect($result['status'])->toBe($case['expected_status'])->and($result['reason'])->toBe($case['expected_reason'])
        ->and($case)->toBe($before);
    foreach (['government_evidence_authenticated', 'legal_applicability_approved', 'source_effective_date_proven',
        'iss_supply_date_materially_qualified', 'documentary_currency_conversion_qualified', 'tax_calculated',
        'xml_generated', 'document_obligation_complete', 'posting_authorized', 'production_ready'] as $key) {
        expect($result[$key])->toBeFalse();
    }
    foreach (['vISSQN', 'tax_amount', 'xml', 'fx_rate'] as $key) {
        expect($result)->not->toHaveKey($key);
    }
    if ($result['status'] !== 'ACTION_REQUIRED') {
        $candidate = $result['status'] === 'LOCAL_SP_EXPORT_NON_INCIDENCE_CANDIDATE';
        expect($result['municipal_documentary_conditions_locally_bound'])->toBeTrue()
            ->and($result['export_non_incidence_candidate'])->toBe($candidate)
            ->and($result['candidate_tribISSQN'])->toBe($candidate ? '3' : null)
            ->and($component->qualify($case['context'], $case['national_policy'], $case['proof'], array_reverse($case['policy'], true), new DateTimeImmutable($case['known_at'])))->toBe($result);
    }
})->with('sp97-documentary-cases');
