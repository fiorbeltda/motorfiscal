"""Verify separate IBS/CBS amounts and identity against all captured/replayed decisions."""
import json,hashlib
from pathlib import Path
from decimal import Decimal,ROUND_HALF_EVEN
ROOT=Path(__file__).resolve().parents[1];E=ROOT/'storage/app/phase4b-realization-consolidated-expansion47-draft-20261009-28'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
corpus=read(E/'expanded-corpus.json');assert corpus['financial_replays']==87 and corpus['xml_bodies']==520
records=[]
for item in corpus['cases']:
 p=E/(item['case']+'-snapshot.json');assert sha(p)==item['snapshot_sha256'];snapshot=read(p);result=snapshot['result'];replay=read(E/(item['case']+'-replay.json'))
 assert replay['replay_matches'] and replay['original_hash']==result['decision_hash']
 assert snapshot['knowledge']['catalog_versions']['rtc_codes']=='2026.10.09-28-draft47'
 base=next(stage['base'] for stage in result['trace'] if stage['stage']=='FINANCIAL_OPERATION_AND_BASE');tax=next(stage for stage in result['trace'] if stage['stage']=='FINANCIAL_GROSS_DEBITS')
 assert tax['base']==base['net_base']==item['base']
 for key,rate in [('ibs_nominal','.001'),('cbs_nominal','.009')]:assert Decimal(tax[key])==(Decimal(base['net_base'])*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN),(item['case'],key)
 records.append({'case':item['case'],'snapshot_sha256':sha(p),'decision_hash':result['decision_hash'],'base':base['net_base'],'ibs_nominal':tax['ibs_nominal'],'cbs_nominal':tax['cbs_nominal']})
target=E/'separate-tax-audit.json';value={'cases':records,'case_count':87,'separate_ibs_cbs_verified':True,'scope28_completed':False,'production_ready':False}
if target.exists():assert read(target)==value
else:
 with target.open('x',encoding='utf-8') as out:out.write(json.dumps(value,indent=2)+'\n')
print('87 replayed decisions: separate IBS/CBS arithmetic verified')
