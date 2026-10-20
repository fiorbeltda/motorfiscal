"""Freeze signed prior deferral under its own replayable knowledge, then permit it in current knowledge."""
import copy,hashlib,json,subprocess
from datetime import datetime,timezone
from pathlib import Path
from dere_daily_memory_oracle import oracle,proof,seal,digest
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal'
E=ROOT/'storage/app/phase4b-realization-negative-history-draft-20261007-28';S=F/'scenarios/tvm-negative-history-2026.10.07-28-draft8'
E.mkdir(parents=True,exist_ok=True);S.mkdir(parents=True,exist_ok=True)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:p.parent.mkdir(parents=True,exist_ok=True);p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
if not (E/'historical-before.json').exists():
 baseline={}
 for folder in [F,ROOT/'storage/app']:
  for p in folder.rglob('*'):
   if p.is_file() and E not in p.parents and not any(v in str(p) for v in ['2026.10.07-28-draft7','2026.10.07-28-draft8']) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']:baseline[p.relative_to(ROOT).as_posix()]=hashlib.sha256(p.read_bytes()).hexdigest()
 save(E/'historical-before.json',baseline)
def bundle(version,old,current=False):
 b=copy.deepcopy(old);c=read(F/old['resource_paths']['rtc_code_catalog']);target=F/f'bundles/consumption-phase4b-{version}.json'
 known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
 c['version']=version;c['financial_operation']['base']['deferred_fair_value_sign_policy']='INDEPENDENT_SIGNED_SUBACCOUNT_RECOGNITION'
 c['financial_operation']['base']['securities_realization']['published_at']=known
 c['dere_monthly_closing_projection']['securities_realization_binding']['published_at']=known
 if current:
  h=c['financial_operation']['base']['securities_realization']['analysis']['history_policy']
  h.update(published_at=known,signed_private_deferral_policy='REPLAYED_INDEPENDENT_SIGNED_SUBACCOUNT_RECOGNITION')
  h['permitted_bundle_manifest_hashes'].append(digest(old))
 cp=f'catalogs/rtc-codes-{version}.json';save(F/cp,c)
 ctrl=read(F/old['production_controls']['path']);ctrl['version']=version
 ct=f'production/phase4b-controls-{version}.json';save(F/ct,ctrl)
 b.update(predecessor_bundle_id=old['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+version,version=version,knowledge_available_at=known)
 b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=version;b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
 return b,c
prior,c=bundle('2026.10.07-28-draft7',read(F/'bundles/consumption-phase4b-2026.10.07-28-draft6.json'))
current,c=bundle('2026.10.07-28-draft8',prior,True)
history=c['financial_operation']['base']['securities_realization']['analysis']['history_policy']
position=read(F/'scenarios/tvm-history-draft-2026.10.06-28/previous-private-august-position.json')['position']
position['appropriations']={'2026-08-20':'100','2026-08-31':'-200'}
curve,monthly,result=oracle(position,history['daily_memory_policies']['2026-08']['series']);position.pop('appropriations');position['contract_curve']=curve;position['declared_monthly']=monthly;seal(position)
position.pop('memory_evidence');position['memory_evidence']=proof(position,'SYNTHETIC.NEGATIVE.HISTORY.AUGUST.MEMORY','2026-08-01','2026-08-31')
save(S/'prior-negative-position-v2.json',{'position':position,'independent_oracle':result})
scenario=read(F/'scenarios/tvm-history-draft-2026.10.06-28/previous-private-august-financial.json')
scenario.update(scenario_id='SYNTHETIC-prior-negative-fair-value',knowledge_at=prior['knowledge_available_at'],rule_bundle_id=prior['bundle_id'],catalog_versions=prior['catalog_versions'])
ledger=scenario['facts']['rtc.financial_ledger'][0];fv=ledger['entries'][1];fv['fair_value_nature']='D'
value={'supplier_id':ledger['supplier_id'],'period_start':ledger['period_start'],'period_end':ledger['period_end'],
 'financial_entry_id':fv['id'],'document_id':fv['document_id'],'amount':fv['amount'],'fair_value_nature':'D','security_identity':fv['security_identity'],
 'subaccount_evidence_id':fv['subaccount_evidence_id'],'exceeds_produced_return':True}
fv['fair_value_sign_evidence']=proof(value,'SYNTHETIC.NEGATIVE.DEFERRAL.SIGN','2026-08-01','2026-08-31')
save(S/'prior-negative-financial.json',scenario)
snapshot=E/'prior-negative-snapshot.json'
if not snapshot.exists():
 p=subprocess.run(['docker','compose','exec','-T','app','php','artisan','fiscal:rtc:run',(S/'prior-negative-financial.json').relative_to(ROOT).as_posix(),'--bundle-manifest=bundles/consumption-phase4b-2026.10.07-28-draft7.json','--snapshot='+snapshot.relative_to(ROOT).as_posix()],cwd=ROOT,capture_output=True,encoding='utf-8')
 assert p.returncode==0,p.stdout+p.stderr
 save(E/'prior-negative-result.json',json.loads(p.stdout))
r=read(snapshot)['result'];assert r['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'],r
base=next(t['base'] for t in r['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE');assert base['net_base']=='100.00' and base['deferred']=='-200.00'
p=read(F/'scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json')['facts']['rtc.financial_ledger'][0]['entries'][4]['securities_realization']['analysis_input']['position']
p['opening']['book_balance']='9900.00';p['previous_position_evidence']['value']['closing']=copy.deepcopy(p['opening']);p['previous_position_evidence'].pop('source_hash');p['previous_position_evidence']['source_hash']=digest(p['previous_position_evidence'])
p['operations'][0]['book_amount']='3960.00';p['operations'][0]['sale_proceeds']='3900.00'
p['appropriations']={'2026-09-30':'150'};curve,monthly,result=oracle(p,c['dere_debt_daily_memory_calculation']['series']);p.pop('appropriations');p['contract_curve']=curve;p['declared_monthly']=monthly;seal(p)
save(S/'current-negative-position.json',{'position':p,'independent_oracle':result,'expected':{'net_base':'710.00','current_curve':'150.00','produced_loss':'60.00','deferred_loss_realized':'80.00','closing_deferred':'-120.00000000'}})
save(E/'research.json',{'legal_url':'https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html','source_sha256':history['source_hash'],
 'rule':'Separate prior expense deferral from current deduction; prior loss is not automatically deducted before realization; current authorization remains required.',
 'prior_bundle':'2026.10.07-28-draft7','current_bundle':'2026.10.07-28-draft8','scope28_completed':False,'production_ready':False})
print('Negative deferral -200 replayed in prior knowledge; current position independently frozen.')
