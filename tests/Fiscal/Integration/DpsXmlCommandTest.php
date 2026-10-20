<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('exposes schema-only validation through the local Artisan command', function (): void {
    $exit = Artisan::call('fiscal:dps:validate-xml', ['path' => base_path('tests/Fixtures/dps-production-schema-valid.xml'),
        '--bundle-manifest' => 'bundles/consumption-phase4b-2026.09.28-18.json']);
    $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    expect($exit)->toBe(0)
        ->and($report['scope'])->toBe('XML_SCHEMA_ONLY_NO_SIGNATURE_OR_TRANSMISSION')
        ->and($report['bundle_id'])->toBe('CONSUMPTION-PHASE4B-2026.09.28-18')
        ->and($report['status'])->toBe('VALID');
});
