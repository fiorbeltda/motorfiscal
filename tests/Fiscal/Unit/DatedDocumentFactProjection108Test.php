<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DatedDocumentFactProjection108;
use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Facts\FactDefinition;
use App\Modules\Fiscal\Core\Facts\FactDefinitionRegistry;
use App\Modules\Fiscal\Core\Facts\FactSourceType;
use App\Modules\Fiscal\Core\Facts\FactValue;
use App\Modules\Fiscal\Core\Facts\FactValueType;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;

function dated108ProjectionBag(string $key, FactSourceType $type): FactBag
{
    $values = ['rtc.operation_date' => '2026-09-25', 'rtc.supplier_id' => '11111111000191',
        'provider.iss_regime_proof_records' => [['kind' => 'SYNTHETIC-TRANSPORT']],
        'rtc.export_dated_dps_identity_records' => [['proof' => 'SYNTHETIC-CARRIER']], $key => 'bound-value'];
    $definitions = [];
    foreach ($values as $name => $value) {
        $definitions[] = new FactDefinition($name, is_array($value) ? FactValueType::ARRAY : FactValueType::STRING);
    }
    $bag = new FactBag(new FactDefinitionRegistry($definitions));
    foreach ($values as $name => $value) {
        $bag->put(new FactValue($name, $value, $name === $key ? $type : FactSourceType::FIXTURE,
            LocalDate::fromString('2026-09-25'), null, new DateTimeImmutable('2026-10-19T16:00:00+00:00')));
    }

    return $bag;
}

it('excludes only a named presentation fact when it actually is derived', function (string $key): void {
    $bag = dated108ProjectionBag($key, FactSourceType::DERIVED);
    $before = $bag->canonicalValues();
    $projection = DatedDocumentFactProjection108::from($bag);
    $provided = $before;
    unset($provided[$key]);
    expect($projection['facts'])->not->toHaveKey($key)->not->toHaveKey('rtc.export_dated_dps_identity_records')
        ->and($projection['excluded_derived_fact_keys'])->toBe([$key])
        ->and($projection['provided_context_facts_hash'])->toBe(CanonicalJson::hash($provided))
        ->and($projection['source_current_facts_hash'])->toBe(CanonicalJson::hash($before))
        ->and($projection['facts']['rtc.operation_date'])->toBe('2026-09-25')
        ->and($projection['facts']['provider.iss_regime_proof'])->toBe($before['provider.iss_regime_proof_records'][0])
        ->and($bag->canonicalValues())->toBe($before);
})->with(['service.lc116_code', 'service.ctribnac_code', 'resolved.iss.location_strategy', 'payment.gross_amount', 'invoice.gross_amount']);

it('keeps supplied presentation facts bound rather than ignoring them by name', function (string $key): void {
    $bag = dated108ProjectionBag($key, FactSourceType::FIXTURE);
    $projection = DatedDocumentFactProjection108::from($bag);
    expect($projection['facts'][$key])->toBe('bound-value')->and($projection['excluded_derived_fact_keys'])->toBe([])
        ->and($projection['provided_context_facts_hash'])->toBe(CanonicalJson::hash($bag->canonicalValues()));
})->with(['service.lc116_code', 'service.ctribnac_code', 'resolved.iss.location_strategy', 'payment.gross_amount', 'invoice.gross_amount']);

it('never discards an unknown derived business fact or treats the DERIVED source type as a general exemption', function (): void {
    $bag = dated108ProjectionBag('rtc.gross_amount', FactSourceType::DERIVED);
    $projection = DatedDocumentFactProjection108::from($bag);
    expect($projection['facts']['rtc.gross_amount'])->toBe('bound-value')->and($projection['excluded_derived_fact_keys'])->toBe([]);
});
