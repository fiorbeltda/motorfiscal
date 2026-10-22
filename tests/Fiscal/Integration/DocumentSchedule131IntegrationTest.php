<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DocumentSchedule131;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;

require_once dirname(__DIR__, 3).'/scripts/phase4b_import129_fixture.php';

it('adds the actual import schedule without granting document fulfilment in both runners', function (string $runner): void {
    $factory = new FiscalEngineFactory;
    [$ignored, $source] = phase4bImport129Fixture($factory);
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-'.DocumentSchedule131::VERSION.'.json');
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
    $source['knowledge_at'] = '2026-10-22T18:00:00+00:00';
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $result = $runner === 'rtc' ? (new RtcScenarioRunService($factory))->evaluate($knowledge, $loaded)->toArray()
        : $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input)->toArray();
    $assessment = $runner === 'rtc' ? $result : array_column($result['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    expect($assessment['reason_codes'])->toContain('RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN');
    $schedule = array_column($assessment['trace'], null, 'stage')['IMPORT_DOCUMENT_SCHEDULE131'];
    expect($schedule['status'])->toBe('RESOLVED')->and($schedule['mandatory_from'])->toBe('2026-10-01')
        ->and($schedule['required_by_schedule'])->toBeFalse()->and($schedule['full_act_review_required'])->toBeFalse()
        ->and($schedule['document_obligation_complete'])->toBeFalse();
})->with(['rtc', 'integrated']);

it('uses the Brazilian taxpayer regime for import schedules and asks for an unknown recipient regime', function (string $case, string $runner): void {
    $factory = new FiscalEngineFactory;
    $recipient = $case !== 'acquirer simples';
    $overrides = $case === 'acquirer simples' ? ['rtc.acquirer_regime' => 'SIMPLES_NACIONAL']
        : ($case === 'recipient simples' ? ['rtc.recipient_regime' => 'SIMPLES_NACIONAL'] : []);
    [$ignored, $source] = phase4bImport129Fixture($factory, $overrides, $recipient);
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-'.DocumentSchedule131::VERSION.'.json');
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
    $source['knowledge_at'] = '2026-10-22T18:00:00+00:00';
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $result = $runner === 'rtc' ? (new RtcScenarioRunService($factory))->evaluate($knowledge, $loaded)->toArray()
        : $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input)->toArray();
    if ($case === 'recipient unknown') {
        expect($result['reason_codes'])->toContain('RTC_DOCUMENT131_TAXPAYER_REGIME_REQUIRED')
            ->and(array_column($result['questions'], 'fact_key'))->toContain('rtc.recipient_regime');
    } else {
        $assessment = $runner === 'rtc' ? $result : array_column($result['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
        $schedule = array_column($assessment['trace'], null, 'stage')['IMPORT_DOCUMENT_SCHEDULE131'];
        expect($schedule['mandatory_from'])->toBe('2027-01-01')->and($schedule['exception_article'])->toBe('art1 §1')
            ->and($assessment['reason_codes'])->toContain('RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN');
    }
})->with(['acquirer simples', 'recipient simples', 'recipient unknown'])->with(['rtc', 'integrated']);
