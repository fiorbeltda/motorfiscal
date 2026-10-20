<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DereContributorXmlProjector;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

/** @return array<string, mixed> */
function dereOfficialContributorContext(): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.05-18.json');
    $catalog = $knowledge['documents']['rtc_code_catalog'];
    $month = json_decode(file_get_contents($root.'/scenarios/dere-local-2026.10.05-18/qualified-monthly-closing.json'), true, 512, JSON_THROW_ON_ERROR);
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray(json_decode(file_get_contents(
        $root.'/scenarios/consumption-phase4b-2026.10.05-18/financial-borrower-credit-loan-2026.json'), true, 512, JSON_THROW_ON_ERROR));
    $result = (new RtcScenarioRunService($factory))->evaluate($knowledge, $scenario)->toArray();

    return ['root' => $root, 'catalog' => $catalog, 'month' => $month, 'known_at' => $scenario->input->context->knowledgeAt,
        'operation' => array_column($result['trace'], null, 'stage')['FINANCIAL_OPERATION_AND_BASE'],
        'rules' => ['closing' => $catalog['dere_monthly_closing_projection'], 'balance' => $catalog['dere_trial_balance_projection'],
            'deductions' => $catalog['dere_deduction_projection'], 'contributor_projection' => $catalog['dere_contributor_projection']]];
}

