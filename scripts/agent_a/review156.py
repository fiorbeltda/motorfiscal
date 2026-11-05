"""Reconcile A44 and H6-H10 against terminal evidence, without changing B's matrix."""
import json
import subprocess
from collections import Counter
from pathlib import Path
from xml.etree import ElementTree as ET

from evidence_io import preserve_json
from handoffs156 import ROOT, OUT, sha

DOC = ROOT/'docs/fiscal/agent-a/20261105'
MODULE = 'app/Modules/Fiscal/ConsumptionReform/'
FINAL = 'regression-v2'
CURRENT = 'final-v2'
NEW = {
    'H6': ['CreditEvidence156', 'SyntheticDocumentEvidence156'], 'H7': ['CreditEvidence156', 'SyntheticDocumentEvidence156'],
    'H8': ['ArithmeticEvidence156', 'FinancialEvidence156'],
    'H9': ['LegacyCoexistence156'], 'H10': ['LegalBaseReduction156', 'BaseReductionMissing156'],
}
TOUCHED = {
    'H6': ['4B.2.8', '4B.3.4'],
    'H7': ['4B.3.2', '4B.4.14'],
    'H8': ['4B.4.10', '4B.4.11', '4B.4.12', '4B.4.13', '4B.4.14'],
    'H9': ['4B.1.1', '4B.1.2', '4B.1.3', '4B.1.4', '4B.1.7'],
    'H10': ['4B.4.1', '4B.4.9'],
}
B_IDS = {'H6': ['4B.5.6', '4B.5.9'], 'H7': ['4B.5.8', '4B.5.9'], 'H8': ['4B.8.6'],
         'H9': ['4B.8.7', '4B.8.11', '4B.8.12'], 'H10': []}


def read(p):
    return json.loads(Path(p).read_text(encoding='utf-8-sig'))


