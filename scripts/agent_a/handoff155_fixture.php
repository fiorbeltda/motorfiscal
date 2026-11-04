<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\AnnexStandardQualificationPolicyB07;
use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\CreditIntegration155;
use App\Modules\Fiscal\ConsumptionReform\CreditZeroSemanticsPolicyB02;
use App\Modules\Fiscal\ConsumptionReform\PeriodCreditAssessment155;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** Synthetic in-memory candidate only. Never writes an installed catalog or bundle.
 * @return array<string, mixed>
 */
function handoff155Knowledge(array $gates = ['H1', 'H2', 'H3'], string $bundle = 'bundles/consumption-phase4b-2026.10.27-141-draft1.json'): array
{
    static $bases = [];
    $bases[$bundle] ??= (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), $bundle);
    $knowledge = $bases[$bundle];
    $codes = &$knowledge['documents']['rtc_code_catalog'];
    foreach (['H1' => [CreditZeroSemanticsPolicyB02::KEY, CreditZeroSemanticsPolicyB02::POLICY],
        'H2' => [AnnexStandardQualificationPolicyB07::KEY, AnnexStandardQualificationPolicyB07::POLICY],
        'H3' => [CreditIntegration155::KEY, CreditIntegration155::POLICY]] as $gate => [$key, $policy]) {
        if (in_array($gate, $gates, true)) {
            $codes[$key] = $policy;
        }
    }
    unset($codes);
    $definitions = &$knowledge['documents']['fact_definitions'];
    foreach (['rtc.annex_noncorrespondence_justification' => 'STRING', 'rtc.annex_noncorrespondence_evidence_id' => 'STRING',
        'rtc.annex_nbs_classification_confirmed' => 'BOOLEAN', 'rtc.acquired_regime' => 'STRING',
        'rtc.fuel_destination' => 'STRING', 'rtc.credit_exception_evidence_id' => 'STRING',
        'rtc.employee_collective_agreement_plan' => 'BOOLEAN', 'rtc.saf_sports_rights_acquisition' => 'BOOLEAN',
        'rtc.financial_permission_article' => 'STRING', 'rtc.presumed_credit_claims' => 'ARRAY',
        'rtc.supplier_specific_credit_debits' => 'ARRAY'] as $key => $type) {
        $definitions[] = ['key' => $key, 'type' => $type, 'material' => true];
    }
    unset($definitions);
    $manifest = &$knowledge['documents']['bundle_manifest'];
    $manifest['bundle_id'] = 'A155.IN_MEMORY.TEST.'.implode('.', $gates);
    $manifest['version'] = 'A155.IN_MEMORY.TEST';
    $knowledge['rule_bundle_id'] = $manifest['bundle_id'];
    $knowledge['bundle_version'] = $manifest['version'];
    foreach ($manifest['content_hashes'] ?? [] as $key => $_) {
        $manifest['content_hashes'][$key] = CanonicalJson::hash($knowledge['documents'][$key]);
    }
    unset($manifest);
    foreach ($knowledge['documents'] as $key => $document) {
        if (array_key_exists($key, $knowledge['content_hashes'])) {
            $knowledge['content_hashes'][$key] = CanonicalJson::hash($document);
        }
    }

    return $knowledge;
}

/** @return array<string, mixed> */
function handoff155Evaluate(array $scenario, array $gates = ['H1', 'H2', 'H3'], bool $integrated = false, string $bundle = 'bundles/consumption-phase4b-2026.10.27-141-draft1.json'): array
{
    $factory = new FiscalEngineFactory;
    $knowledge = handoff155Knowledge($gates, $bundle);
    $scenario['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $scenario['catalog_versions'] = $knowledge['catalog_versions'];
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($scenario);
    if ($integrated) {
        return $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input)->toArray();
    }
    $documents = $knowledge['documents'];

    return (new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog'])))
        ->evaluate($loaded->input->context->facts, $loaded->input->context->competenceDate, $loaded->input->context->knowledgeAt);
}

/** @return array<string, mixed> */
function handoff155Input(): array
{
    $input = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/consumption-phase4b-2026.10.02-1/input-credit-standard-2026.json')), true, 512, JSON_THROW_ON_ERROR);
    $input['knowledge_at'] = '2026-11-04T10:00:00+00:00';
    $input['facts']['rtc.acquired_regime'] = 'GENERAL';

    return $input;
}

/** @return array<string, mixed> */
function handoff155Period(): array
{
    return ['contract' => PeriodCreditAssessment155::CONTRACT, 'assessment_id' => 'A155.PERIOD', 'taxpayer_id' => 'TAXPAYER',
        'ledger_evidence_id' => 'FIXTURE.QUALIFIED.LEDGER', 'ledger_complete' => true, 'prior_utilization_complete' => true, 'period' => '2026-10',
        'credits' => [['credit_id' => 'IBS1', 'tax' => 'IBS', 'document_id' => 'DOC1', 'amount' => '100.00', 'appropriated_on' => '2026-08-01', 'previously_utilized_amount' => '0.00'],
            ['credit_id' => 'CBS1', 'tax' => 'CBS', 'document_id' => 'DOC2', 'amount' => '90.00', 'appropriated_on' => '2026-09-01', 'refund_prohibited' => true, 'previously_utilized_amount' => '0.00']],
        'events' => [['event_id' => 'EVENT1', 'credit_id' => 'IBS1', 'tax' => 'IBS', 'document_id' => 'DOC1',
            'kind' => 'REVERSAL', 'cause' => 'LOSS', 'amount' => '20.00', 'event_date' => '2026-10-01', 'evidence_id' => 'FIXTURE.LOSS']],
        'debts' => [['debt_id' => 'IBS.DEBT', 'tax' => 'IBS', 'amount' => '70.00', 'period' => '2026-10', 'fact_date' => '2026-10-02'],
            ['debt_id' => 'CBS.DEBT', 'tax' => 'CBS', 'amount' => '100.00', 'period' => '2026-10', 'fact_date' => '2026-10-02']],
        'refund_requested' => false, 'transfers' => []];
}
