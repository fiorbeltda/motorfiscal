"""Accept generation 73 development only with terminal regression and preserved evidence."""
from pathlib import Path
import hashlib
import json
import re
import sys
from decimal import Decimal, ROUND_HALF_UP

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-partial-export-fx73-preparation-20261014'
Q = ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def require(condition, message):
    if not condition:
        raise RuntimeError(message)


def plain(path):
    return re.sub(r'\x1b\[[0-9;]*m', '', path.read_text(encoding='utf-8'))


def money(value):
    return format(value.quantize(Decimal('0.01'), rounding=ROUND_HALF_UP), 'f')


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True,
                                     separators=(',', ':')).encode('utf-8')).hexdigest()


evidence = []
preflight = '--preflight' in sys.argv
checkpoint = read(E / 'handoff-checkpoint-validation.json')
for item in checkpoint['implementation']:
    require(sha(ROOT / item['path']) == item['sha256'], 'Checkpoint mismatch: ' + item['path'])
bundle = read(ROOT / checkpoint['implementation'][5]['path'])
require(bundle['status'] == 'SANDBOX_CANDIDATE', 'Bundle production status changed')
for key in ['fact_definitions', 'questions', 'rtc_code_catalog']:
    resource = ROOT / 'resources/fiscal' / bundle['resource_paths'][key]
    require(digest(read(resource)) == bundle['content_hashes'][key], 'Bundle resource mismatch: ' + key)
controls = ROOT / 'resources/fiscal' / bundle['production_controls']['path']
require(digest(read(controls)) == bundle['production_controls']['sha256'], 'Production controls mismatch')

labels = ['scope73-final-directed', 'scope73-phpstan-all',
          'scope73-pint-all', 'scope73-final-pint-all', 'scope73-native-corpus', 'scope73-production-assess']
if not preflight:
    labels.append('scope73-full-suite')
for label in labels:
    record = read(Q / (label + '.json'))
    log = Q / (label + '.log')
    require(record['exit_code'] == 0, 'Failed command: ' + label)
    require(sha(log) == record['log_sha256'], 'Log hash mismatch: ' + label)
    evidence.extend([Q / (label + '.json'), log])

directed = re.search(r'Tests:\s+50 passed \((213) assertions\)', plain(Q / 'scope73-final-directed.log'))
require(directed is not None, 'Missing terminal directed result')
suite = None
if not preflight:
    suite = re.search(r'Tests:\s+2266 passed \((\d+) assertions\)', plain(Q / 'scope73-full-suite.log'))
    require(suite is not None, 'Missing terminal full result for 2266 tests')
require('[OK] No errors' in plain(Q / 'scope73-phpstan-all.log'), 'Missing PHPStan success')
require('501 files' in plain(Q / 'scope73-pint-all.log'), 'Missing Pint scope')
style = re.search(r'PASS\s+\.+\s+(\d+) files', plain(Q / 'scope73-final-pint-all.log'))
require(style is not None, 'Missing current terminal Pint result')
assessment = read(Q / 'scope73-production-assess.log')
require(assessment['ready'] is False and assessment['eligible_for_local_validation'] is True,
        'Production/local eligibility separation changed')
require(assessment['bundle_id'] == bundle['bundle_id'], 'Readiness bundle mismatch')
require(assessment['controls_hash'] == bundle['production_controls']['sha256'], 'Readiness controls mismatch')
require(assessment['official_sources_checked'] > 0
        and assessment['official_source_hashes_valid'] == assessment['official_sources_checked'],
        'Readiness official source hash check failed')

