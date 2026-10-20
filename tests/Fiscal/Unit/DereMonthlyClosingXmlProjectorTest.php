<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DereContributorXmlProjector;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;
use App\Modules\Fiscal\NFSe\DerePgccXmlProjector;

/** @return array<string, mixed> */
function dereMonthlyFixture(bool $zero = false): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $month = json_decode(file_get_contents($root.'scenarios/dere-local-2026.10.02-15/qualified-trial-balance.json'), true, 512, JSON_THROW_ON_ERROR);
    $month['deductions'] = json_decode(file_get_contents($root.'scenarios/dere-local-2026.10.02-16/qualified-deductions-initial.json'), true, 512, JSON_THROW_ON_ERROR)['deductions'];
    $month['prerequisites'] = ['supplier_id' => '11111111000191', 'period_end' => '2026-09-30', 'pgcc_source_hash' => str_repeat('b', 64),
        'contributor_registration_qualified' => true, 'auxiliary_obligations_qualified' => true,
        'contributor_source_hash' => str_repeat('d', 64), 'obligations_source_hash' => str_repeat('e', 64),
        'existing_closing' => false, 'negative_base_use_requested' => false, 'D1106_required' => false, 'D2101_required' => false, 'D1121_required' => true];
    if ($zero) {
        unset($month['deductions']);
        array_pop($month['book']['accounts']);
        array_pop($month['book']['pgcc_accounts']);
    }

    return $month;
}

/** @return array<string, mixed> */
function dereMonthlyOperation(bool $zero = false, bool $negative = false): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.02-16.json');
    $input = json_decode(file_get_contents($root.'scenarios/consumption-phase4b-2026.10.02-16/financial-borrower-credit-loan-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    if ($zero) {
        array_pop($input['facts']['rtc.financial_ledger'][0]['entries']);
    }
    if ($negative) {
        $input['facts']['rtc.financial_ledger'][0]['entries'][2]['amount'] = '1200.00';
    }
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);
    $result = (new RtcScenarioRunService($factory))->evaluate($knowledge, $scenario)->toArray();

    return array_column($result['trace'], null, 'stage')['FINANCIAL_OPERATION_AND_BASE'];
}

/** @return array<string, mixed> */
function dereMonthlyRules(): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $codes = json_decode(file_get_contents($root.'catalogs/rtc-codes-2026.10.02-16.json'), true, 512, JSON_THROW_ON_ERROR);

    return ['balance' => $codes['dere_trial_balance_projection'], 'deductions' => $codes['dere_deduction_projection'],
        'closing' => ['contract' => DereMonthlyClosingXmlProjector::CONTRACT, 'status' => 'ACTIVE', 'published_at' => '2026-10-02',
            'source_hash' => 'b52cb2cf96b176d303d64aeea0bab16e7d35bcc739478f6fc8a26b0512415feb']];
}

/** Synthetic dated catalogs to exercise integration; no official reference classification approval.
 * @return array<string, mixed>
 */
