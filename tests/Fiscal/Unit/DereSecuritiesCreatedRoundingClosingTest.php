<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;

function createdRoundingClosingFixture(string $case): array
{
    $root = dirname(__DIR__, 3);
    $fixture = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-created-rounding-closing-2026.10.08-28-draft34/'.$case.'-opening.json'), true, 512, JSON_THROW_ON_ERROR);
    $catalog = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-2026.10.08-28-draft34.json'), true, 512, JSON_THROW_ON_ERROR);
    $manifest = json_decode(file_get_contents($root.'/resources/fiscal/bundles/consumption-phase4b-2026.10.08-28-draft34.json'), true, 512, JSON_THROW_ON_ERROR);

    return [$fixture, $catalog['financial_operation']['base']['securities_realization']['analysis']['history_policy'], new DateTimeImmutable($manifest['knowledge_available_at'])];
}

it('replays a sale closing while keeping created rounding outside prior fiscal coverage', function (string $case): void {
    [$fixture, $rule, $known] = createdRoundingClosingFixture($case);
    $result = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($fixture['scope'], $fixture['history'], $known, $rule);
    expect($result['status'])->toBe('RESOLVED', $result['reason'])
        ->and($result['opening_fiscal_components'])->toBe($fixture['expected']['opening_fiscal_components'])
        ->and($result['opening_carrying_components'])->toBe($fixture['expected']['opening_carrying_components'])
        ->and($result['public_created_rounding_closing_replayed'])->toBeTrue()
        ->and($result['fiscal_snapshots_replayed'])->toBe(1)
        ->and($result['production_ready'])->toBeFalse()->and($result['posting_performed'])->toBeFalse();
    $sum = array_reduce($result['opening_fiscal_components'], static fn (BigDecimal $a, string $b): BigDecimal => $a->plus($b), BigDecimal::of('0'));
    $excess = BigDecimal::of($fixture['scope']['opening']['book_balance'])->minus($fixture['scope']['opening']['acquisition_cost']);
    expect($sum->plus($result['opening_carrying_components']['uncovered_created_rounding'])->isEqualTo($excess))->toBeTrue()
        ->and(hash_file('sha256', dirname(__DIR__, 3).'/'.$fixture['source_path']))->toBe($fixture['source_sha256']);
    if (getenv('TVM_CREATED_ROUNDING_CLOSING_CAPTURE') === '1') {
        $path = dirname(__DIR__, 3).'/storage/app/phase4b-realization-created-rounding-closing-draft-20261008-28/'.$case.'-opening-result.json';
        if (is_file($path)) {
            expect(CanonicalJson::encode(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($result));
        } else {
            $stream = fopen($path, 'x');
            expect($stream)->not->toBeFalse();
            fwrite($stream, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
            fclose($stream);
        }
    }
})->with(['negative-partial', 'negative-complete', 'positive-partial', 'positive-complete', 'zero-partial', 'zero-complete', 'exact-partial', 'exact-complete', 'positive-aligned-partial', 'negative-aligned-partial']);

it('refuses absent or inconsistent allocations and does not infer fiscal coverage for created rounding', function (string $mutation, string $reason): void {
    [$fixture, $rule, $known] = createdRoundingClosingFixture('positive-partial');
    $source = &$fixture['history']['fiscal_sources'][0];
    if ($mutation === 'policy') {
        unset($rule['public_created_rounding_closing_policy']);
    } elseif ($mutation === 'conflicting-policy') {
        $rule['public_explicit_net_closing_policy'] = 'REPLAYED_PUBLIC_FIRST_ACQUISITION_NET_CLOSING_WITH_CARRYING_ROUNDING';
    } elseif ($mutation === 'missing') {
        unset($source['created_rounding_closing_evidence']);
    } elseif ($mutation === 'allocation') {
        unset($source['current_fiscal_allocation']);
    } elseif ($mutation === 'excess') {
        $source['current_fiscal_allocation']['retained']['assessed_produced_return'] = '40.00000000';
    } elseif ($mutation === 'retained-twice') {
        $source['retained_share'] = '0.5';
    } elseif ($mutation === 'opening') {
        $fixture['scope']['opening']['book_balance'] = '2525.02';
    } else {
        $proof = &$source['created_rounding_closing_evidence'];
        $proof['value']['opening_carrying_components']['fiscal_covered_rounding'] = '0.00750000';
        $proof['source_hash'] = CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]));
        unset($proof);
    }
    unset($source);
    $result = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($fixture['scope'], $fixture['history'], $known, $rule);
    expect($result['reason'])->toBe('RTC_TVM_HISTORY_'.$reason)->and($result['production_ready'])->toBeFalse();
})->with([
    ['policy', 'PUBLIC_CREATED_ROUNDING_CLOSING_POLICY_UNPROVEN'],
    ['conflicting-policy', 'PUBLIC_CREATED_ROUNDING_CLOSING_POLICY_CONFLICT'],
    ['missing', 'PUBLIC_CREATED_ROUNDING_CLOSING_UNPROVEN'], ['allocation', 'PUBLIC_CREATED_ROUNDING_CLOSING_UNPROVEN'],
    ['excess', 'PUBLIC_CREATED_ROUNDING_CLOSING_UNPROVEN'], ['rounding', 'PUBLIC_CREATED_ROUNDING_CLOSING_UNPROVEN'],
    ['retained-twice', 'PUBLIC_REALIZED_CLOSING_RETAINED_TWICE'], ['opening', 'CURRENT_OPENING_POSITION_CONFLICT'],
]);
