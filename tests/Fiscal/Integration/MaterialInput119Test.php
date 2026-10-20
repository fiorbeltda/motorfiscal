<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\MaterialInputPolicy119;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @param array<string, mixed> $overrides
 * @return array{0: array<string, mixed>, 1: array<string, mixed>}
 */
function material119Input(string $branch = 'ordinary', array $overrides = [], string $version = '119'): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $bundle = match ($version) {
        '112' => '2026.10.19-112-draft2', '116' => '2026.10.20-116-draft2',
        default => MaterialInputPolicy119::VERSION,
    };
    $knowledge = (new FiscalEngineFactory)->captureKnowledge($root, 'bundles/consumption-phase4b-'.$bundle.'.json');
    $path = match ($branch) {
        'acquirer' => 'consumption-phase4b-2026.09.28-3/acquirer-eligible-unpaid-2026.json',
        'import' => 'consumption-phase4b/import-digital-2026-document-gap.json',
        'donation' => 'consumption-phase4b-2026.09.28-17/donation-market-nominal-2026.json',
        'partial' => 'document-prices-2026.10.19-112-draft2/000-no-adjustments-gross-before-all-discounts.json',
        default => 'consumption-phase4b/regular-saas-2026.json',
    };
    $source = json_decode((string) file_get_contents($root.'/scenarios/'.$path), true, 512, JSON_THROW_ON_ERROR);
    if ($branch === 'import') {
        $source['facts']['operation.service_origin'] = 'FOREIGN';
        $source['facts']['operation.issuer_role'] = 'CUSTOMER';
        $source['facts']['customer.municipality_ibge'] = '3550308';
    }
    if ($branch === 'partial') {
        $source['facts']['rtc.assess_export_document_prices'] = false;
    }
    // Synthetic knowledge context after this build, not a new source observation.
    $source['knowledge_at'] = '2026-10-20T14:22:21.000000+00:00';
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($source['facts'][$key]);
        } else {
            $source['facts'][$key] = $value;
        }
    }

    return [$knowledge, $source];
}

/** @param array<string, mixed> $knowledge
 * @param  array<string, mixed>  $source
 * @return array<string, mixed>
 */
function material119Evaluate(array $knowledge, array $source, string $runner): array
{
    $factory = new FiscalEngineFactory;
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    if ($runner === 'integrated') {
        $result = $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input);

        return ['status' => $result->status->value, 'reason_codes' => $result->reasonCodes,
            'decision' => $result->decision, 'trace' => $result->trace];
    }
    $d = $knowledge['documents'];

    return (new ConsumptionReformEngine(new TemporalRateCatalog($d['rtc_rate_catalog']),
        $d['rtc_code_catalog'], new TransitionCatalog($d['rtc_transition_catalog'])))->evaluate(
            $loaded->input->context->facts, $loaded->input->context->competenceDate, $loaded->input->context->knowledgeAt);
}

/** @param array<string, mixed> $result
 * @return array<string, mixed>
 */
function material119Decision(array $result, string $runner): array
{
    return $runner === 'rtc' ? $result['decision'] : ($result['decision']['consumption_reform']['decision'] ?? []);
}

it('rejects invalid nested amounts without throwing on every early-consuming branch', function (string $branch, string $runner, mixed $amount): void {
    [$k, $s] = material119Input($branch, ['rtc.base_adjustments' => [
        ['kind' => 'INCLUDED_CHARGE', 'amount' => $amount, 'evidence_id' => 'SYNTHETIC-119'],
    ]]);
    $r = material119Evaluate($k, $s, $runner);
    expect($r['status'])->toBe('INVALID')->and($r['reason_codes'])->toContain('RTC_ADJUSTMENT119_AMOUNT_NOT_EXACT')
        ->and($r['decision'])->toBe([]);
})->with((function (): array {
    $rows = [];
    foreach (['ordinary', 'import', 'donation', 'partial'] as $branch) {
        foreach (['rtc', 'integrated'] as $runner) {
            foreach ([true, false, 1.1, 1.0, '', ' ', '1e2', '1,00', "1.00\n", [], ['amount' => '1.00']] as $amount) {
                $rows[] = [$branch, $runner, $amount];
            }
        }
    }

    return $rows;
})());

