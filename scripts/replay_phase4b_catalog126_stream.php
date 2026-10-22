<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$factory = new FiscalEngineFactory;
while (($line = fgets(STDIN)) !== false) {
    $item = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $snapshot = $item['snapshot'];
    $known = $snapshot['knowledge'];
    $scenario = $factory->scenarioLoaderFromKnowledge($known)->fromArray($snapshot['input']);
    $matches = $item['runner'] === 'rtc' ? (new RtcScenarioRunService($factory))->replay($snapshot)
        : hash_equals($snapshot['result']['decision_hash'], $factory->makeFromKnowledge($known)->evaluate($scenario->input)->hash());
    echo json_encode(['file' => $item['file'], 'runner' => $item['runner'], 'replay_matches' => $matches], JSON_THROW_ON_ERROR).PHP_EOL;
    if (! $matches) {
        exit(1);
    }
}
