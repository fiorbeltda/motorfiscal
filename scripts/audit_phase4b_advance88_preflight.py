"""Freeze actual88 inputs only after directed gates, native replays and source checks pass."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re

ROOT = Path(__file__).resolve().parents[1]
P = ROOT / 'storage/app/phase4b-advance88-preparation-20261015'
E = ROOT / 'storage/app/phase4b-advance88-cli-20261015'
Q = ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014'
F = ROOT / 'resources/fiscal'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p): return hashlib.sha256(p.read_bytes()).hexdigest()
def canonical(v): return hashlib.sha256(json.dumps(v, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
target = E / 'advance88-integration-preflight.json'
assert not target.exists()
corpus = read(E / 'advance88-cli-corpus-validation.json')
assert len(corpus['cases']) == 24 and len(corpus['prior_compatibility']) == 5
assert corpus['exact_replays'] == 48 and corpus['final_replays_after_formatting'] == 48
bundle = read(F / corpus['bundle'])
assert sha(F / corpus['bundle']) == corpus['bundle_sha256']
for role in ['rtc_transition_catalog', 'fact_definitions', 'questions']:
    assert canonical(read(F / bundle['resource_paths'][role])) == bundle['content_hashes'][role]
assert canonical(read(F / bundle['production_controls']['path'])) == bundle['production_controls']['sha256']
old = read(F / 'bundles/consumption-phase4b-2026.10.15-86-draft1.json')
assert bundle['official_sources'] == old['official_sources'] and len(bundle['official_sources']) == 59
assert bundle['content_hashes']['rtc_rate_catalog'] == old['content_hashes']['rtc_rate_catalog']
for source in bundle['official_sources']: assert sha(F / 'sources/official' / source['file']) == source['sha256']
gates = {}
for label in ['scope88-final-directed', 'scope88-final-phpstan-all', 'scope88-final-pint-all']:
    record = read(Q / (label + '.json'))
    assert record['exit_code'] == 0 and sha(Q / (label + '.log')) == record['log_sha256']
    gates[label] = record
directed = re.sub(r'\x1b\[[0-9;]*m', '', (Q / 'scope88-final-directed.log').read_text(encoding='utf-8'))
counts = re.search(r'(\d+) passed\s+\((\d+) assertions\)', directed)
assert counts and int(counts[1]) == 238
archive = read(P / 'before-integration-archive.json')
for path, digest in archive['archived'].items(): assert sha(P / 'before-integration' / path) == digest
for case in corpus['cases']:
    assert sha(ROOT / case['path']) == case['scenario_sha256']
    for kind in ['rtc', 'integrated']:
        snapshot = ROOT / case[kind + '_snapshot']
        assert sha(snapshot) == case[kind + '_snapshot_sha256']
        assert read(E / (case['case'] + '-' + kind + '.stdout')) == read(snapshot)['result']
        assert read(E / ('final-' + case['case'] + '-' + kind + '-replay.stdout'))['replay_matches'] is True
runtime = {p.relative_to(ROOT).as_posix(): sha(p) for p in (ROOT / 'app/Modules/Fiscal/ConsumptionReform').glob('*.php')}
evidence = {}
prior = read(ROOT / 'storage/app/phase4b-temporal86-cli-20261015/temporal86-integration-preflight.json')
for path in prior['evidence_input_hashes']: evidence[path] = sha(ROOT / path)
for folder in [P, E]:
    for path in folder.rglob('*'):
        if path.is_file(): evidence[path.relative_to(ROOT).as_posix()] = sha(path)
with target.open('x', encoding='utf-8') as stream:
    json.dump({'status': 'ADVANCE88_LOCAL_INTEGRATION_PREFLIGHT_VERIFIED_FULL_SUITE_PENDING',
        'created_at': datetime.now(timezone.utc).isoformat(), 'runtime_hashes': runtime, 'evidence_input_hashes': evidence,
        'bundle_sha256': corpus['bundle_sha256'], 'gates': gates, 'directed_tests': int(counts[1]), 'directed_assertions': int(counts[2]),
        'corpus_sha256': sha(E / 'advance88-cli-corpus-validation.json'), 'official_sources_valid': 59,
        'production_blockers': corpus['production_blocker_count'], 'full_suite_complete': False,
        'registration_proven': False, 'document_settlement_complete': False, 'phase4b_complete': False,
        'production_ready': False}, stream, indent=2)
print('PASS:238 directed tests;24 dual-runner scenarios/final replays;59 sources;full suite pending')
