<?php

declare(strict_types=1);

use App\Modules\Fiscal\NFSe\DpsImportCandidate130;
use App\Modules\Fiscal\NFSe\DpsImportCandidatePolicy130;
use Illuminate\Support\Facades\Artisan;

require_once dirname(__DIR__, 3).'/scripts/phase4b_import130_fixture.php';

beforeEach(function (): void {
    $this->import130Dir = sys_get_temp_dir().'/import130-'.bin2hex(random_bytes(6));
    mkdir($this->import130Dir);
});

afterEach(function (): void {
    foreach (glob($this->import130Dir.'/*') ?: [] as $path) {
        unlink($path);
    }
    rmdir($this->import130Dir);
});

it('builds a deterministic XSD-valid import candidate from the real nonfinal integrated assessment', function (): void {
    [$snapshot, $identity] = phase4bImport130Fixture();
    expect($snapshot['result']['status'])->toBe('ACTION_REQUIRED');
    file_put_contents($this->import130Dir.'/snapshot.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
    file_put_contents($this->import130Dir.'/identity.json', json_encode($identity, JSON_THROW_ON_ERROR));
    $args = ['snapshot' => $this->import130Dir.'/snapshot.json', 'identity' => $this->import130Dir.'/identity.json'];
    $exit = Artisan::call('fiscal:dps:import-candidate', [...$args, '--candidate-output' => $this->import130Dir.'/first.xml']);
    $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($report)->toHaveKey('schema_validation')->and($exit)->toBe(0)
        ->and($report['status'])->toBe('CANDIDATE_VALIDATED')
        ->and($report['fiscal_decision_status'])->toBe('ACTION_REQUIRED')
        ->and($report['document_obligation_complete'])->toBeFalse()->and($report['production_ready'])->toBeFalse();
    expect(Artisan::call('fiscal:dps:import-candidate', [...$args, '--candidate-output' => $this->import130Dir.'/second.xml']))->toBe(0);
    $xml = file_get_contents($this->import130Dir.'/first.xml');
    expect($xml)->toBe(file_get_contents($this->import130Dir.'/second.xml'));
    $document = new DOMDocument;
    $document->loadXML($xml);
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('d', 'http://www.sped.fazenda.gov.br/nfse');
    expect($xpath->evaluate('string(/d:DPS/d:infDPS/d:tpEmit)'))->toBe('2')
        ->and($xpath->evaluate('string(/d:DPS/d:infDPS/d:prest/d:NIF)'))->toBe('US-FOREIGN-123')
        ->and($xpath->evaluate('count(/d:DPS/d:infDPS/d:prest/d:CNPJ)'))->toBe(0.0)
        ->and($xpath->evaluate('string(/d:DPS/d:infDPS/d:toma/d:CNPJ)'))->toBe('22222222000191')
        ->and($xpath->evaluate('string(/d:DPS/d:infDPS/d:serv/d:cServ/d:cNBS)'))->toBe('115022000')
        ->and($xpath->evaluate('string(/d:DPS/d:infDPS/d:valores/d:vServPrest/d:vServ)'))->toBe('1000.00');
    // The existing final-decision command still refuses exactly the same nonfinal snapshot.
    expect(Artisan::call('fiscal:dps:project-xml', [...$args,
        '--bundle-manifest' => 'bundles/consumption-phase4b-2026.10.22-129-draft1.json',
        '--output' => $this->import130Dir.'/final.xml']))->toBe(1)
        ->and(file_exists($this->import130Dir.'/final.xml'))->toBeFalse();
});

it('refuses conflicting data and tampered snapshots before writing a candidate', function (string $case): void {
    [$snapshot, $identity] = phase4bImport130Fixture();
    switch ($case) {
        case 'snapshot': $snapshot['result']['trace'][0]['payload']['nodes'] = [];
            break;
        case 'production': $identity['environment'] = '1';
            break;
        case 'issuer': $identity['issuer_cnpj'] = '11111111000191';
            break;
        case 'municipality': $identity['service_municipality'] = '3509502';
            break;
        case 'nif': $identity['foreign_supplier']['nif'] = 'OTHER';
            break;
        case 'country': $identity['foreign_supplier']['country'] = 'CA';
            break;
        case 'supplier regime': $identity['foreign_supplier']['regime']['opSimpNac'] = '3';
            break;
        case 'party field': $identity['foreign_supplier']['extra'] = 'X';
            break;
        case 'currency': $identity['foreign_trade']['tpMoeda'] = '220';
            break;
        case 'amount': $identity['foreign_trade']['vServMoeda'] = '999.00';
            break;
        case 'future mode': $identity['foreign_trade']['mdPrestacao'] = '5';
            break;
        case 'future invoice': $identity['foreign_trade']['nFatura'] = 'INVOICE';
            break;
        case 'identity field': $identity['supplier_cnpj'] = '11111111000191';
            break;
        case 'number': $identity['number'] = '01';
            break;
    }
    file_put_contents($this->import130Dir.'/snapshot.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
    file_put_contents($this->import130Dir.'/identity.json', json_encode($identity, JSON_THROW_ON_ERROR));
    $exit = Artisan::call('fiscal:dps:import-candidate', ['snapshot' => $this->import130Dir.'/snapshot.json',
        'identity' => $this->import130Dir.'/identity.json', '--candidate-output' => $this->import130Dir.'/candidate.xml']);
    $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    $expected = match ($case) {
        'production' => 'RTC_DPS127_PRODUCTION_ENVIRONMENT_FORBIDDEN',
        'issuer', 'municipality', 'number' => 'RTC_IMPORT130_IDENTITY_FACT_BINDING_CONFLICT',
        'nif', 'country' => 'RTC_IMPORT130_SUPPLIER_FACT_BINDING_CONFLICT',
        'supplier regime' => 'RTC_DPS_FOREIGN_PARTY_EXPLICIT_FOREIGN_ORDINARY_REGIME_REQUIRED',
        'party field' => 'RTC_DPS_FOREIGN_PARTY_ADDITIONAL_PARTY_DATA_NOT_MAPPED',
        'currency', 'amount' => 'RTC_IMPORT130_ORIGINAL_AMOUNT_CURRENCY_OR_GOODS_CONFLICT',
        'future mode' => 'RTC_IMPORT130_COMEXT_OR_APPLICATION_FACET_CONFLICT',
        'future invoice' => 'RTC_IMPORT130_EXACT_COMEXT_FIELDS_REQUIRED',
        'identity field' => 'RTC_IMPORT130_ADDITIONAL_IDENTITY_FIELDS_NOT_MAPPED',
        default => null,
    };
    if ($expected !== null) {
        expect($report['reason'] ?? null)->toBe($expected);
    } else {
        expect($report['error'] ?? null)->toBe('Import snapshot fails complete deterministic replay.');
    }
    expect($exit)->toBe(1)
        ->and(file_exists($this->import130Dir.'/candidate.xml'))->toBeFalse();
})->with(['snapshot', 'production', 'issuer', 'municipality', 'nif', 'country', 'supplier regime', 'party field',
    'currency', 'amount', 'future mode', 'future invoice', 'identity field', 'number']);

it('refuses unmapped real fiscal profiles without treating their missing data as defaults', function (string $case, string $reason): void {
    $overrides = match ($case) {
        'missing NBS' => ['rtc.service_nbs_code' => null, 'rtc.service_nbs_evidence_id' => null],
        'unknown NBS' => ['rtc.service_nbs_code' => '1.9999.99.99'],
        'partial consumption' => ['rtc.import_brazil_share_percent' => '40', 'rtc.import_allocation_method' => 'CONTRACTUAL',
            'rtc.import_allocation_evidence_id' => 'SYNTHETIC-130-ALLOCATION'],
        default => [],
    };
    [$snapshot, $identity] = phase4bImport130Fixture($overrides, $case === 'recipient',
        $case === 'early knowledge' ? '2026-10-22T17:00:00+00:00' : '2026-10-22T17:34:00+00:00');
    expect($snapshot['result']['status'])->toBe('ACTION_REQUIRED');
    $candidate = (new DpsImportCandidate130)->project($snapshot, $identity, DpsImportCandidatePolicy130::policy());
    expect($candidate['reason'])->toBe('RTC_IMPORT130_'.$reason)->and($candidate['xml'])->toBeNull();
})->with([
    ['missing NBS', 'EXPLICIT_NBS_AND_EVIDENCE_REQUIRED'], ['unknown NBS', 'EXPLICIT_NBS_AND_EVIDENCE_REQUIRED'],
    ['partial consumption', 'DOCUMENT_PROFILE_NOT_MAPPED'], ['recipient', 'DOCUMENT_PROFILE_NOT_MAPPED'],
    ['early knowledge', 'SOURCE_POLICY_NOT_BOUND'],
]);

it('refuses to overwrite candidate or report artifacts', function (): void {
    [$snapshot, $identity] = phase4bImport130Fixture();
    file_put_contents($this->import130Dir.'/snapshot.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
    file_put_contents($this->import130Dir.'/identity.json', json_encode($identity, JSON_THROW_ON_ERROR));
    file_put_contents($this->import130Dir.'/existing.json', 'PRESERVED');
    $exit = Artisan::call('fiscal:dps:import-candidate', ['snapshot' => $this->import130Dir.'/snapshot.json',
        'identity' => $this->import130Dir.'/identity.json', '--candidate-output' => $this->import130Dir.'/new.xml',
        '--report' => $this->import130Dir.'/existing.json']);
    expect($exit)->toBe(1)->and(file_exists($this->import130Dir.'/new.xml'))->toBeFalse()
        ->and(file_get_contents($this->import130Dir.'/existing.json'))->toBe('PRESERVED');
});
