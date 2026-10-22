<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioExecution;
use Illuminate\Support\Facades\Artisan;

function foreign128Fixture(string $case): array
{
    $factory = new FiscalEngineFactory;
    $manifest = 'bundles/consumption-phase4b-2026.10.22-128-draft1.json';
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), $manifest);
    $path = resource_path('fiscal/scenarios/dps-foreign-acquirer-2026.10.15-76-draft1/'.$case);
    $input = json_decode((string) file_get_contents($path.'-scenario.json'), true, 512, JSON_THROW_ON_ERROR);
    $input['scenario_id'] = 'FOREIGN128-SYNTHETIC-'.$case;
    $input['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $input['catalog_versions'] = $knowledge['catalog_versions'];
    $input['knowledge_at'] = '2026-10-22T11:38:00+00:00';
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);
    $result = $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input);
    $identity = json_decode((string) file_get_contents($path.'-identity.json'), true, 512, JSON_THROW_ON_ERROR);
    $identity['issued_at'] = '2026-10-22T11:37:00+00:00';
    $identity['identity_evidence_id'] = 'SYNTHETIC-FOREIGN128-'.$case;

    return [(new ScenarioExecution($scenario, $result, $knowledge))->snapshot(), $identity, $manifest];
}

it('cannot omit128 or inherited guards from the new catalog', function (string $field, string $reason): void {
    [$snapshot] = foreign128Fixture('nif');
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
    ['dps_foreign_policy128', 'RTC_DPS128_SOURCE_POLICY_NOT_BOUND'], ['dps_layout_policy127', 'RTC_DPS127_SOURCE_POLICY_NOT_BOUND'],
    ['cindop_reference126', 'RTC_CINDOP126_SOURCE_POLICY_NOT_BOUND'], ['credit_input_policy122', 'RTC_CREDIT122_SOURCE_POLICY_NOT_BOUND'],
    ['material_input_policy119', 'RTC_INPUT119_SOURCE_POLICY_NOT_BOUND'],
]);

beforeEach(function (): void {
    $this->foreign128Directory = sys_get_temp_dir().'/foreign128-'.bin2hex(random_bytes(8));
    mkdir($this->foreign128Directory);
});

