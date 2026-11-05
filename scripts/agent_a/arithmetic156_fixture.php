<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;

require_once __DIR__.'/handoff156_fixture.php';

/** @return array<string, array{0: string, 1: string}> */
function profiles156(): array
{
    return [
        'saas' => ['2026.09.28-7', 'consumption-phase4b-2026.09.28-1/regular-saas-2026.json'],
        'education-presential' => ['2026.10.01-6', 'consumption-phase4b-2026.10.01-6/education-presential-2026.json'],
        'education-remote' => ['2026.10.01-6', 'consumption-phase4b-2026.10.01-6/education-remote-2026.json'],
        'health-presential' => ['2026.10.01-6', 'consumption-phase4b-2026.10.01-6/health-presential-2026.json'],
        'health-glosa' => ['2026.10.01-6', 'consumption-phase4b-2026.10.01-6/health-unpaid-glosa-2026.json'],
        'import-service' => ['2026.10.14-68-draft1', 'import-fx-credit-2026.10.14-68-draft1/proven.json'],
    ];
}

/** Hypothetical rates/layout validity in memory; every result is blocked for official use.
 * @return array{0: array<string, mixed>, 1: array<string, mixed>}
 */
function arithmetic156(string $profile, int $year): array
{
    [$version, $path] = profiles156()[$profile];
    $knowledge = knowledge156('bundles/consumption-phase4b-'.$version.'.json', false);
    $input = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/'.$path)), true, 512, JSON_THROW_ON_ERROR);
    $input['competence_date'] = $year.'-10-01';
    $input['knowledge_at'] = $year.'-11-05T10:00:00Z';
    $input['facts']['rtc.operation_date'] = $year.'-10-01';
    $input['facts']['rtc.legacy_event_spans_transition'] = false;
    if ($profile === 'import-service') {
        // A new hypothetical BRL contract, not a future FX quotation or an edited captured FX record.
        $input['facts']['rtc.gross_currency'] = 'BRL';
        $input['facts']['rtc.gross_amount'] = '5179.50';
        $input['facts']['operation.amount'] = '5179.50';
        unset($input['facts']['rtc.import_fx_proof'], $input['facts']['rtc.import_fx_source_id']);
    }
    $codes = &$knowledge['documents']['rtc_code_catalog'];
    $codes[TemporalRateCatalog::EVIDENCE_KEY] = TemporalRateCatalog::EVIDENCE_POLICY;
    // This is a candidate for arithmetic, not an assertion of the law/layout in a future year.
    $extend = function (array $node) use (&$extend): array {
        foreach ($node as $key => $value) {
            if (in_array($key, ['valid_to', 'sandbox_coverage_through'], true) && is_string($value)) {
                $node[$key] = '2099-12-31';
            } elseif (is_array($value)) {
                $node[$key] = $extend($value);
            }
        }

        return $node;
    };
    $codes = $extend($codes);
    unset($codes);
    $rates = &$knowledge['documents']['rtc_rate_catalog'];
    $rates[TemporalRateCatalog::EVIDENCE_KEY] = TemporalRateCatalog::EVIDENCE_POLICY;
    $rates['rates'] = [];
    foreach ([['IBS_STATE', $year < 2029 ? 'BR' : $input['facts']['rtc.destination_state'], '1.25'],
        ['IBS_MUNICIPAL', $year < 2029 ? 'BR' : $input['facts']['rtc.destination_municipality_ibge'], '0.75'], ['CBS', 'BR', '3.50']] as [$tax, $jurisdiction, $percent]) {
        $rates['rates'][] = ['tax' => $tax, 'jurisdiction' => $jurisdiction, 'percent' => $percent,
            'valid_from' => $year.'-01-01', 'valid_to' => $year.'-12-31', 'published_at' => '2026-01-01T00:00:00Z',
            'status' => 'ACTIVE', 'source_kind' => 'SYNTHETIC_TEST', 'synthetic' => true,
            'source_id' => 'SYNTHETIC.ARITHMETIC156.'.$tax, 'source_hash' => hash('sha256', 'SYNTHETIC.RATES156')];
    }
    unset($rates);

    return [$input, refresh156($knowledge)];
}
