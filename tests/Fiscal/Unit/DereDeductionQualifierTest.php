<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DereDeductionQualifier;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DereDeductionXmlProjector;

/** @return array<string, mixed> */
function dereDeductionFixture(bool $continued = false): array
{
    $doc = ['tpDFe' => '03', 'chDFe' => str_repeat('1', 44), 'dtEmi' => $continued ? '2026-08-20' : '2026-09-20', 'tpAtiv' => '01',
        'vOper' => '1000.00', 'vDedTotal' => '800.00', 'vDed' => '200.00',
        'document_validity_qualified' => true, 'document_source_hash' => str_repeat('b', 64), 'evidence_id' => 'FIXTURE.DFE',
        'financial_entry_ids' => ['funding'],
        'items' => [['nItem' => '1', 'vItem' => '1000.00', 'vItemDedTotal' => '800.00', 'vItemDed' => '200.00', 'source_item_qualified' => true]]];
    if ($continued) {
        unset($doc['vDedTotal'], $doc['items'][0]['vItemDedTotal']);
        $doc['previous_deduction'] = ['source_hash' => str_repeat('c', 64), 'chDFe' => $doc['chDFe'], 'dtEmi' => $doc['dtEmi'],
            'supplier_id' => '11111111000191', 'tpDFe' => '03', 'tpAtiv' => '01', 'vOper' => '1000.00',
            'registered_period' => '2026-08', 'through_period' => '2026-08', 'history_complete' => true,
            'vDedTotal' => '800.00', 'accumulated_deduction' => '600.00', 'itemized' => true,
            'items' => ['1' => ['vItem' => '1000.00', 'vItemDedTotal' => '800.00', 'accumulated_deduction' => '600.00']]];
    }

    return ['supplier_id' => '11111111000191', 'document_id' => 'dps-1', 'period_end' => '2026-09-30', 'observed_on' => '2026-10-01',
        'documents_qualified' => true, 'deduction_allocation_complete' => true, 'monthly_period_closed_previously' => false,
        'source_hash' => str_repeat('a', 64), 'documents' => [$doc]];
}

/** @param array<string, mixed> $proof
 * @return array<string, mixed>
 */
function dereDeductionResult(array $proof): array
{
    return (new DereDeductionQualifier)->resolve(dereDeductionOperation(), $proof, new DateTimeImmutable('2026-10-03'), dereDeductionRule());
}

/** @return array<string, mixed> */
function dereDeductionOperation(): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $result = (new RtcScenarioRunService(new FiscalEngineFactory))->execute($root, 'bundles/consumption-phase4b-2026.10.02-15.json',
        $root.'/scenarios/consumption-phase4b-2026.10.02-15/financial-borrower-credit-loan-2026.json')->result->toArray();

    return array_column($result['trace'], null, 'stage')['FINANCIAL_OPERATION_AND_BASE'];
}

/** @return array<string, mixed> */
function dereDeductionRule(): array
{
    return ['strategy' => 'DERE_DOCUMENT_DEDUCTIONS_1_2_0', 'status' => 'ACTIVE', 'published_at' => '2026-10-02',
        'source_hash' => 'b52cb2cf96b176d303d64aeea0bab16e7d35bcc739478f6fc8a26b0512415feb'];
}