def build():
    observed = read(OUT/(FINAL+'-result.json'))
    assert observed['failures'] == observed['errors'] == observed['skipped'] == 0
    assert read(OUT/(FINAL+'-exit.json'))['exit_code'] == 0
    current = read(OUT/(CURRENT+'-result.json'))
    assert current['failures'] == current['errors'] == current['skipped'] == 0
    assert read(OUT/(CURRENT+'-exit.json'))['exit_code'] == 0
    command = read(OUT/(CURRENT+'-command.json'))
    # New delivery scripts may be added later; every captured source must retain its bytes.
    for p, digest in command['hashes'].items():
        assert sha(ROOT/p) == digest, p
    base = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT).decode().strip()
    assert base == '1fa9969b2334806553a08b1a4276e22d1b7d7c8e'
    for name in ['phpstan-v4', 'pint-v7', 'console-v1', 'endpoint-v2']:
        assert read(OUT/(name+'-exit.json'))['exit_code'] == 0
    old = read(ROOT/'docs/fiscal/agent-a/20261104/requirements44-handoff-H5.json')
    plan = read(DOC/'test-plan-A156-v2.json')
    old_plan = read(ROOT/'docs/fiscal/agent-a/20261103/task-A154-test-plan.json')
    old_tests = {r['id']: r['tests'] for r in old_plan['requirements']}
    cases = list(ET.parse(OUT/(CURRENT+'.junit.xml')).iter('testcase'))
    current_paths = {p for p in command['command'] if p.startswith('tests/')}

    def file_of(case):
        return case.get('file', '').replace('/var/www/html/', '').split('::')[0]

    def stats(paths):
        selected = [c for c in cases if file_of(c) in paths]
        return {'tests': len(selected), 'assertions': sum(int(c.get('assertions', 0)) for c in selected),
                'failures': sum(c.find('failure') is not None for c in selected),
                'errors': sum(c.find('error') is not None for c in selected)}

    rows = []
    for previous in old['requirements']:
        ident = previous['id']
        handoffs = [h for h, ids in TOUCHED.items() if ident in ids]
        paths = sorted(set(old_tests[ident]+previous['existing_test_paths']+
                           ['tests/Fiscal/Integration/'+n+'Test.php' for h in handoffs for n in NEW[h]]))
        current_code = set(previous['code_current_sha256'])
        if handoffs:
            current_code.update([MODULE+'ConsumptionReformEngine.php'])
        if 'H6' in handoffs or 'H7' in handoffs:
            current_code.add(MODULE+'CreditEvidence156.php')
        if 'H8' in handoffs or 'H9' in handoffs:
            current_code.add(MODULE+'TemporalRateCatalog.php')
        if 'H10' in handoffs:
            current_code.add(MODULE+'LegalBaseReduction156.php')
        classification = 'PARCIAL' if ident == '4B.4.9' else previous['classification']
        new_scope = {
            'H6': 'Contrato de documento definitivo, pagamento por tributo/data e livro qualificado antes do ramo IMPORT; obrigação documental continua bloqueada.',
            'H7': 'Cada crédito ordinário/pool art51/52 vinculado ao documento e valores escriturados; sem posting.',
            'H8': 'Matriz aritmética sintética segregada, municipal não zero e oráculos Decimal; perfis/famílias delimitados.',
            'H9': '2026/2027/2030/2033 com metadata legada explícita, invariantes e bloqueio de decisão sintética; não é cálculo monetário completo de PIS/Cofins.',
            'H10': 'Hipótese LC214 art12§8 implementada com base pela metade e alíquotas intactas; classificação/ativação material própria pendente de B.',
        }
        remaining = previous['remaining']
        if ident == '4B.2.11':
            remaining = 'Restam PF empresarial e integração dos perfis de domicílio ainda não cobertos; os vínculos A151-A153 foram mantidos na auditoria atual.'
        if ident == '4B.4.9':
            remaining = 'Ativar em novo bundle/classificação de transporte qualificada por B; cobrir combinações especiais/antecipações e demais hipóteses legais aplicáveis. Um mecanismo delimitado não fecha todo o item.'
        rows.append({'id': ident, 'requirement': previous['requirement'], 'classification': classification,
            'previous_classification_H5': previous['classification'], 'historical_observed_scope': previous['observed_scope'],
            'new_scoped_evidence': [new_scope[h] for h in handoffs], 'remaining': remaining,
            'handoffs': handoffs, 'related_test_paths': paths,
            'executed_paths_current_source': sorted(set(paths)&current_paths),
            'executed_paths_pre_final_missing_record_guard': sorted(set(paths)&set(plan['tests'])),
            'not_executed_current_source': sorted(set(paths)-current_paths),
            'excluded_missing_fixture_paths': sorted(set(paths)&set(plan['all_41_exclusion_list'])),
            'related_suite_counts_overlap_not_whole_acceptance': stats(paths),
            'code_current_sha256': {p:sha(ROOT/p) for p in sorted(current_code)},
            'historical_receipts_preserved': previous['historical_scoped_receipts'],
            'whole_item_accepted': False, 'formal_group_closed': False})
    assert len(rows) == len({r['id'] for r in rows}) == 44
    review = {'task': 'A156/H10 retomada da revisão A154', 'base': base,
        'historical_A154_runs_rewritten': False, 'scope': 'AUDITORIA44_COM_REGRESSAO_DIRIGIDA_PARCIAL',
        'classification_counts': dict(Counter(r['classification'] for r in rows)), 'requirements': rows,
        'groups_closed': 0, 'phase_complete': False, 'production_ready': False}
    preserve_json(DOC/'requirements44-handoff-H10.json', review)
    handoffs = {}
    for h in NEW:
        paths = ['tests/Fiscal/Integration/'+n+'Test.php' for n in NEW[h]]
        handoffs[h] = {'delivery': 'IMPLEMENTACAO_OU_EVIDENCIA_DELIMITADA_ENTREGUE_PARA_INTEGRACAO_B',
            'A_ids': TOUCHED[h], 'B_ids': B_IDS[h], 'new_test_files': paths,
            'counts_overlap_with_regression': stats(paths), 'contracts': 'contracts-H6-H10.md',
            'B_activation_performed': False, 'whole_requirement_accepted': False,
            'source_sha256': {p:sha(ROOT/p) for p in paths}}
    receipt = {'task': 'A156/H6-H10', 'base': base, 'branch': 'codex/fiscal-engine-bootstrap',
        'handoffs': handoffs, 'initial_baseline': read(OUT/'baseline-v1-result.json'),
        'regression': {k:observed[k] for k in ['tests','assertions','failures','errors','skipped','sha256']},
        'regression_scope': 'PARCIAL_DIRIGIDA_68_ARQUIVOS_ANTES_DA_GUARDA_FINAL_DE_REGISTRO_AUSENTE_NAO_INTEGRAL',
        'current_source_validation': {k:current[k] for k in ['tests','assertions','failures','errors','skipped','sha256']},
        'current_source_command': CURRENT+'-command.json',
        'code_cut_note': 'The broad v2 cut precedes the final missing-record guard in the new passenger gate and final in-memory version labels. Its exact engine/fixture bytes were preserved externally. Final current-source validation reruns all new handoff tests, B23/H1-H5, and synthetic rate acceptance. Counts are separate and overlapping.',
        'prior_regression_before_documentary_synthetic_guard': read(OUT/'regression-v1-result.json') | {'files': 'see regression-v1-command.json'},
        'legacy_monetary_supplement': read(OUT/'legacy-money-v1-result.json'),
        'excluded_from_A154': plan['excluded_from_A154'],
        'official_source': read(OUT/'source-receipt.json'),
        'decimal_oracles': {'file': str(OUT/'decimal-oracles-v3.json'), 'sha256': sha(OUT/'decimal-oracles-v3.json'),
                            'component_checks': 72, 'scenarios': 24, 'official_decisions': 0},
        'production_sources': {p:sha(ROOT/p) for p in [MODULE+n+'.php' for n in ['ConsumptionReformEngine','TemporalRateCatalog','CreditEvidence156','LegalBaseReduction156']]},
        'requirements44_sha256': sha(DOC/'requirements44-handoff-H10.json'),
        'quality': {'phpstan_global_level8': 'PASS', 'pint_15_php_files': 'PASS', 'console_read_only': '28_PASS', 'endpoint': 200},
        'B_files_edited': False, 'accepted_resources_edited': False, 'global_matrix_edited': False,
        'global_console_edited': False, 'B_received': False, 'groups_closed': 0, 'phase_complete': False,
        'production_ready': False, 'evidence_directory': str(OUT), 'counts_must_not_be_added_across_overlapping_scopes': True}
    preserve_json(DOC/'handoffs-H6-H10-receipt.json', receipt)
    print(json.dumps({'handoffs':{h:v['counts_overlap_with_regression'] for h,v in handoffs.items()}, 'review_counts':review['classification_counts']}))


if __name__ == '__main__':
    build()
