"""Audit observed XSD bytes, native XML/decisions, compatibility and final gates."""
import argparse
import copy
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
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
checks = 0
def require(condition, label):
    global checks
    if not condition:
        raise AssertionError(label)
    checks += 1

bundle_path = FISCAL / 'bundles/consumption-phase4b-2026.10.22-127-draft1.json'
bundle = read(bundle_path)
catalog = read(FISCAL / bundle['resource_paths']['rtc_code_catalog'])
policy = catalog['dps_layout_policy127']
source_hash = '25fdcecc651a67a216496ab10d7566631e6ed10f721e37d62c7845dadb0ea267'
require(canonical(policy) == 'd1edd143d819f7dc93f24157978938dfc2f308b719b10c2f26580d9cf03d2c03', 'policy binding')
require(canonical(catalog) == bundle['content_hashes']['rtc_code_catalog'], 'catalog canonical hash')
require(canonical(read(FISCAL / bundle['production_controls']['path'])) == bundle['production_controls']['sha256'], 'control binding')
require(canonical(catalog['dps_xsd_fields']) == policy['fields_sha256'], 'facets binding')
with zipfile.ZipFile(FISCAL / 'sources/official' / catalog['dps_xsd']['source_file']) as archive:
    names = set(archive.namelist())
    for name, expected in policy['closure_members'].items():
        require(hashlib.sha256(archive.read(name)).hexdigest() == expected, 'closed schema bytes ' + name)
        root = ET.fromstring(archive.read(name))
        for node in root:
            if node.tag in ['{http://www.w3.org/2001/XMLSchema}include', '{http://www.w3.org/2001/XMLSchema}import']:
                require(node.get('schemaLocation') in names, 'local schema dependency ' + name)
for item in bundle['official_sources']:
    require(sha(FISCAL / 'sources/official' / item['file']) == item['sha256'], 'official source ' + item['id'])

native = read(args.evidence / 'native-v3/validation.json')
require(native['complete'] and native['exit_code'] == 0 and native['parse_errors'] == [], 'native terminal')
require(native['native_decisions'] == native['exact_replays'] == 12 and native['xml_files'] == 6, 'native population')
snapshots = {}
for item in native['snapshots']:
    path = args.evidence / 'native-v3' / item['file']
    require(sha(path) == item['sha256'], 'snapshot bytes ' + item['file'])
    require(item['replay_matches'] and item['declared_document_changes_only'], 'native replay/comparison ' + item['file'])
    snapshot = read(path)
    original = read(FISCAL / 'scenarios/dps-local-2026.10.14-74-draft1' / (item['case']+'-scenario.json'))
    require(canonical(snapshot['input']['facts']) == canonical(original['facts']), 'all original typed facts preserved ' + item['file'])
    snapshots[item['case'], item['runner'], item['version']] = snapshot
def assessment(snapshot, runner):
    if runner == 'rtc':
        return snapshot['result']
    return next(item['payload'] for item in snapshot['result']['trace'] if item['type'] == 'CONSUMPTION_REFORM')
mapping = {'IBSCBS.finNFSe': 'finNFSe', 'IBSCBS.indDest': 'IBSCBS.indDest', 'IBSCBS.cIndOp': 'IBSCBS.cIndOp',
    'IBSCBS.valores.trib.gIBSCBS.CST': 'IBSCBS.valores.trib.CST', 'IBSCBS.valores.trib.gIBSCBS.cClassTrib': 'IBSCBS.valores.trib.cClassTrib'}
for name in ['federal', 'education', 'presential']:
    for runner in ['rtc', 'integrated']:
        prior = assessment(snapshots[name, runner, '126'], runner)
        current = assessment(snapshots[name, runner, '127'], runner)
        expected = copy.deepcopy(prior['decision'])
        require(expected['document']['schema_version'] == 'NT004-RTC-1.01.03', 'old schema version ' + name)
        require(expected['document']['schema_source_hash'] == 'c32c30a2aac3aaf8eb71a8baf512954aac70867d883343d202d1c1e9b4453651', 'old layout hash ' + name)
        require(expected['document']['issuer_field_validation']['xsd_source_hash'] == '6c7e0510d3ecff4454f291f4e10b742d27a4818f23aab181494f96d0ea79f3dc', 'old XSD hash ' + name)
        expected['document']['schema_version'] = 'RESTRICTED-LOCAL-OBSERVED-2026.10.22-127'
        expected['document']['schema_source_hash'] = source_hash
        expected['document']['issuer_field_validation']['xsd_source_hash'] = source_hash
        expected['document']['issuer_fields'] = {mapping[k]: v for k, v in expected['document']['issuer_fields'].items()}
        expected['catalog_versions']['codes'] = '2026.10.22-127-draft1'
        require(canonical(expected) == canonical(current['decision']), 'independent exact decision comparison ' + name + runner)
        for key in ['status', 'reason_codes', 'missing_facts']:
            require(canonical({'value': prior[key]}) == canonical({'value': current[key]}), 'unchanged ' + key + name + runner)
