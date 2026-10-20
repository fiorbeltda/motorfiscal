<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DereTrialBalanceQualifier;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DereTrialBalanceXmlProjector;

/** @return array<string, mixed> */
function dereTrialBalanceFixture(): array
{
    $accounts = [];
    $plan = [];
    foreach (['interest' => ['1000.00', 'C', '5'], 'principal' => ['10000.00', 'D', '1'], 'funding' => ['200.00', 'D', '4']] as $id => [$amount, $nature, $code]) {
        $accounts[] = ['cCta' => $id, 'natSaldoInic' => $nature, 'vSaldoInic' => '0.00',
            'vMovDebt' => $nature === 'D' ? $amount : '0.00', 'vMovCred' => $nature === 'C' ? $amount : '0.00',
            'natSaldoFinal' => $nature, 'vSaldoFinal' => $amount, 'natVApur' => $nature, 'vApur' => $amount,
            'financial_entry_ids' => [$id], 'previous_balance' => ['period' => '2026-08', 'amount' => '0.00', 'nature' => $nature, 'source_hash' => str_repeat('c', 64)]];
        $plan[] = ['cCta' => $id, 'indCta' => 'A', 'codNat' => $code, 'valid_from' => '2026-01-01'];
    }

    return ['supplier_id' => '11111111000191', 'document_id' => 'dps-1', 'period_end' => '2026-09-30', 'observed_on' => '2026-10-01',
        'analytical_book_complete' => true, 'pgcc_complete' => true, 'pgcc_account_mapping_qualified' => true, 'opening_balances_qualified' => true,
        'book_source_hash' => str_repeat('a', 64), 'pgcc_source_hash' => str_repeat('b', 64), 'book_evidence_id' => 'FIXTURE.BOOK', 'pgcc_evidence_id' => 'FIXTURE.PGCC',
        'first_reporting_period' => false, 'closing_frequency' => 'M', 'accounts' => $accounts, 'pgcc_accounts' => $plan];
}

/** @param array<string, mixed> $book
 * @return array<string, mixed>
 */
function dereTrialBalanceResult(array $book): array
{
    return (new DereTrialBalanceQualifier)->resolve(dereTrialBalanceOperation(), $book, new DateTimeImmutable('2026-10-02'), dereTrialBalanceRule());
}

/** @return array<string, mixed> */
function dereTrialBalanceOperation(): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $result = (new RtcScenarioRunService(new FiscalEngineFactory))->execute($root, 'bundles/consumption-phase4b-2026.10.02-14.json',
        $root.'/scenarios/consumption-phase4b-2026.10.02-14/financial-borrower-credit-loan-2026.json')->result->toArray();

    return array_column($result['trace'], null, 'stage')['FINANCIAL_OPERATION_AND_BASE'];
}

/** @return array<string, mixed> */
function dereTrialBalanceRule(): array
{
    return ['strategy' => 'DERE_ANALYTICAL_TRIAL_BALANCE_1_2_0', 'status' => 'ACTIVE', 'published_at' => '2026-09-05',
        'source_hash' => 'b52cb2cf96b176d303d64aeea0bab16e7d35bcc739478f6fc8a26b0512415feb'];
}

it('qualifies analytical balances and links every actual financial calculation entry including excluded principal', function (): void {
    $result = dereTrialBalanceResult(dereTrialBalanceFixture());
    expect($result['status'])->toBe('RESOLVED')->and($result['linked_financial_entries'])->toBe(3)
        ->and($result['accounts'][1]['vApur'])->toBe('10000.00')
        ->and($result['accounts'][1]['natSaldoFinal'])->toBe('D')->and($result['posting_performed'])->toBeFalse();
});

it('applies cross adjustment arithmetic without subtracting fiscal adjustments from accounting balances', function (): void {
    $book = dereTrialBalanceFixture();
    $book['accounts'][2] = [...$book['accounts'][2], 'vMovDebt' => '250.00', 'vMovCred' => '50.00', 'vAjusteDebt' => '60.00', 'vAjusteCred' => '10.00'];
    expect(dereTrialBalanceResult($book)['status'])->toBe('RESOLVED');
    $book['accounts'][2]['vSaldoFinal'] = '140.00';
    expect(dereTrialBalanceResult($book)['reason'])->toBe('RTC_DERE_BALANCE_FINAL_BALANCE_OR_NATURE_CONFLICT');
});

it('preserves opening nature at a zero closing balance and checks intermediate sign changes', function (): void {
    $book = dereTrialBalanceFixture();
    $book['pgcc_accounts'][] = ['cCta' => 'extra', 'indCta' => 'A', 'codNat' => '4', 'valid_from' => '2026-01-01'];
    $book['accounts'][] = ['cCta' => 'extra', 'natSaldoInic' => 'C', 'vSaldoInic' => '0.00', 'vMovDebt' => '100.00', 'vMovCred' => '100.00',
        'natSaldoFinal' => 'C', 'vSaldoFinal' => '0.00', 'natVApur' => 'D', 'vApur' => '100.00', 'financial_entry_ids' => []];
    expect(dereTrialBalanceResult($book)['status'])->toBe('RESOLVED');
    $book['accounts'][3]['natSaldoFinal'] = 'D';
    expect(dereTrialBalanceResult($book)['reason'])->toBe('RTC_DERE_BALANCE_FINAL_BALANCE_OR_NATURE_CONFLICT');
});

