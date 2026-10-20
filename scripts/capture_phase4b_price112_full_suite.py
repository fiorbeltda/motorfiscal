"""Single full112 suite after terminal CLI; frozen inputs and exclusive D evidence."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re
import shutil
import subprocess
import time
from verify_phase4b_price112_evidence import verify, inputs

R = Path(__file__).resolve().parents[1]
BASE = Path(r'D:\ProjetoSaasExports')
E = BASE/'phase4b-price112-integration-20261019'
C = E/'cli-v1'
F = BASE/'phase4b-price112-full-suite-20261019'
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
def read(p): return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb') as f: return hashlib.file_digest(f, 'sha256').hexdigest()
def save(p,v):
    with p.open('x',encoding='utf-8') as f: json.dump(v,f,indent=2,ensure_ascii=False)
def clean(p): return re.sub(r'\x1b\[[0-9;]*m','',p.read_text(encoding='utf-8',errors='replace'))

assert not F.exists(), 'Exclusive attempt; never restart a live suite'
corpus, _, independent = verify()
print('PASS:independent preflight '+str(independent['audit_checks'])+' checks',flush=True)
assert corpus['native_decisions'] == corpus['exact_replays'] == 248 and corpus['historical_exact_replays'] == 543
assert corpus['input_inventory_unchanged'] is True
runtime = corpus['runtime_hashes']
for p,h in runtime.items(): assert sha(R/p) == h,p
cli_frozen = read(C/'preflight.json')['input_hashes']
for p,h in cli_frozen.items(): assert sha(Path(p)) == h,p
old_frozen = read(BASE/'phase4b-dated108-full-suite-20261019/preflight.json')['input_hashes']
authorized = {str(R/'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php'),str(R/'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php')}
for p,h in old_frozen.items():
    if p not in authorized: assert sha(Path(p)) == h,p
    else: assert sha(E/'before-integration'/Path(p).relative_to(R)) == h,p
bundle = read(R/'resources/fiscal'/corpus['bundle'])
def inventory():
    return inputs()

top = subprocess.run([D,'compose','top','app'],cwd=R,capture_output=True,text=True,check=True).stdout
assert 'vendor/bin/pest' not in top, 'A suite is already live'
assert shutil.disk_usage(R).free > 256*1024*1024
before = inventory(); F.mkdir()
started = datetime.now(timezone.utc).isoformat()
save(F/'preflight.json',{'created_at':started,'input_hashes':before,'runtime_hashes':runtime,
    'corpus_sha256':sha(C/'price112-cli-validation.json'),'expected_total_tests':7738,'new_tests':538,
    'independent_cli_audit_checks':independent['audit_checks'],'independent_decimal_checks':independent['independent_decimal_checks'],
    'bundle':corpus['bundle'],'bundle_sha256':corpus['bundle_sha256'],'full_suite_complete':False,'production_ready':False})
command = [D,'compose','exec','-T','app','php','-d','memory_limit=512M','vendor/bin/pest',
           '--colors=never','--do-not-cache-result','--cache-directory=/tmp/phase4b-price112-pest-cache']
begin = time.monotonic()
with (F/'full-suite.log').open('xb') as log:
    proc = subprocess.Popen(command,cwd=R,stdout=log,stderr=subprocess.STDOUT)
    save(F/'process-start.json',{'host_docker_pid':proc.pid,'started_at':started,'command':command})
    print(f'LIVE:full112;Docker PID {proc.pid};frozen inputs {len(before)}',flush=True)
    code = proc.wait()
after = inventory()
m = re.search(r'Tests:\s+(\d+) passed\s+\((\d+) assertions\)',clean(F/'full-suite.log'))
changed = [p for p in before if before[p] != after.get(p)]
added = sorted(set(after)-set(before)); removed = sorted(set(before)-set(after))
suite = {'exit_code':code,'started_at':started,'finished_at':datetime.now(timezone.utc).isoformat(),
    'seconds':time.monotonic()-begin,'log_sha256':sha(F/'full-suite.log'),
    'tests':int(m[1]) if m else None,'assertions':int(m[2]) if m else None,
    'input_files_changed':changed,'input_files_added':added,'input_files_removed':removed,
    'full_suite_complete':code == 0 and m is not None and not changed and not added and not removed}
save(F/'full-suite.json',suite); print(json.dumps(suite),flush=True)
assert suite['full_suite_complete'] and suite['tests'] == 7738
def final(label,args):
    cmd = [D,'compose','exec','-T','app','php','-d','memory_limit=512M',*args]
    record = {'command':cmd,'started_at':datetime.now(timezone.utc).isoformat()}
    proc = subprocess.run(cmd,cwd=R,capture_output=True)
    record.update(exit_code=proc.returncode,finished_at=datetime.now(timezone.utc).isoformat())
    for ch in ['stdout','stderr']:
        with (F/(label+'.'+ch)).open('xb') as f: f.write(getattr(proc,ch))
        record[ch+'_sha256'] = sha(F/(label+'.'+ch))
    save(F/(label+'.json'),record); assert proc.returncode == 0,label
    return clean(F/(label+'.stdout'))
assert '[OK] No errors' in final('final-phpstan',['vendor/bin/phpstan','analyse','--no-progress'])
assert re.search(r'PASS.*581 files',final('final-pint',['vendor/bin/pint','--test']))
final('final-production-assess112',['artisan','fiscal:rtc:production-assess','--bundle-manifest='+corpus['bundle'],
    '--controls=resources/fiscal/'+bundle['production_controls']['path'],'--target=local-validation'])
assessment = read(F/'final-production-assess112.stdout')
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert assessment['official_sources_checked'] == assessment['official_source_hashes_valid'] == 94
assert set(assessment['blockers']) == set(read(C/'production-assess112.stdout')['blockers']) and len(assessment['blockers']) == 125
assert inventory() == before
save(F/'price112-regression-validation.json',{'status':'PRICE112_LOCAL_INTEGRATION_REGRESSION_VERIFIED',
    'created_at':datetime.now(timezone.utc).isoformat(),'full_suite':suite,'full_suite_complete':True,
    'bundle':corpus['bundle'],'bundle_sha256':corpus['bundle_sha256'],'runtime_hashes':runtime,
    'unchanged_input_files':len(before),'native_decisions':248,'exact_replays':248,'historical_exact_replays':543,
    'independent_cli_audit_checks':independent['audit_checks'],'independent_decimal_checks':independent['independent_decimal_checks'],
    'official_sources_valid':94,'production_blocker_count':125,'document_obligation_complete':False,
    'legal_applicability_approved':False,'iss_base_calculated':False,'documentary_currency_conversion_qualified':False,
    'xml_generated':False,'phase4b_complete':False,'production_ready':False})
print('PASS:full112/final quality;independent acceptance still required',flush=True)
