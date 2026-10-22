"""Independent preservation, fiscal-decision, XML and terminal-evidence audit128."""
import argparse
import copy
import hashlib
import json
import re
import subprocess
import xml.etree.ElementTree as ET
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
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
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
checks = 0
def require(condition, label):
    global checks
    if not condition:
        raise AssertionError(label)
    checks += 1
bundle_path = F / 'bundles/consumption-phase4b-2026.10.22-128-draft1.json'
bundle = read(bundle_path)
catalog = read(F / bundle['resource_paths']['rtc_code_catalog'])
prior = read(F / 'catalogs/rtc-codes-2026.10.22-127-draft1.json')
require(canonical(catalog['dps_foreign_policy128']) == 'ec67de6d5bfeb9201a70e3cb2dc78d315b790a705e134af1e3e862dafbd6e75b', 'policy128 binding')
require(canonical(catalog) == bundle['content_hashes']['rtc_code_catalog'], 'catalog canonical hash')
require(canonical(read(F / bundle['production_controls']['path'])) == bundle['production_controls']['sha256'], 'production control binding')
for key in prior:
    if key not in ['version', 'local_xml_projection']:
        require(canonical({'value': catalog[key]}) == canonical({'value': prior[key]}), 'preserved catalog ' + key)
expected_projection = copy.deepcopy(prior['local_xml_projection'])
expected_projection['contract_version'] = 'DPS-LOCAL-FOREIGN-ACQUIRER-2026.10.22-128'
require(expected_projection == catalog['local_xml_projection'], 'only declared projection-contract change')
for source in bundle['official_sources']:
    require(sha(F / 'sources/official' / source['file']) == source['sha256'], 'source bytes ' + source['id'])
capture = read(args.evidence / 'primary-source-v1/capture.json')
for source in capture['records']:
    require(sha(args.evidence / 'primary-source-v1' / source['file']) == source['sha256'], 'recapture bytes')
require(next(x for x in capture['records'] if x['file'].endswith('.zip'))['sha256'] == catalog['dps_xsd']['source_sha256'], 'fresh official bytes unchanged')
native = read(args.evidence / 'native-v1/validation.json')
require(native['complete'] and native['exit_code'] == 0 and native['parse_errors'] == [], 'native terminal')
require(native['native_decisions'] == native['exact_replays'] == 24 and native['xml_files'] == 12, 'native population')
snapshots = {}
for item in native['snapshots']:
    path = args.evidence / 'native-v1' / item['file']
    require(sha(path) == item['sha256'], 'snapshot bytes')
    require(item['replay_matches'] and item['declared_document_changes_only'], 'exact native replay')
    snapshot = read(path)
    original = read(F / item['original_scenario'])
    require(canonical(snapshot['input']['facts']) == canonical(original['facts']), 'all typed facts unchanged')
    snapshots[item['case'], item['runner'], item['version']] = snapshot
def rtc_assessment(snapshot, runner):
    if runner == 'rtc':
        return snapshot['result']
    return next(item['payload'] for item in snapshot['result']['trace'] if item['type'] == 'CONSUMPTION_REFORM')
names = sorted({item['case'] for item in native['snapshots']})
require(len(names) == 6, 'six source scenarios')
for name in names:
    for runner in ['rtc', 'integrated']:
        old_snapshot = snapshots[name, runner, '127']
        current_snapshot = snapshots[name, runner, '128']
        old = rtc_assessment(old_snapshot, runner)
        current = rtc_assessment(current_snapshot, runner)
        expected = copy.deepcopy(old['decision'])
        require(expected['catalog_versions']['codes'] == '2026.10.22-127-draft1', 'prior version explicit')
        expected['catalog_versions']['codes'] = '2026.10.22-128-draft1'
        require(canonical(expected) == canonical(current['decision']), 'entire RTC decision preserved except version')
        for key in ['status', 'reason_codes', 'missing_facts']:
            require(old[key] == current[key], 'unchanged ' + key)
        if runner == 'integrated':
            for key in ['iss', 'federal_withholdings']:
                require(canonical(old_snapshot['result']['decision'][key]) == canonical(current_snapshot['result']['decision'][key]), 'legacy decision preserved')
