<?php

declare(strict_types=1);

use App\Modules\Fiscal\NFSe\ImportDocumentValues132;
use Illuminate\Support\Facades\Artisan;

require_once dirname(__DIR__, 3).'/scripts/phase4b_import132_fixture.php';

beforeEach(function (): void {
    $this->values132Dir = sys_get_temp_dir().'/values132-'.bin2hex(random_bytes(6));
    mkdir($this->values132Dir);
});

afterEach(function (): void {
    foreach (glob($this->values132Dir.'/*') ?: [] as $path) {
        unlink($path);
    }
    rmdir($this->values132Dir);
});

it('reconciles a source-bound invoice after complete replay without changing XML or fiscal results', function (): void {
    [$snapshot, $identity, $document, $bytes] = phase4bImport132Fixture();
    foreach (['snapshot' => $snapshot, 'identity' => $identity, 'values' => $document] as $name => $value) {
        file_put_contents($this->values132Dir.'/'.$name.'.json', json_encode($value, JSON_THROW_ON_ERROR));
    }
    file_put_contents($this->values132Dir.'/invoice.txt', $bytes);
    $args = ['snapshot' => $this->values132Dir.'/snapshot.json', 'identity' => $this->values132Dir.'/identity.json'];
    expect(Artisan::call('fiscal:dps:import-candidate', [...$args,
        '--candidate-output' => $this->values132Dir.'/legacy.xml']))->toBe(0);
    $legacy = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($legacy)->not->toHaveKey('document_values132');
    expect(Artisan::call('fiscal:dps:import-candidate', [...$args,
        '--values-document' => $this->values132Dir.'/values.json', '--invoice-source' => $this->values132Dir.'/invoice.txt',
        '--candidate-output' => $this->values132Dir.'/bound.xml', '--report' => $this->values132Dir.'/report.json']))->toBe(0);
    $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect(file_get_contents($this->values132Dir.'/legacy.xml'))->toBe(file_get_contents($this->values132Dir.'/bound.xml'))
        ->and(array_diff_key($report, ['document_values132' => true, 'candidate_output' => true]))
        ->toBe(array_diff_key($legacy, ['candidate_output' => true]))
        ->and($report['document_values132']['status'])->toBe('RECONCILED_DECLARED_VALUES')
        ->and($report['document_values132']['source_sha256'])->toBe(hash('sha256', $bytes))
        ->and($report['document_values132']['compared_amounts'])->toBe([
            'invoice_service_price' => '1000.00', 'operation_amount' => '1000.00',
            'iss_gross_amount' => '1000.00', 'rtc_original_gross_amount' => '1000.00',
        ])
        ->and($report['document_values132']['source_authenticity_verified'])->toBeFalse()
        ->and($report['document_values132']['iss_legal_base_qualified'])->toBeFalse()
        ->and($report['fiscal_decision_final'])->toBeFalse()
        ->and($report['document_obligation_complete'])->toBeFalse();
});

it('rejects documentary conflicts before any output artifact is written', function (string $case, string $reason): void {
    [$snapshot, $identity, $document, $bytes] = phase4bImport132Fixture();
    switch ($case) {
        case 'invoice': $document['invoice_id'] = 'OTHER';
            break;
        case 'supplier': $document['supplier_nif'] = 'OTHER';
            break;
        case 'acquirer': $document['acquirer_id'] = 'OTHER';
            break;
        case 'service': $document['service_id'] = 'OTHER';
            break;
        case 'date': $document['competence_date'] = '2026-10-21';
            break;
        case 'currency': $document['currency'] = 'USD';
            break;
        case 'price': $document['service_price'] = '1000.01';
            break;
        case 'precision': $document['service_price'] = '1000.001';
            break;
        case 'negative': $document['service_price'] = '-1';
            break;
        case 'float': $document['service_price'] = 1000.0;
            break;
        case 'combined': $document['coverage'] = 'MULTIPLE_LINES';
            break;
        case 'extra': $document['exchange_rate'] = '1';
            break;
        case 'missing': unset($document['line_id']);
            break;
        case 'future': $document['source_evidence']['observed_at'] = '2026-10-22T17:34:01+00:00';
            break;
        case 'invalid date': $document['source_evidence']['observed_at'] = '2026-02-30T12:00:00+00:00';
            break;
        case 'changed bytes': $bytes .= 'CHANGED';
            break;
    }
    foreach (['snapshot' => $snapshot, 'identity' => $identity, 'values' => $document] as $name => $value) {
        file_put_contents($this->values132Dir.'/'.$name.'.json', json_encode($value, JSON_THROW_ON_ERROR));
    }
    file_put_contents($this->values132Dir.'/invoice.txt', $bytes);
    expect(Artisan::call('fiscal:dps:import-candidate', [
        'snapshot' => $this->values132Dir.'/snapshot.json', 'identity' => $this->values132Dir.'/identity.json',
        '--values-document' => $this->values132Dir.'/values.json', '--invoice-source' => $this->values132Dir.'/invoice.txt',
        '--candidate-output' => $this->values132Dir.'/candidate.xml', '--report' => $this->values132Dir.'/report.json',
    ]))->toBe(1);
    $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($report['reason'] ?? null)->toBe('IMPORT132_'.$reason)
        ->and(file_exists($this->values132Dir.'/candidate.xml'))->toBeFalse()
        ->and(file_exists($this->values132Dir.'/report.json'))->toBeFalse();
})->with([
    ['invoice', 'DOCUMENT_FACT_BINDING_CONFLICT'], ['supplier', 'DOCUMENT_FACT_BINDING_CONFLICT'],
    ['acquirer', 'DOCUMENT_FACT_BINDING_CONFLICT'], ['service', 'DOCUMENT_FACT_BINDING_CONFLICT'],
    ['date', 'DOCUMENT_FACT_BINDING_CONFLICT'], ['currency', 'INDEPENDENT_ISS_CURRENCY_CONTRACT_REQUIRED'],
    ['price', 'SERVICE_PRICE_CONFLICT'], ['precision', 'EXACT_NONNEGATIVE_CENT_VALUES_REQUIRED'],
    ['negative', 'EXACT_NONNEGATIVE_CENT_VALUES_REQUIRED'], ['float', 'EXPLICIT_DOCUMENT_VALUES_REQUIRED'],
    ['combined', 'DOCUMENT_PROFILE_NOT_MAPPED'], ['extra', 'EXACT_DOCUMENT_FIELDS_REQUIRED'],
    ['missing', 'EXACT_DOCUMENT_FIELDS_REQUIRED'], ['future', 'SOURCE_NOT_KNOWN'],
    ['invalid date', 'SOURCE_NOT_KNOWN'], ['changed bytes', 'SOURCE_BYTES_NOT_BOUND'],
]);