manifest = read(E / 'export-integrated-case-manifest.json')
corpus = read(E / 'export-integrated-corpus-validation.json')
require(len(manifest['cases']) == len(corpus['cases']) == 12, 'Expected twelve export cases')
require(len(corpus['prior_compatibility']) == 18, 'Expected eighteen prior replays')
for expected, case in zip(manifest['cases'], corpus['cases'], strict=True):
    require(all(case[key] == value for key, value in expected.items()), 'Manifest mismatch: ' + case['name'])
    snapshot = E / (case['name'] + '-export-snapshot.json')
    require(case['exact_replay'] and sha(snapshot) == case['snapshot_sha256'], 'Snapshot mismatch')
    for suffix in ['-export-capture', '-export-replay']:
        record_path = E / (case['name'] + suffix + '.json')
        log_path = record_path.with_suffix('.log')
        record = read(record_path)
        require(record['exit_code'] == 0 and sha(log_path) == record['log_sha256'], 'Case command mismatch')
        evidence.extend([record_path, log_path])
    captured = read(E / (case['name'] + '-export-capture.log'))
    require(case['expected_reason'] in captured['reason_codes'], 'Missing expected gate')
    require(captured['status'] == 'ACTION_REQUIRED', 'Unexpected fiscal final acceptance')
    scenario = read(ROOT / case['path'])
    trace = {row['stage']: row for row in captured['trace']}
    if case['nominal_path']:
        original = Decimal(scenario['facts']['rtc.gross_amount'])
        if case['currency'] != 'BRL':
            fx = trace['PARTIAL_EXPORT_FX_TRANSACTION']
            rate = Decimal(fx['conversion']['selected_quote']['rate'])
            require(fx['document_authenticated'] is False and fx['eligible_for_posting'] is False,
                    'FX documentary/posting guard changed')
            gross = Decimal(money(original * rate))
            stages = [row['stage'] for row in captured['trace']]
            require(stages.index('PARTIAL_EXPORT_FX_TRANSACTION') < stages.index('PARTIAL_EXPORT_GROSS_ALLOCATION'),
                    'Allocation happened before conversion')
        else:
            gross = original
        foreign = Decimal(money(gross * Decimal(scenario['facts']['rtc.export_share_percent']) / Decimal('100')))
        domestic = gross - foreign
        independent = {'total_gross_brl': money(gross), 'foreign_immune_gross_brl': money(foreign),
                       'domestic_taxable_base_brl': money(domestic),
                       'ibs_state_nominal_brl': money(domestic * Decimal('0.001')),
                       'ibs_municipal_nominal_brl': '0.00',
                       'cbs_nominal_brl': money(domestic * Decimal('0.009'))}
        require(independent == case['independent_decimal'], 'Independent monetary oracle mismatch')
        for key, value in independent.items():
            stage = 'PARTIAL_EXPORT_GROSS_ALLOCATION' if key == 'total_gross_brl' else 'PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION'
            require(trace[stage][key] == value, 'Captured money mismatch: ' + key)
    else:
        require('PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION' not in trace, 'Refusal produced nominal tax')
    replay = read(E / (case['name'] + '-export-replay.log'))
    require(replay['replay_matches'] is True, 'Export replay mismatch')
    evidence.extend([snapshot, ROOT / case['path']])
for case in corpus['prior_compatibility']:
    snapshot = ROOT / case['path']
    require(case['exact_replay'] and sha(snapshot) == case['sha256'], 'Historical snapshot mismatch')
    record_path = E / ('prior-' + snapshot.stem + '-export-replay.json')
    log_path = record_path.with_suffix('.log')
    record = read(record_path)
    require(record['exit_code'] == 0 and sha(log_path) == record['log_sha256'], 'Historical replay command mismatch')
    require(read(log_path)['replay_matches'] is True, 'Historical replay mismatch')
    evidence.extend([snapshot, record_path, log_path])

source = read(E / 'ibs-conversion-partial-export-source.json')
require(sha(ROOT / source['source_path']) == source['source_sha256'], 'Legal source mismatch')
excerpt = E / 'ibs-conversion-and-partial-export-pages.txt'
require(sha(excerpt) == source['excerpt_sha256'], 'Legal excerpt mismatch')
evidence.extend([ROOT / source['source_path'], excerpt, E / 'ibs-conversion-partial-export-source.json',
                 E / 'handoff-checkpoint-validation.json', E / 'export-integrated-case-manifest.json',
                 E / 'export-integrated-corpus-validation.json'])
require(corpus['document_obligation_complete'] is False and corpus['production_ready'] is False, 'Document/production gate changed')
report = {
    'status': ('EXPORT73_EVIDENCE_PREFLIGHT_VERIFIED_FULL_REGRESSION_PENDING' if preflight else
               'EXPORT73_FX_ALLOCATION_DEVELOPMENT_REGRESSION_ACCEPTED_DOCUMENT_SCOPE_PENDING'),
    'full_suite_complete': not preflight,
    'full_suite': None if preflight else {'tests': 2266, 'assertions': int(suite[1])},
    'directed_tests': 50, 'directed_assertions': 213,
    'current_pint_files': int(style[1]),
    'native_export_cases': 12, 'exact_prior_replays': 18,
    'production_assessment': {'ready': False, 'eligible_for_local_validation': True,
                              'official_sources_checked': assessment['official_sources_checked'],
                              'official_source_hashes_valid': assessment['official_source_hashes_valid'],
                              'blockers': assessment['blockers']},
    'implementation': checkpoint['implementation'],
    'evidence': [{'path': p.relative_to(ROOT).as_posix(), 'sha256': sha(p)} for p in dict.fromkeys(evidence)],
    'quality_timing': {'directed_after_final_formatting': True,
                       'full_suite_after_final_formatting': not preflight,
                       'phpstan_before_final_formatting': True,
                       'pint_after_final_formatting': True},
    'document_obligation_complete': False, 'scope73_complete': False,
    'phase4b_complete': False, 'production_ready': False,
}
target = 'export-integration-preflight-validation.json' if preflight else 'export-integration-development-validation.json'
with (E / target).open('x', encoding='utf-8') as stream:
    json.dump(report, stream, indent=2)
print('PASS: generation73 checkpoint hashes,12 export cases,18 historical replays; preflight=' + str(preflight))
