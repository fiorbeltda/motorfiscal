<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use Illuminate\Support\Facades\Artisan;

require_once dirname(__DIR__, 3).'/scripts/agent_a/handoff155_fixture.php';
require_once dirname(__DIR__).'/Support/b07/AnnexStandardSpecB07.php';

it('exposes a real period CLI with deterministic replay and an explicit refusal exit', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'a155-period-');
    try {
        $input = handoff155Period();
        file_put_contents($path, json_encode($input, JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:rtc:assess-period-credit', ['ledger' => $path]))->toBe(0);
        $first = Artisan::output();
        $result = json_decode($first, true, 512, JSON_THROW_ON_ERROR);
        expect($result['replay_matches'])->toBeTrue();
        Artisan::call('fiscal:rtc:assess-period-credit', ['ledger' => $path]);
        expect(Artisan::output())->toBe($first);
        $input['ledger_complete'] = false;
        file_put_contents($path, json_encode($input, JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:rtc:assess-period-credit', ['ledger' => $path]))->toBe(1);
    } finally {
        unlink($path);
    }
});

it('executes all 320 B07 decisions with a test-only gated capture in each runner', function (string $id, string $case, bool $integrated): void {
    $scenario = b07Scenario($id, $case);
    $expected = b07Expected($id, $case);
    $actual = handoff155Evaluate($scenario, ['H2'], $integrated);
    if ($integrated) {
        $actual = $actual['decision']['consumption_reform'] ?? $actual;
        $actual['reason_codes'] = $actual['reasons'] ?? $actual['reason_codes'] ?? [];
    }
    if ($expected['status'] === 'HISTORICAL') {
        $old = handoff155Evaluate($scenario, [], $integrated);
        if ($integrated) {
            $old = $old['decision']['consumption_reform'] ?? $old;
        }
        expect($actual['status'])->toBe($old['status']);

        return;
    }
    expect($actual['status'])->toBe($expected['status']);
    if ($expected['reason'] !== null) {
        expect($actual['reason_codes'])->toContain($expected['reason']);
    }
    if ($expected['cclasstrib'] !== null) {
        expect($actual['decision']['classification']['cclasstrib'])->toBe($expected['cclasstrib']);
    }
})->with(function (): array {
    $rows = [];
    foreach (b07Rules() as $id) {
        foreach (B07_CASES as $case) {
            foreach ([false, true] as $integrated) {
                $rows[$id.' '.$case.' '.($integrated ? 'integrated' : 'rtc')] = [$id, $case, $integrated];
            }
        }
    }

    return $rows;
});

it('wires B02 without changing ordinary credit statuses amounts or ungated decisions', function (bool $valid, bool $extinguished, bool $integrated): void {
    $input = handoff155Input();
    $input['facts']['rtc.input_document_valid'] = $valid;
    $input['facts']['rtc.prior_tax_extinguished'] = $extinguished;
    $actual = handoff155Evaluate($input, ['H1'], $integrated);
    $old = handoff155Evaluate($input, [], $integrated);
    if ($integrated) {
        $actual = $actual['decision']['consumption_reform'];
        $old = $old['decision']['consumption_reform'];
    }
    expect($actual['status'])->toBe('VALID');
    foreach (['ibs', 'cbs'] as $tax) {
        $credit = &$actual['decision'][$tax]['credit'];
        expect($credit['zero_semantics_version'])->toBe('B02');
        if (! $valid || ! $extinguished) {
            expect($credit['amount'])->toBe('0.00');
            if (! $valid) {
                expect($credit['zero_causes'])->toContain('INPUT_DOCUMENT_NOT_VALID');
            }
            if (! $extinguished) {
                expect($credit['zero_causes'])->toContain('PRIOR_TAX_NOT_EXTINGUISHED');
            }
        }
        unset($credit['zero_semantics_version'], $credit['zero_causes'], $credit['amount_semantics']);
        unset($credit);
    }
    expect(CanonicalJson::hash($actual['decision']))->toBe(CanonicalJson::hash($old['decision']));
})->with([[true, true], [true, false], [false, true], [false, false]])->with([false, true]);

it('wires B02 on the qualified import branch without approving its document', function (): void {
    $input = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/import-fx-credit-2026.10.14-68-draft1/proven.json')), true, 512, JSON_THROW_ON_ERROR);
    $actual = handoff155Evaluate($input, ['H1'], false, 'bundles/consumption-phase4b-2026.10.14-68-draft1.json');
    $trace = array_column($actual['trace'], null, 'stage');
    expect($actual['status'])->toBe('ACTION_REQUIRED')->and($trace)->toHaveKey('IMPORT_CREDIT')
        ->and($trace['IMPORT_CREDIT']['ibs']['zero_semantics_version'])->toBe('B02')
        ->and($trace['IMPORT_CREDIT']['cbs']['amount_semantics'])->toBe('ALLOWED_AMOUNT');
});

it('preserves meaningful reference strings with internal spaces under the B07 STRING contract', function (bool $integrated): void {
    $input = b07Scenario(b07Rules()[0], 'standard-justified');
    $input['facts']['rtc.annex_noncorrespondence_evidence_id'] = 'Laudo clínico 123';
    $input['facts']['rtc.service_nbs_evidence_id'] = 'Classificação NBS 456';
    $result = handoff155Evaluate($input, ['H2'], $integrated);
    if ($integrated) {
        $result = $result['decision']['consumption_reform'];
    }
    expect($result['status'])->toBe('VALID')
        ->and($result['decision']['classification']['cclasstrib'])->toBe('000001');
})->with([false, true]);

it('integrates B13 statutory prohibition while preserving the input122 treatment', function (string $regime): void {
    $input = handoff155Input();
    $input['facts']['rtc.acquired_regime'] = $regime;
    $result = handoff155Evaluate($input, ['H1', 'H3']);
    expect($result['status'])->toBe('VALID');
    foreach (['ibs', 'cbs'] as $tax) {
        expect($result['decision'][$tax]['credit']['status'])->toBe('DENIED')
            ->and($result['decision'][$tax]['credit']['zero_causes'])->toBe(['B13_STATUTORY_CREDIT_PROHIBITION']);
    }
    expect($result['decision']['credit_integration155']['prohibition']['legal_basis'])->not->toBeNull();
})->with(['PERSONAL_USE', 'PRIVATE_PENSION', 'HEALTH_PLAN', 'PET_HEALTH_PLAN', 'BETTING', 'BAR_RESTAURANT_FOOD', 'HOTEL_AMUSEMENT_PARK', 'URBAN_COLLECTIVE_TRANSPORT']);

it('integrates B17 separately without pretending ordinary plus presumed appropriation', function (bool $integrated): void {
    $input = handoff155Input();
    $input['facts']['rtc.presumed_credit_requested'] = true;
    $input['facts']['rtc.presumed_credit_claims'] = [['hypothesis' => 'RURAL_PRODUCER_NON_CONTRIBUTOR',
        'document_id' => $input['facts']['rtc.input_document_id'], 'acquisition_date' => $input['facts']['rtc.operation_date'],
        'acquirer_id' => $input['facts']['rtc.acquirer_id'], 'acquirer_regime' => 'REGULAR', 'base_amount' => '1000.00',
        'rate_parameters' => ['source_id' => 'SYNTHETIC.A155', 'source_kind' => 'SYNTHETIC_TEST', 'ibs_percent' => '2.5', 'cbs_percent' => '1.5']]];
    $result = handoff155Evaluate($input, ['H1', 'H3'], $integrated);
    if ($integrated) {
        $result = $result['decision']['consumption_reform'];
    }
    expect($result['status'])->toBe('VALID');
    $credit = $result['decision']['credit_integration155'];
    expect($credit['presumed']['status'])->toBe('PRESUMED_CREDIT_SYNTHETIC_TEST_ONLY')
        ->and($credit['presumed']['official_decision'])->toBeFalse()
        ->and($credit['presumed']['amounts'])->toBe(['IBS' => '25.00', 'CBS' => '15.00'])
        ->and($credit['presumed_appropriated'])->toBeFalse()->and($credit['ordinary_and_presumed_added'])->toBeFalse()
        ->and($result['decision']['ibs']['credit']['amount'])->toBe('2.00');
})->with([false, true]);

it('distinguishes personal-use denial in the actual motor with B02 enabled', function (): void {
    $input = handoff155Input();
    $input['facts']['rtc.credit_context'] = 'PERSONAL_USE';
    $result = handoff155Evaluate($input, ['H1']);
    expect($result['status'])->toBe('VALID')
        ->and($result['decision']['ibs']['credit']['zero_causes'])->toBe(['PERSONAL_USE_OR_CONSUMPTION']);
});

it('requires explicit acquired regime and forwards B13 missing or conflicting exception inputs', function (string $case, string $reason): void {
    $input = handoff155Input();
    switch ($case) {
        case 'missing': unset($input['facts']['rtc.acquired_regime']);
            break;
        case 'unknown': $input['facts']['rtc.acquired_regime'] = 'UNKNOWN';
            break;
        case 'fuel': $input['facts']['rtc.acquired_regime'] = 'MONOPHASIC_FUEL_FOR_RESALE';
            break;
        case 'health': $input['facts']['rtc.acquired_regime'] = 'HEALTH_PLAN';
            $input['facts']['rtc.employee_collective_agreement_plan'] = true;
            $input['facts']['rtc.credit_exception_evidence_id'] = 'COLLECTIVE.AGREEMENT';
            break;
    }
    $result = handoff155Evaluate($input, ['H1', 'H3']);
    expect($result['reason_codes'])->toContain($reason)->and($result['decision'])->toBe([]);
})->with([['missing', 'RTC_ACQUIRED_REGIME_REQUIRED'], ['unknown', 'B13_REGIME_NOT_IN_CATALOGUE'],
    ['fuel', 'B13_FUEL_DESTINATION_REQUIRED'], ['health', 'RTC_SUPPLIER_SPECIFIC_EXTINGUISHED_DEBITS_REQUIRED']]);

it('binds the collective-plan exception to the suppliers actual input debits before applying adjustments once', function (bool $conflicting): void {
    $input = handoff155Input();
    $facts = &$input['facts'];
    $facts['rtc.acquired_regime'] = 'HEALTH_PLAN';
    $facts['rtc.employee_collective_agreement_plan'] = true;
    $facts['rtc.credit_exception_evidence_id'] = 'COLLECTIVE.AGREEMENT';
    $facts['rtc.credit_adjustments'] = [['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '0.50', 'evidence_id' => 'FIXTURE.ADJUSTMENT']];
    $facts['rtc.supplier_specific_credit_debits'] = [];
    foreach (['IBS' => 'rtc.input_ibs_amount', 'CBS' => 'rtc.input_cbs_amount'] as $tax => $key) {
        $facts['rtc.supplier_specific_credit_debits'][] = ['tax' => $tax, 'amount' => $facts[$key],
            'supplier_id' => $facts['rtc.supplier_id'], 'document_id' => $facts['rtc.input_document_id'],
            'extinguished' => true, 'evidence_id' => 'FIXTURE.SPECIFIC.DEBIT.'.$tax];
    }
    if ($conflicting) {
        $facts['rtc.supplier_specific_credit_debits'][0]['amount'] = '100.00';
    }
    unset($facts);
    $result = handoff155Evaluate($input, ['H1', 'H3']);
    if ($conflicting) {
        expect($result['reason_codes'])->toContain('RTC_SUPPLIER_SPECIFIC_CREDIT_DEBIT_CONFLICT');
    } else {
        expect($result['status'])->toBe('VALID')->and($result['decision']['ibs']['credit']['amount'])->toBe('1.50')
            ->and($result['decision']['cbs']['credit']['amount'])->toBe('18.00');
        $qualification = $result['decision']['credit_integration155']['prohibition'];
        $claimedHash = $qualification['result_hash'];
        unset($qualification['result_hash']);
        expect(CanonicalJson::hash($qualification))->toBe($claimedHash)
            ->and($qualification)->not->toHaveKey('qualified_supplier_debits')
            ->and($result['decision']['credit_integration155']['qualified_supplier_debits'])
            ->toBe($input['facts']['rtc.supplier_specific_credit_debits']);
    }
})->with([false, true]);

it('rejects mismatched presumed claims instead of replacing ordinary guards or trusting caller totals', function (string $case, string $reason): void {
    $input = handoff155Input();
    $facts = &$input['facts'];
    $facts['rtc.presumed_credit_requested'] = true;
    $facts['rtc.presumed_credit_claims'] = [['hypothesis' => 'RURAL_PRODUCER_NON_CONTRIBUTOR',
        'document_id' => $facts['rtc.input_document_id'], 'acquisition_date' => $facts['rtc.operation_date'],
        'acquirer_id' => $facts['rtc.acquirer_id'], 'acquirer_regime' => 'REGULAR', 'base_amount' => '1000.00',
        'rate_parameters' => ['source_id' => 'SYNTHETIC.A155', 'source_kind' => 'SYNTHETIC_TEST', 'ibs_percent' => '2.5', 'cbs_percent' => '1.5']]];
    switch ($case) {
        case 'missing': unset($facts['rtc.presumed_credit_claims']);
            break;
        case 'document': $facts['rtc.presumed_credit_claims'][0]['document_id'] = 'OTHER';
            break;
        case 'party': $facts['rtc.presumed_credit_claims'][0]['acquirer_id'] = 'OTHER';
            break;
        case 'date': $facts['rtc.presumed_credit_claims'][0]['acquisition_date'] = '2026-02-30';
            break;
        case 'parameter': unset($facts['rtc.presumed_credit_claims'][0]['rate_parameters']);
            break;
        case 'request': $facts['rtc.presumed_credit_requested'] = false;
            break;
    }
    unset($facts);
    $result = handoff155Evaluate($input, ['H1', 'H3']);
    expect($result['reason_codes'])->toContain($reason)->and($result['decision'])->toBe([]);
})->with([['missing', 'RTC_PRESUMED_CLAIM_REQUIRED'], ['document', 'RTC_PRESUMED_CLAIM_SCOPE_CONFLICT'],
    ['party', 'RTC_PRESUMED_CLAIM_SCOPE_CONFLICT'], ['date', 'RTC_PRESUMED_CLAIM_SCOPE_CONFLICT'],
    ['parameter', 'B17_ANNUAL_JOINT_ACT_PERCENT_REQUIRED'], ['request', 'RTC_PRESUMED_REQUEST_CONFLICT']]);

it('ignores synthetic overlap without hiding a real official rate conflict', function (): void {
    $catalog = json_decode((string) file_get_contents(resource_path('fiscal/catalogs/rtc-rates-2026.09.25-1.json')), true, 512, JSON_THROW_ON_ERROR);
    $catalog['rates'][] = [...$catalog['rates'][2], 'source_kind' => 'SYNTHETIC_TEST', 'percent' => '99.00'];
    $on = LocalDate::fromString('2026-10-01');
    $known = new DateTimeImmutable('2026-11-04');
    expect((new TemporalRateCatalog($catalog))->resolve('CBS', 'BR', $on, $known)['row']['percent'])->toBe('0.90');
    $catalog['rates'][] = $catalog['rates'][2];
    expect((new TemporalRateCatalog($catalog))->resolve('CBS', 'BR', $on, $known)['status'])->toBe('CONFLICT');
});

it('rejects synthetic conflicting or unknown provenance while preserving old unmarked outputs', function (array $markers, bool $official): void {
    $row = ['tax' => 'CBS', 'jurisdiction' => 'BR', 'percent' => '0.90', 'valid_from' => '2026-01-01', 'valid_to' => '2026-12-31',
        'published_at' => '2025-01-16', 'status' => 'ACTIVE', 'source_id' => 'TEST.SOURCE', 'source_hash' => str_repeat('a', 64), ...$markers];
    $result = (new TemporalRateCatalog(['rates' => [$row]]))->resolve('CBS', 'BR', LocalDate::fromString('2026-10-01'), new DateTimeImmutable('2026-11-04'));
    expect($result['status'])->toBe($official ? 'RESOLVED' : 'NOT_FOUND');
    if ($official && $markers === []) {
        expect($result)->toBe(['status' => 'RESOLVED', 'row' => $row, 'reason' => 'RTC_RATE_RESOLVED']);
    } else {
        expect($result['official'])->toBe($official);
    }
})->with([[[], true], [['source_kind' => 'OFFICIAL'], true], [['source_kind' => 'SYNTHETIC_TEST'], false],
    [['synthetic' => true], false], [['source_kind' => 'OFFICIAL', 'synthetic' => true], false],
    [['source_kind' => null], false], [['source_kind' => 'UNKNOWN'], false], [['synthetic' => 'false'], false]]);
