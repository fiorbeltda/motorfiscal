"""Audit134 integration, source identity, XML input fragments and complete regression after terminal success."""
import copy
import hashlib
import json
import os
import re
import zipfile
from datetime import datetime, timezone
from decimal import Decimal, ROUND_HALF_UP
from pathlib import Path
from xml.etree import ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
E = Path('D:/ProjetoSaasExports/phase4b-iss134-20261022-v1')
OUT = ROOT / 'storage/app/phase4b-iss134-development-20261022'
checks = 0


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def sha(path):
    with path.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()


def canonical(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()


def check(ok, label):
    global checks
    if not ok:
        raise AssertionError(label)
    checks += 1


def save(path, value):
    with path.open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)
        stream.flush()
        os.fsync(stream.fileno())


suite = read(E / 'full-suite-v1/validation.json')
check(suite['complete'] and suite['exit_code'] == 0 and suite['tests'] > 9061, 'complete unfiltered suite terminal success')
check(not suite['changed_inputs'] and not suite['added_inputs'], 'frozen runtime/test/fiscal input population unchanged')
check(sha(E / 'full-suite-v1/stdout.log') == suite['stdout_sha256'], 'full raw log')
check(read(E / 'full-suite-v1/input-hashes-before.json') == read(E / 'full-suite-v1/input-hashes-after.json'), 'full frozen inventory equal')
prior_path = ROOT / 'storage/app/phase4b-iss133-development-20261022/iss133-scoped-validation.json'
check(sha(prior_path) == '748c10c778f20ab857717c2ed6ae9cb7075ed9f86f0a7e4b8a359ad1eeca686b', 'prior receipt preserved')
prior = read(prior_path)
changed = {name for name, digest in prior['runtime_hashes'].items() if sha(ROOT / name) != digest}
expected_changes = {'app/Modules/Fiscal/ConsumptionReform/' + name + '.php' for name in
                    ['ConsumptionReformEngine', 'CreditInputPolicy122', 'CIndOpReference126', 'DpsLayoutPolicy127',
                     'DpsForeignPolicy128', 'ImportResponsibility129', 'DocumentSchedule131']}
check(changed == expected_changes, 'only declared inherited version guards and engine integration changed')
bundle_path = ROOT / 'resources/fiscal/bundles/consumption-phase4b-2026.10.22-134-draft1.json'
bundle = read(bundle_path)
old_bundle = read(ROOT / 'resources/fiscal' / prior['fiscal_bundle_unchanged'])
check(bundle['predecessor_bundle_id'] == old_bundle['bundle_id'], 'immutable predecessor selected')
check(bundle['official_sources'][:107] == old_bundle['official_sources'] and len(bundle['official_sources']) == 112, 'five source observations appended without rewriting old107')
for source in bundle['official_sources']:
    check(sha(ROOT / 'resources/fiscal/sources/official' / source['file']) == source['sha256'], 'official source ' + source['id'])
for key in ['fact_definitions', 'questions']:
    old = read(ROOT / 'resources/fiscal' / old_bundle['resource_paths'][key])
    new = read(ROOT / 'resources/fiscal' / bundle['resource_paths'][key])
    check(new[:-1] == old and new[-1].get('key', new[-1].get('fact_key')) == 'rtc.export_iss_tax_context_proof_records', 'append-only typed proof ' + key)
    check(canonical(new) == bundle['content_hashes'][key], 'bound resource ' + key)
codes = read(ROOT / 'resources/fiscal' / bundle['resource_paths']['rtc_code_catalog'])
old_codes = read(ROOT / 'resources/fiscal' / old_bundle['resource_paths']['rtc_code_catalog'])
expected = copy.deepcopy(old_codes)
expected['version'] = bundle['version']
expected['document_iss_policy134'] = codes['document_iss_policy134']
check(codes == expected and canonical(codes) == bundle['content_hashes']['rtc_code_catalog'], 'exact declared catalog extension')
for folder in ['directed-v3', 'phpstan-v3', 'pint-v1', 'production-assess-v1']:
    record = read(E / folder / 'execution.json')
    check(record['status'] == 'TERMINAL' and record['exit_code'] == 0, 'terminal ' + folder)
    for channel in ['stdout', 'stderr']:
        check(sha(E / folder / (channel + '.log')) == record[channel + '_sha256'], 'raw log ' + folder + channel)
