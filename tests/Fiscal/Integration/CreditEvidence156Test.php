<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CreditEvidence156;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

require_once dirname(__DIR__, 3).'/scripts/agent_a/handoff156_fixture.php';

it('feeds definitive import document and dated tax payments through the IMPORT credit flow', function (): void {
    $result = evaluate156(import156(), knowledge156());
    $trace = array_column($result['trace'], null, 'stage');
    expect($trace)->toHaveKey('IMPORT_CREDIT');
    $credit = $trace['IMPORT_CREDIT'];
    expect($credit['document_evidence156']['import']['document_id'])->toBe(import156()['facts']['rtc.input_document_id'])
        ->and($credit['document_evidence156']['import']['credit_on'])->toBe('2026-10-01')
        ->and($credit['document_evidence156']['authenticated'])->toBeFalse()
        ->and($credit['ibs']['status'])->toBe('ALLOWED')->and($credit['cbs']['status'])->toBe('ALLOWED')
        ->and($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'])->toContain('RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN');
});

it('refuses rehashed contradictions in definitive import and payment records', function (string $case): void {
    $input = import156();
    $record = &$input['facts']['rtc.import_definitive_credit_records'][0]['record'];
    match ($case) {
        'document' => $record['document_id'] = 'OTHER',
        'invoice' => $record['foreign_invoice_id'] = 'OTHER',
        'importer' => $record['importer_id'] = 'OTHER',
        'acquirer' => $record['acquirer_id'] = 'OTHER',
        'draft' => $record['definitive'] = false,
        'invalid' => $record['document_valid'] = false,
        'document date' => $record['document_on'] = '2026-10-02',
        'future credit' => $record['credit_on'] = '2027-01-01',
        'paid before issue' => $record['payments'][0]['paid_on'] = '2026-09-24',
        'paid after credit' => $record['payments'][0]['paid_on'] = '2026-10-02',
        'payer' => $record['payments'][0]['payer_id'] = 'OTHER',
        'payment document' => $record['payments'][0]['document_id'] = 'OTHER',
        'amount' => $record['payments'][0]['amount'] = '999.00',
        'float' => $record['payments'][0]['amount'] = 1.0,
        'unpaid' => $record['payments'][0]['extinguished'] = false,
        'duplicate tax' => $record['payments'][1]['tax'] = 'IBS',
        'missing tax' => array_pop($record['payments']),
        'blank proof' => $record['payments'][0]['evidence_id'] = ' ',
        'future observed' => $record['observed_at'] = '2027-01-01T00:00:00Z',
        'invalid date' => $record['document_on'] = '2026-02-30',
        'extinction evidence' => $record['extinction_evidence_id'] = 'OTHER',
    };
    $input['facts']['rtc.import_definitive_credit_records'][0]['record_hash'] = CanonicalJson::hash($record);
    $result = evaluate156($input, knowledge156());
    expect($result['status'])->toBe('ACTION_REQUIRED')->and(array_column($result['trace'], 'stage'))->not->toContain('IMPORT_CREDIT');
})->with(['document', 'invoice', 'importer', 'acquirer', 'draft', 'invalid', 'document date', 'future credit',
    'paid before issue', 'paid after credit', 'payer', 'payment document', 'amount', 'float', 'unpaid', 'duplicate tax',
    'missing tax', 'blank proof', 'future observed', 'invalid date', 'extinction evidence']);

it('requires books and refuses rehashed documentary linkage contradictions', function (string $case): void {
    $input = import156();
    $record = &$input['facts']['rtc.credit_book_entries'][0]['record'];
    match ($case) {
        'document' => $record['document_id'] = 'OTHER', 'owner' => $record['taxpayer_id'] = 'OTHER',
        'amount' => $record['ibs_amount'] = '999.00', 'not written' => $record['written'] = false,
        'book' => $record['book_id'] = '', 'entry' => $record['entry_id'] = '',
        'date' => $record['booked_on'] = '2026-09-24', 'future' => $record['booked_on'] = '2027-01-01',
        'hash' => $record['book_id'] = 'TAMPERED', 'duplicate' => null, 'missing' => null,
    };
    if ($case !== 'hash') {
        $input['facts']['rtc.credit_book_entries'][0]['record_hash'] = CanonicalJson::hash($record);
    }
    unset($record);
    if ($case === 'duplicate') {
        $input['facts']['rtc.credit_book_entries'][] = $input['facts']['rtc.credit_book_entries'][0];
    } elseif ($case === 'missing') {
        unset($input['facts']['rtc.credit_book_entries']);
    }
    $result = evaluate156($input, knowledge156());
    expect($result['status'])->toBe('ACTION_REQUIRED')->and(array_column($result['trace'], 'stage'))->not->toContain('IMPORT_CREDIT');
})->with(['document', 'owner', 'amount', 'not written', 'book', 'entry', 'date', 'future', 'hash', 'duplicate', 'missing']);

it('keeps accepted import behavior unchanged without the new gate and rejects malformed policy', function (): void {
    $input = import156();
    unset($input['facts']['rtc.credit_book_entries'], $input['facts']['rtc.import_definitive_credit_records']);
    $old = evaluate156($input, knowledge156(enabled: false));
    expect(array_column($old['trace'], 'stage'))->toContain('IMPORT_CREDIT')->not->toContain('CREDIT_DOCUMENT_BOOK156');
    $knowledge = knowledge156();
    $knowledge['documents']['rtc_code_catalog'][CreditEvidence156::KEY]['automatic_posting'] = true;
    expect(evaluate156($input, $knowledge)['reason_codes'])->toContain('RTC_CREDIT_EVIDENCE_POLICY_INVALID');
});

it('binds each supplier pool credit to the book before preserving or annulling benefits', function (string $profile, string $expected): void {
    $knowledge = knowledge156('bundles/consumption-phase4b-2026.10.02-3.json');
    $input = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/consumption-phase4b-2026.10.02-3/supplier-credit-period-'.$profile.'-2026.json')), true, 512, JSON_THROW_ON_ERROR);
    $input['knowledge_at'] = '2026-11-05T10:00:00Z';
    $period = $input['facts']['rtc.supplier_credit_period'][0];
    $input['facts']['rtc.credit_book_entries'] = [];
    foreach ($period['credit_pool'] as $credit) {
        $input['facts']['rtc.credit_book_entries'][] = evidence156(['book_id' => 'BOOK.SUPPLIER156', 'entry_id' => $credit['id'],
            'document_id' => $credit['document_id'], 'taxpayer_id' => $period['supplier_id'], 'written' => true,
            'document_on' => $credit['appropriation_date'], 'booked_on' => $credit['appropriation_date'],
            'ibs_amount' => $credit['ibs_amount'], 'cbs_amount' => $credit['cbs_amount']]);
    }
    $result = evaluate156($input, $knowledge);
    $trace = array_column($result['trace'], null, 'stage');
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['official'])->toBeFalse()
        ->and($trace['SYNTHETIC_ARITHMETIC156']['candidate_status'])->toBe('VALID')
        ->and($trace['SYNTHETIC_ARITHMETIC156']['candidate_decision']['supplier_credit_period']['taxes']['IBS']['annulled_credit'])->toBe($expected);
    $input['facts']['rtc.credit_book_entries'][0]['record']['taxpayer_id'] = 'OTHER';
    $input['facts']['rtc.credit_book_entries'][0]['record_hash'] = CanonicalJson::hash($input['facts']['rtc.credit_book_entries'][0]['record']);
    expect(evaluate156($input, $knowledge)['reason_codes'])->toContain('RTC_CREDIT_BOOK_LINK_CONFLICT');
})->with([['mixed', '30.00'], ['zero-preserved', '0.00'], ['reduced-preserved', '0.00'],
    ['export-preserved', '0.00'], ['books-preserved', '0.00'], ['broadcast-preserved', '0.00'], ['fully-immune', '100.00']]);

it('keeps the book link independent from acquisition treatment denial under article 49', function (string $treatment): void {
    $input = handoff155Input();
    unset($input['facts']['rtc.acquired_regime']);
    $input['knowledge_at'] = '2026-11-05T10:00:00Z';
    $input['facts']['rtc.input_ibs_amount'] = '0.00';
    $input['facts']['rtc.input_cbs_amount'] = '0.00';
    $input['facts']['rtc.credit_book_entries'] = [book156($input['facts'])];
    foreach ($input['facts']['rtc.input_tax_treatments'] as &$row) {
        $row['treatment'] = $treatment;
    }
    unset($row);
    $result = evaluate156($input, knowledge156('bundles/consumption-phase4b-2026.10.02-1.json'));
    $trace = array_column($result['trace'], null, 'stage');
    $decision = $trace['SYNTHETIC_ARITHMETIC156']['candidate_decision'];
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['official'])->toBeFalse()
        ->and($decision['ibs']['credit']['status'])->toBe('DENIED')->and($decision['cbs']['credit']['amount'])->toBe('0.00');
})->with(['IMMUNITY', 'EXEMPT', 'ZERO_RATE', 'DEFERRAL']);