it('refuses boolean amounts in discount and reimbursement too', function (string $kind, string $runner, bool $amount): void {
    [$k, $s] = material119Input(overrides: ['rtc.base_adjustments' => [[
        'kind' => $kind, 'amount' => $amount, 'evidence_id' => 'EV119', 'document_id' => 'dps-1',
        'depends_on_later_event' => false, 'third_party_id' => 'TP119',
        'third_party_document_id' => 'DOC119', 'document_in_third_party_name' => true,
    ]]]);
    $r = material119Evaluate($k, $s, $runner);
    expect($r['reason_codes'])->toContain('RTC_ADJUSTMENT119_AMOUNT_NOT_EXACT')->and($r['decision'])->toBe([]);
})->with(['UNCONDITIONAL_DISCOUNT', 'DOCUMENTED_REIMBURSEMENT'])->with(['rtc', 'integrated'])->with([true, false]);

it('requires meaningful nested evidence and third-party identifiers', function (string $kind, string $key, mixed $value, string $reason, string $runner): void {
    $row = ['kind' => $kind, 'amount' => '100.00', 'evidence_id' => 'EV119', 'document_id' => 'dps-1',
        'depends_on_later_event' => false, 'third_party_id' => 'TP119',
        'third_party_document_id' => 'DOC119', 'document_in_third_party_name' => true];
    $row[$key] = $value;
    [$k, $s] = material119Input(overrides: ['rtc.base_adjustments' => [$row]]);
    $r = material119Evaluate($k, $s, $runner);
    expect($r['status'])->toBe('ACTION_REQUIRED')->and($r['reason_codes'])->toContain($reason)->and($r['decision'])->toBe([]);
})->with((function (): array {
    $rows = [];
    foreach (['INCLUDED_CHARGE', 'UNCONDITIONAL_DISCOUNT', 'DOCUMENTED_REIMBURSEMENT'] as $kind) {
        $keys = ['evidence_id' => 'RTC_ADJUSTMENT119_EVIDENCE_EMPTY'];
        if ($kind === 'DOCUMENTED_REIMBURSEMENT') {
            $keys += ['third_party_id' => 'RTC_THIRD_PARTY_REIMBURSEMENT_PROOF_MISSING',
                'third_party_document_id' => 'RTC_THIRD_PARTY_REIMBURSEMENT_PROOF_MISSING'];
        }
        foreach ($keys as $key => $reason) {
            foreach (['', ' ', 123, false] as $value) {
                foreach (['rtc', 'integrated'] as $runner) {
                    $rows[] = [$kind, $key, $value, $reason, $runner];
                }
            }
        }
    }

    return $rows;
})());

it('keeps exact arithmetic for decimal strings and integers', function (mixed $amount, string $base, string $runner): void {
    [$k, $s] = material119Input(overrides: ['rtc.base_adjustments' => [
        ['kind' => 'INCLUDED_CHARGE', 'amount' => $amount, 'evidence_id' => 'EV119'],
    ]]);
    $r = material119Evaluate($k, $s, $runner);
    expect($r['status'])->toBe($runner === 'rtc' ? 'VALID' : 'VALID_WITH_WARNINGS')
        ->and(material119Decision($r, $runner)['base']['taxable'])->toBe($base);
})->with([[100, '1100.00'], ['100.00', '1100.00'], ['0.01', '1000.01'],
    ['9007199254740993.00', '9007199254741993.00']])->with(['rtc', 'integrated']);

it('requires unique explicit line identities without silently dropping rows', function (string $case, string $runner): void {
    $row = ['kind' => 'UNCONDITIONAL_DISCOUNT', 'amount' => '100.00', 'evidence_id' => 'EV119',
        'document_id' => 'dps-1', 'depends_on_later_event' => false];
    $first = $second = $row;
    if ($case !== 'missing') {
        $first['line_reference'] = '1';
        $second['line_reference'] = $case === 'distinct' ? '2' : ' 1 ';
    }
    if ($case === 'mixed') {
        unset($second['line_reference']);
    }
    [$k, $s] = material119Input(overrides: ['rtc.base_adjustments' => [$first, $second]]);
    $r = material119Evaluate($k, $s, $runner);
    if ($case === 'distinct') {
        $decision = material119Decision($r, $runner);
        expect($decision['base']['taxable'])->toBe('800.00')->and($decision['base']['adjustments'])->toHaveCount(2);
    } else {
        expect($r['reason_codes'])->toContain('RTC_ADJUSTMENT119_LINE_IDENTITY_AMBIGUOUS')->and($r['decision'])->toBe([]);
    }
})->with(['missing', 'repeated', 'mixed', 'distinct'])->with(['rtc', 'integrated']);

