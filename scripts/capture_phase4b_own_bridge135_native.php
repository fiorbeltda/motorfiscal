<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DocumentOwnTaxBridge135;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require_once __DIR__.'/phase4b_own135_pair_fixture.php';
set_exception_handler(static function (Throwable $error): void {
    echo json_encode(['error' => $error->getMessage()], JSON_THROW_ON_ERROR);
    exit(1);
});
$records = [];
foreach (['new-explicit-contract' => '641.33', 'former-iss-only-contract' => '617.92', 'one-cent-higher-contract' => '641.34'] as $name => $price) {
    $case = own135PairFixture($price);
    $snapshot = $case['snapshot'];
    $factory = new FiscalEngineFactory;
    $loaded = $factory->scenarioLoaderFromKnowledge($snapshot['knowledge'])->fromArray($snapshot['input']);
    $fresh = $factory->makeFromKnowledge($snapshot['knowledge'])->evaluate($loaded->input)->toArray();
    if (CanonicalJson::encode($fresh) !== CanonicalJson::encode($snapshot['result'])) {
        throw new RuntimeException('New quote134 source did not replay exactly.');
    }
    $rtc = array_column($fresh['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    $stages = array_column($rtc['trace'], null, 'stage');
    $bridge = (new DocumentOwnTaxBridge135)->reconcile($loaded->input->context->facts, $stages['PARTIAL_EXPORT_DOCUMENT_ISS134'],
        $case['own_proofs'], $stages['PARTIAL_EXPORT_GROSS_ALLOCATION'], DocumentOwnTaxBridge135::policy(), new DateTimeImmutable($case['known_at']));
    $records[] = ['case' => $name, 'explicit_domestic_price_brl' => $price, 'source_replay_exact' => true,
        'snapshot' => $snapshot, 'own_proofs' => $case['own_proofs'], 'result' => $bridge];
}
echo CanonicalJson::encode(['cases' => $records, 'synthetic_contracts_and_proofs_only' => true,
    'bundle135_installed' => false, 'document_obligation_complete' => false]);
