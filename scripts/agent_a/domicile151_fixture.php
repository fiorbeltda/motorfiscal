<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\EntityDomicile151;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

/** @param array<string, mixed> $record
 * @return array<string, mixed>
 */
function domicile151Envelope(array $record): array
{
    return ['source_id' => 'SYNTHETIC.MATERIAL.RECORD.151', 'record' => $record, 'record_hash' => CanonicalJson::hash($record)];
}

/** Rebind synthetic records after explicit scenario changes, never an authenticity assertion.
 * @param  array<string, mixed>  $input
 * @return array<string, mixed>
 */
function domicile151Rebind(array $input): array
{
    foreach (['scope_evidence', 'registry_evidence', 'supply_establishment_evidence'] as $key) {
        $record = $input[$key]['record'];
        foreach (['operation_id', 'supply_id', 'supplier_id', 'acquirer_id', 'recipient_id', 'onerous',
            'acquirer_domiciled_in_brazil', 'recipient_domiciled_in_brazil'] as $field) {
            $record[$field] = $input[$field];
        }
        $record['applicable_on'] = $input['operation_date'];
        $input[$key] = domicile151Envelope($record);
    }

    return $input;
}

/** @return array<string, mixed> */
function domicile151Fixture(string $variant = 'domestic'): array
{
    $onerous = $variant !== 'nononerous';
    $foreign = $variant === 'foreign-acquirer';
    $party = ! $onerous || $foreign ? 'RECIPIENT151' : 'ACQUIRER151';
    $category = $variant === 'remote-personal' ? 'PERSONAL_REMOTE' : 'RESIDUAL_SERVICE';
    $scope = ['supply_category' => $category, 'scope_confirmed' => true,
        'source_article' => $category === 'PERSONAL_REMOTE' ? 'LC214 art.11 X e §5' : 'LC214 art.11 X',
        'outside_inciso_I_to_IX' => true, 'normally_personal_physical' => true,
        'remotely_performed_at_least_partly' => true, 'centralized_purchase' => false];
    $registry = ['role' => 'UNIQUE_REGISTRATION_ESTABLISHMENTS', 'entity_id' => $party, 'country' => 'BR',
        'party_kind' => $variant === 'unincorporated' ? 'UNINCORPORATED_ENTITY' : 'LEGAL_ENTITY',
        'document_id' => 'SYNTHETIC.REGISTRY151', 'regularly_registered' => true, 'unique_registration' => true,
        'complete_for_supplied_establishments' => true,
        'establishments' => [
            ['id' => 'HQ151', 'entity_id' => $party, 'uf' => 'SP', 'municipality_ibge' => '3550308',
                'headquarters' => true, 'valid_from' => '2026-01-01', 'valid_until' => null],
            ['id' => 'BRANCH151', 'entity_id' => $party, 'uf' => 'RJ', 'municipality_ibge' => '3304557',
                'headquarters' => false, 'valid_from' => '2026-01-01', 'valid_until' => null],
        ]];
    $delivery = ['role' => 'ACTUAL_SUPPLY_ESTABLISHMENTS', 'entity_id' => $party,
        'document_id' => 'SYNTHETIC.SUPPLY151', 'complete' => true, 'supplied_establishment_ids' => ['BRANCH151']];
    $input = ['operation_id' => 'OP151', 'supply_id' => 'SUPPLY151', 'supplier_id' => 'SUPPLIER151',
        'acquirer_id' => $onerous ? 'ACQUIRER151' : null, 'recipient_id' => 'RECIPIENT151',
        'onerous' => $onerous, 'acquirer_domiciled_in_brazil' => $onerous ? ! $foreign : null,
        'recipient_domiciled_in_brazil' => true, 'operation_date' => '2026-10-20',
        'knowledge_at' => '2026-11-03T12:30:00Z', 'supply_category' => $category];
    foreach (['scope_evidence' => $scope, 'registry_evidence' => $registry, 'supply_establishment_evidence' => $delivery] as $key => $record) {
        $input[$key] = domicile151Envelope([...$record, 'observed_at' => '2026-11-03T12:25:00Z']);
    }

    return domicile151Rebind($input);
}

function domicile151Qualifier(): EntityDomicile151
{
    $codes = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/resources/fiscal/catalogs/rtc-codes-2026.10.27-141-draft1.json'), true, 512, JSON_THROW_ON_ERROR);

    return new EntityDomicile151($codes['observed_geographic_reference'], $codes['observed_geographic_reference_policy']);
}

/** @return array<string, mixed> */
function domicile151ServiceFixture(): array
{
    require_once __DIR__.'/locality149_fixture.php';
    $entity = domicile151Fixture('remote-personal');
    $input = locality149Fixture('PERSONAL_PRESENTIAL');
    foreach (['operation_id', 'supply_id', 'supplier_id', 'recipient_id', 'operation_date', 'knowledge_at'] as $key) {
        $input[$key] = $entity[$key];
    }
    $scope = $input['scope_evidence']['record'];
    foreach (['operation_id', 'supply_id', 'supplier_id', 'recipient_id'] as $key) {
        $scope[$key] = $input[$key];
    }
    $scope['observed_at'] = '2026-11-03T12:25:00Z';
    $scope['partially_remote'] = true;
    $scope['delivery_mode'] = 'MIXED';
    $input['scope_evidence'] = locality149Envelope($scope);
    unset($input['locations'], $input['transport_document_id']);
    $input['entity_domicile_qualification'] = domicile151Qualifier()->qualify($entity);

    return $input;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    $directory = dirname(__DIR__, 2).'/storage/app/agent-a-A151';
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    $rows = [];
    foreach (['domestic', 'foreign-acquirer', 'nononerous', 'unincorporated', 'remote-personal'] as $variant) {
        $input = domicile151Fixture($variant);
        $result = domicile151Qualifier()->qualify($input);
        $rows[] = ['variant' => $variant, 'result' => $result, 'replay' => domicile151Qualifier()->replay($result)];
    }
    file_put_contents($directory.'/domestic.json', json_encode(domicile151Fixture(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $invalid = domicile151Fixture();
    $invalid['supply_establishment_evidence']['record']['supplied_establishment_ids'] = ['UNKNOWN151'];
    $invalid = domicile151Rebind($invalid);
    file_put_contents($directory.'/invalid.json', json_encode($invalid, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    file_put_contents($directory.'/service.json', json_encode(domicile151ServiceFixture(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(['cases' => $rows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
}
