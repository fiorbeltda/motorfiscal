<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\NFSe\DereContributorXmlProjector;
use Illuminate\Support\Facades\Artisan;

it('replays the captured monthly calculation and rejects a changed body or a different selected bundle', function (string $case, string $expected): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $snapshot = (new RtcScenarioRunService(new FiscalEngineFactory))->execute($root,
        'bundles/consumption-phase4b-2026.10.02-17.json',
        $root.'/scenarios/consumption-phase4b-2026.10.02-17/financial-borrower-credit-loan-2026.json')->snapshot();
    if ($case === 'tampered') {
        $snapshot['result']['trace'][0]['competence_date'] = '2026-09-29';
    }
    $path = tempnam(sys_get_temp_dir(), 'dere-closing-snapshot-');
    expect($path)->not->toBeFalse();
    try {
        file_put_contents($path, json_encode($snapshot, JSON_THROW_ON_ERROR));
        $exit = Artisan::call('fiscal:dere:project-monthly-closing', ['snapshot' => $path,
            'month' => $root.'/scenarios/dere-local-2026.10.02-17/qualified-monthly-closing.json',
            '--bundle-manifest' => 'bundles/consumption-phase4b-2026.10.02-'.($case === 'different-bundle' ? '16' : '17').'.json']);
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        expect($result['status'])->toBe($expected)->and($exit)->toBe($case === 'valid' ? 0 : 1);
        if ($case === 'valid') {
            expect($result['reason'])->toBe('RTC_DERE_UNSIGNED_LOCAL_MONTHLY_CANDIDATES')
                ->and($result['production_ready'])->toBeFalse()->and($result['projection']['deductions']['deductions']['linked_financial_deductions'])->toBe(1);
        }
    } finally {
        unlink($path);
    }
})->with([['valid', 'ACTION_REQUIRED'], ['tampered', 'ERROR'], ['different-bundle', 'ERROR']]);

it('preserves an existing monthly candidate file instead of overwriting it', function (): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $snapshot = (new RtcScenarioRunService(new FiscalEngineFactory))->execute($root,
        'bundles/consumption-phase4b-2026.10.02-17.json',
        $root.'/scenarios/consumption-phase4b-2026.10.02-17/financial-borrower-credit-loan-2026.json')->snapshot();
    $path = tempnam(sys_get_temp_dir(), 'dere-closing-snapshot-');
    $candidate = tempnam(sys_get_temp_dir(), 'dere-closing-existing-');
    try {
        file_put_contents($path, json_encode($snapshot, JSON_THROW_ON_ERROR));
        file_put_contents($candidate, 'preserved candidate');
        $exit = Artisan::call('fiscal:dere:project-monthly-closing', ['snapshot' => $path,
            'month' => $root.'/scenarios/dere-local-2026.10.02-17/qualified-monthly-closing.json',
            '--candidate-output' => $candidate]);
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        expect($exit)->toBe(1)->and($result['error'])->toBe('Refusing to overwrite candidate XML.')
            ->and(file_get_contents($candidate))->toBe('preserved candidate');
    } finally {
        unlink($path);
        unlink($candidate);
    }
});

it('preflights every monthly event output before creating a new candidate', function (): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $snapshot = (new RtcScenarioRunService(new FiscalEngineFactory))->execute($root,
        'bundles/consumption-phase4b-2026.10.02-17.json',
        $root.'/scenarios/consumption-phase4b-2026.10.02-17/financial-borrower-credit-loan-2026.json')->snapshot();
    $path = tempnam(sys_get_temp_dir(), 'dere-closing-snapshot-');
    $candidate = tempnam(sys_get_temp_dir(), 'dere-closing-output-');
    unlink($candidate);
    $balance = $candidate.'.D1101.xml';
    try {
        file_put_contents($path, json_encode($snapshot, JSON_THROW_ON_ERROR));
        file_put_contents($balance, 'preserved balance');
        $exit = Artisan::call('fiscal:dere:project-monthly-closing', ['snapshot' => $path,
            'month' => $root.'/scenarios/dere-local-2026.10.02-17/qualified-monthly-closing.json',
            '--candidate-output' => $candidate]);
        expect($exit)->toBe(1)->and(file_exists($candidate))->toBeFalse()
            ->and(file_exists($candidate.'.D1121.xml'))->toBeFalse()->and(file_get_contents($balance))->toBe('preserved balance');
    } finally {
        unlink($path);
        unlink($balance);
    }
});