function dereMonthlyPgccFixture(): array
{
    $month = dereMonthlyFixture();
    $rules = dereMonthlyRules();
    $rules['closing']['pgcc_qualification_required'] = true;
    $source = ['qualified' => true, 'source_hash' => str_repeat('f', 64), 'valid_from' => '2026-01-01', 'observed_on' => '2026-10-01'];
    $reference = [...$source, 'planoCtaRef' => '1', 'accounts' => []];
    $taxCodes = [...$source, 'codes' => []];
    $accounts = [];
    foreach ($month['book']['accounts'] as $i => &$balance) {
        $internal = $balance['cCta'];
        $balance['cCta'] = $internal.'000';
        $nature = ['4', '1', '5'][$i];
        $ref = 'FIXTURE'.$i;
        $parent = 'ROOT'.$nature.'000';
        $taxCode = '10000000'.($i + 1);
        $reference['accounts'][$ref] = ['valid_from' => '2026-01-01'];
        $taxCodes['codes'][$taxCode] = ['valid_from' => '2026-01-01',
            'auxiliary_requirements' => ['D1106' => false, 'D1121' => true, 'D2101' => false]];
        $accounts[] = ['cCtaInterna' => 'ROOT'.$nature, 'cDbrMista' => '000', 'cCta' => $parent, 'indCta' => 'S',
            'nivelCta' => 1, 'nomeCta' => 'Synthetic root', 'natCta' => 'V', 'codNat' => $nature, 'cCtaRef' => $ref, 'valid_from' => '2026-01-01'];
        $accounts[] = ['cCtaInterna' => $internal, 'cDbrMista' => '000', 'cCta' => $internal.'000', 'indCta' => 'A',
            'nivelCta' => 2, 'cCtaSup' => $parent, 'nomeCta' => 'Synthetic '.$internal, 'descCta' => 'Fixture mapping only',
            'natCta' => $balance['natVApur'], 'codNat' => $nature, 'cCtaRef' => $ref, 'codTrib' => $taxCode, 'valid_from' => '2026-01-01'];
        $month['book']['pgcc_accounts'][$i] = ['cCta' => $internal.'000', 'indCta' => 'A', 'codNat' => $nature, 'valid_from' => '2026-01-01'];
    }
    unset($balance);
    $month['pgcc'] = [...$source, 'source_hash' => $month['book']['pgcc_source_hash'], 'complete' => true,
        'supplier_id' => $month['book']['supplier_id'], 'period_end' => $month['book']['period_end'], 'planoCtaRef' => '1', 'freqEncerr' => 'M', 'accounts' => $accounts];
    $rules['closing']['pgcc_catalogs'] = ['reference' => $reference, 'tax_codes' => $taxCodes];

    return ['month' => $month, 'rules' => $rules];
}

it('integrates qualified PGCC hierarchy with the real calculated financial monthly book', function (): void {
    $f = dereMonthlyPgccFixture();
    $result = (new DereMonthlyClosingXmlProjector)->project(dereMonthlyOperation(), $f['month'], new DateTimeImmutable('2026-10-03'), $f['rules']);
    expect($result['status'])->toBe('PROJECTED')->and($result['pgcc_qualification']['required_auxiliary_events'])->toBe(['D1121'])
        ->and($result['balance']['balance']['accounts'][0]['cCta'])->toBe('interest000')->and($result['period_closed'])->toBeFalse();
});

