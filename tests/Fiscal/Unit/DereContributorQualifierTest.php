<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DereContributorXmlProjector;

/** Explicit synthetic evidence and dated domain catalogs. @return array<string, mixed> */
function dereContributorFixture(): array
{
    $validity = ['valid_from' => '2026-01-01'];
    $catalog = [...$validity, 'qualified' => true, 'source_hash' => str_repeat('c', 64), 'observed_on' => '2026-10-01',
        'codes' => ['01A' => $validity]];
    $profile = [...$validity, 'period_end' => '2026-09-30', 'observed_on' => '2026-10-01', 'complete' => true,
        'supplier_id' => '11111111000191', 'registered_supplier_id' => '11111111000191', 'registration_status' => 'ACTIVE',
        'registration_qualified' => true, 'regimes_qualified' => true, 'tax_nature_qualified' => true, 'dere_start_qualified' => true,
        'registration_validity' => $validity, 'dere_start_on' => '2026-01-01', 'source_hash' => str_repeat('a', 64),
        'registration_source_hash' => str_repeat('b', 64), 'regimes_source_hash' => str_repeat('d', 64),
        'tax_nature_source_hash' => str_repeat('e', 64), 'dere_start_source_hash' => str_repeat('f', 64),
        'regTribPrinc' => '1', 'regTribSecund' => [], 'indNatTrib' => '0', 'servFinanc' => ['tpAtividades' => ['01A']]];

    return ['profile' => $profile, 'catalogs' => ['servFinanc' => $catalog, 'plAssistSaude' => $catalog, 'prognosticos' => $catalog,
        'UF' => [...$catalog, 'codes' => ['35' => $validity]]],
        'identity' => ['generated_at' => '2026-10-01T12:00:00.000-03:00', 'event_sequence' => 1],
        'rule' => ['contract' => DereContributorXmlProjector::CONTRACT, 'status' => 'ACTIVE', 'published_at' => '2026-10-01',
            'source_hash' => 'b52cb2cf96b176d303d64aeea0bab16e7d35bcc739478f6fc8a26b0512415feb']];
}

/** @param array<string, mixed> $f @return array<string, mixed> */
function dereContributorProject(array $f): array
{
    return (new DereContributorXmlProjector)->project($f['profile'], $f['identity'], $f['catalogs'], new DateTimeImmutable('2026-10-05'), $f['rule']);
}

