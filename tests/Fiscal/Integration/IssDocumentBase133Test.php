<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\ISS\IssDocumentBase133;
use Illuminate\Support\Facades\Artisan;

require_once dirname(__DIR__, 3).'/scripts/phase4b_iss133_fixture.php';

it('follows the fresh municipal result even when RTC roles imply the opposite treatment', function (): void {
    $c = phase4bIss133Fixture(reverseResults: true);
    $r = (new IssDocumentBase133)->calculate($c['input111'], $c['proofs'], $c['knowledge_at'], $c['policy']);
    expect($r['status'])->toBe('LOCAL_ISS_DOCUMENT_BASES_CALCULATED')
        ->and($r['documents'][0]['role'])->toBe('RTC_FOREIGN')->and($r['documents'][0]['iss_base_brl'])->toBe('1000.00')
        ->and($r['documents'][0]['iss_tax_brl'])->toBe('29.00')
        ->and($r['documents'][1]['role'])->toBe('RTC_DOMESTIC')->and($r['documents'][1]['iss_base_brl'])->toBeNull()
        ->and($r['documents'][1]['iss_tax_brl'])->toBeNull();
});

it('calculates independent document bases with source-bound municipal rate and null non-incidence amounts', function (string $price, string $tax): void {
    $c = phase4bIss133Fixture(amount: $price);
    $before = CanonicalJson::hash($c);
    $result = (new IssDocumentBase133)->calculate($c['input111'], $c['proofs'], $c['knowledge_at'], $c['policy']);
    expect($result['status'])->toBe('LOCAL_ISS_DOCUMENT_BASES_CALCULATED')
        ->and($result['documents'][0]['iss_base_brl'])->toBeNull()->and($result['documents'][0]['iss_tax_brl'])->toBeNull()
        ->and($result['documents'][1]['iss_base_brl'])->toBe($price)->and($result['documents'][1]['iss_tax_brl'])->toBe($tax)
        ->and($result['documents'][1]['iss_rate_percent'])->toBe('2.90')
        ->and($result['documents'][1]['municipal_code'])->toBe('02660')
        ->and($result['rtc_allocation_used_as_iss_base'])->toBeFalse()
        ->and($result['legal_applicability_approved'])->toBeFalse()
        ->and($result['government_evidence_authenticated'])->toBeFalse()
        ->and($result['municipal_rounding_approved'])->toBeFalse()
        ->and($result['production_ready'])->toBeFalse()
        ->and(CanonicalJson::hash($c))->toBe($before)
        ->and((new IssDocumentBase133)->calculate($c['input111'], $c['proofs'], $c['knowledge_at'], $c['policy']))->toBe($result);
})->with([['1000.00', '29.00'], ['0.01', '0.00'], ['4.31', '0.12'], ['90071992547409.93', '2612087783874.89']]);

it('distinguishes a fully discounted taxable zero base from non-incidence', function (): void {
    $c = phase4bIss133Fixture('021-zero-iss-price-gross-before-all-discounts');
    $r = (new IssDocumentBase133)->calculate($c['input111'], $c['proofs'], $c['knowledge_at'], $c['policy']);
    expect($r['status'])->toBe('LOCAL_ISS_DOCUMENT_BASES_CALCULATED')
        ->and($r['documents'][0]['iss_base_brl'])->toBeNull()
        ->and($r['documents'][1]['iss_base_brl'])->toBe('0.00')
        ->and($r['documents'][1]['iss_tax_brl'])->toBe('0.00')
        ->and($r['documents'][1]['iss_rate_percent'])->toBe('2.90');
});

it('requires an ISS-specific FX contract for a taxable foreign currency price', function (string $seed): void {
    $c = phase4bIss133Fixture($seed);
    $r = (new IssDocumentBase133)->calculate($c['input111'], $c['proofs'], $c['knowledge_at'], $c['policy']);
    expect($r['reason'])->toBe('ISS133_OWN_ISS_FX_CONTRACT_REQUIRED')->and($r['iss_base_calculated'])->toBeFalse();
})->with(['036-dated-variation-12', '037-dated-variation-13', '038-dated-variation-14']);

it('preserves the composed price including conditional discounts instead of the RTC allocation', function (): void {
    $c = phase4bIss133Fixture('009-both-gross-before-all-discounts');
    $r = (new IssDocumentBase133)->calculate($c['input111'], $c['proofs'], $c['knowledge_at'], $c['policy']);
    expect($r['status'])->toBe('LOCAL_ISS_DOCUMENT_BASES_CALCULATED');
    $doc = $r['documents'][1];
    expect($doc['iss_base_brl'])->toBe($doc['price_for_iss_evaluation_in_input_currency'])
        ->and($doc['iss_base_brl'])->not->toBe($doc['payment_candidate_if_conditional_terms_satisfied'])
        ->and($r['conditional_discount_subtracted'])->toBeFalse();
});

