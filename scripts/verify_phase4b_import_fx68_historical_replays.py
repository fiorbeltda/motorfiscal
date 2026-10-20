"""Fresh read-only integrated historical replays after FX activation; never overwrite predecessors."""
from pathlib import Path
import hashlib,json,subprocess
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-import-fx68-integration-20261013'
P=ROOT/'storage/app'

def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
paths=sorted(set(P.glob('phase4b-input-credit-*-snapshot.json'))|set(P.glob('phase4b-integrated-institutional-immunity-*-snapshot.json')))
assert paths,'Historical integrated snapshots missing'
records=[]
for path in paths:
    before=sha(path)
    command=['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan','fiscal:decision:replay',path.relative_to(ROOT).as_posix()]
    executed=subprocess.run(command,cwd=ROOT,capture_output=True,encoding='utf-8')
    label='historical-'+path.stem
    log=E/(label+'.log')
    with log.open('x',encoding='utf-8') as f:f.write(executed.stdout+executed.stderr)
    assert executed.returncode==0,executed.stderr
    result=json.loads(executed.stdout)
    assert result['replay_matches'] is True and sha(path)==before
    records.append({'path':path.relative_to(ROOT).as_posix(),'sha256':before,'log_sha256':sha(log),'exact_replay':True})
    print('PASS:'+path.name+'; exact integrated replay and historical bytes preserved',flush=True)
with (E/'historical-integrated-replay-validation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'HISTORICAL_CREDIT_AND_ISS_RTC_FEDERAL_INTEGRATIONS_EXACTLY_REPLAYED',
        'cases':records,'count':len(records),'production_ready':False},f,indent=2)
