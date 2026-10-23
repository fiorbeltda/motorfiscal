"""Audit own135 final local profile, preserving old observations and original80 reviews."""
import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
E = Path('D:/ProjetoSaasExports/phase4b-own135-20261023-v1')


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


baseline_path = ROOT / 'storage/app/phase4b-iss134-development-20261022/iss134-scoped-validation.json'
baseline = read(baseline_path)
for name, expected in baseline['runtime_hashes'].items():
    assert sha(ROOT / name) == expected, name
guard = read(ROOT / 'storage/app/phase4b-legacy135-development-20261023/legacy135-scoped-validation.json')
for name, expected in guard['runtime_and_support_hashes'].items():
    assert sha(ROOT / name) == expected, name
sources = read(E / 'primary-v1/capture.json')['sources']
assert len(sources) == 5
for source in sources:
    assert source['http_status'] == 200 and sha(E / 'primary-v1' / source['file']) == source['sha256']
court = read(E / 'court-v1/capture.json')
assert court['http_status'] == 200 and sha(E / 'court-v1/stf-tema118.html') == court['sha256']
assert court['merit_decided_on'] == '2026-10-21' and court['minutes_published_on'] == '2026-10-23'
assert not court['final_judgment_verified'] and not court['modulation_verified']
for folder in ['directed-v3', 'phpstan-v2', 'pint-v2']:
    execution = read(E / folder / 'execution.json')
    assert execution['status'] == 'TERMINAL' and execution['exit_code'] == 0
    for channel in ['stdout', 'stderr']:
        assert sha(E / folder / (channel + '.log')) == execution[channel + '_sha256']
assert '143 passed (386 assertions)' in (E / 'directed-v3/stdout.log').read_text(encoding='utf-8')
assert read(E / 'phpstan-v2/stdout.log')['totals'] == {'errors': 0, 'file_errors': 0}
native = read(E / 'native-v2/validation.json')
assert native['exact_source_replays'] == 2 and native['positive'] == 2 and native['negative'] == 4
assert native['independent_decimal_checks'] == 10 and native['domestic_gap_brl'] == '-21.90'
assert not native['bundle135_installed'] and not native['judicial_temporal_effects_approved']
assert sha(E / 'native-v2/execution1.json') == sha(E / 'native-v2/execution2.json') == native['output_sha256']
for case in read(E / 'native-v2/execution1.json')['cases']:
    if case['result']['status'] == 'LOCAL_SOFTWARE_OWN_REVENUE_LINES_CALCULATED':
        assert not case['result']['monthly_liability_calculated'] and not case['result']['judicial_temporal_effects_approved']
paths = ['app/Modules/Fiscal/ConsumptionReform/SoftwareOwnRevenue135.php',
         'scripts/phase4b_own135_fixture.php', 'scripts/capture_phase4b_own135_native.php',
         'scripts/collect_phase4b_own135.py', 'scripts/capture_phase4b_own135_sources.py',
         'scripts/capture_phase4b_own135_court.py', 'tests/Fiscal/Integration/SoftwareOwnRevenue135Test.php']
report = {'created_at': datetime.now(timezone.utc).isoformat(), 'status': 'LOCAL_SOFTWARE_OWN_REVENUE_V2_VALIDATED_NOT_FULL_PAIR',
          'baseline134_runtime_hashes_unchanged': len(baseline['runtime_hashes']), 'earlier_guard135_hashes_unchanged': len(guard['runtime_and_support_hashes']),
          'primary_sources': sources, 'court_source': court, 'directed_tests': 143, 'directed_assertions': 386,
          'new_own_tests': 49, 'quality': {'phpstan': 'PASS', 'pint': 'PASS_4_FILES'}, 'native': native,
          'runtime_and_support_hashes': {p: sha(ROOT / p) for p in paths}, 'evidence_root': str(E),
          'superseded': {'native-v1': 'ISS included;counterfactual source study only. Superseded by court-reviewedV2.',
                         'directed-v1': 'Failed zero override in old positive-only110 contract;fixed using original fully discounted zero seed.',
                         'directed-v2': 'Passed143/386 before STF review;not current own-base fiscal evidence.'},
          'full_suite_rerun': False, 'last_full_suite': '134:9406/145560', 'bundle135_installed': False,
          'judicial_temporal_effects_approved': False, 'final_judgment_verified': False, 'phase4b_complete': False, 'formal_groups_closed': 0}
out = ROOT / 'storage/app/phase4b-own135-development-20261023'
out.mkdir(exist_ok=True)
receipt = out / 'own135-local-validation.json'
with receipt.open('x', encoding='utf-8') as handle:
    handle.write(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
matrix_path = ROOT / 'storage/app/phase4b-iss134-development-20261022/requirements80-current-matrix.json'
baseline_matrix = read(matrix_path)
matrix = copy.deepcopy(baseline_matrix)
evidence = {'package': '135-own', 'path': str(receipt.relative_to(ROOT)).replace('\\', '/'), 'sha256': sha(receipt), 'status': report['status']}
gaps = {'4B.4.1': '135 calcula linhas própriasPIS/Cofins de software com competência/provas próprias eISS fresco;perfilV2 deduzISS sob alegação localTema118. Concluir efeitos judiciais materiais,demais perfis/câmbio e integração,sem confundir linha com apuração mensal.',
        '4B.4.2': '135 liga valores próprios deISS/ICMS/PIS/Cofins por documento à janelaart12. Par anterior tem diferença doméstica21,90 sobV2;concluir ponte,demais exclusões e efeitos judiciais. Não ajustar valores para forçar coincidência.'}
for old, item in zip(baseline_matrix['requirements'], matrix['requirements'], strict=True):
    if item['id'] in gaps:
        item['remaining_before_own135'] = item['current_remaining_gap']
        item['current_remaining_gap'] = gaps[item['id']]
        item.setdefault('subsequent_scoped_evidence', []).append(evidence)
    else:
        assert item == old
    assert item['own_review'] == old['own_review'] and not item['formal_completion_approved']
assert len(matrix['requirements']) == 80 and matrix['formal_phase4b_groups_closed'] == 0
matrix.update(created_at=datetime.now(timezone.utc).isoformat(), status='ORIGINAL80_WITH_LOCAL_OWN135_V2_NOT_PHASE_CLOSURE',
              baseline_matrix={'path': str(matrix_path), 'sha256': sha(matrix_path)}, requirements_with_own135_updates=list(gaps),
              scope_note='80 revisões próprias preservadas;135 cálculo próprio local comTema118 observado,par/efeitos judiciais/demais requisitos pendentes;0/8 grupos encerrados.')
matrix.setdefault('subsequent_scoped_evidence', []).append(evidence)
with (out / 'requirements80-current-matrix.json').open('x', encoding='utf-8') as handle:
    handle.write(json.dumps(matrix, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({'status': report['status'], 'receipt_sha256': sha(receipt), 'requirements_preserved': 80, 'baseline_unchanged': len(baseline['runtime_hashes'])}))