it('binds 26 official activities without inventing fiscal validity or changing candidate17', function (): void {
    $f = dereOfficialContributorContext();
    $catalog = $f['catalog']['dere_monthly_closing_projection']['contributor_catalogs']['servFinanc'];
    $policy = $f['catalog']['dere_contributor_projection'];
    expect(count($catalog['codes']))->toBe(26)->and(CanonicalJson::hash($catalog))->toBe($policy['catalog_sha256'])
        ->and($catalog['fiscal_validity'])->toBe('NOT_SPECIFIED_BY_TABLE')
        ->and(hash_file('sha256', $f['root'].'/sources/official/'.$catalog['source_file']))->toBe($catalog['source_hash']);
    $previous = json_decode(file_get_contents($f['root'].'/catalogs/rtc-codes-2026.10.02-17.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($previous['dere_monthly_closing_projection']['contributor_projection_required'] ?? false)->toBeFalse()
        ->and($f['rules']['closing']['pgcc_projection_required'] ?? false)->toBeFalse();
    foreach (array_keys($catalog['codes']) as $code) {
        $profile = $f['month']['contributor'];
        $profile['servFinanc']['tpAtividades'] = [$code];
        $profile['evidence']['activities']['value'] = [$code];
        $projection = (new DereContributorXmlProjector)->project($profile, $f['month']['contributor_identity'],
            ['servFinanc' => $catalog], $f['known_at'], $policy);
        expect($projection['status'])->toBe('PROJECTED');
        $schema = $policy['xsd'];
        $validation = (new DereXmlSchemaValidator)->validate($projection['xml'], $f['root'].'/sources/official/'.$schema['source_file'],
            $schema['source_sha256'], $projection['schema'], $f['root'].'/sources/official/'.$schema['signature_source_file'], $schema['signature_source_sha256']);
        $this->assertSame('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE', $validation['reason'], $code);
    }
});

it('blocks each independent proof if missing stale future or inconsistent', function (string $name, string $mutation, string $suffix): void {
    $f = dereOfficialContributorContext();
    $profile = $f['month']['contributor'];
    match ($mutation) {
        'missing' => $profile['evidence'][$name]['qualified'] = false,
        'stale' => $profile['evidence'][$name]['valid_to'] = '2026-09-29',
        'future' => $profile['evidence'][$name]['observed_on'] = '2026-10-06',
        'identity' => $profile['evidence'][$name]['supplier_id'] = '22222222000191',
    };
    $projection = (new DereContributorXmlProjector)->project($profile, $f['month']['contributor_identity'],
        $f['rules']['closing']['contributor_catalogs'], $f['known_at'], $f['rules']['contributor_projection']);
    expect($projection['reason'])->toBe('RTC_DERE_CONTRIBUTOR_'.strtoupper($name).'_'.$suffix)
        ->and($projection['status'])->toBe('ACTION_REQUIRED');
})->with(function (): array {
    $cases = [];
    foreach (['registration', 'regimes', 'tax_nature', 'dere_start', 'activities'] as $name) {
        foreach (['missing' => 'EVIDENCE_UNPROVEN', 'stale' => 'EVIDENCE_NOT_EFFECTIVE',
            'future' => 'EVIDENCE_NOT_KNOWN', 'identity' => 'IDENTITY_OR_VALUE_CONFLICT'] as $mutation => $suffix) {
            $cases[$name.'-'.$mutation] = [$name, $mutation, $suffix];
        }
    }

    return $cases;
});

it('reproduces the contributor monthly corpus without promoting local candidates', function (string $name): void {
    $f = dereOfficialContributorContext();
    $month = json_decode(file_get_contents($f['root'].'/scenarios/dere-contributor-2026.10.05-18/'.$name.'.json'), true, 512, JSON_THROW_ON_ERROR);
    $projection = (new DereMonthlyClosingXmlProjector)->project($f['operation'], $month, $f['known_at'], $f['rules']);
    expect($projection['status'])->toBe($month['corpus_expectation']['status'])->and($projection['production_ready'])->toBeFalse();
    if ($name === 'qualified') {
        expect($projection['contributor_projection']['status'])->toBe('PROJECTED')->and($projection['period_closed'])->toBeFalse();
    } else {
        expect($projection['reason'])->toBe($month['corpus_expectation']['reason'])->and(isset($projection['xml']))->toBeFalse();
    }
})->with(['qualified', 'invalid-activity', 'activity-not-effective', 'identity-conflict', 'future-observation',
    'missing-registration', 'missing-start-calendar', 'operation-activity-conflict']);

it('requires official publication knowledge and a contributor covering the full monthly period', function (): void {
    $f = dereOfficialContributorContext();
    $f['rules']['closing']['contributor_catalogs']['servFinanc']['published_on'] = '2026-10-06';
    $f['rules']['contributor_projection']['catalog_sha256'] = CanonicalJson::hash($f['rules']['closing']['contributor_catalogs']['servFinanc']);
    $projection = (new DereMonthlyClosingXmlProjector)->project($f['operation'], $f['month'], $f['known_at'], $f['rules']);
    expect($projection['reason'])->toBe('RTC_DERE_CONTRIBUTOR_ACTIVITY_CATALOG_UNPROVEN');
    $f = dereOfficialContributorContext();
    $f['month']['contributor']['valid_from'] = '2026-09-02';
    $projection = (new DereMonthlyClosingXmlProjector)->project($f['operation'], $f['month'], $f['known_at'], $f['rules']);
    expect($projection['reason'])->toBe('RTC_DERE_MONTHLY_CONTRIBUTOR_MONTH_VALIDITY_CONFLICT');
});

it('rejects mixed catalog policies and secondary regimes outside this financial cut', function (): void {
    $f = dereOfficialContributorContext();
    $f['rules']['closing']['contributor_catalogs']['servFinanc']['codes']['99Z'] = ['code' => '99Z', 'fiscal_validity' => 'NOT_SPECIFIED_BY_TABLE'];
    $projection = (new DereMonthlyClosingXmlProjector)->project($f['operation'], $f['month'], $f['known_at'], $f['rules']);
    expect($projection['reason'])->toBe('RTC_DERE_CONTRIBUTOR_ACTIVITY_CATALOG_POLICY_HASH_CONFLICT');
    $f = dereOfficialContributorContext();
    $f['month']['contributor']['regTribSecund'] = ['2'];
    $projection = (new DereMonthlyClosingXmlProjector)->project($f['operation'], $f['month'], $f['known_at'], $f['rules']);
    expect($projection['reason'])->toBe('RTC_DERE_CONTRIBUTOR_FINANCIAL_ONLY_POLICY_SCOPE_CONFLICT');
});
