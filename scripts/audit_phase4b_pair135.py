"""Audit realCLI pair artifacts and optional terminal regression; never close original4B."""
import argparse
import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--scoped-only', action='store_true')
args = parser.parse_args()
ROOT = Path(__file__).resolve().parents[1]
E = Path('D:/ProjetoSaasExports/phase4b-pair135-20261023-v1')


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


baseline = read(ROOT / 'storage/app/phase4b-iss134-development-20261022/iss134-scoped-validation.json')
for name, expected in baseline['runtime_hashes'].items():
    assert sha(ROOT / name) == expected, name
prior_path = ROOT / 'storage/app/phase4b-own-bridge135-development-20261023/own-bridge135-local-validation.json'
prior = read(prior_path)
helper_changes = []
for name, expected in prior['runtime_and_support_hashes'].items():
    actual = sha(ROOT / name)
    if name in ['scripts/phase4b_iss133_fixture.php', 'scripts/phase4b_own135_pair_fixture.php']:
        helper_changes.append({'path': name, 'before': expected, 'after': actual,
                               'change': 'Optional emission identity/knowledge overrides;prior defaults preserved and tests rerun.'})
    else:
        assert actual == expected, name
for folder in ['directed-v3', 'phpstan-full-v2', 'pint-full-v2']:
    record = read(E / folder / 'execution.json')
    assert record['status'] == 'TERMINAL' and record['exit_code'] == 0
    for channel in ['stdout', 'stderr']:
        assert sha(E / folder / (channel + '.log')) == record[channel + '_sha256']
assert '203 passed (498 assertions)' in (E / 'directed-v3/stdout.log').read_text(encoding='utf-8')
assert read(E / 'phpstan-full-v2/stdout.log')['totals'] == {'errors': 0, 'file_errors': 0}
assert '673 files' in (E / 'pint-full-v2/stdout.log').read_text(encoding='utf-8')
native = read(E / 'native-v3/validation.json')
assert native['identical_reports'] and native['complete_xml_files'] == 4 and native['distinct_complete_dps'] == 2
assert native['base_official_sources_valid'] == 112 and native['additional_sources_valid'] == 8
assert native['full_snapshot_replays'] == 4 and len(native['refusals']) == 2
for record in native['refusals']:
    assert record['exit_code'] == 1 and record['no_directory_created'] and record['reason']
for name, expected in native['input_hashes'].items():
    assert sha(E / 'native-v3' / name) == expected
for label in ['first', 'second']:
    report = read(E / 'native-v3' / (label + '.stdout.json'))
    assert report == read(E / 'native-v3' / label / 'report.json')
    assert report['complete_dps_built'] and not report['complete_fiscal_pair_qualified']
    assert not report['own_pis_cofins_projected_in_xml'] and not report['document_obligation_complete']
    for document in report['records']:
        assert sha(E / 'native-v3' / label / (document['role'] + '.xml')) == native['xml_hashes'][document['role']] == document['xml_sha256']
assert read(E / 'native-v3/first.stdout.json') == read(E / 'native-v3/second.stdout.json')
history = read(E / 'historical134-v1/validation.json')
assert history['exit_code'] == 0 and history['exact_replays'] == history['checked_original_hashes'] == 22
full = None
if not args.scoped_only:
    full = read(E / 'full-suite-v1/validation.json')
    assert full['status'] == 'TERMINAL' and full['exit_code'] == 0 and full['complete']
    assert full['tests'] == 9544 and not full['changed_inputs'] and not full['added_inputs'] and full['script_helpers_included']
    assert sha(E / 'full-suite-v1/stdout.log') == full['stdout_sha256']
    before = read(E / 'full-suite-v1/input-hashes-before.json')
    assert before == read(E / 'full-suite-v1/input-hashes-after.json')
    for name, expected in before.items():
        assert sha(ROOT / name) == expected, name
runtime = set(baseline['runtime_hashes']) | {'app/Modules/Fiscal/ConsumptionReform/LegacyExclusionWindow135.php',
    'app/Modules/Fiscal/ConsumptionReform/SoftwareOwnRevenue135.php', 'app/Modules/Fiscal/ConsumptionReform/DocumentOwnTaxBridge135.php',
    'app/Modules/Fiscal/ConsumptionReform/DocumentComex135.php', 'app/Modules/Fiscal/ConsumptionReform/PartialExportForeignClassification135.php',
    'app/Modules/Fiscal/NFSe/PartialExportDpsPair135.php', 'app/Console/Commands/ProjectPartialExportDpsPair135Command.php'}
