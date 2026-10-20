<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Core\Engine\DecisionStatus;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioRunService;

function rtcPath(string $relative = ''): string
{
    return dirname(__DIR__, 3).'/resources/fiscal'.($relative === '' ? '' : '/'.$relative);
}

function rtcFixture(string $scenario): array
{
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(rtcPath(), 'bundles/consumption-phase4b-2026.09.25-1.json');
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->load(rtcPath("scenarios/consumption-phase4b/{$scenario}.json"));
    $documents = $knowledge['documents'];
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog']));

    return [$factory, $knowledge, $loaded, $engine];
}

function rtcCollectionCase(array $factOverrides): array
{
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(rtcPath(), 'bundles/consumption-phase4b-2026.09.28-3.json');
    $scenario = json_decode((string) file_get_contents(rtcPath('scenarios/consumption-phase4b/regular-saas-2026.json')),
        true, 512, JSON_THROW_ON_ERROR);
    $scenario['scenario_id'] = 'collection-case';
    $scenario['knowledge_at'] = '2026-09-28T11:33:00-03:00';
    $scenario['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $scenario['catalog_versions'] = $knowledge['catalog_versions'];
    $scenario['facts'] = array_merge($scenario['facts'], $factOverrides);
    foreach ($factOverrides as $key => $value) {
        if ($value === null) {
            unset($scenario['facts'][$key]);
        }
    }
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($scenario);
    $documents = $knowledge['documents'];
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog']));

    return $engine->evaluate($loaded->input->context->facts,
        $loaded->input->context->competenceDate, $loaded->input->context->knowledgeAt);
}

it('qualifies article 36 acquirer collection without claiming that tax was paid', function (): void {
    $facts = [
        'rtc.collection_method' => 'ACQUIRER',
        'rtc.collection_legal_basis' => 'LC214 art. 36',
        'rtc.acquirer_regime' => 'REGULAR',
        'rtc.payment_instrument_split_capable' => false,
        'rtc.payment_instrument_evidence_id' => 'contract-payment-clause-1',
        'rtc.payment_transaction_id' => 'payment-1',
        'rtc.accessory_obligations_fulfilled' => false,
    ];
    $result = rtcCollectionCase($facts);
    expect($result['status'])->toBe('VALID')
        ->and($result['decision']['roles']['collector'])->toBe('11111111000191')
        ->and($result['decision']['collection']['eligible_payer'])->toBe('22222222000191')
        ->and($result['decision']['collection']['extinction_status'])->toBe('UNCONFIRMED')
        ->and($result['decision']['ibs']['cash_due'])->toBe('1.00')
        ->and($result['decision']['cbs']['cash_due'])->toBe('9.00');

    $conflict = rtcCollectionCase(array_merge($facts, ['rtc.payment_instrument_split_capable' => true]));
    expect($conflict['status'])->toBe('INVALID')
        ->and($conflict['reason_codes'])->toContain('RTC_ACQUIRER_COLLECTION_CONDITIONS_CONFLICT');

    $missing = rtcCollectionCase(array_merge($facts, ['rtc.payment_instrument_evidence_id' => null]));
    expect($missing['status'])->toBe('ACTION_REQUIRED')
        ->and($missing['missing_facts'])->toContain('rtc.payment_instrument_evidence_id');
});

it('does not activate split payment from a transaction claim without official deployment', function (): void {
    $result = rtcCollectionCase([
        'rtc.collection_method' => 'SPLIT_PAYMENT',
        'rtc.collection_legal_basis' => 'LC214 arts. 31-35',
        'rtc.payment_instrument_split_capable' => true,
        'rtc.payment_instrument_evidence_id' => 'card-arrangement-1',
        'rtc.payment_transaction_id' => 'payment-1',
        'rtc.split_deployment_evidence_id' => 'unverified-user-claim',
    ]);
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'])->toContain('RTC_SPLIT_OFFICIAL_DEPLOYMENT_NOT_PROVEN');
});

it('qualifies the published second-semester Simples option and rejects filings outside March', function (): void {
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(rtcPath(), 'bundles/consumption-phase4b-2026.09.28-2.json');
    $documents = $knowledge['documents'];
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog']));

    foreach (['simples-regular-h2-2027-confirmed' => 'RTC_RATE_NOT_PROVEN:CBS',
        'simples-regular-h2-2027-outside-window' => 'RTC_SIMPLES_OPTION_OUTSIDE_STANDARD_WINDOW'] as $name => $reason) {
        $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->load(
            rtcPath("scenarios/consumption-phase4b-2026.09.28-2/{$name}.json"),
        );
        $result = $engine->evaluate($scenario->input->context->facts,
            $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
        expect($result['status'])->toBe('ACTION_REQUIRED')
            ->and($result['reason_codes'])->toContain($reason);
        if ($name === 'simples-regular-h2-2027-confirmed') {
            $optionTrace = array_values(array_filter($result['trace'],
                static fn (array $row): bool => ($row['stage'] ?? null) === 'SIMPLES_REGULAR_OPTION'));
            expect($optionTrace)->toHaveCount(1)
                ->and($optionTrace[0]['scope'])->toBe('2027_SECOND_SEMESTER');
        }
    }
});

it('does not expose the second-semester option before its preserved source was published', function (): void {
    $catalog = json_decode((string) file_get_contents(rtcPath('catalogs/rtc-transition-2026.09.28-2.json')),
        true, 512, JSON_THROW_ON_ERROR);
    $transitions = new TransitionCatalog($catalog);

    expect($transitions->resolveSimplesOption(LocalDate::fromString('2027-10-10'),
        new DateTimeImmutable('2026-08-11T12:00:00-03:00'))['status'])->toBe('NOT_FOUND')
        ->and($transitions->resolveSimplesOption(LocalDate::fromString('2027-10-10'),
            new DateTimeImmutable('2026-08-12T12:00:00-03:00'))['status'])->toBe('RESOLVED');
});

it('calculates separate 2026 nominal taxes and preserves historical bundle behavior', function (): void {
    [$factory, , $scenario, $engine] = rtcFixture('regular-saas-2026');
    $direct = $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
    expect($direct['status'])->toBe('VALID')
        ->and($direct['decision']['ibs']['state_amount'])->toBe('1.00')
        ->and($direct['decision']['ibs']['municipal_amount'])->toBe('0.00')
        ->and($direct['decision']['cbs']['nominal_amount'])->toBe('9.00')
        ->and($direct['decision']['cbs']['cash_due'])->toBe('0.00')
        ->and($direct['decision']['document']['issuer_fields']['IBSCBS.cIndOp'])->toBe('100301');

    $runner = new ScenarioRunService($factory, rtcPath(), bundleManifestPath: 'bundles/consumption-phase4b-2026.09.25-1.json');
    $integrated = $runner->run(rtcPath('scenarios/consumption-phase4b/regular-saas-2026.json'));
    expect($integrated->status)->toBe(DecisionStatus::VALID_WITH_WARNINGS)
        ->and($integrated->decision['consumption_reform']['decision']['ibs']['nominal_amount'])->toBe('1.00')
        ->and($integrated->decision['dps_projection']['rtc']['issuer_supplied']['IBSCBS.valores.trib.gIBSCBS.cClassTrib'])->toBe('000001');
});

it('replays the captured SaaS rule that predates the canonical service field', function (): void {
    [, $knowledge, $scenario] = rtcFixture('regular-saas-2026');
    $documents = $knowledge['documents'];
    $ruleId = 'RTC.CLASSIFICATION.SAAS.RESIDUAL.SANDBOX-1';
    unset($documents['rtc_code_catalog']['engine_decision_rules'][$ruleId]['canonical_service_id']);
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog']));

    $result = $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
    expect($result['status'])->toBe('VALID')
        ->and($result['decision']['classification']['rule_id'])->toBe($ruleId);
});

it('blocks future rates and rejects overlapping rate records', function (): void {
    [, $knowledge, $scenario, $engine] = rtcFixture('regular-saas-2027-rate-gap');
    $result = $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'])->toContain('RTC_RATE_NOT_PROVEN:CBS');

    $catalog = $knowledge['documents']['rtc_rate_catalog'];
    $catalog['rates'][] = $catalog['rates'][0];
    $resolver = new TemporalRateCatalog($catalog);
    $conflict = $resolver->resolve('IBS_STATE', 'BR', LocalDate::fromString('2026-09-25'), new DateTimeImmutable('2026-09-25T10:00:00-03:00'));
    expect($conflict['status'])->toBe('CONFLICT');
});

it('keeps nominal 2026 collection when the legal dispensation condition is false', function (): void {
    [, , $scenario, $engine] = rtcFixture('regular-saas-2026-collection-due');
    $result = $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
    expect($result['status'])->toBe('VALID')
        ->and($result['decision']['ibs']['cash_due'])->toBe('1.00')
        ->and($result['decision']['cbs']['cash_due'])->toBe('9.00');
});

it('excludes documented legacy taxes from the common IBS and CBS base', function (): void {
    [, , $scenario, $engine] = rtcFixture('legacy-taxes-included-2026');
    $result = $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
    expect($result['status'])->toBe('VALID')
        ->and($result['decision']['base']['taxable'])->toBe('913.50')
        ->and($result['decision']['ibs']['state_amount'])->toBe('0.91')
        ->and($result['decision']['cbs']['nominal_amount'])->toBe('8.22');
});

it('ties input credits to the prior document and keeps IBS and CBS separate', function (): void {
    [$factory, $knowledge, , $engine] = rtcFixture('regular-saas-2026');
    $source = json_decode((string) file_get_contents(rtcPath('scenarios/consumption-phase4b/regular-saas-2026.json')), true, 512, JSON_THROW_ON_ERROR);
    $source['facts']['rtc.credit_context'] = 'INPUT';
    $source['facts']['rtc.acquirer_regime'] = 'REGULAR';
    $source['facts']['rtc.input_document_id'] = 'dfe-purchase-1';
    $source['facts']['rtc.input_document_valid'] = true;
    $source['facts']['rtc.prior_tax_extinguished'] = true;
    $source['facts']['rtc.input_ibs_amount'] = '3.50';
    $source['facts']['rtc.input_cbs_amount'] = '7.25';
    $source['facts']['rtc.credit_adjustments'] = [['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '0.50', 'evidence_id' => 'estorno-1']];
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $result = $engine->evaluate($loaded->input->context->facts, $loaded->input->context->competenceDate, $loaded->input->context->knowledgeAt);
    expect($result['status'])->toBe('VALID')
        ->and($result['decision']['ibs']['credit']['amount'])->toBe('3.00')
        ->and($result['decision']['cbs']['credit']['amount'])->toBe('7.25')
        ->and($result['decision']['ibs']['credit']['document_id'])->toBe('dfe-purchase-1');
});

it('requires the claimant regime before granting input credits', function (): void {
    foreach (['credit-claimant-regime-missing-2026', 'credit-claimant-simples-2026'] as $name) {
        [, , $scenario, $engine] = rtcFixture($name);
        $result = $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
        if ($name === 'credit-claimant-regime-missing-2026') {
            expect($result['status'])->toBe('ACTION_REQUIRED')
                ->and($result['reason_codes'])->toContain('RTC_CREDIT_CLAIMANT_REGIME_MISSING');
        } else {
            expect($result['status'])->toBe('VALID')
                ->and($result['decision']['ibs']['credit']['status'])->toBe('DENIED')
                ->and($result['decision']['cbs']['credit']['amount'])->toBe('0.00');
        }
    }
});

it('allocates documented partial export gross without approving the Brazilian leg', function (): void {
    [$factory, $knowledge, $scenario, $engine] = rtcFixture('export-partial-documented-2026');
    $result = $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
    $allocation = array_values(array_filter($result['trace'], static fn (array $entry): bool => $entry['stage'] === 'PARTIAL_EXPORT_GROSS_ALLOCATION'))[0];
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'])->toContain('RTC_PARTIAL_EXPORT_DOMESTIC_LEG_NOT_MATERIALIZED')
        ->and($allocation['foreign_gross_brl'])->toBe('400.00')
        ->and($allocation['domestic_gross_brl'])->toBe('600.00');

    $source = json_decode((string) file_get_contents(rtcPath('scenarios/consumption-phase4b/export-partial-documented-2026.json')), true, 512, JSON_THROW_ON_ERROR);
    $source['facts']['rtc.gross_amount'] = '1200.00';
    $unreconciled = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $blocked = $engine->evaluate($unreconciled->input->context->facts, $unreconciled->input->context->competenceDate, $unreconciled->input->context->knowledgeAt);
    expect($blocked['reason_codes'])->toContain('RTC_GROSS_OPERATION_RECONCILIATION_MISSING');
});

it('corroborates unregistered destination with two consistent independent criteria', function (): void {
    foreach (['unregistered-destination-corroborated-2026' => 'VALID',
        'unregistered-destination-one-criterion-2026' => 'ACTION_REQUIRED',
        'unregistered-destination-conflict-2026' => 'INVALID'] as $name => $status) {
        [, , $scenario, $engine] = rtcFixture($name);
        $result = $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
        expect($result['status'])->toBe($status);
        if ($status === 'VALID') {
            $corroboration = array_values(array_filter($result['trace'], static fn (array $entry): bool => $entry['stage'] === 'DESTINATION_CORROBORATION'))[0];
            expect($corroboration['criteria'])->toBe(['DECLARED_ADDRESS', 'PAYMENT_ARRANGEMENT']);
        }
    }

    [$factory, $knowledge, , $engine] = rtcFixture('unregistered-destination-corroborated-2026');
    $source = json_decode((string) file_get_contents(rtcPath('scenarios/consumption-phase4b/unregistered-destination-corroborated-2026.json')), true, 512, JSON_THROW_ON_ERROR);
    $source['facts']['rtc.destination_evidence_sources'][1]['evidence_id'] = 'declared-address-1';
    $duplicate = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $blocked = $engine->evaluate($duplicate->input->context->facts, $duplicate->input->context->competenceDate, $duplicate->input->context->knowledgeAt);
    expect($blocked['reason_codes'])->toContain('RTC_DESTINATION_CORROBORATION_UNPROVEN');
});

it('qualifies a free SaaS grant to an employee using evidenced market value', function (): void {
    [, , $scenario, $engine] = rtcFixture('non-onerous-employee-saas-2026');
    $result = $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
    $base = array_values(array_filter($result['trace'], static fn (array $entry): bool => $entry['stage'] === 'NON_ONEROUS_MARKET_BASE'))[0];
    $calculation = array_values(array_filter($result['trace'], static fn (array $entry): bool => $entry['stage'] === 'NON_ONEROUS_NOMINAL_CALCULATION'))[0];
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'])->toContain('RTC_NON_ONEROUS_DOCUMENT_NOT_MATERIALIZED')
        ->and($base['market_value_brl'])->toBe('1000.00')
        ->and($calculation['ibs_state'])->toBe('1.00')
        ->and($calculation['cbs'])->toBe('9.00');

    foreach (['non-onerous-employee-market-evidence-missing-2026' => 'RTC_NON_ONEROUS_EVIDENCE_MISSING',
        'non-onerous-employee-business-use-2026' => 'RTC_NON_ONEROUS_BUSINESS_USE_EXCEPTION',
        'non-onerous-employee-below-market-2026' => 'RTC_BELOW_MARKET_STRATEGY_NOT_MATERIALIZED',
        'non-onerous-employee-zero-market-value-2026' => 'RTC_NON_ONEROUS_MARKET_VALUE_INVALID'] as $name => $reason) {
        [, , $case, $caseEngine] = rtcFixture($name);
        $caseResult = $caseEngine->evaluate($case->input->context->facts, $case->input->context->competenceDate, $case->input->context->knowledgeAt);
        expect($caseResult['reason_codes'])->toContain($reason);
    }
});

it('requires invoice and third-party proof before excluding discounts or reimbursements', function (): void {
    foreach (['unconditional-discount-proven-2026', 'third-party-reimbursement-proven-2026'] as $name) {
        [, , $scenario, $engine] = rtcFixture($name);
        $result = $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
        expect($result['status'])->toBe('VALID')
            ->and($result['decision']['base']['taxable'])->toBe('900.00')
            ->and($result['decision']['cbs']['nominal_amount'])->toBe('8.10');
    }
    foreach (['unconditional-discount-unproven-2026' => 'RTC_UNCONDITIONAL_DISCOUNT_PROOF_MISSING',
        'third-party-reimbursement-unproven-2026' => 'RTC_THIRD_PARTY_REIMBURSEMENT_PROOF_MISSING'] as $name => $reason) {
        [, , $scenario, $engine] = rtcFixture($name);
        $result = $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
        expect($result['reason_codes'])->toContain($reason);
    }
});

it('keeps documented legacy PIS exclusion available through 2032 only', function (): void {
    [, , $withinWindow, $engine] = rtcFixture('legacy-pis-exclusion-2027');
    $result = $engine->evaluate($withinWindow->input->context->facts, $withinWindow->input->context->competenceDate, $withinWindow->input->context->knowledgeAt);
    $base = array_values(array_filter($result['trace'], static fn (array $entry): bool => $entry['stage'] === 'BASE'))[0];
    expect($base['taxable_base'])->toBe('993.50')
        ->and($result['reason_codes'])->toContain('RTC_RATE_NOT_PROVEN:CBS');

    [, , $outsideWindow, $outsideEngine] = rtcFixture('legacy-pis-exclusion-2033-invalid');
    $blocked = $outsideEngine->evaluate($outsideWindow->input->context->facts, $outsideWindow->input->context->competenceDate, $outsideWindow->input->context->knowledgeAt);
    expect($blocked['status'])->toBe('INVALID')
        ->and($blocked['reason_codes'])->toContain('RTC_LEGACY_TAX_EXCLUSION_INVALID');
});

it('qualifies the documented Simples regular option for the first half of 2027', function (): void {
    [, , $scenario, $engine] = rtcFixture('simples-regular-h1-2027-confirmed');
    $result = $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
    $option = array_values(array_filter($result['trace'], static fn (array $entry): bool => $entry['stage'] === 'SIMPLES_REGULAR_OPTION'))[0];
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'])->toContain('RTC_RATE_NOT_PROVEN:CBS')
        ->and($option['scope'])->toBe('2027_FIRST_SEMESTER')
        ->and($option['receipt_id'])->toBe('rfb-option-receipt-1');

    foreach (['simples-regular-h1-2027-unproven' => 'RTC_SIMPLES_REGULAR_OPTION_UNPROVEN',
        'simples-regular-h1-2027-cancelled' => 'RTC_SIMPLES_REGULAR_OPTION_NOT_CONFIRMED',
        'simples-regular-h1-2027-revocable' => 'RTC_SIMPLES_OPTION_REVOCATION_WINDOW_OPEN',
        'simples-regular-h2-2027-unproven' => 'RTC_SIMPLES_OPTION_WINDOW_NOT_PROVEN',
        'simples-regular-h1-2027-new-business-gap' => 'RTC_SIMPLES_OPTION_OUTSIDE_STANDARD_WINDOW'] as $name => $reason) {
        [, , $case, $caseEngine] = rtcFixture($name);
        $blocked = $caseEngine->evaluate($case->input->context->facts, $case->input->context->competenceDate, $case->input->context->knowledgeAt);
        expect($blocked['reason_codes'])->toContain($reason);
    }
});

it('does not infer exportation from a foreign buyer and blocks unknown classification', function (): void {
    foreach (['export-unproven-2026' => 'RTC_EXPORT_CONSUMPTION_UNPROVEN',
        'other-service-export-unclassified-2026' => 'RTC_EXPORT_SERVICE_STRATEGY_NOT_MATERIALIZED',
        'unknown-classification-2026' => 'RTC_SUPPLIED_CLASSIFICATION_CONFLICT',
        'other-digital-service-unclassified-2026' => 'RTC_ENGINE_DECISION_RULE_NOT_PROVEN'] as $scenario => $reason) {
        [, , $loaded, $engine] = rtcFixture($scenario);
        $result = $engine->evaluate($loaded->input->context->facts, $loaded->input->context->competenceDate, $loaded->input->context->knowledgeAt);
        expect($result['reason_codes'])->toContain($reason);
    }
});

it('qualifies evidenced foreign consumption and rejects a Brazilian recipient as an export', function (): void {
    [, , $export, $engine] = rtcFixture('export-foreign-consumption-2026');
    $valid = $engine->evaluate($export->input->context->facts, $export->input->context->competenceDate, $export->input->context->knowledgeAt);
    expect($valid['status'])->toBe('VALID')
        ->and($valid['decision']['ibs']['status'])->toBe('IMMUNE_EXPORT')
        ->and($valid['decision']['cbs']['status'])->toBe('IMMUNE_EXPORT');

    [, , $domesticRecipient] = rtcFixture('export-brazilian-recipient-2026');
    $invalid = $engine->evaluate($domesticRecipient->input->context->facts, $domesticRecipient->input->context->competenceDate, $domesticRecipient->input->context->knowledgeAt);
    expect($invalid['status'])->toBe('INVALID')
        ->and($invalid['reason_codes'])->toContain('RTC_EXPORT_FOREIGN_ROLES_CONFLICT');
});

it('qualifies and calculates a provisional digital service import while keeping taxpayer roles distinct', function (): void {
    [, , $import, $engine] = rtcFixture('import-digital-2026-document-gap');
    $qualified = $engine->evaluate($import->input->context->facts, $import->input->context->competenceDate, $import->input->context->knowledgeAt);
    expect($qualified['status'])->toBe('ACTION_REQUIRED')
        ->and($qualified['reason_codes'])->toContain('RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN')
        ->and(array_column($qualified['trace'], 'stage'))->toContain('IMPORT_QUALIFICATION', 'IMPORT_NOMINAL_CALCULATION', 'IMPORT_LOCAL_DPS_PROJECTION');

    [, , $missing] = rtcFixture('import-digital-2026-missing-consumption');
    $unproven = $engine->evaluate($missing->input->context->facts, $missing->input->context->competenceDate, $missing->input->context->knowledgeAt);
    expect($unproven['reason_codes'])->toContain('RTC_IMPORT_EVIDENCE_MISSING')
        ->and($unproven['missing_facts'])->toContain('rtc.domestic_consumption_evidence_id');

    [, , $wrongTaxpayer] = rtcFixture('import-digital-2026-wrong-taxpayer');
    $invalid = $engine->evaluate($wrongTaxpayer->input->context->facts, $wrongTaxpayer->input->context->competenceDate, $wrongTaxpayer->input->context->knowledgeAt);
    expect($invalid['status'])->toBe('INVALID')
        ->and($invalid['reason_codes'])->toContain('RTC_IMPORT_TAXPAYER_DESTINATION_CONFLICT');
});

it('uses the actual Brazilian destination for B2C and foreign-acquirer domestic services', function (): void {
    [, , $b2c, $engine] = rtcFixture('regular-saas-b2c-2026');
    $consumer = $engine->evaluate($b2c->input->context->facts, $b2c->input->context->competenceDate, $b2c->input->context->knowledgeAt);
    expect($consumer['status'])->toBe('VALID')
        ->and($consumer['decision']['classification']['cindop'])->toBe('100301')
        ->and($consumer['decision']['roles']['buyer_relation'])->toBe('B2C');

    [, , $foreignAcquirer] = rtcFixture('regular-saas-foreign-acquirer-brazilian-recipient-2026');
    $recipient = $engine->evaluate($foreignAcquirer->input->context->facts, $foreignAcquirer->input->context->competenceDate, $foreignAcquirer->input->context->knowledgeAt);
    expect($recipient['status'])->toBe('VALID')
        ->and($recipient['decision']['classification']['cindop'])->toBe('100302')
        ->and($recipient['decision']['destination']['basis'])->toBe('RECIPIENT_DOMICILE');
});

it('uses the Brazilian recipient as taxpayer for a digital import bought abroad', function (): void {
    [, , $scenario, $engine] = rtcFixture('import-digital-foreign-acquirer-brazilian-recipient-2026');
    $result = $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
    $qualification = current(array_values(array_filter($result['trace'], static fn (array $row): bool => $row['stage'] === 'IMPORT_QUALIFICATION')));
    $projection = current(array_values(array_filter($result['trace'], static fn (array $row): bool => $row['stage'] === 'IMPORT_LOCAL_DPS_PROJECTION')));
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'])->toContain('RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN')
        ->and($qualification['taxpayer'])->toBe('22222222000191')
        ->and($projection['issuer_fields']['DPS.infDPS.IBSCBS.cIndOp'])->toBe('100302');
});

it('allocates only documented Brazilian consumption and blocks unconverted foreign currency', function (): void {
    [, , $partial, $engine] = rtcFixture('import-digital-partial-brazil-consumption-2026');
    $allocated = $engine->evaluate($partial->input->context->facts, $partial->input->context->competenceDate, $partial->input->context->knowledgeAt);
    $base = current(array_values(array_filter($allocated['trace'], static fn (array $row): bool => $row['stage'] === 'IMPORT_BRAZIL_ALLOCATION')));
    $nominal = current(array_values(array_filter($allocated['trace'], static fn (array $row): bool => $row['stage'] === 'IMPORT_NOMINAL_CALCULATION')));
    expect($allocated['status'])->toBe('ACTION_REQUIRED')
        ->and($base['brazil_taxable_base_brl'])->toBe('250.00')
        ->and($nominal['ibs_state'])->toBe('0.25')
        ->and($nominal['cbs'])->toBe('2.25');

    [, , $foreignCurrency] = rtcFixture('import-digital-foreign-currency-unconverted-2026');
    $blocked = $engine->evaluate($foreignCurrency->input->context->facts, $foreignCurrency->input->context->competenceDate, $foreignCurrency->input->context->knowledgeAt);
    expect($blocked['reason_codes'])->toContain('RTC_IMPORT_FX_CONVERSION_NOT_MATERIALIZED');

    [, , $unproven] = rtcFixture('import-digital-partial-unproven-2026');
    $missingAllocation = $engine->evaluate($unproven->input->context->facts, $unproven->input->context->competenceDate, $unproven->input->context->knowledgeAt);
    expect($missingAllocation['reason_codes'])->toContain('RTC_PARTIAL_IMPORT_ALLOCATION_UNPROVEN')
        ->and($missingAllocation['missing_facts'])->toContain('rtc.import_allocation_method', 'rtc.import_allocation_evidence_id');
});
