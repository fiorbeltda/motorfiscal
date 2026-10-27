<?php

declare(strict_types=1);

use App\Modules\Fiscal\NFSe\ImportDpsModel143;

require_once __DIR__.'/phase4b_import142_fixture.php';

/** New explicit documentary declarations for the synthetic ordinary import; no authenticated taxpayer/source claim. */
function import143Scenario(array $knowledge): array
{
    $input = import142Scenario($knowledge);
    $input['scenario_id'] = 'SYNTHETIC-COMPLETE-IMPORT-DPS143';
    $input['knowledge_at'] = (new DateTimeImmutable(ImportDpsModel143::AVAILABLE_AT))->modify('+5 seconds')->format('Y-m-d\TH:i:s.uP');

    return $input;
}

function import143Identity(array $input): array
{
    $old = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/dps-foreign-acquirer-2026.10.15-76-draft1/nif-identity.json')), true, 512, JSON_THROW_ON_ERROR);
    $party = $old['foreign_acquirer'];
    $party['name'] = 'Fornecedor estrangeiro sintético143';
    $party['nif'] = $input['facts']['rtc.supplier_foreign_nif'];
    $party['regime'] = ['opSimpNac' => '1', 'regEspTrib' => '0', 'evidence_id' => 'SYNTHETIC-FOREIGN-ORDINARY-REGIME143'];

    return ['contract' => ImportDpsModel143::CONTRACT, 'environment' => '2',
        'issued_at' => (new DateTimeImmutable(ImportDpsModel143::AVAILABLE_AT))->modify('+2 seconds')->format('Y-m-d\TH:i:sP'),
        'observed_at' => (new DateTimeImmutable(ImportDpsModel143::AVAILABLE_AT))->modify('+1 second')->format('Y-m-d\TH:i:s.uP'),
        'application_version' => 'FISCAL-SANDBOX-143', 'series' => '1', 'number' => '143',
        'competence_date' => $input['competence_date'], 'issuer_municipality' => '3550308',
        'issuer_cnpj' => $input['facts']['rtc.importer_id'], 'acquirer_name' => 'Tomador sintético143',
        'service_municipality' => $input['facts']['rtc.performance_municipality_ibge'], 'total_tax_indicator' => '0',
        'identity_evidence_id' => 'SYNTHETIC-IMPORT-IDENTITY143', 'service_location_evidence_id' => 'SYNTHETIC-PERFORMANCE143',
        'total_tax_evidence_id' => 'SYNTHETIC-TOTAL-INDICATOR143', 'foreign_supplier' => $party,
        'acquirer_address' => ['municipality' => '3550308', 'postal_code' => '01001000', 'street' => 'Praça da Sé',
            'number' => '1', 'district' => 'Centro', 'evidence_id' => 'SYNTHETIC-ACQUIRER-ADDRESS143'],
        'legacy_tax_document' => ['cst' => '99', 'evidence_id' => 'SYNTHETIC-PREQUALIFIED-DOCUMENT-LEGACY-VALUES143'],
        'final_consumption_indicator' => '0'];
}
