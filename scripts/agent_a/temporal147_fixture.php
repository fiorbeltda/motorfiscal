<?php

declare(strict_types=1);
use App\Modules\Fiscal\ConsumptionReform\TaxPointContext86;

/** @return array<string, mixed> */
function temporal147Policy(): array
{
    $path = dirname(__DIR__, 2).'/resources/fiscal/catalogs/rtc-transition-2026.10.15-91-draft1.json';
    $catalog = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    return $catalog['tax_point_event_policy'];
}

/** @return array<string, mixed> */
function temporal147Scenario(): array
{
    $root = dirname(__DIR__, 2).'/resources/fiscal/';
    $source = json_decode((string) file_get_contents($root.'scenarios/consumption-phase4b/regular-saas-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    $manifest = json_decode((string) file_get_contents($root.'bundles/consumption-phase4b-2026.10.15-91-draft1.json'), true, 512, JSON_THROW_ON_ERROR);
    $source['scenario_id'] = 'FIXTURE.TEMPORAL147';
    $source['knowledge_at'] = '2026-10-15T23:00:00+00:00';
    $source['rule_bundle_id'] = $manifest['bundle_id'];
    $source['catalog_versions'] = $manifest['catalog_versions'];
    $source['facts']['rtc.assess_tax_point_events'] = true;
    $source['facts']['rtc.tax_point_operation_id'] = 'OP147';
    $source['facts']['rtc.tax_point_contexts'] = [[
        'id' => 'OP147', 'document_id' => $source['facts']['rtc.document_id'],
        'mode' => 'ORDINARY', 'supply_kind' => 'INTANGIBLE_AVAILABILITY',
        'public_purchase' => false, 'cancelled' => false, 'total_amount' => '1000.00',
        'actual_supply' => ['operation_id' => 'OP147', 'document_id' => $source['facts']['rtc.document_id'],
            'kind' => 'INTANGIBLE_AVAILABILITY', 'date' => $source['facts']['rtc.operation_date'],
            'observed_on' => '2026-10-15', 'evidence_id' => 'FIXTURE.MATERIAL.SUPPLY.147'],
        'fiscal_document' => null, 'installments' => [],
    ]];

    return $source;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    if (($argv[1] ?? null) === '--write-scenario') {
        $directory = dirname(__DIR__, 2).'/storage/app/agent-a-A147';
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($directory.'/scenario.json', json_encode(temporal147Scenario(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        echo $directory.'/scenario.json'.PHP_EOL;

        exit(0);
    }
    $policy = temporal147Policy();
    $rows = [];
    foreach (['revoked_at', 'superseded_at'] as $key) {
        foreach (['2026-10-15T23:00:00.000001+00:00', '2026-10-15T23:00:00+00:00',
            '2026-10-15T20:00:00.000001-03:00', '2026-10-15T23:00:00.000001Z'] as $instant) {
            $observed = (new TaxPointContext86)
                ->policyAvailability(new DateTimeImmutable('2026-10-15T23:00:00+00:00'), [...$policy, $key => $instant]);
            $rows[] = ['field' => $key, 'lifecycle_at' => $instant, 'known_at' => '2026-10-15T23:00:00+00:00',
                'observed' => $observed, 'expected_status' => str_contains($instant, '.000001') ? 'RESOLVED' : 'ACTION_REQUIRED'];
        }
    }
    echo json_encode(['synthetic_lifecycle_not_official_revocation' => true, 'observations' => $rows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
}
