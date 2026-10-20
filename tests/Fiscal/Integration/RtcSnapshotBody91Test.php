<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;

it('checks the saved result body as well as the recalculated hash before accepting RTC replay', function (string $mutation): void {
    $snapshot = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/storage/app/phase4b-advance88-cli-20261015/past-payment-without-completed-supply-rtc-snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
    switch ($mutation) {
        case 'key-order': $snapshot['result'] = array_reverse($snapshot['result'], true);
            break;
        case 'trace': $snapshot['result']['trace'][0]['known_at'] = '2099-01-01T00:00:00+00:00';
            break;
        case 'questions': $snapshot['result']['questions'][] = ['fact_key' => 'unproven'];
            break;
        case 'missing-facts': $snapshot['result']['missing_facts'][] = 'unproven';
            break;
        case 'reason-codes': $snapshot['result']['reason_codes'] = [];
            break;
        case 'decision': $snapshot['result']['decision'] = ['posting_authorized' => true];
            break;
        case 'status': $snapshot['result']['status'] = 'VALID';
            break;
        case 'body-and-hash':
            $snapshot['result']['trace'][0]['known_at'] = '2099-01-01T00:00:00+00:00';
            $payload = $snapshot['result'];
            unset($payload['decision_hash']);
            $snapshot['result']['decision_hash'] = CanonicalJson::hash($payload);
            break;
    }
    expect((new RtcScenarioRunService(new FiscalEngineFactory))->replay($snapshot))
        ->toBe(in_array($mutation, ['unchanged', 'key-order'], true));
})->with(['unchanged', 'key-order', 'trace', 'questions', 'missing-facts', 'reason-codes', 'decision', 'status', 'body-and-hash']);
