<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioExecution;
use App\Modules\Fiscal\NFSe\ImportNt009Model142;

require_once dirname(__DIR__, 3).'/scripts/phase4b_import142_fixture.php';

function import142Snapshot(string $invoiceId = '0002026001'): array
{
    static $knowledge;
    $factory = new FiscalEngineFactory;
    $knowledge ??= $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.27-141-draft1.json');
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray(import142Scenario($knowledge, $invoiceId));
    $result = $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input);

    return (new ScenarioExecution($loaded, $result, $knowledge))->snapshot();
}

function import142Project(array $snapshot, ?array $declaration = null, ?string $root = null): array
{
    $source = CanonicalJson::encode(import141Invoice($snapshot['input']));
    $evidence = ['id' => 'SYNTHETIC-NORMALIZED-INVOICE142', 'sha256' => hash('sha256', $source), 'observed_at' => $snapshot['input']['knowledge_at']];

    return (new ImportNt009Model142)->project($snapshot, $source, $evidence,
        $declaration ?? import142Comext($snapshot['input']), $root ?? resource_path('fiscal/sources/official'));
}

it('models the observed NT009 invoice and currency fields without changing ISS vServ or producing unsupported XML', function (): void {
    $snapshot = import142Snapshot();
    $report = import142Project($snapshot);
    expect($report['status'])->toBe('FIELD_MODEL_PREPARED_OBSERVED_SCHEMA_INCOMPATIBLE')
        ->and($report['fields']['DPS.infDPS.serv.comExt.nFatura'])->toBe('0002026001')
        ->and($report['fields']['DPS.infDPS.serv.comExt.vFatura'])->toBe('1000.00')
        ->and($report['fields']['DPS.infDPS.serv.comExt.tpMoeda'])->toBe('220')
        ->and($report['fields']['DPS.infDPS.valores.vServPrest.vServ'])->toBe('5199.10')
        ->and($report['fields']['DPS.infDPS.valores.vAjusteBC.vAjusteBCIBSCBSComExt'])->toBe('-19.60')
        ->and($report['fields'])->not->toHaveKey('DPS.infDPS.serv.comExt.nContCambio')
        ->and($report['schema_coverage']['absent_elements'])->toBe(['nFatura', 'vFatura', 'nContCambio', 'vAjusteBCIBSCBSComExt'])
        ->and($report['xml_generated'])->toBeFalse()
        ->and($report['document_obligation_complete'])->toBeFalse();
});

it('preserves an optional numeric payment reference without claiming authenticated payment', function (): void {
    $snapshot = import142Snapshot();
    $declaration = import142Comext($snapshot['input']);
    $declaration['comext']['nContCambio'] = '0000012345';
    $declaration['payment_evidence_id'] = 'SYNTHETIC-DECLARED-PAYMENT-142';
    $report = import142Project($snapshot, $declaration);
    expect($report['fields']['DPS.infDPS.serv.comExt.nContCambio'])->toBe('0000012345')
        ->and($report['payment_authenticated'])->toBeFalse();
});

it('does not rewrite original invoice identifiers to fit the observed numeric facet', function (string $id): void {
    $report = import142Project(import142Snapshot($id));
    expect($report['status'])->toBe('ACTION_REQUIRED')
        ->and($report['reason'])->toBe('IMPORT_NT009142_ORIGINAL_INVOICE_ID_NOT_REPRESENTABLE_IN_OBSERVED_NFATURA_FACET');
})->with(['foreign-invoice-1', '123-456', '123456789012345678901']);

it('refuses contradictory incomplete or unmapped COMEX declarations', function (string $case): void {
    $snapshot = import142Snapshot();
    $declaration = import142Comext($snapshot['input']);
    match ($case) {
        'invoice' => $declaration['invoice_id'] = 'OTHER',
        'evidence' => $declaration['evidence_id'] = '',
        'future' => $declaration['observed_at'] = '2030-01-01T00:00:00Z',
        'date' => $declaration['observed_at'] = '2026-02-31T00:00:00Z',
        'mode' => $declaration['comext']['mdPrestacao'] = '5',
        'currency' => $declaration['comext']['tpMoeda'] = '790',
        'unknown relationship' => $declaration['comext']['vincPrest'] = '9',
        'benefit' => $declaration['comext']['mecAFComexP'] = '02',
        'goods' => $declaration['comext']['movTempBens'] = '2',
        'mdic' => $declaration['comext']['mdic'] = '3',
        'legacy price field' => $declaration['comext']['vServMoeda'] = '1000.00',
        'alpha payment' => $declaration['comext']['nContCambio'] = 'PAYMENT-1',
        'unbound payment' => $declaration['comext']['nContCambio'] = '123',
        'unbound evidence' => $declaration['payment_evidence_id'] = 'OTHER',
        'unknown contract' => $declaration['contract'] = 'UNKNOWN',
    };
    $report = import142Project($snapshot, $declaration);
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report['xml_generated'])->toBeFalse()
        ->and($report['reason'])->toStartWith('IMPORT_NT009142_');
})->with(['invoice', 'evidence', 'future', 'date', 'mode', 'currency', 'unknown relationship', 'benefit', 'goods', 'mdic',
    'legacy price field', 'alpha payment', 'unbound payment', 'unbound evidence', 'unknown contract']);

it('requires the exact primary technical sources', function (): void {
    $report = import142Project(import142Snapshot(), root: '/not-an-official-source-root');
    expect($report['reason'])->toBe('IMPORT_NT009142_PRIMARY_SOURCE_HASH_MISMATCH');
});
