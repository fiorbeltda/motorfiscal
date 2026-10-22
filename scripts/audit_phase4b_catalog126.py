"""Independent source-cell, preservation, terminal-check and snapshot audit."""
import argparse
import hashlib
import json
import re
import subprocess
import xml.etree.ElementTree as ET
import zipfile
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FISCAL = ROOT / 'resources/fiscal'
parser = argparse.ArgumentParser()
parser.add_argument('--evidence', type=Path, required=True)
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
assert not args.output.exists()
def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))
def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()
def canonical(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
checks = 0
def require(condition, label):
    global checks
    if not condition:
        raise AssertionError(label)
    checks += 1

catalog = read(FISCAL / 'catalogs/rtc-codes-2026.10.22-126-draft1.json')
prior = read(FISCAL / 'catalogs/rtc-codes-2026.10.21-122-draft1.json')
bundle = read(FISCAL / 'bundles/consumption-phase4b-2026.10.22-126-draft1.json')
reference = catalog['cindop_reference126']
require(canonical(reference) == 'd50657ea5a576b7152ca7108ec4a8c96bbd36d3a7712c5d23466bccb247b968f', 'reference binding')
require(canonical(catalog) == bundle['content_hashes']['rtc_code_catalog'], 'canonical catalog hash')
require(canonical(read(FISCAL / bundle['production_controls']['path'])) == bundle['production_controls']['sha256'], 'canonical controls hash')
for key in prior:
    if key not in ['version', 'status', 'sources', 'cindop']:
        require(catalog[key] == prior[key], 'preserved catalog field ' + key)
for key in prior['sources']:
    if key != 'cindop':
        require(catalog['sources'][key] == prior['sources'][key], 'preserved source ' + key)

# Read OOXML directly, independently of the openpyxl materializer. Do not fill
# merged/blank source cells by inheritance, including the restricted annex.
ns = {'s': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
for edition, name, code_column, columns in [('RTC_1_03_00', 'cindop103.xlsx', 0, 10),
                                            ('RESTRICTED_ANNEX_C_1_01', 'restricted-cindop.xlsx', 6, 9)]:
    path = args.evidence / 'primary-source-v1' / name
    with zipfile.ZipFile(path) as archive:
        strings = []
        if 'xl/sharedStrings.xml' in archive.namelist():
            strings = [''.join(item.itertext()) for item in ET.fromstring(archive.read('xl/sharedStrings.xml')).findall('s:si', ns)]
        rows = {}
        for row in ET.fromstring(archive.read('xl/worksheets/sheet1.xml')).findall('.//s:sheetData/s:row', ns):
            values = [None] * columns
            for cell in row.findall('s:c', ns):
                column = re.match('[A-Z]+', cell.attrib['r'])[0]
                index = 0
                for char in column:
                    index = index * 26 + ord(char) - 64
                if index > columns:
                    continue
                value = cell.find('s:v', ns)
                raw = value.text if value is not None else None
                kind = cell.attrib.get('t')
                if kind in [None, 'n'] and raw is not None:
                    raw = int(raw) if re.fullmatch('-?[0-9]+', raw) else float(raw)
                values[index - 1] = strings[int(raw)] if kind == 's' and raw is not None else (
                    ''.join(cell.find('s:is', ns).itertext()) if kind == 'inlineStr' else raw)
            code = values[code_column]
            if isinstance(code, str) and re.fullmatch('[0-9]{6}', code):
                rows[code] = {'source_row': int(row.attrib['r']), 'values': values}
        require(rows == reference['sources'][edition]['rows'], 'literal cells ' + edition)
        require(sha(path) == reference['sources'][edition]['source']['sha256'], 'source bytes ' + edition)
for source in bundle['official_sources']:
    require(sha(FISCAL / 'sources/official' / source['file']) == source['sha256'], 'official source ' + source['id'])

quality = {}
for label in ['final-directed-v1', 'phpstan-v2', 'pint-final-v2', 'production-assess-v1']:
    folder = args.evidence / label
    receipt = read(folder / 'execution.json')
    require(receipt['status'] == 'TERMINAL' and receipt['exit_code'] == 0, 'terminal ' + label)
    for channel in ['stdout', 'stderr']:
        require(sha(folder / (channel + '.log')) == receipt[channel + '_sha256'], label + ' ' + channel)
    quality[label] = {'receipt_sha256': sha(folder / 'execution.json'), 'exit_code': 0}
text = (args.evidence / 'final-directed-v1/stdout.log').read_text(encoding='utf-8')
require('205 passed' in text, 'directed test population')
counts = re.search(r'205 passed \((\d+) assertions\)', text)
require(counts is not None, 'directed assertions observed')
assessment = read(args.evidence / 'production-assess-v1/stdout.log')
require(assessment['ready'] is False and assessment['eligible_for_local_validation'] is True, 'production gate')
require('CINDOP126_REFERENCE_LOCAL_ONLY_NOT_OFFICIAL_ENVIRONMENT_ACTIVATION' in assessment['blockers'], 'reference blocker')

native = read(args.evidence / 'native-v5/execution.json')
require(native['status'] == 'TERMINAL' and native['exit_code'] == 0 and native['parse_errors'] == [], 'native terminal')
require(native['native_decisions'] == native['exact_replays'] == 32, 'native population')
contexts = set()
for case in native['cases']:
    path = args.evidence / 'native-v5' / case['file']
    require(sha(path) == case['sha256'], 'snapshot bytes ' + case['file'])
    require(case['replay_matches'] and case['fiscal_comparison_122_except_explicit_catalog_version'], 'native comparison ' + case['file'])
    snapshot = read(path)
    require(snapshot['result']['status'] in ['VALID', 'VALID_WITH_WARNINGS', 'ACTION_REQUIRED', 'INVALID'], 'controlled native result')
    contexts.update(case['cindop_contexts'])
require(contexts == {'STANDARD_OPERATION', 'PARTIAL_EXPORT_DOMESTIC'}, 'both integration paths reached')
historical = read(args.evidence / 'historical-v1/validation.json')
require(historical['exit_code'] == 0 and historical['exact_replays'] == historical['checked_snapshot_hashes'] == 12, 'preserved historical sample')

baseline_path = ROOT / 'storage/app/phase4b-credit122-development-20261021/credit122-development-validation.json'
baseline = read(baseline_path)
require(sha(baseline_path) == 'fdf88e29d31319a1f39c79b004335c8ad2a985e9fcc40d3c23b8791ec884202c', 'baseline122 receipt')
changed = [path for path, expected in baseline['runtime_hashes'].items() if sha(ROOT / path) != expected]
require(set(changed) == {'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
    'app/Modules/Fiscal/ConsumptionReform/CreditInputPolicy122.php',
    'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php'}, 'declared runtime edits only')
tracked_fiscal = subprocess.check_output(['git', 'diff', '--name-only', '--', 'resources/fiscal'], cwd=ROOT, text=True)
require(tracked_fiscal.strip() == '', 'no prior tracked fiscal resource overwritten')
report = {'created_at': datetime.now(timezone.utc).isoformat(), 'status': 'CATALOG126_SCOPED_LOCAL_REFERENCE_VALIDATED_NOT_PHASE4B_CLOSURE',
    'audit_checks': checks, 'tests': 205, 'assertions': int(counts[1]), 'full_suite_executed': False,
    'quality': quality, 'native_decisions': 32, 'current126_decisions': 16, 'baseline122_comparison_decisions': 16,
    'native_exact_replays': 32, 'preserved122_historical_sample_exact_replays': 12,
    'official_sources_valid': len(bundle['official_sources']), 'production_blockers': len(assessment['blockers']),
    'ready': False, 'eligible_for_local_validation': True, 'formal_phase4b_groups_closed': 0,
    'phase4b_complete': False, 'runtime_baseline_hashes_unchanged': 94 - len(changed),
    'runtime_declared_changes': changed, 'bundle_sha256': sha(FISCAL / 'bundles/consumption-phase4b-2026.10.22-126-draft1.json'),
    'evidence_root': str(args.evidence), 'limits': ['No official environment activation or legal effective date proved.',
    'Partial export evidence is refused at a changed knowledge cut;no positive documentary closure.',
    'No global credit,document,classification or Phase4B acceptance;no new full regression.']}
args.output.parent.mkdir(parents=True, exist_ok=True)
with args.output.open('x', encoding='utf-8', newline='\n') as handle:
    handle.write(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({key: report[key] for key in ['status', 'audit_checks', 'tests', 'assertions', 'official_sources_valid', 'production_blockers']}))
