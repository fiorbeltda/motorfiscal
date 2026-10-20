"""Independent terminal104 evidence audit; local composition is not issuance or production approval."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re
import shutil

ROOT = Path(__file__).resolve().parents[1]
F = Path(r'D:\ProjetoSaasExports\phase4b-identity104-full-suite-20261019')
E = Path(r'D:\ProjetoSaasExports\phase4b-identity104-integration-20261019')
C = E / 'cli-v2'
P = Path(r'D:\ProjetoSaasExports\phase4b-identity103-local-preparation-20261016')
OLD_C = Path(r'D:\ProjetoSaasExports\phase4b-catalog101-integration-20261016\cli')
A = ROOT / 'storage/app/phase4b-identity104-development-20261019'
OLD = ROOT / 'storage/app/phase4b-catalog101-development-20261016/catalog101-development-validation.json'
TARGET = F / 'identity104-development-validation.json'
assert not A.exists() and not TARGET.exists(), 'Exclusive final acceptance'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    with p.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()

def save(p, value):
    with p.open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)

def canonical(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()

assert sha(OLD) == '900bd64cfa7a0eb7775974f936a9d12369929b12028c59fe50e3932a2ed11360'
proof = read(F / 'identity104-regression-validation.json')
assert proof['status'] == 'IDENTITY104_LOCAL_MAPPING_INTEGRATION_REGRESSION_VERIFIED'
preflight = read(F / 'preflight.json')
suite = read(F / 'full-suite.json')
assert proof['full_suite'] == suite and suite['exit_code'] == 0 and suite['full_suite_complete'] is True
assert suite['tests'] == 6529 and isinstance(suite['assertions'], int) and suite['assertions'] > 94755
assert not suite['input_files_changed'] and not suite['input_files_added'] and not suite['input_files_removed']
assert sha(F / 'full-suite.log') == suite['log_sha256']
assert re.search(r'Tests:\s+6529 passed\s+\(' + str(suite['assertions']) + r' assertions\)', (F / 'full-suite.log').read_text(encoding='utf-8'))
for path, digest in preflight['input_hashes'].items():
    assert sha(Path(path)) == digest, path
assert len(preflight['input_hashes']) == proof['unchanged_input_files'] > 9272
assert proof['runtime_hashes'] == preflight['runtime_hashes'] and len(proof['runtime_hashes']) == 79
for path, digest in proof['runtime_hashes'].items():
    assert sha(ROOT / path) == digest, path
raw = (P / 'PartialExportDpsIdentity103.php').read_bytes()
assert (ROOT / 'app/Modules/Fiscal/ConsumptionReform/PartialExportDpsIdentity103.php').read_bytes() == raw.replace(b'namespace Phase4bPrepared;', b'namespace App\\Modules\\Fiscal\\ConsumptionReform;')
assert sha(ROOT / 'tests/Fixtures/identity103-cases.json') == sha(P / 'identity103-cases.json')
bundle_path = ROOT / 'resources/fiscal' / proof['bundle']
assert sha(bundle_path) == proof['bundle_sha256']
bundle = read(bundle_path)
previous = read(ROOT / 'resources/fiscal/bundles/consumption-phase4b-2026.10.16-101-draft1.json')
assert bundle['status'] == 'SANDBOX_CANDIDATE'
for key in ['rtc_rate_catalog', 'rtc_transition_catalog']:
    assert bundle['resource_paths'][key] == previous['resource_paths'][key]
    assert bundle['content_hashes'][key] == previous['content_hashes'][key]
for role in ['rtc_code_catalog', 'fact_definitions', 'questions']:
    assert canonical(read(ROOT / 'resources/fiscal' / bundle['resource_paths'][role])) == bundle['content_hashes'][role]
for role in ['fact_definitions', 'questions']:
    old = read(ROOT / 'resources/fiscal' / previous['resource_paths'][role])
    new = read(ROOT / 'resources/fiscal' / bundle['resource_paths'][role])
    assert new[:len(old)] == old and len(new) == len(old) + 2
codes = read(ROOT / 'resources/fiscal' / bundle['resource_paths']['rtc_code_catalog'])
before = read(ROOT / 'resources/fiscal' / previous['resource_paths']['rtc_code_catalog'])
assert {k: v for k, v in codes.items() if k not in ['version', 'sources', 'partial_export_dps_identity_policy']} == {k: v for k, v in before.items() if k not in ['version', 'sources']}
assert codes['partial_export_dps_identity_policy'] == read(P / 'identity103-cases.json')['cases'][0]['policy']
assert bundle['official_sources'][:len(previous['official_sources'])] == previous['official_sources']
assert len(bundle['official_sources']) == 71
for source in bundle['official_sources']:
    assert sha(ROOT / 'resources/fiscal/sources/official' / source['file']) == source['sha256']
quality = {}
for label in ['final-phpstan', 'final-pint', 'final-production-assess104']:
    gate = read(F / (label + '.json'))
    assert gate['exit_code'] == 0
    for channel in ['stdout', 'stderr']:
        assert sha(F / (label + '.' + channel)) == gate[channel + '_sha256']
    quality[label] = gate
assert '[OK] No errors' in (F / 'final-phpstan.stdout').read_text(encoding='utf-8')
assert re.search(r'PASS.*562 files', (F / 'final-pint.stdout').read_text(encoding='utf-8'))
assessment = read(F / 'final-production-assess104.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_source_hashes_valid'] == assessment['official_sources_checked'] == 71
prior_assess = read(OLD_C / 'production-assess101.stdout')
assert set(assessment['blockers']) == set(prior_assess['blockers']) | {'DPS_IDENTITY_LOCAL_MAPPING_ONLY_NOT_ISSUANCE_APPROVAL'}
assert len(assessment['blockers']) == 123
corpus_path = C / 'identity104-cli-validation.json'
assert sha(corpus_path) == preflight['corpus_sha256']
corpus = read(corpus_path)
assert len(corpus['cases']) == 45 and corpus['native_decisions'] == corpus['exact_replays'] == 90
assert len(corpus['historical_replays']) == corpus['historical_exact_replays'] == 337
old_cases = {c['path']: c for c in read(OLD_C / 'catalog101-cli-validation.json')['cases']}
expected_exits = {'production-assess104': 0, **{'historical-' + str(i): 0 for i in range(337)}}
nominal_controls = 0
for case in corpus['cases']:
    assert sha(ROOT / case['path']) == case['scenario_sha256']
    current = read(ROOT / case['path'])
    assert datetime.fromisoformat(current['knowledge_at']) >= datetime.fromisoformat(case['original_knowledge_at'])
    if 'original_path' in case:
        assert sha(ROOT / case['original_path']) == case['original_sha256']
        assert {k: v for k, v in current['facts'].items() if k not in ['rtc.assess_export_dps_identity', 'rtc.export_dps_identity_records']} == read(ROOT / case['original_path'])['facts']
    for kind in ['rtc', 'integrated']:
        snapshot = C / case[kind + '_snapshot']
        label = case['name'] + '-' + kind
        expected_exits[label] = 1 if kind == 'rtc' and case['status'] == 'INVALID' else 0
        expected_exits[label + '-replay'] = 0
        assert sha(snapshot) == case[kind + '_snapshot_sha256']
        result = read(C / (label + '.stdout'))
        assert read(snapshot)['result'] == result
        assert result['status'] == case['status'] and case['reason'] in result['reason_codes']
        replay = read(C / (label + '-replay.stdout'))
        assert replay['replay_matches'] is True
        if kind == 'integrated':
            assert replay['result'] == result
        rtc = result if kind == 'rtc' else {r['type']: r['payload'] for r in result['trace']}['CONSUMPTION_REFORM']
        stages = {r['stage']: r for r in rtc['trace']}
        if case['identity_status'] is None:
            assert 'PARTIAL_EXPORT_DPS_IDENTITY' not in stages
        else:
            identity = stages['PARTIAL_EXPORT_DPS_IDENTITY']
            assert identity['status'] == case['identity_status'] and identity['reason'] == case['identity_reason']
            assert stages['PARTIAL_EXPORT_ISS_RESULTS']['status'] == 'LOCAL_CURRENT_EXPORT_ISS_RESULTS_BOUND'
            assert stages['PARTIAL_EXPORT_SP_RESULTS']['status'] == 'LOCAL_CURRENT_SP_EXPORT_RESULTS_BOUND'
            for flag in ['fiscal_document_identifiers_qualified', 'cnpj_check_digits_verified', 'municipal_authorization_verified',
                'cadastral_identity_authenticated', 'remote_uniqueness_verified', 'government_evidence_authenticated',
                'iss_supply_date_materially_qualified', 'documentary_currency_conversion_qualified', 'per_document_tax_amount_calculated',
                'document_obligation_complete', 'remote_deployment_proven', 'xml_generated', 'posting_authorized', 'production_ready']:
                assert identity[flag] is False
            if identity['status'] == 'LOCAL_DPS_IDENTITY_PAIR_BOUND':
                assert [d['dps_id'] for d in identity['documents']] == case['expected_ids']
                assert [d['document_reference'] for d in identity['documents']] == [current['facts']['rtc.export_foreign_document_id'], current['facts']['rtc.export_domestic_document_id']]
        if 'original_path' in case and 'PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION' in stages:
            control_case = old_cases[case['original_path']]
            control = read(OLD_C / control_case[kind + '_snapshot'])['result']
            control_rtc = control if kind == 'rtc' else {r['type']: r['payload'] for r in control['trace']}['CONSUMPTION_REFORM']
            control_stages = {r['stage']: r for r in control_rtc['trace']}
            assert stages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION'] == control_stages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION']
            nominal_controls += 1
gates = {p.name.removesuffix('-execution.json'): p for p in C.glob('*-execution.json')}
assert set(gates) == set(expected_exits)
for label, path in gates.items():
    gate = read(path)
    assert gate['exit_code'] == gate['expected_exit_code'] == expected_exits[label]
    for channel in ['stdout', 'stderr']:
        assert sha(C / (label + '.' + channel)) == gate[channel + '_sha256']
for i, prior in enumerate(corpus['historical_replays']):
    assert sha(Path(prior['path'])) == prior['sha256']
    assert read(C / ('historical-' + str(i) + '.stdout'))['replay_matches'] is True
assert nominal_controls > 0
gate = dict(quality['final-production-assess104'])
gate['log_sha256'] = gate['stdout_sha256']
report = {**proof, 'status': 'IDENTITY104_LOCAL_MAPPING_INTEGRATION_DEVELOPMENT_VERIFIED',
    'created_at': datetime.now(timezone.utc).isoformat(), 'previous101_acceptance_sha256': sha(OLD),
    'scope': 'LOCAL_DPS_COMPOSITION_REFERENCE_PAIR_BINDING_AFTER_CURRENT_RTC_ISS_NOT_ISSUANCE_APPROVAL',
    'bundle_id': bundle['bundle_id'], 'scenarios': 45, 'prior_exact_replays': 337,
    'new_permanent_tests': 233, 'directed_tests': 3378, 'directed_assertions': 42846,
    'nominal_controls_equal_to_captured101': nominal_controls,
    'corpus_sha256': sha(corpus_path), 'regression_preflight_sha256': sha(F / 'preflight.json'),
    'regression_validation_sha256': sha(F / 'identity104-regression-validation.json'),
    'final_quality': {'identity104-final-production-assess': gate},
    'quality_evidence_on_d': {p.name: sha(p) for p in F.glob('final-*') if p.is_file()},
    'remote_deployment_proven': False, 'legal_applicability_approved': False, 'posting_authorized': False}
save(TARGET, report)
A.mkdir()
receipt = {}
for source, target in [(TARGET, A / TARGET.name), (F / 'final-production-assess104.stdout', A / 'identity104-final-production-assess.log')]:
    shutil.copy2(source, target)
    assert sha(source) == sha(target)
    receipt[target.name] = sha(target)
save(A / 'evidence-copy-receipt.json', {'created_at': datetime.now(timezone.utc).isoformat(), 'copied_files': receipt, 'large_evidence_retained_on_d': True})
assert sha(OLD) == report['previous101_acceptance_sha256']
print(json.dumps({'status': report['status'], 'tests': suite['tests'], 'assertions': suite['assertions'], 'runtime_hashes': 79,
    'copied_files_verified': len(receipt), 'acceptance_sha256': sha(A / TARGET.name), 'production_ready': False}), flush=True)
