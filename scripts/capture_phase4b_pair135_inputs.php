<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require_once __DIR__.'/phase4b_dps_pair135_fixture.php';
set_exception_handler(static function (Throwable $error): void {
    echo json_encode(['error' => $error->getMessage()], JSON_THROW_ON_ERROR);
    exit(1);
});
$positive = pair135Fixture();
$invalid = $positive['declarations'];
$invalid['documentary_proof']['value']['options']['numbers']['RTC_DOMESTIC'] = '9999';
$invalid = pair135Reseal($positive['snapshot'], $invalid);
$former = pair135Fixture('617.92');
echo CanonicalJson::encode(['positive' => $positive, 'invalid_identity_declarations' => $invalid, 'former_contract' => $former,
    'source_snapshot_canonical_hash' => CanonicalJson::hash($positive['snapshot']), 'synthetic_only' => true]);
