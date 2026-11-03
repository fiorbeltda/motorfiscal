<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioRunService;

// B12: own verification of the five B candidates for integral acceptance
// (4B.6.7, 4B.6.8, 4B.7.3, 4B.8.2, 4B.8.4). Read-only.

function b12Root(): string
{
    return dirname(__DIR__, 3);
}

/** @return array<string, array<string, mixed>> every versioned RTC code catalog plus the B01 correlation edition */
function b12Catalogs(): array
{
    $catalogs = [];
    foreach (glob(b12Root().'/resources/fiscal/catalogs/rtc-codes-*.json') as $file) {
        $catalogs[basename($file)] = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    }
    $b01 = b12Root().'/resources/fiscal/catalogs/rtc-correlation-anexoviii-v1.02.00-b01.json';
    $catalogs[basename($b01)] = json_decode((string) file_get_contents($b01), true, 512, JSON_THROW_ON_ERROR);

    return $catalogs;
}

it('4B.6.8: every official correlation in every catalog version is OFFICIAL_CORRELATION without decision authority', function (): void {
    $rows = 0;
    foreach (b12Catalogs() as $name => $catalog) {
        foreach ($catalog['correlations'] ?? [] as $row) {
            expect($row['kind'] ?? null)->toBe('OFFICIAL_CORRELATION', $name)
                ->and($row['decision_authority'] ?? null)->toBeFalse($name);
            $rows++;
        }
    }
    expect($rows)->toBeGreaterThan(200);
});

it('4B.6.7: engine rules and official correlations stay structurally distinct in every version', function (): void {
    $rules = 0;
    foreach (b12Catalogs() as $name => $catalog) {
        foreach ($catalog['engine_decision_rules'] ?? [] as $id => $rule) {
            expect($rule['kind'] ?? null)->toBe('ENGINE_DECISION_RULE', "{$name} {$id}")
                ->and($rule)->not->toHaveKey('decision_authority');
            $rules++;
        }
        foreach ($catalog['correlations'] ?? [] as $row) {
            expect($row)->not->toHaveKeys(['canonical_service_id', 'tax_treatment', 'valid_from', 'published_at']);
        }
    }
    expect($rules)->toBeGreaterThan(90);
});

it('4B.6.7: no decision code reads official correlations; only the authority-free B01 reference does', function (): void {
    $readers = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(b12Root().'/app'));
    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.php')
            && preg_match('/\[\s*[\'"]correlations[\'"]\s*\]/', (string) file_get_contents($file->getPathname())) === 1) {
            $readers[] = $file->getFilename();
        }
    }
    expect($readers)->toBe(['AnexoViiiCorrelationReferenceB01.php']);
});

it('4B.7.3: the required municipal manual is preserved byte-identical to the original', function (): void {
    expect(hash_file('sha256', b12Root().'/resources/fiscal/sources/official/identity104-municipal_manual-observed20261016.pdf'))
        ->toBe('3b5e982be9298ecc72ee254e26eb6824dc13d2e05704a83f01aa7a8fd321916e');
});

it('4B.8.2/4B.8.4: the own bundle binds every versioned resource by canonical hash and detects tampering', function (): void {
    $bundle = json_decode((string) file_get_contents(b12Root().'/resources/fiscal/bundles/consumption-phase4b-2026.10.27-141-draft1.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($bundle['bundle_id'])->toBe('CONSUMPTION-PHASE4B-2026.10.27-141-draft1');
    $bound = 0;
    foreach ($bundle['resource_paths'] as $key => $path) {
        $file = b12Root().'/resources/fiscal/'.$path;
        if (! is_file($file)) {
            continue;
        }
        $document = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        expect(CanonicalJson::hash($document))->toBe($bundle['content_hashes'][$key], $key);
        if (str_starts_with($key, 'rtc_')) {
            expect($document['version'] ?? null)->toBeString($key)
                ->and(basename($path))->toContain((string) $document['version']);
            $tampered = $document;
            $tampered['version'] .= '-tampered';
            expect(CanonicalJson::hash($tampered))->not->toBe($bundle['content_hashes'][$key], $key);
        }
        $bound++;
    }
    expect($bound)->toBe(13)->and($bundle['catalog_versions']['rtc_codes'])->toBe('2026.10.27-141-draft1');
});

it('4B.8.2: the own bundle loads natively and decides a known scenario', function (): void {
    $runner = new ScenarioRunService(new FiscalEngineFactory,
        b12Root().'/resources/fiscal', bundleManifestPath: 'bundles/consumption-phase4b-2026.10.27-141-draft1.json');
    $scenario = json_decode((string) file_get_contents(b12Root().'/resources/fiscal/scenarios/consumption-integrated-2026.10.01-6/integrated-health-presential-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    $bundle = json_decode((string) file_get_contents(b12Root().'/resources/fiscal/bundles/consumption-phase4b-2026.10.27-141-draft1.json'), true, 512, JSON_THROW_ON_ERROR);
    $scenario['rule_bundle_id'] = $bundle['bundle_id'];
    $scenario['catalog_versions'] = $bundle['catalog_versions'];
    $scenario['knowledge_at'] = '2026-10-28T12:00:00+00:00';
    $tmp = tempnam(sys_get_temp_dir(), 'b12');
    file_put_contents($tmp, json_encode($scenario, JSON_THROW_ON_ERROR));
    $result = $runner->run($tmp)->toArray();
    unlink($tmp);
    expect($result['status'])->toBe('VALID_WITH_WARNINGS')
        ->and($result['decision']['consumption_reform']['decision']['classification']['cclasstrib'])->toBe('200029');
});
