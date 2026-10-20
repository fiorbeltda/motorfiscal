<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Production\Phase4bReadinessAssessor;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

it('blocks production while integrated fixtures and legal reviews remain unresolved', function (): void {
    $resourceRoot = dirname(__DIR__, 3).'/resources/fiscal';
    $report = (new Phase4bReadinessAssessor(new FiscalEngineFactory))->assess(
        $resourceRoot,
        'bundles/consumption-phase4b-2026.09.25-1.json',
        $resourceRoot.'/production/phase4b-controls-2026.09.28-1.json',
    );

    expect($report['ready'])->toBeFalse()
        ->and($report['official_sources_checked'])->toBe(11)
        ->and($report['official_source_hashes_valid'])->toBe(11)
        ->and($report['blockers'])->toContain('INTEGRATED_MUNICIPAL_PARAMETERS_ARE_FIXTURES')
        ->toContain('LEGAL_APPROVAL_SIGNATURE_MISSING_OR_INVALID')
        ->toContain('CLASSIFICATION_RULE_NOT_APPROVED:RTC.CLASSIFICATION.SAAS.RESIDUAL.SANDBOX-1')
        ->toContain('DOCUMENT_LAYOUT_DEPLOYMENT_NOT_PROVEN:NT009-RTC-1.04.00');
});

it('binds the new candidate bundle to its production controls without approving it', function (): void {
    $resourceRoot = dirname(__DIR__, 3).'/resources/fiscal';
    $report = (new Phase4bReadinessAssessor(new FiscalEngineFactory))->assess(
        $resourceRoot,
        'bundles/consumption-phase4b-2026.09.28-1.json',
        $resourceRoot.'/production/phase4b-controls-2026.09.28-1.json',
    );

    expect($report['ready'])->toBeFalse()
        ->and($report['eligible_for_local_validation'])->toBeTrue()
        ->and($report['development_blockers'])->toBe([])
        ->and($report['known_parameters']['rates'])->toHaveCount(5)
        ->and($report['known_parameters']['transition'])->toHaveCount(7)
        ->and($report['bundle_id'])->toBe('CONSUMPTION-PHASE4B-2026.09.28-1')
        ->and($report['blockers'])->not->toContain('PRODUCTION_CONTROLS_NOT_BOUND_TO_BUNDLE')
        ->toContain('INTEGRATED_MUNICIPAL_PARAMETERS_ARE_FIXTURES');
    expect(array_column($report['external_dependencies'], 'code'))
        ->toContain('CBS_RATE_2027_UNAVAILABLE')
        ->toContain('IBS_JURISDICTIONAL_RATES_2029_UNAVAILABLE')
        ->toContain('ISS_BASE_RATE_2028_FUTURE')
        ->toContain('DOCUMENT_LAYOUT_DEPLOYMENT_UNPROVEN:NT009-RTC-1.04.00');
});

it('accepts the researched candidate for local validation while preserving the production gate', function (): void {
    $resourceRoot = dirname(__DIR__, 3).'/resources/fiscal';
    $report = (new Phase4bReadinessAssessor(new FiscalEngineFactory))->assess(
        $resourceRoot,
        'bundles/consumption-phase4b-2026.09.28-2.json',
        $resourceRoot.'/production/phase4b-controls-2026.09.28-1.json',
    );

    expect($report['bundle_id'])->toBe('CONSUMPTION-PHASE4B-2026.09.28-2')
        ->and($report['official_sources_checked'])->toBe(12)
        ->and($report['official_source_hashes_valid'])->toBe(12)
        ->and($report['eligible_for_local_validation'])->toBeTrue()
        ->and($report['ready'])->toBeFalse();
});

it('tracks collection qualification and missing split deployment in the current candidate', function (): void {
    $resourceRoot = dirname(__DIR__, 3).'/resources/fiscal';
    $report = (new Phase4bReadinessAssessor(new FiscalEngineFactory))->assess(
        $resourceRoot,
        'bundles/consumption-phase4b-2026.09.28-3.json',
        $resourceRoot.'/production/phase4b-controls-2026.09.28-2.json',
    );

    expect($report['bundle_id'])->toBe('CONSUMPTION-PHASE4B-2026.09.28-3')
        ->and($report['eligible_for_local_validation'])->toBeTrue()
        ->and($report['ready'])->toBeFalse()
        ->and($report['official_source_hashes_valid'])->toBe(12)
        ->and($report['development_blockers'])->toBe([]);
    expect(array_column($report['external_dependencies'], 'code'))
        ->toContain('SPLIT_OPERATIONAL_DEPLOYMENT_UNPROVEN');
});

