<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioExecution;
use Illuminate\Support\Facades\Artisan;

function dps127Fixture(string $name): array
{
    $factory = new FiscalEngineFactory;
    $manifest = 'bundles/consumption-phase4b-2026.10.22-127-draft1.json';
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), $manifest);
    $path = resource_path('fiscal/scenarios/dps-local-2026.10.14-74-draft1/'.$name);
    $input = json_decode((string) file_get_contents($path.'-scenario.json'), true, 512, JSON_THROW_ON_ERROR);
    $input['scenario_id'] = 'DPS127-SYNTHETIC-'.$name;
    $input['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $input['catalog_versions'] = $knowledge['catalog_versions'];
    $input['knowledge_at'] = '2026-10-22T11:20:00+00:00';
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);
    $result = $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input);
    $identity = json_decode((string) file_get_contents($path.'-identity.json'), true, 512, JSON_THROW_ON_ERROR);
    $identity['issued_at'] = '2026-10-22T11:19:00+00:00';
    $identity['identity_evidence_id'] = 'SYNTHETIC-DPS127-'.$name;

    return [(new ScenarioExecution($loaded, $result, $knowledge))->snapshot(), $identity, $manifest];
}

it('refuses missing127 and inherited policies before calculation', function (string $field, string $reason): void {
    [$snapshot] = dps127Fixture('federal');
    $knowledge = $snapshot['knowledge'];
    $factory = new FiscalEngineFactory;
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($snapshot['input']);
    $documents = $knowledge['documents'];
    $codes = $documents['rtc_code_catalog'];
    unset($codes[$field]);
    $result = (new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']), $codes,
        new TransitionCatalog($documents['rtc_transition_catalog'])))->evaluate($loaded->input->context->facts,
            $loaded->input->context->competenceDate, $loaded->input->context->knowledgeAt);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toContain($reason);
})->with([
    ['dps_layout_policy127', 'RTC_DPS127_SOURCE_POLICY_NOT_BOUND'],
    ['cindop_reference126', 'RTC_CINDOP126_SOURCE_POLICY_NOT_BOUND'],
    ['credit_input_policy122', 'RTC_CREDIT122_SOURCE_POLICY_NOT_BOUND'],
    ['material_input_policy119', 'RTC_INPUT119_SOURCE_POLICY_NOT_BOUND'],
]);

beforeEach(function (): void {
    $this->dps127Directory = sys_get_temp_dir().'/dps127-'.bin2hex(random_bytes(8));
    mkdir($this->dps127Directory);
});

