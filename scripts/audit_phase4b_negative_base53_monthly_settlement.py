"""Accept local53 integration only with full quality, migration and native evidence."""
from pathlib import Path
import hashlib
import json
import re
from decimal import Decimal, ROUND_HALF_EVEN

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base53-command-integration-20261009'
P = ROOT / 'storage/app/phase4b-negative-base50-closing-draft-20261009'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def ref(path):
    return {'path': path.relative_to(ROOT).as_posix(), 'sha256': sha(path)}

quality = []
for label in ['command-full-suite', 'command-phpstan-all', 'command-pint-all', 'command-initial-tests',
              'native-settlement-capture', 'compatibility-after-check', 'command-candidate27', 'command-candidate26', 'command-candidate25']:
    record = read(E / (label + '.json'))
    assert record['exit_code'] == 0 and record['log_sha256'] == sha(E / (label + '.log')), label
    quality.append(ref(E / (label + '.json')))
full_log = re.sub(r'\x1b\[[0-9;]*m', '', (E / 'command-full-suite.log').read_text(encoding='utf-8'))
counts = re.search(r'(\d+) passed \((\d+) assertions\)', full_log)
assert counts and int(counts[1]) == 1987 and int(counts[2]) > 44446
source = ROOT / 'app/Console/Commands/ProjectDereMonthlyClosingXmlCommand.php'
archive = E / 'before-settlement-selection-monthly-command.php'
application = read(E / 'command-source-application.json')
assert sha(archive) == application['before_sha256']
assert sha(E / 'before-pint-monthly-command.php') == application['applied_sha256']
def formatting_normalized(path):
    lines = path.read_text(encoding='utf-8').splitlines()
    imports = sorted(line for line in lines if line.startswith('use '))
    body = '\n'.join(line for line in lines if not line.startswith('use ')).split()
    return imports, body

assert formatting_normalized(E / 'before-pint-monthly-command.php') == formatting_normalized(source)
for item in read(P / 'command-development-validation.json')['implementation']:
    path = ROOT / item['path']
    if path == source:
        assert sha(archive) == item['sha256']
    else:
        assert sha(path) == item['sha256'], item['path']
compatibility = read(E / 'compatibility-after-source-change.json')
assert compatibility['current_source_sha256'] == sha(source)
assert len(compatibility['cases']) == 3 and all(c['projection_and_event_hashes_match'] for c in compatibility['cases'])
native = read(E / 'native-settlement-validation.json')
assert len(native['cases']) == 2 and all(c['exact_native_replay'] for c in native['cases'])
for prior in [ROOT / 'storage/app/phase4b-negative-base51-current-bases-preparation-20261009/current-base-development-validation.json',
              ROOT / 'storage/app/phase4b-negative-base51-current-bases-preparation-20261009/composition-development-validation.json',
              ROOT / 'storage/app/phase4b-negative-base52-settlement-draft-20261009/settlement-development-validation.json']:
    for item in read(prior)['implementation']:
        assert sha(ROOT / item['path']) == item['sha256'], item['path']
for mode in ['derived', 'both']:
    report_path = E / ('settlement-' + mode + '-report.json')
    capture = next(c for c in native['cases'] if c['mode'] == mode)
    assert sha(report_path) == capture['report_sha256']
    report = read(report_path)
    proposal = report['negative_base_settlement']
    assert proposal == read(E / 'prepared-settlement-proposal.json')
    assert not report['production_ready'] and not proposal['eligible_for_posting']
    assert proposal['original_gross_debits_hash'] == read(E / 'prepared-settlement-proposal.json')['original_gross_debits_hash']
    assert proposal['assessed_pools']['1010I']['assessed_base'] == '841.09'
    assert proposal['assessed_pools']['1010C']['assessed_base'] == '881.09'
    assert {row['codBCN']: row['available_after_proposed_consumption'] for row in proposal['stock_after_proposed_consumption']} == {
        '1010I20260600': '0.00', '1010C20260600': '80.00', '1010C20260801': '0.00'}
    assert format((Decimal('841.09') * Decimal('0.001')).quantize(Decimal('.01'), rounding=ROUND_HALF_EVEN), 'f') == proposal['ibs_nominal_proposal']
    assert format((Decimal('881.09') * Decimal('0.009')).quantize(Decimal('.01'), rounding=ROUND_HALF_EVEN), 'f') == proposal['cbs_nominal_proposal']
    for event, output in report['event_outputs'].items():
        assert sha(ROOT / output) == report['event_validations'][event]['xml_sha256']
        assert report['event_validations'][event]['reason'] == 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE'
files = [source, ROOT / 'tests/Fiscal/Integration/DereNegativeBaseMonthlyCommand53Test.php',
    Path(__file__), ROOT / 'scripts/capture_phase4b_negative_base53_monthly_settlement.py',
    ROOT / 'docs/fiscal/phase4b-negative-base-settlement-command53-development.md']
with (E / 'command-development-validation.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'NATIVE_LOCAL_MONTHLY_BCN_SETTLEMENT_INTEGRATION_VALIDATED',
        'full_tests': int(counts[1]), 'full_assertions': int(counts[2]), 'quality': quality,
        'historical50_command_source_preserved': ref(archive), 'implementation': [ref(p) for p in files],
        'native_settlement_selections': 2, 'preserved_xml_hashes': 10, 'legacy_monthly_executions_preserved': 3,
        'authenticated_government_return': False, 'scope28_complete': False, 'phase4b_complete': False,
        'production_ready': False}, stream, indent=2)
print('PASS: local53 monthly BCN settlement integration, complete suite, legacy migration and native replays')