plain = re.sub(r'\x1b\[[0-?]*[ -/]*[@-~]', '', (E / 'directed-v3/stdout.log').read_text(encoding='utf-8'))
check('355 passed' in plain and '2755 assertions' in plain, 'directed count')
readiness = read(E / 'production-assess-v1/stdout.log')
check(not readiness['ready'] and readiness['eligible_for_local_validation'] and readiness['official_source_hashes_valid'] == 112, 'readiness remains local only')
native = read(E / 'native-v1/validation.json')
check(native['complete'] and native['native_decisions'] == native['exact_replays'] == 22 and native['xml_files'] == 16, 'complete native population')
snapshots = {}
for item in native['snapshots']:
    path = E / 'native-v1' / item['file']
    check(sha(path) == item['sha256'] and item['replay_matches'], 'saved native snapshot')
    snapshots[item['case'], item['runner'], item['version']] = read(path)


def rtc(s, runner):
    return s['result'] if runner == 'rtc' else next(t['payload'] for t in s['result']['trace'] if t['type'] == 'CONSUMPTION_REFORM')


for case in ['ordinary', 'discount', 'zero', 'reverse', 'fx']:
    for runner in ['rtc', 'integrated']:
        old = snapshots[case, runner, '131']
        new = snapshots[case, runner, '134']
        raw = copy.deepcopy(new['input']['facts'])
        raw.pop('rtc.export_iss_tax_context_proof_records')
        check(raw == old['input']['facts'], 'all financial facts unchanged except new proof carrier')
        a, b = rtc(old, runner), rtc(new, runner)
        excluded = {'DOCUMENT_ISS_POLICY134', 'PARTIAL_EXPORT_DOCUMENT_ISS134', 'PARTIAL_EXPORT_DOCUMENT_PRICE_CONTEXT',
                    'PARTIAL_EXPORT_CONTRACT_PRICES', 'PARTIAL_EXPORT_ISS_PRICE_COMPOSITION'}
        check([t for t in a['trace'] if t['stage'] not in excluded] == [t for t in b['trace'] if t['stage'] not in excluded], 'all other fiscal trace unchanged')
        if runner == 'integrated':
            old_iss = next(t['payload'] for t in old['result']['trace'] if t['type'] == 'ISS_CALCULATION')
            new_iss = next(t['payload'] for t in new['result']['trace'] if t['type'] == 'ISS_CALCULATION')
            check(old_iss == new_iss, 'entire operation-level ISS unchanged')
        if case != 'fx':
            check(a['status'] == b['status'] and a['reason_codes'] == b['reason_codes'], 'same fiscal nonfinality')
for runner in ['rtc', 'integrated']:
    r = snapshots['missing', runner, '134']['result']
    check('rtc.export_iss_tax_context_proof_records' in [q['fact_key'] for q in r['questions']], 'missing proof question delivered')
tag = '{http://www.sped.fazenda.gov.br/nfse}'
for item in native['xmls']:
    path = E / 'native-v1' / item['file']
    check(sha(path) == item['sha256'], 'saved projection receipt')
    record = read(path)
    for key in ['first_xml', 'second_xml']:
        check(sha(E / 'native-v1' / record[key + '_file']) == record[key + '_sha256'], 'saved fragment hash')
    check(record['first_xml_sha256'] == record['second_xml_sha256'], 'deterministic fragment bytes')
    check(record['validation']['status'] == 'VALID' and not record['validation']['full_document_validated'], 'official complex type only')
    role = record['case'].split('-')[-1]
    values = next(x for x in record['report']['projection']['fragments'] if x['role'] == role)
    root = ET.fromstring((E / 'native-v1' / record['first_xml_file']).read_bytes())
    gross = root.find('./' + tag + 'vServPrest/' + tag + 'vServ').text
    discount = root.find('./' + tag + 'vDescCondIncond/' + tag + 'vDescIncond').text
    if values['iss_base_brl'] is not None:
        base = Decimal(gross) - Decimal(discount)
        tax = (base * Decimal('0.029')).quantize(Decimal('0.01'), rounding=ROUND_HALF_UP)
        check(format(base, '.2f') == values['iss_base_brl'] and format(tax, '.2f') == values['iss_tax_brl'], 'XML values independently reconcile to per-document base/tax')
    else:
        check(values['iss_tax_brl'] is None and root.find('.//' + tag + 'tribISSQN').text == '3', 'non-incidence does not become zero')
    check(not any(node.tag in [tag + 'vBC', tag + 'vISSQN', tag + 'cTribMun'] for node in root.iter()), 'no invented calculated fields or truncated code')
