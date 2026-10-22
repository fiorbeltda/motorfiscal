<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\ImportResponsibility129;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;

require_once dirname(__DIR__, 3).'/scripts/phase4b_import129_fixture.php';

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function import129Assessment(array $overrides, string $runner, bool $recipient = false, string $version = '129'): array
{
    $factory = new FiscalEngineFactory;
    [$knowledge, $source] = phase4bImport129Fixture($factory, $overrides, $recipient, $version);
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $result = $runner === 'rtc' ? (new RtcScenarioRunService($factory))->evaluate($knowledge, $loaded)->toArray()
        : $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input)->toArray();

    return $runner === 'rtc' ? $result : array_column($result['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
}

it('separates Brazilian taxpayer and foreign supplier liability in both import routes', function (string $runner, bool $recipient): void {
    $result = import129Assessment([], $runner, $recipient);
    expect($result['reason_codes'])->toContain('RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN');
    $trace = array_column($result['trace'], null, 'stage');
    $responsibility = $trace['IMPORT_RESPONSIBILITY129'];
    expect($responsibility['status'])->toBe('RESOLVED')
        ->and($responsibility['taxpayer_role'])->toBe($recipient ? 'RECIPIENT' : 'ACQUIRER')
        ->and($responsibility['taxpayer'])->toBe('22222222000191')
        ->and($responsibility['foreign_supplier_responsibility']['party_id'])->toBe('11111111000191')
        ->and($responsibility['source_sha256'])->toBe(ImportResponsibility129::SOURCE_SHA256)
        ->and($responsibility['payment_proven'])->toBeFalse()
        ->and($responsibility['document_obligation_complete'])->toBeFalse()
        ->and($trace['IMPORT_NOMINAL_CALCULATION']['ibs_state'])->toBe('1.00')
        ->and($trace['IMPORT_NOMINAL_CALCULATION']['cbs'])->toBe('9.00');
})->with(['rtc', 'integrated'])->with([false, true]);

it('qualifies only explicit source references and asks for unsupported import bases', function (string $basis, string $reason, string $runner): void {
    $result = import129Assessment(['rtc.collection_legal_basis' => $basis], $runner);
    expect($result['reason_codes'])->toContain('RTC_IMPORT129_'.$reason)
        ->and($result['missing_facts'])->toContain('rtc.collection_legal_basis');
})->with([
    ['anything', 'BASIS_NOT_QUALIFIED'], ['LC214_ART36', 'BASIS_NOT_QUALIFIED'],
    ['LC214-ART64-VI', 'BASIS_ROLE_CONFLICT'], ['LC214-ART64-V ', 'BASIS_NOT_QUALIFIED'],
    [' ', 'BASIS_MISSING'],
])->with(['rtc', 'integrated']);

it('refuses inconsistent explicit import taxpayer or collection role', function (array $overrides, string $reason, string $runner): void {
    $result = import129Assessment($overrides, $runner);
    expect($result['reason_codes'])->toContain('RTC_IMPORT129_'.$reason);
})->with([
    [['rtc.taxpayer_id' => 'FOREIGN-SUPPLIER'], 'TAXPAYER_CONFLICT'],
    [['rtc.collection_method' => 'SUPPLIER'], 'COLLECTION_ROUTE_CONFLICT'],
    [['rtc.collection_method' => 'RECIPIENT'], 'COLLECTION_ROUTE_CONFLICT'],
    [['rtc.recipient_id' => '22222222000191', 'rtc.recipient_country' => 'CA'], 'PARTY_RESIDENCE_CONFLICT'],
    [['rtc.supplier_id' => '22222222000191'], 'PARTY_RESIDENCE_CONFLICT'],
])->with(['rtc', 'integrated']);

it('keeps historical128 evaluation unchanged while129 rejects its unqualified basis', function (string $runner): void {
    $overrides = ['rtc.collection_legal_basis' => 'unrelated-non-empty-reference'];
    $old = import129Assessment($overrides, $runner, version: '128');
    $new = import129Assessment($overrides, $runner);
    expect($old['reason_codes'])->toContain('RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN')
        ->and($new['reason_codes'])->toContain('RTC_IMPORT129_BASIS_NOT_QUALIFIED')
        ->and(array_column($old['trace'], 'stage'))->not->toContain('IMPORT_POLICY129');
})->with(['rtc', 'integrated']);

it('retains every mandatory source guard when its catalog marker is removed', function (string $key, string $stage): void {
    $factory = new FiscalEngineFactory;
    [$knowledge, $source] = phase4bImport129Fixture($factory);
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $codes = $knowledge['documents']['rtc_code_catalog'];
    unset($codes[$key]);
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($knowledge['documents']['rtc_rate_catalog']),
        $codes, new TransitionCatalog($knowledge['documents']['rtc_transition_catalog']));
    $result = $engine->evaluate($loaded->input->context->facts, $loaded->input->context->competenceDate, $loaded->input->context->knowledgeAt);
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and(array_column($result['trace'], null, 'stage')[$stage]['status'])->toBe('ACTION_REQUIRED');
})->with([
    ['import_responsibility_policy129', 'IMPORT_POLICY129'],
    ['dps_foreign_policy128', 'DPS_FOREIGN_POLICY128'], ['dps_layout_policy127', 'DPS_LAYOUT_POLICY127'],
    ['cindop_reference126', 'CINDOP_POLICY126'], ['material_input_policy119', 'MATERIAL_INPUT_POLICY119'],
    ['credit_input_policy122', 'CREDIT_INPUT_POLICY122'],
]);

it('preserves qualified platform responsibility as separate from the import taxpayer', function (string $runner): void {
    $result = import129Assessment(['rtc.platform_id' => 'PLATFORM-129', 'rtc.platform_remote_intermediation' => true,
        'rtc.platform_controlled_elements' => ['PAYMENT'], 'rtc.platform_activity_exclusion' => 'NONE',
        'rtc.platform_qualification_evidence_id' => 'SYNTHETIC-PLATFORM129'], $runner);
    $responsibility = array_column($result['trace'], null, 'stage')['IMPORT_RESPONSIBILITY129'];
    expect($responsibility['taxpayer'])->toBe('22222222000191')
        ->and($responsibility['platform_responsibility']['responsible_id'])->toBe('PLATFORM-129')
        ->and($responsibility['platform_responsibility']['responsibility'])->toBe('FOREIGN_SUPPLIER_SUBSTITUTIVE_SOLIDARY')
        ->and($responsibility['payment_proven'])->toBeFalse();
})->with(['rtc', 'integrated']);

it('accepts the inherited explicit reference aliases for each statutory taxpayer role', function (string $basis, bool $recipient): void {
    $result = import129Assessment(['rtc.collection_legal_basis' => $basis], 'rtc', $recipient);
    expect($result['reason_codes'])->toContain('RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN');
})->with([
    ['LC214 art. 64, §5º, V', false], ['LC214.2025.ART64.PAR5.V', false],
    ['LC214 art. 64, §5º, VI', true], ['LC214.2025.ART64.PAR5.VI', true],
]);
