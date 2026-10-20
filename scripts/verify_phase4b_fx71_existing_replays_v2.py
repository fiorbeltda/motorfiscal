"""Fresh exact replay of direct FX and credit snapshots after combined origin/offset/exact-token successor, preserving old reports."""
from pathlib import Path
import hashlib,json,subprocess
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-fx71-exact-quote-preparation-20261014/replay-v2'
P=ROOT/'storage/app/phase4b-import-fx68-integration-20261013'

def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
cases=[]
paths=[P/(c['name']+'-integrated-snapshot.json') for c in read(P/'integrated-corpus-validation.json')['cases']]
paths += [P/(c['name']+'-fx-credit-snapshot.json') for c in read(P/'fx-credit-validation.json')['cases']]
assert len(paths)==18
for path in paths:
    before=sha(path)
    executed=subprocess.run(['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan',
        'fiscal:rtc:replay',path.relative_to(ROOT).as_posix()],cwd=ROOT,capture_output=True,encoding='utf-8')
    log=E/(path.stem+'-replay.log')
    with log.open('x',encoding='utf-8') as f:f.write(executed.stdout+executed.stderr)
    assert executed.returncode==0 and json.loads(executed.stdout)['replay_matches'] is True
    assert sha(path)==before
    cases.append({'path':path.relative_to(ROOT).as_posix(),'sha256':before,'log_sha256':sha(log),'exact_replay':True})
    print('PASS:'+path.stem+'; combined successor preserves exact decision',flush=True)
with (E/'post-fix-replay-validation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'ALL18_DIRECT_FX_AND_CREDIT_SNAPSHOTS_EXACTLY_REPLAYED_AFTER_COMBINED_SUCCESSOR',
        'cases':cases,'source_hashes_preserved':True,'full_suite_complete':False,'production_ready':False},f,indent=2)
