<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DerePgccQualifier;
use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DerePgccXmlProjector;

/** Explicit synthetic qualified reference rows; no automatic approval of observed COSIF. @return array<string, mixed> */
function derePgccFixture(): array
{
    $root = ['cCtaInterna' => 'ROOT', 'cDbrMista' => '000', 'cCta' => 'ROOT000', 'nomeCta' => 'Root', 'indCta' => 'S', 'nivelCta' => 1,
        'natCta' => 'D', 'codNat' => '1', 'cCtaRef' => '1100000002', 'valid_from' => '2026-01-01'];
    $analytic = ['cCtaInterna' => 'CASH', 'cDbrMista' => '000', 'cCta' => 'CASH000', 'nomeCta' => 'Cash', 'indCta' => 'A', 'nivelCta' => 2,
        'cCtaSup' => 'ROOT000', 'descCta' => 'Synthetic qualified mapping', 'natCta' => 'D', 'codNat' => '1',
        'cCtaRef' => '1111000008', 'codTrib' => '110112001', 'indTribISS' => '0', 'valid_from' => '2026-01-01'];
    $source = ['qualified' => true, 'source_hash' => str_repeat('a', 64), 'valid_from' => '2026-01-01', 'observed_on' => '2026-10-01'];

    return ['plan' => [...$source, 'complete' => true, 'planoCtaRef' => '1', 'freqEncerr' => 'M', 'period_end' => '2026-09-30', 'accounts' => [$root, $analytic]],
        'reference' => [...$source, 'planoCtaRef' => '1', 'accounts' => ['1100000002' => ['valid_from' => '2026-01-01'], '1111000008' => ['valid_from' => '2026-01-01']]],
        'tax' => [...$source, 'codes' => ['110112001' => ['valid_from' => '2026-01-01',
            'auxiliary_requirements' => ['D1106' => false, 'D1121' => true, 'D2101' => false]]]]];
}

/** @param array<string, mixed> $fixture @return array<string, mixed> */
function derePgccResult(array $fixture): array
{
    return (new DerePgccQualifier)->resolve($fixture['plan'], $fixture['reference'], $fixture['tax'], new DateTimeImmutable('2026-10-02'));
}

/** @return array<string, mixed> */
function derePgccProjectionIdentity(): array
{
    return ['generated_at' => '2026-10-01T12:00:00.000-03:00', 'event_sequence' => 1, 'contributor_supplier_id' => '11111111000191',
        'contributor_qualified' => true, 'dere_start_qualified' => true, 'contributor_source_hash' => str_repeat('b', 64),
        'dere_start_source_hash' => str_repeat('c', 64), 'contributor_valid_from' => '2026-01-01', 'dere_start_on' => '2026-01-01', 'observed_on' => '2026-10-01'];
}

/** @param array<string, mixed> $fixture @param array<string, mixed> $identity @return array<string, mixed> */
function derePgccProject(array $fixture, array $identity, bool $history = false): array
{
    $fixture['plan']['supplier_id'] = '11111111000191';

    return (new DerePgccXmlProjector)->project($fixture['plan'], $identity, $fixture['reference'], $fixture['tax'], new DateTimeImmutable('2026-10-02'),
        ['contract' => DerePgccXmlProjector::CONTRACT, 'status' => 'ACTIVE', 'published_at' => '2026-10-01',
            'source_hash' => 'b52cb2cf96b176d303d64aeea0bab16e7d35bcc739478f6fc8a26b0512415feb', 'historical_declaration_required' => $history]);
}

/** @return array<string, mixed> */
function derePgccHistoricalFixture(): array
{
    $f = derePgccFixture();
    $f['plan']['accounts'][] = [...$f['plan']['accounts'][1], 'cCtaInterna' => 'OLD', 'cCta' => 'OLD000',
        'cCtaRef' => 'OLDREF', 'codTrib' => '110112002', 'valid_to' => '2026-08-31'];
    $f['reference']['accounts']['OLDREF'] = ['valid_from' => '2026-01-01', 'valid_to' => '2026-08-31'];
    $f['tax']['codes']['110112002'] = ['valid_from' => '2026-01-01', 'valid_to' => '2026-08-31',
        'auxiliary_requirements' => ['D1106' => false, 'D1121' => false, 'D2101' => true]];

    return $f;
}

