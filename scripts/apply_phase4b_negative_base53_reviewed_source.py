"""Record completed52 quality, then install reviewed53 source with historical archive."""
from pathlib import Path
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base53-command-integration-20261009'
P = ROOT / 'storage/app/phase4b-negative-base52-settlement-draft-20261009'

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

quality = []
for label in ['settlement-full-suite', 'settlement-phpstan-all', 'settlement-pint-all']:
    record = read(P / (label + '.json'))
    assert record['exit_code'] == 0 and record['log_sha256'] == sha(P / (label + '.log'))
    quality.append({'path': str(P / (label + '.json')), 'sha256': sha(P / (label + '.json'))})
log = (P / 'settlement-full-suite.log').read_text(encoding='utf-8')
assert '1974 passed' in log and '44446 assertions' in log
target = ROOT / 'app/Console/Commands/ProjectDereMonthlyClosingXmlCommand.php'
archive = E / 'before-settlement-selection-monthly-command.php'
review = read(E / 'reviewed-source-preparation.json')
planned = E / 'planned-monthly-command-v2.php'
test = ROOT / 'tests/Fiscal/Integration/DereNegativeBaseMonthlyCommand53Test.php'
assert sha(target) == sha(archive) == review['before_sha256']
assert sha(planned) == review['reviewed_proposal_sha256']
assert not test.exists()
with (P / 'settlement-full-quality-supplement.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'LOCAL_SETTLEMENT52_FULL_QUALITY_VALIDATED', 'tests': 1974, 'assertions': 44446,
        'quality': quality, 'command_sha256_at_validation': sha(target),
        'historical_prototype_report_preserved': True, 'production_ready': False}, stream, indent=2)
target.write_bytes(planned.read_bytes())
with test.open('xb') as stream:
    stream.write((E / 'planned-command-test.php').read_bytes())
with (E / 'command-source-application.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'REVIEWED53_SOURCE_APPLIED_VALIDATION_PENDING', 'before_sha256': sha(archive),
        'applied_sha256': sha(target), 'integration_test_sha256': sha(test),
        'accepted50_source_archive': str(archive), 'production_ready': False}, stream, indent=2)
print('PASS: full52 quality recorded; reviewed53 source installed; tests pending')
