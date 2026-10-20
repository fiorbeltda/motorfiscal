"""Extract every original requirement and preserve current evidence inventory; no completion inference."""
from pathlib import Path
import hashlib, json, re

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-original-requirements72-audit-v3-20261014'
E.mkdir(exist_ok=False)
PLAN = Path('C:/Users/gabriel.oliveira/.codex/attachments/e85edde7-ac3a-4a4a-b380-9cf54627bd84/Texto colado.txt')
def sha(path): return hashlib.sha256(path.read_bytes()).hexdigest()
def read(path): return json.loads(path.read_text(encoding='utf-8'))
text = PLAN.read_text(encoding='utf-8-sig')
section = text[text.index('4B.1 Temporalidade'):].split('FASE 5 —', 1)[0]
group = None
requirements = []
for line in section.splitlines():
    match = re.match(r'(4B\.[1-8])\s+(.+)', line.strip())
    if match: group = match.group(1)
    elif group and line.strip():
        requirements.append({'id': group + '.' + str(1 + sum(r['group'] == group for r in requirements)),
                             'group': group, 'requirement': line.strip().removeprefix('- ').rstrip(';.'),
                             'completion_status': 'UNPROVEN_REQUIRES_SCOPED_EVIDENCE'})
assert {r['group'] for r in requirements} == {'4B.' + str(i) for i in range(1, 9)}
assert len(requirements) >= 70
bundle_path = ROOT / 'resources/fiscal/bundles/consumption-phase4b-2026.10.14-70-draft1.json'
bundle = read(bundle_path)
resources = []
for key, relative in bundle['resource_paths'].items():
    path = ROOT / 'resources/fiscal' / relative
    assert path.exists(), path
    resources.append({'role': key, 'path': path.relative_to(ROOT).as_posix(),
                      'sha256': sha(path) if path.is_file() else None, 'kind': 'FILE' if path.is_file() else 'DIRECTORY'})
transition = read(ROOT / 'resources/fiscal' / bundle['resource_paths']['rtc_transition_catalog'])
rates = read(ROOT / 'resources/fiscal' / bundle['resource_paths']['rtc_rate_catalog'])
engine = ROOT / 'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php'
guards = [{'line': i, 'reason': reason} for i, line in enumerate(engine.read_text(encoding='utf-8').splitlines(), 1)
          for reason in re.findall(r"'(RTC_[A-Z0-9_]*(?:NOT_MATERIALIZED|NOT_PROVEN)[A-Z0-9_:]*)'", line)]
tests = [{'path': p.relative_to(ROOT).as_posix(), 'sha256': sha(p)} for p in sorted((ROOT / 'tests/Fiscal').rglob('*.php'))]
year_rates = [{'year': year, 'rate_rows': [r for r in rates['rates'] if r['valid_from'] <= f'{year}-07-01' <= r['valid_to']],
               'transition_rows': [r for r in transition['rows'] if r['valid_from'] <= f'{year}-07-01' <= r['valid_to']]}
              for year in range(2026, 2034)]
record = {'status': 'ORIGINAL_REQUIREMENTS_EXTRACTED_CURRENT_EVIDENCE_INDEXED_COMPLETION_UNPROVEN',
          'original_plan_path': str(PLAN), 'original_plan_sha256': sha(PLAN), 'requirements': requirements,
          'bundle_path': bundle_path.relative_to(ROOT).as_posix(), 'bundle_sha256': sha(bundle_path),
          'resources': resources, 'fiscal_test_inventory': tests, 'engine_sha256': sha(engine),
          'guard_inventory_not_automatic_gap_classification': guards, 'year_policy_inventory': year_rates,
          'confirmed_partial_export_fx_gap': {'active_partial_export_policy': transition['partial_export_domestic_leg'],
              'engine_refusal': 'RTC_PARTIAL_EXPORT_CURRENCY_CONVERSION_NOT_MATERIALIZED',
              'requires_native_counterexample': True},
          'phase4b_complete': False, 'production_ready': False}
with (E / 'original-requirements-inventory.json').open('x', encoding='utf-8') as stream:
    json.dump(record, stream, ensure_ascii=False, indent=2)
with (E / 'original-phase4b-requirements.txt').open('x', encoding='utf-8') as stream: stream.write(section)
print(f'PASS:{len(requirements)} original numbered requirements preserved; {len(tests)} test sources indexed; broad completion unproven')
