<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DereFinancialXmlProjector;

/** @return array<string, mixed> */
function dereFinancialProjectionFixture(): array
{
    $hash = str_repeat('a', 64);

    return ['operation' => ['status' => 'RESOLVED',
        'qualification' => ['regime' => 'SPECIFIC_FINANCIAL_ART181', 'supplier_id' => '11111111000191', 'document_id' => 'monthly-document', 'operation_category' => 'CREDIT'],
        'base' => ['net_base' => '800.00', 'period_end' => '2026-09-30']],
        'tax' => ['status' => 'RESOLVED', 'supplier_id' => '11111111000191', 'base' => '800.00', 'period_end' => '2026-09-30',
            'components' => ['IBS_STATE' => ['percent' => '0.10', 'nominal_amount' => '0.80', 'source_hash' => $hash],
                'IBS_MUNICIPAL' => ['percent' => '0.00', 'nominal_amount' => '0.00', 'source_hash' => $hash],
                'CBS' => ['percent' => '0.90', 'nominal_amount' => '7.20', 'source_hash' => $hash]]],
        'proof' => ['supplier_id' => '11111111000191', 'document_id' => 'monthly-document', 'period_end' => '2026-09-30',
            'generated_at' => '2026-10-01T12:00:00.000-03:00', 'observed_on' => '2026-10-01', 'event_sequence' => 1,
            'allocation_complete' => true, 'allocation_qualified' => true, 'net_bases_qualified' => true, 'iss_incidence_qualified' => true,
            'parties_qualified' => true, 'iss_incident' => false, 'allocation_source_hash' => $hash,
            'allocation_evidence_id' => 'FIXTURE.ALLOCATION', 'iss_incidence_evidence_id' => 'FIXTURE.ISS', 'parties_evidence_id' => 'FIXTURE.PARTIES',
            'groups' => [['registration_type' => '1', 'acquirer_id' => '22222222000191', 'municipality' => '3550308', 'series' => 1,
                'operations' => [['transaction_id' => 'transaction1', 'occurred_at' => '2026-09-20T12:00:00.000-03:00', 'net_base' => '500.00', 'foreign_currency_referenced' => false],
                    ['transaction_id' => 'transaction2', 'occurred_at' => '2026-09-21T12:00:00.000-03:00', 'net_base' => '300.00', 'foreign_currency_referenced' => false]]]]],
        'rule' => ['contract' => DereFinancialXmlProjector::CONTRACT, 'status' => 'ACTIVE', 'published_at' => '2026-09-05', 'source_hash' => $hash,
            'base_codes' => ['CREDIT' => ['NO_ISS' => ['code' => '1010', 'event' => 'evtOperFinanc', 'event_code' => '2211',
                'namespace' => 'http://www.dere.gov.br/schemas/evtOperFinanc/v0_0_1']]]]];
}

/** @param array<string, mixed> $fixture
 * @return array<string, mixed>
 */
function dereFinancialProjected(array $fixture): array
{
    return (new DereFinancialXmlProjector)->project($fixture['operation'], $fixture['tax'], $fixture['proof'], new DateTimeImmutable('2026-10-02'), $fixture['rule']);
}

it('projects reconciled detail from qualified allocation and passes unsigned official body schema checks', function (): void {
    $result = dereFinancialProjected(dereFinancialProjectionFixture());
    expect($result['status'])->toBe('PROJECTED')->and($result['allocated_base'])->toBe('800.00')
        ->and($result['transaction_count'])->toBe(2)->and($result['production_ready'])->toBeFalse();
    $root = dirname(__DIR__, 3).'/resources/fiscal/sources/official/';
    $validation = (new DereXmlSchemaValidator)->validate($result['xml'], $root.'dere-observed-20261002-06-arquivos-xsd-v-1-2-0.zip',
        'c750f7d522400e5bd93b7ddd1d1d0d8108cd033eb250c1f10011ac57cc732818', 'evtOperFinanc-v0_0_1.xsd',
        $root.'w3c-xmldsig-core-schema-observed-20261002.xsd', 'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
    expect($validation['reason'])->toBe('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE');
});

it('rejects unreconciled monthly totals unqualified allocation duplicate operations and future evidence', function (string $case, string $reason): void {
    $fixture = dereFinancialProjectionFixture();
    match ($case) {
        'scope' => $fixture['proof']['document_id'] = 'other',
        'total' => $fixture['proof']['groups'][0]['operations'][0]['net_base'] = '499.00',
        'qualification' => $fixture['proof']['allocation_complete'] = false,
        'duplicate' => $fixture['proof']['groups'][0]['operations'][1]['transaction_id'] = 'transaction1',
        'period' => $fixture['proof']['groups'][0]['operations'][1]['occurred_at'] = '2026-10-01T12:00:00.000-03:00',
        'future' => $fixture['proof']['observed_on'] = '2026-10-03',
        'rate' => $fixture['tax']['components']['CBS']['source_hash'] = 'unknown',
        'rounding' => $fixture['tax']['components']['IBS_STATE']['nominal_amount'] = '0.81',
    };
    $result = dereFinancialProjected($fixture);
    expect($result['reason'])->toBe('RTC_DERE_'.$reason)->and($result['xml'])->toBeNull();
})->with([['scope', 'SCOPE_CONFLICT'], ['total', 'MONTHLY_ALLOCATION_BASE_MISMATCH'],
    ['qualification', 'QUALIFICATION_MISSING:allocation_complete'], ['duplicate', 'TRANSACTION_DUPLICATE_OR_INVALID'],
    ['period', 'OPERATION_TIME_CONFLICT'], ['future', 'OBSERVATION_IN_FUTURE'], ['rate', 'COMPONENT_RATE_UNPROVEN'],
    ['rounding', 'MONTHLY_ROUNDING_CONFLICT:IBS_STATE']]);
