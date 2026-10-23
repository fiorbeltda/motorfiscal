<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SoftwareOwnRevenue135;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require_once __DIR__.'/phase4b_own135_fixture.php';
set_exception_handler(static function (Throwable $error): void {
    echo json_encode(['error' => $error->getMessage()], JSON_THROW_ON_ERROR);
    exit(1);
});
$snapshot = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$factory = new FiscalEngineFactory;
$loaded = $factory->scenarioLoaderFromKnowledge($snapshot['knowledge'])->fromArray($snapshot['input']);
$fresh = $factory->makeFromKnowledge($snapshot['knowledge'])->evaluate($loaded->input)->toArray();
if (CanonicalJson::encode($fresh) !== CanonicalJson::encode($snapshot['result'])) {
    throw new RuntimeException('Own135 source fails complete134 replay.');
}
$rtc = array_column($fresh['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
$stages = array_column($rtc['trace'], null, 'stage');
$iss = $stages['PARTIAL_EXPORT_DOCUMENT_ISS134'];
$allocation = $stages['PARTIAL_EXPORT_GROSS_ALLOCATION'];
$calculator = new SoftwareOwnRevenue135;
$known = new DateTimeImmutable(SoftwareOwnRevenue135::AVAILABLE_AT);
$proofs = own135Proofs($iss);
$cases = [];
foreach (['captured-own', 'foreign-ordinary', 'missing-export-inflow', 'revoked', 'changed-revenue', 'changed-iss-context'] as $case) {
    $p = $proofs;
    if ($case === 'foreign-ordinary') {
        $p[0]['value']['revenue']['treatment'] = 'ORDINARY_CUMULATIVE';
        $p[0]['value']['revenue']['bank_inflow'] = null;
    } elseif ($case === 'missing-export-inflow') {
        $p[0]['value']['revenue']['bank_inflow'] = null;
    } elseif ($case === 'revoked') {
        $p[1]['revoked_at'] = $known->format('Y-m-d\TH:i:s.uP');
    } elseif ($case === 'changed-revenue') {
        $p[1]['value']['revenue']['service_revenue_brl'] = '600.00';
    } elseif ($case === 'changed-iss-context') {
        $p[1]['value']['document']['fresh_iss_binding_hash'] = str_repeat('0', 64);
    }
    $p = array_map('own135Capture', $p);
    $cases[] = ['case' => $case, 'proofs' => $p, 'result' => $calculator->calculate($iss, $p, $calculator::policy(), $known)];
}
$documents = $cases[0]['result']['documents'] ?? throw new RuntimeException('Own135 positive result missing.');
$bridge = [];
foreach ($documents as $document) {
    $role = $document['role'];
    $net = $document['legacy_reconciliation']['price_after_declared_legacy_exclusions_brl'];
    $rtcLeg = $allocation[$role === 'RTC_FOREIGN' ? 'foreign_gross_brl' : 'domestic_gross_brl'];
    $bridge[] = ['role' => $role, 'own_net_candidate_brl' => $net, 'rtc_leg_brl' => $rtcLeg,
        'difference_brl' => DecimalValue::fromString($net)->minus(DecimalValue::fromString($rtcLeg))->value(),
        'bridge_matches' => $net === $rtcLeg];
}
echo CanonicalJson::encode(['source_replay_exact' => true, 'source_result_hash' => CanonicalJson::hash($fresh),
    'source_iss_hash' => $iss['binding_hash'], 'cases' => $cases, 'bridge' => $bridge,
    'synthetic_proofs_only' => true, 'bundle135_installed' => false, 'document_obligation_complete' => false]);
