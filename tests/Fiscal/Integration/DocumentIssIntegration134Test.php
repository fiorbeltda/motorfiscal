<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CIndOpReference126;
use App\Modules\Fiscal\ConsumptionReform\CreditInputPolicy122;
use App\Modules\Fiscal\ConsumptionReform\DocumentIssIntegration134;
use App\Modules\Fiscal\ConsumptionReform\DocumentSchedule131;
use App\Modules\Fiscal\ConsumptionReform\DpsForeignPolicy128;
use App\Modules\Fiscal\ConsumptionReform\DpsLayoutPolicy127;
use App\Modules\Fiscal\ConsumptionReform\ImportResponsibility129;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DpsDocumentValues134;
use Illuminate\Support\Facades\Artisan;

require_once dirname(__DIR__, 3).'/scripts/phase4b_iss134_fixture.php';

it('integrates fresh document ISS in both runners without rewriting RTC or the operation-level ISS', function (string $runner): void {
    $c = phase4bIss134Fixture();
    $factory = new FiscalEngineFactory;
    $loaded = $factory->scenarioLoaderFromKnowledge($c['knowledge'])->fromArray($c['scenario']);
    $r = $runner === 'rtc' ? (new RtcScenarioRunService($factory))->evaluate($c['knowledge'], $loaded)->toArray()
        : $c['snapshot']['result'];
    $assessment = $runner === 'rtc' ? $r : array_column($r['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    $stage = array_column($assessment['trace'], null, 'stage')['PARTIAL_EXPORT_DOCUMENT_ISS134'];
    expect($stage['status'])->toBe('LOCAL_ISS_DOCUMENT_BASES_CALCULATED')
        ->and($stage['documents'][0]['iss_base_brl'])->toBeNull()
        ->and($stage['documents'][1]['iss_base_brl'])->toBe('1000.00')->and($stage['documents'][1]['iss_tax_brl'])->toBe('29.00')
        ->and($stage['proof_carrier_excluded_only'])->toBe(DocumentIssIntegration134::PROOFS)
        ->and($assessment['reason_codes'])->toBe(['RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN']);
    $nominal = array_column($assessment['trace'], null, 'stage')['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION'];
    expect($nominal['domestic_taxable_base_brl'])->toBe('600.00');
    if ($runner === 'integrated') {
        $legacy = array_column($r['trace'], 'payload', 'type')['ISS_CALCULATION'];
        $priorKnowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.22-131-draft2.json');
        $priorInput = $factory->scenarioLoaderFromKnowledge($priorKnowledge)->fromArray($c['component']['scenario']);
        $priorResult = $factory->makeFromKnowledge($priorKnowledge)->evaluate($priorInput->input)->toArray();
        expect($legacy)->toBe(array_column($priorResult['trace'], 'payload', 'type')['ISS_CALCULATION'])
            ->and($legacy['tax_amount'])->toBe('20.00');
    }
})->with(['rtc', 'integrated']);

it('asks for missing typed proof records in both runners', function (string $runner): void {
    $c = phase4bIss134Fixture();
    unset($c['scenario']['facts'][DocumentIssIntegration134::PROOFS]);
    $factory = new FiscalEngineFactory;
    $loaded = $factory->scenarioLoaderFromKnowledge($c['knowledge'])->fromArray($c['scenario']);
    $r = $runner === 'rtc' ? (new RtcScenarioRunService($factory))->evaluate($c['knowledge'], $loaded)->toArray()
        : $factory->makeFromKnowledge($c['knowledge'])->evaluate($loaded->input)->toArray();
    expect($r['reason_codes'])->toBe(['RTC_ISS134_TWO_TAX_CONTEXT_PROOFS_REQUIRED'])
        ->and(array_column($r['questions'], 'fact_key'))->toContain(DocumentIssIntegration134::PROOFS);
})->with(['rtc', 'integrated']);

it('requires the134 policy by version even if its marker is deleted', function (): void {
    $c = phase4bIss134Fixture();
    $codes = $c['knowledge']['documents']['rtc_code_catalog'];
    unset($codes['document_iss_policy134']);
    expect(DocumentIssIntegration134::required($codes))->toBeTrue()
        ->and((new DocumentIssIntegration134)->qualifyPolicy($codes, new DateTimeImmutable('2026-10-22T18:40:00+00:00'))['reason'])
        ->toBe('RTC_ISS134_SOURCE_POLICY_NOT_BOUND');
});

it('validates values fragments after full replay and keeps calculated amounts outside DPS input fields', function (bool $issuerRate, bool $reverse): void {
    $c = phase4bIss134Fixture(reverseResults: $reverse);
    $options = phase4bIss134Options($c['snapshot'], $issuerRate);
    $dir = sys_get_temp_dir().'/iss134-'.bin2hex(random_bytes(6));
    mkdir($dir);
    try {
        file_put_contents($dir.'/snapshot.json', json_encode($c['snapshot'], JSON_THROW_ON_ERROR));
        file_put_contents($dir.'/options.json', json_encode($options, JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:dps:project-document-values', ['snapshot' => $dir.'/snapshot.json',
            'options' => $dir.'/options.json', '--output-dir' => $dir.'/projected']))->toBe(0);
        $r = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        expect($r['status'])->toBe('LOCAL_DOCUMENT_VALUES_VALIDATED')->and($r['snapshot_replay_matches'])->toBeTrue()
            ->and($r['complete_dps_built'])->toBeFalse()->and($r['document_obligation_complete'])->toBeFalse();
        foreach ($r['projection']['fragments'] as $fragment) {
            $doc = new DOMDocument;
            $doc->loadXML($fragment['xml']);
            $x = new DOMXPath($doc);
            $x->registerNamespace('d', 'http://www.sped.fazenda.gov.br/nfse');
            $taxable = $fragment['iss_base_brl'] !== null;
            expect($fragment['validation']['status'])->toBe('VALID')->and($fragment['validation']['full_document_validated'])->toBeFalse()
                ->and($x->evaluate('string(/d:valores/d:vServPrest/d:vServ)'))->toBe('1000.00')
                ->and($x->evaluate('string(/d:valores/d:trib/d:tribMun/d:pAliq)'))->toBe($taxable && $issuerRate ? '2.90' : '')
                ->and($x->evaluate('count(//d:vBC | //d:vISSQN | //d:cTribMun)'))->toBe(0.0);
            $schema = $c['knowledge']['documents']['rtc_code_catalog']['dps_xsd'];
            expect((new DpsXmlSchemaValidator)->validate($fragment['xml'], resource_path('fiscal/sources/official/'.$schema['source_file']),
                $schema['source_sha256'], $schema['root_schema'])['status'])->toBe('INVALID');
        }
        expect(Artisan::call('fiscal:dps:project-document-values', ['snapshot' => $dir.'/snapshot.json',
            'options' => $dir.'/options.json', '--output-dir' => $dir.'/projected']))->toBe(1);
    } finally {
        foreach (glob($dir.'/projected/*') ?: [] as $path) {
            unlink($path);
        }
        if (is_dir($dir.'/projected')) {
            rmdir($dir.'/projected');
        }
        foreach (glob($dir.'/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($dir);
    }
})->with([true, false])->with([true, false]);

it('refuses a self-consistent changed fiscal context before the document values projection', function (): void {
    $c = phase4bIss134Fixture();
    $c['scenario']['facts'][DocumentIssIntegration134::PROOFS][1]['value']['municipal_code'] = '02692';
    $c['scenario']['facts'][DocumentIssIntegration134::PROOFS][1] = price112Capture($c['scenario']['facts'][DocumentIssIntegration134::PROOFS][1], 'synthetic://iss134/conflict');
    $factory = new FiscalEngineFactory;
    $loaded = $factory->scenarioLoaderFromKnowledge($c['knowledge'])->fromArray($c['scenario']);
    $r = $factory->makeFromKnowledge($c['knowledge'])->evaluate($loaded->input)->toArray();
    expect($r['reason_codes'])->toBe(['ISS133_GRANULAR_CLASSIFICATION_OR_TAX_CONTEXT_CONFLICT']);
});

it('requires all inherited source guards when only the new version identifier remains', function (): void {
    foreach ([CreditInputPolicy122::class,
        CIndOpReference126::class,
        DpsLayoutPolicy127::class,
        DpsForeignPolicy128::class,
        ImportResponsibility129::class,
        DocumentSchedule131::class] as $class) {
        expect($class::required(['version' => DocumentIssIntegration134::VERSION]))->toBeTrue();
    }
});

it('refuses documentary defaults conflicts and unmapped fields', function (string $case): void {
    $c = phase4bIss134Fixture();
    $options = phase4bIss134Options($c['snapshot']);
    switch ($case) {
        case 'missing': array_pop($options);
            break;
        case 'duplicate': $options[1] = $options[0];
            break;
        case 'document': $options[1]['document_reference'] = 'OTHER';
            break;
        case 'withholding': $options[1]['withholding'] = '0';
            break;
        case 'export withholding': $options[0]['withholding'] = '2';
            break;
        case 'rate source': $options[1]['rate_source'] = 'DEFAULT';
            break;
        case 'export rate': $options[0]['rate_source'] = 'ISSUER_SUPPLIED';
            break;
        case 'country': $options[0]['result_country'] = 'BR';
            break;
        case 'unknown field': $options[1]['cTribMun'] = '660';
            break;
        case 'total': $options[1]['total_tax_indicator'] = '1';
            break;
    }
    $rtc = array_column($c['snapshot']['result']['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    $stage = array_column($rtc['trace'], null, 'stage')['PARTIAL_EXPORT_DOCUMENT_ISS134'];
    $r = (new DpsDocumentValues134)->project($stage, $options);
    expect($r['status'])->toBe('ACTION_REQUIRED')->and($r['complete_dps_built'])->toBeFalse()
        ->and($r)->not->toHaveKey('fragments');
})->with(['missing', 'duplicate', 'document', 'withholding', 'export withholding', 'rate source', 'export rate', 'country', 'unknown field', 'total']);

it('refuses a tampered snapshot before creating an output directory', function (): void {
    $c = phase4bIss134Fixture();
    $options = phase4bIss134Options($c['snapshot']);
    $c['snapshot']['result']['trace'][0]['payload']['nodes'] = [];
    $dir = sys_get_temp_dir().'/iss134-refuse-'.bin2hex(random_bytes(6));
    mkdir($dir);
    try {
        file_put_contents($dir.'/snapshot.json', json_encode($c['snapshot'], JSON_THROW_ON_ERROR));
        file_put_contents($dir.'/options.json', json_encode($options, JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:dps:project-document-values', ['snapshot' => $dir.'/snapshot.json',
            'options' => $dir.'/options.json', '--output-dir' => $dir.'/projected']))->toBe(1);
        $r = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        expect($r['error'])->toBe('Snapshot fails complete deterministic replay.')
            ->and(file_exists($dir.'/projected'))->toBeFalse();
    } finally {
        foreach (glob($dir.'/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($dir);
    }
});
