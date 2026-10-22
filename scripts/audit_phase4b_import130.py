"""Independently verify candidate130 XML/amounts, nonfinal gates and preserved129 resources."""
import argparse
import hashlib
import json
import os
import re
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
checks = 0

def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def canonical(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()

def require(condition, label):
    global checks
    if not condition:
        raise AssertionError(label)
    checks += 1

base = read(ROOT / 'storage/app/phase4b-import129-development-20261022/import129-scoped-validation.json')
bundle_path = F / base['bundle']
require(sha(bundle_path) == base['bundle_sha256'], 'fiscal129 manifest unchanged')
bundle = read(bundle_path)
for key, path in bundle['resource_paths'].items():
    if key in bundle['content_hashes'] and (F / path).is_file():
        require(canonical(read(F / path)) == bundle['content_hashes'][key], 'unchanged fiscal resource '+key)
for source in bundle['official_sources']:
    require(sha(F / 'sources/official' / source['file']) == source['sha256'], 'prior official bytes '+source['id'])
policy_path = F / 'projections/import-dps-candidate-2026.10.22-130.json'
policy = read(policy_path)
require(policy['contract'] == 'DPS-IMPORT-CANDIDATE-2026.10.22-130', 'candidate contract')
require(policy['available_at'] == '2026-10-22T17:33:37.832938+00:00', 'accepted observation time')
capture = read(args.evidence / 'primary-text-v2/capture.json')
require(len(capture['records']) == len(policy['source_observations']) == 3, 'three accepted captures')
for source, record in zip(policy['source_observations'], capture['records'], strict=True):
    require(source['url'] == record['url'] and source['observed_at'] == record['observed_at'], 'literal provenance')
    require(source['sha256'] == record['sha256'] == sha(args.evidence / 'primary-text-v2' / record['file']), 'capture raw bytes')
    require(sha(F / 'sources/official' / source['file']) == source['sha256'], 'immutable source copy')
integrity = read(args.evidence / 'source-storage-integrity-observation.json')
require(integrity['v1_rejected_not_overwritten'] and all(x['v2_verified'] for x in integrity['v1_sources']), 'failed capture preserved and superseded')
native = read(args.evidence / 'native-v1/validation.json')
require(native['complete'] and native['exit_code'] == 0 and native['native_decisions'] == native['exact_replays'] == 1 and native['xml_files'] == 2, 'native terminal population')
item = native['snapshots'][0]
require(sha(args.evidence / 'native-v1' / item['file']) == item['sha256'] and item['fiscal_snapshot_unchanged'], 'snapshot hash unchanged')
snapshot = read(args.evidence / 'native-v1' / item['file'])
require(snapshot['result']['status'] == 'ACTION_REQUIRED' and snapshot['result']['reason_codes'] == ['RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN'], 'nonfinal fiscal assessment preserved')
require(snapshot['result']['decision'] == [], 'no invented final fiscal decision')
record_item = native['xmls'][0]
require(sha(args.evidence / 'native-v1' / record_item['file']) == record_item['sha256'], 'candidate report hash')
record = read(args.evidence / 'native-v1' / record_item['file'])
require(record['report']['status'] == 'CANDIDATE_VALIDATED' and record['validation']['status'] == 'VALID', 'local candidate validated')
require(record['report']['fiscal_decision_final'] is False and record['report']['document_obligation_complete'] is False and record['report']['production_ready'] is False, 'no approval promotion')
require(record['report']['source_policy_sha256'] == canonical(policy), 'policy binding')
require(record['first_xml_sha256'] == record['second_xml_sha256'] == record['validation']['xml_sha256'], 'deterministic candidate bytes')
require(record['validation']['schema_sha256'] == policy['schema_sha256'] == '25fdcecc651a67a216496ab10d7566631e6ed10f721e37d62c7845dadb0ea267', 'actual source schema')
N = {'d': 'http://www.sped.fazenda.gov.br/nfse'}
facts = snapshot['input']['facts']
projection = record['report']['projection']
for name in ['first_xml', 'second_xml']:
    path = args.evidence / 'native-v1' / record[name+'_file']
    require(sha(path) == record[name+'_sha256'], 'saved XML hash')
    root = ET.fromstring(path.read_bytes())
    info = root.find('d:infDPS', N)
    require(info is not None and root.get('versao') == '1.01', 'DPS root')
    expected = {
        'tpEmit': '2', 'cMotivoEmisTI': '1', 'finNFSe': '0', 'cLocEmi': '3550308',
        'prest/NIF': facts['rtc.supplier_foreign_nif'], 'prest/end/endExt/cPais': facts['rtc.supplier_country'],
        'prest/regTrib/opSimpNac': '1', 'prest/regTrib/regEspTrib': '0',
        'toma/CNPJ': facts['rtc.importer_id'], 'serv/cServ/cNBS': facts['rtc.service_nbs_code'].replace('.', ''),
        'valores/vServPrest/vServ': projection['iss_calculation']['gross_amount'],
        'valores/trib/tribMun/tpRetISSQN': '2', 'serv/comExt/vServMoeda': facts['rtc.gross_amount'],
        'serv/comExt/tpMoeda': '790',
    }
    for xpath, text in expected.items():
        require(info.findtext('/'.join('d:'+x for x in xpath.split('/')), namespaces=N) == text, 'literal node '+xpath)
    require(info.find('d:prest/d:CNPJ', N) is None, 'no fake foreign supplier CNPJ')
    require(info.get('Id') == 'DPS35503082'+facts['rtc.importer_id']+'00001'+'000000000000130', 'DPS ID uses actual issuer')
    require(root.find('.//{http://www.w3.org/2000/09/xmldsig#}Signature') is None, 'unsigned local candidate')
require(projection['iss_calculation']['tax_amount'] == '20.00' and projection['rtc_nominal_calculation']['ibs_state'] == '1.00' and projection['rtc_nominal_calculation']['cbs'] == '9.00', 'computed amounts remain literal')
require(projection['documentary_evidence']['foreign_invoice_id'] == facts['rtc.import_document_id'] and projection['documentary_evidence']['transaction_evidence_id'] == facts['rtc.import_transaction_evidence_id'], 'document references bound')
for folder in ['directed-final-v2', 'phpstan-v1', 'pint-v1', 'production-assess129-v1']:
    receipt = read(args.evidence / folder / 'execution.json')
    require(receipt['status'] == 'TERMINAL' and receipt['exit_code'] == 0, 'successful terminal '+folder)
    for channel in ['stdout', 'stderr']:
        require(sha(args.evidence / folder / (channel+'.log')) == receipt[channel+'_sha256'], 'raw terminal log '+folder+channel)
log = re.sub(r'\x1b\[[0-?]*[ -/]*[@-~]', '', (args.evidence / 'directed-final-v2/stdout.log').read_text(encoding='utf-8'))
require(re.search(r'99 passed\s*\(333 assertions\)', log) is not None, 'observed test counts')
history = read(args.evidence / 'historical128-v1/validation.json')
require(history['exit_code'] == 0 and history['exact_replays'] == history['checked_original_hashes'] == 24, 'historical replay')
assessment = read(args.evidence / 'production-assess129-v1/stdout.log')
require(assessment['ready'] is False and assessment['eligible_for_local_validation'] is True and assessment['official_source_hashes_valid'] == 107, 'current129 readiness')
changed = [p for p,h in base['runtime_hashes'].items() if sha(ROOT / p) != h]
require(changed == ['app/Modules/Fiscal/NFSe/DpsForeignPartyProjector.php'], 'only declared inherited file changed')
runtime = set(base['runtime_hashes']) | {
    'app/Modules/Fiscal/NFSe/DpsImportCandidate130.php', 'app/Modules/Fiscal/NFSe/DpsImportCandidatePolicy130.php',
    'app/Console/Commands/ProjectImportDpsCandidate130Command.php'}
report = {'created_at': datetime.now(timezone.utc).isoformat(), 'status': 'SCOPED130_CANDIDATE_VALIDATED_NOT_IMPORT_OBLIGATION_CLOSURE',
    'audit_checks': checks, 'tests': 99, 'assertions': 333, 'quality': {'phpstan': 'PASS', 'pint': 'PASS'},
    'full_suite_executed': False, 'native_decisions': 1, 'native_exact_replays': 1, 'unsigned_xsd_valid_candidate_xmls': 2,
    'historical_sample_exact_replays': 24, 'bundle_official_sources_valid': 107, 'candidate_source_files_valid': 3,
    'production_blockers': len(assessment['blockers']), 'ready': False, 'eligible_for_local_validation': True,
    'fiscal_bundle': base['bundle'], 'fiscal_bundle_sha256': base['bundle_sha256'],
    'projection_policy': str(policy_path.relative_to(F)).replace('\\','/'), 'projection_policy_sha256': sha(policy_path),
    'runtime_hashes': {p: sha(ROOT / p) for p in sorted(runtime)},
    'formal_phase4b_groups_closed': 0, 'phase4b_complete': False, 'fiscal_decision_final': False,
    'document_obligation_complete': False, 'evidence_root': str(args.evidence), 'unresolved': policy['unresolved']}
args.output.parent.mkdir(parents=True, exist_ok=True)
with args.output.open('x', encoding='utf-8', newline='\n') as handle:
    handle.write(json.dumps(report, ensure_ascii=False, indent=2)+'\n');handle.flush();os.fsync(handle.fileno())
print(json.dumps({k: report[k] for k in ['status','audit_checks','tests','assertions','unsigned_xsd_valid_candidate_xmls']}))
