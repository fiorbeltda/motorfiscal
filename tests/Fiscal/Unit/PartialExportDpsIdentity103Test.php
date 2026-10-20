<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\PartialExportDpsIdentity103;

function identity103Cases(): array
{
    static $cases;

    return $cases ??= json_decode((string) file_get_contents(dirname(__DIR__, 3).'/tests/Fixtures/identity103-cases.json'), true, 512, JSON_THROW_ON_ERROR)['cases'];
}

dataset('identity103-native-cases', fn (): array => array_combine(array_column(identity103Cases(), 'name'), array_map(fn (array $c): array => [$c], identity103Cases())));

it('preserves audited103 identity scope and refusals after namespace-only installation', function (array $case): void {
    $result = (new PartialExportDpsIdentity103)->qualify($case['facts'], $case['records'], $case['known_at'], $case['policy']);
    expect($result['status'])->toBe($case['expected_status'])->and($result['reason'])->toBe($case['expected_reason'])
        ->and($result['original_references_preserved'])->toBeTrue();
    foreach (['fiscal_document_identifiers_qualified', 'cnpj_check_digits_verified', 'municipal_authorization_verified',
        'cadastral_identity_authenticated', 'remote_uniqueness_verified', 'government_evidence_authenticated',
        'iss_supply_date_materially_qualified', 'documentary_currency_conversion_qualified', 'per_document_tax_amount_calculated',
        'document_obligation_complete', 'remote_deployment_proven', 'xml_generated', 'posting_authorized', 'production_ready'] as $flag) {
        expect($result[$flag])->toBeFalse();
    }
    if ($case['expected_status'] === 'LOCAL_DPS_IDENTITY_PAIR_BOUND') {
        expect(array_column($result['documents'], 'dps_id'))->toBe($case['expected_ids'])
            ->and(array_column($result['documents'], 'document_reference'))->toBe([
                $case['facts']['rtc.export_foreign_document_id'], $case['facts']['rtc.export_domestic_document_id']]);
    }
})->with('identity103-native-cases');

it('retains semantic order independence without hiding raw record order', function (): void {
    $case = identity103Cases()[0];
    $component = new PartialExportDpsIdentity103;
    $first = $component->qualify($case['facts'], $case['records'], $case['known_at'], $case['policy']);
    $second = $component->qualify($case['facts'], array_reverse($case['records']), $case['known_at'], $case['policy']);
    expect($first['documents'])->toBe($second['documents'])->and($first['binding_hash'])->toBe($second['binding_hash'])
        ->and($first['raw_records_hash'])->not->toBe($second['raw_records_hash']);
});
