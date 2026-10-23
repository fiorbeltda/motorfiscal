"""Audit all-own-tax bridge and retain all original80 reviews and earlier receipts."""
import copy
import hashlib
import json
import subprocess
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
E = Path('D:/ProjetoSaasExports/phase4b-own-bridge135-20261023-v1')


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


baseline = read(ROOT / 'storage/app/phase4b-iss134-development-20261022/iss134-scoped-validation.json')
for name, expected in baseline['runtime_hashes'].items():
    assert sha(ROOT / name) == expected, name
own_path = ROOT / 'storage/app/phase4b-own135-development-20261023/own135-local-validation.json'
own = read(own_path)
for name, expected in own['runtime_and_support_hashes'].items():
    assert sha(ROOT / name) == expected, name
for folder in ['directed-v1', 'phpstan-v1', 'pint-v1']:
    execution = read(E / folder / 'execution.json')
    assert execution['status'] == 'TERMINAL' and execution['exit_code'] == 0
    for channel in ['stdout', 'stderr']:
        assert sha(E / folder / (channel + '.log')) == execution[channel + '_sha256']
assert '166 passed (426 assertions)' in (E / 'directed-v1/stdout.log').read_text(encoding='utf-8')
assert read(E / 'phpstan-v1/stdout.log')['totals'] == {'errors': 0, 'file_errors': 0}
native = read(E / 'native-v1/validation.json')
assert native['exact_source_replays'] == 6 and native['source_scenarios'] == 3 and native['independent_decimal_checks'] == 6
assert native['positive'] == 1 and native['negative'] == 2 and not native['bundle135_installed']
assert sha(E / 'native-v1/execution1.json') == sha(E / 'native-v1/execution2.json') == native['output_sha256']
for snapshot in native['snapshots']:
    assert sha(E / 'native-v1' / snapshot['file']) == snapshot['sha256']
cases = read(E / 'native-v1/execution1.json')['cases']
positive = next(c for c in cases if c['case'] == 'new-explicit-contract')
assert positive['result']['document_price_total_brl'] == '1041.33'
assert positive['result']['own_exclusions_total_brl'] == '41.33'
assert positive['result']['rtc_total_brl'] == '1000.00'
assert not positive['result']['iss_base_rewritten'] and not positive['result']['rtc_base_rewritten']
before = subprocess.run(['git', 'show', 'HEAD:scripts/phase4b_iss133_fixture.php'], cwd=ROOT, capture_output=True, check=True).stdout
paths = ['app/Modules/Fiscal/ConsumptionReform/DocumentOwnTaxBridge135.php',
         'scripts/phase4b_own135_pair_fixture.php', 'scripts/phase4b_iss133_fixture.php',
         'scripts/capture_phase4b_own_bridge135_native.php', 'scripts/collect_phase4b_own_bridge135.py',
         'tests/Fiscal/Integration/DocumentOwnTaxBridge135Test.php']
report = {'created_at': datetime.now(timezone.utc).isoformat(), 'status': 'LOCAL_ALL_OWN_TAX_PRICE_BRIDGE_VALIDATED_NOT_FULL_DPS',
          'baseline134_runtime_hashes_unchanged': len(baseline['runtime_hashes']), 'earlier_own135_hashes_unchanged': len(own['runtime_and_support_hashes']),
          'directed_tests': 166, 'directed_assertions': 426, 'new_bridge_tests': 23,
          'quality': {'phpstan': 'PASS', 'pint': 'PASS_5_FILES'}, 'native': native,
          'runtime_and_support_hashes': {p: sha(ROOT / p) for p in paths},
          'fixture133_extension': {'optional_argument': 'declaredPrices', 'git_head_version_sha256': hashlib.sha256(before).hexdigest(),
                                   'current_working_sha256': sha(ROOT / 'scripts/phase4b_iss133_fixture.php'),
                                   'previous_defaults_preserved': True, 'legacy_directed_tests_rerun': True},
          'evidence_root': str(E), 'full_suite_rerun': False, 'last_full_suite': '134:9406/145560',
          'bundle135_installed': False, 'complete_dps_documents': 0, 'document_obligation_complete': False,
          'judicial_temporal_effects_approved': False, 'phase4b_complete': False, 'formal_groups_closed': 0}
out = ROOT / 'storage/app/phase4b-own-bridge135-development-20261023'
out.mkdir(exist_ok=True)
receipt = out / 'own-bridge135-local-validation.json'
with receipt.open('x', encoding='utf-8') as handle:
    handle.write(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
matrix_path = ROOT / 'storage/app/phase4b-own135-development-20261023/requirements80-current-matrix.json'
baseline_matrix = read(matrix_path)
matrix = copy.deepcopy(baseline_matrix)
evidence = {'package': '135-own-bridge', 'path': str(receipt.relative_to(ROOT)).replace('\\', '/'), 'sha256': sha(receipt), 'status': report['status']}
for old, item in zip(baseline_matrix['requirements'], matrix['requirements'], strict=True):
    if item['id'] == '4B.4.2':
        item['remaining_before_own_bridge135'] = item['current_remaining_gap']
        item['current_remaining_gap'] = 'Ponte135 local reexecuta contribuições próprias e confronta todos os quatro tributos com parcelasRTC. Novo contrato sintético400/641,33 confere;anterior617,92 é recusado por21,90. Concluir integração,demais exclusões e efeitos judiciais/materiais,sem alterar contratos para forçar resultado.'
        item.setdefault('subsequent_scoped_evidence', []).append(evidence)
    else:
        assert item == old
    assert item['own_review'] == old['own_review'] and not item['formal_completion_approved']
assert len(matrix['requirements']) == 80 and matrix['formal_phase4b_groups_closed'] == 0
matrix.update(created_at=datetime.now(timezone.utc).isoformat(), status='ORIGINAL80_WITH_LOCAL_OWN_BRIDGE135_NOT_PHASE_CLOSURE',
              baseline_matrix={'path': str(matrix_path), 'sha256': sha(matrix_path)}, requirements_with_own_bridge135_updates=['4B.4.2'],
              scope_note='80 revisões próprias preservadas;ponte local135 com todos os tributos,integração/par/câmbio/demais requisitos pendentes;0/8 grupos encerrados.')
matrix.setdefault('subsequent_scoped_evidence', []).append(evidence)
with (out / 'requirements80-current-matrix.json').open('x', encoding='utf-8') as handle:
    handle.write(json.dumps(matrix, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({'status': report['status'], 'receipt_sha256': sha(receipt), 'requirements_preserved': 80, 'baseline_unchanged': len(baseline['runtime_hashes'])}))