it('keeps the platform candidate eligible for local validation while integrated approval remains pending', function (): void {
    $resourceRoot = dirname(__DIR__, 3).'/resources/fiscal';
    $report = (new Phase4bReadinessAssessor(new FiscalEngineFactory))->assess(
        $resourceRoot,
        'bundles/consumption-phase4b-2026.09.28-4.json',
        $resourceRoot.'/production/phase4b-controls-2026.09.28-3.json',
    );

    expect($report['bundle_id'])->toBe('CONSUMPTION-PHASE4B-2026.09.28-4')
        ->and($report['eligible_for_local_validation'])->toBeTrue()
        ->and($report['ready'])->toBeFalse()
        ->and($report['official_source_hashes_valid'])->toBe(12)
        ->and($report['development_blockers'])->toBe([]);
});

it('binds the partial export candidate to reviewed official source hashes without marking production ready', function (): void {
    $resourceRoot = dirname(__DIR__, 3).'/resources/fiscal';
    $report = (new Phase4bReadinessAssessor(new FiscalEngineFactory))->assess(
        $resourceRoot,
        'bundles/consumption-phase4b-2026.09.28-6.json',
        $resourceRoot.'/production/phase4b-controls-2026.09.28-5.json',
    );

    expect($report['bundle_id'])->toBe('CONSUMPTION-PHASE4B-2026.09.28-6')
        ->and($report['eligible_for_local_validation'])->toBeTrue()
        ->and($report['ready'])->toBeFalse()
        ->and($report['official_sources_checked'])->toBe(13)
        ->and($report['official_source_hashes_valid'])->toBe(13)
        ->and($report['development_blockers'])->toBe([]);
});

it('checks the post-ISS candidate without treating synthetic future values as official rates', function (): void {
    $resourceRoot = dirname(__DIR__, 3).'/resources/fiscal';
    $report = (new Phase4bReadinessAssessor(new FiscalEngineFactory))->assess(
        $resourceRoot,
        'bundles/consumption-phase4b-2026.09.28-7.json',
        $resourceRoot.'/production/phase4b-controls-2026.09.28-6.json',
    );

    expect($report['bundle_id'])->toBe('CONSUMPTION-PHASE4B-2026.09.28-7')
        ->and($report['eligible_for_local_validation'])->toBeTrue()
        ->and($report['ready'])->toBeFalse()
        ->and($report['official_sources_checked'])->toBe(13)
        ->and($report['official_source_hashes_valid'])->toBe(13);
    expect(array_column($report['external_dependencies'], 'code'))
        ->toContain('IBS_JURISDICTIONAL_RATES_2029_UNAVAILABLE');
});

it('keeps the employee below-market candidate eligible only for local validation', function (): void {
    $resourceRoot = dirname(__DIR__, 3).'/resources/fiscal';
    $report = (new Phase4bReadinessAssessor(new FiscalEngineFactory))->assess(
        $resourceRoot,
        'bundles/consumption-phase4b-2026.09.28-8.json',
        $resourceRoot.'/production/phase4b-controls-2026.09.28-7.json',
    );

    expect($report['bundle_id'])->toBe('CONSUMPTION-PHASE4B-2026.09.28-8')
        ->and($report['eligible_for_local_validation'])->toBeTrue()
        ->and($report['ready'])->toBeFalse()
        ->and($report['official_source_hashes_valid'])->toBe(13);
});

it('keeps the post-ISS federal candidate eligible only for local validation', function (): void {
    $resourceRoot = dirname(__DIR__, 3).'/resources/fiscal';
    $report = (new Phase4bReadinessAssessor(new FiscalEngineFactory))->assess(
        $resourceRoot,
        'bundles/consumption-phase4b-2026.09.28-9.json',
        $resourceRoot.'/production/phase4b-controls-2026.09.28-8.json',
    );

    expect($report['bundle_id'])->toBe('CONSUMPTION-PHASE4B-2026.09.28-9')
        ->and($report['eligible_for_local_validation'])->toBeTrue()
        ->and($report['ready'])->toBeFalse()
        ->and($report['official_source_hashes_valid'])->toBe(13);
});

