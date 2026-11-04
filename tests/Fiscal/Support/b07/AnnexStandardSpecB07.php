<?php

declare(strict_types=1);
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioRunService;

// B07 test support: scenario builder for the 40 Q-B06-1 rules and the reference oracle
// of the approved decision table. Test-only; it is not used by the engine.

const B07_ROOT = __DIR__.'/../../../..';
const B07_BUNDLE_141 = 'bundles/consumption-phase4b-2026.10.27-141-draft1.json';
const B07_BASE_SCENARIO = 'scenarios/consumption-integrated-2026.10.01-6/integrated-health-presential-2026.json';
const B07_CASES = ['annex-correct-reduced', 'standard-without-justification', 'standard-contradiction', 'standard-justified',
    'standard-justification-insufficient', 'standard-nbs-reclassification', 'standard-missing-nbs', 'standard-service-outside-annex'];

/** @return array<string, mixed> */
function b07Catalog(): array
{
    static $catalog = null;

    return $catalog ??= json_decode((string) file_get_contents(B07_ROOT.'/resources/fiscal/catalogs/rtc-codes-2026.10.27-141-draft1.json'), true, 512, JSON_THROW_ON_ERROR);
}

/** @return list<string> the 40 STANDARD rules flagged by B06 */
function b07Rules(): array
{
    $analysis = json_decode((string) file_get_contents(B07_ROOT.'/storage/app/phase4b-b06-development-20261103/b06-rule-documentation-analysis.json'), true, 512, JSON_THROW_ON_ERROR);

    return array_values(array_map(fn (array $r): string => $r['rule'], array_filter($analysis['results'],
        fn (array $r): bool => $r['classification'] === 'CONTROVERSA_INTERPRETACAO_JURIDICA')));
}

/** @return array{category: string, item: string, nbs: string, rule_id: string} */
function b07AnnexItem(array $rule): array
{
    $catalog = b07Catalog();
    $category = $rule['annex_service_category'];
    $annex = $catalog['annex_service_reduction']['annexes'][$category];
    foreach ($annex['items'] as $item => $spec) {
        if (isset($rule['annex_items']) && ! in_array((string) $item, array_map('strval', $rule['annex_items']), true)) {
            continue;
        }
        foreach ($rule['nbs_prefixes'] as $prefix) {
            $itemNbs = (string) $spec['nbs'];
            if ($itemNbs === '' || ! (str_starts_with($itemNbs, $prefix) || str_starts_with($prefix, $itemNbs))) {
                continue;
            }
            foreach (array_keys($catalog['nbs']) as $code) {
                $code = (string) $code;
                if (preg_match('/^1\.[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/', $code) === 1 && str_starts_with($code, $itemNbs) && str_starts_with($code, $prefix)) {
                    return ['category' => $category, 'item' => (string) $item, 'nbs' => $code, 'rule_id' => $annex['rule_id']];
                }
            }
        }
    }
    throw new RuntimeException('No annex item for rule.');
}

/** An NBS in the official table that is not listed in any Annex II/III item. */
function b07NbsOutsideAnnex(): string
{
    $listed = [];
    foreach (b07Catalog()['annex_service_reduction']['annexes'] as $annex) {
        foreach ($annex['items'] as $spec) {
            if ($spec['nbs'] !== '') {
                $listed[] = (string) $spec['nbs'];
            }
        }
    }
    foreach (array_keys(b07Catalog()['nbs']) as $code) {
        $code = (string) $code;
        if (preg_match('/^1\.[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/', $code) === 1
            && array_filter($listed, fn (string $p): bool => str_starts_with($code, $p)) === []) {
            return $code;
        }
    }
    throw new RuntimeException('No NBS outside annexes.');
}

