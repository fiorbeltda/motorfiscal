"""Verify the native monthly BCN declaration integration without replacing evidence."""
from pathlib import Path
import hashlib
import json
import re
import xml.etree.ElementTree as ET
from dere_daily_memory_oracle import digest

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base50-closing-draft-20261009'
OLD = ROOT / 'storage/app/phase4b-private-consolidation48-draft-20261009-28'
F = ROOT / 'resources/fiscal'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def ref(path):
    return {'path': path.relative_to(ROOT).as_posix(), 'sha256': sha(path)}


quality = {}
for label in ['command-integration-tests', 'command-pint', 'command-phpstan',
              'command-pint-all', 'command-phpstan-all', 'command-full-suite',
              'command-candidate27', 'command-candidate26', 'command-candidate25',
              'command-financial-run', 'command-monthly-projection']:
    path = E / (label + '.json')
    record = read(path)
    assert record['exit_code'] == 0, label
    assert record['log_sha256'] == sha(E / (label + '.log')), label
    quality[label] = ref(path)

targeted = (E / 'command-integration-tests.log').read_text(encoding='utf-8')
assert '12 passed' in targeted and '56 assertions' in targeted
full = re.sub(r'\x1b\[[0-9;]*m', '', (E / 'command-full-suite.log').read_text(encoding='utf-8'))
counts = re.search(r'([\d,]+) passed.*?\(([\d,]+) assertions\)', full, re.S)
assert counts, 'Full suite needs a terminal passing count'
full_tests, full_assertions = (int(value.replace(',', '')) for value in counts.groups())
assert full_tests >= 1936 and full_assertions > 44012

prototype = read(E / 'options-development-validation.json')
for item in prototype['implementation']:
    assert sha(ROOT / item['path']) == item['sha256'], item['path']
for item in prototype['prior_implementation_reports_verified']:
    path = ROOT / item['path']
    assert sha(path) == item['sha256']
    prior = read(path)
    for source in prior.get('implementation_hashes', prior.get('implementation', [])):
        assert sha(ROOT / source['path']) == source['sha256'], source['path']

evolution = read(E / 'monthly-command-source-evolution.json')
assert evolution['before_sha256'] == evolution['preserved_sha256']
assert sha(ROOT / evolution['preserved_source']) == evolution['before_sha256']
assert sha(ROOT / evolution['source_path']) != evolution['before_sha256']

bundle_path = F / 'bundles/consumption-phase4b-2026.10.09-28-draft50.json'
bundle = read(bundle_path)
catalog_path = F / bundle['resource_paths']['rtc_code_catalog']
catalog = read(catalog_path)
assert digest(catalog) == bundle['content_hashes']['rtc_code_catalog']
for source in bundle['official_sources']:
    assert sha(F / 'sources/official' / source['file']) == source['sha256']
assert len(bundle['official_sources']) == 51
gate = read(E / 'production-assessment.json')
assert gate['ready'] is False and gate['official_source_hashes_valid'] == 51

snapshot = read(E / 'command-financial-snapshot.json')
month = read(E / 'command-month-input.json')
request = read(E / 'command-negative-base-request.json')
report = read(E / 'command-monthly-report.json')
assert snapshot['knowledge']['rule_bundle_id'] == bundle['bundle_id'] == report['bundle_id']
assert snapshot['knowledge']['documents']['rtc_code_catalog'] == catalog
assert report['snapshot_hash'] == request['snapshot_hash'] == digest(snapshot)
assert report['monthly_input_hash'] == request['monthly_input_hash'] == digest(month)
assert report['negative_base_request_hash'] == digest(request)
assert report['negative_base_request_input'] == request
assert report['decision_hash'] == snapshot['result']['decision_hash']
assert report['reason'] == 'RTC_DERE_UNSIGNED_LOCAL_MONTHLY_CANDIDATES'
assert report['production_ready'] is False
option = report['projection']['negative_base_options_projection']
assert option['baseline_projection_hash'] == request['baseline_projection_hash']
assert option['request_hash'] == digest(request)
assert option['policy_hash'] == catalog['dere_negative_base_closing_options_projection']['source_hash']
assert option['status'] == 'PROJECTED' and option['synthetic'] is True
for flag in ['gross_debits_recalculated', 'period_closed', 'signature_created',
             'transmission_performed', 'production_ready']:
    assert option[flag] is False

