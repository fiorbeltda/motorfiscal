<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DocumentPriceFactProjection112;
use App\Modules\Fiscal\ConsumptionReform\PartialExportDocumentPrices112;
use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Facts\FactDefinition;
use App\Modules\Fiscal\Core\Facts\FactDefinitionRegistry;
use App\Modules\Fiscal\Core\Facts\FactSourceType;
use App\Modules\Fiscal\Core\Facts\FactValue;
use App\Modules\Fiscal\Core\Facts\FactValueType;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

function price112Fact(string $key, mixed $value, FactSourceType $source = FactSourceType::FIXTURE): FactValue
{
    return new FactValue($key, $value, $source, LocalDate::fromString('2026-09-25'), null, new DateTimeImmutable('2026-10-19T19:00:00.123456+00:00'));
}

function price112ProjectionBag(FactSourceType $presentationSource = FactSourceType::FIXTURE, FactSourceType $monetarySource = FactSourceType::FIXTURE): FactBag
{
    $values = ['provider.iss_regime_proof_records' => [['evidence_id' => 'REGIME']],
        'rtc.export_dated_dps_identity_records' => [['proof' => 'IDENTITY']],
        DocumentPriceFactProjection112::PRICE_RECORDS => [['role' => 'RTC_FOREIGN', 'service_price' => '350.00'], ['role' => 'RTC_DOMESTIC', 'service_price' => '650.00']],
        DocumentPriceFactProjection112::COMPOSITION_RECORDS => [['principal' => '350.00'], ['principal' => '650.00']],
        DocumentPriceFactProjection112::PRICE_PROOFS => [['source_hash' => str_repeat('a', 64)]],
        DocumentPriceFactProjection112::COMPOSITION_PROOFS => [['source_hash' => str_repeat('b', 64)]],
        'service.lc116_code' => '1.01', 'price112.unknown_business_fact' => 'UNREAD-BUSINESS',
        'price112.unknown_derived_fact' => 'UNREAD-DERIVED', 'rtc.currency' => 'USD', 'rtc.payment_date' => '2026-09-01'];
    $definitions = [];
    foreach ($values as $key => $value) {
        $definitions[] = new FactDefinition($key, is_array($value) ? FactValueType::ARRAY : FactValueType::STRING);
    }
    $bag = new FactBag(new FactDefinitionRegistry($definitions));
    foreach ($values as $key => $value) {
        $source = $key === 'service.lc116_code' ? $presentationSource : ($key === 'price112.unknown_derived_fact' ? FactSourceType::DERIVED : FactSourceType::FIXTURE);
        if (in_array($key, [DocumentPriceFactProjection112::PRICE_RECORDS, DocumentPriceFactProjection112::COMPOSITION_RECORDS], true)) {
            $source = $monetarySource;
        }
        $bag->put(price112Fact($key, $value, $source));
    }

    return $bag;
}

function price112ScenarioBag(): FactBag
{
    $factory = new FiscalEngineFactory;
    $root = dirname(__DIR__, 3);
    $knowledge = $factory->captureKnowledge($root.'/resources/fiscal', 'bundles/consumption-phase4b-2026.10.19-112-draft2.json');
    $manifest = json_decode((string) file_get_contents($root.'/tests/Fixtures/price112-scenario-expectations-v2.json'), true, 512, JSON_THROW_ON_ERROR);

    return $factory->scenarioLoaderFromKnowledge($knowledge)->load($root.'/'.$manifest['cases'][0]['path'])->input->context->facts;
}

function price112ExtendBag(FactBag $source, string $key, mixed $value, FactValueType $type, FactSourceType $origin): FactBag
{
    $bag = new FactBag(new FactDefinitionRegistry([...$source->definitions(), new FactDefinition($key, $type)]));
    foreach ($source->all() as $fact) {
        $bag->put($fact);
    }
    $bag->put(price112Fact($key, $value, $origin));

    return $bag;
}

it('removes only declared proof carriers and retains all provided financial and unknown business context', function (FactSourceType $presentationSource, FactSourceType $monetarySource): void {
    $bag = price112ProjectionBag($presentationSource, $monetarySource);
    $before = CanonicalJson::hash($bag->canonicalValues());
    $projected = DocumentPriceFactProjection112::from($bag);
    $expected = $bag->canonicalValues();
    unset($expected['rtc.export_dated_dps_identity_records'], $expected[DocumentPriceFactProjection112::PRICE_PROOFS], $expected[DocumentPriceFactProjection112::COMPOSITION_PROOFS]);
    if ($presentationSource === FactSourceType::DERIVED) {
        unset($expected['service.lc116_code']);
    }
    $expected['provider.iss_regime_proof'] = ['evidence_id' => 'REGIME'];
    expect(CanonicalJson::hash($projected['facts']))->toBe(CanonicalJson::hash($expected))
        ->and($projected['facts']['price112.unknown_business_fact'])->toBe('UNREAD-BUSINESS')
        ->and($projected['facts']['price112.unknown_derived_fact'])->toBe('UNREAD-DERIVED')
        ->and($projected['facts']['rtc.currency'])->toBe('USD')->and($projected['facts']['rtc.payment_date'])->toBe('2026-09-01')
        ->and($projected['source_current_facts_hash'])->toBe($before)->and(CanonicalJson::hash($bag->canonicalValues()))->toBe($before)
        ->and($projected['qualification_context_facts_hash'])->toBe(CanonicalJson::hash($expected));
})->with([
    'provided presentation and money' => [FactSourceType::FIXTURE, FactSourceType::FIXTURE],
    'derived presentation only' => [FactSourceType::DERIVED, FactSourceType::FIXTURE],
    'derived monetary records remain business' => [FactSourceType::DERIVED, FactSourceType::DERIVED],
]);

