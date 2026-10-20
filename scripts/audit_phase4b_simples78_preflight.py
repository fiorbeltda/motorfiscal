"""Verify own H1 policy, corpus, snapshots and guards before a new full-suite run."""
from pathlib import Path
import hashlib
import json
import re

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = ROOT / 'storage/app/phase4b-simples78-cli-v2-20261015'
OLD = ROOT / 'storage/app/phase4b-simples78-cli-20261015'
P = ROOT / 'storage/app/phase4b-simples78-integration-preparation-20261015'
Q = ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def canonical(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def execution(label, expected_exit=0):
    directory = E if (E / (label + '-execution.json')).exists() else OLD
    receipt = read(directory / (label + '-execution.json'))
    assert receipt['exit_code'] == expected_exit, label
    for channel in ['stdout', 'stderr']:
        assert sha(directory / (label + '.' + channel)) == receipt[channel + '_sha256']
    return read(directory / (label + '.stdout'))


gates = {}
for label in ['scope78-runtime-directed', 'scope78-runtime-phpstan', 'scope78-runtime-pint',
              'scope78-integrated-directed', 'scope78-phpstan-all', 'scope78-pint-all']:
    record = read(Q / (label + '.json'))
    assert record['exit_code'] == 0 and sha(Q / (label + '.log')) == record['log_sha256']
    gates[label] = record
for label, count, assertions in [('scope78-runtime-directed', 84, 407), ('scope78-integrated-directed', 100, 550)]:
    text = re.sub(r'\x1b\[[0-9;]*m', '', (Q / (label + '.log')).read_text(encoding='utf-8'))
    assert re.search(r'Tests:\s+' + str(count) + r' passed\s+\(' + str(assertions) + r' assertions\)', text)
corpus = read(E / 'simples78-cli-corpus-validation.json')
assert len(corpus['cases']) == 15 and len(corpus['prior_compatibility']) == 2
bundle_path = F / corpus['bundle']
assert sha(bundle_path) == corpus['bundle_sha256']
bundle = read(bundle_path)
for role in ['rtc_transition_catalog', 'fact_definitions', 'questions']:
    assert canonical(read(F / bundle['resource_paths'][role])) == bundle['content_hashes'][role]
assert canonical(read(F / bundle['production_controls']['path'])) == bundle['production_controls']['sha256']
assert len(bundle['official_sources']) == 55
for source in bundle['official_sources']:
    assert sha(F / 'sources/official' / source['file']) == source['sha256']
archive = read(P / 'before-integration-archive.json')
for row in archive['files']:
    assert sha(P / row['archive']) == row['sha256']
    if row['path'].startswith('resources/'):
        assert sha(ROOT / row['path']) == row['sha256'], 'Historical resource rewritten'
transitions = read(F / bundle['resource_paths']['rtc_transition_catalog'])
installation = read(P / 'simples78-bundle-installation.json')
assert canonical(transitions['simples_option_windows'][1]) == installation['second_semester_row_unchanged_sha256']
facts = read(F / bundle['resource_paths']['fact_definitions'])
questions = read(F / bundle['resource_paths']['questions'])
for key in installation['new_facts']:
    assert sum(fact['key'] == key for fact in facts) == 1
    assert sum(question['fact_key'] == key for question in questions) == 1
evidence = {row['path']: row['sha256'] for row in read(P / 'component-fixture-installation.json')['files']}
evidence[(E / 'simples78-cli-corpus-validation.json').relative_to(ROOT).as_posix()] = sha(E / 'simples78-cli-corpus-validation.json')
for case in corpus['cases']:
    name = case['case']
    for path, digest in [(case['scenario'], case['scenario_sha256']),
                         (case['rtc_snapshot'], case['rtc_snapshot_sha256']),
                         (case['integrated_snapshot'], case['integrated_snapshot_sha256'])]:
        assert sha(ROOT / path) == digest
        evidence[path] = digest
    rtc = execution(name + '-rtc', 1 if case['expected_status'] == 'INVALID' else 0)
    full = execution(name + '-integrated')
    assert rtc == read(ROOT / case['rtc_snapshot'])['result']
    assert full == read(ROOT / case['integrated_snapshot'])['result']
    assert rtc['status'] == full['status'] == case['expected_status']
    assert case['expected_reason'] in rtc['reason_codes'] and case['expected_reason'] in full['reason_codes']
    assert execution(name + '-rtc-replay')['replay_matches'] is True
    replay = execution(name + '-integrated-replay')
    assert replay['replay_matches'] is True and replay['result'] == full
    if case['election_locally_qualified']:
        trace = {row['stage']: row for row in rtc['trace']}
        assert trace['SIMPLES_REGULAR_ELECTION_194']['government_receipt_authenticated'] is False
        assert trace['SIMPLES_REGULAR_ELECTION_194']['production_ready'] is False
        assert rtc['reason_codes'] == ['RTC_RATE_NOT_PROVEN:CBS']
for case in corpus['prior_compatibility']:
    assert sha(ROOT / case['path']) == case['sha256'] and case['exact_replay'] is True
    evidence[case['path']] = case['sha256']
assessment = execution('production-assessment')
old_assessment = read(ROOT / 'storage/app/phase4b-foreign-acquirer76-development-20261015/foreign76-final-production-assess.log')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 55
assert set(old_assessment['blockers']) - set(assessment['blockers']) == {'RTC_SIMPLES_SUPERSEDING_ACT_REVIEW_REQUIRED'}
assert not set(assessment['blockers']) - set(old_assessment['blockers'])
assert 'RTC_DPS_RESTRICTED_SCHEMA_LOCAL_ONLY' in assessment['blockers']
for path, digest in evidence.items():
    assert sha(ROOT / path) == digest
paths = ['app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
         'app/Modules/Fiscal/ConsumptionReform/TransitionCatalog.php',
         'app/Modules/Fiscal/ConsumptionReform/SimplesRegularElectionQualifier78.php',
         'tests/Fiscal/Unit/SimplesRegularElection78Test.php',
         'tests/Fiscal/Unit/SimplesTemporalCatalog78Test.php',
         'tests/Fiscal/Integration/SimplesRegularElectionEngine78Test.php']
report = {'status': 'SIMPLES78_LOCAL_INTEGRATION_PREFLIGHT_VERIFIED_FULL_SUITE_PENDING',
    'runtime_hashes': {path: sha(ROOT / path) for path in paths}, 'evidence_input_hashes': evidence,
    'gates': gates, 'bundle_sha256': sha(bundle_path), 'scenarios': 15, 'native_decisions': 30,
    'exact_replays': 30, 'prior_exact_replays': 2, 'official_sources_valid': 55,
    'removed_production_blocker': 'RTC_SIMPLES_SUPERSEDING_ACT_REVIEW_REQUIRED',
    'production_blocker_count': len(assessment['blockers']),
    'full_suite_complete': False, 'future_cbs_rate_qualified': False,
    'government_receipt_authenticated': False, 'phase4b_complete': False, 'production_ready': False}
with (E / 'simples78-integration-preflight.json').open('x', encoding='utf-8') as stream:
    json.dump(report, stream, ensure_ascii=False, indent=2)
print('PASS:15 cases/30 native decisions/32 replays/55 sources;only resolved act-review blocker removed;full suite pending')
