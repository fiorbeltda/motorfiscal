<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\CreditEvidence156;
use App\Modules\Fiscal\ConsumptionReform\LegalBaseReduction156;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

require_once dirname(__DIR__, 3).'/scripts/agent_a/handoff156_fixture.php';

// B24: the installed bundle that activates the A156 handoffs H6/H7 (credit_evidence_a156) in a new version.
const B24_BUNDLE = 'bundles/consumption-phase4b-2026.11.05-b24-draft1.json';
const B24_PREVIOUS = 'bundles/consumption-phase4b-2026.11.04-b23-draft1.json';

/** @return array<string, mixed> */
function b24Manifest(string $path = B24_BUNDLE): array
{
    return json_decode((string) file_get_contents(resource_path('fiscal/'.$path)), true, 512, JSON_THROW_ON_ERROR);
}

/** @return array<string, mixed> */
function b24Evaluate(array $scenario, bool $integrated, string $bundle = B24_BUNDLE): array
{
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), $bundle);
    $scenario['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $scenario['catalog_versions'] = $knowledge['catalog_versions'];
    $scenario['knowledge_at'] = max($scenario['knowledge_at'], b24Manifest($bundle)['knowledge_available_at']);
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($scenario);
    if ($integrated) {
        return $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input)->toArray();
    }
    $documents = $knowledge['documents'];

    return (new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog'])))
        ->evaluate($loaded->input->context->facts, $loaded->input->context->competenceDate, $loaded->input->context->knowledgeAt);
}

/** Book entry declared as documentary input (not synthetic). @return array<string, mixed> */
function b24DocumentaryBook(array $facts): array
{
    $envelope = book156($facts);
    $record = ['source_kind' => 'DOCUMENTARY_INPUT', 'evidence_id' => 'B24.BOOK.EVIDENCE'] + $envelope['record'];

    return ['record' => $record, 'record_hash' => CanonicalJson::hash($record)];
}

/** @return array<string, mixed> */
function b24GeneralRegime(): array
{
    $input = handoff155Input();
    $input['knowledge_at'] = '2026-11-05T13:00:00Z';

    return $input;
}

/** @return array<string, mixed> */
function b24Codes(string $bundle = B24_BUNDLE): array
{
    return (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), $bundle)['documents']['rtc_code_catalog'];
}

it('forks bundle B23 changing only the code catalog, facts, questions and production controls', function (): void {
    $new = b24Manifest();
    $old = b24Manifest(B24_PREVIOUS);
    expect($new['predecessor_bundle_id'])->toBe($old['bundle_id'])
        ->and(array_keys(array_diff_assoc($new['content_hashes'], $old['content_hashes'])))
        ->toEqualCanonicalizing(['rtc_code_catalog', 'fact_definitions', 'questions'])
        ->and(array_diff_assoc($new['catalog_versions'], $old['catalog_versions']))->toBe(['rtc_codes' => '2026.11.05-b24-draft1']);
    $codes = b24Codes();
    $oldCodes = b24Codes(B24_PREVIOUS);
    expect(array_values(array_diff(array_keys($codes), array_keys($oldCodes))))->toBe([CreditEvidence156::KEY]);
    foreach ($oldCodes as $key => $value) {
        if ($key !== 'version') {
            expect(json_encode($codes[$key], JSON_THROW_ON_ERROR))->toBe(json_encode($value, JSON_THROW_ON_ERROR));
        }
    }
    expect(CreditEvidence156::enabled($codes))->toBeTrue()->and(CreditEvidence156::enabled($oldCodes))->toBeFalse()
        ->and($codes)->not->toHaveKey(TemporalRateCatalog::EVIDENCE_KEY)
        ->and($codes)->not->toHaveKey(LegalBaseReduction156::KEY);
});

it('defines the two A156 documentary facts as material ARRAY facts, each with a question', function (): void {
    $documents = (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), B24_BUNDLE)['documents'];
    $asked = array_column($documents['questions'], 'answer_type', 'fact_key');
    foreach (['rtc.credit_book_entries', 'rtc.import_definitive_credit_records'] as $key) {
        expect($documents['fact_definitions'])->toContain(['key' => $key, 'type' => 'ARRAY', 'material' => true])
            ->and($asked[$key] ?? null)->toBe('ARRAY');
    }
    expect(array_column($documents['fact_definitions'], 'key'))->not->toContain('rtc.legal_base_reduction_records');
});

it('keeps the 222001 base reduction out of the NFS-e scope, as the official row states', function (): void {
    $row = b24Codes()['cclasstrib']['222001'];
    expect($row['cst'])->toBe('222')->and($row['legal_reference'])->toBe('Art. 12 § 8º')
        ->and($row['nfse_supported'])->toBeFalse()
        ->and($row['document_and_group_indicators']['indNFSe'])->toBe(0)
        ->and($row['document_and_group_indicators']['indBPe'])->toBe(1);
    foreach (b24Codes()['engine_decision_rules'] as $rule) {
        expect($rule)->not->toHaveKey('legal_base_reduction_family');
    }
});

