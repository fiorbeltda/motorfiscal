<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Production\Phase4bReadinessAssessor;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\NFSe\DpsForeignAcquirerCandidate;
use App\Modules\Fiscal\NFSe\DpsRestrictedSchemaSelection;

it('keeps a local-only schema blocked even when the exact reviewer signature is verified', function (string $contract): void {
    $resourceRoot = dirname(__DIR__, 3).'/resources/fiscal';
    $bundle = json_decode((string) file_get_contents($resourceRoot.'/bundles/consumption-phase4b-2026.09.25-1.json'), true, 512, JSON_THROW_ON_ERROR);
    $codes = json_decode((string) file_get_contents($resourceRoot.'/'.$bundle['resource_paths']['rtc_code_catalog']), true, 512, JSON_THROW_ON_ERROR);
    $codes['version'] = 'SYNTHETIC-LOCAL-SCHEMA-74';
    $codes['local_xml_projection'] = ['contract_version' => $contract];
    $files = [];
    $newFile = static function (string $directory, string $prefix) use (&$files): string {
        $path = tempnam($directory, $prefix);
        if ($path === false) {
            throw new RuntimeException('Unable to create isolated readiness fixture.');
        }
        $files[] = $path;

        return $path;
    };
    try {
        $codePath = $newFile($resourceRoot.'/catalogs', 'test-local74-');
        $bundlePath = $newFile($resourceRoot.'/bundles', 'test-local74-');
        $approvalPath = $newFile(sys_get_temp_dir(), 'test-local74-approval-');
        $keyPath = $newFile(sys_get_temp_dir(), 'test-local74-key-');
        file_put_contents($codePath, json_encode($codes, JSON_THROW_ON_ERROR));
        $bundle['bundle_id'] = 'SYNTHETIC-LOCAL-SCHEMA-READINESS-74';
        $bundle['resource_paths']['rtc_code_catalog'] = 'catalogs/'.basename($codePath);
        $bundle['catalog_versions']['rtc_codes'] = $codes['version'];
        $bundle['content_hashes']['rtc_code_catalog'] = CanonicalJson::hash($codes);
        $controls = $resourceRoot.'/production/phase4b-controls-2026.09.28-1.json';
        $controlDocument = json_decode((string) file_get_contents($controls), true, 512, JSON_THROW_ON_ERROR);
        $bundle['production_controls'] = ['path' => 'production/phase4b-controls-2026.09.28-1.json',
            'sha256' => CanonicalJson::hash($controlDocument)];
        file_put_contents($bundlePath, json_encode($bundle, JSON_THROW_ON_ERROR));
        $assessor = new Phase4bReadinessAssessor(new FiscalEngineFactory);
        $initial = $assessor->assess($resourceRoot, 'bundles/'.basename($bundlePath), $controls);
        expect($initial['ready'])->toBeFalse()
            ->and($initial['eligible_for_local_validation'])->toBeTrue()
            ->and($initial['blockers'])->toContain('RTC_DPS_RESTRICTED_SCHEMA_LOCAL_ONLY');
        $payload = ['decision' => 'APPROVED', 'scope' => 'INTEGRATED_ISS_4A_4B',
            'bundle_manifest_hash' => $initial['bundle_manifest_hash'], 'controls_hash' => $initial['controls_hash'],
            'reviewer' => 'Synthetic test reviewer', 'approved_at' => '2026-10-14T12:30:00Z'];
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($key === false || ! openssl_sign(CanonicalJson::encode($payload), $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to create synthetic reviewer signature.');
        }
        $details = openssl_pkey_get_details($key);
        if ($details === false) {
            throw new RuntimeException('Unable to read synthetic reviewer public key.');
        }
        file_put_contents($keyPath, $details['key']);
        file_put_contents($approvalPath, json_encode(['payload' => $payload, 'signature_base64' => base64_encode($signature)], JSON_THROW_ON_ERROR));
        $verified = $assessor->assess($resourceRoot, 'bundles/'.basename($bundlePath), $controls, $approvalPath, $keyPath);
        expect($verified['ready'])->toBeFalse()
            ->and($verified['blockers'])->not->toContain('LEGAL_APPROVAL_SIGNATURE_MISSING_OR_INVALID')
            ->toContain('RTC_DPS_RESTRICTED_SCHEMA_LOCAL_ONLY');
        expect(array_column($verified['external_dependencies'], 'code'))
            ->toContain('RTC_DPS_RESTRICTED_SCHEMA_LOCAL_ONLY');
    } finally {
        foreach ($files as $path) {
            unlink($path);
        }
    }
})->with([DpsRestrictedSchemaSelection::CONTRACT, DpsForeignAcquirerCandidate::CONTRACT]);
