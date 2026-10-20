<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;
use Brick\Math\BigDecimal;

require_once __DIR__.'/../Support/TvmCurrentFixtures.php';
require_once __DIR__.'/../Support/TvmBookFixtures.php';

require_once __DIR__.'/../Support/TvmFairFixtures.php';

it('projects fair-value realizations and excludes produced returns already assessed or included in the current curve', function (string $case, string $base): void {
    [$operation, $month, $known, $rules, $ledger, $manifest] = tvmFairFixture($case);
    $projection = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($operation['base']['net_base'])->toBe($base)->and($projection['status'])->toBe('PROJECTED')->and($projection['production_ready'])->toBeFalse();
    if (getenv('TVM_FAIR_CAPTURE') === '1') {
        $root = dirname(__DIR__, 3);
        $scenario = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json'), true, 512, JSON_THROW_ON_ERROR);
        $scenario['scenario_id'] = 'SYNTHETIC-fair-value-'.$case;
        $scenario['knowledge_at'] = $known->format(DATE_ATOM);
        $scenario['rule_bundle_id'] = $manifest['bundle_id'];
        $scenario['catalog_versions'] = $manifest['catalog_versions'];
        $scenario['facts']['rtc.financial_ledger'] = [$ledger];
        foreach (['financial' => $scenario, 'month' => $month] as $suffix => $data) {
            $path = $root.'/resources/fiscal/scenarios/tvm-fair-value-2026.10.06-28-draft4/'.$case.'-'.$suffix.'.json';
            if (is_file($path)) {
                expect(CanonicalJson::encode(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($data));
            } else {
                $stream = fopen($path, 'x');
                expect($stream)->not->toBeFalse();
                fwrite($stream, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
                fclose($stream);
            }
        }
    }
})->with([['mixed-history-fair-value', '1010.00'], ['first-new-fair-gain', '780.00'], ['first-new-fair-loss', '600.00'], ['first-current-curve', '900.00'], ['first-opposing-new-components', '700.00']]);

it('preserves gross reclassification movements and avoids taxing the counter-entry twice', function (): void {
    [$operation, $month, $known, $rules] = tvmFairFixture('mixed-history-fair-value');
    $accounts = array_column($month['book']['accounts'], null, 'cCta');
    expect($accounts['FV000']['vMovDebt'])->toBe('80.00')->and($accounts['FV000']['vAjusteDebt'])->toBe('80.00')->and($accounts['FV000']['vApur'])->toBe('80.00')
        ->and($accounts['fvnew000']['vMovCred'])->toBe('130.00')->and($accounts['fvnew000']['vAjusteCred'])->toBe('80.00')->and($accounts['fvnew000']['vApur'])->toBe('50.00');
    $projection = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($projection['status'])->toBe('PROJECTED');
    $binding = $operation['base']['realization_bindings']['tvm1'];
    $deferred = array_values(array_filter($binding['components'], static fn (array $c): bool => $c['component_kind'] === 'DEFERRED_FAIR_VALUE_REALIZED'))[0];
    expect($deferred['expected_codTrib'])->toBe('311011702')->and(BigDecimal::of($binding['analysis']['realizations'][0]['prior_return_not_reincluded'])->isEqualTo('40'))->toBeTrue();
});

it('requires the explicit fair-value policy and complete independent subledger', function (string $mutation, string $reason): void {
    [$operation, $month, $known, $rules] = tvmFairFixture('mixed-history-fair-value');
    match ($mutation) {
        'policy' => $rules['closing']['securities_realization_binding']['fair_value_journal_mode'] = 'UNAVAILABLE',
        'bridge' => $month['securities_realization_book'][0]['fair_value_bridge'] = [],
        'closing' => $month['securities_realization_book'][0]['fair_value_bridge']['subaccounts'][0]['closing_deferred_value'] = '121',
        'release' => $month['securities_realization_book'][0]['fair_value_bridge']['subaccounts'][0]['releases'][0]['prior_deferred_value'] = '81',
        'counter-adjustment' => $month['book']['accounts'][array_search('fvnew000', array_column($month['book']['accounts'], 'cCta'), true)]['vAjusteCred'] = '0.00',
        'origin-adjustment' => $month['book']['accounts'][array_search('FV000', array_column($month['book']['accounts'], 'cCta'), true)]['vAjusteDebt'] = '0.00',
        'journal' => array_splice($month['securities_realization_book'][0]['journals'], 1, 1),
        'proof' => $month['securities_realization_book'][0]['independent_evidence']['qualified'] = false,
        'homologation' => $month['securities_realization_book'][0]['fair_value_bridge']['normative_homologation_claimed'] = true,
    };
    expect((new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules)['reason'])->toBe('RTC_DERE_TVM_BOOK_'.$reason);
})->with([
    ['policy', 'FAIR_VALUE_SUBACCOUNT_REALIZATION_BRIDGE_PENDING'], ['bridge', 'FAIR_VALUE_SUBLEDGER_AND_METHOD_UNPROVEN'],
    ['closing', 'FAIR_VALUE_SUBACCOUNT_CLOSING_CONFLICT'], ['release', 'FAIR_VALUE_RELEASE_COMPONENT_CONFLICT'],
    ['counter-adjustment', 'FAIR_VALUE_RECLASSIFICATION_TAX_ADJUSTMENT_CONFLICT'], ['origin-adjustment', 'FAIR_VALUE_RECLASSIFICATION_TAX_ADJUSTMENT_CONFLICT'],
    ['journal', 'BOOK_JOURNAL_COMPONENT_OR_BALANCE_CONFLICT'], ['proof', 'INDEPENDENT_BOOK_BRIDGE_UNPROVEN'], ['homologation', 'FAIR_VALUE_SUBLEDGER_AND_METHOD_UNPROVEN'],
]);
