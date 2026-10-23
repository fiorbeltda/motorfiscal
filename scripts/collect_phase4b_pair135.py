"""Run the realCLI twice, retrieve exclusive artifacts and exercise two pre-write refusals."""
import argparse
import hashlib
import json
import subprocess
from pathlib import Path
from xml.etree import ElementTree as ET

parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
args.output.mkdir(parents=True, exist_ok=False)
root = Path(__file__).resolve().parents[1]
docker = [r'D:\Docker\DockerDesktop\resources\bin\docker.exe', 'compose']


def call(arguments, **kwargs):
    return subprocess.run(docker + arguments, cwd=root, capture_output=True, check=False, **kwargs)


generated = call(['exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'scripts/capture_phase4b_pair135_inputs.php'])
(args.output / 'input-bundle.json').write_bytes(generated.stdout)
(args.output / 'capture.stderr.log').write_bytes(generated.stderr)
assert generated.returncode == 0, generated.stderr.decode(errors='replace')
bundle = json.loads(generated.stdout)
assert bundle['synthetic_only']
container = '/tmp/phase4b-pair135-' + hashlib.sha256(str(args.output.resolve()).encode()).hexdigest()[:12]
created = call(['exec', '-T', 'app', 'php', '-r', 'if(file_exists($argv[1]))exit(2);exit(mkdir($argv[1],0700)?0:1);', container])
assert created.returncode == 0, 'Exclusive container evidence directory already exists or unavailable.'
files = {'snapshot.json': bundle['positive']['snapshot'], 'declarations.json': bundle['positive']['declarations'],
         'invalid-identity.json': bundle['invalid_identity_declarations'],
         'former-snapshot.json': bundle['former_contract']['snapshot'], 'former-declarations.json': bundle['former_contract']['declarations']}
input_hashes = {}
for name, value in files.items():
    path = args.output / name
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    input_hashes[name] = hashlib.sha256(path.read_bytes()).hexdigest()
    copied = call(['cp', str(path.resolve()), 'app:' + container + '/' + name])
    assert copied.returncode == 0, copied.stderr.decode(errors='replace')
    verified = call(['exec', '-T', 'app', 'php', '-r', 'echo hash_file("sha256",$argv[1]);', container + '/' + name])
    assert verified.returncode == 0 and verified.stdout.decode() == input_hashes[name]
reports = []
for label in ['first', 'second']:
    result = call(['exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'artisan', 'fiscal:dps:project-export-pair',
                   container + '/snapshot.json', container + '/declarations.json', '--output-dir=' + container + '/' + label])
    (args.output / (label + '.stdout.json')).write_bytes(result.stdout)
    (args.output / (label + '.stderr.log')).write_bytes(result.stderr)
    assert result.returncode == 0, result.stdout.decode(errors='replace')
    report = json.loads(result.stdout)
    assert report['status'] == 'LOCAL_PARTIAL_EXPORT_PAIR_DPS_XSD_VALIDATED'
    assert report['snapshot_hash'] == bundle['source_snapshot_canonical_hash'] and report['snapshot_replay_matches']
    assert report['base_official_sources_valid'] == 112 and report['additional_sources_valid'] == 8
    assert report['complete_dps_built'] and not report['complete_fiscal_pair_qualified'] and not report['production_ready']
    assert not report['own_pis_cofins_projected_in_xml']
    copied = call(['cp', 'app:' + container + '/' + label, str((args.output / label).resolve())])
    assert copied.returncode == 0, copied.stderr.decode(errors='replace')
    saved = json.loads((args.output / label / 'report.json').read_text(encoding='utf-8'))
    assert saved == report
    for record in report['records']:
        path = args.output / label / (record['role'] + '.xml')
        assert hashlib.sha256(path.read_bytes()).hexdigest() == record['xml_sha256'] and record['validation']['status'] == 'VALID'
        node = ET.fromstring(path.read_bytes())
        ns = {'d': 'http://www.sped.fazenda.gov.br/nfse'}
        foreign = record['role'] == 'RTC_FOREIGN'
        assert node.findtext('./d:infDPS/d:nDPS', namespaces=ns) == ('1353' if foreign else '1354')
        assert node.findtext('./d:infDPS/d:valores/d:vServPrest/d:vServ', namespaces=ns) == ('400.00' if foreign else '641.33')
        assert node.findtext('./d:infDPS/d:serv/d:comExt/d:vServMoeda', namespaces=ns) == ('400.00' if foreign else '641.33')
        assert node.findtext('./d:infDPS/d:IBSCBS/d:valores/d:trib/d:cClassTrib', namespaces=ns) == ('410027' if foreign else '000001')
        assert (node.find('./d:infDPS/d:IBSCBS/d:cIndOp', ns) is None) == foreign
    reports.append(report)
assert reports[0] == reports[1]
refusals = []
for label, snapshot, declaration in [('invalid-identity', 'snapshot.json', 'invalid-identity.json'),
                                      ('former-contract', 'former-snapshot.json', 'former-declarations.json')]:
    result = call(['exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'artisan', 'fiscal:dps:project-export-pair',
                   container + '/' + snapshot, container + '/' + declaration, '--output-dir=' + container + '/' + label])
    (args.output / (label + '.stdout.json')).write_bytes(result.stdout)
    (args.output / (label + '.stderr.log')).write_bytes(result.stderr)
    refusal = json.loads(result.stdout)
    assert result.returncode == 1 and refusal['status'] == 'ERROR'
    expected_reason = 'Qualified identity differs' if label == 'invalid-identity' else 'Own-tax price bridge refused:OWN_BRIDGE135_ALL_OWN_TAX_PRICE_DIFFERENCE_UNEXPLAINED'
    assert expected_reason in refusal['error']
    absent = call(['exec', '-T', 'app', 'php', '-r', 'echo json_encode(file_exists($argv[1]));', container + '/' + label])
    assert absent.returncode == 0 and absent.stdout == b'false'
    refusals.append({'case': label, 'exit_code': result.returncode, 'reason': refusal['error'], 'no_directory_created': True})
validation = {'status': 'LOCAL_COMPLETE_PAIR_CLI_VALIDATED_NOT_FISCAL_FULFILLMENT', 'identical_reports': True,
              'full_snapshot_replays': 4, 'complete_xml_files': 4, 'distinct_complete_dps': 2,
              'base_official_sources_valid': 112, 'additional_sources_valid': 8, 'refusals': refusals,
              'input_hashes': input_hashes, 'container_evidence_root': container,
              'xml_hashes': {record['role']: record['xml_sha256'] for record in reports[0]['records']},
              'document_obligation_complete': False, 'production_ready': False}
(args.output / 'validation.json').write_text(json.dumps(validation, indent=2) + '\n', encoding='utf-8')
print(json.dumps(validation))