it('qualifies declared collection references without proving extinction', function (?string $basis, string $reason, string $runner): void {
    [$k, $s] = material119Input('acquirer', ['rtc.collection_legal_basis' => $basis]);
    $r = material119Evaluate($k, $s, $runner);
    if ($reason !== 'RESOLVED') {
        expect($r['reason_codes'])->toContain($reason)->and($r['decision'])->toBe([]);
    } else {
        $collection = material119Decision($r, $runner)['collection'];
        expect($collection['eligible_payer'])->toBe($s['facts']['rtc.acquirer_id'])
            ->and($collection['extinction_status'])->toBe('UNCONFIRMED');
    }
})->with([['LC214 art. 36', 'RESOLVED'], ['LC214_ART36', 'RESOLVED'], ['LC214.2025.ART36', 'RESOLVED'],
    [null, 'RTC_COLLECTION_BASIS_MISSING'], [' ', 'RTC_COLLECTION_BASIS_MISSING'],
    ['LC214 arts. 31-35', 'RTC_COLLECTION119_BASIS_METHOD_CONFLICT'],
    ['SYNTHETIC-UNRELATED-BASIS', 'RTC_COLLECTION119_BASIS_NOT_QUALIFIED']])->with(['rtc', 'integrated']);

it('cannot remove or change119 policy even when transport hashes are recomputed', function (string $mutation, string $runner): void {
    [$k, $s] = material119Input();
    $codes = &$k['documents']['rtc_code_catalog'];
    if ($mutation === 'missing') {
        unset($codes['material_input_policy119']);
    } else {
        $codes['material_input_policy119'][$mutation] = 'CORRUPTED';
    }
    unset($codes);
    $hash = CanonicalJson::hash($k['documents']['rtc_code_catalog']);
    $k['content_hashes']['rtc_code_catalog'] = $hash;
    $k['documents']['bundle_manifest']['content_hashes']['rtc_code_catalog'] = $hash;
    $k['content_hashes']['bundle_manifest'] = CanonicalJson::hash($k['documents']['bundle_manifest']);
    $r = material119Evaluate($k, $s, $runner);
    expect($r['reason_codes'])->toContain('RTC_INPUT119_SOURCE_POLICY_NOT_BOUND')->and($r['decision'])->toBe([]);
})->with(['missing', 'source', 'adjustments', 'collection_references', 'available_at'])->with(['rtc', 'integrated']);

it('preserves known historical defects rather than rewriting old knowledge', function (string $version, string $runner): void {
    [$k, $s] = material119Input('acquirer', ['rtc.collection_legal_basis' => 'SYNTHETIC-UNRELATED-BASIS'], $version);
    $r = material119Evaluate($k, $s, $runner);
    expect(material119Decision($r, $runner)['collection']['extinction_status'])->toBe('UNCONFIRMED');
    [$k, $s] = material119Input(overrides: ['rtc.base_adjustments' => [
        ['kind' => 'INCLUDED_CHARGE', 'amount' => true, 'evidence_id' => 'EV119'],
    ]], version: $version);
    $r = material119Evaluate($k, $s, $runner);
    expect(material119Decision($r, $runner)['base']['taxable'])->toBe('1001.00');
})->with(['112', '116'])->with(['rtc', 'integrated']);