N = '{http://www.sped.fazenda.gov.br/nfse}'
for item in native['xmls']:
    path = args.evidence / 'native-v3' / item['file']
    require(sha(path) == item['sha256'], 'XML receipt bytes')
    record = read(path)
    name = record['case']
    require(record['selection']['environment'] == '2' and record['selection']['remote_deployment_verified'] is False, 'local environment')
    require(record['validation']['status'] == 'VALID' and record['validation']['schema_sha256'] == source_hash, 'official XSD validation')
    require(record['first_xml_sha256'] == record['second_xml_sha256'] == record['validation']['xml_sha256'], 'deterministic XML bytes')
    decision = snapshots[name, 'integrated', '127']['result']['decision']
    fields = decision['dps_projection']['rtc']['issuer_supplied']
    for key in ['first_xml', 'second_xml']:
        xml_path = args.evidence / 'native-v3' / record[key+'_file']
        require(sha(xml_path) == record[key+'_sha256'], 'XML file bytes ' + key)
        xml = ET.fromstring(xml_path.read_bytes())
        info = xml.find(N+'infDPS')
        require(info.find(N+'finNFSe').text == fields['finNFSe'], 'purpose value at new root')
        require(info.find(N+'IBSCBS/'+N+'finNFSe') is None, 'no obsolete purpose path')
        trib = info.find(N+'IBSCBS/'+N+'valores/'+N+'trib')
        for tag in ['CST', 'cClassTrib']:
            require(trib.find(N+tag).text == fields['IBSCBS.valores.trib.'+tag], 'classification scalar ' + tag)
            require(trib.find(N+'gIBSCBS/'+N+tag) is None, 'no obsolete classification ' + tag)
        service = info.find(N+'serv/'+N+'cServ')
        nbs = decision['dps_projection']['service'].get('cNBS')
        if nbs is not None:
            require(service.find(N+'cNBS').text == nbs, 'NBS preserved')
            order = [node.tag for node in service]
            require(order.index(N+'cNBS') < order.index(N+'xDescServ'), 'observed service sequence')
        require(xml.find('.//{http://www.w3.org/2000/09/xmldsig#}Signature') is None, 'unsigned local output')

quality = {}
for label in ['final-directed-v1', 'phpstan-v2', 'pint-final-v1', 'production-assess-v1']:
    folder = args.evidence / label
    receipt = read(folder / 'execution.json')
    require(receipt['status'] == 'TERMINAL' and receipt['exit_code'] == 0, 'terminal gate ' + label)
    for channel in ['stdout', 'stderr']:
        require(sha(folder / (channel+'.log')) == receipt[channel+'_sha256'], 'gate log bytes')
    quality[label] = {'exit_code': 0, 'receipt_sha256': sha(folder / 'execution.json')}
log = (args.evidence / 'final-directed-v1/stdout.log').read_text(encoding='utf-8')
counts = re.search(r'305 passed \((\d+) assertions\)', log)
require(counts is not None, 'observed directed population')
assessment_report = read(args.evidence / 'production-assess-v1/stdout.log')
require(assessment_report['ready'] is False and assessment_report['eligible_for_local_validation'] is True, 'production gate separate')
require(assessment_report['official_source_hashes_valid'] == assessment_report['official_sources_checked'] == 106, 'full source inventory')
require(len(assessment_report['blockers']) == 129, 'production blockers preserved')
historical = read(args.evidence / 'historical-v1/validation.json')
require(historical['exit_code'] == 0 and historical['exact_replays'] == 12, 'historical122 sample')
prior126 = read(ROOT / 'storage/app/phase4b-catalog126-development-20261022/catalog126-scoped-validation.json')
require(sha(FISCAL / 'bundles/consumption-phase4b-2026.10.22-126-draft1.json') == prior126['bundle_sha256'], 'prior126 bundle preserved')
require(subprocess.check_output(['git', 'diff', '--name-only', '--', 'resources/fiscal'], cwd=ROOT, text=True).strip() == '', 'all tracked historical fiscal resources unchanged')
baseline122 = read(ROOT / 'storage/app/phase4b-credit122-development-20261021/credit122-development-validation.json')
runtime = set(baseline122['runtime_hashes']) | {'app/Modules/Fiscal/ConsumptionReform/CIndOpReference126.php',
    'app/Modules/Fiscal/ConsumptionReform/DpsLayoutPolicy127.php', 'app/Modules/Fiscal/NFSe/DpsRestrictedSchemaSelection127.php',
    'app/Modules/Fiscal/NFSe/DpsXmlProjector127.php', 'app/Console/Commands/ProjectDpsXmlCommand.php'}
report = {'created_at': datetime.now(timezone.utc).isoformat(), 'status': 'DPS127_SCOPED_CURRENT_XSD_PROJECTION_VALIDATED_NOT_PHASE4B_CLOSURE',
    'audit_checks': checks, 'tests': 305, 'assertions': int(counts[1]), 'full_suite_executed': False,
    'quality': quality, 'native_decisions': 12, 'current127_decisions': 6, 'comparison126_decisions': 6,
    'native_exact_replays': 12, 'unsigned_xsd_valid_xmls': 6, 'historical122_sample_exact_replays': 12,
    'official_sources_valid': 106, 'production_blockers': 129, 'ready': False, 'eligible_for_local_validation': True,
    'bundle': 'bundles/'+bundle_path.name, 'bundle_sha256': sha(bundle_path),
    'runtime_hashes': {path: sha(ROOT / path) for path in sorted(runtime)},
    'formal_phase4b_groups_closed': 0, 'phase4b_complete': False, 'evidence_root': str(args.evidence),
    'scope': 'OBSERVED_RESTRICTED_SCHEMA_REGULAR_DOMESTIC_FEDERAL_EDUCATION_PRESENTIAL_NOT_COMPLETE_DOCUMENTARY_SCOPE',
    'limits': ['No deployment,signature,remote authentication or transmission approval.',
               'No complete import/partial-export document or remaining special-profile acceptance.',
               'No new full regression;122 remains last full acceptance.']}
args.output.parent.mkdir(parents=True, exist_ok=True)
with args.output.open('x', encoding='utf-8', newline='\n') as handle:
    handle.write(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({key: report[key] for key in ['status', 'audit_checks', 'tests', 'assertions', 'unsigned_xsd_valid_xmls']}))