support = ['scripts/phase4b_iss133_fixture.php', 'scripts/phase4b_own135_pair_fixture.php', 'scripts/phase4b_dps_pair135_fixture.php',
           'scripts/capture_phase4b_pair135_inputs.php', 'scripts/collect_phase4b_pair135.py', 'scripts/capture_phase4b_pair135_full_suite.py',
           'tests/Fiscal/Integration/PartialExportDpsPair135Test.php']
report = {'created_at': datetime.now(timezone.utc).isoformat(),
          'status': 'PAIR135_LOCAL_CLI_VALIDATED_FULL_SUITE_PENDING' if full is None else 'PAIR135_LOCAL_CLI_AND_FULL_REGRESSION_VALIDATED_NOT_PHASE_CLOSURE',
          'directed_tests': 203, 'directed_assertions': 498, 'new_pair_tests': 37,
          'quality': {'phpstan': 'PASS_ALL_CONFIGURED_PATHS_512M', 'pint': 'PASS_673_FILES'},
          'native': native, 'historical134_exact_replays': 22, 'earlier134_runtime_hashes_unchanged': len(baseline['runtime_hashes']),
          'runtime_hashes': {name: sha(ROOT / name) for name in sorted(runtime)}, 'support_hashes': {name: sha(ROOT / name) for name in support},
          'fixture_helper_extensions': helper_changes, 'full_suite_executed': full is not None, 'full_suite': full,
          'last_full_suite_before135': '134:9406/145560', 'evidence_root': str(E), 'bundle135_installed': False,
          'projection135_cli_installed': True, 'own_pis_cofins_projected_in_xml': False, 'complete_fiscal_pair_qualified': False,
          'document_obligation_complete': False, 'production_ready': False, 'formal_phase4b_groups_closed': 0, 'phase4b_complete': False}
out = ROOT / 'storage/app/phase4b-pair135-development-20261023'
out.mkdir(exist_ok=True)
receipt = out / ('pair135-directed-validation.json' if full is None else 'pair135-full-validation.json')
with receipt.open('x', encoding='utf-8') as handle:
    handle.write(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
matrix_path = ROOT / 'storage/app/phase4b-own-bridge135-development-20261023/requirements80-current-matrix.json'
old_matrix = read(matrix_path)
matrix = copy.deepcopy(old_matrix)
evidence = {'package': '135-pair', 'path': str(receipt.relative_to(ROOT)).replace('\\', '/'), 'sha256': sha(receipt), 'status': report['status']}
gaps = {'4B.4.2': 'Ponte de todos os tributos próprios ligada ao comando/projetor135 com replay e provas capturadas. Concluir efeitos judiciais,mensais e demais exclusões/perfis;grupo federal próprio ainda só no relatório.',
        '4B.7.1': 'CLI135 monta doisDPS completos XSD com identidade,COMEX,classificação própria e preços reconciliados. Concluir CST/indicadores/projeçãoPIS-Cofins próprios noXML,país/moeda,demais dados municipais quando exigidos,efeitos judiciais e demais perfis/câmbio/importação;XSD não encerra obrigação.'}
for old, item in zip(old_matrix['requirements'], matrix['requirements'], strict=True):
    if item['id'] in gaps:
        item['remaining_before_pair135'] = item['current_remaining_gap']
        item['current_remaining_gap'] = gaps[item['id']]
        item.setdefault('subsequent_scoped_evidence', []).append(evidence)
    else:
        assert item == old
    assert item['own_review'] == old['own_review'] and not item['formal_completion_approved']
assert len(matrix['requirements']) == 80 and matrix['formal_phase4b_groups_closed'] == 0
matrix.update(created_at=datetime.now(timezone.utc).isoformat(), status='ORIGINAL80_WITH_PAIR135_LOCAL_CLI_NOT_PHASE_CLOSURE',
              baseline_matrix={'path': str(matrix_path), 'sha256': sha(matrix_path)}, requirements_with_pair135_updates=list(gaps),
              scope_note='80 revisões próprias preservadas;par135 local candidato,campos/perfis/efeitos restantes,0/8 grupos encerrados.')
matrix.setdefault('subsequent_scoped_evidence', []).append(evidence)
matrix_name = 'requirements80-directed-matrix.json' if full is None else 'requirements80-current-matrix.json'
with (out / matrix_name).open('x', encoding='utf-8') as handle:
    handle.write(json.dumps(matrix, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({'status': report['status'], 'runtime_hashes': len(runtime), 'receipt_sha256': sha(receipt), 'full_suite_executed': full is not None}))
