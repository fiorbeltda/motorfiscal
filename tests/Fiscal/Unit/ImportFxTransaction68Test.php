<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ImportFxTransactionQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

/** @return array<string,mixed> */
function fxTransactionFixture68(): array
{
    $root = dirname(__DIR__, 3);
    $native = json_decode((string) file_get_contents($root.'/storage/app/phase4b-import-fx68-integration-20261013/fx-permanent-inputs.json'), true, 512, JSON_THROW_ON_ERROR);
    $case = $native['cases']['usd-direct'];
    $known = new DateTimeImmutable($case['known_at']);
    $facts = ['rtc.gross_amount' => '1000.00', 'rtc.gross_currency' => 'USD', 'rtc.supplier_id' => '11111111000191',
        'rtc.supplier_foreign_nif' => 'US-FOREIGN-123', 'rtc.importer_id' => '22222222000191', 'rtc.document_id' => 'dps-1',
        'rtc.import_document_id' => 'foreign-invoice-1', 'rtc.import_transaction_evidence_id' => 'payment-record-1',
        'operation.amount' => '5179.50', 'rtc.operation_amount_reconciliation_id' => 'SYNTHETIC-FX68-BOUND',
        'rtc.import_fx_source_id' => 'USD', 'rtc.base_adjustments' => []];
    $value = ['supplier_id' => $facts['rtc.supplier_id'], 'supplier_foreign_nif' => $facts['rtc.supplier_foreign_nif'],
        'importer_id' => $facts['rtc.importer_id'], 'document_id' => $facts['rtc.document_id'],
        'foreign_document_id' => $facts['rtc.import_document_id'], 'transaction_evidence_id' => $facts['rtc.import_transaction_evidence_id'],
        'tax_point_date' => '2026-09-25', 'original_amount' => '1000.00', 'original_currency' => 'USD',
        'converted_gross_brl' => '5179.50', 'operation_amount_brl' => '5179.50', 'quote_source_id' => 'USD',
        'quote_source_sha256' => $case['result']['raw_source_sha256']];
    $proof = ['qualified' => true, 'scope' => 'SYNTHETIC_COMPLETE_IMPORT_FX_TRANSACTION_NOT_REMOTE_VERIFICATION',
        'evidence_id' => 'SYNTHETIC-FX68-BOUND', 'observed_at' => $known->modify('-1 second')->format('Y-m-d\TH:i:s.uP'),
        'valid_from' => '2026-09-01', 'valid_to' => '2026-09-30', 'value' => $value];
    $proof['source_hash'] = CanonicalJson::hash($proof);
    $facts['rtc.import_fx_proof'] = $proof;
    $policy = ['contract' => ImportFxTransactionQualifier::CONTRACT, 'status' => 'SANDBOX_CANDIDATE',
        'published_at' => $known->modify('-1 second')->format('Y-m-d\TH:i:s.uP'), 'valid_from' => '2026-01-01', 'valid_to' => '2033-12-31',
        'quote_sources' => $case['trusted_source_inputs']];
    $policy['source_hash'] = CanonicalJson::hash($policy);

    return ['facts' => $facts, 'policy' => $policy, 'known' => $known, 'tax_point' => '2026-09-25'];
}

it('binds local FX to operation and documents while rejecting inconsistent proofs', function (string $case): void {
    $input = fxTransactionFixture68();
    $facts = &$input['facts'];
    $policy = &$input['policy'];
    $expected = null;
    switch ($case) {
        case 'missing':unset($facts['rtc.import_fx_proof']);
            $expected = 'TRANSACTION_FACTS_REQUIRED';
            break;
        case 'supplier':$facts['rtc.import_fx_proof']['value']['supplier_id'] = '33333333000191';
            $expected = 'TRANSACTION_OPERATION_OR_DOCUMENT_BINDING_CONFLICT';
            break;
        case 'foreign-document':$facts['rtc.import_fx_proof']['value']['foreign_document_id'] = 'other-invoice';
            $expected = 'TRANSACTION_OPERATION_OR_DOCUMENT_BINDING_CONFLICT';
            break;
        case 'brl-operation':$facts['operation.amount'] = '1000.00';
            $facts['rtc.import_fx_proof']['value']['operation_amount_brl'] = '1000.00';
            $expected = 'TRANSACTION_OPERATION_OR_DOCUMENT_BINDING_CONFLICT';
            break;
        case 'proof-hash':$facts['rtc.import_fx_proof']['source_hash'] = str_repeat('0', 64);
            $expected = 'TRANSACTION_PROOF_HASH_CONFLICT';
            break;
        case 'scope':$facts['rtc.import_fx_proof']['scope'] = 'REAL';
            $expected = 'EXPLICIT_LOCAL_TRANSACTION_PROOF_REQUIRED';
            break;
        case 'reconciliation':$facts['rtc.operation_amount_reconciliation_id'] = 'different';
            $expected = 'EXPLICIT_LOCAL_TRANSACTION_PROOF_REQUIRED';
            break;
        case 'future-proof':$facts['rtc.import_fx_proof']['observed_at'] = $input['known']->modify('+1 second')->format('Y-m-d\TH:i:s.uP');
            $expected = 'TRANSACTION_PROOF_NOT_KNOWN';
            break;
        case 'proof-period':$facts['rtc.import_fx_proof']['valid_to'] = '2026-09-24';
            $expected = 'TRANSACTION_PROOF_PERIOD_REQUIRED';
            break;
        case 'adjustment-unit':$facts['rtc.base_adjustments'] = [['kind' => 'INCLUDED_CHARGE', 'amount' => '10.00', 'currency' => 'USD', 'evidence_id' => 'fee']];
            $expected = 'ADJUSTMENT_BRL_UNIT_REQUIRED';
            break;
        case 'future-policy':$policy['published_at'] = $input['known']->modify('+1 second')->format('Y-m-d\TH:i:s.uP');
            $expected = 'POLICY_NOT_KNOWN';
            break;
        case 'revoked':$policy['revoked_at'] = $input['known']->format(DATE_ATOM);
            $expected = 'POLICY_UNQUALIFIED';
            break;
    }
    if (isset($facts['rtc.import_fx_proof']) && $case !== 'proof-hash') {
        unset($facts['rtc.import_fx_proof']['source_hash']);
        $facts['rtc.import_fx_proof']['source_hash'] = CanonicalJson::hash($facts['rtc.import_fx_proof']);
    }
    unset($policy['source_hash']);
    $policy['source_hash'] = CanonicalJson::hash($policy);
    if (isset($facts['rtc.import_fx_proof'])) {
        $facts['rtc.import_fx_proof'] = [$facts['rtc.import_fx_proof']];
    }
    $result = (new ImportFxTransactionQualifier)->qualify($facts, $input['tax_point'], $input['known'], $policy);
    if ($expected === null) {
        expect($result['status'])->toBe('LOCAL_IMPORT_FX_TRANSACTION_BOUND')
            ->and($result['converted_gross_brl'])->toBe('5179.50')
            ->and($result['operation_document_binding_locally_verified'])->toBeTrue()
            ->and($result['document_authenticated'])->toBeFalse()->and($result['credit_appropriated'])->toBeFalse()
            ->and($result['production_ready'])->toBeFalse();
    } else {
        expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('RTC_IMPORT_FX_'.$expected)
            ->and($result['production_ready'])->toBeFalse();
    }
})->with(['valid', 'missing', 'supplier', 'foreign-document', 'brl-operation', 'proof-hash', 'scope', 'reconciliation',
    'future-proof', 'proof-period', 'adjustment-unit', 'future-policy', 'revoked']);