it('asks for the book entry on B24 where B23 decided the same input credit without it', function (bool $integrated): void {
    $input = b24GeneralRegime();
    $old = b24Evaluate($input, false, B24_PREVIOUS);
    expect($old['status'])->toBe('VALID');
    $new = b24Evaluate($input, $integrated);
    if ($integrated) {
        expect($new['missing_facts'])->toContain('rtc.credit_book_entries')
            ->and(array_filter($new['reason_codes'], fn (string $r): bool => str_starts_with($r, 'QUESTION_UNMAPPED:')))->toBe([]);
    } else {
        expect($new['status'])->toBe('ACTION_REQUIRED')
            ->and($new['reason_codes'])->toBe(['RTC_CREDIT_BOOK_ENTRIES_REQUIRED'])
            ->and($new['missing_facts'])->toBe(['rtc.credit_book_entries']);
    }
})->with([false, true]);

it('reaches the B23 decision on B24 once the documentary book entry links the credit', function (): void {
    $input = b24GeneralRegime();
    $old = b24Evaluate($input, false, B24_PREVIOUS);
    $input['facts']['rtc.credit_book_entries'] = [b24DocumentaryBook($input['facts'])];
    $new = b24Evaluate($input, false);
    expect($new['status'])->toBe('VALID')->and($new)->not->toHaveKey('official');
    $stage = array_values(array_filter($new['trace'], fn (array $s): bool => $s['stage'] === 'CREDIT_DOCUMENT_BOOK156'))[0];
    expect($stage['reason'])->toBe('RTC_CREDIT_DOCUMENT_BOOK_LINKS_QUALIFIED')
        ->and($stage['authenticated'])->toBeFalse()->and($stage['posted'])->toBeFalse();
    $strip = function (array $decision): array {
        unset($decision['catalog_versions']);

        return $decision;
    };
    expect(CanonicalJson::hash($strip($new['decision'])))->toBe(CanonicalJson::hash($strip($old['decision'])))
        ->and($new['decision']['catalog_versions']['codes'])->toBe('2026.11.05-b24-draft1');
});

it('refuses on B24 a book entry that does not match the credit document, holder or amounts', function (string $field, mixed $value): void {
    $input = b24GeneralRegime();
    $book = b24DocumentaryBook($input['facts']);
    $book['record'][$field] = $value;
    $book['record_hash'] = CanonicalJson::hash($book['record']);
    $input['facts']['rtc.credit_book_entries'] = [$book];
    $result = b24Evaluate($input, false);
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'][0])->toBeIn(['RTC_CREDIT_DOCUMENT_NOT_IN_BOOK', 'RTC_CREDIT_BOOK_LINK_CONFLICT']);
})->with([['document_id', 'OTHER.DOC'], ['taxpayer_id', 'OTHER.HOLDER'], ['ibs_amount', '999.99'], ['cbs_amount', '0.01']]);

it('never turns a synthetic book entry into an official decision on B24', function (): void {
    $input = b24GeneralRegime();
    $input['facts']['rtc.credit_book_entries'] = [book156($input['facts'])];
    $result = b24Evaluate($input, false);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'][0])->toBe('RTC_SYNTHETIC_TEST_ONLY')
        ->and($result['official'])->toBeFalse()->and($result['decision'])->toBe([]);
});

it('qualifies on B24 the definitive import document and both extinguished payments before the IMPORT credit', function (): void {
    $input = import156();
    $facts = &$input['facts'];
    $facts['rtc.credit_book_entries'] = [b24DocumentaryBook($facts)];
    $record = ['source_kind' => 'DOCUMENTARY_INPUT', 'evidence_id' => 'B24.IMPORT.EVIDENCE']
        + $facts['rtc.import_definitive_credit_records'][0]['record'];
    $facts['rtc.import_definitive_credit_records'] = [['record' => $record, 'record_hash' => CanonicalJson::hash($record)]];
    unset($facts);
    $result = b24Evaluate($input, false);
    $stages = array_column($result['trace'], 'stage');
    expect($stages)->toContain('CREDIT_DOCUMENT_BOOK156');
    $book = $result['trace'][array_search('CREDIT_DOCUMENT_BOOK156', $stages, true)];
    expect($book['status'])->toBe('RESOLVED')->and($book['import']['contract'])->toBe('IMPORT-CREDIT-A156-V1');
    $without = import156();
    $without['facts']['rtc.credit_book_entries'] = [b24DocumentaryBook($without['facts'])];
    unset($without['facts']['rtc.import_definitive_credit_records']);
    expect(b24Evaluate($without, false)['reason_codes'])->toBe(['RTC_IMPORT_DEFINITIVE_RECORD_REQUIRED']);
});

it('rejects a tampered record hash on B24', function (): void {
    $input = b24GeneralRegime();
    $book = b24DocumentaryBook($input['facts']);
    $book['record']['ibs_amount'] = '1.00';
    $input['facts']['rtc.credit_book_entries'] = [$book];
    expect(b24Evaluate($input, false)['reason_codes'])->toBe(['RTC_CREDIT_BOOK_ENTRY_INVALID']);
});
