"""Audit documentary reconciliation independently; preserve prior fiscal acceptance and matrix reviews."""
import copy
import hashlib
import json
import os
import re
from datetime import datetime, timezone
from pathlib import Path
from xml.etree import ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
EVIDENCE = Path('D:/ProjetoSaasExports/phase4b-values132-20261022-v1')
OUTPUT = ROOT / 'storage/app/phase4b-values132-development-20261022'
checks = 0


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def require(condition, label):
    global checks
    if not condition:
        raise AssertionError(label)
    checks += 1


def write(path, value):
    with path.open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)
        stream.flush()
        os.fsync(stream.fileno())


prior_path = ROOT / 'storage/app/phase4b-schedule131-development-20261022/schedule131-draft2-scoped-validation.json'
require(sha(prior_path) == '83b8afac8e2c590ffb5986a988ba16c8eb5a9d464fbe750064317397b31b9348', 'prior receipt preserved')
prior = read(prior_path)
changed = {p for p, digest in prior['runtime_hashes'].items() if sha(ROOT / p) != digest}
require(changed == {'app/Console/Commands/ProjectImportDpsCandidate130Command.php'}, 'only declared inherited command changed')
runtime = set(prior['runtime_hashes']) | {'app/Modules/Fiscal/NFSe/ImportDocumentValues132.php'}
for bundle_name in [prior['bundle'], 'bundles/consumption-phase4b-2026.10.22-129-draft1.json']:
    bundle = read(ROOT / 'resources/fiscal' / bundle_name)
    for source in bundle['official_sources']:
        require(sha(ROOT / 'resources/fiscal/sources/official' / source['file']) == source['sha256'], 'official bytes ' + source['id'])
require(sha(ROOT / 'resources/fiscal' / prior['bundle']) == prior['bundle_sha256'], 'active fiscal bundle unchanged')
for folder in ['directed-v3', 'phpstan-v2', 'pint-v2']:
    execution = read(EVIDENCE / folder / 'execution.json')
    require(execution['status'] == 'TERMINAL' and execution['exit_code'] == 0, 'successful terminal ' + folder)
    for channel in ['stdout', 'stderr']:
        require(sha(EVIDENCE / folder / (channel + '.log')) == execution[channel + '_sha256'], 'raw log ' + folder + channel)
log = re.sub(r'\x1b\[[0-?]*[ -/]*[@-~]', '', (EVIDENCE / 'directed-v3/stdout.log').read_text(encoding='utf-8'))
require('57 passed' in log and '206 assertions' in log, 'observed directed counts')
native = read(EVIDENCE / 'native-v2/validation.json')
require(native['complete'] and native['native_decisions'] == native['exact_replays'] == 1 and native['xml_files'] == 2, 'native population')
for item in native['snapshots'] + native['xmls']:
    require(sha(EVIDENCE / 'native-v2' / item['file']) == item['sha256'], 'native artifact hash')
snapshot = read(EVIDENCE / 'native-v2' / native['snapshots'][0]['file'])
projection = read(EVIDENCE / 'native-v2' / native['xmls'][0]['file'])
values = projection['report']['document_values132']
require(hashlib.sha256(projection['invoice_source_bytes'].encode()).hexdigest() == values['source_sha256'], 'saved original source bytes')
require(values['declared_document'] == projection['values_document'], 'declared document retained literally')
require(not values['source_fields_extracted'] and not values['source_authenticity_verified'] and not values['iss_legal_base_qualified'], 'bounded reconciliation claims')
iss = next(t['payload'] for t in snapshot['result']['trace'] if t['type'] == 'ISS_CALCULATION')
require(values['iss_calculation'] == iss, 'entire ISS calculation unchanged')
require(set(values['compared_amounts'].values()) == {'1000.00'}, 'independent positive monetary oracle')
require(snapshot['result']['status'] == 'ACTION_REQUIRED' and not projection['report']['document_obligation_complete'], 'fiscal nonfinality retained')
first = EVIDENCE / 'native-v2' / projection['first_xml_file']
second = EVIDENCE / 'native-v2' / projection['second_xml_file']
require(sha(first) == sha(second) == projection['first_xml_sha256'] == projection['second_xml_sha256'], 'deterministic XML bytes')
old_projection = read(Path('D:/ProjetoSaasExports/phase4b-import130-20261022-v1/native-v1/ordinary-projection.json'))
require(sha(first) == old_projection['first_xml_sha256'], 'prior candidate XML remains identical')
tag = '{http://www.sped.fazenda.gov.br/nfse}'
root = ET.fromstring(first.read_bytes())
require(root.find('.//' + tag + 'vServ').text == '1000.00', 'XML service amount oracle')
require(root.find('.//' + tag + 'vServMoeda').text == '1000.00', 'XML original price oracle')
for folder, count in [('historical130-v1', 1), ('historical131-v1', 18)]:
    history = read(EVIDENCE / folder / 'validation.json')
    require(history['exit_code'] == 0 and history['checked_original_hashes'] == history['exact_replays'] == count, 'historical integrity and exact replay')