afterEach(function (): void {
    foreach (glob($this->dps127Directory.'/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($this->dps127Directory);
});

it('projects and validates the current observed schema through the native command', function (string $name): void {
    [$snapshot, $identity, $manifest] = dps127Fixture($name);
    expect($snapshot['result']['status'])->toBe('VALID_WITH_WARNINGS');
    $fields = $snapshot['result']['decision']['dps_projection']['rtc']['issuer_supplied'];
    expect($fields)->toHaveKeys(['finNFSe', 'IBSCBS.valores.trib.CST', 'IBSCBS.valores.trib.cClassTrib'])
        ->and(array_keys($fields))->not->toContain('IBSCBS.finNFSe', 'IBSCBS.valores.trib.gIBSCBS.CST');
    file_put_contents($this->dps127Directory.'/snapshot.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
    file_put_contents($this->dps127Directory.'/identity.json', json_encode($identity, JSON_THROW_ON_ERROR));
    $arguments = ['snapshot' => $this->dps127Directory.'/snapshot.json', 'identity' => $this->dps127Directory.'/identity.json', '--bundle-manifest' => $manifest];
    $exit = Artisan::call('fiscal:dps:project-xml', [...$arguments, '--output' => $this->dps127Directory.'/first.xml']);
    $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($report)->toHaveKey('schema_validation');
    expect($report['schema_validation']['errors'])->toBe([]);
    expect($exit)->toBe(0)->and($report['schema_validation']['status'])->toBe('VALID')
        ->and($report['schema_validation']['schema_sha256'])->toBe('25fdcecc651a67a216496ab10d7566631e6ed10f721e37d62c7845dadb0ea267')
        ->and($report['schema_selection']['remote_deployment_verified'])->toBeFalse()
        ->and($report['production_ready'])->toBeFalse();
    $exit = Artisan::call('fiscal:dps:project-xml', [...$arguments, '--output' => $this->dps127Directory.'/second.xml']);
    expect($exit)->toBe(0)->and(file_get_contents($this->dps127Directory.'/first.xml'))->toBe(file_get_contents($this->dps127Directory.'/second.xml'));
    $document = new DOMDocument;
    $document->loadXML((string) file_get_contents($this->dps127Directory.'/first.xml'));
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('d', 'http://www.sped.fazenda.gov.br/nfse');
    expect($xpath->evaluate('count(/d:DPS/d:infDPS/d:finNFSe)'))->toBe(1.0)
        ->and($xpath->evaluate('count(/d:DPS/d:infDPS/d:IBSCBS/d:finNFSe)'))->toBe(0.0)
        ->and($xpath->evaluate('count(/d:DPS/d:infDPS/d:IBSCBS/d:valores/d:trib/d:CST)'))->toBe(1.0)
        ->and($xpath->evaluate('count(/d:DPS/d:infDPS/d:IBSCBS/d:valores/d:trib/d:gIBSCBS/d:CST)'))->toBe(0.0);
})->with(['federal', 'education', 'presential']);

it('refuses production changed snapshots and conflicting identities before writing XML', function (string $case, string $reason): void {
    [$snapshot, $identity, $manifest] = dps127Fixture('federal');
    switch ($case) {
        case 'production': $identity['environment'] = '1';
            break;
        case 'future': $identity['issued_at'] = '2026-10-22T11:20:01+00:00';
            break;
        case 'old': $identity['issued_at'] = '2026-10-22T11:13:42+00:00';
            break;
        case 'series': $identity['series'] = '90000';
            break;
        case 'supplier': $identity['supplier_cnpj'] = '33333333000191';
            break;
        case 'tampered': $snapshot['result']['decision']['dps_projection']['rtc']['issuer_supplied']['finNFSe'] = '1';
            break;
    }
    file_put_contents($this->dps127Directory.'/snapshot.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
    file_put_contents($this->dps127Directory.'/identity.json', json_encode($identity, JSON_THROW_ON_ERROR));
    $exit = Artisan::call('fiscal:dps:project-xml', ['snapshot' => $this->dps127Directory.'/snapshot.json',
        'identity' => $this->dps127Directory.'/identity.json', '--bundle-manifest' => $manifest,
        '--output' => $this->dps127Directory.'/forbidden.xml', '--candidate-output' => $this->dps127Directory.'/candidate.xml']);
    $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($exit)->toBe(1)->and($case === 'tampered' ? $report['error'] : $report['reason'])->toBe($reason)
        ->and(file_exists($this->dps127Directory.'/forbidden.xml'))->toBeFalse()
        ->and(file_exists($this->dps127Directory.'/candidate.xml'))->toBeFalse();
})->with([
    ['production', 'RTC_DPS127_PRODUCTION_ENVIRONMENT_FORBIDDEN'], ['future', 'RTC_DPS127_ISSUANCE_AFTER_KNOWLEDGE'],
    ['old', 'RTC_DPS127_ARTIFACT_NOT_YET_OBSERVED'], ['series', 'RTC_DPS127_SERIES_OUTSIDE_RESTRICTED_SCHEMA'],
    ['supplier', 'RTC_DPS_SUPPLIER_MISMATCH'], ['tampered', 'Snapshot decision fails deterministic replay.'],
]);