it('keeps the NT004 XSD candidate eligible only for local validation', function (): void {
    $resourceRoot = dirname(__DIR__, 3).'/resources/fiscal';
    $report = (new Phase4bReadinessAssessor(new FiscalEngineFactory))->assess(
        $resourceRoot,
        'bundles/consumption-phase4b-2026.09.28-10.json',
        $resourceRoot.'/production/phase4b-controls-2026.09.28-9.json',
    );

    expect($report['bundle_id'])->toBe('CONSUMPTION-PHASE4B-2026.09.28-10')
        ->and($report['eligible_for_local_validation'])->toBeTrue()
        ->and($report['ready'])->toBeFalse()
        ->and($report['official_source_hashes_valid'])->toBe(13);
});

it('keeps the personal-use recipient candidate eligible only for local validation', function (): void {
    $resourceRoot = dirname(__DIR__, 3).'/resources/fiscal';
    $report = (new Phase4bReadinessAssessor(new FiscalEngineFactory))->assess(
        $resourceRoot,
        'bundles/consumption-phase4b-2026.09.28-11.json',
        $resourceRoot.'/production/phase4b-controls-2026.09.28-10.json',
    );

    expect($report['bundle_id'])->toBe('CONSUMPTION-PHASE4B-2026.09.28-11')
        ->and($report['eligible_for_local_validation'])->toBeTrue()
        ->and($report['ready'])->toBeFalse()
        ->and($report['official_source_hashes_valid'])->toBe(13);
});

it('verifies a reviewer signature against the exact bundle and control hashes', function (): void {
    $resourceRoot = dirname(__DIR__, 3).'/resources/fiscal';
    $assessor = new Phase4bReadinessAssessor(new FiscalEngineFactory);
    $controlsPath = $resourceRoot.'/production/phase4b-controls-2026.09.28-1.json';
    $initial = $assessor->assess($resourceRoot, 'bundles/consumption-phase4b-2026.09.25-1.json', $controlsPath);
    $payload = [
        'decision' => 'APPROVED',
        'scope' => 'INTEGRATED_ISS_4A_4B',
        'bundle_manifest_hash' => $initial['bundle_manifest_hash'],
        'controls_hash' => $initial['controls_hash'],
        'reviewer' => 'Test reviewer',
        'approved_at' => '2026-09-28T10:00:00-03:00',
    ];
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_sign(CanonicalJson::encode($payload), $signature, $key, OPENSSL_ALGO_SHA256);
    $approvalPath = tempnam(sys_get_temp_dir(), 'phase4b-approval-');
    $keyPath = tempnam(sys_get_temp_dir(), 'phase4b-reviewer-');
    file_put_contents($approvalPath, json_encode(['payload' => $payload, 'signature_base64' => base64_encode($signature)], JSON_THROW_ON_ERROR));
    file_put_contents($keyPath, openssl_pkey_get_details($key)['key']);

    try {
        $verified = $assessor->assess($resourceRoot, 'bundles/consumption-phase4b-2026.09.25-1.json', $controlsPath, $approvalPath, $keyPath);
        expect($verified['blockers'])->not->toContain('LEGAL_APPROVAL_SIGNATURE_MISSING_OR_INVALID')
            ->toContain('INTEGRATED_MUNICIPAL_PARAMETERS_ARE_FIXTURES');

        $payload['approved_at'] = '2026-09-29T10:00:00-03:00';
        file_put_contents($approvalPath, json_encode(['payload' => $payload, 'signature_base64' => base64_encode($signature)], JSON_THROW_ON_ERROR));
        $tampered = $assessor->assess($resourceRoot, 'bundles/consumption-phase4b-2026.09.25-1.json', $controlsPath, $approvalPath, $keyPath);
        expect($tampered['blockers'])->toContain('LEGAL_APPROVAL_SIGNATURE_MISSING_OR_INVALID');
    } finally {
        unlink($approvalPath);
        unlink($keyPath);
    }
});

it('does not accept an incomplete control matrix', function (): void {
    $resourceRoot = dirname(__DIR__, 3).'/resources/fiscal';
    $controls = json_decode((string) file_get_contents($resourceRoot.'/production/phase4b-controls-2026.09.28-1.json'), true, 512, JSON_THROW_ON_ERROR);
    array_shift($controls['controls']);
    $path = tempnam(sys_get_temp_dir(), 'phase4b-controls-');
    file_put_contents($path, json_encode($controls, JSON_THROW_ON_ERROR));

    try {
        $report = (new Phase4bReadinessAssessor(new FiscalEngineFactory))->assess(
            $resourceRoot,
            'bundles/consumption-phase4b-2026.09.25-1.json',
            $path,
        );
        expect($report['ready'])->toBeFalse()
            ->and($report['blockers'])->toContain('REQUIRED_CONTROL_MISSING:4B.1.TEMPORALITY');
    } finally {
        unlink($path);
    }
});
