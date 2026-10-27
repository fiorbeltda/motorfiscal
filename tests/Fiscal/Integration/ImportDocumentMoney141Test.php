<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioExecution;
use App\Modules\Fiscal\NFSe\ImportDocumentMoney141;

require_once dirname(__DIR__, 3).'/scripts/phase4b_import141_fixture.php';

function import141Snapshot(): array
{
    static $snapshot;
    if ($snapshot === null) {
        $factory = new FiscalEngineFactory;
        $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.27-141-draft1.json');
        $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray(import141Scenario($knowledge));
        $result = $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input);
        $snapshot = (new ScenarioExecution($loaded, $result, $knowledge))->snapshot();
    }

    return $snapshot;
}

function import141Evidence(array $snapshot, string $source): array
{
    return ['id' => 'SYNTHETIC-NORMALIZED-INVOICE141', 'sha256' => hash('sha256', $source),
        'observed_at' => $snapshot['input']['knowledge_at']];
}

it('reconciles original currency and the separate ISS and RTC monetary memories including explicit legacy exclusions', function (): void {
    $snapshot = import141Snapshot();
    $source = CanonicalJson::encode(import141Invoice($snapshot['input']));
    $report = (new ImportDocumentMoney141)->reconcile($snapshot, $source, import141Evidence($snapshot, $source));
    expect($snapshot['result']['reason_codes'])->toBe(['RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN'])
        ->and($report['status'])->toBe('LOCAL_NORMALIZED_INVOICE_MONETARY_RECONCILED')
        ->and($report['iss_document_price_brl'])->toBe('5199.10')
        ->and($report['rtc_converted_gross_brl'])->toBe('5179.50')
        ->and($report['fx_adjustment_brl'])->toBe('-19.60')
        ->and($report['legacy_exclusions_brl'])->toBe('739.06')
        ->and($report['rtc_taxable_base_brl'])->toBe('4440.44')
        ->and($report['source_fields_extracted'])->toBeTrue()
        ->and($report['legacy_tax_assessment_performed'])->toBeFalse()
        ->and($report['xml_generated'])->toBeFalse()
        ->and($report['document_obligation_complete'])->toBeFalse();
});

it('refuses canonical newly recaptured invoice contradictions', function (string $field): void {
    $snapshot = import141Snapshot();
    $invoice = import141Invoice($snapshot['input']);
    match ($field) {
        'price' => $invoice['service_price'] = '999.00',
        'currency' => $invoice['currency'] = 'BRL',
        'invoice' => $invoice['invoice_id'] = 'OTHER',
        'supplier' => $invoice['supplier_nif'] = 'OTHER',
        'customer' => $invoice['acquirer_id'] = 'OTHER',
        'service' => $invoice['service_id'] = 'SOFTWARE_DEVELOPMENT',
        'nbs' => $invoice['nbs_code'] = '1.1502.20.00',
        'supply' => $invoice['supply_on'] = '2026-09-24',
        'event' => $invoice['supply_event_reference'] = 'OTHER',
        'missing tax' => array_pop($invoice['legacy_taxes']),
        'extra tax' => $invoice['legacy_taxes'][] = $invoice['legacy_taxes'][0],
        'tax amount' => $invoice['legacy_taxes'][1]['amount'] = '0.00',
        'tax evidence' => $invoice['legacy_taxes'][1]['evidence_id'] = 'OTHER',
        'unknown field' => $invoice['total'] = '1000.00',
    };
    $source = CanonicalJson::encode($invoice);
    $report = (new ImportDocumentMoney141)->reconcile($snapshot, $source, import141Evidence($snapshot, $source));
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report['xml_generated'])->toBeFalse();
})->with(['price', 'currency', 'invoice', 'supplier', 'customer', 'service', 'nbs', 'supply', 'event',
    'missing tax', 'extra tax', 'tax amount', 'tax evidence', 'unknown field']);

