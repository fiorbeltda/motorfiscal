"""Allow only explicit correction questions/trace changes; all other old results stay exact."""
from pathlib import Path
from datetime import datetime, timezone
import copy
import hashlib
import json

base = Path(r'D:\ProjetoSaasExports\phase4b-credit122-preparation-20261021')
old_root = base / 'credit-cli-v1'
new_root = base / 'credit-cli-v2'
destination = base / 'question-fix-v2/question-result-comparison.json'


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def sha(path):
    with path.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()


def canonical(value):
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':'))


corrections = {
    'RTC_CREDIT122_EVIDENCE_REQUIRED': ['rtc.credit_adjustments'],
    'RTC_CREDIT122_LINE_REFERENCE_INVALID': ['rtc.credit_adjustments'],
    'RTC_CREDIT122_LINE_IDENTITY_AMBIGUOUS': ['rtc.credit_adjustments'],
    'RTC_CREDIT_ADJUSTMENT_UNPROVEN': ['rtc.credit_adjustments'],
    'RTC_CREDIT_TAX_UNKNOWN': ['rtc.credit_adjustments'],
    'RTC_NEGATIVE_CREDIT_ADJUSTMENT': ['rtc.credit_adjustments'],
    'RTC_CREDIT_ADJUSTMENT_EXCEEDS_AVAILABLE': ['rtc.credit_adjustments'],
    'RTC_NEGATIVE_INPUT_CREDIT': ['rtc.input_ibs_amount', 'rtc.input_cbs_amount'],
}


def normalize_guard_trace(trace, reason, required):
    output = []
    for row in trace:
        if row.get('stage') == 'CREDIT_CORRECTION122':
            assert set(row) == {'stage', 'reason', 'missing_facts', 'credit_granted'}
            assert row['reason'] == reason and row['missing_facts'] == required and row['credit_granted'] is False
            continue
        if row.get('stage') == 'CREDIT_INPUT122' and row['status'] == 'ACTION_REQUIRED':
            assert row['reason'] == reason and row['missing_facts'] == required
            row['missing_facts'] = []
        output.append(row)
    return output


def normalize_corrected(result, runner, reason, required):
    result = copy.deepcopy(result)
    result.pop('decision_hash')
    assert result['missing_facts'] == required
    result['missing_facts'] = []
    assert {question['fact_key'] for question in result['questions']} == set(required)
    result['questions'] = []
    if runner == 'rtc':
        result['trace'] = normalize_guard_trace(result['trace'], reason, required)
    else:
        for row in result['trace']:
            if row['type'] == 'CONSUMPTION_REFORM':
                payload = row['payload']
                assert payload['missing_facts'] == required
                payload['missing_facts'] = []
                payload['trace'] = normalize_guard_trace(payload['trace'], reason, required)
    return result


assert not destination.exists(), 'Exclusive result comparison already exists'
old_report = old_root / 'credit122-cli-validation.json'
new_report = new_root / 'credit122-cli-validation.json'
assert sha(old_report) == '7f61754e048ebe1ee92e68f852e0417a45daf31a4beffcc08fec841daa97bd59'
before = read(old_report)
after = read(new_report)
assert before['native_decisions'] == before['exact_replays'] == 176
assert after['native_decisions'] == after['exact_replays'] == 180
assert after['input_inventory_unchanged'] is True and after['correction_question_checks'] > 0
new_cases = {row['name']: row for row in after['cases']}
assert len(before['cases']) == 88 and len(new_cases) == 90
records = []
for prior in before['cases']:
    current = new_cases[prior['name']]
    for runner in ['rtc', 'integrated']:
        old_path = old_root / prior[runner]['snapshot']
        new_path = new_root / current[runner]['snapshot']
        assert sha(old_path) == prior[runner]['snapshot_sha256']
        assert sha(new_path) == current[runner]['snapshot_sha256']
        old_snapshot = read(old_path)
        new_snapshot = read(new_path)
        assert canonical(old_snapshot['input']) == canonical(new_snapshot['input']), prior['name']
        a, b = old_snapshot['result'], new_snapshot['result']
        assert a['status'] == b['status'] and a['reason_codes'] == b['reason_codes'], prior['name']
        assert canonical(a['decision']) == canonical(b['decision']), prior['name']
        if canonical(a) == canonical(b):
            change = 'ENTIRE_RESULT_IDENTICAL'
        else:
            reason = a['reason_codes'][0]
            assert a['status'] == 'ACTION_REQUIRED' and reason in corrections, prior['name']
            assert a['missing_facts'] == [] and a['questions'] == [], prior['name']
            original = copy.deepcopy(a)
            original.pop('decision_hash')
            normalized = normalize_corrected(b, runner, reason, corrections[reason])
            assert canonical(original) == canonical(normalized), (prior['name'], runner, 'unexpected non-question result/trace change')
            change = 'ONLY_DECLARED_CORRECTION_QUESTIONS_AND_TRACE_CHANGED'
        records.append({'case': prior['name'], 'runner': runner, 'classification': change,
                        'old_snapshot_sha256': sha(old_path), 'new_snapshot_sha256': sha(new_path)})
assert len(records) == 176
report = {
    'created_at': datetime.now(timezone.utc).isoformat(),
    'status': 'CREDIT122_BUSINESS_RESULTS_UNCHANGED_ONLY_CORRECTION_QUESTIONS_AND_EXPLICIT_TRACE_CHANGED',
    'compared_prior_scenarios': 88, 'compared_prior_results': 176,
    'entire_results_identical': sum(row['classification'] == 'ENTIRE_RESULT_IDENTICAL' for row in records),
    'declared_question_corrections': sum(row['classification'] != 'ENTIRE_RESULT_IDENTICAL' for row in records),
    'all_prior_inputs_identical': True, 'all_prior_statuses_reasons_and_decisions_identical': True,
    'new_negative_input_cases': sorted(set(new_cases) - {row['name'] for row in before['cases']}),
    'old_corpus_sha256': sha(old_report), 'new_corpus_sha256': sha(new_report), 'records': records,
    'full122_acceptance': False, 'phase4b_complete': False, 'production_ready': False,
}
with destination.open('x', encoding='utf-8') as stream:
    json.dump(report, stream, ensure_ascii=False, indent=2)
print('PASS:176 prior inputs/statuses/reasons/decisions preserved;only declared correction questions/trace changes')
