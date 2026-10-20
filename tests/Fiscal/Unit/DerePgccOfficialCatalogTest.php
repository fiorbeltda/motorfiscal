<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DerePgccQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DerePgccXmlProjector;

/** @return array<string, mixed> */
function dereOfficialPgccFixture(): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $read = static fn (string $path): array => json_decode(file_get_contents($root.$path), true, 512, JSON_THROW_ON_ERROR);
    $month = $read('scenarios/dere-local-2026.10.05-19/qualified-monthly-closing.json');
    $codes = $read('catalogs/rtc-codes-2026.10.05-19.json');

    return ['plan' => $month['pgcc'], 'identity' => $month['pgcc_identity'], 'rule' => $codes['dere_pgcc_projection'],
        'reference' => $codes['dere_monthly_closing_projection']['pgcc_catalogs']['reference'],
        'tax' => $codes['dere_monthly_closing_projection']['pgcc_catalogs']['tax_codes']];
}

/** @param array<string, mixed> $fixture @return array<string, mixed> */
function dereOfficialPgccResolve(array $fixture, bool $history = true): array
{
    return (new DerePgccQualifier)->resolve($fixture['plan'], $fixture['reference'], $fixture['tax'],
        new DateTimeImmutable('2026-10-05T15:00:00Z'), $history, $fixture['rule']['qualification_policy']);
}

/** @param array<string, mixed> $fixture */
function dereOfficialPgccRefresh(array &$fixture): void
{
    foreach ($fixture['plan']['accounts'] as &$row) {
        if (! isset($row['mapping_evidence'])) {
            continue;
        }
        $row['mapping_evidence']['value']['mapping'] = array_intersect_key($row, array_flip(['cCta', 'cCtaRef', 'codTrib', 'natCta', 'codNat']));
        unset($row['mapping_evidence']['source_hash']);
        $row['mapping_evidence']['source_hash'] = CanonicalJson::hash($row['mapping_evidence']);
    }
}

it('derives complete auxiliary lists from official RN and blocks cross-document missing codes', function (): void {
    $f = dereOfficialPgccFixture();
    expect(count($f['tax']['codes']))->toBe(756)
        ->and(array_map('count', $f['tax']['auxiliary_codes']))->toBe(['D1106' => 4, 'D1121' => 34, 'D2101' => 2])
        ->and($f['tax']['cross_catalog_unresolved'])->toBe(['111270002', '111290001', '411031281']);
    expect(dereOfficialPgccResolve($f)['required_auxiliary_events'])->toBe([]);
});

it('activates only the versioned original local PGCC with explicit synthetic applicability', function (): void {
    $f = dereOfficialPgccFixture();
    $result = (new DerePgccXmlProjector)->project($f['plan'], $f['identity'], $f['reference'], $f['tax'],
        new DateTimeImmutable('2026-10-05T15:00:00Z'), $f['rule']);
    expect($result['status'])->toBe('PROJECTED')->and(count($result['qualification']['accounts']))->toBe(6)
        ->and($result['production_ready'])->toBeFalse()->and($result['signature_created'])->toBeFalse();
    $xml = new DOMDocument;
    $xml->loadXML($result['xml']);
    expect($xml->getElementsByTagName('infoConta')->length)->toBe(6)
        ->and($xml->getElementsByTagName('codTrib')->length)->toBe(3);
});

it('derives auxiliary requirements by codTrib rather than caller-provided flags', function (string $code, string $event): void {
    $f = dereOfficialPgccFixture();
    $index = $event === 'D2101' || $event === 'D1106' ? 4 : 5;
    $f['plan']['accounts'][$index]['codTrib'] = $code;
    dereOfficialPgccRefresh($f);
    expect(dereOfficialPgccResolve($f)['required_auxiliary_events'])->toBe([$event]);
})->with([['411011105', 'D1121'], ['110113001', 'D2101'], ['120130001', 'D1106']]);

