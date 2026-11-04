<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\AnnexStandardQualificationPolicyB07;
use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\CreditIntegration155;
use App\Modules\Fiscal\ConsumptionReform\CreditZeroSemanticsPolicyB02;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

require_once dirname(__DIR__, 3).'/scripts/agent_a/handoff155_fixture.php';
require_once dirname(__DIR__).'/Support/b07/AnnexStandardSpecB07.php';

// B23: the installed bundle that activates the A155 handoffs (H1 B02, H2 B07, H3 A155) in a new version.
const B23_BUNDLE = 'bundles/consumption-phase4b-2026.11.04-b23-draft1.json';
const B23_PREVIOUS = 'bundles/consumption-phase4b-2026.10.27-141-draft1.json';
const B23_SCENARIO_NAMES = ['h3-general-regime', 'h1-zero-valid-true-extinguished-false', 'h1-zero-valid-false-extinguished-true',
    'h1-zero-valid-false-extinguished-false', 'h1-personal-use', 'h3-b13-personal_use', 'h3-b13-health_plan', 'h3-b13-betting',
    'h3-regime-missing', 'h3-b17-presumed', 'h3-b17-scope-conflict', 'h2-standard-contradiction', 'h2-standard-justified',
    'h2-standard-without-justification'];

/** @return array<string, mixed> */
function b23Manifest(string $path = B23_BUNDLE): array
{
    return json_decode((string) file_get_contents(resource_path('fiscal/'.$path)), true, 512, JSON_THROW_ON_ERROR);
}

/** Runs a scenario on the installed B23 bundle (RTC runner or integrated runner). */
function b23Evaluate(array $scenario, bool $integrated): array
{
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), B23_BUNDLE);
    $scenario['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $scenario['catalog_versions'] = $knowledge['catalog_versions'];
    $scenario['knowledge_at'] = max($scenario['knowledge_at'], b23Manifest()['knowledge_available_at']);
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($scenario);
    if ($integrated) {
        return $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input)->toArray();
    }
    $documents = $knowledge['documents'];

    return (new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog'])))
        ->evaluate($loaded->input->context->facts, $loaded->input->context->competenceDate, $loaded->input->context->knowledgeAt);
}

/** Bundle-independent RTC outcome: status, reasons, missing facts and decision (no bundle identity or questions). */
function b23Outcome(array $result, bool $integrated): array
{
    if ($integrated) {
        $rtc = $result['decision']['consumption_reform'] ?? null;

        return is_array($rtc)
            ? ['status' => $rtc['status'], 'reasons' => $rtc['reasons'] ?? [], 'decision' => $rtc['decision'] ?? null]
            : ['status' => $result['status'], 'reasons' => $result['reason_codes'], 'missing' => $result['missing_facts'] ?? []];
    }

    return ['status' => $result['status'], 'reasons' => $result['reason_codes'], 'missing' => $result['missing_facts'],
        'decision' => $result['decision']];
}

/** @return array<string, array<string, mixed>> scenarios covering H1, H2 and H3 (same facts as the A155 suite) */
function b23Scenarios(): array
{
    $base = handoff155Input();
    $claim = ['hypothesis' => 'RURAL_PRODUCER_NON_CONTRIBUTOR', 'document_id' => $base['facts']['rtc.input_document_id'],
        'acquisition_date' => $base['facts']['rtc.operation_date'], 'acquirer_id' => $base['facts']['rtc.acquirer_id'],
        'acquirer_regime' => 'REGULAR', 'base_amount' => '1000.00',
        'rate_parameters' => ['source_id' => 'SYNTHETIC.A155', 'source_kind' => 'SYNTHETIC_TEST', 'ibs_percent' => '2.5', 'cbs_percent' => '1.5']];
    $cases = ['h3-general-regime' => $base];
    foreach ([[true, false], [false, true], [false, false]] as [$valid, $extinguished]) {
        $case = $base;
        $case['facts']['rtc.input_document_valid'] = $valid;
        $case['facts']['rtc.prior_tax_extinguished'] = $extinguished;
        $cases['h1-zero-valid-'.var_export($valid, true).'-extinguished-'.var_export($extinguished, true)] = $case;
    }
    $personal = $base;
    $personal['facts']['rtc.credit_context'] = 'PERSONAL_USE';
    $cases['h1-personal-use'] = $personal;
    foreach (['PERSONAL_USE', 'HEALTH_PLAN', 'BETTING'] as $regime) {
        $case = $base;
        $case['facts']['rtc.acquired_regime'] = $regime;
        $cases['h3-b13-'.strtolower($regime)] = $case;
    }
    $missing = $base;
    unset($missing['facts']['rtc.acquired_regime']);
    $cases['h3-regime-missing'] = $missing;
    $presumed = $base;
    $presumed['facts']['rtc.presumed_credit_requested'] = true;
    $presumed['facts']['rtc.presumed_credit_claims'] = [$claim];
    $cases['h3-b17-presumed'] = $presumed;
    $conflict = $presumed;
    $conflict['facts']['rtc.presumed_credit_claims'][0]['document_id'] = 'OTHER';
    $cases['h3-b17-scope-conflict'] = $conflict;
    $rule = b07Rules()[0];
    foreach (['standard-contradiction', 'standard-justified', 'standard-without-justification'] as $b07) {
        $cases['h2-'.$b07] = b07Scenario($rule, $b07);
    }

    return $cases;
}