it('reconstructs the original PGCC declaration alongside the monthly prerequisites when required', function (): void {
    $f = dereMonthlyPgccFixture();
    $f['rules']['closing']['pgcc_projection_required'] = true;
    $f['rules']['pgcc_projection'] = ['contract' => DerePgccXmlProjector::CONTRACT,
        'status' => 'ACTIVE', 'published_at' => '2026-10-02', 'source_hash' => str_repeat('a', 64)];
    $f['month']['pgcc_identity'] = [...$f['month']['identity'], 'contributor_qualified' => true, 'dere_start_qualified' => true,
        'contributor_supplier_id' => '11111111000191', 'contributor_source_hash' => str_repeat('b', 64), 'dere_start_source_hash' => str_repeat('c', 64),
        'contributor_valid_from' => '2026-01-01', 'dere_start_on' => '2026-01-01', 'observed_on' => '2026-10-01'];
    $result = (new DereMonthlyClosingXmlProjector)->project(dereMonthlyOperation(), $f['month'], new DateTimeImmutable('2026-10-03'), $f['rules']);
    expect($result['status'])->toBe('PROJECTED')->and($result['pgcc_projection']['status'])->toBe('PROJECTED');
    $root = dirname(__DIR__, 3).'/resources/fiscal/sources/official/';
    $component = $result['pgcc_projection'];
    $validation = (new DereXmlSchemaValidator)->validate($component['xml'],
        $root.'dere-observed-20261002-06-arquivos-xsd-v-1-2-0.zip',
        'c750f7d522400e5bd93b7ddd1d1d0d8108cd033eb250c1f10011ac57cc732818', $component['schema'],
        $root.'w3c-xmldsig-core-schema-observed-20261002.xsd',
        'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
    expect($validation['reason'])->toBe('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE');
    unset($f['month']['pgcc_identity']);
    expect((new DereMonthlyClosingXmlProjector)->project(dereMonthlyOperation(), $f['month'], new DateTimeImmutable('2026-10-03'), $f['rules'])['reason'])
        ->toBe('RTC_DERE_MONTHLY_PGCC_DECLARATION_IDENTITY_UNPROVEN');
});

it('keeps historical PGCC accounts in D1011 without adding obsolete monthly obligations or balances', function (): void {
    $f = dereMonthlyPgccFixture();
    $f['rules']['closing']['pgcc_projection_required'] = true;
    $f['rules']['pgcc_projection'] = ['contract' => DerePgccXmlProjector::CONTRACT,
        'status' => 'ACTIVE', 'published_at' => '2026-10-02', 'source_hash' => str_repeat('a', 64), 'historical_declaration_required' => true];
    $f['month']['pgcc_identity'] = [...$f['month']['identity'], 'contributor_qualified' => true, 'dere_start_qualified' => true,
        'contributor_supplier_id' => '11111111000191', 'contributor_source_hash' => str_repeat('b', 64), 'dere_start_source_hash' => str_repeat('c', 64),
        'contributor_valid_from' => '2026-01-01', 'dere_start_on' => '2026-01-01', 'observed_on' => '2026-10-01'];
    $f['month']['pgcc']['accounts'][] = [...$f['month']['pgcc']['accounts'][1], 'cCtaInterna' => 'OLD', 'cCta' => 'OLD000',
        'cCtaRef' => 'OLDREF', 'codTrib' => '110112002', 'valid_to' => '2026-08-31'];
    $f['rules']['closing']['pgcc_catalogs']['reference']['accounts']['OLDREF'] = ['valid_from' => '2026-01-01', 'valid_to' => '2026-08-31'];
    $f['rules']['closing']['pgcc_catalogs']['tax_codes']['codes']['110112002'] = ['valid_from' => '2026-01-01', 'valid_to' => '2026-08-31',
        'auxiliary_requirements' => ['D1106' => true, 'D1121' => false, 'D2101' => true]];
    $result = (new DereMonthlyClosingXmlProjector)->project(dereMonthlyOperation(), $f['month'], new DateTimeImmutable('2026-10-03'), $f['rules']);
    expect($result['status'])->toBe('PROJECTED')->and(count($result['pgcc_qualification']['accounts']))->toBe(6)
        ->and(count($result['pgcc_projection']['qualification']['accounts']))->toBe(7)
        ->and($result['pgcc_projection']['qualification']['required_auxiliary_events'])->toBe(['D1121'])
        ->and(count($result['balance']['balance']['accounts']))->toBe(3)->and($result['period_closed'])->toBeFalse();
    $root = dirname(__DIR__, 3).'/resources/fiscal/sources/official/';
    foreach ([$result['pgcc_projection'], $result['balance'], $result['deductions'], $result] as $component) {
        $validation = (new DereXmlSchemaValidator)->validate($component['xml'],
            $root.'dere-observed-20261002-06-arquivos-xsd-v-1-2-0.zip',
            'c750f7d522400e5bd93b7ddd1d1d0d8108cd033eb250c1f10011ac57cc732818', $component['schema'],
            $root.'w3c-xmldsig-core-schema-observed-20261002.xsd',
            'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
        expect($validation['reason'])->toBe('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE');
    }
    $f['rules']['closing']['pgcc_catalogs']['reference']['accounts']['OLDREF']['valid_to'] = '2026-08-30';
    expect((new DereMonthlyClosingXmlProjector)->project(dereMonthlyOperation(), $f['month'], new DateTimeImmutable('2026-10-03'), $f['rules'])['reason'])
        ->toBe('RTC_DERE_PGCC_REFERENCE_ACCOUNT_NOT_EFFECTIVE');
});

/** @return array<string, mixed> */
function dereMonthlyContributorFixture(): array
{
    $f = dereMonthlyPgccFixture();
    $f['rules']['closing']['contributor_projection_required'] = true;
    $f['rules']['closing']['pgcc_projection_required'] = true;
    $f['rules']['contributor_projection'] = ['contract' => DereContributorXmlProjector::CONTRACT, 'status' => 'ACTIVE',
        'published_at' => '2026-10-01', 'source_hash' => str_repeat('a', 64)];
    $f['rules']['pgcc_projection'] = ['contract' => DerePgccXmlProjector::CONTRACT, 'status' => 'ACTIVE',
        'published_at' => '2026-10-01', 'source_hash' => str_repeat('a', 64), 'historical_declaration_required' => true];
    $f['rules']['closing']['contributor_catalogs'] = ['servFinanc' => ['qualified' => true, 'source_hash' => str_repeat('a', 64),
        'valid_from' => '2026-01-01', 'observed_on' => '2026-10-01', 'codes' => ['01A' => ['valid_from' => '2026-01-01']]]];
    $f['month']['contributor'] = ['complete' => true, 'supplier_id' => $f['month']['book']['supplier_id'],
        'registered_supplier_id' => $f['month']['book']['supplier_id'], 'source_hash' => $f['month']['prerequisites']['contributor_source_hash'],
        'period_end' => $f['month']['book']['period_end'], 'valid_from' => '2026-01-01', 'observed_on' => '2026-10-01',
        'registration_qualified' => true, 'registration_status' => 'ACTIVE', 'registration_validity' => ['valid_from' => '2026-01-01'],
        'registration_source_hash' => str_repeat('b', 64), 'regimes_qualified' => true, 'regimes_source_hash' => str_repeat('c', 64),
        'tax_nature_qualified' => true, 'tax_nature_source_hash' => str_repeat('d', 64), 'dere_start_qualified' => true,
        'dere_start_source_hash' => str_repeat('e', 64), 'dere_start_on' => '2026-01-01',
        'regTribPrinc' => '1', 'regTribSecund' => [], 'indNatTrib' => '0', 'servFinanc' => ['tpAtividades' => ['01A']]];
    $f['month']['contributor_identity'] = $f['month']['identity'];
    $f['month']['pgcc_identity'] = [...$f['month']['identity'], 'contributor_qualified' => true, 'dere_start_qualified' => true,
        'contributor_supplier_id' => $f['month']['book']['supplier_id'],
        'contributor_source_hash' => $f['month']['contributor']['source_hash'], 'dere_start_source_hash' => str_repeat('e', 64),
        'contributor_valid_from' => '2026-01-01', 'dere_start_on' => '2026-01-01', 'observed_on' => '2026-10-01'];

    return $f;
}

it('reconstructs D1001 and PGCC alongside the financial monthly events with consistent registration evidence', function (): void {
    $f = dereMonthlyContributorFixture();
    $result = (new DereMonthlyClosingXmlProjector)->project(dereMonthlyOperation(), $f['month'], new DateTimeImmutable('2026-10-05'), $f['rules']);
    expect($result['status'])->toBe('PROJECTED')->and($result['contributor_projection']['registration_performed'])->toBeFalse()
        ->and($result['period_closed'])->toBeFalse();
    $root = dirname(__DIR__, 3).'/resources/fiscal/sources/official/';
    foreach ([$result['contributor_projection'], $result['pgcc_projection'], $result['balance'], $result['deductions'], $result] as $component) {
        $validation = (new DereXmlSchemaValidator)->validate($component['xml'],
            $root.'dere-observed-20261002-06-arquivos-xsd-v-1-2-0.zip',
            'c750f7d522400e5bd93b7ddd1d1d0d8108cd033eb250c1f10011ac57cc732818', $component['schema'],
            $root.'w3c-xmldsig-core-schema-observed-20261002.xsd',
            'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
        expect($validation['reason'])->toBe('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE');
    }
});

it('rejects contradictions between local D1001 PGCC and monthly registration prerequisites', function (string $case, string $reason): void {
    $f = dereMonthlyContributorFixture();
    match ($case) {
        'identity' => $f['month']['contributor_identity'] = null,
        'supplier' => $f['month']['contributor']['supplier_id'] = 'different',
        'period' => $f['month']['contributor']['period_end'] = '2026-08-31',
        'hash' => $f['month']['contributor']['source_hash'] = str_repeat('f', 64),
        'pgcc-hash' => $f['month']['pgcc_identity']['contributor_source_hash'] = str_repeat('f', 64),
        'pgcc-start' => $f['month']['pgcc_identity']['contributor_valid_from'] = '2026-02-01',
        'pgcc-calendar' => $f['month']['pgcc_identity']['dere_start_source_hash'] = str_repeat('f', 64),
        'catalog' => $f['rules']['closing']['contributor_catalogs']['servFinanc']['qualified'] = false,
    };
    expect((new DereMonthlyClosingXmlProjector)->project(dereMonthlyOperation(), $f['month'], new DateTimeImmutable('2026-10-05'), $f['rules'])['reason'])
        ->toBe($reason);
})->with([
    ['identity', 'RTC_DERE_MONTHLY_CONTRIBUTOR_SCOPE_OR_CATALOGS_UNPROVEN'], ['supplier', 'RTC_DERE_MONTHLY_CONTRIBUTOR_SCOPE_OR_CATALOGS_UNPROVEN'],
    ['period', 'RTC_DERE_MONTHLY_CONTRIBUTOR_SCOPE_OR_CATALOGS_UNPROVEN'], ['hash', 'RTC_DERE_MONTHLY_CONTRIBUTOR_SCOPE_OR_CATALOGS_UNPROVEN'],
    ['pgcc-hash', 'RTC_DERE_MONTHLY_CONTRIBUTOR_PGCC_VALIDITY_OR_EVIDENCE_CONFLICT'],
    ['pgcc-start', 'RTC_DERE_MONTHLY_CONTRIBUTOR_PGCC_VALIDITY_OR_EVIDENCE_CONFLICT'],
    ['pgcc-calendar', 'RTC_DERE_MONTHLY_CONTRIBUTOR_PGCC_VALIDITY_OR_EVIDENCE_CONFLICT'], ['catalog', 'RTC_DERE_CONTRIBUTOR_ACTIVITY_CATALOG_UNPROVEN'],
]);

it('rejects monthly flags and books that conflict with the newly qualified PGCC', function (string $case, string $reason): void {
    $f = dereMonthlyPgccFixture();
    match ($case) {
        'source' => $f['rules']['closing']['pgcc_catalogs']['reference']['qualified'] = false,
        'scope' => $f['month']['pgcc']['supplier_id'] = 'different',
        'frequency' => $f['month']['pgcc']['freqEncerr'] = 'A',
        'book' => $f['month']['book']['pgcc_accounts'][0]['codNat'] = '5',
        'missing-balance' => array_pop($f['month']['book']['accounts']),
        'aux' => $f['month']['prerequisites']['D1121_required'] = false,
        'duplicate-book' => $f['month']['book']['pgcc_accounts'][] = $f['month']['book']['pgcc_accounts'][0],
    };
    expect((new DereMonthlyClosingXmlProjector)->project(dereMonthlyOperation(), $f['month'], new DateTimeImmutable('2026-10-03'), $f['rules'])['reason'])
        ->toBe($reason);
})->with([
    ['source', 'RTC_DERE_PGCC_SOURCES_OR_PLAN_UNQUALIFIED'], ['scope', 'RTC_DERE_MONTHLY_PGCC_SCOPE_OR_CATALOGS_UNPROVEN'],
    ['frequency', 'RTC_DERE_MONTHLY_PGCC_CLOSING_FREQUENCY_CONFLICT'], ['book', 'RTC_DERE_MONTHLY_PGCC_BOOK_ACCOUNTS_CONFLICT'],
    ['missing-balance', 'RTC_DERE_MONTHLY_PGCC_ANALYTICAL_BALANCE_INCOMPLETE'], ['aux', 'RTC_DERE_MONTHLY_PGCC_AUXILIARY_OBLIGATIONS_CONFLICT'],
    ['duplicate-book', 'RTC_DERE_MONTHLY_PGCC_BOOK_ACCOUNTS_INVALID'],
]);

it('rebuilds local prerequisites and validates the monthly closing body in the unchanged XSD', function (bool $zero, bool $required): void {
    $month = dereMonthlyFixture($zero);
    $month['prerequisites']['D1121_required'] = $required;
    $result = (new DereMonthlyClosingXmlProjector)->project(dereMonthlyOperation($zero), $month, new DateTimeImmutable('2026-10-03'), dereMonthlyRules());
    expect($result['status'])->toBe('PROJECTED')->and($result['period_closed'])->toBeFalse()
        ->and($result['transmission_performed'])->toBeFalse()->and($result['balance']['status'])->toBe('PROJECTED');
    expect($result['deductions'] !== null)->toBe(! $zero);
    $root = dirname(__DIR__, 3).'/resources/fiscal/sources/official/';
    $validation = (new DereXmlSchemaValidator)->validate($result['xml'],
        $root.'dere-observed-20261002-06-arquivos-xsd-v-1-2-0.zip',
        'c750f7d522400e5bd93b7ddd1d1d0d8108cd033eb250c1f10011ac57cc732818', $result['schema'],
        $root.'w3c-xmldsig-core-schema-observed-20261002.xsd',
        'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
    $this->assertSame('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE', $validation['reason'], json_encode($validation, JSON_THROW_ON_ERROR));
    $xml = new DOMDocument;
    $xml->loadXML($result['xml']);
    expect($xml->getElementsByTagName('indInexistDedu')->length)->toBe($zero && $required ? 1 : 0)
        ->and($xml->getElementsByTagName('gUtilizBCN')->length)->toBe(0);
    foreach (array_filter([$result['balance'], $result['deductions']]) as $component) {
        $validation = (new DereXmlSchemaValidator)->validate($component['xml'],
            $root.'dere-observed-20261002-06-arquivos-xsd-v-1-2-0.zip',
            'c750f7d522400e5bd93b7ddd1d1d0d8108cd033eb250c1f10011ac57cc732818', $component['schema'],
            $root.'w3c-xmldsig-core-schema-observed-20261002.xsd',
            'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
        expect($validation['reason'])->toBe('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE');
    }
})->with([[false, true], [true, true], [true, false]]);

it('does not close a calculated negative base without its specific carryforward accounting', function (): void {
    $month = dereMonthlyFixture();
    foreach (['vMovDebt', 'vSaldoFinal', 'vApur'] as $field) {
        $month['book']['accounts'][2][$field] = '1200.00';
    }
    $operation = dereMonthlyOperation(false, true);
    expect($operation['base']['net_base'])->toBe('-200.00');
    $result = (new DereMonthlyClosingXmlProjector)->project($operation, $month, new DateTimeImmutable('2026-10-03'), dereMonthlyRules());
    expect($result['reason'])->toBe('RTC_DERE_MONTHLY_NEGATIVE_BASE_ACCOUNTING_PENDING')->and($result['period_closed'])->toBeFalse();
});

it('rejects unproven monthly prerequisites and conflicting reconstructed detail', function (string $case, string $reason): void {
    $month = dereMonthlyFixture();
    match ($case) {
        'contributor' => $month['prerequisites']['contributor_registration_qualified'] = false,
        'obligations' => $month['prerequisites']['auxiliary_obligations_qualified'] = false,
        'pgcc' => $month['prerequisites']['pgcc_source_hash'] = str_repeat('a', 64),
        'closed' => $month['prerequisites']['existing_closing'] = true,
        'negative-use' => $month['prerequisites']['negative_base_use_requested'] = true,
        'unknown' => $month['prerequisites']['D1106_required'] = null,
        'investment' => $month['prerequisites']['D1106_required'] = true,
        'public-debt' => $month['prerequisites']['D2101_required'] = true,
        'deduction' => $month['deductions'] = null,
        'deduction-scope' => $month['deductions']['document_id'] = 'different',
        'balance' => $month['book']['accounts'][0]['vSaldoFinal'] = '1.00',
    };
    expect((new DereMonthlyClosingXmlProjector)->project(dereMonthlyOperation(), $month, new DateTimeImmutable('2026-10-03'), dereMonthlyRules())['reason'])
        ->toBe($reason);
})->with([
    ['contributor', 'RTC_DERE_MONTHLY_PREREQUISITES_UNPROVEN_OR_CONFLICTING'], ['obligations', 'RTC_DERE_MONTHLY_PREREQUISITES_UNPROVEN_OR_CONFLICTING'],
    ['pgcc', 'RTC_DERE_MONTHLY_PREREQUISITES_UNPROVEN_OR_CONFLICTING'], ['closed', 'RTC_DERE_MONTHLY_PREREQUISITES_UNPROVEN_OR_CONFLICTING'],
    ['negative-use', 'RTC_DERE_MONTHLY_PREREQUISITES_UNPROVEN_OR_CONFLICTING'], ['unknown', 'RTC_DERE_MONTHLY_AUXILIARY_OBLIGATIONS_UNPROVEN'],
    ['investment', 'RTC_DERE_MONTHLY_ADDITIONAL_AUXILIARY_EVENTS_PENDING'], ['public-debt', 'RTC_DERE_MONTHLY_ADDITIONAL_AUXILIARY_EVENTS_PENDING'],
    ['deduction', 'RTC_DERE_MONTHLY_DEDUCTIONS_REQUIRED'], ['deduction-scope', 'RTC_DERE_MONTHLY_DEDUCTIONS_AND_BALANCE_SCOPE_CONFLICT'],
    ['balance', 'RTC_DERE_BALANCE_FINAL_BALANCE_OR_NATURE_CONFLICT'],
]);