it('preserves prior material guards after119 input qualification', function (array $row, array $facts, string $reason, string $runner): void {
    [$k, $s] = material119Input(overrides: ['rtc.base_adjustments' => [$row], ...$facts]);
    $r = material119Evaluate($k, $s, $runner);
    expect($r['reason_codes'])->toContain($reason)->and($r['decision'])->toBe([]);
})->with([
    [['kind' => 'INCLUDED_CHARGE', 'amount' => '-1.00', 'evidence_id' => 'EV'], [], 'RTC_NEGATIVE_ADJUSTMENT'],
    [['kind' => 'UNKNOWN', 'amount' => '1.00', 'evidence_id' => 'EV'], [], 'RTC_ADJUSTMENT_KIND_UNKNOWN'],
    [['kind' => 'UNCONDITIONAL_DISCOUNT', 'amount' => '1.00', 'evidence_id' => 'EV', 'document_id' => 'dps-1', 'depends_on_later_event' => true], [], 'RTC_UNCONDITIONAL_DISCOUNT_PROOF_MISSING'],
    [['kind' => 'UNCONDITIONAL_DISCOUNT', 'amount' => '1.00', 'evidence_id' => 'EV', 'document_id' => 'WRONG', 'depends_on_later_event' => false], [], 'RTC_UNCONDITIONAL_DISCOUNT_PROOF_MISSING'],
    [['kind' => 'DOCUMENTED_REIMBURSEMENT', 'amount' => '1.00', 'evidence_id' => 'EV', 'third_party_id' => 'TP', 'third_party_document_id' => 'DOC', 'document_in_third_party_name' => false], [], 'RTC_THIRD_PARTY_REIMBURSEMENT_PROOF_MISSING'],
    [['kind' => 'LEGACY_TAX_EXCLUSION', 'amount' => '1.00', 'evidence_id' => 'EV', 'tax' => 'ISS'], [], 'RTC_LEGACY_TAX_EXCLUSION_DOUBLE_COUNT'],
    [['kind' => 'LEGACY_TAX_EXCLUSION', 'amount' => '1.00', 'evidence_id' => 'EV', 'tax' => 'UNKNOWN'], ['rtc.legacy_tax_inclusion' => 'INCLUDED'], 'RTC_LEGACY_TAX_EXCLUSION_INVALID'],
])->with(['rtc', 'integrated']);

it('does not replace instrument eligibility or official deployment with a recognized reference', function (array $facts, string $reason, string $runner): void {
    [$k, $s] = material119Input('acquirer', $facts);
    $r = material119Evaluate($k, $s, $runner);
    expect($r['reason_codes'])->toContain($reason)->and($r['decision'])->toBe([]);
})->with([
    [['rtc.payment_instrument_split_capable' => true], 'RTC_ACQUIRER_COLLECTION_CONDITIONS_CONFLICT'],
    [['rtc.acquirer_regime' => 'SIMPLES_NACIONAL'], 'RTC_ACQUIRER_COLLECTION_CONDITIONS_CONFLICT'],
    [['rtc.payment_instrument_evidence_id' => null], 'RTC_PAYMENT_INSTRUMENT_UNPROVEN'],
    [['rtc.collection_method' => 'SPLIT_PAYMENT', 'rtc.collection_legal_basis' => 'LC214 arts. 31-35', 'rtc.payment_instrument_split_capable' => true], 'RTC_SPLIT_OFFICIAL_DEPLOYMENT_NOT_PROVEN'],
])->with(['rtc', 'integrated']);

it('does not force an importer into an ordinary acquirer art36 declaration', function (string $runner): void {
    [$k, $s] = material119Input('import');
    $r = material119Evaluate($k, $s, $runner);
    expect(implode('|', $r['reason_codes']))->not->toContain('RTC_COLLECTION119_')
        ->and(implode('|', $r['reason_codes']))->not->toContain('RTC_COLLECTION_BASIS_MISSING');
    // Positive route witness: the existing import nominal stage is still reached, not an earlier unrelated stop.
    $trace = json_encode($r, JSON_THROW_ON_ERROR);
    expect($trace)->toContain('IMPORT_NOMINAL_CALCULATION')->toContain('RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN');
})->with(['rtc', 'integrated']);

it('keeps geography mandatory119 even when both inherited geography fields are removed', function (string $runner): void {
    [$k, $s] = material119Input();
    unset($k['documents']['rtc_code_catalog']['observed_geographic_reference'], $k['documents']['rtc_code_catalog']['observed_geographic_reference_policy']);
    $hash = CanonicalJson::hash($k['documents']['rtc_code_catalog']);
    $k['content_hashes']['rtc_code_catalog'] = $hash;
    $k['documents']['bundle_manifest']['content_hashes']['rtc_code_catalog'] = $hash;
    $k['content_hashes']['bundle_manifest'] = CanonicalJson::hash($k['documents']['bundle_manifest']);
    $r = material119Evaluate($k, $s, $runner);
    expect($r['reason_codes'])->toContain('GEOGRAPHY115_SOURCE_POLICY_NOT_BOUND')->and($r['decision'])->toBe([]);
})->with(['rtc', 'integrated']);
