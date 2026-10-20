"""Verify captured primary sources, staged native SP checks and immutable96 inputs independently."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-document97-sp-primary-research-20261016-v2')
F = Path(r'D:\ProjetoSaasExports\phase4b-export-iss96-full-suite-20261016')
TARGET = E / 'sp97-preparation-validation.json'
assert not TARGET.exists()

def read(path): return json.loads(path.read_text(encoding='utf-8'))
def sha(path):
    with path.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
def save(path, payload):
    with path.open('x', encoding='utf-8') as stream: json.dump(payload, stream, ensure_ascii=False, indent=2)

observations = read(E / 'primary-source-observation.json')
assert len(observations['sources']) == 7
rendered = []
for source in observations['sources']:
    assert source['status'] == 'PRIMARY_CONTENT_VERIFIED_RENDERED_WHERE_PDF'
    assert source['tls_validation'] == 'WINDOWS_DEFAULT_ENABLED'
    assert sha(E / source['file']) == source['sha256']
    assert source['rules_activated'] is False and source['operation_applicability_proven'] is False
    assert source['effective_from'] is None
    for page in source.get('rendered_pages', []):
        assert (E / page).read_bytes().startswith(b'\x89PNG\r\n\x1a\n')
        rendered.append({'file': page, 'sha256': sha(E / page), 'review': 'VISUALLY_REVIEWED_LEGIBLE_COMPLETE_PAGE'})
assert len(rendered) == 4
pn = (E / 'sp-pn4-2016-consolidado.html.txt').read_text(encoding='utf-8')
for clause in ['elemento material, imaterial ou o interesse econômico', 'etapa mensal', 'vinculado a pessoa localizada no Brasil',
               'ônus de comprovar documentalmente', 'que com ele discordem']:
    assert clause in pn
details = (E / 'sp-pn4-2016-detalhes.html.txt').read_text(encoding='utf-8')
assert 'SEM REVOGAÇÃO EXPRESSA' in details and 'Data de publicação 10/11/2016' in details
assert 'Salvo disposição de lei em contrário' in (E / 'ctn-current.html.txt').read_text(encoding='utf-8')
save(E / 'primary-source-review.json', {'created_at': datetime.now(timezone.utc).isoformat(),
    'visual_review_complete': True, 'reviewed_pdf_pages': rendered,
    'review_scope': ['PN4/2016 full text and details', 'SC51/2012 both pages', 'SC8/2015 both pages',
        'CTN143 and selected clauses Lei13701 art2 and Decreto53151 arts16-17,117; not an exhaustive municipal-law audit'],
    'interpretation': {
        'SC51_2012': 'Imported continuing engineering services7.03/7.17: BCB closing sell last business day of month of effective service, in the consultation context.',
        'SC8_2015': 'Imported consulting17.01: final report completion, not payment, and BCB closing sell on completion day, in the consultation context.',
        'PN4_2016': 'SP supplier establishment, foreign result subject and documentary absence of software BR-person link; proportional monthly stage for continuing service.',
        'CTN143': 'Baseline FX day of taxable event subject to contrary law; does not itself prove a generic sell quote or payment-day policy.',
        'revocation_limit': 'Portal no express revocation is not a complete applicability audit. PN4 art4 revokes conflicting older consultations, not all older consultations automatically.'},
    'export_fx_rule_qualified': False, 'legal_applicability_approved': False, 'rules_activated': False, 'production_ready': False})
native_record = read(E / 'native-final-v2.json')
for label in ['native-final-v2', 'phpstan-final-v2', 'pint-final-v2']:
    gate = read(E / (label + '.json'))
    assert gate['exit_code'] == 0
    for channel in ['stdout', 'stderr']: assert sha(E / (label + '.' + channel)) == gate[channel + '_sha256']
assert '[OK] No errors' in (E / 'phpstan-final-v2.stdout').read_text(encoding='utf-8')
style = (E / 'pint-final-v2.stdout').read_text(encoding='utf-8')
assert 'PASS' in style and '2 files' in style
native = read(E / 'native-final-v2.stdout')
assert native['status'] == 'SP97_NATIVE_DOCUMENTARY_CASES_PASSED' and native['passed'] == 140 and native['checks'] == 2314
cases = read(E / 'cases-v2.json')
v1 = read(E / 'cases.json')
assert len(cases) == 140 and len({c['name'] for c in cases}) == 140
changed = [(a, b) for a, b in zip(v1, cases, strict=True) if a != b]
assert len(changed) == 1 and changed[0][0]['name'] == 'proof-missing-source_hash'
assert changed[0][1]['expected_status'] == 'ACTION_REQUIRED'
assert {k: v for k, v in changed[0][0].items() if k != 'expected_reason'} == {k: v for k, v in changed[0][1].items() if k != 'expected_reason'}
limits = ['government_evidence_authenticated', 'legal_applicability_approved', 'source_effective_date_proven',
    'iss_supply_date_materially_qualified', 'documentary_currency_conversion_qualified', 'tax_calculated',
    'xml_generated', 'document_obligation_complete', 'posting_authorized', 'production_ready']
counts = {}
for case in cases:
    result = native['cases'][case['name']]
    assert result['status'] == case['expected_status'] and result['reason'] == case['expected_reason']
    assert all(result[key] is False for key in limits)
    assert all(key not in result for key in ['vISSQN', 'tax_amount', 'xml', 'fx_rate'])
    counts[result['status']] = counts.get(result['status'], 0) + 1
    if result['status'] != 'ACTION_REQUIRED':
        value = case['proof']['value']
        expected = value['result_subject_scope'] == 'ABROAD' and value['br_person_link'] == 'NO_BR_LINK'
        assert result['export_non_incidence_candidate'] is expected
        assert result['candidate_tribISSQN'] == ('3' if expected else None)
        assert value['supplier_establishment_municipality'] == '3550308'
        assert value['national_result_proof_hash'] == case['context']['result_proof']['source_hash']
        assert value['service_list_item'] == '1'
        if value['service_execution'] == 'CONTINUING_MONTHLY':
            assert value['monthly_stage']['period'] == value['supply_on'][:7]
            assert value['monthly_stage']['completed_on'] == value['supply_on']
assert native['cases']['foreign-subject-but-software-linked-to-br']['export_non_incidence_candidate'] is False
files = {'SpSoftwareExportResult97.php': sha(E / 'SpSoftwareExportResult97-formatted.php'),
    'probe97.php': sha(E / 'probe97-formatted.php'), 'cases.json': sha(E / 'cases-v2.json')}
subprocess.run([sys.executable, str(ROOT / 'scripts/capture_phase4b_sp97_check.py'), 'binding-final-v2', '-r',
    '$o=[];foreach(["SpSoftwareExportResult97.php","probe97.php","cases.json"] as $f)'
    '{$o[$f]=hash_file("sha256","/tmp/phase4b-sp97/".$f);}echo json_encode($o,JSON_THROW_ON_ERROR);'], cwd=ROOT, check=True)
assert read(E / 'binding-final-v2.stdout') == files
preflight = read(F / 'preflight.json')
for path, digest in preflight['input_hashes'].items(): assert sha(Path(path)) == digest, path
assert len(preflight['input_hashes']) == 6121
for path, digest in preflight['runtime_hashes'].items(): assert sha(ROOT / path) == digest, path
assert len(preflight['runtime_hashes']) == 75
acceptance = ROOT / 'storage/app/phase4b-export-iss96-development-20261016/export-iss96-development-validation.json'
assert sha(acceptance) == '5b44a4cd995dc8ae3e8c8a08f03a4361ae40c671aa151f79234286b4974f3453'
report = {'status': 'SP97_MUNICIPAL_SOFTWARE_RESULT_PREPARATION_VERIFIED_NOT_INTEGRATED',
    'created_at': datetime.now(timezone.utc).isoformat(), 'native_cases': 140, 'native_checks': 2314, 'case_statuses': counts,
    'primary_sources': 7, 'pdf_pages_visually_reviewed': 4, 'native_file_hashes': files,
    'frozen96_input_hashes_unchanged': 6121, 'runtime96_hashes_unchanged': 75,
    'acceptance96_sha256': sha(acceptance), 'source_review_sha256': sha(E / 'primary-source-review.json'),
    'evidence_hashes': {p.relative_to(E).as_posix(): sha(p) for p in E.rglob('*') if p.is_file()},
    'runtime_integrated': False, 'engine_branch_implemented': False, 'phase4b_complete': False,
    **{key: False for key in limits},
    'next_required': ['Integrate national95/current96 and municipal97 conditions into a versioned current-facts branch with two independent documentary proofs and municipality binding.',
        'Add permanent tests, native scenarios and exact historical replays; run full suite and final acceptance before treating new runtime as accepted.',
        'Qualify documentary FX and material service date separately; neither consultation imports nor RTC allocation determines export ISS automatically.',
        'Reconcile ISS/RTC bases and qualify actual document identifiers; materialize separate documents using proven schema/environment/date.']}
save(TARGET, report)
print(json.dumps({k: v for k, v in report.items() if k not in ['evidence_hashes', 'next_required', 'native_file_hashes']}))