it('requires official membership immutable catalogs and independent dated mapping proofs', function (string $case, string $reason): void {
    $f = dereOfficialPgccFixture();
    switch ($case) {
        case 'catalog-tamper': $f['reference']['accounts']['7110599002']['title'] = 'changed';
            break;
        case 'tax-tamper': $f['tax']['auxiliary_codes']['D1121'][] = '411011201';
            break;
        case 'catalog-future':
            $f['reference']['observed_on'] = '2026-10-06';
            $f['rule']['qualification_policy']['reference_catalog_sha256'] = CanonicalJson::hash($f['reference']);
            break;
        case 'publication-future':
            $f['tax']['published_on'] = '2026-10-06';
            $f['rule']['qualification_policy']['tax_catalog_sha256'] = CanonicalJson::hash($f['tax']);
            break;
        case 'list-incomplete':
            $f['tax']['auxiliary_lists_complete'] = false;
            $f['rule']['qualification_policy']['tax_catalog_sha256'] = CanonicalJson::hash($f['tax']);
            break;
        case 'institution-missing': unset($f['plan']['institution_evidence']);
            break;
        case 'institution-type': $f['plan']['institution_type'] = 'PAYMENT_INSTITUTION';
            break;
        case 'mapping-missing': unset($f['plan']['accounts'][3]['mapping_evidence']);
            break;
        case 'plan-observed-before-proof': $f['plan']['observed_on'] = '2026-10-01';
            break;
        case 'mapping-tamper': $f['plan']['accounts'][3]['mapping_evidence']['value']['mapping']['codTrib'] = '311011102';
            break;
        case 'reference-absent': $f['plan']['accounts'][3]['cCtaRef'] = '9999999999';
            break;
        case 'nature': $f['plan']['accounts'][3]['codNat'] = '5';
            break;
        case 'tax-nature': $f['plan']['accounts'][3]['codTrib'] = '411011201';
            dereOfficialPgccRefresh($f);
            break;
        default: $f['plan']['accounts'][3]['codTrib'] = $case;
            dereOfficialPgccRefresh($f);
            break;
    }
    expect(dereOfficialPgccResolve($f)['reason'])->toBe('RTC_DERE_PGCC_'.$reason);
})->with([
    ['catalog-tamper', 'OFFICIAL_CATALOG_HASH_CONFLICT'], ['tax-tamper', 'OFFICIAL_CATALOG_HASH_CONFLICT'],
    ['catalog-future', 'OFFICIAL_CATALOG_NOT_KNOWN'], ['publication-future', 'OFFICIAL_CATALOG_NOT_KNOWN'],
    ['list-incomplete', 'OFFICIAL_CATALOG_SCOPE_UNPROVEN'], ['institution-missing', 'INSTITUTION_EVIDENCE_UNPROVEN'],
    ['institution-type', 'INSTITUTION_EVIDENCE_UNPROVEN'], ['mapping-missing', 'ACCOUNT_MAPPING_EVIDENCE_UNPROVEN'],
    ['plan-observed-before-proof', 'INSTITUTION_EVIDENCE_UNPROVEN'],
    ['mapping-tamper', 'ACCOUNT_MAPPING_EVIDENCE_UNPROVEN'], ['reference-absent', 'OFFICIAL_REFERENCE_ACCOUNT_ABSENT'],
    ['nature', 'REFERENCE_ACCOUNT_NATURE_CONFLICT'], ['tax-nature', 'TAX_CODE_ACCOUNT_NATURE_CONFLICT'],
    ['399999999', 'OFFICIAL_TAX_CODE_ABSENT'], ['111270002', 'OFFICIAL_TAX_CODE_ABSENT'],
    ['111290001', 'OFFICIAL_TAX_CODE_ABSENT'], ['411031281', 'OFFICIAL_TAX_CODE_ABSENT'],
]);

it('does not generate an official-catalog PGCC before its qualified plan was observed', function (): void {
    $f = dereOfficialPgccFixture();
    $f['identity']['generated_at'] = '2026-10-04T15:00:00.000-03:00';
    $r = (new DerePgccXmlProjector)->project($f['plan'], $f['identity'], $f['reference'], $f['tax'],
        new DateTimeImmutable('2026-10-05T15:00:00Z'), $f['rule']);
    expect($r['reason'])->toBe('RTC_DERE_PGCC_GENERATION_DATE_CONFLICT');
});

it('requires the full historical mapping interval and only derives auxiliaries from active accounts', function (): void {
    $f = dereOfficialPgccFixture();
    $old = $f['plan']['accounts'][5];
    $old['cCtaInterna'] = 'old';
    $old['cCta'] = 'old000';
    $old['codTrib'] = '411011105';
    $old['valid_to'] = '2026-08-31';
    $f['plan']['accounts'][] = $old;
    dereOfficialPgccRefresh($f);
    expect(count(dereOfficialPgccResolve($f)['accounts']))->toBe(7)
        ->and(dereOfficialPgccResolve($f)['required_auxiliary_events'])->toBe([])
        ->and(count(dereOfficialPgccResolve($f, false)['accounts']))->toBe(6);
    $f['plan']['accounts'][6]['mapping_evidence']['valid_from'] = '2026-02-01';
    dereOfficialPgccRefresh($f);
    expect(dereOfficialPgccResolve($f)['reason'])->toBe('RTC_DERE_PGCC_ACCOUNT_MAPPING_EVIDENCE_UNPROVEN')
        ->and(dereOfficialPgccResolve($f, false)['status'])->toBe('RESOLVED');
});

it('rejects future stale wrong-supplier and malformed evidence even when its content hash is valid', function (string $field, string $value): void {
    $f = dereOfficialPgccFixture();
    $f['plan']['accounts'][3]['mapping_evidence'][$field] = $value;
    dereOfficialPgccRefresh($f);
    expect(dereOfficialPgccResolve($f)['status'])->toBe('ACTION_REQUIRED');
})->with([['observed_on', '2026-10-06'], ['valid_from', '2026-02-01'], ['valid_to', '2026-09-29'],
    ['valid_to', '2025-12-31'], ['supplier_id', '22222222000191'], ['valid_from', '2026-02-30']]);
