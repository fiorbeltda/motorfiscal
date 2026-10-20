<?php

declare(strict_types=1);

require_once __DIR__.'/TvmCurrentFixtures.php';
require_once __DIR__.'/TvmBookFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\DerePgccQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

function privateConsolidation48Fixture(array $metadata, bool $rebindPublicPolicy = false): array
{
    $root = dirname(__DIR__, 3);
    $scenario = json_decode(file_get_contents($root.'/'.$metadata['financial_input']), true, 512, JSON_THROW_ON_ERROR);
    $month = json_decode(file_get_contents($root.'/'.$metadata['monthly_input']), true, 512, JSON_THROW_ON_ERROR);
    $catalog = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-2026.10.09-28-draft48.json'), true, 512, JSON_THROW_ON_ERROR);
    $manifest = json_decode(file_get_contents($root.'/resources/fiscal/bundles/consumption-phase4b-2026.10.09-28-draft48.json'), true, 512, JSON_THROW_ON_ERROR);
    $known = new DateTimeImmutable($manifest['knowledge_available_at']);
    $ledger = $scenario['facts']['rtc.financial_ledger'][0];
    if ($rebindPublicPolicy) {
        foreach ($ledger['entries'] as &$entry) {
            if (isset($entry['public_offer_debt']['sale_recognition']['independent_evidence'])) {
                $proof = &$entry['public_offer_debt']['sale_recognition']['independent_evidence'];
                $proof['value']['policy_hash'] = CanonicalJson::hash($catalog['financial_operation']['base']['public_offer_debt']['sale_recognition_policy']);
                $proof['source_hash'] = CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]));
                unset($proof);
            }
        }
        unset($entry);
        $ledger = sealTvmCurrentLedger($ledger);
    }
    [$operation] = tvmMonthlyBookFixture('first-gain');
    $operation['base'] = tvmCurrentBase($ledger, $catalog['financial_operation']['base'], $known);
    expect($operation['base']['status'])->toBe('RESOLVED', $operation['base']['reason']);
    $codes = [];
    foreach ($month['securities_realization_book'] as $row) {
        foreach ($operation['base']['realization_bindings'][$row['parent_id']]['financial_entries'] as $entry) {
            $account = $row['component_accounts'][$entry['id']];
            $code = $entry['realization_binding']['expected_codTrib'];
            expect(! isset($codes[$account]) || $codes[$account] === $code)->toBeTrue();
            $codes[$account] = $code;
        }
    }
    foreach ($month['pgcc']['accounts'] as &$account) {
        if (isset($codes[$account['cCta']])) {
            $account['codTrib'] = $codes[$account['cCta']];
        }
    }
    unset($account);
    $month['pgcc']['source_hash'] = CanonicalJson::hash(array_diff_key($month['pgcc'], ['source_hash' => true]));
    $month['prerequisites']['pgcc_source_hash'] = $month['book']['pgcc_source_hash'] = $month['pgcc']['source_hash'];
    $closing = $catalog['dere_monthly_closing_projection'];
    $pgcc = (new DerePgccQualifier)->resolve($month['pgcc'], $closing['pgcc_catalogs']['reference'], $closing['pgcc_catalogs']['tax_codes'], $known, false, $closing['pgcc_qualification_policy']);
    expect($pgcc['status'])->toBe('RESOLVED', $pgcc['reason']);
    foreach ($month['securities_realization_book'] as &$row) {
        $proof = &$row['independent_evidence'];
        $proof['value']['qualification_hash'] = CanonicalJson::hash($operation['base']['realization_bindings'][$row['parent_id']]);
        $proof['value']['pgcc_source_hash'] = $pgcc['source_hash'];
        $proof['value']['book_content_hash'] = CanonicalJson::hash($month['book']);
        $proof['value']['pgcc_content_hash'] = CanonicalJson::hash($pgcc);
        $proof['source_hash'] = CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]));
        unset($proof);
    }
    unset($row);
    if ($rebindPublicPolicy && isset($month['debt_yield']['evidence'])) {
        $proof = &$month['debt_yield']['evidence'];
        $proof['value']['operation_hash'] = CanonicalJson::hash($operation);
        $proof['value']['book_input_hash'] = CanonicalJson::hash($month['book']);
        $proof['value']['pgcc_source_hash'] = $pgcc['source_hash'];
        $proof['source_hash'] = CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]));
        unset($proof);
    }
    $month['financial_scenario'] = $metadata['case'].'-financial.json';
    $rules = ['closing' => $closing, 'balance' => $catalog['dere_trial_balance_projection'], 'deductions' => $catalog['dere_deduction_projection'],
        'pgcc_projection' => $catalog['dere_pgcc_projection'], 'contributor_projection' => $catalog['dere_contributor_projection']];

    return [$operation, $month, $known, $rules, $ledger, $manifest];
}