it('refuses changed material context even when its synthetic proof is consistently recaptured', function (string $field, mixed $value): void {
    $c = phase4bIss133Fixture();
    $c['proofs'][1]['value'][$field] = $value;
    $c['proofs'][1] = price112Capture($c['proofs'][1], 'synthetic://iss133/changed');
    $r = (new IssDocumentBase133)->calculate($c['input111'], $c['proofs'], $c['knowledge_at'], $c['policy']);
    expect($r['reason'])->toBe('ISS133_GRANULAR_CLASSIFICATION_OR_TAX_CONTEXT_CONFLICT')
        ->and($r['iss_base_calculated'])->toBeFalse();
})->with([
    ['lc116_subitem', '1'], ['lc116_subitem', '01.04'], ['municipal_code', '02692'], ['municipal_code', '2660'],
    ['document_reference', 'OTHER'], ['contract_reference', 'OTHER'], ['unit_reference', 'OTHER'], ['line_reference', 'OTHER'],
    ['supplier_id', 'OTHER'], ['acquirer_id', 'OTHER'], ['municipality', '3509502'], ['iss_supply_on', '2026-09-26'],
    ['supplier_person_type', 'PF'], ['supplier_iss_regime', 'SIMPLES_NACIONAL'], ['ordinary_taxation_without_benefit', false],
    ['special_base_rule', true], ['iss_included_in_price', false], ['price111_binding_hash', str_repeat('0', 64)],
    ['dated106_binding_hash', str_repeat('0', 64)], ['source_policy_hash', str_repeat('0', 64)],
]);

it('refuses missing evidence revoked evidence stale source knowledge and a modified price', function (string $case, string $reason): void {
    $c = phase4bIss133Fixture();
    switch ($case) {
        case 'missing': array_pop($c['proofs']);
            break;
        case 'duplicate': $c['proofs'][1] = $c['proofs'][0];
            break;
        case 'revoked': $c['proofs'][1]['revoked_at'] = $c['knowledge_at'];
            break;
        case 'superseded': $c['proofs'][1]['superseded_at'] = $c['knowledge_at'];
            break;
        case 'future': $c['proofs'][1]['observed_at'] = '2026-10-22T18:21:00+00:00';
            break;
        case 'dependency': $c['proofs'][1]['observed_at'] = '2026-10-22T18:19:23+00:00';
            break;
        case 'coverage': $c['proofs'][1]['valid_to'] = '2026-09-24';
            break;
        case 'reuse': $c['proofs'][1]['evidence_id'] = $c['input111']['input110']['facts']['provider.iss_regime_proof']['evidence_id'];
            break;
        case 'hash': $c['proofs'][1]['source_hash'] = str_repeat('0', 64);
            break;
        case 'price': $c['input111']['records'][1]['principal'] = '999.00';
            break;
        case 'knowledge': $c['knowledge_at'] = '2026-10-22T18:19:21+00:00';
            break;
        case 'policy': $c['policy']['rate_percent'] = '2.00';
            break;
    }
    if (in_array($case, ['revoked', 'superseded', 'future', 'dependency', 'coverage', 'reuse'], true)) {
        $c['proofs'][1] = price112Capture($c['proofs'][1], 'synthetic://iss133/lifecycle');
    }
    $r = (new IssDocumentBase133)->calculate($c['input111'], $c['proofs'], $c['knowledge_at'], $c['policy']);
    expect($r['reason'])->toBe('ISS133_'.$reason)->and($r['iss_base_calculated'])->toBeFalse();
})->with([
    ['missing', 'TWO_DOCUMENT_TAX_CONTEXT_PROOFS_REQUIRED'], ['duplicate', 'DISTINCT_CURRENT_DOCUMENT_PROOFS_REQUIRED'],
    ['revoked', 'TAX_CONTEXT_REVOKED_OR_SUPERSEDED'], ['superseded', 'TAX_CONTEXT_REVOKED_OR_SUPERSEDED'],
    ['future', 'TAX_CONTEXT_NOT_KNOWN'], ['coverage', 'TAX_CONTEXT_DOES_NOT_COVER_ISS_DATE'],
    ['dependency', 'TAX_CONTEXT_PRECEDES_PRICE_EVIDENCE'],
    ['reuse', 'DISTINCT_TAX_CONTEXT_EVIDENCE_REQUIRED'], ['hash', 'TAX_CONTEXT_ENVELOPE_HASH_CONFLICT'],
    ['price', 'FRESH_COMPOSITION_REQUIRED'], ['knowledge', 'POLICY_NOT_KNOWN'], ['policy', 'EXACT_SOURCE_POLICY_REQUIRED'],
]);

it('executes the headless command with verified original sources', function (): void {
    $path = sys_get_temp_dir().'/iss133-'.bin2hex(random_bytes(6)).'.json';
    file_put_contents($path, json_encode(phase4bIss133Fixture(), JSON_THROW_ON_ERROR));
    try {
        expect(Artisan::call('fiscal:iss:document-base', ['input' => $path]))->toBe(0);
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        expect($result['status'])->toBe('LOCAL_ISS_DOCUMENT_BASES_CALCULATED')
            ->and($result['documents'][1]['iss_tax_brl'])->toBe('29.00')
            ->and($result['document_obligation_complete'])->toBeFalse();
    } finally {
        unlink($path);
    }
});
