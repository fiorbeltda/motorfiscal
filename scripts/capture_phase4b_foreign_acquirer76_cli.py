"""Exercise the own foreign-buyer CLI contract; all output remains a local structural candidate."""
from pathlib import Path
import copy
import hashlib
import json
import subprocess
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-foreign-acquirer76-cli-20261015'
F = ROOT / 'resources/fiscal'
M = 'bundles/consumption-phase4b-2026.10.15-76-draft1.json'
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def save(path, value):
    with path.open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)


def rel(path):
    return path.relative_to(ROOT).as_posix()


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def run(label, args, expected_exit=0):
    command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'artisan', *args]
    result = subprocess.run(command, cwd=ROOT, capture_output=True, encoding='utf-8')
    with (E / (label + '.stdout')).open('x', encoding='utf-8') as stream:
        stream.write(result.stdout)
    with (E / (label + '.stderr')).open('x', encoding='utf-8') as stream:
        stream.write(result.stderr)
    save(E / (label + '-execution.json'), {'command': command, 'exit_code': result.returncode,
        'stdout_sha256': sha(E / (label + '.stdout')), 'stderr_sha256': sha(E / (label + '.stderr'))})
    assert result.returncode == expected_exit, (label, result.stdout[-2500:], result.stderr[-1000:])
    return json.loads(result.stdout)


E.mkdir(exist_ok=False)
bundle = read(F / M)
gap = ROOT / 'storage/app/phase4b-international76-gap-20261015'
original = read(gap / 'foreign-buyer-domestic-consumption-input.json')
identity = read(gap / 'foreign-buyer-domestic-consumption-identity.json')
identity.update(issued_at=bundle['knowledge_available_at'], acquirer_name='Foreign & Company',
    foreign_acquirer_id='US-CUSTOMER-1', foreign_document_id='dps-1', foreign_trade_evidence_id='SYNTHETIC.COMEXT.76')
identity['foreign_acquirer'] = {'country': 'US', 'name': 'Foreign & Company', 'nif': 'NIF-76',
    'address': {'postal_code': '10001', 'city': 'New York', 'province': 'NY',
        'street': 'Main & Second', 'number': '10', 'district': 'Manhattan'},
    'party_evidence_id': 'SYNTHETIC.PARTY.76', 'identification_evidence_id': 'SYNTHETIC.ID.76',
    'address_evidence_id': 'SYNTHETIC.ADDRESS.76'}
identity['foreign_trade'] = {'mdPrestacao': '2', 'vincPrest': '0', 'tpMoeda': '790',
    'vServMoeda': '1000.00', 'mecAFComexP': '01', 'mecAFComexT': '01', 'movTempBens': '1', 'mdic': '0'}