it('validates writes and preserves an optional D1001 using an isolated synthetic policy', function (): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $token = bin2hex(random_bytes(8));
    $manifestName = 'bundles/test-dere-contributor-'.$token.'.json';
    $catalogName = 'catalogs/test-dere-contributor-'.$token.'.json';
    $paths = [$root.'/'.$manifestName, $root.'/'.$catalogName];
    $snapshotPath = tempnam(sys_get_temp_dir(), 'dere-contributor-snapshot-');
    $scenarioPath = tempnam(sys_get_temp_dir(), 'dere-contributor-scenario-');
    $monthPath = tempnam(sys_get_temp_dir(), 'dere-contributor-month-');
    $candidate = sys_get_temp_dir().'/dere-contributor-'.$token.'.xml';
    $manifest = json_decode(file_get_contents($root.'/bundles/consumption-phase4b-2026.10.02-17.json'), true, 512, JSON_THROW_ON_ERROR);
    $catalog = json_decode(file_get_contents($root.'/catalogs/rtc-codes-2026.10.02-17.json'), true, 512, JSON_THROW_ON_ERROR);
    $manifest['bundle_id'] = 'SYNTHETIC-DERE-CONTRIBUTOR-TEST-'.$token;
    $manifest['resource_paths']['rtc_code_catalog'] = $catalogName;
    $catalog['dere_monthly_closing_projection']['contributor_projection_required'] = true;
    $catalog['dere_monthly_closing_projection']['contributor_catalogs'] = ['servFinanc' => ['qualified' => true,
        'source_hash' => str_repeat('a', 64), 'observed_on' => '2026-10-01', 'valid_from' => '2026-01-01',
        'codes' => ['01A' => ['valid_from' => '2026-01-01']]]];
    $catalog['dere_contributor_projection'] = ['contract' => DereContributorXmlProjector::CONTRACT, 'status' => 'ACTIVE',
        'published_at' => '2026-10-01', 'source_hash' => str_repeat('a', 64)];
    $manifest['content_hashes']['rtc_code_catalog'] = CanonicalJson::hash($catalog);
    $month = json_decode(file_get_contents($root.'/scenarios/dere-local-2026.10.02-17/qualified-monthly-closing.json'), true, 512, JSON_THROW_ON_ERROR);
    $month['contributor_identity'] = $month['identity'];
    $month['contributor'] = ['complete' => true, 'supplier_id' => $month['book']['supplier_id'],
        'registered_supplier_id' => $month['book']['supplier_id'], 'source_hash' => $month['prerequisites']['contributor_source_hash'],
        'period_end' => $month['book']['period_end'], 'valid_from' => '2026-01-01', 'observed_on' => '2026-10-01',
        'registration_qualified' => true, 'registration_status' => 'ACTIVE', 'registration_validity' => ['valid_from' => '2026-01-01'],
        'registration_source_hash' => str_repeat('b', 64), 'regimes_qualified' => true, 'regimes_source_hash' => str_repeat('c', 64),
        'tax_nature_qualified' => true, 'tax_nature_source_hash' => str_repeat('d', 64), 'dere_start_qualified' => true,
        'dere_start_source_hash' => str_repeat('e', 64), 'dere_start_on' => '2026-01-01',
        'regTribPrinc' => '1', 'regTribSecund' => [], 'indNatTrib' => '0', 'servFinanc' => ['tpAtividades' => ['01A']]];
    try {
        file_put_contents($paths[0], json_encode($manifest, JSON_THROW_ON_ERROR));
        file_put_contents($paths[1], json_encode($catalog, JSON_THROW_ON_ERROR));
        $scenario = json_decode(file_get_contents($root.'/scenarios/consumption-phase4b-2026.10.02-17/financial-borrower-credit-loan-2026.json'), true, 512, JSON_THROW_ON_ERROR);
        $scenario['rule_bundle_id'] = $manifest['bundle_id'];
        file_put_contents($scenarioPath, json_encode($scenario, JSON_THROW_ON_ERROR));
        $snapshot = (new RtcScenarioRunService(new FiscalEngineFactory))->execute($root, $manifestName, $scenarioPath)->snapshot();
        file_put_contents($snapshotPath, json_encode($snapshot, JSON_THROW_ON_ERROR));
        file_put_contents($monthPath, json_encode($month, JSON_THROW_ON_ERROR));
        $args = ['snapshot' => $snapshotPath, 'month' => $monthPath, '--bundle-manifest' => $manifestName, '--candidate-output' => $candidate];
        $exit = Artisan::call('fiscal:dere:project-monthly-closing', $args);
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        expect($exit)->toBe(0)->and(array_keys($result['event_validations']))->toContain('D1001')
            ->and($result['event_validations']['D1001']['reason'])->toBe('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE')
            ->and($result['projection']['contributor_projection'])->not->toHaveKey('xml')
            ->and($result['projection']['contributor_projection']['registration_performed'])->toBeFalse();
        $registrationPath = $candidate.'.D1001.xml';
        $registrationBytes = file_get_contents($registrationPath);
        expect(hash('sha256', $registrationBytes))->toBe($result['event_validations']['D1001']['xml_sha256']);
        foreach ([$candidate, $candidate.'.D1101.xml', $candidate.'.D1121.xml'] as $created) {
            unlink($created);
        }
        expect(Artisan::call('fiscal:dere:project-monthly-closing', $args))->toBe(1)
            ->and(file_exists($candidate))->toBeFalse()->and(file_exists($candidate.'.D1101.xml'))->toBeFalse()
            ->and(file_get_contents($registrationPath))->toBe($registrationBytes);
    } finally {
        foreach ([...$paths, $snapshotPath, $scenarioPath, $monthPath, $candidate, $candidate.'.D1001.xml', $candidate.'.D1101.xml', $candidate.'.D1121.xml'] as $path) {
            if (is_string($path) && file_exists($path)) {
                unlink($path);
            }
        }
    }
});
