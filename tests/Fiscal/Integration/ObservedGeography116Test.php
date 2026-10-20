<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/**
 * @param  array<string, mixed>  $overrides
 * @return array{0: array<string, mixed>, 1: array<string, mixed>}
 */
function geographic116Input(string $branch, array $overrides = [], bool $historical = false): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, $historical
        ? 'bundles/consumption-phase4b-2026.10.19-112-draft2.json'
        : 'bundles/consumption-phase4b-2026.10.20-116-draft2.json');
    $path = match ($branch) {
        'ordinary' => 'scenarios/consumption-phase4b/regular-saas-2026.json',
        'import' => 'scenarios/consumption-phase4b/import-digital-2026-document-gap.json',
        'donation' => 'scenarios/consumption-phase4b-2026.09.28-17/donation-market-nominal-2026.json',
        'partial' => 'scenarios/document-prices-2026.10.19-112-draft2/000-no-adjustments-gross-before-all-discounts.json',
        default => throw new InvalidArgumentException('Unknown geographic test branch'),
    };
    $source = json_decode((string) file_get_contents($root.'/'.$path), true, 512, JSON_THROW_ON_ERROR);
    // Explicit synthetic fixture additions let the ISS import path reach RTC without overriding its guards.
    if ($branch === 'import') {
        $source['facts']['operation.service_origin'] = 'FOREIGN';
        $source['facts']['operation.issuer_role'] = 'CUSTOMER';
        $source['facts']['customer.municipality_ibge'] = '3550308';
    }
    if ($branch === 'partial') {
        $source['facts']['rtc.assess_export_document_prices'] = false;
    }
    $source['knowledge_at'] = '2026-10-20T12:06:00+00:00';
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($source['facts'][$key]);
        } else {
            $source['facts'][$key] = $value;
        }
    }

    return [$knowledge, $source];
}

/**
 * @param  array<string, mixed>  $knowledge
 * @param  array<string, mixed>  $source
 * @return array<string, mixed>
 */
function geographic116Evaluate(array $knowledge, array $source, string $runner): array
{
    $factory = new FiscalEngineFactory;
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    if ($runner === 'integrated') {
        $result = $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input);

        return ['status' => $result->status->value, 'reason_codes' => $result->reasonCodes,
            'trace' => $result->trace, 'decision' => $result->decision];
    }
    $documents = $knowledge['documents'];

    return (new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog'])))->evaluate(
            $loaded->input->context->facts, $loaded->input->context->competenceDate, $loaded->input->context->knowledgeAt);
}

/** @return list<array{0: string, 1: string}> */
function geographic116Paths(): array
{
    $paths = [];
    foreach (['ordinary', 'import', 'donation', 'partial'] as $branch) {
        foreach (['rtc', 'integrated'] as $runner) {
            $paths[] = [$branch, $runner];
        }
    }

    return $paths;
}

/** @param array<string, mixed> $value
 * @return list<array<string, mixed>>
 */
function geographic116Stages(array $value): array
{
    $found = [];
    if (($value['stage'] ?? null) === 'OBSERVED_GEOGRAPHIC_REFERENCE') {
        $found[] = $value;
    }
    foreach ($value as $child) {
        if (is_array($child)) {
            $found = [...$found, ...geographic116Stages($child)];
        }
    }

    return $found;
}

it('requires coherent geography on all destination-consuming paths without a caller opt in', function (string $branch, string $runner, string $uf, string $code, string $status, string $reason): void {
    [$knowledge, $source] = geographic116Input($branch, ['rtc.destination_state' => $uf, 'rtc.destination_municipality_ibge' => $code]);
    $result = geographic116Evaluate($knowledge, $source, $runner);
    expect($result['status'])->toBe($status)->and($result['reason_codes'])->toContain('GEOGRAPHY115_'.$reason)
        ->and($result['decision'])->toBe([]);
    $stages = geographic116Stages($result['trace']);
    expect($stages)->toHaveCount(1)->and($stages[0]['fiscal_destination_determined'])->toBeFalse()
        ->and($stages[0]['historical_validity_proven'])->toBeFalse()
        ->and($stages[0]['production_ready'])->toBeFalse();
})->with((function (): array {
    $cases = [];
    foreach (geographic116Paths() as [$branch, $runner]) {
        foreach ([['RS', '3509502', 'INVALID', 'UF_CODE_PREFIX_CONFLICT'],
            ['XX', '3509502', 'INVALID', 'UF_NOT_IN_OBSERVED_REFERENCE'],
            ['SP', '3599999', 'ACTION_REQUIRED', 'TERRITORIAL_CODE_NOT_IN_OBSERVED_REFERENCE']] as $row) {
            $cases[] = [$branch, $runner, ...$row];
        }
    }

    return $cases;
})());

