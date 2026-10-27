<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\NFSe\FederalRetention137;

require_once dirname(__DIR__, 3).'/scripts/phase4b_public139_fixture.php';

function public139Knowledge(bool $legacy = false): array
{
    static $cache = [];

    return $cache[(int) $legacy] ??= (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'),
        $legacy ? 'bundles/consumption-phase4b-2026.10.22-134-draft1.json' : 'bundles/consumption-phase4b-2026.10.26-139-draft1.json');
}

function public139Evaluate(array $input, ?array $knowledge = null): array
{
    $factory = new FiscalEngineFactory;
    $knowledge ??= public139Knowledge();
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);

    return $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input)->toArray();
}

it('asks the missing material Siafi fact instead of silently dispensing the payment', function (): void {
    $input = public139Input(public139Knowledge(), ['operation.amount' => '100.00',
        'payment.gross_amount' => '100.00', 'invoice.gross_amount' => '100.00']);
    unset($input['facts']['payer.siafi_electronic_collection']);
    $result = public139Evaluate($input);
    $federal = array_values(array_filter($result['trace'], fn (array $t): bool => $t['type'] === 'FEDERAL_WITHHOLDINGS'))[0]['payload'];
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['missing_facts'])->toContain('payer.siafi_electronic_collection')
        ->and($federal['status'])->toBe('ACTION_REQUIRED')
        ->and($federal['missing_facts'])->toContain('payer.siafi_electronic_collection')
        ->and($federal['irrf']['reason_codes'])->toContain('PUBLIC_SIAFI_COLLECTION_STATUS_REQUIRED')
        ->and(array_column($result['questions'], 'fact_key'))->toContain('payer.siafi_electronic_collection');
    $question = array_values(array_filter($result['questions'], fn (array $q): bool => $q['fact_key'] === 'payer.siafi_electronic_collection'))[0];
    expect($question['prompt'])->toContain('Darf eletrônico via Siafi');
});

it('preserves the fiscal components for explicit Siafi answers and nonmaterial cases', function (string $gross, ?bool $siafi, string $status, string $irrf, string $pcc): void {
    $input = public139Input(public139Knowledge(), ['operation.amount' => $gross,
        'payment.gross_amount' => $gross, 'invoice.gross_amount' => $gross]);
    if ($siafi === null) {
        unset($input['facts']['payer.siafi_electronic_collection']);
    } else {
        $input['facts']['payer.siafi_electronic_collection'] = $siafi;
    }
    $federal = public139Evaluate($input)['decision']['federal_withholdings'];
    expect($federal['irrf']['status'])->toBe($status)
        ->and($federal['irrf']['amount'])->toBe($irrf)
        ->and($federal['pcc']['total_withholding'])->toBe($pcc);
})->with([
    ['100.00', false, 'DISPENSED_THRESHOLD', '0.00', '0.00'],
    ['100.00', true, 'WITHHOLD', '4.80', '4.65'],
    ['1000.00', null, 'WITHHOLD', '48.00', '46.50'],
    ['1000.00', false, 'WITHHOLD', '48.00', '46.50'],
    ['1000.00', true, 'WITHHOLD', '48.00', '46.50'],
    ['0.00', null, 'ZERO_RATE', '0.00', '0.00'],
    ['0.00', false, 'ZERO_RATE', '0.00', '0.00'],
    ['0.00', true, 'ZERO_RATE', '0.00', '0.00'],
]);

it('projects municipal IR with canonical zero PCC and no automatic social retention', function (): void {
    $result = public139Evaluate(public139Input(public139Knowledge(), ['payer.person_type' => 'MUNICIPAL_PUBLIC_ADMINISTRATION']));
    $federal = $result['decision']['federal_withholdings'];
    $projection = (new FederalRetention137)->project($federal, FederalRetention137::policy(),
        new DateTimeImmutable('2026-10-26T19:15:00+00:00'), resource_path('fiscal'));
    expect($federal['status'])->toBe('RESOLVED')
        ->and($federal['pcc']['status'])->toBe('NOT_APPLICABLE')
        ->and($federal['pcc']['total_withholding'])->toBe('0.00')
        ->and($federal['irrf']['amount'])->toBe('30.78')
        ->and($federal['net_payment'])->toBe('610.55')
        ->and($projection['status'])->toBe('STAGED_FEDERAL_RETENTION_FIELDS_COMPUTED')
        ->and($projection['fields'])->toBe(['piscofins.tpRetPisCofins' => '0', 'vRetIRRF' => '30.78']);
});

it('retains the distinct public and private company component totals', function (array $overrides, string $irrf, string $pcc, string $net, string $code): void {
    $federal = public139Evaluate(public139Input(public139Knowledge(), $overrides))['decision']['federal_withholdings'];
    expect($federal['status'])->toBe('RESOLVED')
        ->and($federal['irrf']['amount'])->toBe($irrf)
        ->and($federal['pcc']['total_withholding'])->toBe($pcc)
        ->and($federal['net_payment'])->toBe($net)
        ->and($federal['pcc']['collection_code'])->toBe($code);
})->with([
    [[], '30.78', '29.82', '580.73', '6190'],
    [['payer.public_entity_type' => 'MIXED_CAPITAL_COMPANY'], '30.78', '29.82', '580.73', '6190'],
    [['payer.person_type' => 'MUNICIPAL_PUBLIC_ADMINISTRATION', 'public.article33_convention_exists' => true,
        'public.article33_convention_source_id' => 'SYNTHETIC-CONVENTION-139',
        'public.article33_convention_valid_from' => '2026-01-01', 'public.article33_convention_valid_to' => '2026-12-31'], '30.78', '29.82', '580.73', '4085'],
    [['payer.person_type' => 'STATE_PUBLIC_ADMINISTRATION', 'payer.public_entity_type' => 'PUBLIC_COMPANY'], '0.00', '29.82', '611.51', '5952'],
]);

it('keeps archived knowledge results byte identical and rejects unknown public contracts', function (): void {
    $legacy = public139Knowledge(true);
    $input = public139Input($legacy, ['operation.amount' => '100.00', 'payment.gross_amount' => '100.00', 'invoice.gross_amount' => '100.00']);
    unset($input['facts']['payer.siafi_electronic_collection']);
    $before = CanonicalJson::hash($legacy);
    $first = public139Evaluate($input, $legacy);
    expect($first['decision']['federal_withholdings']['irrf']['status'])->toBe('DISPENSED_THRESHOLD')
        ->and(CanonicalJson::hash(public139Evaluate($input, $legacy)))->toBe(CanonicalJson::hash($first))
        ->and(CanonicalJson::hash($legacy))->toBe($before);
    $bad = public139Knowledge();
    $bad['documents']['bundle_manifest']['public_payment_contract'] = 'UNKNOWN-PUBLIC-CONTRACT';
    $bad['content_hashes']['bundle_manifest'] = CanonicalJson::hash($bad['documents']['bundle_manifest']);
    expect(fn () => (new FiscalEngineFactory)->makeFromKnowledge($bad))->toThrow(RuntimeException::class, 'Unknown public document payment contract.');
});
