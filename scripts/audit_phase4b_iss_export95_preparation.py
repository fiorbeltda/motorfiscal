"""Audit95 isolated result-evidence candidate against immutable94 regression inputs; no engine installation."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-iss-export95-preparation-20261016')
SOURCE = Path(r'D:\ProjetoSaasExports\phase4b-document94-iss-bridge-sources-20261016')
REGRESSION = Path(r'D:\ProjetoSaasExports\phase4b-document94-component-integration-20261016')
TARGET = E / 'iss-export95-preparation-validation.json'
assert not TARGET.exists(), 'Exclusive audit output'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    with p.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()

source = read(SOURCE / 'iss-bridge-primary-observation.json')
lc = next(s for s in source['sources'] if s['file'] == 'lc116-current.html')
assert lc['sha256'] == '3ae4d3fc894ced12874364e366867b736c81369352cbaf97c70d57e534cdf86c'
assert sha(SOURCE / lc['file']) == lc['sha256']
assert lc['observed_at'] == '2026-10-16T14:16:16.333043+00:00'
assert lc['engine_rule_activated'] is False and lc['operation_municipal_applicability_proven'] is False
assert 'resultado aqui se verifique' in lc['selected_articles'][0]['text']
frozen = read(REGRESSION / 'preflight.json')['input_hashes']
for path, digest in frozen.items():
    assert sha(Path(path)) == digest, path
print('PASS:all ' + str(len(frozen)) + ' frozen94 input hashes unchanged during isolated95 preparation', flush=True)

def run(label, args):
    subprocess.run([sys.executable, str(ROOT / 'scripts/capture_phase4b_iss_export95_check.py'), label, *args],
                   cwd=ROOT, check=True)
    record = read(E / (label + '.json'))
    assert record['exit_code'] == 0
    for channel in ['stdout', 'stderr']:
        assert sha(E / (label + '.' + channel)) == record[channel + '_sha256']
    return (E / (label + '.stdout')).read_text(encoding='utf-8')

native_files = {'IssExportResultQualifier95.php': E / 'IssExportResultQualifier95-formatted.php',
                'probe95.php': E / 'probe95-formatted.php', 'cases.json': E / 'cases.json'}
local_hashes = {name: sha(path) for name, path in native_files.items()}
remote = json.loads(run('audit-file-binding-v1', ['-r',
    '$out=[];foreach(["IssExportResultQualifier95.php","probe95.php","cases.json"] as $f)'
    '{$out[$f]=hash_file("sha256","/tmp/phase4b-iss-export95/".$f);}echo json_encode($out,JSON_THROW_ON_ERROR);']))
assert remote == local_hashes
native = json.loads(run('native-audit-v1', ['/tmp/phase4b-iss-export95/probe95.php']))
assert native['passed'] == 116 and native['checks'] == 1553
cases = read(E / 'cases.json')
assert len(cases) == 116 and len({c['name'] for c in cases}) == 116
results = {c['name']: c['result'] for c in native['cases']}
limits = ['government_evidence_authenticated', 'legal_applicability_approved',
          'documentary_currency_conversion_qualified', 'tax_calculated', 'iss_full_operation_rewritten',
          'xml_generated', 'document_obligation_complete', 'posting_authorized', 'production_ready']
for case in cases:
    result = results[case['name']]
    assert result['status'] == case['expected_status'] and result['reason'] == case['expected_reason']
    assert all(result[flag] is False for flag in limits)
    assert 'tax_amount' not in result and 'vISSQN' not in result and 'xml' not in result
    if case['expected_result_scope'] is not None:
        proof = case['context']['result_proof']
        material = {k: v for k, v in proof.items() if k != 'source_hash'}
        digest = hashlib.sha256(json.dumps(material, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
        assert proof['source_hash'] == digest == result['result_proof_hash']
        assert proof['kind'] == 'SERVICE_RESULT_MATERIAL_EVIDENCE'
        assert result['independent_result_evidence_locally_bound'] is True
assert results['foreign-payer-but-result-br']['candidate_tribISSQN'] is None
assert results['qualified-domestic-sibling']['candidate_tribISSQN'] is None
assert results['zero-id-not-discarded']['document_id'] == '0'
for label in ['foreign-proof-swapped-onto-sibling', 'result-scope-MIXED', 'substitute-PAYMENT',
              'substitute-RECIPIENT_DOMICILE', 'substitute-RTC_FOREIGN_CONSUMPTION', 'period-2033-01-01']:
    assert results[label]['status'] == 'ACTION_REQUIRED'
assert '[OK] No errors' in run('phpstan-audit-v1', ['vendor/bin/phpstan', 'analyse', '--no-progress', '/tmp/phase4b-iss-export95/IssExportResultQualifier95.php'])
style = run('pint-audit-v1', ['vendor/bin/pint', '--test', '/tmp/phase4b-iss-export95/IssExportResultQualifier95.php', '/tmp/phase4b-iss-export95/probe95.php'])
assert 'PASS' in style and '2 files' in style
assert {name: sha(path) for name, path in native_files.items()} == local_hashes
for path, digest in frozen.items():
    assert sha(Path(path)) == digest, path
report = {'status': 'ISS95_INDEPENDENT_RESULT_EVIDENCE_PREPARATION_VERIFIED_NOT_INTEGRATED',
          'created_at': datetime.now(timezone.utc).isoformat(), 'native_cases': 116, 'native_checks': 1553,
          'scope': 'LOCAL_SOFTWARE_RESULT_EVIDENCE_NOT_LEGAL_ACCEPTANCE',
          'native_file_hashes': local_hashes, 'frozen94_input_hashes_unchanged': len(frozen),
          'regression94_preflight_sha256': sha(REGRESSION / 'preflight.json'),
          'source_observation_sha256': sha(SOURCE / 'iss-bridge-primary-observation.json'),
          'evidence_hashes': {p.name: sha(p) for p in E.iterdir() if p.is_file()},
          'runtime_integrated': False, 'engine_branch_implemented': False,
          'phase4b_complete': False, **{flag: False for flag in limits},
          'next_required': ['Integrate the qualified export-result branch and permanent tests after regression94 is terminal and accepted.',
                            'Bind distinct document contexts to immutable source operations and reconcile independent ISS/RTC bases.',
                            'Qualify documentary currency conversion separately; do not import RTC quote timing automatically.',
                            'Materialize and validate the separate export/domestic documents under a proven schema/environment contract.']}
with TARGET.open('x', encoding='utf-8') as stream:
    json.dump(report, stream, ensure_ascii=False, indent=2)
print(json.dumps({k: v for k, v in report.items() if k not in ['evidence_hashes', 'next_required']}), flush=True)