it('cannot replace the full replay with a self-consistent invoice', function (): void {
    [$snapshot, $identity, $document, $bytes] = phase4bImport132Fixture();
    $snapshot['result']['trace'][0]['payload']['nodes'] = [];
    foreach (['snapshot' => $snapshot, 'identity' => $identity, 'values' => $document] as $name => $value) {
        file_put_contents($this->values132Dir.'/'.$name.'.json', json_encode($value, JSON_THROW_ON_ERROR));
    }
    file_put_contents($this->values132Dir.'/invoice.txt', $bytes);
    expect(Artisan::call('fiscal:dps:import-candidate', [
        'snapshot' => $this->values132Dir.'/snapshot.json', 'identity' => $this->values132Dir.'/identity.json',
        '--values-document' => $this->values132Dir.'/values.json', '--invoice-source' => $this->values132Dir.'/invoice.txt',
        '--candidate-output' => $this->values132Dir.'/candidate.xml',
    ]))->toBe(1);
    expect(json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR)['error'])
        ->toBe('Import snapshot fails complete deterministic replay.')
        ->and(file_exists($this->values132Dir.'/candidate.xml'))->toBeFalse();
});

it('requires the original source and values contract together', function (string $option): void {
    [$snapshot, $identity] = phase4bImport132Fixture();
    foreach (['snapshot' => $snapshot, 'identity' => $identity] as $name => $value) {
        file_put_contents($this->values132Dir.'/'.$name.'.json', json_encode($value, JSON_THROW_ON_ERROR));
    }
    expect(Artisan::call('fiscal:dps:import-candidate', [
        'snapshot' => $this->values132Dir.'/snapshot.json', 'identity' => $this->values132Dir.'/identity.json',
        $option => $this->values132Dir.'/absent', '--candidate-output' => $this->values132Dir.'/candidate.xml',
    ]))->toBe(1);
    expect(json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR)['error'])
        ->toBe('Values document and invoice source must be supplied together.')
        ->and(file_exists($this->values132Dir.'/candidate.xml'))->toBeFalse();
})->with(['--values-document', '--invoice-source']);

it('compares every monetary source independently and refuses ambiguous ISS traces', function (string $case, string $reason): void {
    [$snapshot, , $document, $bytes] = phase4bImport132Fixture();
    $issIndex = array_search('ISS_CALCULATION', array_column($snapshot['result']['trace'], 'type'), true);
    expect($issIndex)->not->toBeFalse();
    switch ($case) {
        case 'operation': $snapshot['input']['facts']['operation.amount'] = '999.99';
            break;
        case 'rtc': $snapshot['input']['facts']['rtc.gross_amount'] = '999.99';
            break;
        case 'iss': $snapshot['result']['trace'][$issIndex]['payload']['gross_amount'] = '999.99';
            break;
        case 'rtc currency': $snapshot['input']['facts']['rtc.gross_currency'] = 'USD';
            break;
        case 'iss currency': $snapshot['result']['trace'][$issIndex]['payload']['currency'] = 'USD';
            break;
        case 'deduction': $snapshot['result']['trace'][$issIndex]['payload']['deduction_amount'] = '10.00';
            break;
        case 'missing': unset($snapshot['result']['trace'][$issIndex]);
            break;
        case 'duplicate': $snapshot['result']['trace'][] = $snapshot['result']['trace'][$issIndex];
            break;
    }
    expect((new ImportDocumentValues132)->reconcile($snapshot, $document, hash('sha256', $bytes))['reason'])
        ->toBe('IMPORT132_'.$reason);
})->with([
    ['operation', 'SERVICE_PRICE_CONFLICT'], ['rtc', 'SERVICE_PRICE_CONFLICT'], ['iss', 'SERVICE_PRICE_CONFLICT'],
    ['rtc currency', 'INDEPENDENT_ISS_CURRENCY_CONTRACT_REQUIRED'], ['iss currency', 'ISS_PROFILE_NOT_MAPPED'],
    ['deduction', 'ISS_PROFILE_NOT_MAPPED'], ['missing', 'ISS_PROFILE_NOT_MAPPED'], ['duplicate', 'AMBIGUOUS_ISS_CALCULATION'],
]);
