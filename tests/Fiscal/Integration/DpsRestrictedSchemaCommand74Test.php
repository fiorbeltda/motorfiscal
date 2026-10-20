<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioRunService;
use Illuminate\Support\Facades\Artisan;

function schema74CommandFixture(string $name): array
{
    $manifest = 'bundles/consumption-phase4b-2026.10.14-74-draft1.json';
    $fixtures = resource_path('fiscal/scenarios/dps-local-2026.10.14-74-draft1');
    $runner = new ScenarioRunService(new FiscalEngineFactory, resource_path('fiscal'), bundleManifestPath: $manifest);
    $snapshot = $runner->snapshot($fixtures.'/'.$name.'-scenario.json');
    $identity = json_decode((string) file_get_contents($fixtures.'/'.$name.'-identity.json'), true, 512, JSON_THROW_ON_ERROR);

    return [$snapshot, $identity, $manifest];
}

beforeEach(function (): void {
    $this->schema74Directory = sys_get_temp_dir().'/dps74-'.bin2hex(random_bytes(8));
    mkdir($this->schema74Directory);
});

afterEach(function (): void {
    foreach (glob($this->schema74Directory.'/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($this->schema74Directory);
});

it('validates the exact restricted source through the integrated CLI and preserves deterministic XML', function (string $name, string $series): void {
    [$snapshot, $identity, $manifest] = schema74CommandFixture($name);
    $identity['series'] = $series;
    file_put_contents($this->schema74Directory.'/snapshot.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
    file_put_contents($this->schema74Directory.'/identity.json', json_encode($identity, JSON_THROW_ON_ERROR));
    $arguments = ['snapshot' => $this->schema74Directory.'/snapshot.json',
        'identity' => $this->schema74Directory.'/identity.json', '--bundle-manifest' => $manifest];
    $exit = Artisan::call('fiscal:dps:project-xml', [...$arguments, '--output' => $this->schema74Directory.'/first.xml']);
    $first = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($exit)->toBe(0)->and($first['status'])->toBe('VALID')
        ->and($first['schema_validation']['status'])->toBe('VALID')
        ->and($first['schema_selection']['environment'])->toBe('2')
        ->and($first['schema_selection']['remote_deployment_verified'])->toBeFalse()
        ->and($first['schema_selection']['document_obligation_qualified'])->toBeFalse()
        ->and($first['production_ready'])->toBeFalse()
        ->and($first['decision_hash'])->toBe($snapshot['result']['decision_hash']);
    $exit = Artisan::call('fiscal:dps:project-xml', [...$arguments, '--output' => $this->schema74Directory.'/second.xml']);
    expect($exit)->toBe(0)
        ->and(file_get_contents($this->schema74Directory.'/first.xml'))->toBe(file_get_contents($this->schema74Directory.'/second.xml'))
        ->and(hash_file('sha256', $this->schema74Directory.'/first.xml'))->toBe($first['schema_validation']['xml_sha256']);
})->with(['federal', 'education', 'presential'])->with(['1', '00001', '89999']);

it('refuses prohibited environment series and issuance without writing XML', function (string $name, string $case, string $reason): void {
    [$snapshot, $identity, $manifest] = schema74CommandFixture($name);
    $identity = match ($case) {
        'production' => [...$identity, 'environment' => '1'],
        'series' => [...$identity, 'series' => '90000'],
        'old' => [...$identity, 'issued_at' => '2026-10-01T12:00:00Z'],
        'future' => [...$identity, 'issued_at' => (new DateTimeImmutable($snapshot['input']['knowledge_at']))->modify('+1 second')->format(DateTimeInterface::ATOM)],
    };
    file_put_contents($this->schema74Directory.'/snapshot.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
    file_put_contents($this->schema74Directory.'/identity.json', json_encode($identity, JSON_THROW_ON_ERROR));
    $exit = Artisan::call('fiscal:dps:project-xml', ['snapshot' => $this->schema74Directory.'/snapshot.json',
        'identity' => $this->schema74Directory.'/identity.json', '--bundle-manifest' => $manifest,
        '--output' => $this->schema74Directory.'/forbidden.xml', '--candidate-output' => $this->schema74Directory.'/candidate.xml']);
    $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($exit)->toBe(1)->and($report['reason'])->toBe('RTC_DPS_RESTRICTED_SCHEMA_'.$reason)
        ->and(file_exists($this->schema74Directory.'/forbidden.xml'))->toBeFalse()
        ->and(file_exists($this->schema74Directory.'/candidate.xml'))->toBeFalse();
})->with(['federal', 'education', 'presential'])->with([
    ['production', 'PRODUCTION_ENVIRONMENT_FORBIDDEN'],
    ['series', 'SERIES_OUTSIDE_RESTRICTED_SCHEMA'],
    ['old', 'ARTIFACT_NOT_YET_OBSERVED'],
    ['future', 'ISSUANCE_AFTER_KNOWLEDGE'],
]);
