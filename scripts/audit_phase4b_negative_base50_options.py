"""Audit source-bound synthetic declaration evidence; preserve UTF-8 and prior resources."""
from pathlib import Path
import hashlib
import json
import re
from dere_daily_memory_oracle import digest

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = ROOT / 'storage/app/phase4b-negative-base50-closing-draft-20261009'
P = ROOT / 'storage/app/phase4b-negative-base49-preparation-20261009'
OLD = ROOT / 'storage/app/phase4b-private-consolidation48-draft-20261009-28'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def ref(path):
    return {'path': str(path.relative_to(ROOT)).replace('\\', '/'), 'sha256': sha(path)}


bundlePath = F / 'bundles/consumption-phase4b-2026.10.09-28-draft50.json'
bundle = read(bundlePath)
catPath = F / bundle['resource_paths']['rtc_code_catalog']
catalog = read(catPath)
assert digest(catalog) == bundle['content_hashes']['rtc_code_catalog']
controls = F / bundle['production_controls']['path']
assert digest(read(controls)) == bundle['production_controls']['sha256']
policy = catalog['dere_negative_base_closing_options_projection']
assert digest({k: v for k, v in policy.items() if k != 'source_hash'}) == policy['source_hash']
for item in bundle['official_sources']:
    assert sha(F / 'sources/official' / item['file']) == item['sha256']
gate = read(E / 'production-assessment.json')
assert gate['ready'] is False and gate['official_source_hashes_valid'] == 51
previous = [OLD / 'regression-positive-validation-report.json', P / 'parameters-development-validation.json',
            P / 'return-development-validation.json', P / 'consumption-development-validation.json',
            P / 'versions-development-validation.json']
for path in previous:
    record = read(path)
    for item in record.get('implementation_hashes', record.get('implementation', [])):
        assert sha(ROOT / item['path']) == item['sha256'], item['path']
quality = {}
for label in ['options-final-pint', 'options-final-tests', 'options-final-phpstan',
              'options-native-capture', 'options-native-replay']:
    path = E / (label + '.json')
    record = read(path)
    assert record['exit_code'] == 0 and record['log_sha256'] == sha(E / (label + '.log'))
    quality[label] = ref(path)
log = re.sub(r'\x1b\[[0-9;]*m', '', (E / 'options-final-tests.log').read_text(encoding='utf-8'))
assert '88 passed' in log and '294 assertions' in log
result = read(E / 'synthetic-options-result.json')
inputs = read(E / 'synthetic-options-input.json')
assert result['status'] == 'PROJECTED' and result['gross_debits_recalculated'] is False
assert result['production_ready'] is False and inputs['policy'] == policy
assert result['schema_validation']['xml_sha256'] == sha(E / 'synthetic-d1199-options.xml')
assert result['baseline_projection_hash'] == digest(inputs['baseline'])
assert result['request_hash'] == digest(inputs['request'])
files = ['scripts/build_phase4b_negative_base50_bundle.py', 'scripts/capture_phase4b_negative_base50_options.py',
         'scripts/audit_phase4b_negative_base50_options.py',
         'app/Modules/Fiscal/NFSe/DereNegativeBaseClosingOptionsXmlProjector.php',
         'tests/Fiscal/Unit/DereNegativeBaseClosingOptions50Test.php',
         'docs/fiscal/phase4b-negative-base-closing50-development.md']
report = {'status': 'LOCAL_D1199_OPTIONS_PROTOTYPE_VALIDATED_NOT_MONTHLY_ENGINE_INTEGRATION',
          'bundle': bundle['version'], 'targeted_tests': 88, 'targeted_assertions': 294,
          'quality': quality, 'implementation': [ref(ROOT / path) for path in files],
          'knowledge': [ref(bundlePath), ref(catPath), ref(controls)],
          'captures': [ref(E / name) for name in ['synthetic-d1199-options.xml',
                                               'synthetic-options-input.json', 'synthetic-options-result.json']],
          'production_assessment': ref(E / 'production-assessment.json'), 'official_sources_verified': 51,
          'prior_implementation_reports_verified': [ref(path) for path in previous],
          'preserved_failure': ref(E / 'options-phpstan.json'),
          'monthly_command_integration_complete': False, 'gross_debit_recalculation_integrated': False,
          'full_suite_after50_run': False, 'scope28_complete': False,
          'phase4b_complete': False, 'production_ready': False}
path = E / 'options-development-validation.json'
raw = json.dumps(report, ensure_ascii=False, indent=2) + '\n'
if path.exists():
    assert path.read_text(encoding='utf-8') == raw
else:
    with path.open('x', encoding='utf-8') as stream:
        stream.write(raw)
print('PASS:88 tests/294 assertions; own knowledge hashes; native options replay;51 sources valid and production blocked')