it('rejects absent mismatched future or ambiguous source evidence', function (string $case): void {
    $snapshot = import141Snapshot();
    $source = CanonicalJson::encode(import141Invoice($snapshot['input']));
    $evidence = import141Evidence($snapshot, $source);
    match ($case) {
        'hash' => $evidence['sha256'] = str_repeat('0', 64),
        'future' => $evidence['observed_at'] = '2030-01-01T00:00:00Z',
        'invalid date' => $evidence['observed_at'] = '2026-02-31T00:00:00Z',
        'blank id' => $evidence['id'] = '',
        'missing date' => $evidence = array_diff_key($evidence, ['observed_at' => true]),
        'duplicate source key' => $source = substr($source, 0, -1).',"currency":"USD"}',
        'extra whitespace' => $source .= "\n",
        'invalid json' => $source = '{broken',
    };
    $report = (new ImportDocumentMoney141)->reconcile($snapshot, $source, $evidence);
    expect($report['status'])->toBe('ACTION_REQUIRED');
})->with(['hash', 'future', 'invalid date', 'blank id', 'missing date', 'duplicate source key', 'extra whitespace', 'invalid json']);

it('keeps the prior98 rules and all catalog sections unchanged in the additive141 catalog', function (): void {
    $knowledge = import141Snapshot()['knowledge'];
    $factory = new FiscalEngineFactory;
    $old = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.27-140-draft1.json');
    $prior = $old['documents']['rtc_code_catalog'];
    $current = $knowledge['documents']['rtc_code_catalog'];
    unset($current['engine_decision_rules'][ImportDocumentMoney141::RULE]);
    $current['version'] = $prior['version'];
    expect(CanonicalJson::hash($current))->toBe(CanonicalJson::hash($prior));
});

it('does not select the consultancy rule without its explicit NBS scope', function (string $nbs): void {
    $snapshot = import141Snapshot();
    $input = $snapshot['input'];
    $input['facts']['rtc.service_nbs_code'] = $nbs;
    $factory = new FiscalEngineFactory;
    $loaded = $factory->scenarioLoaderFromKnowledge($snapshot['knowledge'])->fromArray($input);
    $result = $factory->makeFromKnowledge($snapshot['knowledge'])->evaluate($loaded->input)->toArray();
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'])->toContain('RTC_ENGINE_DECISION_RULE_NOT_PROVEN');
})->with(['1.1502.20.00', '1.1401.12.00']);

it('rejects contradictory or ambiguous monetary memories before reporting reconciliation', function (string $case): void {
    $snapshot = import141Snapshot();
    foreach ($snapshot['result']['trace'] as &$trace) {
        if ($trace['type'] === 'ISS_CALCULATION' && $case === 'iss amount') {
            $trace['payload']['gross_amount'] = '5179.50';
        }
        if ($trace['type'] === 'CONSUMPTION_REFORM') {
            foreach ($trace['payload']['trace'] as &$stage) {
                if ($stage['stage'] === 'BASE' && $case === 'rtc base') {
                    $stage['taxable_base'] = '5179.50';
                }
                if ($stage['stage'] === 'IMPORT_FX_TRANSACTION' && $case === 'unbound fx') {
                    $stage['transaction_locally_bound'] = false;
                }
                if ($stage['stage'] === 'CLASSIFICATION' && $case === 'other rule') {
                    $stage['rule_id'] = 'RTC.CLASSIFICATION.IMPORTED_SAAS.RESIDUAL.SANDBOX-1';
                }
            }
            unset($stage);
        }
    }
    unset($trace);
    if ($case === 'duplicate iss') {
        $snapshot['result']['trace'][] = array_values(array_filter($snapshot['result']['trace'], fn (array $t): bool => $t['type'] === 'ISS_CALCULATION'))[0];
    }
    if ($case === 'final decision') {
        $snapshot['result']['status'] = 'VALID';
    }
    if ($case === 'partial consumption') {
        $snapshot['input']['facts']['rtc.import_brazil_share_percent'] = '50';
    }
    $source = CanonicalJson::encode(import141Invoice($snapshot['input']));
    $report = (new ImportDocumentMoney141)->reconcile($snapshot, $source, import141Evidence($snapshot, $source));
    expect($report['status'])->toBe('ACTION_REQUIRED');
})->with(['iss amount', 'rtc base', 'unbound fx', 'other rule', 'duplicate iss', 'final decision', 'partial consumption']);
