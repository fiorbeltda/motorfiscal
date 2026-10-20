<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioRunService;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->foreign76Directory = sys_get_temp_dir().'/foreign76-'.bin2hex(random_bytes(8));
    mkdir($this->foreign76Directory);
});

afterEach(function (): void {
    foreach (glob($this->foreign76Directory.'/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($this->foreign76Directory);
});

function foreign76CommandFixture(string $case): array
{
    $fixtures = resource_path('fiscal/scenarios/dps-foreign-acquirer-2026.10.15-76-draft1');
    $runner = new ScenarioRunService(new FiscalEngineFactory, resource_path('fiscal'),
        bundleManifestPath: 'bundles/consumption-phase4b-2026.10.15-76-draft1.json');

    return [$runner->snapshot($fixtures.'/'.$case.'-scenario.json'),
        json_decode((string) file_get_contents($fixtures.'/'.$case.'-identity.json'), true, 512, JSON_THROW_ON_ERROR)];
}

it('connects replayed domestic tax decisions to foreign taker and COMEX candidates only in the opt-in bundle', function (string $case): void {
    [$snapshot, $identity] = foreign76CommandFixture($case);
    expect($snapshot['result']['decision']['iss']['calculation']['tax_amount'])->toBe('20.00')
        ->and($snapshot['result']['decision']['consumption_reform']['decision']['ibs']['nominal_amount'])->toBe('1.00')
        ->and($snapshot['result']['decision']['consumption_reform']['decision']['cbs']['nominal_amount'])->toBe('9.00');
    file_put_contents($this->foreign76Directory.'/snapshot.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
    file_put_contents($this->foreign76Directory.'/identity.json', json_encode($identity, JSON_THROW_ON_ERROR));
    $arguments = ['snapshot' => $this->foreign76Directory.'/snapshot.json', 'identity' => $this->foreign76Directory.'/identity.json',
        '--bundle-manifest' => 'bundles/consumption-phase4b-2026.10.15-76-draft1.json',
        '--output' => $this->foreign76Directory.'/candidate.xml', '--report' => $this->foreign76Directory.'/report.json'];
    $exit = Artisan::call('fiscal:dps:project-xml', $arguments);
    $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($exit)->toBe(0)->and($report['status'])->toBe('VALID')
        ->and($report['schema_validation']['status'])->toBe('VALID')
        ->and($report['decision_hash'])->toBe($snapshot['result']['decision_hash'])
        ->and(hash_file('sha256', $this->foreign76Directory.'/candidate.xml'))->toBe($report['schema_validation']['xml_sha256'])
        ->and($report['projection']['foreign_acquirer_binding']['currency_competence_validity_proven'])->toBeFalse()
        ->and($report['projection']['foreign_acquirer_binding']['document_obligation_complete'])->toBeFalse()
        ->and($report['production_ready'])->toBeFalse();
    $arguments['--output'] = $this->foreign76Directory.'/repeat.xml';
    unset($arguments['--report']);
    expect(Artisan::call('fiscal:dps:project-xml', $arguments))->toBe(0)
        ->and(file_get_contents($this->foreign76Directory.'/repeat.xml'))->toBe(file_get_contents($this->foreign76Directory.'/candidate.xml'));
})->with(['nif', 'no-nif-1', 'no-nif-2']);

it('rejects source time party environment and replay substitution without creating XML', function (string $case): void {
    [$snapshot, $identity] = foreign76CommandFixture('nif');
    match ($case) {
        'party' => $identity['foreign_acquirer_id'] = 'OTHER',
        'production' => $identity['environment'] = '1',
        'future-mode' => $identity['foreign_trade']['mdPrestacao'] = '5',
        'currency' => $identity['foreign_trade']['tpMoeda'] = '220',
        'source-time' => $identity['issued_at'] = '2026-10-14T15:00:00+00:00',
        'replay' => $snapshot['result']['decision']['dps_projection']['values']['vServ'] = '1.00',
    };
    file_put_contents($this->foreign76Directory.'/snapshot.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
    file_put_contents($this->foreign76Directory.'/identity.json', json_encode($identity, JSON_THROW_ON_ERROR));
    $exit = Artisan::call('fiscal:dps:project-xml', ['snapshot' => $this->foreign76Directory.'/snapshot.json',
        'identity' => $this->foreign76Directory.'/identity.json', '--bundle-manifest' => 'bundles/consumption-phase4b-2026.10.15-76-draft1.json',
        '--output' => $this->foreign76Directory.'/forbidden.xml']);
    expect($exit)->toBe(1)->and(file_exists($this->foreign76Directory.'/forbidden.xml'))->toBeFalse();
})->with(['party', 'production', 'future-mode', 'currency', 'source-time', 'replay']);