monthly_replay = read(E / 'command-monthly-replay.json')
assert monthly_replay['exit_code'] == 0 and monthly_replay['projection_matches'] is True
assert monthly_replay['prior_report_sha256'] == sha(E / 'command-monthly-report.json')
assert read(E / 'command-financial-replay.json')['replay_matches'] is True

xmls = []
assert set(report['event_validations']) == {'D1101', 'D1199', 'D1121', 'D1011', 'D1001'}
for name, validation in report['event_validations'].items():
    path = E / ('command-monthly-candidate.xml' + ('' if name == 'D1199' else '.' + name + '.xml'))
    assert validation['xml_sha256'] == sha(path)
    assert validation['reason'] == 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE'
    assert validation['unsigned_candidate_structure_checked'] is True
    assert validation['signature_verified'] is False and validation['production_ready'] is False
    assert len(validation['errors']) == 1 and 'Signature' in validation['errors'][0]['message']
    if name != 'D1199':
        assert path.read_bytes() == (OLD / ('first-gain-candidate.xml.' + name + '.xml')).read_bytes()
    xmls.append(ref(path))

new = ET.fromstring((E / 'command-monthly-candidate.xml').read_bytes())
groups = list(new.iter())
options = [node for node in groups if node.tag.endswith('}gUtilizBCN')]
assert len(options) == 1
for parent in groups:
    if options[0] in list(parent):
        parent.remove(options[0])
for parent in list(new.iter()):
    for child in list(parent):
        if child.tag.endswith('}infoFechamento') and len(child) == 0:
            parent.remove(child)
before = ET.fromstring((OLD / 'first-gain-candidate.xml').read_bytes())
assert ET.canonicalize(ET.tostring(new, encoding='unicode'), rewrite_prefixes=True) == ET.canonicalize(
    ET.tostring(before, encoding='unicode'), rewrite_prefixes=True)

files = ['app/Console/Commands/ProjectDereMonthlyClosingXmlCommand.php',
         'tests/Fiscal/Integration/DereNegativeBaseMonthlyCommand50Test.php',
         'scripts/capture_phase4b_negative_base50_check.py',
         'scripts/audit_phase4b_negative_base50_monthly_command.py',
         'docs/fiscal/phase4b-negative-base-monthly-command50-development.md']
captures = ['command-financial-input.json', 'command-financial-snapshot.json',
            'command-month-input.json', 'command-negative-base-request.json',
            'prepare-command-request.php', 'command-monthly-report.json',
            'command-monthly-replay.json', 'command-financial-replay.json',
            'command-original-components-preservation.json', 'monthly-command-source-evolution.json']
result = {'status': 'NATIVE_MONTHLY_BCN_DECLARATION_INTEGRATION_VALIDATED',
          'bundle': bundle['version'], 'targeted_tests': 12, 'targeted_assertions': 56,
          'full_suite_tests': full_tests, 'full_suite_assertions': full_assertions,
          'quality': quality, 'implementation': [ref(ROOT / name) for name in files],
          'captures': [ref(E / name) for name in captures], 'xml_candidates': xmls,
          'prototype_preserved': ref(E / 'options-development-validation.json'),
          'preserved_auditor_identity_failure': ref(E / 'command-audit-initial.json'),
          'preserved_auditor_source': ref(E / 'before-bundle-identity-auditor.py'),
          'production_assessment': ref(E / 'production-assessment.json'),
          'official_sources_verified': 51, 'original_auxiliary_xmls_unchanged': 4,
          'd1199_only_bcn_options_changed': True, 'monthly_command_integration_complete': True,
          'financial_replay_matches': True, 'monthly_replay_matches': True,
          'gross_debit_recalculation_integrated': False, 'received_return_authenticity_verified': False,
          'expiry_calendar_qualified': False, 'dependent_periods_reprocessed': False,
          'scope28_complete': False, 'phase4b_complete': False, 'production_ready': False}
path = E / 'command-development-validation.json'
raw = json.dumps(result, ensure_ascii=False, indent=2) + '\n'
if path.exists():
    assert path.read_text(encoding='utf-8') == raw
else:
    with path.open('x', encoding='utf-8') as stream:
        stream.write(raw)
print(f'PASS: native monthly BCN declaration; 12/56 targeted; {full_tests}/{full_assertions} full; '
      'five XMLs, two exact replays, prior sources preserved and production blocked')