OUTPUT.mkdir(exist_ok=True)
receipt = {'created_at': datetime.now(timezone.utc).isoformat(), 'status': 'SCOPED132_VALUES_RECONCILED_NOT_PHASE_CLOSURE',
           'audit_checks': checks, 'tests': 57, 'assertions': 206, 'quality': {'phpstan': 'PASS', 'pint': 'PASS'},
           'full_suite_executed': False, 'native_decisions': 1, 'native_exact_replays': 1, 'xml_files': 2,
           'historical_sample_exact_replays': 19, 'fiscal_bundle_unchanged': prior['bundle'],
           'source_fields_extracted': False, 'source_authenticity_verified': False, 'iss_legal_base_qualified': False,
           'formal_phase4b_groups_closed': 0, 'phase4b_complete': False, 'document_obligation_complete': False,
           'production_readiness_reassessed': False, 'production_ready': False,
           'runtime_hashes': {p: sha(ROOT / p) for p in sorted(runtime)}, 'evidence_root': str(EVIDENCE)}
receipt_path = OUTPUT / 'values132-scoped-validation.json'
write(receipt_path, receipt)
matrix_path = ROOT / 'storage/app/phase4b-schedule131-development-20261022/requirements80-current-matrix.json'
require(sha(matrix_path) == 'a2767c5e548a39d53f3baa247c7c6efb59f3cef086184f09a837e3f031867ece', 'prior matrix preserved')
baseline = read(matrix_path)
matrix = copy.deepcopy(baseline)
evidence = {'package': '132', 'path': str(receipt_path.relative_to(ROOT)).replace('\\', '/'), 'sha256': sha(receipt_path), 'status': receipt['status']}
for old, item in zip(baseline['requirements'], matrix['requirements'], strict=True):
    if item['id'] == '4B.7.1':
        item['remaining_before_scoped132'] = item['current_remaining_gap']
        item['current_remaining_gap'] = '132 acrescenta reconciliação declarada de preçoBRL por fatura/linha,hash dos bytes e resultadoISS reexecutado;sem extrair campos da fonte,qualificar base legal,câmbio ou encerrar obrigação. ' + item['current_remaining_gap']
        item.setdefault('subsequent_scoped_evidence', []).append(evidence)
    else:
        require(old == item, 'unaffected requirement preserved')
    require(item['own_review'] == old['own_review'] and not item['formal_completion_approved'], 'own review and incomplete state preserved')
require(len(matrix['requirements']) == 80 and matrix['formal_phase4b_groups_closed'] == 0, 'original population and closure unchanged')
matrix.setdefault('subsequent_scoped_evidence', []).append(evidence)
matrix.update(created_at=datetime.now(timezone.utc).isoformat(), status='ORIGINAL80_WITH_SCOPED132_DECLARED_VALUES_NOT_CLOSURE',
              baseline_matrix={'path': str(matrix_path), 'sha256': sha(matrix_path)}, requirements_with_scoped132_updates=['4B.7.1'],
              scope_note='80 revisões preservadas;uma pendência atualizada com reconciliação132.0/8 grupos encerrados;produção bloqueada.')
write(OUTPUT / 'requirements80-current-matrix.json', matrix)
print(json.dumps({'status': receipt['status'], 'tests': 57, 'assertions': 206, 'audit_checks': receipt['audit_checks'], 'runtime_hashes': len(runtime), 'receipt_sha256': sha(receipt_path)}))
