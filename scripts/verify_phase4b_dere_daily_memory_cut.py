"""Verify daily position, curve, monthly arithmetic and immutable command replay contracts."""
import copy,hashlib,json,subprocess
from decimal import Decimal,ROUND_HALF_EVEN
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';E=ROOT/'storage/app/phase4b-daily-memory-20261006-26';S=F/'scenarios/dere-daily-memory-2026.10.06-26';B='bundles/consumption-phase4b-2026.10.06-26.json'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def digest(v):return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
def save(p,v):
 if p.exists():assert read(p)==v,p;return
 p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
def run(args,code=0):
 p=subprocess.run(['docker','compose','exec','-T','app','php','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8');assert p.returncode==code,p.stdout+p.stderr
 return json.loads(p.stdout)
cases=[];replays=0
for source in sorted(S.glob('*.json')):
 v=read(source);pos=E/(source.stem+'-position-final.json');save(pos,v['position']);snap=E/(source.stem+'-snapshot.json')
 positive=v['expectation']['status']=='RESOLVED';args=['fiscal:dere:calculate-debt-daily-memory',pos.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']
 if positive and not snap.exists():args+=['--snapshot='+snap.relative_to(ROOT).as_posix()]
 r=run(args,0 if positive else 1);actual=r['result'];assert not r['production_ready'] and not actual['production_ready'];assert digest(actual)==r['result_hash']
 assert actual['status']==v['expectation']['status']
 if positive:
  for key,value in v['expectation']['oracle'].items():
   if key=='daily_memory':
    for i,row in enumerate(value):
     for field,amount in row.items():assert actual[key][i][field]==amount,(source.name,i,field)
   else:assert actual[key]==value,(source.name,key)
  assert len(actual['daily_memory'])==30 and actual['daily_rates_applied']==21 and actual['retention_years']==5
  assert all(actual[k] is False for k in ['normative_method_homologated','financial_base_integration_performed','xml_projection_performed','sale_gain_tax_treatment_performed','negative_base_created','cross_title_loss_offset_performed'])
  m=actual['monthly'];D=Decimal
  assert D(m['vSaldoContIni'])+D(m['vEntradas'])-D(m['vSaidas'])-D(m['vJurosRec'])+D(m['vVarMensal'])==D(m['vSaldoContFin'])
  assert max(D(0),min(D(m['vSelic']),D(m['vVarMensal']))-D(m['vPisCofins']))==D(m['vApur'])
  replay=run(['fiscal:dere:calculate-debt-daily-memory','--replay='+snap.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']);assert replay['replay_matches'] and replay['result_hash']==r['result_hash'];replays+=1
 else:assert actual['reason']==v['expectation']['reason'],(source.name,actual)
 save(E/(source.stem+'-result.json'),r);cases.append({'case':source.stem,'status':actual['status'],'reason':actual['reason'],'result_hash':r['result_hash']})
assert len(cases)==44 and replays==16
snap=E/'constant-snapshot.json';position=E/'constant-position-final.json';tampered=copy.deepcopy(read(snap));tampered['position']['declared_monthly']['vSelic']='0.00';target=E/'tampered-snapshot.json';save(target,tampered)
guard_args=[['--replay='+target.relative_to(ROOT).as_posix()],['--replay='+snap.relative_to(ROOT).as_posix(),'--knowledge-at=2026-10-06T00:00:00+00:00'],
 ['--replay='+snap.relative_to(ROOT).as_posix(),'--snapshot='+str(E/'forbidden-snapshot.json')],
 ['--replay='+snap.relative_to(ROOT).as_posix(),'--bundle-manifest=bundles/consumption-phase4b-2026.10.06-25.json'],
 [position.relative_to(ROOT).as_posix(),'--knowledge-at=2026-10-05T00:00:00+00:00'],
 [position.relative_to(ROOT).as_posix(),'--snapshot='+snap.relative_to(ROOT).as_posix()]]
for args in guard_args:assert 'error' in run(['fiscal:dere:calculate-debt-daily-memory',*args],1)
save(E/'corpus.json',{'valid':True,'cases':cases,'snapshots_replayed':replays,'command_guards':len(guard_args),'oracle':'INDEPENDENT_PYTHON_DECIMAL_PRECISION_100_HALF_EVEN_8_2','local_conventions_homologated':False})
g=run(['fiscal:rtc:production-assess',f'--bundle-manifest={B}','--controls=resources/fiscal/production/phase4b-controls-2026.10.06-26.json','--target=local-validation']);assert not g['ready'] and g['official_source_hashes_valid']==47;save(E/'production-assessment.json',g)
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or hashlib.sha256((ROOT/p).read_bytes()).hexdigest()!=h];assert not changed,changed
save(E/'historical-verification.json',{'checked':len(baseline),'changed':changed})
print(f'Candidate26 PASS:44 cases/16 replays/6 command guards;30 daily rows per positive;{len(baseline)} historical artifacts preserved.')
