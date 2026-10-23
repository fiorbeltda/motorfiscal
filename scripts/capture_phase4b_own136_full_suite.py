"""Full unfiltered Pest in the same app image/environment, with bulk temporary evidence on D:."""
import hashlib
import json
import re
import shutil
import subprocess
import time
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
E = Path('D:/ProjetoSaasExports/phase4b-own-federal136-20261023-v1')
OUT = E / 'full-suite-v1'
DOCKER = 'D:/Docker/DockerDesktop/resources/bin/docker.exe'
NAME = 'phase4b-own136-full-suite-v1'

def sha(path):
    with path.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def save(name, value):
    with (OUT / name).open('x', encoding='utf-8') as handle:
        handle.write(json.dumps(value, indent=2) + '\n')

def docker(*args):
    return subprocess.check_output([DOCKER, *args], cwd=ROOT)

def inventory():
    paths = set()
    for folder in ['app', 'tests', 'resources/fiscal', 'scripts', 'config', 'routes']:
        paths.update(p for p in (ROOT / folder).rglob('*') if p.is_file() and '__pycache__' not in p.parts and p.suffix != '.pyc')
    for name in ['composer.json','composer.lock','phpunit.xml','phpstan.neon','pint.json','.gitattributes','compose.yaml','bootstrap/app.php','bootstrap/providers.php']:
        if (ROOT / name).is_file():
            paths.add(ROOT / name)
    return {str(p.relative_to(ROOT)).replace('\\','/'): sha(p) for p in sorted(paths)}

def mount_map(inspect):
    return {m['Destination']: {'source':m['Source'],'rw':m['RW'],'type':m['Type']} for m in inspect['Mounts']}

OUT.mkdir(exist_ok=False)
(OUT / 'tmp').mkdir()
scoped = read(ROOT / 'storage/app/phase4b-own136-development-20261023/own136-integration-scoped-validation.json')
assert scoped['status'] == 'OWN136_LOCAL_CLI_SCOPED_VALIDATED_FULL_SUITE_PENDING'
assert read(E / 'integration-directed-v2/validation.json')['complete']
probe = read(E / 'runtime-probe-v1/validation.json')
assert probe['status'] == 'SAME_APP_IMAGE_ENV_EXTENSIONS_AND_MOUNTS_WITH_WRITABLE_D_TEMP_VERIFIED'
assert shutil.disk_usage('C:/').free > 512 * 1024 * 1024 and shutil.disk_usage('D:/').free > 2 * 1024**3
before = inventory()
old = read(E / 'integration-directed-v2/input-hashes-before.json')
assert all(before.get(name) == digest for name, digest in old.items())
assert set(before)-set(old) == {'scripts/capture_phase4b_own136_full_suite.py'}
save('input-hashes-before.json', before)
app_id = docker('compose','ps','-q','app').decode().strip()
baseline = json.loads(docker('inspect',app_id))[0]
assert baseline['Image'] == probe['app_image']
save('app-inspect-before.json', baseline)
command = [DOCKER,'compose','run','--rm','--no-deps','-T','--pull','never','--name',NAME,
           '--volume',str(OUT / 'tmp').replace('\\','/')+':/tmp','app','php','-d','memory_limit=512M',
           'vendor/bin/pest','--compact','--colors=never','--do-not-cache-result','--cache-directory=/tmp/phase4b-own136-full-pest-cache']
started = datetime.now(timezone.utc).isoformat()
verified = False
with (OUT / 'stdout.log').open('xb') as log:
    process = subprocess.Popen(command,cwd=ROOT,stdout=log,stderr=subprocess.STDOUT)
    deadline = time.monotonic()+60
    while process.poll() is None and time.monotonic() < deadline:
        inspection = subprocess.run([DOCKER,'inspect',NAME],cwd=ROOT,capture_output=True)
        if inspection.returncode == 0:
            current = json.loads(inspection.stdout)[0]
            if current['State']['Running']:
                assert current['Image'] == baseline['Image']
                assert sorted(current['Config']['Env']) == sorted(baseline['Config']['Env'])
                assert current['Config']['User'] == baseline['Config']['User']
                assert current['Config']['WorkingDir'] == baseline['Config']['WorkingDir']
                mounts = mount_map(current)
                assert {k:v for k,v in mounts.items() if k != '/tmp'} == mount_map(baseline)
                assert mounts['/tmp']['rw'] and mounts['/tmp']['type'] == 'bind'
                save('validation-container-inspect.json',current)
                save('process.json',{'status':'RUNNING','started_at':started,'host_docker_pid':process.pid,
                    'container_name':NAME,'container_id':current['Id'],'linux_host_php_pid':current['State']['Pid'],
                    'container_php_pid':1,'frozen_inputs':len(before),'command':command,'same_app_runtime_verified':True,
                    'bulk_temporary_evidence_on_D':True,'phase4b_complete':False})
                verified = True
                print(json.dumps({'status':'RUNNING','container':NAME,'linux_host_php_pid':current['State']['Pid'],
                                  'host_docker_pid':process.pid,'frozen_inputs':len(before),'temporary_evidence_on_D':True}),flush=True)
                break
        time.sleep(0.2)
    code = process.wait()
after = inventory()
save('input-hashes-after.json',after)
log = (OUT / 'stdout.log').read_text(encoding='utf-8',errors='replace')
match = re.search(r'Tests:\s+(\d+) passed\s+\((\d+) assertions\)',log)
report = {'status':'TERMINAL','started_at':started,'finished_at':datetime.now(timezone.utc).isoformat(),
    'exit_code':code,'tests':int(match[1]) if match else None,'assertions':int(match[2]) if match else None,
    'input_files':len(before),'changed_inputs':[k for k,v in before.items() if after.get(k) != v],
    'added_inputs':sorted(set(after)-set(before)),'stdout_sha256':sha(OUT / 'stdout.log'),
    'same_app_runtime_verified':verified,'temporary_evidence_on_D':True,
    'complete':code==0 and match is not None and before==after and verified,'phase4b_complete':False}
save('validation.json',report)
print(json.dumps(report),flush=True)
print(log[-2500:],flush=True)
raise SystemExit(0 if report['complete'] else 1)