cases = []
for name in ['nif', 'no-nif-1', 'no-nif-2']:
    scenario = copy.deepcopy(original)
    scenario.update(scenario_id='SYNTHETIC-FOREIGN-ACQUIRER76-' + name,
        rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'], knowledge_at=bundle['knowledge_available_at'])
    assert scenario['facts'] == original['facts']
    scenario_path, snapshot = E / (name + '-scenario.json'), E / (name + '-snapshot.json')
    save(scenario_path, scenario)
    decision = run(name + '-run', ['fiscal:scenario:run', rel(scenario_path), '--bundle-manifest=' + M, '--snapshot=' + rel(snapshot)])
    assert decision['status'] in ['VALID', 'VALID_WITH_WARNINGS']
    assert decision['decision']['iss']['calculation']['tax_amount'] == '20.00'
    assert decision['decision']['consumption_reform']['decision']['ibs']['nominal_amount'] == '1.00'
    assert decision['decision']['consumption_reform']['decision']['cbs']['nominal_amount'] == '9.00'
    replay = run(name + '-replay', ['fiscal:decision:replay', rel(snapshot)])
    assert replay['replay_matches'] and replay['result'] == decision
    document_identity = copy.deepcopy(identity)
    if name != 'nif':
        del document_identity['foreign_acquirer']['nif']
        document_identity['foreign_acquirer']['no_nif_reason'] = name[-1]
    identity_path = E / (name + '-identity.json')
    save(identity_path, document_identity)
    output = E / (name + '.xml')
    args = ['fiscal:dps:project-xml', rel(snapshot), rel(identity_path), '--bundle-manifest=' + M]
    projection = run(name + '-project', [*args, '--output=' + rel(output)])
    repeat = run(name + '-repeat', args)
    assert projection['status'] == 'VALID' and projection['schema_validation']['status'] == 'VALID'
    assert {k: v for k, v in projection.items() if k != 'output'} == {k: v for k, v in repeat.items() if k != 'output'}
    binding = projection['projection']['foreign_acquirer_binding']
    assert binding['production_ready'] is False and binding['currency_competence_validity_proven'] is False
    assert binding['document_obligation_complete'] is False and binding['comext_mode_fiscally_qualified'] is False
    assert sha(output) == projection['schema_validation']['xml_sha256']
    tree = ET.fromstring(output.read_bytes())
    ns = {'n': 'http://www.sped.fazenda.gov.br/nfse'}
    assert tree.find('n:infDPS/n:toma/n:CNPJ', ns) is None
    assert tree.find('n:infDPS/n:toma/n:end/n:endExt/n:cPais', ns).text == 'US'
    assert tree.find('n:infDPS/n:IBSCBS/n:dest/n:CNPJ', ns).text == '22222222000191'
    assert tree.find('n:infDPS/n:serv/n:comExt/n:tpMoeda', ns).text == '790'
    cases.append({'case': name, 'scenario': rel(scenario_path), 'snapshot': rel(snapshot),
        'snapshot_sha256': sha(snapshot), 'identity': rel(identity_path), 'xml': rel(output), 'xml_sha256': sha(output),
        'exact_replay': True, 'deterministic_projection': True})
    print('PASS:' + name + ' exact fiscal replay and complete local XSD candidate;business qualifications remain false', flush=True)
snapshot = E / 'nif-snapshot.json'
refusals = []
for name in ['production', 'party-conflict', 'future-mode', 'wrong-currency', 'pre-observation']:
    candidate = copy.deepcopy(identity)
    if name == 'production':
        candidate['environment'] = '1'
    elif name == 'party-conflict':
        candidate['foreign_acquirer_id'] = 'OTHER'
    elif name == 'future-mode':
        candidate['foreign_trade']['mdPrestacao'] = '5'
    elif name == 'wrong-currency':
        candidate['foreign_trade']['tpMoeda'] = '220'
    else:
        candidate['issued_at'] = '2026-10-14T15:00:00+00:00'
    identity_path = E / (name + '-identity.json')
    output = E / (name + '-forbidden.xml')
    save(identity_path, candidate)
    report = run(name + '-refusal', ['fiscal:dps:project-xml', rel(snapshot), rel(identity_path),
        '--bundle-manifest=' + M, '--output=' + rel(output)], 1)
    assert not output.exists() and report['status'] in ['INVALID', 'ACTION_REQUIRED']
    refusals.append({'case': name, 'reason': report['reason'], 'xml_not_written': True})
assessment = run('production-assessment', ['fiscal:rtc:production-assess', '--bundle-manifest=' + M,
    '--controls=resources/fiscal/' + bundle['production_controls']['path'], '--target=local-validation'])
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 53
assert 'RTC_DPS_RESTRICTED_SCHEMA_LOCAL_ONLY' in assessment['blockers']
save(E / 'foreign-acquirer-cli-validation.json', {'status': 'FOREIGN_ACQUIRER76_LOCAL_CLI_CANDIDATES_VERIFIED',
    'bundle': M, 'bundle_sha256': sha(F / M), 'cases': cases, 'refusals': refusals,
    'official_sources_valid': 53, 'production_blockers': len(assessment['blockers']),
    'full_suite_complete': False, 'foreign_identity_authenticity_verified': False,
    'document_obligation_complete': False, 'mixed_export_pair_complete': False,
    'nt009_migration_complete': False, 'phase4b_complete': False, 'production_ready': False})
print('PASS:three local international candidates,five refusals,53 sources;production blocked')
