<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ImportFxQuoteConverter;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\ISS\IssDocumentCurrency140;
use App\Modules\Fiscal\ISS\PtaxClosingQuote140;

require_once dirname(__DIR__, 3).'/scripts/phase4b_iss_currency140_fixture.php';

function iss140Knowledge(): array
{
    static $knowledge;

    return $knowledge ??= (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.27-140-draft1.json');
}

function iss140Run(array $input, ?array $knowledge = null): array
{
    $factory = new FiscalEngineFactory;
    $knowledge ??= iss140Knowledge();
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);

    return $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input)->toArray();
}

it('converts the document ISS price on the explicit supply day while preserving the original RTC amount', function (string $currency, string $gross, string $tax): void {
    $case = issCurrency140Scenario(iss140Knowledge(), $currency);
    $hash = CanonicalJson::hash($case);
    $result = iss140Run($case);
    expect($result['status'])->toBe('VALID_WITH_WARNINGS');
    $trace = array_column($result['trace'], 'payload', 'type');
    $conversion = $trace['ISS_DOCUMENT_CURRENCY140'];
    expect($conversion['status'])->toBe('LOCAL_ISS_DOCUMENT_PRICE_CONVERTED')
        ->and($conversion['converted_price_brl'])->toBe($gross)
        ->and($conversion['rtc_operation_amount_preserved'])->toBe('5179.50')
        ->and($conversion['payment_date_used_as_tax_point'])->toBeFalse()
        ->and($conversion['rtc_quote_reused'])->toBeFalse()
        ->and($result['decision']['iss']['calculation']['gross_amount'])->toBe($gross)
        ->and($result['decision']['iss']['calculation']['tax_amount'])->toBe($tax)
        ->and($result['decision']['dps_projection']['values']['vServ'])->toBe($gross)
        ->and(CanonicalJson::hash($case))->toBe($hash)
        ->and(CanonicalJson::hash(iss140Run($case)))->toBe(CanonicalJson::hash($result));
    if ($currency === 'USD') {
        expect($conversion['quote']['quote_date'])->toBe('2026-09-25')
            ->and($conversion['quote']['rate'])->toBe('5.19910');
    }
})->with([['USD', '5199.10', '259.96'], ['BRL', '1000.00', '50.00']]);

it('asks for the material document currency and does not calculate from an RTC amount by default', function (): void {
    $case = issCurrency140Scenario(iss140Knowledge());
    unset($case['facts']['iss.original_price_currency']);
    $result = iss140Run($case);
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['missing_facts'])->toContain('iss.original_price_currency')
        ->and(array_column($result['questions'], 'fact_key'))->toContain('iss.original_price_currency')
        ->and($result['decision'])->toBe([]);
});

it('refuses freshly recaptured contradictory document and currency claims', function (string $mutation): void {
    $case = issCurrency140Scenario(iss140Knowledge());
    $proof = &$case['facts'][IssDocumentCurrency140::PROOFS][0];
    match ($mutation) {
        'wrong quote day' => $proof['value']['quote_date'] = '2026-09-24',
        'rtc rate' => $proof['value']['rate'] = '5.17950',
        'buy rate' => $proof['value']['rate'] = '5.19850',
        'wrong document' => $proof['value']['document_reference'] = 'OTHER-INVOICE',
        'wrong supplier' => $proof['value']['supplier_id'] = 'OTHER-SUPPLIER',
        'wrong customer' => $proof['value']['customer_id'] = '22222222000191',
        'wrong event evidence' => $proof['value']['supply_event_reference'] = 'OTHER-REPORT',
        'wrong original rtc amount' => $proof['value']['original_operation_amount_brl'] = '5199.10',
        'rounded aggregate' => $proof['value']['converted_price_brl'] = '5199.09',
        'future capture' => $proof['observed_at'] = '2026-10-28T12:05:05.000000+00:00',
        'revoked' => $proof['revoked_at'] = $case['knowledge_at'],
        'superseded' => $proof['superseded_at'] = $case['knowledge_at'],
        'wrong coverage' => $proof['valid_from'] = '2026-10-01',
        'old contract' => $proof['contract'] = 'UNKNOWN-CONTRACT',
        'continuous service' => $case['facts']['iss.supply_event_kind'] = 'CONTINUOUS_MONTH_END',
        'different day' => $case['facts']['iss.document_supply_on'] = '2026-09-24',
        'unsupported currency' => $case['facts']['iss.original_price_currency'] = 'EUR',
        'cross tax party' => $case['facts']['rtc.acquirer_id'] = '22222222000191',
    };
    $proof = own135Capture($proof);
    unset($proof);
    $result = iss140Run($case);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['decision'])->toBe([]);
})->with(['wrong quote day', 'rtc rate', 'buy rate', 'wrong document', 'wrong supplier', 'wrong customer',
    'wrong event evidence', 'wrong original rtc amount', 'rounded aggregate', 'future capture', 'revoked', 'superseded',
    'wrong coverage', 'old contract', 'continuous service', 'different day', 'unsupported currency', 'cross tax party']);

it('requires an exact closing on the ISS day rather than a weekend or previous-day fallback', function (): void {
    $source = iss140Knowledge()['documents']['iss_currency_catalog']['quote_source'];
    $reader = new PtaxClosingQuote140;
    $result = $reader->select($source, 'USD', '2026-09-26', new DateTimeImmutable(IssDocumentCurrency140::AVAILABLE_AT));
    expect($result['status'])->toBe('ACTION_REQUIRED');
    $source['url'] = str_replace('09-25-2026', '09-26-2026', $source['url']);
    $result = $reader->select($source, 'USD', '2026-09-26', new DateTimeImmutable(IssDocumentCurrency140::AVAILABLE_AT));
    expect($result['reason'])->toBe('PTAX140_EXACT_DAY_CLOSING_NOT_AVAILABLE');
});

