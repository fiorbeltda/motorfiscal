"""Bind the observed91 corpus, source hashes and final directed gates before regression."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re

ROOT = Path(__file__).resolve().parents[1]
P = ROOT / 'storage/app/phase4b-advance91-integration-20261015'
E = ROOT / 'storage/app/phase4b-advance91-cli-20261015'
Q = ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014'
F = ROOT / 'resources/fiscal'

def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p): return hashlib.file_digest(p.open('rb'), 'sha256').hexdigest()
def canonical(v): return hashlib.sha256(json.dumps(v, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()

target = E / 'advance91-integration-preflight.json'
assert not target.exists()
corpus = read(E / 'advance91-cli-corpus-validation.json')
assert corpus['status'] == 'ADVANCE91_NATIVE_CORPUS_VERIFIED_FULL_REGRESSION_PENDING'
assert len(corpus['cases']) == 30 and len(corpus['prior_compatibility']) == 53
assert corpus['native_decisions'] == corpus['exact_replays'] == 60
bundle = read(F / corpus['bundle'])
assert sha(F / corpus['bundle']) == corpus['bundle_sha256']
for role in ['rtc_transition_catalog', 'fact_definitions', 'questions']:
    assert canonical(read(F / bundle['resource_paths'][role])) == bundle['content_hashes'][role]
assert canonical(read(F / bundle['production_controls']['path'])) == bundle['production_controls']['sha256']
old = read(F / 'bundles/consumption-phase4b-2026.10.15-88-draft1.json')
assert bundle['official_sources'] == old['official_sources'] and len(bundle['official_sources']) == 59
assert bundle['content_hashes']['rtc_rate_catalog'] == old['content_hashes']['rtc_rate_catalog']
for source in bundle['official_sources']: assert sha(F / 'sources/official' / source['file']) == source['sha256']

def execution(label):
    record = read(E / (label + '-execution.json'))
    assert record['exit_code'] == 0, label
    for suffix in ['stdout', 'stderr']:
        assert sha(E / (label + '.' + suffix)) == record[suffix + '_sha256'], label
    return read(E / (label + '.stdout'))

for case in corpus['cases']:
    assert sha(ROOT / case['path']) == case['scenario_sha256']
    for kind in ['rtc', 'integrated']:
        label = case['case'] + '-' + kind
        snapshot = ROOT / case[kind + '_snapshot']
        assert sha(snapshot) == case[kind + '_snapshot_sha256']
        result = execution(label)
        assert read(snapshot)['result'] == result
        assert result['status'] == case['status'] and case['reason'] in result['reason_codes']
        assert execution(label + '-replay')['replay_matches'] is True
for index, prior in enumerate(corpus['prior_compatibility']):
    assert sha(ROOT / prior['path']) == prior['sha256']
    assert execution('historical-' + str(index))['replay_matches'] is True
assessment = execution('production-assessment91-bound')
previous = read(ROOT / 'storage/app/phase4b-advance88-cli-20261015/production-assessment88-bound.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 59
assert set(assessment['blockers']) == set(previous['blockers']) and len(assessment['blockers']) == 119
gates = {}
for label in ['scope91-final-directed', 'scope91-final-phpstan-all', 'scope91-final-pint-all']:
    record = read(Q / (label + '.json'))
    assert record['exit_code'] == 0 and sha(Q / (label + '.log')) == record['log_sha256']
    gates[label] = record
directed = re.sub(r'\x1b\[[0-9;]*m', '', (Q / 'scope91-final-directed.log').read_text(encoding='utf-8'))
assert re.search(r'295 passed\s+\(2182 assertions\)', directed)
assert '[OK] No errors' in (Q / 'scope91-final-phpstan-all.log').read_text(encoding='utf-8')
assert re.search(r'PASS.*542 files', (Q / 'scope91-final-pint-all.log').read_text(encoding='utf-8'))
archive = read(P / 'before-integration-archive.json')
for path, digest in archive['archived'].items(): assert sha(P / 'before-integration' / path) == digest
accepted = ROOT / 'storage/app/phase4b-advance88-development-20261015/advance88-development-validation.json'
assert sha(accepted) == archive['accepted88_sha256']
runtime = {p.relative_to(ROOT).as_posix(): sha(p) for p in (ROOT / 'app/Modules/Fiscal/ConsumptionReform').glob('*.php')}
service = 'app/Modules/Fiscal/Infrastructure/Scenario/RtcScenarioRunService.php'
runtime[service] = sha(ROOT / service)
assert len(runtime) == 71
evidence = {}
prior = read(ROOT / 'storage/app/phase4b-advance88-cli-20261015/advance88-integration-preflight.json')
for path, digest in prior['evidence_input_hashes'].items():
    assert sha(ROOT / path) == digest, path
    evidence[path] = digest
for folder in [P, E, accepted.parent]:
    for path in folder.rglob('*'):
        if path.is_file(): evidence[path.relative_to(ROOT).as_posix()] = sha(path)
for label in gates:
    for suffix in ['.json', '.log']:
        path = Q / (label + suffix)
        evidence[path.relative_to(ROOT).as_posix()] = sha(path)
with target.open('x', encoding='utf-8') as stream:
    json.dump({'status': 'ADVANCE91_LOCAL_INTEGRATION_PREFLIGHT_VERIFIED_FULL_SUITE_PENDING',
        'created_at': datetime.now(timezone.utc).isoformat(), 'runtime_hashes': runtime,
        'evidence_input_hashes': evidence, 'bundle_sha256': corpus['bundle_sha256'], 'gates': gates,
        'directed_tests': 295, 'directed_assertions': 2182,
        'corpus_sha256': sha(E / 'advance91-cli-corpus-validation.json'), 'official_sources_valid': 59,
        'production_blockers': 119, 'full_suite_complete': False, 'prior_advance_debits_proven': False,
        'actual_registration_consumed': False, 'document_settlement_complete': False,
        'phase4b_complete': False, 'production_ready': False}, stream, indent=2)
print('PASS:91 preflight;295/2182 directed;60 decisions/replays;53 historical;71 runtime files')
