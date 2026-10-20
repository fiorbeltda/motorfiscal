"""Audit pair95 with independent configured knowledge, original sources and native regenerated counterexamples."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-iss-export95-pair-binding-20261016')
Q = Path(r'D:\ProjetoSaasExports\phase4b-iss-export95-preparation-20261016')
S = Path(r'D:\ProjetoSaasExports\phase4b-document94-pair-binding-gap-20261016')
REGRESSION = Path(r'D:\ProjetoSaasExports\phase4b-document94-component-integration-20261016')
TARGET = E / 'iss-export95-pair-binding-validation.json'
assert not TARGET.exists(), 'Exclusive audit output'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    with p.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()

def canonical(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()

frozen = read(REGRESSION / 'preflight.json')['input_hashes']
for path, digest in frozen.items():
    assert sha(Path(path)) == digest, path
qualifier_proof = read(Q / 'iss-export95-preparation-validation.json')
assert qualifier_proof['native_cases'] == 116 and qualifier_proof['runtime_integrated'] is False
assert sha(Q / 'IssExportResultQualifier95-formatted.php') == qualifier_proof['native_file_hashes']['IssExportResultQualifier95.php']
sources = read(E / 'source-index.json')
prior_sources = {c['name']: c['native_captures']['rtc']['sha256'] for c in read(S / 'document94-native-pair-binding-gap.json')['cases']}
assert len(sources) == 6
for source in sources:
    assert sha(E / source['file']) == source['sha256'] == prior_sources[source['name']]
print('PASS:5360 frozen94 inputs, audited qualifier95 and six native source captures unchanged', flush=True)

def run(label, args):
    subprocess.run([sys.executable, str(ROOT / 'scripts/capture_phase4b_iss_export95_pair_check.py'), label, *args], cwd=ROOT, check=True)
    record = read(E / (label + '.json'))
    assert record['exit_code'] == 0
    for channel in ['stdout', 'stderr']:
        assert sha(E / (label + '.' + channel)) == record[channel + '_sha256']
    return (E / (label + '.stdout')).read_text(encoding='utf-8')

files = {'PartialExportIssResultPair95.php': E / 'PartialExportIssResultPair95-formatted-v2.php',
         'probe-pair95.php': E / 'probe-pair95-formatted-v2.php', 'cases.json': E / 'cases.json',
         'source-index.json': E / 'source-index.json'}
expected_files = {name: sha(path) for name, path in files.items()}
remote = json.loads(run('audit-file-binding-v1', ['-r',
    '$out=[];foreach(["PartialExportIssResultPair95.php","probe-pair95.php","cases.json","source-index.json"] as $f)'
    '{$out[$f]=hash_file("sha256","/tmp/phase4b-iss-export95-pair/".$f);}echo json_encode($out,JSON_THROW_ON_ERROR);']))
assert remote == expected_files
remote_qualifier = run('audit-qualifier-binding-v1', ['-r', 'echo hash_file("sha256","/tmp/phase4b-iss-export95/IssExportResultQualifier95.php");'])
assert remote_qualifier == qualifier_proof['native_file_hashes']['IssExportResultQualifier95.php']
native = json.loads(run('native-audit-v1', ['/tmp/phase4b-iss-export95-pair/probe-pair95.php']))
assert native['status'] == 'ISS95_SOURCE_BOUND_PAIR_CASES_PASSED' and native['passed'] == 58 and native['assertions'] == 856
cases = read(E / 'cases.json')
results = {c['name']: c['result'] for c in native['cases']}
assert len(results) == len(cases) == 58
limits = ['economic_operation_identity_authenticated', 'fiscal_document_identifiers_qualified',
          'iss_supply_date_materially_qualified', 'documentary_currency_conversion_qualified',
          'per_document_tax_amount_calculated', 'full_operation_iss_rewritten', 'legal_applicability_approved',
          'xml_generated', 'document_obligation_complete', 'posting_authorized', 'production_ready']
bound = 0
for case in cases:
    result = results[case['name']]
    assert result['status'] == case['expected_status'] and result['reason'] == case['expected_reason']
    assert all(result[flag] is False for flag in limits)
    if case['expected_scopes'] is not None:
        assert result['source_native_replay_verified'] is True
        assert result['source_knowledge_matches_configured_bundle'] is True
        assert [d['role'] for d in result['documents']] == ['RTC_FOREIGN', 'RTC_DOMESTIC']
        assert [d['iss_result']['result_scope'] for d in result['documents']] == case['expected_scopes']
        for record, document in zip(sorted(case['records'], key=lambda r: r['role'], reverse=True), result['documents']):
            # Explicit role lookup avoids treating list order as an identity.
            context = next(r['iss_context'] for r in case['records'] if r['role'] == document['role'])
            assert canonical(context) == document['iss_context_hash']
        bound += 1
assert bound == 10
assert results['native-brl-control']['status'] == 'ACTION_REQUIRED'
assert results['source-resigned_body']['reason'] == 'ISS_PAIR95_SOURCE_NATIVE_REPLAY_MISMATCH'
assert [d['iss_result']['candidate_tribISSQN'] for d in results['foreign-rtc-can-have-iss-result-br']['documents']] == [None, None]
assert [d['iss_result']['candidate_tribISSQN'] for d in results['domestic-rtc-result-not-forced-br']['documents']] == ['3', '3']
variants = native['regenerated_native_snapshots']
assert len(variants) == 4
parent = variants['parent-may-name-foreign-child']['input']['facts']
assert parent['rtc.document_id'] == parent['rtc.export_foreign_document_id'] != parent['rtc.export_domestic_document_id']
children = variants['two-child-documents-must-differ']['input']['facts']
assert children['rtc.export_foreign_document_id'] == children['rtc.export_domestic_document_id']
assert results['two-child-documents-must-differ']['reason'] == 'ISS_PAIR95_SOURCE_DOCUMENT_REFERENCE_ALIAS'
new_brl = variants['brl-with-new-explicit-references-not-old-corpus-acceptance']['input']['facts']
old_brl = read(E / 'sources/brl-control.json')['input']['facts']
assert old_brl.get('rtc.export_foreign_document_id') is None and new_brl['rtc.export_foreign_document_id']
assert new_brl['rtc.gross_currency'] == 'BRL'
assert '[OK] No errors' in run('phpstan-audit-v1', ['vendor/bin/phpstan', 'analyse', '--no-progress',
    '/tmp/phase4b-iss-export95-pair/PartialExportIssResultPair95.php', '/tmp/phase4b-iss-export95/IssExportResultQualifier95.php'])
style = run('pint-audit-v1', ['vendor/bin/pint', '--test', '/tmp/phase4b-iss-export95-pair/PartialExportIssResultPair95.php', '/tmp/phase4b-iss-export95-pair/probe-pair95.php'])
assert 'PASS' in style and '2 files' in style
assert read(E / 'phpstan-v1.json')['exit_code'] == 1, 'Preserve initial static failure'
assert read(E / 'native-v1.json')['stdout_sha256'] == read(E / 'native-v2.json')['stdout_sha256']
for path, digest in frozen.items():
    assert sha(Path(path)) == digest, path
report = {'status': 'ISS95_SOURCE_BOUND_DOCUMENT_PAIR_RESULT_PREPARATION_VERIFIED_NOT_INTEGRATED',
          'created_at': datetime.now(timezone.utc).isoformat(), 'native_cases': 58, 'native_checks': 856,
          'locally_bound_cases': bound, 'rejected_cases': len(cases) - bound, 'original_source_captures': 6,
          'regenerated_native_variants': 4, 'frozen94_inputs_unchanged': len(frozen),
          'native_files': expected_files, 'qualifier95_validation_sha256': sha(Q / 'iss-export95-preparation-validation.json'),
          'regression94_preflight_sha256': sha(REGRESSION / 'preflight.json'),
          'source_bundle_file_sha256': sha(ROOT / 'resources/fiscal/bundles/consumption-phase4b-2026.10.14-73-draft1.json'),
          'trusted_knowledge_canonical_hash': native['trusted_knowledge_hash'],
          'evidence_hashes': {p.relative_to(E).as_posix(): sha(p) for p in E.rglob('*') if p.is_file()},
          'initial_static_failure_preserved': True, 'runtime_integrated': False, 'phase4b_complete': False,
          **{flag: False for flag in limits},
          'next_required': ['Install with permanent tests and a versioned trusted policy after regression94 terminal acceptance.',
                            'Qualify each document identity, ISS supply date, documentary currency conversion and independent bases.',
                            'Materialize the two actual documents and schema validation without conflating RTC scope and ISS result.']}
with TARGET.open('x', encoding='utf-8') as stream:
    json.dump(report, stream, ensure_ascii=False, indent=2)
print(json.dumps({k: v for k, v in report.items() if k not in ['evidence_hashes', 'next_required']}), flush=True)
