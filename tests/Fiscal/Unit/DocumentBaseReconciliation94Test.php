<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DocumentBaseReconciliation;

function documentBase94Cases(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/document-base94-cases.json'), true, 512, JSON_THROW_ON_ERROR);
}

dataset('document-base94-independent-cases', function (): array {
    $data = [];
    foreach (documentBase94Cases() as $case) {
        $data[$case['name']] = [$case];
    }

    return $data;
});

it('compares declared documentary memory without claiming qualification or authorization', function (array $case): void {
    $component = new DocumentBaseReconciliation;
    $before = $case;
    $result = $component->compare($case['document'], new DateTimeImmutable($case['known_at']), $case['policy']);
    expect($result['status'])->toBe($case['expected_status'])
        ->and($result['reason'])->toBe($case['expected_reason'])
        ->and($case)->toBe($before);
    foreach (['inputs_materially_qualified', 'source_effective_date_proven', 'tax_recalculated',
        'issuer_fields_generated', 'xml_schema_validated', 'document_obligation_complete',
        'posting_authorized', 'production_ready'] as $flag) {
        expect($result[$flag])->toBeFalse();
    }
    if (isset($case['independent_decimal_base'])) {
        expect($result['calculated_document_base_brl'])->toBe($case['independent_decimal_base'])
            ->and($result['difference_brl'])->toBe($case['independent_decimal_difference'])
            ->and($result['input_memory_hash'])->toBe(CanonicalJson::hash($case['document']))
            ->and($result['policy_hash'])->toBe(CanonicalJson::hash($case['policy']))
            ->and($component->compare(array_reverse($case['document'], true), new DateTimeImmutable($case['known_at']), $case['policy']))->toBe($result);
    }
})->with('document-base94-independent-cases');

it('does not turn source observation or a declared base match into documentary acceptance', function (): void {
    $case = documentBase94Cases()[0];
    $result = (new DocumentBaseReconciliation)->compare($case['document'], new DateTimeImmutable($case['known_at']), $case['policy']);
    expect($result['status'])->toBe('LOCAL_DOCUMENT_BASE_MATCH')
        ->and($result['scope'])->toBe('LOCAL_DECLARED_BASE_MEMORY_COMPARISON_ONLY')
        ->and($result['source_effective_date_proven'])->toBeFalse()
        ->and($result['document_obligation_complete'])->toBeFalse()
        ->and($result)->not->toHaveKey('xml')
        ->and($result)->not->toHaveKey('taxes');
});

it('keeps different documentary memories and policies independently traceable', function (): void {
    $case = documentBase94Cases()[0];
    $component = new DocumentBaseReconciliation;
    $first = $component->compare($case['document'], new DateTimeImmutable($case['known_at']), $case['policy']);
    $case['document']['evidence_id'] = 'OTHER-DECLARED-MEMORY';
    $case['policy']['audit_reference'] = 'LOCAL-NOT-LEGAL';
    $second = $component->compare($case['document'], new DateTimeImmutable($case['known_at']), $case['policy']);
    expect($second['calculated_document_base_brl'])->toBe($first['calculated_document_base_brl'])
        ->and($second['input_memory_hash'])->not->toBe($first['input_memory_hash'])
        ->and($second['policy_hash'])->not->toBe($first['policy_hash'])
        ->and($second['posting_authorized'])->toBeFalse();
});