/** @return array<string, mixed> scenario for one rule and case */
function b07Scenario(string $ruleId, string $case): array
{
    $rule = b07Catalog()['engine_decision_rules'][$ruleId];
    $item = b07AnnexItem($rule);
    $bundle = json_decode((string) file_get_contents(B07_ROOT.'/resources/fiscal/'.(B07_BUNDLE_141)), true, 512, JSON_THROW_ON_ERROR);
    $scenario = json_decode((string) file_get_contents(B07_ROOT.'/resources/fiscal/'.(B07_BASE_SCENARIO)), true, 512, JSON_THROW_ON_ERROR);
    $scenario['scenario_id'] = 'b07-'.$case.'-'.$rule['ctribnac_code'].'-'.strtolower($rule['destination_basis']);
    $scenario['rule_bundle_id'] = $bundle['bundle_id'];
    $scenario['catalog_versions'] = $bundle['catalog_versions'];
    $scenario['knowledge_at'] = '2026-10-28T12:00:00+00:00';
    $f = &$scenario['facts'];
    $f['service.canonical_id'] = 'CTRIBNAC_'.$rule['ctribnac_code'];
    $f['service.ctribnac_code'] = $rule['ctribnac_code'];
    $f['rtc.annex_service_category'] = $item['category'];
    $f['rtc.annex_service_item'] = $item['item'];
    $f['rtc.service_nbs_code'] = $item['nbs'];
    $f['rtc.treatment_rule_id'] = $item['rule_id'];
    unset($f['rtc.health_medical_audit_glosa_amount']);
    if ($item['category'] === 'HEALTH') {
        $f['rtc.health_medical_audit_glosa_amount'] = '0.00';
    }
    if ($rule['destination_basis'] === 'ACQUIRER_DOMICILE') {
        foreach (['rtc.presence_at_supplier_establishment', 'rtc.performance_municipality_ibge', 'rtc.performance_state',
            'rtc.supplier_establishment_state', 'rtc.service_presence_evidence_id'] as $k) {
            unset($f[$k]);
        }
        $f['rtc.delivery_mode'] = 'REMOTE';
        $f['rtc.destination_basis'] = 'ACQUIRER_DOMICILE';
        $f['rtc.destination_municipality_ibge'] = '3509502';
        $f['rtc.destination_evidence_id'] = 'acquirer-domicile-1';
        $f['rtc.service_on_person_or_enjoyed_in_person'] = false;
    }
    $standard = ['rtc.tax_treatment' => 'STANDARD'];
    $qualified = [...$standard, 'rtc.annex_service_description_confirmed' => false,
        'rtc.annex_noncorrespondence_justification' => 'Serviço contratado não corresponde à descrição do item do anexo: ver laudo.',
        'rtc.annex_noncorrespondence_evidence_id' => 'B07.NONCORRESPONDENCE.EVIDENCE', 'rtc.annex_nbs_classification_confirmed' => true];
    $patch = match ($case) {
        'annex-correct-reduced' => ['rtc.tax_treatment' => 'REDUCED_60'],
        'standard-without-justification' => [...$standard, 'rtc.annex_service_description_confirmed' => null],
        'standard-contradiction' => [...$standard, 'rtc.annex_service_description_confirmed' => true],
        'standard-justified' => $qualified,
        'standard-justification-insufficient' => [...$qualified, 'rtc.annex_noncorrespondence_justification' => '   '],
        'standard-nbs-reclassification' => [...$qualified, 'rtc.annex_nbs_classification_confirmed' => false],
        'standard-missing-nbs' => [...$qualified, 'rtc.service_nbs_code' => null],
        'standard-service-outside-annex' => [...$standard, 'rtc.service_nbs_code' => b07NbsOutsideAnnex()],
    };
    foreach ($patch as $k => $v) {
        if ($v === null) {
            unset($f[$k]);
        } else {
            $f[$k] = $v;
        }
    }
    unset($f);

    return $scenario;
}

/**
 * Reference oracle of the approved decision table (Q-B06-1, alternative B).
 *
 * @return array{status: string, reason: string|null, cclasstrib: string|null}
 */
function b07Expected(string $ruleId, string $case): array
{
    $rule = b07Catalog()['engine_decision_rules'][$ruleId];
    $reduced = $rule['annex_service_category'] === 'HEALTH' ? '200029' : '200028';

    return match ($case) {
        'annex-correct-reduced' => ['status' => 'VALID', 'reason' => null, 'cclasstrib' => $reduced],
        'standard-without-justification' => ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_ANNEX_NONCORRESPONDENCE_PROOF_MISSING', 'cclasstrib' => null],
        'standard-contradiction' => ['status' => 'INVALID', 'reason' => 'RTC_ANNEX_STANDARD_CONTRADICTS_CONFIRMED_DESCRIPTION', 'cclasstrib' => null],
        'standard-justified' => ['status' => 'VALID', 'reason' => null, 'cclasstrib' => '000001'],
        'standard-justification-insufficient' => ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_ANNEX_NONCORRESPONDENCE_JUSTIFICATION_INSUFFICIENT', 'cclasstrib' => null],
        'standard-nbs-reclassification' => ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_ANNEX_NBS_RECLASSIFICATION_REQUIRED', 'cclasstrib' => null],
        'standard-missing-nbs' => ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_ANNEX_SERVICE_NBS_REQUIRED', 'cclasstrib' => null],
        // Outside the annexes the gate does not apply: the result must equal the historical one.
        'standard-service-outside-annex' => ['status' => 'HISTORICAL', 'reason' => null, 'cclasstrib' => null],
    };
}

/** @return array<string, mixed> */
function b07Run(array $scenario, string $bundle = B07_BUNDLE_141): array
{
    if ($bundle !== B07_BUNDLE_141) {
        // Same facts, replayed on the selected bundle: identity, catalog versions and its knowledge date.
        $manifest = json_decode((string) file_get_contents(B07_ROOT.'/resources/fiscal/'.$bundle), true, 512, JSON_THROW_ON_ERROR);
        $scenario['rule_bundle_id'] = $manifest['bundle_id'];
        $scenario['catalog_versions'] = $manifest['catalog_versions'];
        $scenario['knowledge_at'] = max($scenario['knowledge_at'], $manifest['knowledge_available_at']);
    }
    $tmp = tempnam(sys_get_temp_dir(), 'b07');
    file_put_contents($tmp, json_encode($scenario, JSON_THROW_ON_ERROR));
    try {
        $runner = new ScenarioRunService(
            new FiscalEngineFactory, B07_ROOT.'/resources/fiscal', bundleManifestPath: $bundle);

        return $runner->run($tmp)->toArray();
    } finally {
        unlink($tmp);
    }
}

/**
 * RTC outcome of an integrated run. A VALID qualification is reported in decision.consumption_reform; a blocked one
 * (ACTION_REQUIRED/INVALID) stops the integrated decision, which then carries the RTC status and reason codes.
 *
 * @return array{status: string|null, reasons: list<string>, decision: array<string, mixed>|null}
 */
function b07Outcome(array $result): array
{
    $rtc = $result['decision']['consumption_reform'] ?? null;
    if (is_array($rtc)) {
        return ['status' => $rtc['status'] ?? null, 'reasons' => $rtc['reasons'] ?? [], 'decision' => $rtc['decision'] ?? null];
    }

    return ['status' => $result['status'] ?? null, 'reasons' => $result['reason_codes'] ?? [], 'decision' => null];
}
