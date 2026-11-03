<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;

require_once __DIR__.'/domicile151_fixture.php';

/** @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function centralized152Rebind(array $input): array
{
    foreach (['scope_evidence', 'registry_evidence', 'centralized_purchase_evidence', 'regular_regime_evidence', 'credit_restriction_evidence'] as $key) {
        if (! isset($input[$key]['record'])) {
            continue;
        }
        $record = $input[$key]['record'];
        foreach (['operation_id', 'supply_id', 'supplier_id', 'acquirer_id', 'recipient_id', 'onerous',
            'acquirer_domiciled_in_brazil', 'recipient_domiciled_in_brazil'] as $field) {
            $record[$field] = $input[$field];
        }
        $record['applicable_on'] = $input['operation_date'];
        $input[$key] = ['source_id' => 'SYNTHETIC.SUPPLIED.PREDICATES.152', 'record' => $record, 'record_hash' => CanonicalJson::hash($record)];
    }

    return $input;
}

/** @return array<string, mixed> */
function centralized152Fixture(string $category = 'FIXED_COMMUNICATION'): array
{
    $input = domicile151Fixture($category === 'PERSONAL_REMOTE' ? 'remote-personal' : 'domestic');
    $input['operation_id'] = 'OP152';
    $input['supply_id'] = 'SUPPLY152';
    $input['knowledge_at'] = '2026-11-03T12:50:00Z';
    $input['supply_category'] = $category;
    $articles = ['FIXED_COMMUNICATION' => 'LC214 art.11 IX', 'MOVABLE_RENTAL' => 'LC214 art.11 §4 I',
        'RESIDUAL_SERVICE' => 'LC214 art.11 X', 'PERSONAL_REMOTE' => 'LC214 art.11 X e §5'];
    $input['scope_evidence']['record']['supply_category'] = $category;
    $input['scope_evidence']['record']['source_article'] = $articles[$category];
    $input['scope_evidence']['record']['centralized_purchase'] = true;
    $input['scope_evidence']['record']['physical_movable_rental'] = true;
    $input['registry_evidence']['record']['all_establishments_included'] = true;
    $party = $input['acquirer_id'];
    $common = ['entity_id' => $party, 'document_id' => 'SYNTHETIC.DOCUMENT152', 'observed_at' => '2026-11-03T12:40:00Z'];
    $input['centralized_purchase_evidence'] = ['record' => [...$common, 'role' => 'CENTRALIZED_ACQUISITION',
        'centralized' => true, 'complete' => true, 'headquarters_establishment_id' => 'HQ151',
        'covered_establishment_ids' => ['BRANCH151']]];
    $input['regular_regime_evidence'] = ['record' => [...$common, 'role' => 'ACQUIRER_IBS_CBS_REGIME',
        'ibs_regime' => 'REGULAR', 'cbs_regime' => 'REGULAR', 'valid_from' => '2026-01-01', 'valid_until' => null]];
    $input['credit_restriction_evidence'] = ['record' => [...$common, 'role' => 'CREDIT_PROHIBITION_QUALIFICATION',
        'complete' => true, 'qualification_reference' => 'SYNTHETIC.EXTERNAL.QUALIFICATION152.NOT_A_B_DELIVERY',
        'ibs_credit_prohibited' => false, 'cbs_credit_prohibited' => false]];
    unset($input['supply_establishment_evidence']);

    return centralized152Rebind($input);
}

/** @return array<string, mixed> */
function centralized152ServiceFixture(string $category = 'FIXED_COMMUNICATION'): array
{
    require_once __DIR__.'/locality149_fixture.php';
    $entity = centralized152Fixture($category);
    $input = locality149Fixture($category === 'FIXED_COMMUNICATION' ? 'FIXED_COMMUNICATION' : 'PERSONAL_PRESENTIAL');
    foreach (['operation_id', 'supply_id', 'supplier_id', 'recipient_id', 'operation_date', 'knowledge_at'] as $key) {
        $input[$key] = $entity[$key];
    }
    $scope = $input['scope_evidence']['record'];
    foreach (['operation_id', 'supply_id', 'supplier_id', 'recipient_id'] as $key) {
        $scope[$key] = $input[$key];
    }
    $scope['centralized_purchase'] = true;
    $scope['observed_at'] = '2026-11-03T12:40:00Z';
    $scope['delivery_mode'] = $category === 'PERSONAL_REMOTE' ? 'MIXED' : 'PRESENTIAL';
    $scope['partially_remote'] = $category === 'PERSONAL_REMOTE';
    $input['scope_evidence'] = locality149Envelope($scope);
    if ($category === 'FIXED_COMMUNICATION') {
        $input['centralized_purchase_qualification'] = domicile151Qualifier()->qualify($entity);
        $terminal = $input['locations'][0]['evidence']['record'];
        foreach (['operation_id', 'supply_id', 'supplier_id', 'recipient_id'] as $key) {
            $terminal[$key] = $input[$key];
        }
        $terminal['uf'] = 'RJ';
        $terminal['municipality_ibge'] = '3304557';
        $input['locations'][0]['uf'] = 'RJ';
        $input['locations'][0]['municipality_ibge'] = '3304557';
        $input['locations'][0]['evidence'] = locality149Envelope($terminal);
    } else {
        unset($input['locations']);
        $input['entity_domicile_qualification'] = domicile151Qualifier()->qualify($entity);
    }

    return $input;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    $directory = dirname(__DIR__, 2).'/storage/app/agent-a-A152';
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    $rows = [];
    foreach (['FIXED_COMMUNICATION', 'MOVABLE_RENTAL', 'RESIDUAL_SERVICE', 'PERSONAL_REMOTE'] as $category) {
        $input = centralized152Fixture($category);
        $result = domicile151Qualifier()->qualify($input);
        $rows[] = ['category' => $category, 'report' => $result, 'replay' => domicile151Qualifier()->replay($result)];
    }
    $inputs = ['centralized' => centralized152Fixture(), 'fixed-service' => centralized152ServiceFixture(),
        'personal-service' => centralized152ServiceFixture('PERSONAL_REMOTE')];
    $invalid = centralized152Fixture();
    unset($invalid['credit_restriction_evidence']);
    $inputs['invalid'] = $invalid;
    foreach ($inputs as $name => $input) {
        file_put_contents($directory.'/'.$name.'.json', json_encode($input, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
    echo json_encode(['synthetic_external_predicates' => true, 'B_delivery_received' => false, 'cases' => $rows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
}