it('changes the authoritative qualification hash when monetary or unread provided facts change', function (string $key, mixed $value): void {
    $bag = price112ProjectionBag();
    $old = DocumentPriceFactProjection112::from($bag);
    $changed = $bag->withReplaced(price112Fact($key, $value));
    $new = DocumentPriceFactProjection112::from($changed);
    expect($new['qualification_context_facts_hash'])->not->toBe($old['qualification_context_facts_hash'])
        ->and($new['source_current_facts_hash'])->not->toBe($old['source_current_facts_hash']);
})->with([
    'prices' => [DocumentPriceFactProjection112::PRICE_RECORDS, [['service_price' => '351.00']]],
    'composition' => [DocumentPriceFactProjection112::COMPOSITION_RECORDS, [['principal' => '351.00']]],
    'unknown business' => ['price112.unknown_business_fact', 'CHANGED'],
    'provided presentation is binding' => ['service.lc116_code', '1.02'],
    'separate currency' => ['rtc.currency', 'EUR'],
    'separate payment date' => ['rtc.payment_date', '2026-10-01'],
]);

it('tracks proof-carrier corruption in the full source hash without introducing a qualification self-cycle', function (string $key): void {
    $bag = price112ProjectionBag();
    $old = DocumentPriceFactProjection112::from($bag);
    $changed = $bag->withReplaced(price112Fact($key, [['source_hash' => str_repeat('0', 64)]]));
    $new = DocumentPriceFactProjection112::from($changed);
    expect($new['qualification_context_facts_hash'])->toBe($old['qualification_context_facts_hash'])
        ->and($new['source_current_facts_hash'])->not->toBe($old['source_current_facts_hash']);
})->with([DocumentPriceFactProjection112::PRICE_PROOFS, DocumentPriceFactProjection112::COMPOSITION_PROOFS, 'rtc.export_dated_dps_identity_records']);

it('refuses to silently overwrite a caller-provided normalized regime proof', function (): void {
    $bag = price112ExtendBag(price112ProjectionBag(), 'provider.iss_regime_proof', [['caller' => 'unexpected']], FactValueType::ARRAY, FactSourceType::FIXTURE);
    expect(fn () => DocumentPriceFactProjection112::from($bag))->toThrow(InvalidArgumentException::class);
});

it('rejects singleton transport violations before any cached price or composition assertion can be trusted', function (string $key, array $replacement): void {
    $bag = price112ScenarioBag()->withReplaced(price112Fact($key, $replacement));
    $result = (new PartialExportDocumentPrices112)->qualify($bag, new DateTimeImmutable('2026-10-19T19:00:00.123456+00:00'), PartialExportDocumentPrices112::policy());
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('RTC_EXPORT_PRICE112_SINGLETON_PROOF_TRANSPORT_REQUIRED')
        ->and($result['missing_facts'])->toBe([$key])->and(array_column($result['trace'], 'stage'))->toBe(['PARTIAL_EXPORT_DOCUMENT_PRICE_CONTEXT']);
})->with([
    'no price proof' => [DocumentPriceFactProjection112::PRICE_PROOFS, []],
    'two price proofs' => [DocumentPriceFactProjection112::PRICE_PROOFS, [[], []]],
    'no composition proof' => [DocumentPriceFactProjection112::COMPOSITION_PROOFS, []],
    'two composition proofs' => [DocumentPriceFactProjection112::COMPOSITION_PROOFS, [[], []]],
    'no regime proof' => ['provider.iss_regime_proof_records', []],
    'two regime proofs' => ['provider.iss_regime_proof_records', [[], []]],
]);

it('rejects stale mappings when an otherwise unread business fact is supplied,including derived unknowns', function (FactSourceType $origin): void {
    $bag = price112ExtendBag(price112ScenarioBag(), 'price112.extra_business_fact', 'MUST-BE-BOUND', FactValueType::STRING, $origin);
    $result = (new PartialExportDocumentPrices112)->qualify($bag, new DateTimeImmutable('2026-10-19T19:00:00.123456+00:00'), PartialExportDocumentPrices112::policy());
    expect($result['status'])->toBe('INVALID')->and($result['reason'])->toBe('PRICE110_FRESH_DATED_IDENTITY_PAIR_REQUIRED')
        ->and(array_column($result['trace'], 'stage'))->not->toContain('PARTIAL_EXPORT_ISS_PRICE_COMPOSITION');
})->with([FactSourceType::FIXTURE, FactSourceType::DERIVED]);

it('requires an exact transport policy and its own availability before examining evidence', function (bool $changePolicy): void {
    $policy = PartialExportDocumentPrices112::policy();
    if ($changePolicy) {
        $policy['monetary_records_remain_qualification_facts'] = false;
    }
    $at = new DateTimeImmutable($changePolicy ? '2026-10-19T19:00:00.123456+00:00' : '2026-10-19T18:25:35+00:00');
    $result = (new PartialExportDocumentPrices112)->qualify(price112ScenarioBag(), $at, $policy);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe($changePolicy ? 'RTC_EXPORT_PRICE112_EXACT_TRANSPORT_POLICY_REQUIRED' : 'RTC_EXPORT_PRICE112_TRANSPORT_NOT_YET_AVAILABLE')
        ->and(array_column($result['trace'], 'stage'))->toBe(['PARTIAL_EXPORT_DOCUMENT_PRICE_CONTEXT']);
})->with([true, false]);
