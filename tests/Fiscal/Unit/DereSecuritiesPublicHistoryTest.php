<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialPublicOfferDebtQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

function tvmPublicHistoryData(string $case): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $read = static fn (string $path): array => json_decode(file_get_contents($root.$path), true, 512, JSON_THROW_ON_ERROR);
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.07-28-draft12.json');
    $folder = 'scenarios/tvm-public-history-2026.10.07-28-draft12/';
    $scenario = $read($folder.$case.'-financial.json');

    return [$scenario, $read($folder.$case.'-month.json'), $read($folder.$case.'-oracle.json'), $knowledge, $factory];
}

it('replays prior taxable excluded and removed tax public yield and never includes it again at disposal', function (string $case): void {
    [$scenario, $month, $oracle, $knowledge, $factory] = tvmPublicHistoryData($case);
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($scenario);
    $result = (new RtcScenarioRunService($factory))->evaluate($knowledge, $loaded)->toArray();
    expect($result['reason_codes'])->toBe(['RTC_FINANCIAL_DOCUMENT_PENDING']);
    $operation = array_column($result['trace'], null, 'stage')['FINANCIAL_OPERATION_AND_BASE'];
    $entry = array_values(array_filter($operation['base']['entries'], static fn (array $e): bool => $e['id'] === 'yield1'))[0];
    $bridge = $entry['d2101_binding']['sale_recognition_binding'];
    $history = $bridge['analysis']['replayed_fiscal_history'];
    expect($operation['base']['net_base'])->toBe($oracle['expected_base'])
        ->and($history['opening_fiscal_components'])->toBe($oracle['prior_available'])
        ->and($history['fiscal_snapshots_replayed'])->toBe(1)->and($history['closing_memory_replayed'])->toBeTrue()
        ->and($bridge['analysis']['historical_fiscal_snapshot_replay_performed'])->toBeTrue()
        ->and($bridge['additional_sale_taxable_amount'])->toBe('0.00')->and($bridge['production_ready'])->toBeFalse()
        ->and($entry['d2101_binding']['xml_title']['vVarMensal'])->toBe('50.00');
    $catalog = $knowledge['documents']['rtc_code_catalog'];
    $monthly = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $loaded->input->context->knowledgeAt,
        ['closing' => $catalog['dere_monthly_closing_projection'], 'balance' => $catalog['dere_trial_balance_projection'],
            'deductions' => $catalog['dere_deduction_projection'], 'pgcc_projection' => $catalog['dere_pgcc_projection'],
            'contributor_projection' => $catalog['dere_contributor_projection']]);
    expect($monthly['status'])->toBe('PROJECTED')->and($monthly['debt_yield_projection']['total_vApur'])->toBe($oracle['independent_oracle']['monthly']['vApur']);
})->with(['partial', 'complete', 'exclusive-fund', 'two-sales']);

it('requires actual public history intact knowledge nonduplicated source and all prior fiscal pools', function (string $mutation, string $reason): void {
    [$scenario, , , $knowledge] = tvmPublicHistoryData('partial');
    $ledger = $scenario['facts']['rtc.financial_ledger'][0];
    $rule = $knowledge['documents']['rtc_code_catalog']['financial_operation']['base']['public_offer_debt'];
    $input = &$ledger['entries'][4]['public_offer_debt']['sale_recognition']['analysis_input'];
    switch ($mutation) {
        case 'missing': unset($input['recognition_history']);
            break;
        case 'result': $input['recognition_history']['fiscal_sources'][0]['snapshot']['result']['decision'] = ['forged' => true];
            break;
        case 'knowledge': $input['recognition_history']['fiscal_sources'][0]['snapshot']['knowledge']['documents']['rtc_code_catalog']['version'] = 'forged';
            break;
        case 'duplicate': $input['recognition_history']['fiscal_sources'][] = $input['recognition_history']['fiscal_sources'][0];
            break;
        case 'manifest': $rule['sale_recognition_policy']['analysis']['history_policy']['permitted_bundle_manifest_hashes'] = [];
            break;
        case 'sale-reference': $input['realizations'][0]['history']['snapshot_id'] = 'SYNTHETIC.UNBOUND';
            break;
        case 'pis': unset($input['realizations'][0]['history']['previously_removed_pis_cofins']);
            break;
        case 'closing-fiscal': $input['recognition_inventory']['closing_fiscal_components']['removed_pis_cofins'] = '0.00000000';
            $proof = &$input['inventory_evidence'];
            $proof['value']['inventory'] = $input['recognition_inventory'];
            $proof['source_hash'] = CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]));
            unset($proof);
            $proof = &$input['complete_evidence'];
            $proof['value']['recognition_inventory'] = $input['recognition_inventory'];
            $proof['value']['inventory_evidence_hash'] = $input['inventory_evidence']['source_hash'];
            $proof['source_hash'] = CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]));
            unset($proof);
            break;
    }
    unset($input);
    $result = (new FinancialPublicOfferDebtQualifier)->resolve($ledger['entries'][4], $ledger, new DateTimeImmutable($scenario['knowledge_at']), $rule);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe($reason);
})->with([
    ['missing', 'RTC_TVM_REALIZATION_REPLAYED_FISCAL_HISTORY_MISSING'],
    ['result', 'RTC_TVM_HISTORY_FISCAL_SNAPSHOT_REPLAY_MISMATCH'],
    ['knowledge', 'RTC_TVM_HISTORY_CAPTURED_KNOWLEDGE_OR_REPLAY_INVALID'],
    ['duplicate', 'RTC_TVM_HISTORY_DUPLICATE_FISCAL_SOURCE'],
    ['manifest', 'RTC_TVM_HISTORY_FISCAL_KNOWLEDGE_NOT_PERMITTED'],
    ['sale-reference', 'RTC_TVM_REALIZATION_SALE_FISCAL_HISTORY_REFERENCE_CONFLICT'],
    ['pis', 'RTC_TVM_REALIZATION_PRIOR_ASSESSED_AND_EXCLUDED_RETURN_CONFLICT'],
    ['closing-fiscal', 'RTC_TVM_REALIZATION_CLOSING_FISCAL_COMPONENT_CONFLICT'],
]);