it('forks bundle 141 changing only the code catalog, facts, questions and production controls', function (): void {
    $new = b23Manifest();
    $old = b23Manifest(B23_PREVIOUS);
    expect($new['predecessor_bundle_id'])->toBe($old['bundle_id'])
        ->and(array_keys(array_diff_assoc($new['content_hashes'], $old['content_hashes'])))
        ->toEqualCanonicalizing(['rtc_code_catalog', 'fact_definitions', 'questions']);
    $versions = array_diff_assoc($new['catalog_versions'], $old['catalog_versions']);
    expect($versions)->toBe(['rtc_codes' => '2026.11.04-b23-draft1']);
    $codes = json_decode((string) file_get_contents(resource_path('fiscal/'.$new['resource_paths']['rtc_code_catalog'])), true, 512, JSON_THROW_ON_ERROR);
    $oldCodes = json_decode((string) file_get_contents(resource_path('fiscal/'.$old['resource_paths']['rtc_code_catalog'])), true, 512, JSON_THROW_ON_ERROR);
    $added = array_diff(array_keys($codes), array_keys($oldCodes));
    expect(array_values($added))->toEqualCanonicalizing([CreditZeroSemanticsPolicyB02::KEY, AnnexStandardQualificationPolicyB07::KEY, CreditIntegration155::KEY]);
    foreach ($oldCodes as $key => $value) {
        if ($key !== 'version') {
            expect(json_encode($codes[$key], JSON_THROW_ON_ERROR))->toBe(json_encode($value, JSON_THROW_ON_ERROR));
        }
    }
    expect(CreditZeroSemanticsPolicyB02::required($codes))->toBeTrue()
        ->and(AnnexStandardQualificationPolicyB07::required($codes))->toBeTrue()
        ->and(CreditIntegration155::required($codes))->toBeTrue()
        ->and(CreditIntegration155::required($oldCodes))->toBeFalse();
});

it('defines the eleven A155 facts with the exact types of the A-side candidate, each with a question', function (): void {
    $candidate = handoff155Knowledge()['documents']['fact_definitions'];
    $installed = (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), B23_BUNDLE)['documents'];
    expect(CanonicalJson::hash($installed['fact_definitions']))->toBe(CanonicalJson::hash($candidate));
    $asked = array_column($installed['questions'], 'fact_key');
    foreach (array_slice($candidate, -11) as $fact) {
        expect($asked)->toContain($fact['key']);
    }
});

it('reproduces on the installed B23 bundle exactly the A155 in-memory candidate result', function (string $name, bool $integrated): void {
    $scenario = b23Scenarios()[$name];
    $installed = b23Outcome(b23Evaluate($scenario, $integrated), $integrated);
    $candidate = b23Outcome(handoff155Evaluate($scenario, ['H1', 'H2', 'H3'], $integrated), $integrated);
    // Only two expected differences: (1) the decision names the installed code-catalog version; (2) the in-memory
    // candidate had no question for the new facts (QUESTION_UNMAPPED), which B23 supplies.
    if (isset($installed['decision']['catalog_versions']['codes'])) {
        expect($installed['decision']['catalog_versions']['codes'])->toBe('2026.11.04-b23-draft1')
            ->and($candidate['decision']['catalog_versions']['codes'])->toBe('2026.10.27-141-draft1');
        $candidate['decision']['catalog_versions']['codes'] = $installed['decision']['catalog_versions']['codes'];
    }
    $unmapped = array_values(array_filter($candidate['reasons'], fn (string $r): bool => str_starts_with($r, 'QUESTION_UNMAPPED:')));
    expect(array_filter($installed['reasons'], fn (string $r): bool => str_starts_with($r, 'QUESTION_UNMAPPED:')))->toBe([]);
    $candidate['reasons'] = array_values(array_diff($candidate['reasons'], $unmapped));
    expect(CanonicalJson::hash($installed))->toBe(CanonicalJson::hash($candidate));
})->with(B23_SCENARIO_NAMES)->with([false, true]);

it('lists every built scenario in the dataset', function (): void {
    expect(array_keys(b23Scenarios()))->toBe(B23_SCENARIO_NAMES);
});

it('activates H1 and H3 on the installed bundle with the expected B02/B13/B17 outcomes', function (): void {
    $cases = b23Scenarios();
    $zero = b23Evaluate($cases['h1-zero-valid-false-extinguished-false'], false);
    expect($zero['status'])->toBe('VALID')->and($zero['decision']['ibs']['credit']['zero_semantics_version'])->toBe('B02')
        ->and($zero['decision']['ibs']['credit']['zero_causes'])->toContain('INPUT_DOCUMENT_NOT_VALID', 'PRIOR_TAX_NOT_EXTINGUISHED');
    $prohibited = b23Evaluate($cases['h3-b13-betting'], false);
    expect($prohibited['decision']['cbs']['credit']['status'])->toBe('DENIED')
        ->and($prohibited['decision']['cbs']['credit']['zero_causes'])->toBe(['B13_STATUTORY_CREDIT_PROHIBITION']);
    $presumed = b23Evaluate($cases['h3-b17-presumed'], false)['decision']['credit_integration155'];
    expect($presumed['presumed']['official_decision'])->toBeFalse()->and($presumed['presumed_appropriated'])->toBeFalse()
        ->and($presumed['ordinary_and_presumed_added'])->toBeFalse();
    expect(b23Evaluate($cases['h3-regime-missing'], false)['reason_codes'])->toContain('RTC_ACQUIRED_REGIME_REQUIRED');
});

it('keeps bundle 141 historical: same scenarios there carry no B02 annotation and no A155 block', function (): void {
    $scenario = b23Scenarios()['h1-zero-valid-false-extinguished-false'];
    $old = handoff155Evaluate($scenario, [], false);
    expect($old['decision']['ibs']['credit'])->not->toHaveKey('zero_semantics_version')
        ->and($old['decision'])->not->toHaveKey('credit_integration155');
});
