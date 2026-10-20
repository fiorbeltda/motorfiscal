<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereNegativeBaseClosingOptionsXmlProjector;

/** Preserved native monthly baseline and synthetic prior chain; no signature or transmission. @return array<string, mixed> */
function bcnOptions50(): array
{
    $root = dirname(__DIR__, 3);
    $e48 = $root.'/storage/app/phase4b-private-consolidation48-draft-20261009-28/';
    $report = json_decode(file_get_contents($e48.'first-gain-monthly-report.json'), true, 512, JSON_THROW_ON_ERROR);
    $baseline = $report['projection'];
    $baseline['xml'] = file_get_contents($root.'/'.$report['event_outputs']['D1199']);
    $chain = json_decode(file_get_contents($root.'/storage/app/phase4b-negative-base49-preparation-20261009/synthetic-version-chain-input.json'), true, 512, JSON_THROW_ON_ERROR)['events'];
    foreach ($chain as &$event) {
        foreach (['xml', 'request_xml', 'return_xml'] as $field) {
            if (isset($event[$field])) {
                $event[$field] = str_replace(['2026-09', '202609', '2026-10-01'], ['2026-08', '202608', '2026-09-01'], $event[$field]);
            }
        }
    }
    unset($event);
    $catalog = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-2026.10.09-28-draft50.json'), true, 512, JSON_THROW_ON_ERROR);
    $policy = $catalog['dere_negative_base_closing_options_projection'];
    $request = ['synthetic' => true, 'supplier_id' => '11111111000191', 'period' => '2026-09',
        'baseline_projection_hash' => CanonicalJson::hash($baseline), 'prior_closing_events' => $chain,
        'parameters' => ['infoBCN' => [
            ['codBCNRaiz' => '1010I', 'usarBCNAcum' => '1', 'metodoAproveit' => '0'],
            ['codBCNRaiz' => '1010C', 'usarBCNAcum' => '1', 'metodoAproveit' => '1'],
        ], 'detBCNeg' => [['codBCN' => '1010C20260801', 'vUsarBCN' => '90.00']]]];

    return [$baseline, $request, new DateTimeImmutable($policy['published_at']), $policy, $root.'/resources/fiscal/sources/official'];
}

it('adds source-bound options to preserved native D1199 and retains the existing closing declaration', function (): void {
    $fixture = bcnOptions50();
    $result = (new DereNegativeBaseClosingOptionsXmlProjector)->project(...$fixture);
    expect($result['status'])->toBe('PROJECTED')->and($result['schema_validation']['reason'])->toBe('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE')
        ->and($result['xml'])->toContain('<gUtilizBCN>', '<codBCN>1010C20260801</codBCN>', '<vUsarBCN>90.00</vUsarBCN>')
        ->and($result['baseline_projection_hash'])->toBe(CanonicalJson::hash($fixture[0]))
        ->and($result['gross_debits_recalculated'])->toBeFalse()->and($result['production_ready'])->toBeFalse();
});

it('serializes fields in official order regardless of caller object key order', function (): void {
    $fixture = bcnOptions50();
    $fixture[1]['parameters']['infoBCN'][0] = ['metodoAproveit' => '0', 'usarBCNAcum' => '1', 'codBCNRaiz' => '1010I'];
    $fixture[1]['parameters']['detBCNeg'][0] = ['vUsarBCN' => '90.00', 'codBCN' => '1010C20260801'];
    expect((new DereNegativeBaseClosingOptionsXmlProjector)->project(...$fixture)['status'])->toBe('PROJECTED');
});

it('rejects unknown knowledge baseline mutation and unproven prior origins', function (string $mutation, string $reason): void {
    $fixture = bcnOptions50();
    switch ($mutation) {
        case 'future-policy': $fixture[2] = $fixture[2]->modify('-1 second');
            break;
        case 'baseline-hash': $fixture[1]['baseline_projection_hash'] = str_repeat('0', 64);
            break;
        case 'supplier': $fixture[1]['supplier_id'] = '22222222000191';
            break;
        case 'real-mode': $fixture[1]['synthetic'] = false;
            break;
        case 'unknown-origin': $fixture[1]['parameters']['detBCNeg'][0]['codBCN'] = '1010C20260700';
            break;
        case 'source': $fixture[3]['mod_sha256'] = str_repeat('0', 64);
            $fixture[3]['source_hash'] = CanonicalJson::hash(array_diff_key($fixture[3], ['source_hash' => true]));
            break;
    }
    $result = (new DereNegativeBaseClosingOptionsXmlProjector)->project(...$fixture);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('RTC_DERE_BCN_OPTIONS_'.$reason)
        ->and($result['production_ready'])->toBeFalse();
})->with([
    ['future-policy', 'POLICY_NOT_KNOWN'], ['baseline-hash', 'MONTHLY_BASELINE_OR_REQUEST_SCOPE_UNPROVEN'],
    ['supplier', 'MONTHLY_BASELINE_OR_REQUEST_SCOPE_UNPROVEN'], ['real-mode', 'MONTHLY_BASELINE_OR_REQUEST_SCOPE_UNPROVEN'],
    ['unknown-origin', 'ORIGIN_NOT_IN_ACTIVE_PRIOR_STOCK'], ['source', 'OFFICIAL_SOURCE_CONFLICT'],
]);
