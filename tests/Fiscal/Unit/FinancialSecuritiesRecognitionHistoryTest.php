<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRealizationAnalyzer;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;

function tvmHistoryProof(array $value, string $id, string $period): array
{
    $start = $period.'-01';
    $p = ['qualified' => true, 'evidence_id' => 'SYNTHETIC.HISTORY.'.$id, 'supplier_id' => '11111111000191',
        'valid_from' => $start, 'valid_to' => (new DateTimeImmutable($start))->format('Y-m-t'), 'observed_on' => '2026-10-06',
        'scope' => 'SYNTHETIC_INDEPENDENT_POSITION_AND_RETAINED_ASSIGNMENT_REAL_REPLAY', 'value' => $value];
    $p['source_hash'] = CanonicalJson::hash($p);

    return $p;
}

function captureTvmHistoryDraft(string $name, array $snapshot): void
{
    if (getenv('TVM_HISTORY_CAPTURE') !== '1') {
        return;
    }
    $directory = dirname(__DIR__, 3).'/storage/app/phase4b-realization-history-draft-20261006-28';
    $path = $directory.'/'.$name.'-snapshot.json';
    $snapshot['input_hash'] = CanonicalJson::hash(array_diff_key($snapshot, ['result' => true]));
    if (is_file($path)) {
        expect(CanonicalJson::encode(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($snapshot));

        return;
    }
    $stream = fopen($path, 'x');
    expect($stream)->not->toBeFalse();
    fwrite($stream, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    fclose($stream);
}

function tvmHistoryFixture(bool $public = false): array
{
    $root = dirname(__DIR__, 3);
    $policy = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/tvm-recognition-history-draft-2026.10.06-28-v2.json'), true, 512, JSON_THROW_ON_ERROR);
    $period = $public ? '2026-09' : '2026-08';
    $known = new DateTimeImmutable($policy['published_at']);
    if ($public) {
        $snapshot = json_decode(file_get_contents($root.'/storage/app/phase4b-variable-yield-20261006-27/constant-snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
        $row = array_values(array_filter($snapshot['result']['trace'], static fn (array $v): bool => ($v['stage'] ?? null) === 'FINANCIAL_OPERATION_AND_BASE'))[0];
        $entry = array_values(array_filter($row['base']['entries'], static fn (array $v): bool => $v['id'] === 'yield1'))[0];
        $position = $entry['public_offer_debt']['position'];
        $availableById = ['yield1' => ['assessed_produced_return' => '98.17000000', 'excluded_art195_return' => '41.83000000',
            'removed_pis_cofins' => '10.00000000', 'deferred_fair_value' => '0.00000000']];
        $expected = $availableById['yield1'];
    } else {
        $snapshot = json_decode(file_get_contents($root.'/storage/app/phase4b-realization-history-draft-20261006-28/previous-private-august-snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
        $fixture = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-history-draft-2026.10.06-28/previous-private-august-position.json'), true, 512, JSON_THROW_ON_ERROR);
        $position = $fixture['position'];
        $availableById = ['prior-return' => ['assessed_produced_return' => '100.00000000', 'excluded_art195_return' => '0.00000000', 'removed_pis_cofins' => '0.00000000', 'deferred_fair_value' => '0.00000000'],
            'prior-fair-value' => ['assessed_produced_return' => '0.00000000', 'excluded_art195_return' => '0.00000000', 'removed_pis_cofins' => '0.00000000', 'deferred_fair_value' => '200.00000000']];
        $expected = ['assessed_produced_return' => '100.00000000', 'excluded_art195_return' => '0.00000000', 'removed_pis_cofins' => '0.00000000', 'deferred_fair_value' => '200.00000000'];
    }
    $daily = $policy['daily_memory_policies'][$period];
    $result = (new FinancialDebtDailyMemoryCalculator)->calculate($position, $daily['series'], $known, $daily);
    expect($result['status'])->toBe('RESOLVED');
    if (! $public) {
        foreach ($fixture['independent_oracle'] as $key => $value) {
            if ($key !== 'daily_memory') {
                expect($result[$key])->toBe($value);
            }
        }
        expect($result['memory_days'])->toBe(31)->and($result['daily_rates_applied'])->toBe(21);
    }
    $closing = ['contract' => 'TVM-RECOGNITION-CLOSING-MEMORY-1', 'position' => $position, 'result' => $result,
        'known_at' => $known->format(DATE_ATOM), 'policy_hash' => CanonicalJson::hash($daily)];
    $title = ['idTitulo' => $position['idTitulo'], 'cCta' => $position['cCta'], 'CNPJDevedor' => '11222333000181', 'currency' => 'BRL',
        'instrument_type' => $public ? 'CORPORATE_DEBT_PUBLIC_OFFER' : 'CORPORATE_DEBT_PRIVATE'];
    $scope = ['supplier_id' => $position['supplier_id'], 'period' => $public ? '2026-10' : '2026-09', 'title' => $title,
        'opening' => ['quantity' => $result['closing_quantity'], 'unit_cost' => $result['closing_unit_cost'],
            'acquisition_cost' => $result['closing_acquisition_cost'], 'book_balance' => $result['monthly']['vSaldoContFin']]];
    $sources = $references = [];
    foreach ($availableById as $id => $available) {
        $hash = CanonicalJson::hash($snapshot);
        $value = ['supplier_id' => $scope['supplier_id'], 'current_period' => $scope['period'], 'title' => $title,
            'snapshot_hash' => $hash, 'financial_entry_id' => $id, 'retained_share' => '1'];
        $proof = tvmHistoryProof($value, 'ASSIGN.'.$id, $period);
        $sources[] = ['snapshot' => $snapshot, 'financial_entry_id' => $id, 'retained_share' => '1', 'assignment_evidence' => $proof];
        $sourceDocument = $public ? 'variable-offer-yield1' : ($id === 'prior-return' ? 'contract1' : 'prior-fv-document');
        $sourceKey = CanonicalJson::hash(['supplier_id' => $scope['supplier_id'], 'period' => $period, 'document_id' => $sourceDocument, 'financial_entry_id' => $id]);
        $references[] = ['source_key' => $sourceKey, 'snapshot_hash' => $hash, 'decision_hash' => $snapshot['result']['decision_hash'],
            'snapshot_status' => $snapshot['result']['status'], 'period' => $period, 'entry_id' => $id, 'retained_share' => '1',
            'available' => $available, 'retained' => $available, 'assignment_evidence_hash' => $proof['source_hash']];
    }
    usort($references, static fn (array $a, array $b): int => $a['source_key'] <=> $b['source_key']);
    $proof = tvmHistoryProof(['scope' => $scope, 'sources' => $references, 'closing_memory_hash' => CanonicalJson::hash($closing),
        'opening_fiscal_components' => $expected], 'COMPLETE', $period);
    $history = ['closing_memory' => $closing, 'fiscal_sources' => $sources, 'complete_evidence' => $proof];

    return compact('scope', 'history', 'policy', 'known', 'expected', 'references');
}

it('replays actual captured financial decisions and prior closing memory with an exact split of taxable excluded and deferred amounts', function (bool $public): void {
    $f = tvmHistoryFixture($public);
    $r = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($f['scope'], $f['history'], $f['known'], $f['policy']);
    expect($r['status'])->toBe('RESOLVED')->and($r['opening_fiscal_components'])->toBe($f['expected'])
        ->and($r['fiscal_snapshots_replayed'])->toBe(1)->and($r['closing_memory_replayed'])->toBeTrue()
        ->and($r['production_ready'])->toBeFalse();
    if (! $public) {
        expect($r['sources'][0]['snapshot_status'])->toBe('ACTION_REQUIRED');
    }
    captureTvmHistoryDraft($public ? 'public-history' : 'private-history', ['contract' => 'TVM-RECOGNITION-HISTORY-SNAPSHOT-DRAFT-1',
        'scope' => $f['scope'], 'history' => $f['history'], 'rule' => $f['policy'], 'known_at' => $f['known']->format(DATE_ATOM), 'result' => $r]);
})->with([false, true]);

it('refuses inconsistent scopes knowledge histories positions or source assignments', function (string $mutation, string $reason): void {
    $f = tvmHistoryFixture();
    $scope = $f['scope'];
    $history = $f['history'];
    $policy = $f['policy'];
    switch ($mutation) {
        case 'opening': $scope['opening']['book_balance'] = '10301.00';
            break;
        case 'identity': $scope['title']['CNPJDevedor'] = '11222333000180';
            break;
        case 'supplier': $scope['supplier_id'] = '22222222000191';
            break;
        case 'closing-result': $history['closing_memory']['result']['closing_quantity'] = '999.00000000';
            break;
        case 'closing-policy': $history['closing_memory']['policy_hash'] = str_repeat('a', 64);
            break;
        case 'future': $history['closing_memory']['known_at'] = '2026-10-07T00:00:00+00:00';
            break;
        case 'empty': $history['fiscal_sources'] = [];
            break;
        case 'duplicate': $history['fiscal_sources'][] = $history['fiscal_sources'][0];
            break;
        case 'duplicate-envelope':
            $duplicate = $history['fiscal_sources'][0];
            $duplicate['snapshot']['transport_metadata'] = ['different_envelope' => true];
            $history['fiscal_sources'][] = $duplicate;
            break;
        case 'unknown-entry': $history['fiscal_sources'][0]['financial_entry_id'] = 'unknown';
            break;
        case 'missing-source': array_pop($history['fiscal_sources']);
            break;
        case 'retained-too-much': $history['fiscal_sources'][0]['retained_share'] = '1.1';
            break;
        case 'assignment': $history['fiscal_sources'][0]['assignment_evidence']['qualified'] = false;
            break;
        case 'complete': $history['complete_evidence']['qualified'] = false;
            break;
        case 'snapshot-result': $history['fiscal_sources'][0]['snapshot']['result']['decision'] = ['forged' => true];
            break;
        case 'snapshot-knowledge': $history['fiscal_sources'][0]['snapshot']['knowledge']['documents']['rtc_code_catalog']['version'] = 'forged';
            break;
        case 'manifest': $policy['permitted_bundle_manifest_hashes'] = [];
            break;
        case 'snapshot-date': $history['fiscal_sources'][0]['snapshot']['input']['competence_date'] = '2026-09-30';
            break;
    }
    $r = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($scope, $history, $f['known'], $policy);
    expect($r['reason'])->toBe('RTC_TVM_HISTORY_'.$reason)->and($r['production_ready'])->toBeFalse();
})->with([
    ['opening', 'CURRENT_OPENING_POSITION_CONFLICT'], ['identity', 'SCOPE_INVALID'], ['supplier', 'CLOSING_MEMORY_SCOPE_CONFLICT'],
    ['closing-result', 'CLOSING_MEMORY_REPLAY_MISMATCH'], ['closing-policy', 'CLOSING_MEMORY_SCOPE_CONFLICT'], ['future', 'CLOSING_MEMORY_SCOPE_CONFLICT'],
    ['empty', 'SOURCE_COVERAGE_INVALID'], ['duplicate', 'DUPLICATE_FISCAL_SOURCE'], ['duplicate-envelope', 'DUPLICATE_FISCAL_SOURCE'], ['unknown-entry', 'SOURCE_ENTRY_ABSENT_OR_DUPLICATE'],
    ['missing-source', 'CLOSING_CARRYING_COMPONENT_CONFLICT'], ['retained-too-much', 'RETAINED_SHARE_INVALID'],
    ['assignment', 'INDEPENDENT_RETAINED_ASSIGNMENT_UNPROVEN'], ['complete', 'COMPLETE_HISTORY_UNPROVEN'],
    ['snapshot-result', 'FISCAL_SNAPSHOT_REPLAY_MISMATCH'], ['snapshot-knowledge', 'CAPTURED_KNOWLEDGE_OR_REPLAY_INVALID'],
    ['manifest', 'FISCAL_KNOWLEDGE_NOT_PERMITTED'], ['snapshot-date', 'FISCAL_PERIOD_OR_KNOWLEDGE_CONFLICT'],
]);

function tvmHistoryIntegrationFixture(): array
{
    $f = tvmHistoryFixture();
    $root = dirname(__DIR__, 3);
    $v = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-realization-draft-2026.10.06-28/mixed-components.json'), true, 512, JSON_THROW_ON_ERROR);
    $position = $v['position'];
    $title = [...$f['scope']['title'], 'creditor_in_financial_specific_regime' => true, 'holding_route' => 'DIRECT'];
    $scope = [...$f['scope'], 'title' => $title, 'opening' => $position['opening']];
    $references = $f['references'];
    foreach ($f['history']['fiscal_sources'] as $i => &$source) {
        $value = $source['assignment_evidence']['value'];
        $value['title'] = $title;
        $source['assignment_evidence'] = tvmHistoryProof($value, 'ASSIGN.'.$source['financial_entry_id'], '2026-08');
        foreach ($references as &$reference) {
            if ($reference['entry_id'] === $source['financial_entry_id']) {
                $reference['assignment_evidence_hash'] = $source['assignment_evidence']['source_hash'];
            }
        }
        unset($reference);
    }
    unset($source);
    $f['history']['complete_evidence'] = tvmHistoryProof(['scope' => $scope, 'sources' => $references,
        'closing_memory_hash' => CanonicalJson::hash($f['history']['closing_memory']), 'opening_fiscal_components' => $f['expected']], 'COMPLETE', '2026-08');
    $daily = $f['policy']['daily_memory_policies']['2026-09'];
    $rule = ['contract' => FinancialSecuritiesRealizationAnalyzer::CONTRACT, 'status' => 'ACTIVE',
        'published_at' => $f['policy']['published_at'], 'source_hash' => $f['policy']['source_hash'],
        'daily_memory_calculation' => $daily, 'historical_snapshot_required' => true, 'history_policy' => $f['policy']];
    $memory = (new FinancialDebtDailyMemoryCalculator)->calculate($position, $daily['series'], $f['known'], $daily);
    expect($memory['status'])->toBe('RESOLVED');
    foreach ($v['independent_daily_oracle'] as $key => $expected) {
        if ($key !== 'daily_memory') {
            expect($memory[$key])->toBe($expected);
        }
    }
    $sale = [];
    foreach ($memory['daily_memory'] as $day) {
        foreach ($day['operations'] as $op) {
            if ($op['kind'] === 'SELL') {
                $sale = ['date' => $day['date'], ...$op];
            }
        }
    }
    $historical = ['snapshot_id' => CanonicalJson::hash($references), 'period' => '2026-08',
        'previously_recognized_produced_return' => '40', 'previously_assessed_produced_return' => '40',
        'previously_excluded_art195_return' => '0', 'previously_removed_pis_cofins' => '0',
        'deferred_fair_value_released' => '80', 'fair_value_subaccount' => 'FV000'];
    $historyProof = tvmHistoryProof(['supplier_id' => $position['supplier_id'], 'idTitulo' => $title['idTitulo'], 'cCta' => $title['cCta'],
        'operation_id' => $sale['id'], 'quantity' => $sale['quantity'], 'history' => $historical], 'SALE.HISTORY', '2026-08');
    $componentProof = tvmHistoryProof(['supplier_id' => $position['supplier_id'], 'period' => $position['period'],
        'idTitulo' => $title['idTitulo'], 'cCta' => $title['cCta'], 'sale' => $sale, 'components' => $v['components'],
        'history_evidence_hash' => $historyProof['source_hash']], 'SALE.COMPONENTS', '2026-09');
    $inventory = $v['recognition_inventory'];
    $inventory['closing_fiscal_components'] = ['assessed_produced_return' => '60', 'excluded_art195_return' => '0', 'removed_pis_cofins' => '0'];
    $input = ['position' => $position, 'title' => $title, 'recognition_history' => $f['history'], 'recognition_inventory' => $inventory,
        'realizations' => [['operation_id' => $sale['id'], 'components' => $v['components'], 'history' => $historical,
            'history_evidence' => $historyProof, 'component_evidence' => $componentProof]]];
    $common = ['supplier_id' => $position['supplier_id'], 'period' => $position['period']];
    $input['classification_evidence'] = tvmHistoryProof([...$common, 'title' => $title, 'position_input_hash' => CanonicalJson::hash($position)], 'CLASSIFICATION', '2026-09');
    $input['inventory_evidence'] = tvmHistoryProof([...$common, 'idTitulo' => $title['idTitulo'], 'cCta' => $title['cCta'],
        'position_input_hash' => CanonicalJson::hash($position), 'inventory' => $inventory], 'INVENTORY', '2026-09');
    $input['complete_evidence'] = tvmHistoryProof([...$common, 'position_input_hash' => CanonicalJson::hash($position), 'title' => $title,
        'realizations' => $input['realizations'], 'recognition_inventory' => $inventory, 'inventory_evidence_hash' => $input['inventory_evidence']['source_hash']], 'CURRENT.COMPLETE', '2026-09');

    return ['input' => $input, 'rule' => $rule, 'known' => $f['known']];
}

it('binds the current disposal and its remaining recognition balances to a fully reproduced prior financial base', function (): void {
    $f = tvmHistoryIntegrationFixture();
    $r = (new FinancialSecuritiesRealizationAnalyzer)->analyze($f['input'], $f['rule'], $f['known']);
    expect($r['status'])->toBe('RECONCILED')->and($r['historical_fiscal_snapshot_replay_performed'])->toBeTrue()
        ->and($r['replayed_fiscal_history']['fiscal_snapshots_replayed'])->toBe(1)
        ->and($r['realizations'][0]['previously_assessed_return'])->toBe('40')
        ->and(BigDecimal::of($r['realizations'][0]['ordinary_current_component'])->isEqualTo('160'))->toBeTrue()
        ->and($r['recognition_inventory']['closing_fiscal_components']['assessed_produced_return'])->toBe('60')
        ->and($r['financial_base_integration_performed'])->toBeFalse()->and($r['xml_projection_performed'])->toBeFalse();
    captureTvmHistoryDraft('current-realization-history', ['contract' => 'TVM-REALIZATION-SNAPSHOT-DRAFT-1',
        'input' => $f['input'], 'rule' => $f['rule'], 'known_at' => $f['known']->format(DATE_ATOM), 'result' => $r]);
});

it('keeps snapshot history mandatory and rejects contradictory current historical references or balances', function (string $mutation, string $reason): void {
    $f = tvmHistoryIntegrationFixture();
    $input = $f['input'];
    switch ($mutation) {
        case 'history-missing': unset($input['recognition_history']);
            break;
        case 'sale-reference': $input['realizations'][0]['history']['snapshot_id'] = 'SYNTHETIC.UNBOUND';
            break;
        case 'pis-undeclared': unset($input['realizations'][0]['history']['previously_removed_pis_cofins']);
            break;
        case 'closing-missing': unset($input['recognition_inventory']['closing_fiscal_components']);
            break;
        case 'closing-conflict': $input['recognition_inventory']['closing_fiscal_components']['assessed_produced_return'] = '100';
            break;
    }
    if (str_starts_with($mutation, 'closing-')) {
        $p = $input['inventory_evidence'];
        $p['value']['inventory'] = $input['recognition_inventory'];
        unset($p['source_hash']);
        $p['source_hash'] = CanonicalJson::hash($p);
        $input['inventory_evidence'] = $p;
    }
    $r = (new FinancialSecuritiesRealizationAnalyzer)->analyze($input, $f['rule'], $f['known']);
    expect($r['reason'])->toBe('RTC_TVM_REALIZATION_'.$reason);
})->with([
    ['history-missing', 'REPLAYED_FISCAL_HISTORY_MISSING'], ['sale-reference', 'SALE_FISCAL_HISTORY_REFERENCE_CONFLICT'],
    ['pis-undeclared', 'SALE_FISCAL_HISTORY_REFERENCE_CONFLICT'], ['closing-missing', 'CLOSING_FISCAL_COMPONENTS_MISSING'],
    ['closing-conflict', 'CLOSING_FISCAL_COMPONENT_CONFLICT'],
]);
