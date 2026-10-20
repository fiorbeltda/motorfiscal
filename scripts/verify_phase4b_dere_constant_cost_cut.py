"""Independent Decimal oracle, frozen official response, CLI snapshots and replays."""
import hashlib,json,subprocess
from decimal import Decimal,localcontext,ROUND_HALF_UP
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';E=ROOT/'storage/app/phase4b-constant-cost-20261006-24';V='2026.10.06-24';B=f'bundles/consumption-phase4b-{V}.json'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p;return
 p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
def run(args,code=0):
 p=subprocess.run(['docker','compose','exec','-T','app','php','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8');assert p.returncode==code,p.stdout+p.stderr;return json.loads(p.stdout)
b=read(F/B);c=read(F/b['resource_paths']['rtc_code_catalog']);policy=c['dere_debt_constant_cost_calculation'];raw=read(F/'sources/official'/policy['official_data_file']);assert len(raw)==21
assert policy['official_data_sha256']==hashlib.sha256((F/'sources/official'/policy['official_data_file']).read_bytes()).hexdigest()
with localcontext() as context:
 context.prec=100
 factor=Decimal(1)
 for row in raw:factor*=1+Decimal(row['valor'])/100
 assert (Decimal('10000')*(factor-1)).quantize(Decimal('.01'),rounding=ROUND_HALF_UP)==Decimal('108.17')
 cases=[];replays=0
 for path in sorted((F/f'scenarios/dere-constant-cost-{V}').glob('*.json')):
  v=read(path);pos=v['position'];positive=v['expectation']['status']=='RESOLVED';inp=E/f'{path.stem}-position.json';save(inp,pos);snap=E/f'{path.stem}-snapshot.json'
  args=['fiscal:dere:calculate-debt-constant-cost',inp.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']
  if positive and not snap.exists():args.append('--snapshot='+snap.relative_to(ROOT).as_posix())
  r=run(args,0 if positive else 1);actual=r['result'];assert actual['production_ready'] is False
  assert actual['status']==v['expectation']['status']
  if positive:
   cap=(Decimal(pos['cost_basis'])*(factor-1)).quantize(Decimal('.01'),rounding=ROUND_HALF_UP)
   base=max(Decimal(0),min(cap,Decimal(pos['vVarMensal']))-Decimal(pos.get('vPisCofins','0.00')))
   assert Decimal(actual['vSelic'])==cap and Decimal(actual['vApur'])==base
   assert actual['daily_rates_applied']==21 and actual['negative_base_created'] is False and actual['tax_totalization_performed'] is False
   replay=run(['fiscal:dere:calculate-debt-constant-cost','--replay='+snap.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']);assert replay['replay_matches'] and replay['result_hash']==r['result_hash'];replays+=1
  else:assert actual['reason']==v['expectation']['reason'],(path.name,actual)
  save(E/f'{path.stem}-result.json',r);cases.append({'case':path.stem,'status':actual['status'],'reason':actual['reason'],'result_hash':r['result_hash']})
assert len(cases)==22 and replays==7
sample=read(E/'capped-yield-snapshot.json');sample['position']['vVarMensal']='151.00';tampered=E/'tampered-snapshot.json';save(tampered,sample)
assert 'error' in run(['fiscal:dere:calculate-debt-constant-cost','--replay='+tampered.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}'],1)
assert 'error' in run(['fiscal:dere:calculate-debt-constant-cost','--replay='+str((E/'capped-yield-snapshot.json').relative_to(ROOT)), '--bundle-manifest=bundles/consumption-phase4b-2026.10.06-23.json'],1)
assert 'error' in run(['fiscal:dere:calculate-debt-constant-cost',str((E/'capped-yield-position.json').relative_to(ROOT)),f'--bundle-manifest={B}','--knowledge-at=2026-10-05T00:00:00+00:00'],1)
save(E/'corpus.json',{'valid':True,'cases':cases,'snapshots_replayed':replays,'command_guards':3,'official_daily_rows':21,'oracle':'PYTHON_DECIMAL_100_DIGIT_FINAL_ROUND_HALF_UP'})
g=run(['fiscal:rtc:production-assess',f'--bundle-manifest={B}',f'--controls=resources/fiscal/production/phase4b-controls-{V}.json','--target=local-validation']);assert not g['ready'] and g['official_source_hashes_valid']==47;save(E/'production-assessment.json',g)
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or hashlib.sha256((ROOT/p).read_bytes()).hexdigest()!=h];assert not changed;save(E/'historical-verification.json',{'checked':len(baseline),'changed':changed})
print(f'Candidate24 PASS:22 cases/7 replays/3 command guards;21 official BCB rows;{len(baseline)} historical artifacts preserved.')
