<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CooperativeCreditTransferQualifier;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @return array{0: array<string, mixed>, 1: mixed} */
function cooperativeNetworkCase(string $name): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.02-10.json');
    $input = json_decode((string) file_get_contents($root.'/scenarios/consumption-integrated-2026.10.02-10/integrated-cooperative-network-transfer-'.$name.'-2026.json'), true, 512, JSON_THROW_ON_ERROR);

    return [$knowledge, $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input)];
}

/** @return array<string, mixed> */
function cooperativeNetworkResult(string $name): array
{
    [$knowledge, $scenario] = cooperativeNetworkCase($name);

    return (new FiscalEngineFactory)->makeFromKnowledge($knowledge)->evaluate($scenario->input)->toArray();
}

it('qualifies documented nonfinancial cooperative network routes without changing ISS', function (string $name): void {
    $result = cooperativeNetworkResult($name);
    expect($result['status'])->toBe('VALID_WITH_WARNINGS')
        ->and($result['decision']['consumption_reform']['reason_codes'])->toBe(['RTC_COOPERATIVE_ZERO_RATE'])
        ->and($result['decision']['consumption_reform']['decision']['ibs']['nominal_amount'])->toBe('0.00')
        ->and($result['decision']['consumption_reform']['decision']['cbs']['nominal_amount'])->toBe('0.00')
        ->and($result['decision']['iss']['calculation']['tax_amount'])->toBe('50.00');
})->with(['single-central', 'federation-confederation', 'bank-participant']);

it('bounds proposed transfers by the reconciled balance per component and preserves original expiry', function (string $name, string $ibs, string $cbs, string $remaining): void {
    $result = cooperativeNetworkResult($name);
    $transfer = $result['decision']['consumption_reform']['decision']['treatment']['credit_transfer'];
    expect($result['status'])->toBe('VALID_WITH_WARNINGS')
        ->and($transfer['ibs_available'])->toBe('70.00')->and($transfer['cbs_available'])->toBe('600.00')
        ->and($transfer['ibs_requested'])->toBe($ibs)->and($transfer['cbs_requested'])->toBe($cbs)
        ->and($transfer['credits'][0]['remaining_after_requested_transfer'])->toBe($remaining)
        ->and($transfer['credits'][0]['expires_on'])->toBe('2031-10-01')
        ->and($transfer['transfer_performed'])->toBeFalse()->and($transfer['posting_performed'])->toBeFalse();
})->with([
    ['transfer-partial', '50.00', '500.00', '20.00'],
    ['transfer-full', '70.00', '600.00', '0.00'],
    ['transfer-presumed', '50.00', '500.00', '20.00'],
]);

it('rejects conflicting routes double use excess balance and unproven qualification', function (string $name, string $status, string $reason): void {
    $result = cooperativeNetworkResult($name);
    expect($result['status'])->toBe($status)->and($result['reason_codes'])->toBe([$reason]);
})->with([
    ['wrong-network-type', 'INVALID', 'RTC_COOPERATIVE_ZERO_NETWORK_PARTIES_CONFLICT'],
    ['missing-network-proof', 'ACTION_REQUIRED', 'RTC_COOPERATIVE_ZERO_NETWORK_EVIDENCE_MISSING:acquirer_registry_evidence_id'],
    ['wrong-bank-participant', 'INVALID', 'RTC_COOPERATIVE_ZERO_BANK_PARTICIPANT_CONFLICT'],
    ['missing-bank-proof', 'ACTION_REQUIRED', 'RTC_COOPERATIVE_ZERO_NETWORK_EVIDENCE_MISSING:bank_participation_evidence_id'],
    ['transfer-missing', 'ACTION_REQUIRED', 'RTC_COOPERATIVE_TRANSFER_PROOF_MISSING'],
    ['transfer-wrong-owner', 'INVALID', 'RTC_COOPERATIVE_TRANSFER_CREDIT_ENTRY_CONFLICT'],
    ['transfer-overdrawn', 'INVALID', 'RTC_COOPERATIVE_TRANSFER_TRANSFER_EXCEEDS_AVAILABLE_CREDIT'],
    ['transfer-unrelated', 'INVALID', 'RTC_COOPERATIVE_TRANSFER_CREDIT_NOT_RELATED_TO_SUPPLY'],
    ['transfer-duplicate', 'INVALID', 'RTC_COOPERATIVE_TRANSFER_DUPLICATE_DOCUMENT_COMPONENT'],
    ['transfer-future-date', 'INVALID', 'RTC_COOPERATIVE_TRANSFER_APPROPRIATION_DATE_CONFLICT'],
    ['transfer-future-observation', 'INVALID', 'RTC_COOPERATIVE_TRANSFER_OBSERVATION_CONFLICT'],
    ['transfer-already-recorded', 'INVALID', 'RTC_COOPERATIVE_TRANSFER_TRANSFER_ALREADY_RECORDED'],
    ['transfer-wrong-route', 'INVALID', 'RTC_COOPERATIVE_TRANSFER_MEMBER_SUPPLY_ROUTE_REQUIRED'],
    ['transfer-presumed-unqualified', 'ACTION_REQUIRED', 'RTC_COOPERATIVE_TRANSFER_PRESUMED_CREDIT_NOT_QUALIFIED'],
    ['transfer-missing-regulatory-proof', 'ACTION_REQUIRED', 'RTC_COOPERATIVE_TRANSFER_EVIDENCE_MISSING:transfer_regulatory_validation_evidence_id'],
]);

it('expires transferred credit at the original five year boundary without restarting the term', function (): void {
    [$knowledge, $scenario] = cooperativeNetworkCase('transfer-partial');
    $rule = $knowledge['documents']['rtc_code_catalog']['cooperative_credit_transfer'];
    $qualifier = new CooperativeCreditTransferQualifier;
    $proof = ['status' => 'RESOLVED', 'proof' => ['route' => 'MEMBER_TO_COOPERATIVE']];
    $before = $qualifier->resolve($scenario->input->context->facts, LocalDate::fromString('2031-09-30'), new DateTimeImmutable('2031-10-02'), $rule, $proof);
    $expired = $qualifier->resolve($scenario->input->context->facts, LocalDate::fromString('2031-10-01'), new DateTimeImmutable('2031-10-02'), $rule, $proof);
    expect($before['status'])->toBe('RESOLVED')
        ->and($expired['status'])->toBe('INVALID')->and($expired['reason'])->toBe('RTC_COOPERATIVE_TRANSFER_CREDIT_EXPIRED');
});
