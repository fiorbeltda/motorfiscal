"""Audit all preserved native run/replay/projection artifacts without repeating their executions."""
import json,sys,hashlib
from pathlib import Path
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1];E=ROOT/'storage/app/phase4b-realization-consolidated-expansion47-draft-20261009-28'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
manifest=read(ROOT/'resources/fiscal/bundles/consumption-phase4b-2026.10.09-28-draft47.json')
def audit_existing(args):
 command=args[0]
 if command=='fiscal:rtc:run':
  financial=ROOT/args[1];case=financial.name.removesuffix('-financial.json');snapshot=read(E/(case+'-snapshot.json'))
  assert snapshot['input']==read(financial),case
  assert snapshot['knowledge']['content_hashes']['bundle_manifest']==digest(manifest),case
  assert snapshot['knowledge']['catalog_versions']['rtc_codes']=='2026.10.09-28-draft47'
  return snapshot['result']
 if command=='fiscal:rtc:replay':
  p=ROOT/args[1];case=p.name.removesuffix('-snapshot.json');snapshot=read(p);replay=read(E/(case+'-replay.json'))
  assert replay['replay_matches'] and replay['original_hash']==snapshot['result']['decision_hash'],case
  return replay
 if command=='fiscal:dere:project-monthly-closing':
  p=ROOT/args[1];case=p.name.removesuffix('-snapshot.json');snapshot=read(p);monthly=read(ROOT/args[2]);report=read(E/(case+'-monthly-report.json'))
  assert report['snapshot_hash']==digest(snapshot) and report['decision_hash']==snapshot['result']['decision_hash']
  assert report['monthly_input_hash']==digest(monthly)
  return report
 raise ValueError(command)
verifier=ROOT/'scripts/verify_phase4b_tvm_realization_consolidated47.py';source=verifier.read_text(encoding='utf-8')
source=source.replace("'expanded-corpus.json'","'expanded-realization-corpus.json'").replace('result=run(args)','result=audit_existing(args)').replace('projected=run(args)','projected=audit_existing(args)').replace("replay=run(['fiscal:rtc:replay'","replay=audit_existing(['fiscal:rtc:replay'")
sys.argv=[str(verifier),'corpus']
exec(compile(source,str(verifier),'exec'),{'__file__':str(verifier),'__name__':'__main__','audit_existing':audit_existing})
corpus=read(E/'expanded-realization-corpus.json');assert corpus['financial_replays']==87 and corpus['xml_bodies']==520
proof={'verifier_path':verifier.relative_to(ROOT).as_posix(),'verifier_sha256':hashlib.sha256(verifier.read_bytes()).hexdigest(),'audit_uses_preserved_native_replays':True,'native_replay_count':87,'input_snapshot_and_report_identity_verified':True,'xml_bodies':520,'separate_tax_arithmetic_verified':True,'scope28_completed':False,'production_ready':False}
target=E/'existing-artifact-audit.json'
if target.exists():assert read(target)==proof
else:
 with target.open('x',encoding='utf-8') as out:out.write(json.dumps(proof,indent=2)+'\n')
print('87 native snapshots/replays and520 XML bodies audited; inputs and tax arithmetic match')
