<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioRunService;

it('qualifies relevant dates in both runners while preserving the prior-debit and future-rate gates', function (string $name): void {
    $root = dirname(__DIR__, 3);
    $manifest = json_decode((string) file_get_contents($root.'/storage/app/phase4b-temporal86-integration-preparation-20261015/temporal86-case-manifest-v2.json'), true, 512, JSON_THROW_ON_ERROR);
    $case = current(array_filter($manifest['cases'], static fn (array $row): bool => $row['case'] === $name));
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), $manifest['bundle']);
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->load($root.'/'.$case['path']);
    $rtc = (new RtcScenarioRunService($factory))->evaluate($knowledge, $loaded)->toArray();
    $full = (new ScenarioRunService($factory, resource_path('fiscal'), bundleManifestPath: $manifest['bundle']))
        ->run($root.'/'.$case['path'])->toArray();
    expect($rtc['status'])->toBe($case['status'])
        ->and($full['status'])->toBe($case['status'] === 'VALID' ? 'VALID_WITH_WARNINGS' : $case['status']);
    if ($case['status'] === 'VALID') {
        $events = array_column($full['trace'], 'payload', 'type');
        expect($events['DECISION_COMPLETED']['warnings'])->toBe(['RULE_BUNDLE_NOT_PRODUCTION_APPROVED', 'MUNICIPAL_PARAMETERS_ARE_FIXTURES'])
            ->and($events['ISS_CALCULATION']['tax_amount'])->toBe('20.00');
    }
    if ($case['reason'] !== null) {
        expect($rtc['reason_codes'])->toContain($case['reason'])->and($full['reason_codes'])->toContain($case['reason']);
    }
    $trace = array_column($rtc['trace'], null, 'stage');
    if ($name === 'opt-in-absent-unchanged') {
        expect($trace)->not->toHaveKey('TAX_POINT_EVENTS');
    } else {
        expect($trace['TAX_POINT_EVENTS']['production_ready'])->toBeFalse()
            ->and($trace['TAX_POINT_EVENTS']['government_evidence_authenticated'])->toBeFalse();
    }
    if ($name === 'advance-candidate-not-booked') {
        $candidate = $trace['TEMPORAL_ADVANCE_RECONCILIATION'];
        expect($candidate['taxes']['CBS']['computed_advances'])->toBe('2.70')
            ->and($candidate['taxes']['CBS']['definitive_amount'])->toBe('9.00')
            ->and($candidate['taxes']['CBS']['arithmetic_balance'])->toBe('6.30')
            ->and($candidate['posting_authorized'])->toBeFalse()
            ->and($candidate['prior_advance_debits_proven'])->toBeFalse();
    }
})->with(['expected-date', 'issue-date-with-explicit-absence', 'contrary-material-proof', 'declared-date-conflict',
    'future-presumption', 'unknown-is-not-absent', 'wrong-operation-document', 'wrong-supply-kind',
    'advance-candidate-not-booked', 'payment-amount-conflict', 'advance-no-future-cbs-default',
    'continuous-right-constituted', 'continuous-missing-selected-parcel', 'opt-in-absent-unchanged']);
