<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioExecution;
use App\Modules\Fiscal\NFSe\AnexoViStructure143;
use App\Modules\Fiscal\NFSe\ImportDpsModel143;

require_once dirname(__DIR__, 3).'/scripts/phase4b_import143_fixture.php';

function import143Snapshot(): array
{
    static $snapshot;
    if ($snapshot === null) {
        $factory = new FiscalEngineFactory;
        $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.27-141-draft1.json');
        $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray(import143Scenario($knowledge));
        $snapshot = (new ScenarioExecution($loaded, $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input), $knowledge))->snapshot();
    }

    return $snapshot;
}

function import143Project(?array $identity = null): array
{
    $s = import143Snapshot();
    $source = CanonicalJson::encode(import141Invoice($s['input']));

    return (new ImportDpsModel143)->project($s, $source,
        ['id' => 'SYNTHETIC-INVOICE143', 'sha256' => hash('sha256', $source), 'observed_at' => $s['input']['knowledge_at']],
        import142Comext($s['input']), $identity ?? import143Identity($s['input']), resource_path('fiscal/sources/official'),
        resource_path('fiscal/projections/import-dps-nt009-2026.10.27-143.json'));
}

it('assembles the complete local ordinary import profile and exposes both official-source refusals', function (): void {
    $report = import143Project();
    expect($report['status'])->toBe('LOCAL_COMPLETE_PROFILE_DPS_ASSEMBLED_NOT_OFFICIALLY_ACCEPTED')
        ->and($report['table_structure_validation']['status'])->toBe('OBSERVED_TABLE_STRUCTURE_CONFORMS')
        ->and($report['official_xsd_validation']['status'])->not->toBe('VALID')
        ->and($report['application_business_rule_gate']['code'])->toBe('E9996')
        ->and($report['application_business_rule_gate']['companion_import_issuer_rule']['code'])->toBe('E0027')
        ->and($report['document_obligation_complete'])->toBeFalse()
        ->and($report['signed'])->toBeFalse()->and($report['transmitted'])->toBeFalse();
    $dom = new DOMDocument;
    expect($dom->loadXML($report['candidate_xml'], LIBXML_NONET))->toBeTrue();
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');
    foreach ($report['fields'] as $path => $value) {
        $parts = explode('.', $path);
        $query = '/'.implode('/', array_map(fn (string $p): string => str_starts_with($p, '@') ? $p : 'n:'.$p, $parts));
        expect($xpath->evaluate('string('.$query.')'))->toBe($value);
    }
    expect($xpath->evaluate('count(/n:DPS/n:infDPS/n:IBSCBS/n:indDest)'))->toBe(0.0)
        ->and($xpath->evaluate('string(/n:DPS/n:infDPS/n:indDest)'))->toBe('0')
        ->and($xpath->evaluate('count(//n:vServMoeda)'))->toBe(0.0)
        ->and($xpath->evaluate('string(//n:vFatura)'))->toBe('1000.00')
        ->and($xpath->evaluate('string(//n:vServ)'))->toBe('5199.10')
        ->and($xpath->evaluate('string(//n:vPis)'))->toBe('85.46')
        ->and($xpath->evaluate('string(//n:vCofins)'))->toBe('393.64')
        ->and(CanonicalJson::hash(import143Project()))->toBe(CanonicalJson::hash($report));
});

it('refuses material identity and party contradictions before constructing a candidate', function (string $case): void {
    $identity = import143Identity(import143Snapshot()['input']);
    match ($case) {
        'issuer' => $identity['issuer_cnpj'] = '11111111000191',
        'issuer municipality' => $identity['issuer_municipality'] = '3304557',
        'service place' => $identity['service_municipality'] = '3304557',
        'supplier nif' => $identity['foreign_supplier']['nif'] = 'OTHER',
        'supplier country' => $identity['foreign_supplier']['country'] = 'FR',
        'address municipality' => $identity['acquirer_address']['municipality'] = '3304557',
        'address evidence' => $identity['acquirer_address']['evidence_id'] = '',
        'postal facet' => $identity['acquirer_address']['postal_code'] = 'INVALID',
        'no legacy classification' => $identity['legacy_tax_document'] = [],
        'invalid legacy classification' => $identity['legacy_tax_document']['cst'] = 'XX',
        'future identity' => $identity['observed_at'] = '2030-01-01T00:00:00Z',
        'zero series' => $identity['series'] = '0',
        'production' => $identity['environment'] = '1',
        'future issue' => $identity['issued_at'] = '2030-01-01T00:00:00Z',
        'fractional issue' => $identity['issued_at'] = '2026-10-27T13:23:43.019734+00:00',
        'long application' => $identity['application_version'] = str_repeat('a', 21),
        'missing evidence' => $identity['identity_evidence_id'] = '',
    };
    $report = import143Project($identity);
    expect($report['status'])->toBeIn(['ACTION_REQUIRED', 'INVALID'])
        ->and($report['document_obligation_complete'] ?? false)->toBeFalse()
        ->and($report)->not->toHaveKey('candidate_xml');
})->with(['issuer', 'issuer municipality', 'service place', 'supplier nif', 'supplier country', 'address municipality',
    'address evidence', 'postal facet', 'no legacy classification', 'invalid legacy classification', 'future identity',
    'zero series', 'production', 'future issue', 'fractional issue', 'long application', 'missing evidence']);

it('checks required groups choices and inactive rows directly against the source table', function (string $case): void {
    $report = import143Project();
    $fields = $report['fields'];
    match ($case) {
        'missing date' => $fields = array_diff_key($fields, ['DPS.infDPS.dCompet' => true]),
        'missing regime' => $fields = array_diff_key($fields, ['DPS.infDPS.prest.regTrib.opSimpNac' => true, 'DPS.infDPS.prest.regTrib.regEspTrib' => true]),
        'multiple ids' => $fields['DPS.infDPS.prest.CNPJ'] = '11111111000191',
        'struck field' => $fields['DPS.infDPS.serv.comExt.vServMoeda'] = '1000.00',
        'unknown field' => $fields['DPS.infDPS.UNSUPPORTED'] = '1',
        'both addresses' => $fields['DPS.infDPS.toma.end.endExt.cPais'] = 'US',
    };
    $profile = json_decode((string) file_get_contents(resource_path('fiscal/projections/import-dps-nt009-2026.10.27-143.json')), true, 512, JSON_THROW_ON_ERROR);
    $result = (new AnexoViStructure143)->validate($fields, $profile);
    expect($result['status'])->toBe('ACTION_REQUIRED');
})->with(['missing date', 'missing regime', 'multiple ids', 'struck field', 'unknown field', 'both addresses']);