it('preserves inactive accounts in the original declaration with historically effective reference and tax codes', function (): void {
    $result = derePgccProject(derePgccHistoricalFixture(), derePgccProjectionIdentity(), true);
    expect($result['status'])->toBe('PROJECTED')->and(count($result['qualification']['accounts']))->toBe(3)
        ->and($result['qualification']['required_auxiliary_events'])->toBe(['D1121']);
    $xml = new DOMDocument;
    $xml->loadXML($result['xml']);
    expect($xml->getElementsByTagName('infoConta')->length)->toBe(3)
        ->and($xml->getElementsByTagName('fimVig')->item(0)->textContent)->toBe('2026-08-31');
    $root = dirname(__DIR__, 3).'/resources/fiscal/sources/official/';
    $validation = (new DereXmlSchemaValidator)->validate($result['xml'], $root.'dere-observed-20261002-06-arquivos-xsd-v-1-2-0.zip',
        'c750f7d522400e5bd93b7ddd1d1d0d8108cd033eb250c1f10011ac57cc732818', $result['schema'],
        $root.'w3c-xmldsig-core-schema-observed-20261002.xsd', 'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
    $this->assertSame('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE', $validation['reason'], json_encode($validation, JSON_THROW_ON_ERROR));
});

it('rejects historical gaps in references tax codes descriptions and parent accounts', function (string $case, string $reason): void {
    $f = derePgccHistoricalFixture();
    match ($case) {
        'reference-start' => $f['reference']['accounts']['OLDREF']['valid_from'] = '2026-02-01',
        'reference-end' => $f['reference']['accounts']['OLDREF']['valid_to'] = '2026-08-30',
        'tax-start' => $f['tax']['codes']['110112002']['valid_from'] = '2026-02-01',
        'tax-end' => $f['tax']['codes']['110112002']['valid_to'] = '2026-08-30',
        'parent-start' => $f['plan']['accounts'][0]['valid_from'] = '2026-02-01',
        'description' => $f['plan']['accounts'][2]['descCta'] = '',
        'future' => $f['plan']['accounts'][2]['valid_from'] = '2026-10-01',
    };
    expect(derePgccProject($f, derePgccProjectionIdentity(), true)['reason'])->toBe('RTC_DERE_PGCC_'.$reason);
})->with([
    ['reference-start', 'REFERENCE_ACCOUNT_NOT_EFFECTIVE'], ['reference-end', 'REFERENCE_ACCOUNT_NOT_EFFECTIVE'],
    ['tax-start', 'TAX_CODE_NOT_EFFECTIVE'], ['tax-end', 'TAX_CODE_NOT_EFFECTIVE'],
    ['parent-start', 'PARENT_ACCOUNT_HISTORY_GAP'], ['description', 'ANALYTICAL_DESCRIPTION_OR_TAX_CODE_INVALID'],
    ['future', 'DECLARATION_ACCOUNT_PERIOD_INVALID_OR_FUTURE'],
]);

it('requires the mixed origin to cover the entire historical split interval', function (string $case, ?string $reason): void {
    $f = derePgccHistoricalFixture();
    $original = $f['plan']['accounts'][2];
    $f['plan']['accounts'][2]['indCta'] = 'S';
    unset($f['plan']['accounts'][2]['codTrib'], $f['plan']['accounts'][2]['indTribISS']);
    $f['plan']['accounts'][] = [...$original, 'cCta' => 'OLD001', 'cDbrMista' => '001', 'nivelCta' => 3];
    if ($case === 'start-gap') {
        $f['plan']['accounts'][2]['valid_from'] = '2026-02-01';
    } elseif ($case === 'end-gap') {
        $f['plan']['accounts'][2]['valid_to'] = '2026-08-30';
    }
    $result = derePgccProject($f, derePgccProjectionIdentity(), true);
    if ($reason !== null) {
        expect($result['reason'])->toBe('RTC_DERE_PGCC_'.$reason);
    } else {
        expect($result['status'])->toBe('PROJECTED')->and(count($result['qualification']['accounts']))->toBe(4)
            ->and($result['qualification']['required_auxiliary_events'])->toBe(['D1121']);
    }
})->with([
    ['covered', null], ['start-gap', 'MIXED_ACCOUNT_HISTORY_GAP'], ['end-gap', 'MIXED_ACCOUNT_HISTORY_GAP'],
]);

it('projects the original PGCC with synthetic and analytical accounts against the unchanged official XSD', function (): void {
    $result = derePgccProject(derePgccFixture(), derePgccProjectionIdentity());
    expect($result['status'])->toBe('PROJECTED')->and($result['signature_created'])->toBeFalse();
    $root = dirname(__DIR__, 3).'/resources/fiscal/sources/official/';
    $validation = (new DereXmlSchemaValidator)->validate($result['xml'], $root.'dere-observed-20261002-06-arquivos-xsd-v-1-2-0.zip',
        'c750f7d522400e5bd93b7ddd1d1d0d8108cd033eb250c1f10011ac57cc732818', $result['schema'],
        $root.'w3c-xmldsig-core-schema-observed-20261002.xsd', 'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
    $this->assertSame('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE', $validation['reason'], json_encode($validation, JSON_THROW_ON_ERROR));
    $xml = new DOMDocument;
    $xml->loadXML($result['xml']);
    expect($xml->getElementsByTagName('infoConta')->length)->toBe(2)
        ->and($xml->getElementsByTagName('codTrib')->length)->toBe(1)
        ->and($xml->getElementsByTagName('iniValid')->item(0)->textContent)->toBe('2026-01-01')
        ->and($xml->getElementsByTagName('novaValidade')->length)->toBe(0);
});

it('requires registration calendar and generation proof before projecting PGCC', function (string $case, string $reason): void {
    $identity = derePgccProjectionIdentity();
    match ($case) {
        'registration' => $identity['contributor_qualified'] = false,
        'calendar' => $identity['dere_start_qualified'] = false,
        'supplier' => $identity['contributor_supplier_id'] = 'different',
        'start' => $identity['dere_start_on'] = '2026-02-01',
        'registration-start' => $identity['contributor_valid_from'] = '2026-02-01',
        'registration-end' => $identity['contributor_valid_to'] = '2025-12-31',
        'observed' => $identity['observed_on'] = '2026-10-03',
        'generated' => $identity['generated_at'] = '2026-10-03T12:00:00.000-03:00',
        'malformed-date' => $identity['generated_at'] = '2026-02-30T12:00:00.000-03:00',
        'sequence' => $identity['event_sequence'] = 0,
    };
    expect(derePgccProject(derePgccFixture(), $identity)['reason'])->toBe('RTC_DERE_PGCC_'.$reason);
})->with([
    ['registration', 'REGISTRATION_OR_START_UNPROVEN'], ['calendar', 'REGISTRATION_OR_START_UNPROVEN'], ['supplier', 'REGISTRATION_OR_START_UNPROVEN'],
    ['start', 'REGISTRATION_OR_START_DATE_CONFLICT'], ['registration-start', 'REGISTRATION_OR_START_DATE_CONFLICT'],
    ['registration-end', 'REGISTRATION_OR_START_DATE_CONFLICT'], ['observed', 'REGISTRATION_OR_START_DATE_CONFLICT'],
    ['generated', 'GENERATION_DATE_CONFLICT'], ['malformed-date', 'GENERATION_DATE_CONFLICT'], ['sequence', 'EVENT_IDENTITY_INVALID'],
]);

it('does not silently drop historical accounts from a complete PGCC declaration', function (): void {
    $f = derePgccFixture();
    $f['plan']['accounts'][] = [...$f['plan']['accounts'][1], 'cCtaInterna' => 'OLD', 'cCta' => 'OLD000', 'valid_to' => '2026-08-31'];
    expect(derePgccProject($f, derePgccProjectionIdentity())['reason'])->toBe('RTC_DERE_PGCC_HISTORICAL_ACCOUNT_DECLARATION_REVIEW_REQUIRED');
});

it('qualifies hierarchy membership and derives auxiliary obligations from explicit dated code rows', function (): void {
    $result = derePgccResult(derePgccFixture());
    expect($result['status'])->toBe('RESOLVED')->and($result['required_auxiliary_events'])->toBe(['D1121'])->and($result['production_ready'])->toBeFalse();
});

it('handles synthetic mixed accounts and their analytical splits with the same origin reference and parent', function (): void {
    $fixture = derePgccFixture();
    $original = $fixture['plan']['accounts'][1];
    $fixture['plan']['accounts'][1]['indCta'] = 'S';
    unset($fixture['plan']['accounts'][1]['codTrib'], $fixture['plan']['accounts'][1]['indTribISS']);
    $fixture['plan']['accounts'][] = [...$original, 'cCta' => 'CASH001', 'cDbrMista' => '001', 'nivelCta' => 3];
    expect(derePgccResult($fixture)['status'])->toBe('RESOLVED');
    $fixture['plan']['accounts'][2]['cCtaRef'] = '1100000002';
    expect(derePgccResult($fixture)['reason'])->toBe('RTC_DERE_PGCC_MIXED_ACCOUNT_SPLIT_CONFLICT');
});

it('excludes inactive historical accounts without requiring their obsolete reference mapping', function (): void {
    $fixture = derePgccFixture();
    $fixture['plan']['accounts'][] = [...$fixture['plan']['accounts'][1], 'cCtaInterna' => 'OLD', 'cCta' => 'OLD000',
        'valid_to' => '2026-08-31', 'cCtaRef' => 'OBSOLETE'];
    expect(count(derePgccResult($fixture)['accounts']))->toBe(2);
});

it('rejects unproven sources invalid hierarchy and missing temporal membership', function (string $case, string $reason): void {
    $f = derePgccFixture();
    match ($case) {
        'source' => $f['reference']['qualified'] = false,
        'plan-kind' => $f['reference']['planoCtaRef'] = '4',
        'future' => $f['reference']['observed_on'] = '2026-10-03',
        'concat' => $f['plan']['accounts'][1]['cCta'] = 'CASH',
        'duplicate' => $f['plan']['accounts'][] = $f['plan']['accounts'][1],
        'reference' => $f['reference']['accounts']['1111000008']['valid_to'] = '2026-08-31',
        'tax' => $f['tax']['codes']['110112001']['valid_to'] = '2026-08-31',
        'tax-short' => $f['plan']['accounts'][1]['codTrib'] = '110112',
        'aux' => $f['tax']['codes']['110112001']['auxiliary_requirements']['D1106'] = null,
        'desc' => $f['plan']['accounts'][1]['descCta'] = '',
        'root-tax' => $f['plan']['accounts'][0]['codTrib'] = '110112001',
        'parent' => $f['plan']['accounts'][1]['cCtaSup'] = 'ABSENT',
        'cycle' => $f['plan']['accounts'][1]['cCtaSup'] = 'CASH000',
        'root-parent' => $f['plan']['accounts'][0]['cCtaSup'] = 'CASH000',
    };
    expect(derePgccResult($f)['reason'])->toBe('RTC_DERE_PGCC_'.$reason);
})->with([
    ['source', 'SOURCES_OR_PLAN_UNQUALIFIED'], ['plan-kind', 'SOURCES_OR_PLAN_UNQUALIFIED'], ['future', 'SOURCE_OBSERVED_IN_FUTURE'],
    ['concat', 'ACCOUNT_IDENTITY_OR_FIELDS_INVALID'], ['duplicate', 'ACCOUNT_IDENTITY_OR_FIELDS_INVALID'],
    ['reference', 'REFERENCE_ACCOUNT_NOT_EFFECTIVE'], ['tax', 'TAX_CODE_NOT_EFFECTIVE'], ['tax-short', 'ANALYTICAL_DESCRIPTION_OR_TAX_CODE_INVALID'],
    ['aux', 'AUXILIARY_REQUIREMENTS_UNPROVEN'], ['desc', 'ANALYTICAL_DESCRIPTION_OR_TAX_CODE_INVALID'],
    ['root-tax', 'SYNTHETIC_TAX_FIELDS_FORBIDDEN'], ['parent', 'PARENT_ACCOUNT_INVALID'], ['cycle', 'PARENT_ACCOUNT_INVALID'], ['root-parent', 'ROOT_ACCOUNT_INVALID'],
]);