N = '{http://www.sped.fazenda.gov.br/nfse}'
for item in native['xmls']:
    path = args.evidence / 'native-v1' / item['file']
    require(sha(path) == item['sha256'], 'projection receipt bytes')
    record = read(path)
    require(record['validation']['status'] == 'VALID' and record['validation']['schema_sha256'] == catalog['dps_xsd']['source_sha256'], 'current XSD validation')
    require(record['first_xml_sha256'] == record['second_xml_sha256'] == record['validation']['xml_sha256'], 'deterministic XML pair')
    identity = record['identity']
    require(identity['environment'] == '2' and record['production_ready'] is False, 'local environment only')
    for key in ['first_xml', 'second_xml']:
        path = args.evidence / 'native-v1' / record[key+'_file']
        require(sha(path) == record[key+'_sha256'], 'XML bytes')
        xml = ET.fromstring(path.read_bytes())
        info = xml.find(N+'infDPS')
        require(info.find(N+'finNFSe').text == '0' and info.find(N+'IBSCBS/'+N+'finNFSe') is None, 'new purpose path')
        require(xml.find('.//{http://www.w3.org/2000/09/xmldsig#}Signature') is None, 'unsigned output')
        if record['case'].startswith('foreign-'):
            binding = record['foreign_binding']
            require(binding['document_obligation_complete'] is False and binding['comext_mode_fiscally_qualified'] is False, 'no documentary/fiscal approval')
            require(binding['identity_authenticity_verified'] is False and binding['currency_competence_validity_proven'] is False, 'no authenticity/currency approval')
            require(binding['party_input_hash'] == canonical(identity['foreign_acquirer']), 'party input bound')
            require(binding['identity_input_hash'] == canonical(identity), 'identity input bound')
            party = identity['foreign_acquirer']
            toma = info.find(N+'toma')
            nif = toma.find(N+'NIF')
            no_nif = toma.find(N+'cNaoNIF')
            require((nif is not None) != (no_nif is not None), 'exclusive NIF choice')
            require((nif.text if nif is not None else no_nif.text) == party.get('nif', party.get('no_nif_reason')), 'NIF value retained')
            require(toma.find(N+'end/'+N+'endExt/'+N+'cPais').text == party['country'], 'foreign country retained')
            require(toma.find(N+'xNome').text == party['name'], 'party text/escaping retained')
            require(info.find(N+'IBSCBS/'+N+'dest/'+N+'CNPJ').text == '22222222000191', 'domestic recipient distinct')
            for tag, value in identity['foreign_trade'].items():
                require(info.find(N+'serv/'+N+'comExt/'+N+tag).text == value, 'literal COMEX ' + tag)
        else:
            require(record['foreign_binding'] is None and info.find(N+'serv/'+N+'comExt') is None, 'no foreign fields invented for domestic route')
quality = {}
for label in ['final-directed-v1', 'phpstan-v1', 'pint-final-v1', 'production-assess-v1']:
    folder = args.evidence / label
    receipt = read(folder / 'execution.json')
    require(receipt['status'] == 'TERMINAL' and receipt['exit_code'] == 0, 'terminal gate ' + label)
    for channel in ['stdout', 'stderr']:
        require(sha(folder / (channel+'.log')) == receipt[channel+'_sha256'], 'gate log bytes')
    quality[label] = {'exit_code': 0, 'receipt_sha256': sha(folder / 'execution.json')}
log = (args.evidence / 'final-directed-v1/stdout.log').read_text(encoding='utf-8')
counts = re.search(r'(\d+) passed \((\d+) assertions\)', log)
require(counts is not None and int(counts[1]) >= 340, 'observed directed test population')
assessment = read(args.evidence / 'production-assess-v1/stdout.log')
require(assessment['ready'] is False and assessment['eligible_for_local_validation'] is True, 'production separated')
require(assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 106, 'source inventory')
old_assessment = read(Path(r'D:\ProjetoSaasExports\phase4b-dps127-20261022-v1\production-assess-v1\stdout.log'))
require(set(assessment['blockers']) == set(old_assessment['blockers']) | {'DPS128_FOREIGN_PARTY_COMEXT_LOCAL_ONLY_NOT_DOCUMENT_OBLIGATION_APPROVAL'}, 'no prior blockers dropped')
for label in ['historical122-v1', 'historical126-127-v1']:
    historical = read(args.evidence / label / 'validation.json')
    require(historical['exit_code'] == 0 and historical['exact_replays'] == 12, 'preserved historical sample ' + label)
prior_receipt = read(ROOT / 'storage/app/phase4b-dps127-development-20261022/dps127-scoped-validation.json')
require(sha(F / 'bundles/consumption-phase4b-2026.10.22-127-draft1.json') == prior_receipt['bundle_sha256'], 'bundle127 unchanged')
require(subprocess.check_output(['git', 'diff', '--name-only', '--', 'resources/fiscal'], cwd=ROOT, text=True).strip() == '', 'tracked historical fiscal resources unchanged')
runtime = set(prior_receipt['runtime_hashes']) | {'app/Modules/Fiscal/ConsumptionReform/DpsForeignPolicy128.php',
    'app/Modules/Fiscal/NFSe/DpsForeignPartyProjector.php', 'app/Modules/Fiscal/NFSe/DpsForeignAcquirerCandidate.php'}
report = {'created_at': datetime.now(timezone.utc).isoformat(), 'status': 'FOREIGN128_CURRENT_XSD_PARTY_COMEX_SCOPED_VALIDATED_NOT_PHASE4B_CLOSURE',
    'audit_checks': checks, 'tests': int(counts[1]), 'assertions': int(counts[2]), 'full_suite_executed': False,
    'quality': quality, 'native_decisions': 24, 'current128_decisions': 12, 'comparison127_decisions': 12,
    'native_exact_replays': 24, 'unsigned_xsd_valid_xmls': 12, 'historical_sample_exact_replays': 24,
    'official_sources_valid': 106, 'production_blockers': len(assessment['blockers']), 'ready': False, 'eligible_for_local_validation': True,
    'bundle': 'bundles/'+bundle_path.name, 'bundle_sha256': sha(bundle_path), 'runtime_hashes': {path: sha(ROOT / path) for path in sorted(runtime)},
    'formal_phase4b_groups_closed': 0, 'phase4b_complete': False, 'evidence_root': str(args.evidence),
    'scope': 'FOREIGN_ACQUIRER_DOMESTIC_RECIPIENT_AND_DOMESTIC_COMPATIBILITY_NOT_IMPORT_OR_PARTIAL_EXPORT_DOCUMENT_CLOSURE'}
args.output.parent.mkdir(parents=True, exist_ok=True)
with args.output.open('x', encoding='utf-8', newline='\n') as handle:
    handle.write(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({key: report[key] for key in ['status', 'audit_checks', 'tests', 'assertions', 'unsigned_xsd_valid_xmls', 'production_blockers']}))