it('uses the existing RTC converter independently and does not transplant its prior-day selection into ISS', function (): void {
    $knowledge = iss140Knowledge();
    $source = $knowledge['documents']['iss_currency_catalog']['quote_source'];
    $rtc = (new ImportFxQuoteConverter)->convert('1000.00', 'USD', '2026-09-25', new DateTimeImmutable(IssDocumentCurrency140::AVAILABLE_AT),
        ['synthetic' => true, 'operation_kind' => 'ORDINARY_SERVICE_IMPORT', 'side' => 'SALE', 'bulletin' => 'Fechamento', 'source_id' => $source['source_id']], [$source['source_id'] => $source]);
    $iss = iss140Run(issCurrency140Scenario($knowledge));
    expect($rtc['converted_gross_brl'])->toBe('5179.50')
        ->and($rtc['selected_quote']['quote_date'])->toBe('2026-09-24')
        ->and($iss['decision']['iss']['calculation']['gross_amount'])->toBe('5199.10');
});

it('rejects freshly rehashed malformed or ambiguous primary quote data', function (string $mutation): void {
    $source = iss140Knowledge()['documents']['iss_currency_catalog']['quote_source'];
    $raw = json_decode($source['raw_json'], true, 512, JSON_THROW_ON_ERROR);
    $index = count($raw['value']) - 1;
    match ($mutation) {
        'string rate' => $raw['value'][$index]['cotacaoVenda'] = '5.19910',
        'boolean rate' => $raw['value'][$index]['cotacaoVenda'] = true,
        'zero rate' => $raw['value'][$index]['cotacaoVenda'] = 0,
        'negative rate' => $raw['value'][$index]['cotacaoVenda'] = -1,
        'duplicate closing' => $raw['value'][] = $raw['value'][$index],
        'missing closing' => $raw['value'][$index]['tipoBoletim'] = 'Abertura',
        'invalid date' => $raw['value'][$index]['dataHoraCotacao'] = '2026-09-32 13:10:17.447657',
        'future closing' => $raw['value'][$index]['dataHoraCotacao'] = '2026-10-28 13:10:17.447657',
        'paged source' => $raw['@odata.nextLink'] = 'https://olinda.bcb.gov.br/next',
        'malformed observed time' => $source['observed_at'] = 'today',
        'future source' => $source['observed_at'] = '2026-10-28T00:00:00+00:00',
        'foreign endpoint' => $source['url'] = str_replace('olinda.bcb.gov.br', 'example.com', $source['url']),
        'wrong currency' => $source['currency'] = 'EUR',
        'exponent rate' => null,
    };
    $source['raw_json'] = CanonicalJson::encode($raw);
    if ($mutation === 'exponent rate') {
        $source['raw_json'] = str_replace('"cotacaoVenda":5.1991', '"cotacaoVenda":5.19910e0', $source['raw_json']);
    }
    $source['sha256'] = hash('sha256', $source['raw_json']);
    $result = (new PtaxClosingQuote140)->select($source, 'USD', '2026-09-25', new DateTimeImmutable(IssDocumentCurrency140::AVAILABLE_AT));
    expect($result['status'])->toBe('ACTION_REQUIRED');
})->with(['string rate', 'boolean rate', 'zero rate', 'negative rate', 'duplicate closing', 'missing closing', 'invalid date',
    'future closing', 'paged source', 'malformed observed time', 'future source', 'foreign endpoint', 'wrong currency', 'exponent rate']);

it('limits the new foreign-regime value to a foreign supplier and foreign service origin', function (string $key, string $value): void {
    $case = issCurrency140Scenario(iss140Knowledge());
    $case['facts'][$key] = $value;
    $result = iss140Run($case);
    expect($result['status'])->toBe('INVALID')
        ->and($result['reason_codes'])->toContain('ISS_CURRENCY140_FOREIGN_REGIME_PROFILE_CONFLICT')
        ->and($result['decision'])->toBe([]);
})->with([['operation.service_origin', 'DOMESTIC'], ['provider.person_type', 'PRIVATE_LEGAL_ENTITY']]);

it('rejects unknown selectors and rehashed mismatched currency catalog versions', function (string $mutation): void {
    $knowledge = iss140Knowledge();
    if ($mutation === 'unknown selector') {
        $knowledge['documents']['bundle_manifest']['iss_currency_contract'] = 'UNKNOWN';
    } else {
        $knowledge['documents']['iss_currency_catalog']['version'] = 'OTHER';
        $knowledge['content_hashes']['iss_currency_catalog'] = CanonicalJson::hash($knowledge['documents']['iss_currency_catalog']);
        $knowledge['documents']['bundle_manifest']['content_hashes']['iss_currency_catalog'] = $knowledge['content_hashes']['iss_currency_catalog'];
    }
    $knowledge['content_hashes']['bundle_manifest'] = CanonicalJson::hash($knowledge['documents']['bundle_manifest']);
    expect(fn () => (new FiscalEngineFactory)->makeFromKnowledge($knowledge))->toThrow(RuntimeException::class);
})->with(['unknown selector', 'catalog version']);
