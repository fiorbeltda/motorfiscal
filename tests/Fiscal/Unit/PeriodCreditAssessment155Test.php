<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\PeriodCreditAssessment155;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

require_once dirname(__DIR__, 3).'/scripts/agent_a/handoff155_fixture.php';

it('composes adjustments before utilization and conserves each tax without extinguishing debts', function (string $reversal): void {
    $input = handoff155Period();
    $input['events'][0]['amount'] = $reversal;
    if ($reversal === '0.00') {
        $input['events'] = [];
    }
    $result = (new PeriodCreditAssessment155)->assess($input);
    expect($result['status'])->toBe('LOCAL_PERIOD_CREDIT_ASSESSMENT')
        ->and($result['debts_extinguished'])->toBeFalse()->and($result['ledger_posted'])->toBeFalse()
        ->and((new PeriodCreditAssessment155)->replay($result))->toBeTrue();
    foreach (['IBS' => '100.00', 'CBS' => '90.00'] as $tax => $original) {
        $used = '0.00';
        foreach ($result['utilization']['compensations'] as $row) {
            if ($row['tax'] === $tax) {
                $used = bcadd($used, $row['amount'], 2);
            }
        }
        $remaining = '0.00';
        foreach ($result['utilization']['remaining_credits'] as $row) {
            if ($row['tax'] === $tax) {
                $remaining = bcadd($remaining, $row['remaining'], 2);
            }
        }
        expect(bcadd(bcadd($used, $remaining, 2), $tax === 'IBS' ? $reversal : '0.00', 2))->toBe($original);
    }
})->with(['0.00', '0.01', '20.00', '99.99', '100.00']);

it('preserves presumed-credit refund restrictions and original dates across the B11 B14 adapter', function (): void {
    $input = handoff155Period();
    $input['debts'] = [];
    $input['refund_requested'] = true;
    $result = (new PeriodCreditAssessment155)->assess($input);
    expect($result['utilization']['refund'])->toHaveCount(1)
        ->and($result['utilization']['refund'][0]['tax'])->toBe('IBS')
        ->and($result['utilization']['refund'][0]['amount'])->toBe('80.00')
        ->and($result['utilization']['remaining_credits'][1]['refund_prohibited'])->toBeTrue();
    $input['period'] = '2033-01';
    $expired = (new PeriodCreditAssessment155)->assess($input);
    expect($expired['utilization']['expired_credits'])->toHaveCount(2)
        ->and($expired['utilization']['compensations'])->toBe([]);
});

it('never treats a changed report or recomputed report hash as a valid replay', function (): void {
    $assessor = new PeriodCreditAssessment155;
    $result = $assessor->assess(handoff155Period());
    $result['utilization']['compensations'][0]['amount'] = '999.00';
    unset($result['report_hash']);
    $result['report_hash'] = CanonicalJson::hash($result);
    expect($assessor->replay($result))->toBeFalse();
});

it('never makes an already consumed credit available a second time', function (string $used, ?string $expected): void {
    $input = handoff155Period();
    $input['credits'][0]['previously_utilized_amount'] = $used;
    $result = (new PeriodCreditAssessment155)->assess($input);
    if ($expected === null) {
        expect($result['reason'])->toBe('RTC_PERIOD_CREDIT_ADJUSTED_BALANCE_ALREADY_CONSUMED');
    } else {
        $pool = array_column($result['available_pool_after_prior_utilization'], null, 'credit_id');
        expect($result['status'])->toBe('LOCAL_PERIOD_CREDIT_ASSESSMENT')
            ->and($pool['IBS1']['amount'])->toBe($expected);
    }
})->with([['0.00', '80.00'], ['10.00', '70.00'], ['79.99', '0.01'], ['80.00', '0.00'], ['80.01', null]]);

it('refuses malformed conflicting and excessive period inputs without posting partial state', function (string $mutation, string $reason): void {
    $input = handoff155Period();
    switch ($mutation) {
        case 'contract': $input['contract'] = 'OTHER';
            break;
        case 'complete': $input['ledger_complete'] = false;
            break;
        case 'scope': $input['credits'][0]['taxpayer_id'] = 'OTHER';
            break;
        case 'date': $input['credits'][0]['appropriated_period'] = '2026-09';
            break;
        case 'correction': $input['credits'][0]['monetary_correction'] = '1.00';
            break;
        case 'refund-type': $input['credits'][0]['refund_prohibited'] = 'true';
            break;
        case 'event-link': $input['events'][0]['credit_id'] = [];
            break;
        case 'debt-date': $input['debts'][0]['fact_date'] = '2026-02-30';
            break;
        case 'excess': $input['events'][0]['amount'] = '100.01';
            break;
        case 'transfer': $input['transfers'] = [['reason' => 'SALE']];
            break;
        case 'float': $input['credits'][0]['amount'] = 1.5;
            break;
        case 'duplicate': $input['credits'][] = $input['credits'][0];
            break;
    }
    $result = (new PeriodCreditAssessment155)->assess($input);
    expect($result['reason'])->toBe($reason)->and($result['ledger_posted'])->toBeFalse()
        ->and($result['debts_extinguished'])->toBeFalse();
})->with([
    ['contract', 'RTC_PERIOD_CREDIT_CONTRACT_REQUIRED'], ['complete', 'RTC_PERIOD_CREDIT_COMPLETE_LEDGER_REQUIRED'],
    ['scope', 'RTC_PERIOD_CREDIT_RECORD_SCOPE_CONFLICT'], ['date', 'RTC_PERIOD_CREDIT_ORIGINAL_DATE_CONFLICT'],
    ['correction', 'B14_MONETARY_CORRECTION_PROHIBITED_ART53_2'], ['refund-type', 'RTC_PERIOD_CREDIT_REFUND_RESTRICTION_TYPE_INVALID'],
    ['event-link', 'RTC_PERIOD_CREDIT_EVENT_LINK_INVALID'], ['debt-date', 'RTC_PERIOD_CREDIT_DEBT_DATE_INVALID'],
    ['excess', 'B11_ADJUSTMENT_EXCEEDS_REMAINING'], ['transfer', 'B14_CREDIT_TRANSFER_PROHIBITED_ART55'],
    ['float', 'B11_CREDIT_RECORD_INVALID'], ['duplicate', 'RTC_PERIOD_CREDIT_DUPLICATE_OR_INVALID_ID'],
]);