for folder, count in [('historical131-v1', 18), ('historical132-v1', 1)]:
    history = read(E / folder / 'validation.json')
    check(history['exit_code'] == 0 and history['exact_replays'] == count, 'preserved historical replay')
matrix_path = ROOT / 'storage/app/phase4b-iss133-development-20261022/requirements80-current-matrix.json'
baseline = read(matrix_path)
matrix = copy.deepcopy(baseline)
for old, item in zip(baseline['requirements'], matrix['requirements'], strict=True):
    if item['id'] == '4B.7.1':
        item['remaining_before_scoped134'] = item['current_remaining_gap']
        item['current_remaining_gap'] = '134 integra cálculoISS133 aos dois runners e projeta o grupo valores por documento no tipo oficialDPS. Concluir o parDPS completo e classificaçãoRTC da parcela estrangeira,mapeamento municipal de3 dígitos quando exigido,câmbioISS,corpo documental/fatura/câmbio da importação e demais perfis/ajustes. Fragmentos não encerram obrigação.'
    else:
        check(item == old, 'unaffected requirement preserved')
    check(item['own_review'] == old['own_review'] and not item['formal_completion_approved'], 'own review unchanged without closure')
check(len(matrix['requirements']) == 80 and matrix['formal_phase4b_groups_closed'] == 0, 'original80 scope retained')
runtime = set(prior['runtime_hashes']) | {'app/Modules/Fiscal/ConsumptionReform/DocumentIssIntegration134.php',
    'app/Modules/Fiscal/NFSe/DpsDocumentValues134.php', 'app/Console/Commands/ProjectDpsDocumentValues134Command.php',
    'app/Modules/Fiscal/Infrastructure/Schema/DpsXmlSchemaValidator.php'}
report = {'created_at': datetime.now(timezone.utc).isoformat(), 'status': 'SCOPED134_INTEGRATED_ISS_VALUES_VALIDATED_NOT_PHASE_CLOSURE',
          'audit_checks': checks, 'directed_tests': 355, 'directed_assertions': 2755,
          'full_suite_tests': suite['tests'], 'full_suite_assertions': suite['assertions'], 'full_suite_executed': True,
          'full_suite_frozen_inputs': suite['input_files'], 'quality': {'phpstan': 'PASS_512M', 'pint': 'PASS'},
          'native_decisions': 22, 'native_exact_replays': 22, 'values_fragment_files': 16, 'complete_dps_documents': 0,
          'historical_sample_exact_replays': 19, 'official_sources_valid': 112, 'production_blockers': len(readiness['blockers']),
          'ready': False, 'eligible_for_local_validation': True, 'bundle': 'bundles/' + bundle_path.name,
          'bundle_sha256': sha(bundle_path), 'runtime_hashes': {name: sha(ROOT / name) for name in sorted(runtime)},
          'formal_phase4b_groups_closed': 0, 'phase4b_complete': False, 'document_obligation_complete': False, 'evidence_root': str(E)}
OUT.mkdir(exist_ok=True)
receipt_path = OUT / 'iss134-scoped-validation.json'
save(receipt_path, report)
evidence = {'package': '134', 'path': str(receipt_path.relative_to(ROOT)).replace('\\', '/'), 'sha256': sha(receipt_path), 'status': report['status']}
for item in matrix['requirements']:
    if item['id'] == '4B.7.1':
        item.setdefault('subsequent_scoped_evidence', []).append(evidence)
matrix.setdefault('subsequent_scoped_evidence', []).append(evidence)
matrix.update(created_at=datetime.now(timezone.utc).isoformat(), status='ORIGINAL80_WITH_SCOPED134_INTEGRATED_ISS_VALUES_NOT_CLOSURE',
              baseline_matrix={'path': str(matrix_path), 'sha256': sha(matrix_path)}, requirements_with_scoped134_updates=['4B.7.1'],
              scope_note='80 revisões próprias preservadas;integraçãoISS e valoresDPS134.Par completo e outros requisitos pendentes,0/8 grupos encerrados.')
save(OUT / 'requirements80-current-matrix.json', matrix)
print(json.dumps({'status': report['status'], 'audit_checks': checks, 'runtime_hashes': len(runtime),
                  'full_suite_tests': suite['tests'], 'receipt_sha256': sha(receipt_path)}))