afterEach(function (): void {
    foreach (glob($this->foreign128Directory.'/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($this->foreign128Directory);
});

it('projects foreign acquirer and domestic recipient with COMEX in the current source', function (string $case): void {
    [$snapshot, $identity, $manifest] = foreign128Fixture($case);
    expect($snapshot['result']['status'])->toBe('VALID_WITH_WARNINGS');
    $decision = $snapshot['result']['decision'];
    expect($decision['iss']['calculation']['tax_amount'])->toBe('20.00')
        ->and($decision['consumption_reform']['decision']['ibs']['nominal_amount'])->toBe('1.00')
        ->and($decision['consumption_reform']['decision']['cbs']['nominal_amount'])->toBe('9.00');
    file_put_contents($this->foreign128Directory.'/snapshot.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
    file_put_contents($this->foreign128Directory.'/identity.json', json_encode($identity, JSON_THROW_ON_ERROR));
    $arguments = ['snapshot' => $this->foreign128Directory.'/snapshot.json', 'identity' => $this->foreign128Directory.'/identity.json', '--bundle-manifest' => $manifest];
    $exit = Artisan::call('fiscal:dps:project-xml', [...$arguments, '--output' => $this->foreign128Directory.'/first.xml']);
    $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($report)->toHaveKey('schema_validation');
    expect($report['schema_validation']['errors'])->toBe([])->and($exit)->toBe(0)
        ->and($report['projection']['foreign_acquirer_binding']['document_obligation_complete'])->toBeFalse()
        ->and($report['projection']['foreign_acquirer_binding']['comext_mode_fiscally_qualified'])->toBeFalse()
        ->and($report['schema_validation']['schema_sha256'])->toBe('25fdcecc651a67a216496ab10d7566631e6ed10f721e37d62c7845dadb0ea267');
    $exit = Artisan::call('fiscal:dps:project-xml', [...$arguments, '--output' => $this->foreign128Directory.'/second.xml']);
    expect($exit)->toBe(0)->and(file_get_contents($this->foreign128Directory.'/first.xml'))->toBe(file_get_contents($this->foreign128Directory.'/second.xml'));
    $xml = new DOMDocument;
    $xml->loadXML((string) file_get_contents($this->foreign128Directory.'/first.xml'));
    $xpath = new DOMXPath($xml);
    $xpath->registerNamespace('d', 'http://www.sped.fazenda.gov.br/nfse');
    expect($xpath->evaluate('string(/d:DPS/d:infDPS/d:toma/d:end/d:endExt/d:cPais)'))->toBe('US')
        ->and($xpath->evaluate('string(/d:DPS/d:infDPS/d:serv/d:comExt/d:tpMoeda)'))->toBe('790')
        ->and($xpath->evaluate('string(/d:DPS/d:infDPS/d:IBSCBS/d:dest/d:CNPJ)'))->toBe('22222222000191')
        ->and($xpath->evaluate('count(/d:DPS/d:infDPS/d:finNFSe)'))->toBe(1.0);
})->with(['nif', 'no-nif-1', 'no-nif-2']);

it('refuses inconsistent international evidence without writing an XML', function (string $case): void {
    [$snapshot, $identity, $manifest] = foreign128Fixture('nif');
    switch ($case) {
        case 'production': $identity['environment'] = '1';
            break;
        case 'identity': $identity['foreign_acquirer_id'] = 'OTHER';
            break;
        case 'country': $identity['foreign_acquirer']['country'] = 'BR';
            break;
        case 'choice': $identity['foreign_acquirer']['no_nif_reason'] = '1';
            break;
        case 'missing evidence': unset($identity['foreign_acquirer']['address_evidence_id']);
            break;
        case 'extra party': $identity['foreign_acquirer']['unexpected'] = 'X';
            break;
        case 'mode': $identity['foreign_trade']['mdPrestacao'] = '5';
            break;
        case 'currency': $identity['foreign_trade']['tpMoeda'] = '220';
            break;
        case 'amount': $identity['foreign_trade']['vServMoeda'] = '999.00';
            break;
        case 'amount type': $identity['foreign_trade']['vServMoeda'] = 1000;
            break;
        case 'missing COMEX': unset($identity['foreign_trade']['vincPrest']);
            break;
        case 'extra COMEX': $identity['foreign_trade']['new_field'] = 'X';
            break;
        case 'missing DI': $identity['foreign_trade']['movTempBens'] = '2';
            break;
        case 'extra DI': $identity['foreign_trade']['nDI'] = 'DI-1';
            break;
        case 'early': $identity['issued_at'] = '2026-10-22T11:13:42+00:00';
            break;
        case 'future': $identity['issued_at'] = '2026-10-22T11:39:00+00:00';
            break;
        case 'replay': $snapshot['result']['decision']['dps_projection']['values']['vServ'] = '1.00';
            break;
    }
    file_put_contents($this->foreign128Directory.'/snapshot.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
    file_put_contents($this->foreign128Directory.'/identity.json', json_encode($identity, JSON_THROW_ON_ERROR));
    $exit = Artisan::call('fiscal:dps:project-xml', ['snapshot' => $this->foreign128Directory.'/snapshot.json',
        'identity' => $this->foreign128Directory.'/identity.json', '--bundle-manifest' => $manifest,
        '--output' => $this->foreign128Directory.'/forbidden.xml', '--candidate-output' => $this->foreign128Directory.'/candidate.xml']);
    $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($exit)->toBe(1)->and($report['status'])->toBeIn(['INVALID', 'ACTION_REQUIRED', 'ERROR'])
        ->and(file_exists($this->foreign128Directory.'/forbidden.xml'))->toBeFalse()
        ->and(file_exists($this->foreign128Directory.'/candidate.xml'))->toBeFalse();
})->with(['production', 'identity', 'country', 'choice', 'missing evidence', 'extra party', 'mode', 'currency', 'amount', 'amount type',
    'missing COMEX', 'extra COMEX', 'missing DI', 'extra DI', 'early', 'future', 'replay']);
