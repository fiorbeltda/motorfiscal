"""Audit129 source bytes, declared trace changes, historical evidence and terminal checks."""
import argparse
import copy
import hashlib
import json
import re
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
parser = argparse.ArgumentParser()
parser.add_argument('--evidence', type=Path, required=True)
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
assert not args.output.exists()
checks = 0

def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def canonical(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()

def require(condition, label):
    global checks
    if not condition:
        raise AssertionError(label)
    checks += 1

base = read(ROOT / 'storage/app/phase4b-foreign128-development-20261022/foreign128-scoped-validation.json')
bundle_path = F / 'bundles/consumption-phase4b-2026.10.22-129-draft1.json'
bundle = read(bundle_path)
prior_bundle_path = F / base['bundle']
require(sha(prior_bundle_path) == base['bundle_sha256'], 'prior128 manifest preserved')
prior_bundle = read(prior_bundle_path)
prior_codes = read(F / prior_bundle['resource_paths']['rtc_code_catalog'])
require(canonical(prior_codes) == prior_bundle['content_hashes']['rtc_code_catalog'], 'prior catalog preserved')
codes = read(F / bundle['resource_paths']['rtc_code_catalog'])
require(canonical(codes) == bundle['content_hashes']['rtc_code_catalog'], 'current catalog bound')
expected = copy.deepcopy(prior_codes)
expected['version'] = '2026.10.22-129-draft1'
expected['import_responsibility_policy129'] = codes['import_responsibility_policy129']
require(canonical(codes['import_responsibility_policy129']) == 'a4612d5398292f5cd946388027d0fc970e78a31e4bb1562e8e0a04bae578ab2a', 'policy independently bound')
require(codes == expected, 'all other catalog fields unchanged')
require(bundle['official_sources'][:-1] == prior_bundle['official_sources'], 'old sources inventory unchanged')
for source in bundle['official_sources']:
    require(sha(F / 'sources/official' / source['file']) == source['sha256'], 'official bytes '+source['id'])
capture = read(args.evidence / 'primary-source-v2/capture.json')
require(sha(args.evidence / 'primary-source-v2/lc214compilado.htm') == capture['sha256'], 'raw new capture')
require(read(args.evidence / 'preparation-v1/preparation.json')['article64_text_identical_to_preserved119'], 'reviewed article matches prior text')
require(bundle['knowledge_available_at'] == capture['observed_at'], 'knowledge threshold from actual observation')

native = read(args.evidence / 'native-v1/validation.json')
require(native['complete'] and native['exit_code'] == 0 and native['native_decisions'] == native['exact_replays'] == 28, 'native population terminal')
snapshots = {}
for item in native['snapshots']:
    path = args.evidence / 'native-v1' / item['file']
    require(sha(path) == item['sha256'] and item['replay_matches'], 'saved snapshot/replay')
    key = (item['case'], item['runner'], item['version'])
    require(key not in snapshots, 'unique case/runner/version')
    snapshots[key] = read(path)

def assessment(snapshot, runner):
    if runner == 'rtc':
        return snapshot['result']
    return next(x['payload'] for x in snapshot['result']['trace'] if x['type'] == 'CONSUMPTION_REFORM')

names = ['acquirer', 'recipient', 'unknown-basis', 'domestic-basis', 'wrong-role', 'taxpayer-conflict', 'qualified-platform']
for name in names:
    for runner in ['rtc', 'integrated']:
        old = snapshots[name, runner, '128']
        new = snapshots[name, runner, '129']
        require(old['input']['facts'] == new['input']['facts'], 'identical literal input facts')
        require(old['input']['competence_date'] == new['input']['competence_date'] and old['input']['knowledge_at'] == new['input']['knowledge_at'], 'identical material and knowledge dates')
        old_a, new_a = assessment(old, runner), assessment(new, runner)
        trace = new_a['trace']
        index = next(i for i, x in enumerate(trace) if x['stage'] == 'IMPORT_RESPONSIBILITY129')
        prefix = [x for x in trace[:index] if x['stage'] != 'IMPORT_POLICY129']
        require(prefix == old_a['trace'][:len(prefix)], 'entire pre-responsibility trace preserved')
        responsibility = trace[index]
        require(responsibility['payment_proven'] is False and responsibility['document_obligation_complete'] is False, 'no new payment/document claims')
        if name in ['acquirer', 'recipient', 'qualified-platform']:
            without_new = copy.deepcopy(new_a)
            without_new['trace'] = [x for x in trace if x['stage'] not in ['IMPORT_RESPONSIBILITY129', 'IMPORT_POLICY129']]
            # RTC facade hash/scenario metadata cover the trace; compare the full assessment fields.
            for field in ['trace', 'status', 'reason_codes', 'missing_facts', 'decision']:
                require(without_new[field] == old_a[field], 'entire positive-assessment field preserved '+field)
            require(responsibility['status'] == 'RESOLVED' and responsibility['taxpayer'] == '22222222000191', 'qualified taxpayer')
            require(responsibility['taxpayer_role'] == ('RECIPIENT' if name == 'recipient' else 'ACQUIRER'), 'statutory role')
            require(responsibility['source_sha256'] == capture['sha256'], 'native source binding')
        else:
            require(responsibility['status'] in ['ACTION_REQUIRED', 'INVALID'], 'early controlled refusal')
            require(new_a['reason_codes'] == [responsibility['reason']], 'refusal reason bound')
            require(all(x['stage'] != 'IMPORT_NOMINAL_CALCULATION' for x in trace[index+1:]), 'no continuation after responsibility refusal')

for folder in ['directed-v2', 'phpstan-v1', 'pint-v2', 'production-assess-v1']:
    receipt = read(args.evidence / folder / 'execution.json')
    require(receipt['status'] == 'TERMINAL' and receipt['exit_code'] == 0, 'successful terminal '+folder)
    for channel in ['stdout', 'stderr']:
        require(sha(args.evidence / folder / (channel+'.log')) == receipt[channel+'_sha256'], 'terminal raw log '+folder+channel)
log = re.sub(r'\x1b\[[0-?]*[ -/]*[@-~]', '', (args.evidence / 'directed-v2/stdout.log').read_text(encoding='utf-8'))
require(re.search(r'318 passed\s*\(1220 assertions\)', log) is not None, 'observed directed test counts')
assessment129 = read(args.evidence / 'production-assess-v1/stdout.log')
assessment128 = read(Path(base['evidence_root']) / 'production-assess-v1/stdout.log')
require(assessment129['ready'] is False and assessment129['eligible_for_local_validation'] is True, 'local-only readiness')
require(set(assessment128['blockers']).issubset(assessment129['blockers']), 'all prior production blockers preserved')
require(assessment129['official_source_hashes_valid'] == 107, 'all current sources checked')
for folder, count in [('historical128-v1', 24), ('historical122-v1', 12)]:
    paths = list((args.evidence / folder).glob('*validation.json'))
    require(len(paths) == 1, 'one historical terminal receipt')
    record = read(paths[0])
    require(record['exit_code'] == 0 and record['exact_replays'] == count, 'historical exact replays')
changed = {p for p, h in base['runtime_hashes'].items() if sha(ROOT / p) != h}
require(changed == {
    'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
    'app/Modules/Fiscal/ConsumptionReform/CIndOpReference126.php',
    'app/Modules/Fiscal/ConsumptionReform/CreditInputPolicy122.php',
    'app/Modules/Fiscal/ConsumptionReform/DpsLayoutPolicy127.php',
    'app/Modules/Fiscal/ConsumptionReform/DpsForeignPolicy128.php'}, 'only declared inherited runtime files changed')
runtime = set(base['runtime_hashes']) | {'app/Modules/Fiscal/ConsumptionReform/ImportResponsibility129.php'}
report = {'created_at': datetime.now(timezone.utc).isoformat(), 'status': 'SCOPED129_VALIDATED_NOT_FULL_PHASE_ACCEPTANCE',
    'audit_checks': checks, 'tests': 318, 'assertions': 1220, 'full_suite_executed': False,
    'quality': {'phpstan': 'PASS', 'pint': 'PASS'}, 'native_decisions': 28, 'native_exact_replays': 28,
    'historical_sample_exact_replays': 36, 'new_import_xmls': 0, 'official_sources_valid': 107,
    'production_blockers': len(assessment129['blockers']), 'ready': False, 'eligible_for_local_validation': True,
    'bundle': 'bundles/'+bundle_path.name, 'bundle_sha256': sha(bundle_path),
    'runtime_hashes': {p: sha(ROOT / p) for p in sorted(runtime)},
    'formal_phase4b_groups_closed': 0, 'phase4b_complete': False, 'evidence_root': str(args.evidence),
    'scope': 'IMPORT_TAXPAYER_REFERENCE_AND_RESPONSIBILITY_NOT_FULL_IMPORT_DOCUMENT_OR_COLLECTION_CLOSURE'}
args.output.parent.mkdir(parents=True, exist_ok=True)
with args.output.open('x', encoding='utf-8', newline='\n') as handle:
    handle.write(json.dumps(report, ensure_ascii=False, indent=2)+'\n')
print(json.dumps({k: report[k] for k in ['status', 'audit_checks', 'tests', 'assertions', 'production_blockers']}))
