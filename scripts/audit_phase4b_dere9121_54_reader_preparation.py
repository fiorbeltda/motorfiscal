"""Verify prototype evidence without claiming installed reader or whole54 completion."""
from pathlib import Path
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def ref(path):
    return {'path': path.relative_to(ROOT).as_posix(), 'sha256': sha(path)}

assert not (ROOT / 'app/Modules/Fiscal/ConsumptionReform/DerePublicOfferDebtReturnXmlReader.php').exists()
contract = read(E / 'research-contract.json')
for source in contract['sources']:
    assert sha(ROOT / source['source_path']) == source['source_sha256']
    assert sha(ROOT / source['excerpt_path']) == source['excerpt_sha256']
assert sha(E / 'evtRetornoTitPub-v0_0_2.xsd') == contract['schema_sha256']
quality = []
for label in ['reader-native-v2-capture', 'reader-native-v2-replay', 'reader-guarded-phpstan', 'reader-planned-final-pint']:
    record = read(E / (label + '.json'))
    assert record['exit_code'] == 0 and record['log_sha256'] == sha(E / (label + '.log'))
    quality.append(ref(E / (label + '.json')))
native = read(E / 'reader-native-v2-result.json')
assert len(native['cases']) == 16
assert native['cases']['zero']['total_vApur'] == '0.00'
assert native['cases']['positive']['total_vApur'] == '98.17'
for name, result in native['cases'].items():
    assert not result['production_ready'] and not result['received_return_verified']
    if name in ['zero', 'positive']:
        assert result['status'] == 'LOCAL_D9121_AGGREGATE_RECONCILED'
        assert not result['composition_integrated'] and not result['active_origin_version_qualified']
    else:
        assert result['status'] == 'ACTION_REQUIRED'
        expected = ('EXPLICIT_SYNTHETIC_BOUND_SCOPE_REQUIRED' if name in ['real', 'origin-hash'] else
            'ORIGIN_AGGREGATE_AMOUNT_CONFLICT' if name == 'amount' else
            'TOTALIZATION_REQUIRED' if name == 'missing-total' else
            'SANDBOX_NON_EXCLUSION_ORIGIN_REQUIRED' if name in ['production-origin', 'excluded-origin'] else
            'RETURN_OR_ORIGIN_CONTEXT_CONFLICT')
        assert result['reason'] == 'RTC_DERE_D9121_' + expected, name
assert read(E / 'synthetic-schema-check.json')['exit_code'] == 1
assert read(E / 'reader-planned-phpstan.json')['exit_code'] == 1
assert sha(E / 'before-pint-planned-reader.php') == sha(E / 'planned-DerePublicOfferDebtReturnXmlReader.php')
context = read(E / 'synthetic-d9121-context.json')
assert sha(ROOT / context['source_report_path']) == context['source_report_sha256']
assert sha(ROOT / context['source_d2101_path']) == context['source_d2101_sha256']
files = [E / 'planned-DerePublicOfferDebtReturnXmlReader.php', E / 'exercise-planned-reader-v2.php',
    ROOT / 'scripts/capture_phase4b_dere9121_54_check.py', Path(__file__),
    ROOT / 'docs/fiscal/phase4b-dere9121-54-reader-preparation.md']
with (E / 'reader-preparation-validation.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'LOCAL_D9121_READER_PROTOTYPE_VALIDATED_NOT_INSTALLED', 'native_cases': 16,
        'exact_replay': True, 'quality': quality, 'implementation': [ref(p) for p in files],
        'source_contract': ref(E / 'research-contract.json'), 'native_capture': ref(E / 'reader-native-v2-result.json'),
        'reader_installed': False, 'composition_integrated': False, 'active_version_qualified': False,
        'scope54_complete': False, 'phase4b_complete': False, 'production_ready': False}, stream, indent=2)
print('PASS: 16 planned-reader cases and exact replay, immutable sources, preserved failures; integration pending')