it('projects initial and continued D1121 bodies against unchanged official schemas', function (bool $continued): void {
    $projection = (new DereDeductionXmlProjector)->project(dereDeductionOperation(), dereDeductionFixture($continued),
        ['generated_at' => '2026-10-01T12:00:00.000-03:00', 'event_sequence' => 1], new DateTimeImmutable('2026-10-03'), dereDeductionRule());
    expect($projection['status'])->toBe('PROJECTED')->and($projection['signature_created'])->toBeFalse();
    $root = dirname(__DIR__, 3).'/resources/fiscal/sources/official/';
    $validation = (new DereXmlSchemaValidator)->validate($projection['xml'],
        $root.'dere-observed-20261002-06-arquivos-xsd-v-1-2-0.zip',
        'c750f7d522400e5bd93b7ddd1d1d0d8108cd033eb250c1f10011ac57cc732818', $projection['schema'],
        $root.'w3c-xmldsig-core-schema-observed-20261002.xsd',
        'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
    $this->assertSame('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE', $validation['reason'], json_encode($validation, JSON_THROW_ON_ERROR));
    expect($validation['unsigned_candidate_structure_checked'])->toBeTrue()->and($validation['production_ready'])->toBeFalse();
    $xml = new DOMDocument;
    $xml->loadXML($projection['xml']);
    expect($xml->getElementsByTagName('vDedTotal')->length)->toBe($continued ? 0 : 1)
        ->and($xml->getElementsByTagName('vItemDedTotal')->length)->toBe($continued ? 0 : 1)
        ->and($xml->getElementsByTagName('finEvt')->length)->toBe(0);
})->with([false, true]);

it('qualifies initial and continued document deductions against actual financial entries', function (bool $continued): void {
    $result = dereDeductionResult(dereDeductionFixture($continued));
    expect($result['status'])->toBe('RESOLVED')->and($result['linked_financial_deductions'])->toBe(1)
        ->and($result['posting_performed'])->toBeFalse()->and($result['documents'][0]['vDed'])->toBe('200.00');
    expect(isset($result['documents'][0]['vDedTotal']))->toBe(! $continued);
})->with([false, true]);

it('rejects missing qualification document ceilings item details and incomplete linkage', function (string $case, string $reason): void {
    $proof = dereDeductionFixture();
    match ($case) {
        'qualification' => $proof['documents_qualified'] = false,
        'closed' => $proof['monthly_period_closed_previously'] = true,
        'identity' => $proof['documents'][0]['chDFe'] = 'bad-key',
        'duplicate' => $proof['documents'][] = $proof['documents'][0],
        'future' => $proof['documents'][0]['dtEmi'] = '2026-10-01',
        'ceiling' => $proof['documents'][0]['vDedTotal'] = '1001.00',
        'items' => $proof['documents'][0]['items'] = [],
        'item-ceiling' => $proof['documents'][0]['items'][0]['vItemDedTotal'] = '199.00',
        'item-sum' => $proof['documents'][0]['items'][0]['vItemDed'] = '199.00',
        'link' => $proof['documents'][0]['financial_entry_ids'] = ['principal'],
        'link-sum' => $proof['documents'][0]['financial_entry_ids'] = [],
        'observation' => $proof['observed_on'] = '2026-10-04',
    };
    expect(dereDeductionResult($proof)['reason'])->toBe('RTC_DERE_DEDUCTIONS_'.$reason);
})->with([
    ['qualification', 'DOCUMENT_QUALIFICATION_OR_ORIGINAL_PERIOD_UNPROVEN'], ['closed', 'DOCUMENT_QUALIFICATION_OR_ORIGINAL_PERIOD_UNPROVEN'],
    ['identity', 'DOCUMENT_IDENTITY_OR_EVIDENCE_INVALID'], ['duplicate', 'DOCUMENT_IDENTITY_OR_EVIDENCE_INVALID'],
    ['future', 'DOCUMENT_ISSUED_AFTER_PERIOD'], ['ceiling', 'DOCUMENT_DEDUCTION_CEILING_EXCEEDED'],
    ['items', 'ITEM_DETAIL_REQUIRED_OR_INVALID'], ['item-ceiling', 'ITEM_DEDUCTION_CEILING_EXCEEDED'], ['item-sum', 'ITEM_SUM_CONFLICT'],
    ['link', 'FINANCIAL_LINK_UNKNOWN_OR_DUPLICATE'], ['link-sum', 'FINANCIAL_LINK_AMOUNT_CONFLICT'], ['observation', 'PERIOD_OR_OBSERVATION_CONFLICT'],
]);

it('rejects reopening an account outside issuance month and exceeding historical ceilings', function (string $case, string $reason): void {
    $proof = dereDeductionFixture(true);
    match ($case) {
        'missing-history' => $proof['documents'][0]['previous_deduction'] = null,
        'redeclaration' => $proof['documents'][0]['vDedTotal'] = '800.00',
        'stale-history' => $proof['documents'][0]['previous_deduction']['through_period'] = '2026-07',
        'other-document' => $proof['documents'][0]['previous_deduction']['chDFe'] = str_repeat('2', 44),
        'ceiling' => $proof['documents'][0]['previous_deduction']['accumulated_deduction'] = '600.01',
        'item-ceiling' => $proof['documents'][0]['previous_deduction']['items']['1']['accumulated_deduction'] = '600.01',
        'item-redeclaration' => $proof['documents'][0]['items'][0]['vItemDedTotal'] = '800.00',
    };
    expect(dereDeductionResult($proof)['reason'])->toBe('RTC_DERE_DEDUCTIONS_'.$reason);
})->with([
    ['missing-history', 'PREVIOUS_DOCUMENT_ACCOUNT_UNPROVEN'], ['redeclaration', 'PREVIOUS_DOCUMENT_ACCOUNT_UNPROVEN'],
    ['stale-history', 'PREVIOUS_DOCUMENT_ACCOUNT_UNPROVEN'], ['other-document', 'PREVIOUS_DOCUMENT_ACCOUNT_UNPROVEN'],
    ['ceiling', 'DOCUMENT_DEDUCTION_CEILING_EXCEEDED'], ['item-ceiling', 'PREVIOUS_ITEM_SUM_CONFLICT'],
    ['item-redeclaration', 'PREVIOUS_ITEM_ACCOUNT_UNPROVEN'],
]);
