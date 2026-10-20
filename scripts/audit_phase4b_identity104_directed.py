"""Audit104 directed integration and only two authorized predecessor runtime changes; not full acceptance."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-identity104-integration-20261019')
OLD = Path(r'D:\ProjetoSaasExports\phase4b-catalog101-full-suite-20261016')
S = Path(r'D:\ProjetoSaasExports\phase4b-identity103-local-preparation-20261016')
F = ROOT / 'resources/fiscal'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    with p.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()

def canonical(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()

def clean(p):
    return re.sub(r'\x1b\[[0-9;]*m', '', p.read_text(encoding='utf-8', errors='replace'))

allowed = {'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
           'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php'}
archive = read(E / 'before-integration-archive.json')
pre = read(OLD / 'preflight.json')
changed = []
for path, h in pre['input_hashes'].items():
    p = Path(path)
    if sha(p) != h:
        relative = p.relative_to(ROOT).as_posix()
        assert relative in allowed, relative
        assert sha(E / 'before-integration' / relative) == h, relative
        changed.append(relative)
assert set(changed) == allowed
print('PASS:9270 predecessor inputs unchanged;only2 authorized runtime changes archived', flush=True)
quality = {}
for label in ['directed-v2', 'phpstan-v2', 'pint-v2']:
    gate = read(E / (label + '.json'))
    assert gate['exit_code'] == 0
    assert gate['scope_input_hashes_at_start'] == gate['scope_input_hashes_at_end']
    for channel in ['stdout', 'stderr']:
        assert sha(E / (label + '.' + channel)) == gate[channel + '_sha256']
    for p, h in gate['scope_input_hashes_at_start'].items():
        assert sha(ROOT / p) == h, p
    quality[label] = gate
match = re.search(r'(\d+) passed \((\d+) assertions\)', clean(E / 'directed-v2.stdout'))
assert match is not None
tests, checks = map(int, match.groups())
assert tests == 3378, (tests, checks)
assert '[OK] No errors' in clean(E / 'phpstan-v2.stdout')
assert re.search(r'PASS.*562 files', clean(E / 'pint-v2.stdout'))
manifest = read(E / 'identity104-case-manifest-v2.json')
assert len(manifest['cases']) == 45
bundle = read(F / manifest['bundle'])
prior = read(F / 'bundles/consumption-phase4b-2026.10.16-101-draft1.json')
assert bundle['official_sources'][:len(prior['official_sources'])] == prior['official_sources']
assert len(bundle['official_sources']) == 71
for source in bundle['official_sources']:
    assert sha(F / 'sources/official' / source['file']) == source['sha256'], source['id']
for role in ['rtc_rate_catalog', 'rtc_transition_catalog']:
    assert bundle['resource_paths'][role] == prior['resource_paths'][role]
    assert bundle['content_hashes'][role] == prior['content_hashes'][role]
for role in ['rtc_code_catalog', 'fact_definitions', 'questions']:
    assert canonical(read(F / bundle['resource_paths'][role])) == bundle['content_hashes'][role]
codes = read(F / bundle['resource_paths']['rtc_code_catalog'])
old_codes = read(F / prior['resource_paths']['rtc_code_catalog'])
assert {k: v for k, v in codes.items() if k not in ['version', 'sources', 'partial_export_dps_identity_policy']} == {k: v for k, v in old_codes.items() if k not in ['version', 'sources']}
for k, v in old_codes['sources'].items():
    assert codes['sources'][k] == v
policy = read(ROOT / 'tests/Fixtures/identity103-cases.json')['cases'][0]['policy']
assert codes['partial_export_dps_identity_policy'] == policy
for role in ['fact_definitions', 'questions']:
    old = read(F / prior['resource_paths'][role])
    new = read(F / bundle['resource_paths'][role])
    assert new[:len(old)] == old and len(new) == len(old) + 2
raw = (S / 'PartialExportDpsIdentity103.php').read_bytes()
assert (ROOT / 'app/Modules/Fiscal/ConsumptionReform/PartialExportDpsIdentity103.php').read_bytes() == raw.replace(b'namespace Phase4bPrepared;', b'namespace App\\Modules\\Fiscal\\ConsumptionReform;')
for case in manifest['cases']:
    assert sha(ROOT / case['path']) == case['sha256']
    assert datetime.fromisoformat(case['knowledge_at']) >= datetime.fromisoformat(case['original_knowledge_at'])
    if 'original_path' in case:
        assert sha(ROOT / case['original_path']) == case['original_sha256']
        facts = read(ROOT / case['path'])['facts']
        assert {k: v for k, v in facts.items() if k not in ['rtc.assess_export_dps_identity', 'rtc.export_dps_identity_records']} == read(ROOT / case['original_path'])['facts']
acceptance = ROOT / 'storage/app/phase4b-catalog101-development-20261016/catalog101-development-validation.json'
assert sha(acceptance) == archive['acceptance101_sha256']
report = {'status': 'IDENTITY104_DIRECTED_INTEGRATION_VERIFIED_CORPUS_REGRESSION_PENDING',
    'created_at': datetime.now(timezone.utc).isoformat(), 'auditor_sha256': sha(Path(__file__)),
    'directed_tests': tests, 'directed_assertions': checks, 'new_pest_tests': 233,
    'predecessor101_inputs_unchanged': len(pre['input_hashes']) - len(changed), 'authorized_changes': changed,
    'acceptance101_sha256': sha(acceptance), 'component_namespace_only': True,
    'bundle': manifest['bundle'], 'bundle_sha256': sha(F / manifest['bundle']), 'official_source_entries_verified': 71,
    'scenario_count': 45, 'quality': quality, 'full_suite_complete': False, 'production_ready': False,
    'next_required': ['Native dual-runner corpus and337 historical replays', 'Complete regression with frozen inputs',
                      'Independent development acceptance audit', 'Material dates,documentary FX/bases,cadastral/remote validity and XML remain separate requirements']}
with (E / 'identity104-directed-validation.json').open('x', encoding='utf-8') as stream:
    json.dump(report, stream, ensure_ascii=False, indent=2)
print(json.dumps({k: report[k] for k in ['status', 'directed_tests', 'directed_assertions', 'predecessor101_inputs_unchanged', 'authorized_changes']}), flush=True)
