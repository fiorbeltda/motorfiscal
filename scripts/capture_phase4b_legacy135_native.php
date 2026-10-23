<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\LegacyExclusionWindow135;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $error): void {
    echo json_encode(['error' => $error->getMessage()], JSON_THROW_ON_ERROR);
    exit(1);
});
$snapshot = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$factory = new FiscalEngineFactory;
$loaded = $factory->scenarioLoaderFromKnowledge($snapshot['knowledge'])->fromArray($snapshot['input']);
$fresh = $factory->makeFromKnowledge($snapshot['knowledge'])->evaluate($loaded->input)->toArray();
if (CanonicalJson::encode($fresh) !== CanonicalJson::encode($snapshot['result'])) {
    throw new RuntimeException('Source134 does not replay exactly.');
}
$rtc = array_column($fresh['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
$iss = array_column($rtc['trace'], null, 'stage')['PARTIAL_EXPORT_DOCUMENT_ISS134'];
$qualifier = new LegacyExclusionWindow135;
$known = new DateTimeImmutable(LegacyExclusionWindow135::AVAILABLE_AT);
$checks = [];
foreach ($iss['documents'] as $document) {
    $context = ['document_reference' => $document['document_reference'], 'supply_on' => $document['iss_supply_on'],
        'composed_price_brl' => $document['price_for_iss_evaluation_in_input_currency'], 'fresh_iss_binding_hash' => $iss['binding_hash']];
    $assessments = [];
    foreach (['ISS', 'ICMS', 'PIS', 'COFINS'] as $tax) {
        $assessments[] = ['tax' => $tax, 'basis' => 'OWN_OPERATION_INCIDENT_AMOUNT',
            'document_context_hash' => CanonicalJson::hash($context),
            'amount_brl' => $tax === 'ISS' ? ($document['iss_tax_brl'] ?? '0.00') : '0.00',
            'evidence_reference' => 'SYNTHETIC-OWN-'.$document['role'].'-'.$tax,
            'assessment_source_hash' => hash('sha256', 'SYNTHETIC-OWN-'.$document['role'].'-'.$tax)];
    }
    foreach (['synthetic-only-iss', 'missing-own', 'withholding-not-requested', 'synthetic-nonzero-pis-cofins'] as $case) {
        $rows = $assessments;
        if ($case === 'missing-own') {
            $rows = [];
        } elseif ($case === 'withholding-not-requested') {
            $rows[2]['basis'] = 'FEDERAL_WITHHOLDING_NOT_REQUESTED';
        } elseif ($case === 'synthetic-nonzero-pis-cofins') {
            $rows[2]['amount_brl'] = '4.02';
            $rows[3]['amount_brl'] = '18.54';
        }
        $checks[] = ['role' => $document['role'], 'case' => $case, 'context' => $context,
            'assessments' => $rows, 'result' => $qualifier->reconcile($qualifier::policy(), $context, $rows, $known)];
    }
}
echo CanonicalJson::encode(['source_replay_exact' => true, 'source_result_hash' => CanonicalJson::hash($fresh),
    'source_federal_withholdings_skipped' => in_array('FEDERAL_WITHHOLDINGS_SKIPPED', array_column($fresh['trace'], 'type'), true),
    'synthetic_assessments_only' => true, 'own_incidence_derived_from_withholding' => false,
    'integrated135' => false, 'checks' => $checks]);
