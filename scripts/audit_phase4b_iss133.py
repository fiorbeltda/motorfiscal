"""Audit scoped133 sources, deterministic local calculations and preservation of prior fiscal behavior."""
import copy
import hashlib
import html
import json
import os
import re
from datetime import datetime, timezone
from pathlib import Path
from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[1]
E = Path('D:/ProjetoSaasExports/phase4b-iss133-20261022-v1')
OUT = ROOT / 'storage/app/phase4b-iss133-development-20261022'
checks = 0


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def check(ok, label):
    global checks
    if not ok:
        raise AssertionError(label)
    checks += 1


def write(path, value):
    with path.open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)
        stream.flush()
        os.fsync(stream.fileno())


prior_path = ROOT / 'storage/app/phase4b-values132-development-20261022/values132-scoped-validation.json'
check(sha(prior_path) == '1b9042995b355aa4fcc480a5d47b8cd218a28c3e12b8749851beabc55cd2e174', 'prior receipt intact')
prior = read(prior_path)
for name, digest in prior['runtime_hashes'].items():
    check(sha(ROOT / name) == digest, 'inherited runtime unchanged ' + name)
bundle = read(ROOT / 'resources/fiscal' / prior['fiscal_bundle_unchanged'])
for source in bundle['official_sources']:
    check(sha(ROOT / 'resources/fiscal/sources/official' / source['file']) == source['sha256'], 'prior official source ' + source['id'])
capture = read(E / 'primary-v1/capture.json')
check(len(capture['records']) == 5, 'five original municipal sources')
for source in capture['records']:
    check(sha(E / 'primary-v1' / source['file']) == source['sha256'], 'original primary ' + source['file'])
    check(sha(ROOT / 'resources/fiscal/sources/official' / ('iss133-' + source['file'])) == source['sha256'], 'runtime primary ' + source['file'])
pdf = PdfReader(E / 'primary-v1/sp-annex1-2026.pdf')
row = re.sub(r'\s+', ' ', pdf.pages[8].extract_text())
notes = re.sub(r'\s+', ' ', pdf.pages[34].extract_text())
check(len(pdf.pages) == 36 and '2660 1.01 Análise e desenvolvimento de sistemas PJ 2,90%' in row, 'granular rate from current annex page9')
check('2692 1.04' in row, 'distinct program-development code not conflated')
check('NOTA 23: Código de serviço instituído' in notes and 'vigência a partir de 13/02/18' in notes, 'code effect from note23')
check(all((E / 'primary-v1' / name).is_file() for name in ['annex-09.png', 'notes-35.png']), 'rendered row and lifecycle note')
law = html.unescape(re.sub(r'<[^>]+>', ' ', (E / 'primary-v1/sp-law13701.html').read_text(encoding='utf-8')))
law = re.sub(r'\s+', ' ', law)
check('sem nenhuma dedução, excetuados os descontos ou abatimentos concedidos independentemente de qualquer condição' in law, 'base price rule from art14')
check('O montante do Imposto é considerado parte integrante e indissociável do preço' in law, 'ISS-inclusive price from art14 paragraph4')
for folder in ['directed-v4', 'phpstan-v3', 'pint-v2']:
    execution = read(E / folder / 'execution.json')
    check(execution['status'] == 'TERMINAL' and execution['exit_code'] == 0, 'terminal success ' + folder)
    for channel in ['stdout', 'stderr']:
        check(sha(E / folder / (channel + '.log')) == execution[channel + '_sha256'], 'raw quality log ' + folder + channel)
plain = re.sub(r'\x1b\[[0-?]*[ -/]*[@-~]', '', (E / 'directed-v4/stdout.log').read_text(encoding='utf-8'))
check('338 passed' in plain and '2599 assertions' in plain, 'observed directed count')
native = read(E / 'native-v2/validation.json')
check(native['complete'] and native['component_assessments'] == native['exact_component_cli_replays'] == 52, 'native scope explicitly component only')
check(native['full_integrated_snapshots'] == 0 and native['monetary_document_checks'] == 84, 'no false integrated acceptance')
check(native['statuses'] == {'LOCAL_ISS_DOCUMENT_BASES_CALCULATED': 42, 'ISS133_OWN_ISS_FX_CONTRACT_REQUIRED': 8, 'ISS133_FRESH_COMPOSITION_REQUIRED': 2}, 'all prior positive seeds including current refusals')
for item in native['records']:
    check(sha(E / 'native-v2' / item['file']) == item['sha256'], 'saved component input and output')
