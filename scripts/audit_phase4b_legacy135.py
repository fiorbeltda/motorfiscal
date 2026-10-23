"""Audit the isolated135 boundary without replacing acceptance134 or closing4B."""
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
E = Path('D:/ProjetoSaasExports/phase4b-price135-20261023-v1')


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


prior_path = ROOT / 'storage/app/phase4b-iss134-development-20261022/iss134-scoped-validation.json'
prior = read(prior_path)
for name, expected in prior['runtime_hashes'].items():
    assert sha(ROOT / name) == expected, name
source = read(E / 'primary-v2/capture.json')
assert source['http_status'] == 200 and sha(E / 'primary-v2/lc214.html') == source['sha256']
assert source['sha256'] == '70672c70d87f6e8978c34cd10056fd2d91e7a80c6f3483fa8715c850aae6d9c7'
assert source['observed_at'] == '2026-10-23T10:41:42.939166+00:00'
assert len(source['active_article12_paragraphs']) == 26
assert '2026' in source['active_article12_paragraphs'][13] and '2032' in source['active_article12_paragraphs'][13]
for folder in ['directed-v1', 'phpstan-v1', 'pint-v3']:
    record = read(E / folder / 'execution.json')
    assert record['status'] == 'TERMINAL' and record['exit_code'] == 0
    for channel in ['stdout', 'stderr']:
        assert sha(E / folder / (channel + '.log')) == record[channel + '_sha256']
directed = (E / 'directed-v1/stdout.log').read_text(encoding='utf-8')
assert '94 passed' in directed and '316 assertions' in directed
assert read(E / 'phpstan-v1/stdout.log')['totals'] == {'errors': 0, 'file_errors': 0}
native = read(E / 'native-v2/validation.json')
assert native['exact_source_replays'] == 2 and native['positive'] == native['negative'] == 4
assert native['independent_decimal_checks'] == 4 and not native['integrated135']
assert sha(E / 'native-v2/execution1.json') == sha(E / 'native-v2/execution2.json') == native['output_sha256']
paths = ['app/Modules/Fiscal/ConsumptionReform/LegacyExclusionWindow135.php',
         'tests/Fiscal/Unit/LegacyExclusionWindow135Test.php',
         'scripts/capture_phase4b_legacy135_native.php', 'scripts/collect_phase4b_legacy135.py',
         'scripts/capture_phase4b_price135_legal.py']
report = {'created_at': datetime.now(timezone.utc).isoformat(), 'status': 'LOCAL_DECLARED_INCIDENT_AMOUNTS_BOUNDARY_VALIDATED',
          'baseline134_runtime_hashes_unchanged': len(prior['runtime_hashes']), 'baseline134_receipt_sha256': sha(prior_path),
          'primary_capture': {'path': str(E / 'primary-v2/capture.json'), 'sha256': sha(E / 'primary-v2/capture.json'),
                              'primary_bytes_sha256': source['sha256']},
          'directed_tests': 94, 'directed_assertions': 316, 'new_tests': 29, 'quality': {'phpstan': 'PASS', 'pint': 'PASS_3_FILES'},
          'native': native, 'runtime_and_support_hashes': {p: sha(ROOT / p) for p in paths},
          'evidence_root': str(E), 'full_suite_rerun': False, 'latest_full_suite': '134:9406/145560',
          'bundle135_installed': False, 'own_incidence_assessed_or_reexecuted': False,
          'legacy_exclusion_legally_approved': False, 'phase4b_complete': False, 'formal_groups_closed': 0,
          'remaining': ['Actual own-tax assessments/provenance for all four taxes per document.',
                        'Other article12 exclusions,material source lifecycle and full135 integration.',
                        'ISS FX/import documents and remaining original80 requirements.']}
out = ROOT / 'storage/app/phase4b-legacy135-development-20261023'
out.mkdir(exist_ok=True)
with (out / 'legacy135-scoped-validation.json').open('x', encoding='utf-8') as handle:
    handle.write(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({'status': report['status'], 'receipt_sha256': sha(out / 'legacy135-scoped-validation.json'),
                  'baseline_unchanged': report['baseline134_runtime_hashes_unchanged']}))