it('projects complete original D1001 profiles into the unchanged official schema', function (string $principal, array $secondary, bool $accredited): void {
    $f = dereContributorFixture();
    $f['profile']['regTribPrinc'] = $principal;
    $f['profile']['regTribSecund'] = $secondary;
    unset($f['profile']['servFinanc']);
    foreach (['1' => 'servFinanc', '2' => 'plAssistSaude', '3' => 'prognosticos'] as $regime => $group) {
        if (in_array((string) $regime, [$principal, ...$secondary], true)) {
            $f['profile'][$group] = ['tpAtividades' => ['01A']];
        }
    }
    if ($accredited) {
        $f['profile']['prognosticos']['UFsCredenc'] = ['35'];
        $f['profile']['accreditations_qualified'] = true;
        $f['profile']['accreditations_source_hash'] = str_repeat('b', 64);
    }
    $result = dereContributorProject($f);
    expect($result['status'])->toBe('PROJECTED')->and($result['registration_performed'])->toBeFalse();
    $root = dirname(__DIR__, 3).'/resources/fiscal/sources/official/';
    $validation = (new DereXmlSchemaValidator)->validate($result['xml'],
        $root.'dere-observed-20261002-06-arquivos-xsd-v-1-2-0.zip',
        'c750f7d522400e5bd93b7ddd1d1d0d8108cd033eb250c1f10011ac57cc732818', $result['schema'],
        $root.'w3c-xmldsig-core-schema-observed-20261002.xsd',
        'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
    $this->assertSame('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE', $validation['reason'], json_encode($validation, JSON_THROW_ON_ERROR));
    $xml = new DOMDocument;
    $xml->loadXML($result['xml']);
    expect($xml->getElementsByTagName('regTribPrinc')->item(0)->textContent)->toBe($principal)
        ->and($xml->getElementsByTagName('regTribSecund')->length)->toBe(count($secondary))
        ->and($xml->getElementsByTagName('tpOper')->item(0)->textContent)->toBe('1')
        ->and($xml->getElementsByTagName('tpAmb')->item(0)->textContent)->toBe('2');
})->with([['1', [], false], ['2', [], false], ['3', [], true], ['9', [], false], ['9', ['1', '2', '3'], true]]);

it('blocks inconsistent registration dates regimes activities and external proof', function (string $case, string $reason): void {
    $f = dereContributorFixture();
    match ($case) {
        'incomplete' => $f['profile']['complete'] = false,
        'supplier' => $f['profile']['registered_supplier_id'] = 'different',
        'registration' => $f['profile']['registration_qualified'] = false,
        'classification' => $f['profile']['regimes_qualified'] = false,
        'nature-proof' => $f['profile']['tax_nature_qualified'] = false,
        'calendar' => $f['profile']['dere_start_qualified'] = false,
        'hash' => $f['profile']['registration_source_hash'] = '',
        'status' => $f['profile']['registration_status'] = 'VOID',
        'start' => $f['profile']['dere_start_on'] = '2026-02-01',
        'observed' => $f['profile']['observed_on'] = '2026-10-06',
        'registration-end' => $f['profile']['registration_validity']['valid_to'] = '2026-09-29',
        'end' => $f['profile']['valid_to'] = '2026-09-29',
        'month' => $f['profile']['period_end'] = '2026-09-29',
        'date' => $f['profile']['valid_from'] = '2026-02-30',
        'principal' => $f['profile']['regTribPrinc'] = '4',
        'secondary-principal' => $f['profile']['regTribSecund'] = ['1'],
        'secondary-duplicate' => $f['profile']['regTribSecund'] = ['2', '2'],
        'nature' => $f['profile']['indNatTrib'] = '2',
        'group' => $f['profile']['plAssistSaude'] = ['tpAtividades' => ['01A']],
        'activities' => $f['profile']['servFinanc']['tpAtividades'] = [],
        'duplicate-activity' => $f['profile']['servFinanc']['tpAtividades'] = ['01A', '01A'],
        'catalog' => $f['catalogs']['servFinanc']['qualified'] = false,
        'catalog-observed' => $f['catalogs']['servFinanc']['observed_on'] = '2026-10-06',
        'activity' => $f['profile']['servFinanc']['tpAtividades'] = ['99Z'],
        'activity-start' => $f['catalogs']['servFinanc']['codes']['01A']['valid_from'] = '2026-02-01',
        'activity-end' => $f['catalogs']['servFinanc']['codes']['01A']['valid_to'] = '2026-09-29',
        'generation' => $f['identity']['generated_at'] = '2026-10-06T12:00:00.000-03:00',
        'generation-before-proof' => $f['identity']['generated_at'] = '2026-09-30T12:00:00.000-03:00',
        'sequence' => $f['identity']['event_sequence'] = 0,
        'rule-date' => $f['rule']['published_at'] = '2026-02-30',
    };
    expect(dereContributorProject($f)['reason'])->toBe('RTC_DERE_CONTRIBUTOR_'.$reason);
})->with([
    ['incomplete', 'PROFILE_OR_REGISTRATION_UNPROVEN'], ['supplier', 'PROFILE_OR_REGISTRATION_UNPROVEN'],
    ['registration', 'PROFILE_OR_REGISTRATION_UNPROVEN'], ['classification', 'PROFILE_OR_REGISTRATION_UNPROVEN'],
    ['nature-proof', 'PROFILE_OR_REGISTRATION_UNPROVEN'], ['calendar', 'PROFILE_OR_REGISTRATION_UNPROVEN'],
    ['hash', 'EVIDENCE_HASH_INVALID'], ['status', 'REGISTRATION_STATUS_REVIEW_REQUIRED'], ['start', 'VALIDITY_OR_START_CONFLICT'],
    ['observed', 'VALIDITY_OR_START_CONFLICT'], ['registration-end', 'VALIDITY_OR_START_CONFLICT'], ['end', 'VALIDITY_OR_START_CONFLICT'],
    ['month', 'VALIDITY_OR_START_CONFLICT'], ['date', 'DATE_INVALID'], ['principal', 'REGIMES_OR_TAX_NATURE_INVALID'],
    ['secondary-principal', 'REGIMES_OR_TAX_NATURE_INVALID'], ['secondary-duplicate', 'REGIMES_OR_TAX_NATURE_INVALID'],
    ['nature', 'REGIMES_OR_TAX_NATURE_INVALID'], ['group', 'REGIME_GROUP_CONFLICT'], ['activities', 'ACTIVITIES_INVALID'],
    ['duplicate-activity', 'ACTIVITIES_INVALID'], ['catalog', 'ACTIVITY_CATALOG_UNPROVEN'], ['catalog-observed', 'ACTIVITY_CATALOG_UNPROVEN'],
    ['activity', 'ACTIVITY_NOT_EFFECTIVE'], ['activity-start', 'ACTIVITY_NOT_EFFECTIVE'], ['activity-end', 'ACTIVITY_NOT_EFFECTIVE'],
    ['generation', 'GENERATION_DATE_CONFLICT'], ['generation-before-proof', 'GENERATION_DATE_CONFLICT'],
    ['sequence', 'EVENT_IDENTITY_INVALID'], ['rule-date', 'RULE_UNAVAILABLE'],
]);

it('requires dated accreditation evidence and actual UF catalog membership', function (): void {
    $f = dereContributorFixture();
    $f['profile']['regTribPrinc'] = '3';
    unset($f['profile']['servFinanc']);
    $f['profile']['prognosticos'] = ['tpAtividades' => ['01A'], 'UFsCredenc' => ['35']];
    expect(dereContributorProject($f)['reason'])->toBe('RTC_DERE_CONTRIBUTOR_ACCREDITATIONS_UNPROVEN');
    $f['profile']['accreditations_qualified'] = true;
    $f['profile']['accreditations_source_hash'] = str_repeat('b', 64);
    $f['profile']['prognosticos']['UFsCredenc'] = ['99'];
    expect(dereContributorProject($f)['reason'])->toBe('RTC_DERE_CONTRIBUTOR_ACCREDITATION_UF_NOT_EFFECTIVE');
});
