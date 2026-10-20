"""Audit isolated94 against native PHP and frozen91 inputs; never claims runtime integration."""
from pathlib import Path
from datetime import datetime, timezone
from decimal import Decimal
import hashlib
import json
import subprocess

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-document94-base-reconciliation-20261016')
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
TARGET = E / 'document94-preparation-validation.json'
assert not TARGET.exists(), 'Preserve exclusive audit output'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    with p.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()

def save(p, value):
    with p.open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)

def run(label, args):
    for suffix in ['.stdout', '.stderr', '.json']:
        assert not (E / (label + suffix)).exists(), label
    command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', *args]
    started = datetime.now(timezone.utc).isoformat()
    proc = subprocess.run(command, cwd=ROOT, capture_output=True)
    record = {'command': command, 'started_at': started,
              'finished_at': datetime.now(timezone.utc).isoformat(), 'exit_code': proc.returncode}
    for channel in ['stdout', 'stderr']:
        raw = getattr(proc, channel)
        with (E / (label + '.' + channel)).open('xb') as stream:
            stream.write(raw)
        record[channel + '_sha256'] = hashlib.sha256(raw).hexdigest()
    save(E / (label + '.json'), record)
    assert proc.returncode == 0, (label, proc.stderr.decode(errors='replace'))
    return proc.stdout

preflight = read(Path(r'D:\ProjetoSaasExports\phase4b-advance91-full-suite-20261015\preflight.json'))
accept_path = ROOT / 'storage/app/phase4b-advance91-development-20261015/advance91-development-validation.json'
accept = read(accept_path)
assert sha(accept_path) == '0f530497e45b469ac0d7b2fa33b3965e645c3eb4e26bb1651d2f3e2f8e49db11'
assert accept['full_suite_complete'] is True and accept['full_suite']['tests'] == 2735

def unchanged():
    inventory = set()
    for directory, extensions in [('app', {'.php'}), ('tests', {'.php'}), ('resources/fiscal', {'.json', '.xml', '.xsd'})]:
        inventory.update(p.relative_to(ROOT).as_posix() for p in (ROOT / directory).rglob('*')
                         if p.is_file() and p.suffix in extensions)
    inventory.update(['phpunit.xml', 'composer.lock', 'compose.yaml', *accept['evidence_input_hashes']])
    assert inventory == set(preflight['input_hashes']), 'Frozen91 inventory changed'
    for path, digest in preflight['input_hashes'].items():
        assert sha(ROOT / path) == digest, path
    for path, digest in accept['runtime_hashes'].items():
        assert sha(ROOT / path) == digest, path
    assert len(inventory) == 5353 and len(accept['runtime_hashes']) == 71
    return len(inventory)

unchanged()
source = read(E / 'exact-source-cells.json')
assert sha(E / source['annex']['file']) == source['annex']['sha256']
assert sha(E / 'nt009-v1-01.pdf') == source['nt009']['sha256']
assert source['annex']['effective_from'] is None and source['annex']['environment_deployment_verified'] is False
cells = {(r['sheet'], c['cell']): c for r in source['selected_rows'] for c in r['cells']}
formula = cells['LEIAUTE DPS_NFS-e - RT', 'H53']
assert not formula['font_strike'] and 'até 2026' in formula['value'] and '2027 até 2032' in formula['value']
assert 'vAjusteBCIBSCBSComExt' in formula['value'] and 'vPIS' in formula['value']
presence = cells['RN DPS_NFS-e', 'D601']
assert not presence['font_strike'] and 'tpEmit = 1 e tribISSQN <> 3' in presence['value']
assert cells['RN DPS_NFS-e', 'H601']['value'] == 'EXXX'
assert cells['RN DPS_NFS-e', 'D602']['font_strike'] and cells['RN DPS_NFS-e', 'D603']['font_strike']

local_files = {'DocumentBaseReconciliation94.php': E / 'DocumentBaseReconciliation94-formatted.php',
               'probe94.php': E / 'probe94-formatted.php', 'cases.json': E / 'cases.json'}
