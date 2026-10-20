<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DereSecuritiesRealizationBindingQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;
use App\Modules\Fiscal\NFSe\DereTrialBalanceXmlProjector;

require_once __DIR__.'/../Support/TvmBookFixtures.php';

it('projects the complete local monthly candidate for private first-month realizations', function (string $case): void {
    [$operation, $month, $known, $rules] = tvmMonthlyBookFixture($case);
    $result = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($result['status'])->toBe('PROJECTED')->and($result['securities_realization_binding']['status'])->toBe('RESOLVED')
        ->and($result['production_ready'])->toBeFalse()->and($result['debt_yield_projection'] ?? null)->toBeNull();
    if (getenv('TVM_BOOK_CAPTURE') === '1') {
        $path = dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-book-2026.10.06-28-draft3/'.$case.'-month.json';
        if (is_file($path)) {
            expect(CanonicalJson::encode(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($month));
        } else {
            $stream = fopen($path, 'x');
            expect($stream)->not->toBeFalse();
            fwrite($stream, json_encode($month, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
            fclose($stream);
        }
    }
})->with(['first-gain', 'first-loss', 'first-offsetting']);

it('binds separate gains and losses to fiscal codes and balanced gross journals', function (string $case): void {
    [$operation, $month, $pgcc, $known, $rule] = tvmBookFixture($case);
    $result = (new DereSecuritiesRealizationBindingQualifier)->resolve($operation, $month, $pgcc, $known, $rule);
    expect($result['status'])->toBe('RESOLVED')->and($result['parents']['tvm1']['journal_count'])->toBe($case === 'first-offsetting' ? 3 : 2)
        ->and($result['posting_performed'])->toBeFalse()->and($result['production_ready'])->toBeFalse();
})->with(['first-gain', 'first-loss', 'first-offsetting']);

it('rejects resealed journal or mapping contradictions', function (string $mutation, string $reason): void {
    [$operation, $month, $pgcc, $known, $rule] = tvmBookFixture();
    match ($mutation) {
        'code' => $pgcc['accounts'][2]['codTrib'] = '311011104',
        'nature' => $pgcc['accounts'][2]['codNat'] = '5',
        'journal' => $month['securities_realization_book'][0]['journals'][1]['lines'][0]['amount'] = '10201.00',
        'gross' => $month['book']['accounts'][0]['vMovCred'] = '9999.00',
        'principal' => $month['book']['accounts'][0]['vApur'] = '10000.00',
        'closing' => $month['book']['accounts'][0]['vSaldoFinal'] = '1.00',
        'memory' => $month['securities_realization_book'][0]['daily_memory']['monthly']['vSaldoContFin'] = '1.00',
        'coverage' => $month['securities_realization_book'] = [],
        'derived' => $operation['base']['entries'][4]['computed_amount'] = '201.00',
    };
    if ($mutation !== 'coverage') {
        $month = tvmBookSeal($operation, $month, $pgcc);
    }
    $result = (new DereSecuritiesRealizationBindingQualifier)->resolve($operation, $month, $pgcc, $known, $rule);
    expect($result['reason'])->toBe('RTC_DERE_TVM_BOOK_'.$reason);
})->with([
    ['code', 'COMPONENT_PGCC_CODE_OR_NATURE_CONFLICT'], ['nature', 'COMPONENT_PGCC_CODE_OR_NATURE_CONFLICT'],
    ['journal', 'BOOK_JOURNAL_COMPONENT_OR_BALANCE_CONFLICT'], ['gross', 'JOURNAL_GROSS_MOVEMENTS_OR_COMPONENT_LINKS_CONFLICT'],
    ['principal', 'ASSET_POSITION_OR_PRINCIPAL_EXCLUSION_CONFLICT'], ['closing', 'ASSET_POSITION_OR_PRINCIPAL_EXCLUSION_CONFLICT'],
    ['memory', 'REPLAYED_POSITION_MEMORY_CONFLICT'], ['coverage', 'PARENT_BOOK_COVERAGE_INCOMPLETE'], ['derived', 'COMPONENT_PGCC_CODE_OR_NATURE_CONFLICT'],
]);

it('requires independent book evidence beyond self-consistent journal figures', function (): void {
    [$operation, $month, $pgcc, $known, $rule] = tvmBookFixture();
    unset($month['securities_realization_book'][0]['independent_evidence']);
    expect((new DereSecuritiesRealizationBindingQualifier)->resolve($operation, $month, $pgcc, $known, $rule)['reason'])
        ->toBe('RTC_DERE_TVM_BOOK_INDEPENDENT_BOOK_BRIDGE_UNPROVEN');
});

it('requires the full book binding when projecting the new components through standalone D1101', function (): void {
    [$operation, $month, $known, $rules] = tvmMonthlyBookFixture('first-gain');
    expect((new DereTrialBalanceXmlProjector)->project($operation, $month['book'], $month['identity'], $known, $rules['balance'])['reason'])
        ->toBe('RTC_DERE_BALANCE_TVM_BOOK_BINDING_REQUIRED');
});

it('does not bypass the new book requirement by omitting the parent bindings', function (): void {
    [$operation, $month, $known, $rules] = tvmMonthlyBookFixture('first-gain');
    unset($operation['base']['realization_bindings']);
    expect((new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules)['reason'])
        ->toBe('RTC_DERE_TVM_BOOK_PARENT_BOOK_COVERAGE_INCOMPLETE');
});

it('rejects a qualified book binding reused against a changed book', function (): void {
    [$operation, $month, $known, $rules] = tvmMonthlyBookFixture('first-gain');
    $monthly = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known, $rules);
    expect($monthly['status'])->toBe('PROJECTED');
    $month['book']['accounts'][0]['vMovCred'] = '1001.00';
    expect((new DereTrialBalanceXmlProjector)->project($operation, $month['book'], $month['identity'], $known, $rules['balance'], $monthly['securities_realization_binding'])['reason'])
        ->toBe('RTC_DERE_BALANCE_TVM_BOOK_BINDING_REQUIRED');
});
