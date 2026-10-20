"""Prove four explicit artifact deliveries without inferring complete family/period coverage."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-acceptance93-artifact-inspection-20261015')
Q = ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014'
INVENTORY = ROOT / 'storage/app/phase4b-original-requirements72-audit-v3-20261014/original-requirements-inventory.json'
PREFLIGHT = ROOT / 'storage/app/phase4b-advance91-cli-20261015/advance91-integration-preflight.json'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p):
    with p.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
gate = read(Q / 'scope93-artifact-inspection-v3.json')
assert gate['exit_code'] == 0 and sha(Q / 'scope93-artifact-inspection-v3.log') == gate['log_sha256']
native = read(Q / 'scope93-artifact-inspection-v3.log')
assert native['status'] == 'OWN91_ARTIFACTS_POSITIVE_DECISION_AND_REPLAY_OBSERVED_NOT_PHASE_ACCEPTANCE'
assert native['result']['status'] == 'VALID' and native['result']['decision'] and native['result']['trace']
assert native['exact_replay'] is True
assert native['scenario']['knowledge_at'] == '2026-10-15T17:34:43+00:00'
assert len(native['own91_fact_question_contracts']) == 4
proof = read(PREFLIGHT)
for path, digest in proof['runtime_hashes'].items(): assert sha(ROOT / path) == digest
bundle_path = ROOT / 'resources/fiscal/bundles/consumption-phase4b-2026.10.15-91-draft1.json'
bundle = read(bundle_path)
assert sha(bundle_path) == proof['bundle_sha256']
facts_path = ROOT / 'resources/fiscal' / bundle['resource_paths']['fact_definitions']
questions_path = ROOT / 'resources/fiscal' / bundle['resource_paths']['questions']
facts = read(facts_path); questions = read(questions_path)
assert len({f['key'] for f in facts}) == len(facts) == native['facts_count']
assert len({q['fact_key'] for q in questions}) == len(questions) == native['questions_count']
for key, typ in native['own91_fact_question_contracts'].items():
    assert next(f for f in facts if f['key'] == key)['type'] == typ
    assert next(q for q in questions if q['fact_key'] == key)['prompt']
requirements = {r['id']: r for r in read(INVENTORY)['requirements']}
limits = {
    '4B.8.2': 'Own versioned bundle exists, hash-bound and loaded natively; not proof that all rules are complete.',
    '4B.8.3': 'Own facts/questions loaded,344/339 unique keys and four91 additions checked; no claim of facts for every unfinished profile.',
    '4B.8.8': 'Non-empty native trace and exact replay observed on one regular2026 scenario; not corpus coverage for every family.',
    '4B.8.9': 'Positive native decision with deterministic hash observed; not authorization for posting, production or unresolved decisions.',
}
target = E / 'acceptance93-artifact-review.json'
assert not target.exists()
with target.open('x', encoding='utf-8') as stream:
    json.dump({'status': 'FOUR_EXPLICIT_DELIVERABLES_OBSERVED_COMPLETE_PHASE_UNPROVEN',
        'created_at': datetime.now(timezone.utc).isoformat(), 'original_inventory_sha256': sha(INVENTORY),
        'bundle_sha256': sha(bundle_path), 'preflight91_sha256': sha(PREFLIGHT),
        'native_gate': gate, 'probe_sha256': sha(E / 'probe.php'),
        'fact_definitions_sha256': sha(facts_path), 'questions_sha256': sha(questions_path),
        'requirements': [{**requirements[key], 'review_status': 'EXPLICIT_LOCAL_ARTIFACT_DELIVERED',
            'original_completion_status_retained_for_full_scope_audit': True, 'observed_scope_and_limit': limit}
            for key, limit in limits.items()],
        'native_decision_hash': native['result']['decision_hash'], 'exact_replay': True,
        'full_suite91_still_required': True, 'remaining_76_inventory_entries_not_audited_here': True,
        'group4b8_formally_closed': False, 'phase4b_complete': False, 'production_ready': False}, stream, ensure_ascii=False, indent=2)
print('PASS:4B.8.2/3/8/9 explicit local artifacts observed;no phase closure or global percentage')
