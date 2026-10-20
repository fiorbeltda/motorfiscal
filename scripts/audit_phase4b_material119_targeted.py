"""Independent targeted119 evidence audit; not a full regression or fiscal acceptance."""
from pathlib import Path
from datetime import datetime, timezone
from decimal import Decimal, ROUND_CEILING
import hashlib
import json
import re

R = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-material-input119-integration-20261020')
TARGET = E/'material119-targeted-validation.json'
assert not TARGET.exists(), 'Exclusive targeted audit; do not overwrite evidence'
checks = 0
def check(condition, label):
    global checks
    assert condition, label
    checks += 1
def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))
def sha(path):
    with path.open('rb') as f:
        return hashlib.file_digest(f, 'sha256').hexdigest()
def canonical(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
def execution(label):
    folder = E/label
    record = read(folder/'execution.json')
    check(record['exit_code'] == 0, label+' terminal success')
    for channel in ['stdout', 'stderr']:
        check(sha(folder/(channel+'.log')) == record[channel+'_sha256'], label+' '+channel+' bytes')
    return (folder/'stdout.log').read_text(encoding='utf-8', errors='strict')

archive = read(E/'before-integration-archive.json')
check(archive['archive_count'] == len(archive['archived_files']) == 91, 'pre119 archive membership')
engine = 'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php'
for path, digest in archive['archived_files'].items():
    check(sha(E/'before-integration'/path) == digest, 'immutable archived '+path)
    if path != engine:
        check(sha(R/path) == digest, 'unchanged previous runtime/resource '+path)
check(sha(R/engine) != archive['runtime_hashes'][engine], 'new engine actually installed')
runtime = {path: sha(R/path) for path in archive['runtime_hashes']}
for name in ['MaterialInputPolicy119.php', 'OrdinaryAdjustmentInput119.php', 'CollectionBasisReference119.php']:
    path = 'app/Modules/Fiscal/ConsumptionReform/'+name
    check((R/path).is_file(), 'new119 class '+name)
    runtime[path] = sha(R/path)
check(len(runtime) == 91, 'new runtime inventory')

version = '2026.10.20-119-draft1'
bundle_path = R/'resources/fiscal/bundles'/('consumption-phase4b-'+version+'.json')
bundle = read(bundle_path)
catalog = read(R/'resources/fiscal'/bundle['resource_paths']['rtc_code_catalog'])
controls = read(R/'resources/fiscal'/bundle['production_controls']['path'])
check(canonical(catalog) == bundle['content_hashes']['rtc_code_catalog'], 'canonical119 catalog binding')
check(canonical(controls) == bundle['production_controls']['sha256'], 'canonical119 controls binding')
check(catalog['version'] == bundle['catalog_versions']['rtc_codes'] == version, 'mandatory119 version')
check(bundle['predecessor_bundle_id'] == 'CONSUMPTION-PHASE4B-2026.10.20-116-draft2', 'actual predecessor')
policy = catalog['material_input_policy119']
check(policy['source']['observed_at_raw'] == '2026-10-20T12:49:02.0276095+00:00', 'raw100ns observation retained')
raw_end = policy['source']['capture_completed_at_raw']
fraction = Decimal('0.'+raw_end.split('.')[1].split('+')[0])
ceil_us = (fraction*Decimal(1000000)).to_integral_value(rounding=ROUND_CEILING)
check(policy['available_at'] == '2026-10-20T12:49:04.'+str(ceil_us)+'+00:00', 'conservative capture-completion ceiling')
check(datetime.fromisoformat(bundle['knowledge_available_at']) >= datetime.fromisoformat(policy['available_at']), 'no backdated bundle availability')
check(policy['adjustments']['amount_types'] == ['DECIMAL_STRING', 'JSON_INTEGER'], 'no bool or binary amount contract')
check(policy['adjustments']['repeated_evidence_context'] == 'DISTINCT_LINE_REFERENCES_REQUIRED', 'no silent deduplication')
check(set(policy['collection_references']) == {'SUPPLIER','ACQUIRER','SPLIT_PAYMENT'}, 'bounded ordinary reference methods')
for source in bundle['official_sources']:
    check(sha(R/'resources/fiscal/sources/official'/source['file']) == source['sha256'], 'official bytes '+source['id'])
source = next(s for s in bundle['official_sources'] if s['id'] == 'INPUT119_LC214_OBSERVED117')
check(source['sha256'] == policy['source']['sha256'] == '3ccd180f88a872ff118fc3108fb119837fd2c09101f16d1e032662c22b7140f5', 'capture117 source binding')
check(source['effective_from'] is None and source['published_on'] is None, 'no invented fiscal validity')

build = json.loads(execution('build-v1'))
check(build['bundle_raw_sha256'] == sha(bundle_path), 'build exact manifest bytes')
check(build['official_sources'] == 98, '98 observed sources')
targeted = execution('directed-v2')
match = re.search(r'Tests:\s+(\d+) passed\s+\((\d+) assertions\)', targeted)
check(match is not None, 'actual Pest terminal summary')
check((int(match[1]),int(match[2])) == (350,1079), 'observed targeted test and assertion counts')
check('[OK] No errors' in execution('phpstan-final-v1'), 'general PHPStan')
check(re.search(r'PASS.*593 files', execution('pint-final-v1')) is not None, 'general Pint593')
assessment = json.loads(execution('production-assess-v1'))
check(assessment['ready'] is False and assessment['eligible_for_local_validation'] is True, 'production remains blocked')
check(assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 98, '98 source hashes assessed')
check(len(assessment['blockers']) == 127, '127 explicit production blockers')
check(assessment['development_blockers'] == [], 'no local development blockers')
for path, digest in runtime.items():
    check(sha(R/path) == digest, 'frozen current runtime '+path)
report = {'created_at': datetime.now(timezone.utc).isoformat(),
          'status': 'MATERIAL119_INSTALLED_TARGETED_VERIFIED_NOT_FULL_REGRESSION_ACCEPTANCE',
          'bundle': str(bundle_path.relative_to(R)).replace('\\','/'), 'bundle_sha256': sha(bundle_path),
          'runtime_hashes': runtime, 'pre119_archive_sha256': sha(E/'before-integration-archive.json'),
          'audit_checks': checks, 'targeted_tests':350, 'targeted_assertions':1079,
          'phpstan_complete':True, 'pint_files':593, 'official_sources_valid':98,
          'production_blockers':127, 'full119_suite_started':False, 'full_suite_complete':False,
          'new119_cli_corpus_complete':False, 'new119_historical_replays_complete':False,
          'last_full_acceptance_generation':112, 'known_defects_in_historical_bundles_preserved':True,
          'original_requirement_ids_reviewed':44, 'original_requirement_ids_remaining':36,
          'formal_phase4b_groups_closed':0, 'phase4b_complete':False, 'production_ready':False,
          'next_step':'Rebase synthetic119 price/identity/composition transports after bundle availability;preserve literal lifecycle events,qualify124+short geography cases,capture native/replays and full regression before any acceptance.'}
with TARGET.open('x',encoding='utf-8') as f:
    json.dump(report,f,ensure_ascii=False,indent=2)
print(json.dumps({'status':report['status'],'audit_checks':checks,'report_sha256':sha(TARGET),
                  'targeted_tests':350,'full_acceptance':False,'production_ready':False}),flush=True)