it('rejects conflicting PGCC previous balances fiscal movement and link completeness', function (string $case, string $reason): void {
    $book = dereTrialBalanceFixture();
    match ($case) {
        'synthetic' => $book['pgcc_accounts'][0]['indCta'] = 'S',
        'expired' => $book['pgcc_accounts'][0]['valid_to'] = '2026-09-29',
        'prior' => $book['accounts'][1]['previous_balance']['period'] = '2026-07',
        'opening' => $book['accounts'][1]['previous_balance']['amount'] = '1.00',
        'reset' => $book['accounts'][0]['vSaldoInic'] = '1.00',
        'adjustment' => $book['accounts'][0]['vAjusteCred'] = '1001.00',
        'assessment' => $book['accounts'][2]['vApur'] = '199.00',
        'nature' => $book['accounts'][0]['natVApur'] = null,
        'duplicate' => $book['accounts'][2]['financial_entry_ids'] = ['interest'],
        'missing-link' => $book['accounts'][1]['financial_entry_ids'] = [],
        'future' => $book['observed_on'] = '2026-10-03',
        'qualification' => $book['pgcc_account_mapping_qualified'] = false,
    };
    $result = dereTrialBalanceResult($book);
    expect($result['reason'])->toBe('RTC_DERE_BALANCE_'.$reason);
})->with([['synthetic', 'PGCC_ACCOUNT_DUPLICATE_SYNTHETIC_OR_NOT_EFFECTIVE'], ['expired', 'PGCC_ACCOUNT_DUPLICATE_SYNTHETIC_OR_NOT_EFFECTIVE'],
    ['prior', 'PATRIMONIAL_PREVIOUS_BALANCE_UNPROVEN'], ['opening', 'PATRIMONIAL_OPENING_CONFLICT'],
    ['reset', 'RESULT_ACCOUNT_OPENING_NOT_ZERO'], ['adjustment', 'ADJUSTMENT_EXCEEDS_MOVEMENT'], ['assessment', 'ASSESSMENT_MOVEMENT_CONFLICT'],
    ['nature', 'ASSESSMENT_NATURE_REQUIRED'], ['duplicate', 'FINANCIAL_LINK_UNKNOWN_OR_DUPLICATE'], ['missing-link', 'FINANCIAL_LEDGER_NOT_COMPLETELY_LINKED'],
    ['future', 'MONTH_END_OR_OBSERVATION_CONFLICT'], ['qualification', 'QUALIFICATION_MISSING:pgcc_account_mapping_qualified']]);

it('projects actual qualified financial balances against the unchanged official D1101 schema', function (): void {
    $result = (new DereTrialBalanceXmlProjector)->project(dereTrialBalanceOperation(), dereTrialBalanceFixture(),
        ['generated_at' => '2026-10-01T12:00:00.000-03:00', 'event_sequence' => 1], new DateTimeImmutable('2026-10-02'), dereTrialBalanceRule());
    expect($result['status'])->toBe('PROJECTED')->and($result['signature_created'])->toBeFalse();
    $root = dirname(__DIR__, 3).'/resources/fiscal/sources/official/';
    $validation = (new DereXmlSchemaValidator)->validate($result['xml'],
        $root.'dere-observed-20261002-06-arquivos-xsd-v-1-2-0.zip',
        'c750f7d522400e5bd93b7ddd1d1d0d8108cd033eb250c1f10011ac57cc732818', $result['schema'],
        $root.'w3c-xmldsig-core-schema-observed-20261002.xsd',
        'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
    expect($validation['reason'])->toBe('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE')
        ->and($validation['unsigned_candidate_structure_checked'])->toBeTrue()->and($validation['production_ready'])->toBeFalse();
    $doc = new DOMDocument;
    $doc->loadXML($result['xml']);
    expect($doc->getElementsByTagName('infoConta')->length)->toBe(3)
        ->and($doc->getElementsByTagName('nrRecibo')->length)->toBe(0)
        ->and($doc->getElementsByTagName('vApur')->item(1)->textContent)->toBe('10000.00');
});

it('requalifies the book and rejects invalid local monthly event identities', function (): void {
    $projector = new DereTrialBalanceXmlProjector;
    $book = dereTrialBalanceFixture();
    $book['accounts'][0]['vSaldoFinal'] = '1.00';
    $identity = ['generated_at' => '2026-10-01T12:00:00.000-03:00', 'event_sequence' => 1];
    expect($projector->project(dereTrialBalanceOperation(), $book, $identity, new DateTimeImmutable('2026-10-02'), dereTrialBalanceRule())['reason'])
        ->toBe('RTC_DERE_BALANCE_FINAL_BALANCE_OR_NATURE_CONFLICT');
    foreach (['2026-09-30T12:00:00.000-03:00', '2026-10-03T12:00:00.000-03:00', '2026-02-30T12:00:00.000-03:00'] as $date) {
        $identity['generated_at'] = $date;
        expect($projector->project(dereTrialBalanceOperation(), dereTrialBalanceFixture(), $identity, new DateTimeImmutable('2026-10-02'), dereTrialBalanceRule())['reason'])
            ->toBe('RTC_DERE_BALANCE_GENERATION_DATE_CONFLICT');
    }
    $identity['event_sequence'] = 0;
    expect($projector->project(dereTrialBalanceOperation(), dereTrialBalanceFixture(), $identity, new DateTimeImmutable('2026-10-02'), dereTrialBalanceRule())['reason'])
        ->toBe('RTC_DERE_BALANCE_EVENT_IDENTITY_INVALID');
});
