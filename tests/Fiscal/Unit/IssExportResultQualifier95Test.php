<?php

declare(strict_types=1);

use App\Modules\Fiscal\ISS\IssExportResultQualifier95;

dataset('iss-export95-independent-results', function (): array {
    $cases = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/iss-export95-cases.json'), true, 512, JSON_THROW_ON_ERROR);
    $data = [];
    foreach ($cases as $case) {
        $data[$case['name']] = [$case];
    }

    return $data;
});

it('binds independent per-document service results without inventing taxes or document approval', function (array $case): void {
    $component = new IssExportResultQualifier95;
    $before = $case;
    $result = $component->qualify($case['context'], new DateTimeImmutable($case['known_at']), $case['policy']);
    expect($result['status'])->toBe($case['expected_status'])
        ->and($result['reason'])->toBe($case['expected_reason'])->and($case)->toBe($before);
    foreach (['government_evidence_authenticated', 'legal_applicability_approved',
        'documentary_currency_conversion_qualified', 'tax_calculated', 'iss_full_operation_rewritten',
        'xml_generated', 'document_obligation_complete', 'posting_authorized', 'production_ready'] as $flag) {
        expect($result[$flag])->toBeFalse();
    }
    expect($result)->not->toHaveKey('tax_amount')->and($result)->not->toHaveKey('vISSQN');
    if ($case['expected_result_scope'] !== null) {
        $abroad = $case['expected_result_scope'] === 'ABROAD';
        expect($result['result_scope'])->toBe($case['expected_result_scope'])
            ->and($result['export_non_incidence_candidate'])->toBe($abroad)
            ->and($result['candidate_tribISSQN'])->toBe($abroad ? '3' : null)
            ->and($result['independent_result_evidence_locally_bound'])->toBeTrue()
            ->and($component->qualify(array_reverse($case['context'], true), new DateTimeImmutable($case['known_at']), array_reverse($case['policy'], true)))->toBe($result);
    } else {
        expect($result['independent_result_evidence_locally_bound'])->toBeFalse();
    }
})->with('iss-export95-independent-results');
