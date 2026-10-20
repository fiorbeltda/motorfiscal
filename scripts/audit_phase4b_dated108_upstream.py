"""Audit all36 upstream transformations against immutable source facts and terminal checks."""
from datetime import datetime, timezone
from verify_phase4b_dated108_evidence import ROOT, E, A104, read, sha, save, gate, clean

target = E / 'dated108-upstream-validation.json'
assert not target.exists()
assert sha(A104) == '8f3d57986e7fea2f87ca317914bc1ecac90e7cfd719607839e88fa5128262bd1'
manifest_path = ROOT / 'tests/Fixtures/dated108-upstream-expectations.json'
manifest = read(manifest_path)
assert len(manifest['cases']) == 36
seed = read(E / 'upstream-scenario-seed.json')
assert len(seed['cases']) == 36
directed = gate(E, 'directed-v4')
assert '904 passed (12595 assertions)' in clean(E / 'directed-v4.stdout')
assert gate(E, 'phpstan-v3')['runtime_hashes'] == directed['runtime_hashes']
assert gate(E, 'pint-v4')['runtime_hashes'] == directed['runtime_hashes']
assert '[OK] No errors' in clean(E / 'phpstan-v3.stdout')
assert '572 files' in clean(E / 'pint-v4.stdout')
for path, digest in directed['runtime_hashes'].items():
    assert sha(ROOT / path) == digest
added = ['rtc.assess_export_dated_documents', 'rtc.export_iss_material_records', 'rtc.export_iss_dated_result_records',
    'rtc.export_dated_dps_identity_records', 'provider.iss_regime', 'provider.iss_regime_proof_records']
prior_flags = ['rtc.assess_export_iss_results', 'rtc.assess_export_sp_results', 'rtc.assess_export_dps_identity']
national = municipal = identities = fx_refusals = 0
records = []
for case, selection in zip(manifest['cases'], seed['cases'], strict=True):
    assert case['name'] == selection['name']
    for key in ['status', 'reason', 'dated_status', 'dated_reason', 'dated_subreason', 'fx_refusal_before_nominal']:
        assert case[key] == selection[key]
    original_path = ROOT / case['original_path']
    current_path = ROOT / case['path']
    assert sha(original_path) == case['original_sha256'] == selection['original_sha256']
    assert sha(current_path) == case['scenario_sha256']
    source = read(original_path)
    assert source == selection['source']
    current = read(current_path)
    assert datetime.fromisoformat(current['knowledge_at']) >= datetime.fromisoformat(source['knowledge_at'])
    facts, old = current['facts'], source['facts']
    restored = {k: v for k, v in facts.items() if k not in added}
    for key in prior_flags:
        assert facts[key] is False
        if key in old:
            restored[key] = old[key]
        else:
            restored.pop(key, None)
    assert restored == old
    if case['fx_refusal_before_nominal']:
        fx_refusals += 1
        assert facts['rtc.export_iss_material_records'] == facts['rtc.export_iss_dated_result_records'] == facts['rtc.export_dated_dps_identity_records'] == []
    else:
        contexts = {r['role']: r['iss_context'] for r in old['rtc.export_iss_result_contexts']}
        original_municipal = old.get('rtc.export_sp_result_proofs', [])
        results = facts['rtc.export_iss_dated_result_records']
        assert len(results) == len(original_municipal)
        for result, previous in zip(results, original_municipal, strict=True):
            assert result['role'] == previous['role']
            assert result['iss_context'] == contexts[previous['role']]
            assert result['municipal_proof'] == previous['municipal_proof']
            national += 1
            municipal += 1
        new_identity = facts['rtc.export_dated_dps_identity_records']
        assert len(new_identity) == len(old['rtc.export_dps_identity_records'])
        for record, previous in zip(new_identity, old['rtc.export_dps_identity_records'], strict=True):
            assert {k: v for k, v in record.items() if k != 'proof'} == {k: v for k, v in previous.items() if k != 'proof'}
            identities += 1
    records.append({'name': case['name'], 'scenario_sha256': sha(current_path), 'original_sha256': sha(original_path),
        'original_facts_preserved_except_three_disabled_control_flags': True, 'original_material_proof_bodies_preserved': True})
assert fx_refusals == 6 and national > 0 and municipal > 0 and identities == 60
report = {'status': 'DATED108_UPSTREAM36_TRANSFORMATIONS_AND_DIRECTED_CHECKS_VERIFIED',
    'created_at': datetime.now(timezone.utc).isoformat(), 'auditor_sha256': sha(ROOT / 'scripts/audit_phase4b_dated108_upstream.py'),
    'manifest_sha256': sha(manifest_path), 'upstream_scenarios': 36, 'new_permanent_tests': 72,
    'total_new_permanent_tests108': 671, 'directed_tests': 904, 'directed_assertions': 12595,
    'national_contexts_preserved': national, 'municipal_proof_bodies_preserved': municipal,
    'original_identity_values_preserved': identities, 'original_fx_negative_cases': fx_refusals,
    'runtime_hashes': directed['runtime_hashes'], 'phpstan_general_passed': True, 'pint_general_files': 572,
    'collector_limit': 'upstream- prefix prevents original collector name lookup;independent preflight/acceptance verifier compares captured104 by original_path instead',
    'cases': records, 'cli_corpus_complete_claimed_here': False, 'full_suite_complete': False,
    'document_obligation_complete': False, 'legal_applicability_approved': False, 'phase4b_complete': False, 'production_ready': False}
save(target, report)
print(str({k: report[k] for k in ['status', 'upstream_scenarios', 'directed_tests', 'directed_assertions',
    'national_contexts_preserved', 'municipal_proof_bodies_preserved', 'original_identity_values_preserved']}), flush=True)