reverse = read(E / 'native-v2/fresh-results-reverse-rtc-roles.json')['result']['documents']
check(reverse[0]['role'] == 'RTC_FOREIGN' and reverse[0]['iss_base_brl'] == '1000.00' and reverse[1]['iss_base_brl'] is None, 'fresh ISS result overrides role')
rounding = read(E / 'native-v2/technical-rounding-4-31.json')['result']['documents'][1]
check(rounding['iss_base_brl'] == '4.31' and rounding['iss_tax_brl'] == '0.12', 'no double rounding')
for folder, count in [('historical132-v1', 1), ('historical131-v1', 18)]:
    history = read(E / folder / 'validation.json')
    check(history['exit_code'] == 0 and history['checked_original_hashes'] == history['exact_replays'] == count, 'old integrated snapshots replay exactly')
matrix_path = ROOT / 'storage/app/phase4b-values132-development-20261022/requirements80-current-matrix.json'
archived = Path('D:/ProjetoSaasExports/phase4b-values132-20261022-v1/final-monitoring-v1/requirements80-current-matrix.json')
check(sha(matrix_path) == sha(archived), 'prior matrix unchanged')
baseline = read(matrix_path)
matrix = copy.deepcopy(baseline)
for old, item in zip(baseline['requirements'], matrix['requirements'], strict=True):
    if item['id'] == '4B.7.1':
        item['remaining_before_scoped133'] = item['current_remaining_gap']
        item['current_remaining_gap'] = '133 calcula base/impostoISS locais emBRL por documento do par de exportação parcial,com reexecução106/111,classificação granular02660 e contexto tributário explícito. Integrar ao fluxo versionado eDPS,concluir câmbioISS próprio,importação e demais perfis. ' + item['current_remaining_gap']
    else:
        check(item == old, 'unaffected requirement')
    check(item['own_review'] == old['own_review'] and not item['formal_completion_approved'], 'review preserved without closure')
check(len(matrix['requirements']) == 80 and matrix['formal_phase4b_groups_closed'] == 0, 'original scope retained')
runtime = set(prior['runtime_hashes']) | {'app/Modules/Fiscal/ISS/IssDocumentBase133.php', 'app/Console/Commands/CalculateIssDocumentBase133Command.php'}
report = {'created_at': datetime.now(timezone.utc).isoformat(), 'status': 'SCOPED133_LOCAL_ISS_BASES_VALIDATED_NOT_INTEGRATED_CLOSURE',
          'audit_checks': checks, 'tests': 338, 'assertions': 2599, 'quality': {'phpstan': 'PASS', 'pint': 'PASS'},
          'full_suite_executed': False, 'component_assessments': 52, 'exact_component_cli_replays': 52,
          'calculated_pairs': 42, 'monetary_document_checks': 84, 'historical_sample_exact_replays': 19,
          'prior_official_sources_preserved': 107, 'companion_municipal_sources': 5,
          'fiscal_bundle_unchanged': prior['fiscal_bundle_unchanged'], 'formal_phase4b_groups_closed': 0,
          'phase4b_complete': False, 'document_obligation_complete': False, 'production_ready': False,
          'runtime_hashes': {name: sha(ROOT / name) for name in sorted(runtime)}, 'evidence_root': str(E)}
OUT.mkdir(exist_ok=True)
receipt_path = OUT / 'iss133-scoped-validation.json'
write(receipt_path, report)
evidence = {'package': '133', 'path': str(receipt_path.relative_to(ROOT)).replace('\\', '/'), 'sha256': sha(receipt_path), 'status': report['status']}
for item in matrix['requirements']:
    if item['id'] == '4B.7.1':
        item.setdefault('subsequent_scoped_evidence', []).append(evidence)
matrix.setdefault('subsequent_scoped_evidence', []).append(evidence)
matrix.update(created_at=datetime.now(timezone.utc).isoformat(), status='ORIGINAL80_WITH_SCOPED133_LOCAL_ISS_CALCULATION_NOT_CLOSURE',
              baseline_matrix={'path': str(matrix_path), 'sha256': sha(matrix_path)}, requirements_with_scoped133_updates=['4B.7.1'],
              scope_note='80 revisões preservadas;componente localISS133 documentado no item4B.7.1.Sem integração versionada,0/8 grupos encerrados.')
write(OUT / 'requirements80-current-matrix.json', matrix)
print(json.dumps({'status': report['status'], 'audit_checks': checks, 'runtime_hashes': len(runtime), 'receipt_sha256': sha(receipt_path)}))
