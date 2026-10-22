<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioExecution;

require_once __DIR__.'/phase4b_import129_fixture.php';

/** @param array<string, mixed> $overrides
 * @return array{array<string, mixed>, array<string, mixed>}
 */
function phase4bImport130Fixture(array $overrides = [], bool $recipient = false, string $knownAt = '2026-10-22T17:34:00+00:00'): array
{
    $factory = new FiscalEngineFactory;
    [$knowledge, $source] = phase4bImport129Fixture($factory, array_replace(['rtc.performance_municipality_ibge' => '3550308',
        'rtc.service_nbs_code' => '1.1502.20.00', 'rtc.service_nbs_evidence_id' => 'SYNTHETIC-IMPORT130-NBS'], $overrides), $recipient);
    $source['knowledge_at'] = $knownAt;
    $source['scenario_id'] = 'IMPORT130-SYNTHETIC-DOCUMENT';
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $result = $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input);
    $snapshot = (new ScenarioExecution($loaded, $result, $knowledge))->snapshot();
    $prior = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/dps-foreign-acquirer-2026.10.15-76-draft1/nif-identity.json')), true, 512, JSON_THROW_ON_ERROR);
    $party = $prior['foreign_acquirer'];
    $party['nif'] = $source['facts']['rtc.supplier_foreign_nif'];
    $party['regime'] = ['opSimpNac' => '1', 'regEspTrib' => '0', 'evidence_id' => 'SYNTHETIC-IMPORT130-SUPPLIER-REGIME'];
    $identity = ['environment' => '2', 'issued_at' => '2026-10-22T17:33:45+00:00',
        'application_version' => 'FISCAL-SANDBOX-130', 'series' => '1', 'number' => '130',
        'competence_date' => $source['competence_date'], 'issuer_municipality' => '3550308',
        'issuer_cnpj' => '22222222000191', 'acquirer_name' => 'Tomador sintético de importação',
        'service_municipality' => '3550308', 'total_tax_indicator' => '0',
        'identity_evidence_id' => 'SYNTHETIC-IMPORT130-IDENTITY',
        'service_location_evidence_id' => 'SYNTHETIC-IMPORT130-PERFORMANCE',
        'total_tax_evidence_id' => 'SYNTHETIC-IMPORT130-TOTAL-INDICATOR',
        'foreign_supplier' => $party, 'foreign_trade' => $prior['foreign_trade'],
        'foreign_trade_evidence_id' => 'SYNTHETIC-IMPORT130-COMEXT'];

    return [$snapshot, $identity];
}
