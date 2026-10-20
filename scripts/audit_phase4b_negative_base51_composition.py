"""Verify native composition, economic preservation and successor synthetic witnesses."""
from pathlib import Path
import hashlib
import json
import re
from dere_daily_memory_oracle import digest

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base51-current-bases-preparation-20261009'
P = ROOT / 'storage/app/phase4b-negative-base50-closing-draft-20261009'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def ref(path):
    return {'path': path.relative_to(ROOT).as_posix(), 'sha256': sha(path)}


prior_reports = [P / 'command-development-validation.json', E / 'current-base-development-validation.json']
for path in prior_reports:
    record = read(path)
    for item in record['implementation']:
        assert sha(ROOT / item['path']) == item['sha256'], item['path']
quality = []
for label in ['composition-final-tests', 'composition-final-pint', 'composition-final-phpstan',
              'composition-native-final-capture', 'composition-native-final-replay']:
    path = E / (label + '.json')
    record = read(path)
    assert record['exit_code'] == 0 and record['log_sha256'] == sha(E / (label + '.log'))
    quality.append(ref(path))
log = re.sub(r'\x1b\[[0-9;]*m', '', (E / 'composition-final-tests.log').read_text(encoding='utf-8'))
assert '25 passed' in log and '60 assertions' in log
capture = read(E / 'composition-native-result.json')
assert capture['original_snapshot_sha256'] == sha(P / 'command-financial-snapshot.json')
assert capture['financial_replay_matches'] is True
assert capture['monthly_consumption_integrated'] is False and capture['production_ready'] is False
snapshot = read(P / 'command-financial-snapshot.json')
stages = {row['stage']: row for row in snapshot['result']['trace']}
old = read(P / 'command-month-input.json')
old_book = {k: v for k, v in old['book'].items() if k != 'pgcc_source_hash'}
checks = 0
for name, case in capture['cases'].items():
    source = case['input']
    result = case['result']
    month = source['month']
    assert source['operation'] == stages['FINANCIAL_OPERATION_AND_BASE']
    assert source['gross'] == stages['FINANCIAL_GROSS_DEBITS']
    assert result['operation_hash'] == digest(source['operation'])
    assert result['monthly_input_hash'] == digest(month)
    assert result['gross_tax_hash'] == digest(source['gross'])
    assert result['status'] == 'LOCAL_MONTHLY_CURRENT_BASE_COMPOSITION_RECONCILED'
    assert result['composition_locally_reconciled'] is True
    assert result['classification_external_verified'] is False
    assert result['negative_base_consumption_applied'] is False and result['production_ready'] is False
    assert {k: v for k, v in month['book'].items() if k != 'pgcc_source_hash'} == old_book
    assert month['pgcc']['source_hash'] == digest({k: v for k, v in month['pgcc'].items() if k != 'source_hash'})
    assert month['book']['pgcc_source_hash'] == month['prerequisites']['pgcc_source_hash'] == month['pgcc']['source_hash']
    for before, after in zip(old['securities_realization_book'], month['securities_realization_book'], strict=True):
        assert {k: v for k, v in before.items() if k != 'independent_evidence'} == {
            k: v for k, v in after.items() if k != 'independent_evidence'}
        proof = after['independent_evidence']
        assert proof['source_hash'] == digest({k: v for k, v in proof.items() if k != 'source_hash'})
        assert proof['value']['book_content_hash'] == digest(month['book'])
        assert proof['evidence_id'].endswith('.BCN51.EXPLICIT.ISS')
    input_values = result['calculation_input']
    assert input_values['general_revenue'] == '1200.00'
    assert input_values['other_deductible_expenses'] == '100.00'
    assert input_values['allocated_funding_expenses'] == '200.00'
    assert result['calculation']['input_hash'] == digest(input_values)
    assert input_values['codBC'] == ('1005' if name == 'explicit-iss' else '1010')
    for pool in ['I', 'C']:
        assert result['calculation']['pools'][input_values['codBC'] + pool]['positive_base'] == '891.09'
    assert len(case['event_validations']) == 5
    for validation in case['event_validations'].values():
        assert validation['reason'] == 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE'
        assert validation['unsigned_candidate_structure_checked'] is True
        assert validation['production_ready'] is False and validation['signature_verified'] is False
        assert len(validation['errors']) == 1 and 'Signature' in validation['errors'][0]['message']
        checks += 1
assert checks == 10
assert capture['missing_classification']['reason'] == 'RTC_DERE_CURRENT_COMPOSITION_EXPLICIT_REVENUE_ISS_CLASSIFICATION_REQUIRED'
observations = []
for label in ['table11-current-financial-composition', 'rounding-rule']:
    path = E / (label + '-observation.json')
    observation = read(path)
    assert sha(ROOT / observation['source_path']) == observation['source_sha256']
    assert sha(ROOT / observation['excerpt_path']) == observation['excerpt_sha256']
    observations.append(ref(path))
files = ['app/Modules/Fiscal/ConsumptionReform/DereFinancialCurrentBaseCompositionQualifier.php',
         'tests/Fiscal/Unit/DereFinancialCurrentComposition51Test.php',
         'scripts/capture_phase4b_negative_base51_composition.py',
         'scripts/audit_phase4b_negative_base51_composition.py',
         'docs/fiscal/phase4b-negative-base-current-composition51-development.md']
preserved = ['composition-initial-tests.json', 'composition-bootstrap-tests.json',
             'composition-native-capture-check.json', 'composition-native-replay-check.json',
             'before-test-application-bootstrap.php', 'before-independent-bridge-successor-test.php',
             'before-pest-bootstrap-filter-capture.py', 'capture-current-composition.php']
report = {'status': 'LOCAL_CURRENT_BASE_COMPOSITION_RECONCILED_NOT_CONSUMPTION_INTEGRATION',
          'targeted_tests': 25, 'targeted_assertions': 60, 'forming_code_cases': 2, 'unsigned_xsd_checks': checks,
          'quality': quality, 'implementation': [ref(ROOT / name) for name in files],
          'capture': ref(E / 'composition-native-result.json'), 'source_observations': observations,
          'prior_implementation_reports_verified': [ref(path) for path in prior_reports],
          'preserved_diagnostics': [ref(E / name) for name in preserved],
          'original_economics_preserved': True, 'original_snapshot_replay_matches': True,
          'classification_external_verified': False, 'temporal_successor_policy_integrated': False,
          'negative_base_consumption_applied': False, 'full_suite_after51_run': False,
          'scope51_complete': False, 'scope28_complete': False, 'phase4b_complete': False, 'production_ready': False}
path = E / 'composition-development-validation.json'
raw = json.dumps(report, ensure_ascii=False, indent=2) + '\n'
if path.exists():
    assert path.read_text(encoding='utf-8') == raw
else:
    with path.open('x', encoding='utf-8') as stream:
        stream.write(raw)
print('PASS: two source-bound compositions; 25/60 targeted; ten XSD checks; economics preserved and consumption integration pending')
