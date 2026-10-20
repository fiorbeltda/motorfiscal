<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__);
$folder = $root.'/storage/app/phase4b-advance91-integration-20261015';
$scenarios = $folder.'/scenarios';
if (is_dir($scenarios) || is_file($folder.'/advance91-case-manifest.json')) {
    throw new RuntimeException('Exclusive fixtures already exist');
}
mkdir($scenarios);
$read = static fn (string $p): array => json_decode((string) file_get_contents($p), true, 512, JSON_THROW_ON_ERROR);
$save = static function (string $path, array $value): void {
    $stream = fopen($path, 'xb');
    if ($stream === false) {
        throw new RuntimeException('Exclusive artifact cannot be created');
    }
    fwrite($stream, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    fclose($stream);
};
$bundle = $read($root.'/resources/fiscal/bundles/consumption-phase4b-2026.10.15-91-draft1.json');
$old = $read($root.'/storage/app/phase4b-advance88-cli-20261015/past-payment-without-completed-supply-rtc-snapshot.json');
$factory = new FiscalEngineFactory;
$runner = new RtcScenarioRunService($factory);
$make = static function (array $snapshots) use ($old, $bundle): array {
    $input = $old['input'];
    $input['rule_bundle_id'] = $bundle['bundle_id'];
    $input['catalog_versions'] = $bundle['catalog_versions'];
    $input['knowledge_at'] = '2026-10-21T23:00:00+00:00';
    $facts = &$input['facts'];
    unset($facts['rtc.assess_advance_origin'], $facts['rtc.advance_origin_contexts'], $facts['rtc.tax_point_installment_id'], $facts['rtc.operation_amount_reconciliation_id']);
    $facts['rtc.gross_amount'] = '1000.00';
    $facts['rtc.operation_date'] = '2026-10-20';
    $facts['rtc.document_id'] = 'SYNTHETIC.FINAL91';
    $facts['rtc.assess_tax_point_events'] = true;
    $facts['rtc.reconcile_recorded_advances'] = true;
    $supply = ['operation_id' => 'OP87', 'agreement_id' => 'AG87', 'supplier_id' => $facts['rtc.supplier_id'],
        'document_id' => $facts['rtc.document_id'], 'kind' => 'INTANGIBLE_AVAILABILITY', 'currency' => 'BRL',
        'date' => '2026-10-20', 'observed_on' => '2026-10-21', 'total_base' => '1000.00', 'evidence_id' => 'SYNTHETIC.SUPPLY91'];
    $book = ['contract' => 'LOCAL-PRIOR-ADVANCE-BOOK-89', 'book_id' => 'SYNTHETIC.BOOK91',
        'operation_id' => 'OP87', 'agreement_id' => 'AG87', 'supplier_id' => $facts['rtc.supplier_id'], 'currency' => 'BRL',
        'state' => 'ACTIVE', 'qualification_id' => 'SYNTHETIC.QUALIFICATION91', 'evidence_id' => 'SYNTHETIC.BOOK.EVIDENCE91',
        'source_hash' => hash('sha256', 'SYNTHETIC independent book, not taxpayer evidence'), 'qualified_on' => '2026-10-19',
        'complete_advance_installment_ids' => [], 'entries' => []];
    $captures = [];
    $parts = [];
    foreach ($snapshots as $index => $snapshot) {
        $nominal = array_values(array_filter($snapshot['result']['trace'], static fn (array $r): bool => ($r['stage'] ?? null) === 'ADVANCE_ORIGIN_NOMINAL'))[0];
        $event = $nominal['origin']['event'];
        $id = $event['installment_id'];
        $digest = CanonicalJson::hash($snapshot);
        $book['complete_advance_installment_ids'][] = $id;
        $book['entries'][] = ['installment_id' => $id, 'snapshot_hash' => $digest, 'decision_hash' => $snapshot['result']['decision_hash'],
            'kind' => 'BOOKED_ADVANCE_DEBIT_MEMORY', 'state' => 'ACTIVE', 'registration_id' => 'SYNTHETIC.REG91.'.$index,
            'entry_evidence_id' => 'SYNTHETIC.JOURNAL91.'.$index, 'registered_at' => '2026-10-16T12:00:00+00:00',
            'assessment_period' => '2026-10', 'revoked_at' => null, 'replaced_by' => null, 'consumed_by_final_id' => null,
            'event' => $event, 'taxes' => $nominal['taxes']];
        $captures[] = ['snapshot' => $snapshot, 'snapshot_hash' => $digest];
        $parts[] = array_values(array_filter($snapshot['input']['facts']['rtc.advance_origin_contexts'][0]['paid_installments'],
            static fn (array $part): bool => $part['id'] === $id))[0];
    }
    $facts['rtc.tax_point_contexts'] = [['id' => 'OP87', 'document_id' => $facts['rtc.document_id'], 'mode' => 'ORDINARY',
        'supply_kind' => 'INTANGIBLE_AVAILABILITY', 'public_purchase' => false, 'cancelled' => false, 'total_amount' => '1000.00',
        'actual_supply' => $supply, 'fiscal_document' => null, 'installments' => $parts]];
    $facts['rtc.prior_advance_books'] = [$book];
    $facts['rtc.prior_advance_captures'] = $captures;
    $facts['rtc.recorded_advance_final_contexts'] = [['operation_id' => 'OP87', 'agreement_id' => 'AG87', 'supplier_id' => $facts['rtc.supplier_id'],
        'book_id' => $book['book_id'], 'book_hash' => CanonicalJson::hash($book), 'destination_state' => $facts['rtc.destination_state'],
        'destination_municipality' => $facts['rtc.destination_municipality_ibge'], 'supply_date' => '2026-10-20', 'total_base' => '1000.00',
        'currency' => 'BRL', 'final_registered' => false, 'final_document_id' => $facts['rtc.document_id'],
        'final_decision_id' => 'SYNTHETIC.FINAL.DECISION91', 'supply_kind' => 'INTANGIBLE_AVAILABILITY', 'supply_evidence' => $supply]];

    return $input;
};
$cases = [];
$normal = 'RTC_RECORDED_ADVANCE_DOCUMENT_AND_POSTING_NOT_PROVEN';
$add = static function (string $name, array $input, string $reason, ?string $cbsBalance = null) use (&$cases, $scenarios, $root, $save): void {
    $input['scenario_id'] = 'SYNTHETIC-RECORDED-ADVANCE91-'.$name;
    $path = $scenarios.'/'.$name.'.json';
    $save($path, $input);
    $cases[] = ['case' => $name, 'path' => substr($path, strlen($root) + 1), 'status' => 'ACTION_REQUIRED',
        'reason' => $reason, 'expected_cbs_balance' => $cbsBalance];
};
$base = $make([$old]);
$add('recorded-single', $base, $normal, '6.30');
foreach (['0', '01', '0001'] as $id) {
    $saved = $read($root.'/storage/app/phase4b-advance88-cli-20261015/text-id-'.$id.'-rtc-snapshot.json');
    $add('text-id-'.$id, $make([$saved]), $normal, '6.30');
}
$second = $read($root.'/storage/app/phase4b-advance88-cli-20261015/multiple-selected-rtc-snapshot.json');
$second['input']['facts']['rtc.tax_point_installment_id'] = 'A';
$loaded = $factory->scenarioLoaderFromKnowledge($second['knowledge'])->fromArray($second['input']);
$second['result'] = $runner->evaluate($second['knowledge'], $loaded)->toArray();
$multiple = $make([$old, $second]);
$add('recorded-multiple', $multiple, $normal, '3.60');
$permuted = $multiple;
$permuted['facts']['rtc.prior_advance_captures'] = array_reverse($permuted['facts']['rtc.prior_advance_captures']);
$permuted['facts']['rtc.prior_advance_books'][0]['entries'] = array_reverse($permuted['facts']['rtc.prior_advance_books'][0]['entries']);
$permuted['facts']['rtc.tax_point_contexts'][0]['installments'] = array_reverse($permuted['facts']['rtc.tax_point_contexts'][0]['installments']);
$permuted['facts']['rtc.recorded_advance_final_contexts'][0]['book_hash'] = CanonicalJson::hash($permuted['facts']['rtc.prior_advance_books'][0]);
$add('recorded-multiple-permuted', $permuted, $normal, '3.60');
$negative = [
    ['opt-in-absent', 'RTC_TEMPORAL_PRIOR_ADVANCE_DEBITS_NOT_PROVEN'],
    ['temporal-opt-in-absent', 'RTC_RECORDED_ADVANCE_FINAL_CONTEXT_REQUIRED'],
    ['missing-final', 'RTC_RECORDED_ADVANCE_SINGLE_FINAL_BOOK_AND_CAPTURE_SET_REQUIRED'],
    ['missing-book', 'RTC_RECORDED_ADVANCE_SINGLE_FINAL_BOOK_AND_CAPTURE_SET_REQUIRED'],
    ['missing-captures', 'RTC_RECORDED_ADVANCE_SINGLE_FINAL_BOOK_AND_CAPTURE_SET_REQUIRED'],
    ['wrong-final-gross', 'RTC_RECORDED_ADVANCE_FINAL_FACT_BINDING_INVALID'],
    ['wrong-final-supplier', 'RTC_RECORDED_ADVANCE_FINAL_FACT_BINDING_INVALID'],
    ['wrong-final-destination', 'RTC_RECORDED_ADVANCE_FINAL_FACT_BINDING_INVALID'],
    ['wrong-qualified-supply', 'RTC_RECORDED_ADVANCE_QUALIFIED_SUPPLY_BINDING_INVALID'],
    ['wrong-declared-set', 'RTC_RECORDED_ADVANCE_EVENT_SET_BINDING_INVALID'],
    ['wrong-record-date', 'RTC_RECORDED_ADVANCE_PLAN_VALUE_OR_DATE_CONFLICT'],
    ['wrong-record-proof', 'RTC_RECORDED_ADVANCE_PLAN_EVIDENCE_CONFLICT'],
    ['wrong-book-owner', 'RTC_RECORDED_ADVANCE_PRIOR_BOOK_OWNER_OPERATION_BINDING_INVALID'],
    ['record-consumed', 'RTC_RECORDED_ADVANCE_PRIOR_PRIOR_RECORD_LIFECYCLE_NOT_OPEN'],
    ['bad-snapshot-hash', 'RTC_RECORDED_ADVANCE_PRIOR_SNAPSHOT_HASH_OR_UNIQUENESS_INVALID'],
    ['tampered-body', 'RTC_RECORDED_ADVANCE_PRIOR_SNAPSHOT_RESULT_BODY_HASH_MISMATCH'],
    ['duplicate-capture', 'RTC_RECORDED_ADVANCE_PRIOR_COMPLETE_RECORD_AND_SNAPSHOT_SET_REQUIRED'],
    ['final-already-registered', 'RTC_RECORDED_ADVANCE_PRIOR_FINAL_SUPPLY_CONTEXT_UNPROVEN'],
    ['wrong-period', 'RTC_RECORDED_ADVANCE_PRIOR_PRIOR_RECORD_DATE_OR_PERIOD_UNPROVEN'],
    ['base-transformation', 'RTC_TAX_POINT_EVENT_BASE_OR_RATE_TRANSFORMATION_UNPROVEN'],
    ['public-context', 'RTC_TAX_POINT_PUBLIC_OR_CANCELLED_CONTEXT_REQUIRES_SEPARATE_TREATMENT'],
    ['future-cbs-unavailable', 'RTC_RECORDED_ADVANCE_RTC_RATE_NOT_PROVEN:CBS'],
];
foreach ($negative as [$name, $reason]) {
    $input = $base;
    $facts = &$input['facts'];
    switch ($name) {
        case 'opt-in-absent': unset($facts['rtc.reconcile_recorded_advances']);
            break;
        case 'temporal-opt-in-absent': $facts['rtc.assess_tax_point_events'] = false;
            break;
        case 'missing-final': $facts['rtc.recorded_advance_final_contexts'] = [];
            break;
        case 'missing-book': $facts['rtc.prior_advance_books'] = [];
            break;
        case 'missing-captures': $facts['rtc.prior_advance_captures'] = [];
            break;
        case 'wrong-final-gross': $facts['rtc.recorded_advance_final_contexts'][0]['total_base'] = '999.99';
            break;
        case 'wrong-final-supplier': $facts['rtc.recorded_advance_final_contexts'][0]['supplier_id'] = 'OTHER';
            break;
        case 'wrong-final-destination': $facts['rtc.recorded_advance_final_contexts'][0]['destination_municipality'] = '3550308';
            break;
        case 'wrong-qualified-supply': $facts['rtc.recorded_advance_final_contexts'][0]['supply_evidence']['evidence_id'] = 'OTHER';
            break;
        case 'wrong-declared-set': $facts['rtc.prior_advance_books'][0]['complete_advance_installment_ids'] = ['OTHER'];
            break;
        case 'wrong-record-date': $facts['rtc.prior_advance_books'][0]['entries'][0]['event']['rate_date'] = '2026-10-08';
            break;
        case 'wrong-record-proof': $facts['rtc.prior_advance_books'][0]['entries'][0]['event']['payment_evidence_id'] = 'OTHER';
            break;
        case 'wrong-book-owner': $facts['rtc.prior_advance_books'][0]['supplier_id'] = 'OTHER';
            break;
        case 'record-consumed': $facts['rtc.prior_advance_books'][0]['entries'][0]['consumed_by_final_id'] = 'OTHER';
            break;
        case 'bad-snapshot-hash': $facts['rtc.prior_advance_captures'][0]['snapshot_hash'] = str_repeat('0', 64);
            break;
        case 'tampered-body':
            $capture = &$facts['rtc.prior_advance_captures'][0];
            $capture['snapshot']['result']['trace'][0]['known_at'] = '2099-01-01T00:00:00+00:00';
            $capture['snapshot_hash'] = CanonicalJson::hash($capture['snapshot']);
            $facts['rtc.prior_advance_books'][0]['entries'][0]['snapshot_hash'] = $capture['snapshot_hash'];
            unset($capture);
            break;
        case 'duplicate-capture': $facts['rtc.prior_advance_captures'][] = $facts['rtc.prior_advance_captures'][0];
            break;
        case 'final-already-registered': $facts['rtc.recorded_advance_final_contexts'][0]['final_registered'] = true;
            break;
        case 'wrong-period': $facts['rtc.prior_advance_books'][0]['entries'][0]['assessment_period'] = '2026-11';
            break;
        case 'base-transformation': $facts['rtc.base_adjustments'] = [['kind' => 'INCLUDED_CHARGE', 'amount' => '1.00', 'evidence_id' => 'SYNTHETIC.CHARGE91']];
            break;
        case 'public-context': $facts['customer.person_type'] = 'PUBLIC_ADMINISTRATION';
            break;
        case 'future-cbs-unavailable':
            $input['knowledge_at'] = '2027-01-10T23:00:00+00:00';
            $facts['rtc.operation_date'] = '2027-01-02';
            $facts['rtc.tax_point_contexts'][0]['actual_supply']['date'] = '2027-01-02';
            $facts['rtc.tax_point_contexts'][0]['actual_supply']['observed_on'] = '2027-01-03';
            $facts['rtc.recorded_advance_final_contexts'][0]['supply_date'] = '2027-01-02';
            $facts['rtc.recorded_advance_final_contexts'][0]['supply_evidence'] = $facts['rtc.tax_point_contexts'][0]['actual_supply'];
            break;
    }
    if ($facts['rtc.prior_advance_books'] !== [] && $facts['rtc.recorded_advance_final_contexts'] !== []) {
        $facts['rtc.recorded_advance_final_contexts'][0]['book_hash'] = CanonicalJson::hash($facts['rtc.prior_advance_books'][0]);
    }
    $add($name, $input, $reason);
    unset($facts);
}
$save($folder.'/advance91-case-manifest.json', ['bundle' => 'bundles/consumption-phase4b-2026.10.15-91-draft1.json',
    'cases' => $cases, 'synthetic_only' => true, 'production_ready' => false]);
echo json_encode(['status' => 'ADVANCE91_SYNTHETIC_FIXTURES_CREATED', 'cases' => count($cases)], JSON_THROW_ON_ERROR).PHP_EOL;
