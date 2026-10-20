<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioRunService;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->dpsDirectory = sys_get_temp_dir().'/dps-command-'.bin2hex(random_bytes(8));
    mkdir($this->dpsDirectory);
    $runner = new ScenarioRunService(new FiscalEngineFactory, resource_path('fiscal'),
        bundleManifestPath: 'bundles/consumption-phase4b-2026.10.01-2.json');
    $this->dpsSnapshot = $runner->snapshot(resource_path('fiscal/scenarios/consumption-phase4b-2026.10.01-2/dps-standard-mandatory-2026.json'));
    $this->dpsIdentity = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/dps-local-2026.10.01-2/standard-identity.json')), true, 512, JSON_THROW_ON_ERROR);
    file_put_contents($this->dpsDirectory.'/snapshot.json', json_encode($this->dpsSnapshot, JSON_THROW_ON_ERROR));
    file_put_contents($this->dpsDirectory.'/identity.json', json_encode($this->dpsIdentity, JSON_THROW_ON_ERROR));
});

afterEach(function (): void {
    foreach (glob($this->dpsDirectory.'/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($this->dpsDirectory);
});

it('produces an auditable local candidate and distinguishes the official series conflict', function (): void {
    $exit = Artisan::call('fiscal:dps:project-xml', ['--bundle-manifest' => 'bundles/consumption-phase4b-2026.10.01-2.json', 'snapshot' => $this->dpsDirectory.'/snapshot.json',
        'identity' => $this->dpsDirectory.'/identity.json', '--candidate-output' => $this->dpsDirectory.'/candidate.xml',
        '--report' => $this->dpsDirectory.'/report.json', '--output' => $this->dpsDirectory.'/validated.xml']);
    $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($exit)->toBe(1)->and($report['status'])->toBe('ACTION_REQUIRED')
        ->and($report['reason'])->toBe('RTC_DPS_OFFICIAL_SERIE_PATTERN_CONFLICT')
        ->and($report['projection']['status'])->toBe('PROJECTED')
        ->and($report['schema_validation']['status'])->toBe('INVALID')
        ->and($report['schema_validation']['errors'])->toHaveCount(1)
        ->and(is_file($this->dpsDirectory.'/candidate.xml'))->toBeTrue()
        ->and(is_file($this->dpsDirectory.'/validated.xml'))->toBeFalse()
        ->and($report['production_ready'])->toBeFalse()
        ->and($report['decision_hash'])->toBe($this->dpsSnapshot['result']['decision_hash']);
    $saved = json_decode((string) file_get_contents($this->dpsDirectory.'/report.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($saved)->toBe($report)->and(hash_file('sha256', $this->dpsDirectory.'/candidate.xml'))->toBe($report['schema_validation']['xml_sha256']);
});

it('rejects a changed decision even when its claimed bundle is intact', function (): void {
    $this->dpsSnapshot['result']['decision']['dps_projection']['values']['vServ'] = '1.00';
    file_put_contents($this->dpsDirectory.'/snapshot.json', json_encode($this->dpsSnapshot, JSON_THROW_ON_ERROR));
    $exit = Artisan::call('fiscal:dps:project-xml', ['--bundle-manifest' => 'bundles/consumption-phase4b-2026.10.01-2.json', 'snapshot' => $this->dpsDirectory.'/snapshot.json', 'identity' => $this->dpsDirectory.'/identity.json']);
    $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($exit)->toBe(1)->and($report['error'])->toBe('Snapshot decision fails deterministic replay.');
});

it('protects existing local candidate artifacts from overwrite', function (): void {
    file_put_contents($this->dpsDirectory.'/candidate.xml', 'preserved-user-content');
    $exit = Artisan::call('fiscal:dps:project-xml', ['--bundle-manifest' => 'bundles/consumption-phase4b-2026.10.01-2.json', 'snapshot' => $this->dpsDirectory.'/snapshot.json',
        'identity' => $this->dpsDirectory.'/identity.json', '--candidate-output' => $this->dpsDirectory.'/candidate.xml']);
    expect($exit)->toBe(1)->and(file_get_contents($this->dpsDirectory.'/candidate.xml'))->toBe('preserved-user-content');
});

it('requires DPS competence to match the replayed fiscal decision', function (): void {
    $this->dpsIdentity['competence_date'] = '2026-09-30';
    file_put_contents($this->dpsDirectory.'/identity.json', json_encode($this->dpsIdentity, JSON_THROW_ON_ERROR));
    $exit = Artisan::call('fiscal:dps:project-xml', ['--bundle-manifest' => 'bundles/consumption-phase4b-2026.10.01-2.json', 'snapshot' => $this->dpsDirectory.'/snapshot.json', 'identity' => $this->dpsDirectory.'/identity.json']);
    $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($exit)->toBe(1)->and($report['error'])->toBe('DPS competence differs from the replayed decision.');
});

it('loads a complete integrated question contract with unique fact keys', function (): void {
    $knowledge = (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.01-2.json');
    $keys = array_column($knowledge['documents']['questions'], 'fact_key');
    expect(count($keys))->toBe(count(array_unique($keys)))
        ->and($this->dpsSnapshot['result']['status'])->toBe('VALID_WITH_WARNINGS')
        ->and($this->dpsSnapshot['result']['decision']['consumption_reform']['decision']['document']['obligation']['required_by_schedule'])->toBeTrue();
});

it('projects real integrated ISS 4A IBS CBS decisions with their separate calculation amounts', function (): void {
    $runner = new ScenarioRunService(new FiscalEngineFactory, resource_path('fiscal'),
        bundleManifestPath: 'bundles/consumption-phase4b-2026.10.01-2.json');
    $snapshot = $runner->snapshot(resource_path('fiscal/scenarios/consumption-integrated-2026.10.01-2/dps-integrated-iss-4a-rtc-2026.json'));
    expect($snapshot['result']['decision']['iss']['calculation']['tax_amount'])->toBe('20.00')
        ->and($snapshot['result']['decision']['federal_withholdings']['irrf']['amount'])->toBe('15.00')
        ->and($snapshot['result']['decision']['federal_withholdings']['pcc']['total_withholding'])->toBe('46.50')
        ->and($snapshot['result']['decision']['consumption_reform']['decision']['ibs']['nominal_amount'])->toBe('1.00')
        ->and($snapshot['result']['decision']['consumption_reform']['decision']['cbs']['nominal_amount'])->toBe('9.00');
    file_put_contents($this->dpsDirectory.'/snapshot.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
    copy(resource_path('fiscal/scenarios/dps-local-2026.10.01-2/federal-identity.json'), $this->dpsDirectory.'/identity.json');
    Artisan::call('fiscal:dps:project-xml', ['--bundle-manifest' => 'bundles/consumption-phase4b-2026.10.01-2.json', 'snapshot' => $this->dpsDirectory.'/snapshot.json',
        'identity' => $this->dpsDirectory.'/identity.json', '--candidate-output' => $this->dpsDirectory.'/candidate.xml']);
    $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    $xml = (string) file_get_contents($this->dpsDirectory.'/candidate.xml');
    expect($report['reason'])->toBe('RTC_DPS_OFFICIAL_SERIE_PATTERN_CONFLICT')
        ->and($xml)->toContain('<vRetIRRF>15.00</vRetIRRF>', '<vRetCSLL>10.00</vRetCSLL>', '<tpRetPisCofins>3</tpRetPisCofins>')
        ->and($xml)->not->toContain('<vPis>6.50</vPis>', '<vCofins>30.00</vCofins>');
});
