<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\JsonFileWriter;
use Illuminate\Support\Facades\Artisan;

it('preserves caller monetary types across native run snapshot and exact replay', function (mixed $amount, string $runner): void {
    $root = sys_get_temp_dir().'/fidelity119-'.bin2hex(random_bytes(8));
    mkdir($root);
    $writer = new JsonFileWriter;
    $factory = new FiscalEngineFactory;
    $bundle = 'bundles/consumption-phase4b-2026.10.20-119-draft1.json';
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), $bundle);
    $source = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/consumption-phase4b/regular-saas-2026.json')), true, 512, JSON_THROW_ON_ERROR);
    $source['scenario_id'] = 'JSON-FIDELITY119-'.get_debug_type($amount);
    $source['knowledge_at'] = '2026-10-20T14:22:21.000000+00:00';
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
    $source['facts']['rtc.base_adjustments'] = [['kind' => 'INCLUDED_CHARGE', 'amount' => $amount, 'evidence_id' => 'SYNTHETIC-FIDELITY119']];
    $invalidType = is_float($amount) || is_bool($amount);
    $input = $root.'/input.json';
    $snapshot = $root.'/snapshot.json';
    try {
        $writer->write($input, $source);
        $command = $runner === 'rtc' ? 'fiscal:rtc:run' : 'fiscal:scenario:run';
        $exit = Artisan::call($command, ['path' => $input, '--bundle-manifest' => $bundle, '--snapshot' => $snapshot]);
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        expect($exit)->toBe($invalidType && $runner === 'rtc' ? 1 : 0)
            ->and($result['status'])->toBe($invalidType ? 'INVALID' : ($runner === 'rtc' ? 'VALID' : 'VALID_WITH_WARNINGS'));
        if ($invalidType) {
            expect($result['reason_codes'])->toContain('RTC_ADJUSTMENT119_AMOUNT_NOT_EXACT');
        }
        $savedBytes = (string) file_get_contents($snapshot);
        $saved = json_decode($savedBytes, true, 512, JSON_THROW_ON_ERROR);
        $savedAmount = $saved['input']['facts']['rtc.base_adjustments'][0]['amount'];
        expect(get_debug_type($savedAmount))->toBe(get_debug_type($amount))->and($savedAmount)->toBe($amount)
            ->and(CanonicalJson::hash($saved['input']))->toBe(CanonicalJson::hash($source))
            ->and($saved['result'])->toBe($result);
        $replayCommand = $runner === 'rtc' ? 'fiscal:rtc:replay' : 'fiscal:decision:replay';
        expect(Artisan::call($replayCommand, ['snapshot' => $snapshot]))->toBe(0);
        $replay = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        expect($replay['replay_matches'])->toBeTrue()->and(file_get_contents($snapshot))->toBe($savedBytes);
        if ($runner === 'integrated') {
            expect($replay['result'])->toBe($result);
        }
    } finally {
        foreach ([$input, $snapshot] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($root);
    }
})->with([[1.0], [0.0], [-0.0], [1.1], [1], ['1.00'], [true], [false]])->with(['rtc', 'integrated']);

it('round trips nested JSON primitive types without changing canonical input bytes', function (mixed $value): void {
    $path = sys_get_temp_dir().'/fidelity119-codec-'.bin2hex(random_bytes(8)).'.json';
    $payload = ['records' => [['value' => $value]], 'negative_zero' => -0.0];
    try {
        (new JsonFileWriter)->write($path, $payload);
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        expect(get_debug_type($decoded['records'][0]['value']))->toBe(get_debug_type($value))
            ->and(CanonicalJson::hash($decoded))->toBe(CanonicalJson::hash($payload));
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
})->with([[1.0], [0.0], [-0.0], [1], ['1.00'], [true], [false], [null]]);

it('still refuses to overwrite an immutable snapshot', function (): void {
    $path = sys_get_temp_dir().'/fidelity119-immutable-'.bin2hex(random_bytes(8)).'.json';
    $writer = new JsonFileWriter;
    try {
        $writer->write($path, ['value' => 1.0]);
        $before = file_get_contents($path);
        expect(fn () => $writer->write($path, ['value' => 2.0]))->toThrow(RuntimeException::class)
            ->and(file_get_contents($path))->toBe($before);
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

it('rejects nonfinite values before creating a snapshot', function (float $value): void {
    $path = sys_get_temp_dir().'/fidelity119-nonfinite-'.bin2hex(random_bytes(8)).'.json';
    expect(fn () => (new JsonFileWriter)->write($path, ['value' => $value]))->toThrow(JsonException::class)
        ->and(is_file($path))->toBeFalse();
})->with([[INF], [-INF], [NAN]]);