it('keeps valid-path decisions while adding only an observed-reference trace', function (string $branch, string $runner): void {
    [$knowledge, $source] = geographic116Input($branch);
    [$previous, $oldSource] = geographic116Input($branch, historical: true);
    $old = geographic116Evaluate($previous, $oldSource, $runner);
    $new = geographic116Evaluate($knowledge, $source, $runner);
    $expected = $old['decision'];
    $actual = $new['decision'];
    // The new bundle must report its actual identity; only these explicit metadata paths may change.
    if ($expected !== []) {
        if ($runner === 'rtc') {
            $expected['catalog_versions']['codes'] = $knowledge['catalog_versions']['rtc_codes'];
        } else {
            $expected['versions']['rule_bundle_id'] = $knowledge['rule_bundle_id'];
            $expected['versions']['catalogs']['rtc_codes'] = $knowledge['catalog_versions']['rtc_codes'];
            foreach (['bundle_manifest', 'rtc_code_catalog'] as $key) {
                $expected['versions']['content_hashes'][$key] = $knowledge['content_hashes'][$key];
            }
            $expected['consumption_reform']['decision']['catalog_versions']['codes'] = $knowledge['catalog_versions']['rtc_codes'];
            $actual['consumption_reform']['trace'] = array_values(array_filter(
                $actual['consumption_reform']['trace'],
                fn (array $stage): bool => ($stage['stage'] ?? null) !== 'OBSERVED_GEOGRAPHIC_REFERENCE'));
        }
    }
    expect($new['status'])->toBe($old['status'])->and($actual)->toBe($expected);
    expect(geographic116Stages($old['trace']))->toBe([]);
    $stages = geographic116Stages($new['trace']);
    expect($stages)->toHaveCount(1)->and($stages[0]['municipality_ibge'])->toBe('3509502')
        ->and($stages[0]['historical_validity_proven'])->toBeFalse()
        ->and($stages[0]['operation_date_not_reference_validity'])->toBe('2026-09-25');
})->with(geographic116Paths());

it('does not silently bypass missing or changed geographic policy and source rows', function (string $branch, string $runner, string $mutation): void {
    [$knowledge, $source] = geographic116Input($branch);
    $codes = &$knowledge['documents']['rtc_code_catalog'];
    if ($mutation === 'missing-both') {
        unset($codes['observed_geographic_reference_policy'], $codes['observed_geographic_reference']);
    } elseif ($mutation === 'missing-policy') {
        unset($codes['observed_geographic_reference_policy']);
    } elseif ($mutation === 'missing-reference') {
        unset($codes['observed_geographic_reference']);
    } elseif ($mutation === 'changed-row-rehashed') {
        $codes['observed_geographic_reference']['municipalities']['3509502']['uf'] = 'RS';
        $codes['observed_geographic_reference']['municipalities_sha256'] = CanonicalJson::hash($codes['observed_geographic_reference']['municipalities']);
    } else {
        $codes['observed_geographic_reference']['historical_validity_proven'] = true;
    }
    unset($codes);
    $hash = CanonicalJson::hash($knowledge['documents']['rtc_code_catalog']);
    $knowledge['content_hashes']['rtc_code_catalog'] = $hash;
    $knowledge['documents']['bundle_manifest']['content_hashes']['rtc_code_catalog'] = $hash;
    $knowledge['content_hashes']['bundle_manifest'] = CanonicalJson::hash($knowledge['documents']['bundle_manifest']);
    $result = geographic116Evaluate($knowledge, $source, $runner);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['decision'])->toBe([]);
    $stages = geographic116Stages($result['trace']);
    expect($stages)->toHaveCount(1)->and($stages[0]['status'])->toBe('ACTION_REQUIRED');
})->with((function (): array {
    $cases = [];
    foreach (geographic116Paths() as [$branch, $runner]) {
        foreach (['missing-policy', 'missing-reference', 'missing-both', 'changed-row-rehashed', 'injected-historical-approval'] as $mutation) {
            $cases[] = [$branch, $runner, $mutation];
        }
    }

    return $cases;
})());

it('does not grant a new reference gate to historical captured knowledge', function (string $branch, string $runner): void {
    [$knowledge, $source] = geographic116Input($branch, ['rtc.destination_state' => 'RS'], historical: true);
    $result = geographic116Evaluate($knowledge, $source, $runner);
    expect(geographic116Stages($result['trace']))->toBe([])
        ->and(implode('|', $result['reason_codes']))->not->toContain('GEOGRAPHY115_');
})->with(geographic116Paths());