local_hashes = {name: sha(path) for name, path in local_files.items()}
remote = json.loads(run('audit94-runtime-file-binding-v1', ['-r',
    '$out=[];foreach(["DocumentBaseReconciliation94.php","probe94.php","cases.json"] as $f)'
    '{$out[$f]=hash_file("sha256","/tmp/phase4b-document94/".$f);}echo json_encode($out,JSON_THROW_ON_ERROR);']))
assert remote == local_hashes, 'Native temp files must match final archived artifacts'
native = json.loads(run('audit94-final-native-v1', ['/tmp/phase4b-document94/probe94.php']))
assert native['status'] == 'STAGED94_NATIVE_CASES_AND_DECIMAL_ORACLES_PASSED'
assert native['passed'] == 56 and native['checks'] == 596
cases = read(E / 'cases.json')
assert len(cases) == 56 and len({c['name'] for c in cases}) == 56
results = {case['name']: case['result'] for case in native['cases']}
flags = ['inputs_materially_qualified', 'source_effective_date_proven', 'tax_recalculated',
         'issuer_fields_generated', 'xml_schema_validated', 'document_obligation_complete',
         'posting_authorized', 'production_ready']
oracles = 0
for case in cases:
    result = results[case['name']]
    assert result['status'] == case['expected_status'] and result['reason'] == case['expected_reason']
    assert all(result[f] is False for f in flags)
    if 'independent_decimal_base' not in case:
        continue
    doc = case['document']
    money = lambda key: Decimal(doc[key]) if doc[key] is not None else Decimal('0.00')
    base = money('vServ') - money('descIncond') - money('vCalcAjusteBCIBSCBS') - money('vCalcAjusteBCLocImoveis') - money('vISSQN') + money('vAjusteBCIBSCBSComExt')
    if doc['competence_date'] <= '2026-12-31':
        base -= money('vPIS') + money('vCOFINS')
    assert format(base, '.2f') == case['independent_decimal_base'] == result['calculated_document_base_brl']
    assert format(money('expected_rtc_base_brl') - base, '.2f') == case['independent_decimal_difference'] == result['difference_brl']
    oracles += 1
assert oracles == 12
assert results['not-net-price1000']['difference_brl'] == '29.25'
assert results['comext-even-zero-forbidden']['status'] == 'ACTION_REQUIRED'
assert results['year2033-no-guess']['status'] == 'ACTION_REQUIRED'
assert '[OK] No errors' in run('audit94-final-phpstan-v1', ['vendor/bin/phpstan', 'analyse', '--no-progress', '/tmp/phase4b-document94/DocumentBaseReconciliation94.php']).decode()
style = run('audit94-final-pint-v1', ['vendor/bin/pint', '--test', '/tmp/phase4b-document94/DocumentBaseReconciliation94.php', '/tmp/phase4b-document94/probe94.php']).decode()
assert 'PASS' in style and '2 files' in style
unchanged()
assert {name: sha(path) for name, path in local_files.items()} == local_hashes
evidence = {p.name: sha(p) for p in E.iterdir() if p.is_file()}
report = {'status': 'DOCUMENT94_DECLARED_BASE_MEMORY_PREPARATION_VERIFIED_NOT_INTEGRATED',
          'created_at': datetime.now(timezone.utc).isoformat(),
          'scope': 'LOCAL_DECLARED_MEMORY_COMPARISON_ONLY_NOT_A_DOCUMENT_OR_FISCAL_DECISION',
          'baseline91_acceptance_sha256': sha(accept_path), 'frozen91_input_files_unchanged': 5353,
          'runtime91_hashes_unchanged': 71, 'native_cases': 56, 'native_checks': 596,
          'independent_decimal_oracles': oracles, 'native_files': local_hashes, 'evidence_hashes': evidence,
          'runtime_integrated': False, 'paired_export_projection_complete': False,
          'full_suite_rerun': False, 'phase4b_complete': False, **{f: False for f in flags},
          'next_required': ['Bind each memory to immutable qualified ISS/RTC snapshots and allocation73.',
                            'Emit distinct foreign/domestic documents with correct NT009 structure and indicator-driven omission.',
                            'Qualify applicable schema/environment/version before asserting document validation.',
                            'Integrate permanent tests and native corpora; full regression after runtime changes.']}
save(TARGET, report)
print(json.dumps({k: v for k, v in report.items() if k not in ['evidence_hashes', 'next_required']}, ensure_ascii=False))
