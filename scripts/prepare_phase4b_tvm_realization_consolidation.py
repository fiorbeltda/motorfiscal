"""Inspect scope28 requirements against actual heterogeneous evidence; do not claim a unified candidate."""
import json,hashlib,re,gc
from pathlib import Path
from decimal import Decimal
from html.parser import HTMLParser
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1];E=ROOT/'storage/app/phase4b-realization-consolidation-preparation-20261009-28'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
prior=ROOT/'storage/app/phase4b-realization-shared-asset-current-draft-20261009-28/multiple-progress-report.json';accepted=read(prior);assert accepted['current_multiple_title_closing_accepted'] and not accepted['scope28_completed']
cases={
 'private-buy-sell':('phase4b-realization-book-draft-20261006-28','first-gain'),
 'private-opposing':('phase4b-realization-book-draft-20261006-28','first-offsetting'),
 'public-prior-fair':('phase4b-realization-public-deferral-draft-20261008-28','full-gain'),
 'public-fund':('phase4b-realization-public-fair-draft-20261007-28','exclusive-fund'),
 'third-sale-rounding':('phase4b-realization-third-sale-integrated-draft-20261008-28','positive-partial-complete'),
 'historical-separate-assets':('phase4b-realization-historical-multiple-complete-draft-20261009-28','partial-partial-shared'),
 'shared-asset-partial':('phase4b-realization-shared-asset-current-draft-20261009-28','partial-partial-shared'),
 'shared-asset-complete':('phase4b-realization-shared-asset-current-draft-20261009-28','complete-complete-shared'),
 'shared-asset-asymmetric':('phase4b-realization-shared-asset-current-draft-20261009-28','partial-complete-separate')}
evidence={};bodies=0
for name,(folder,case) in cases.items():
 d=ROOT/'storage/app'/folder;sp=d/(case+'-snapshot.json');rp=d/(case+'-monthly-report.json');snapshot=read(sp);report=read(rp);assert report['projection']['status']=='PROJECTED' and not report['production_ready'];assert report['decision_hash']==snapshot['result']['decision_hash'] and report['snapshot_hash']==digest(snapshot)
 base=next(t['base'] for t in snapshot['result']['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE');parents=base['realization_bindings'];sales=[s for parent in parents.values() for s in parent['analysis']['realizations']];observations={'parent_count':len(parents),'base':base['net_base'],'sales':[{'accounting_gain':s['accounting_gain'],'price_minus_acquisition_cost':s['price_minus_acquisition_cost'],'book_value_released':s['book_value_released'],'acquisition_cost_released':s['acquisition_cost_released'],'components':s['components']} for s in sales],'holding_routes':[p['title'].get('holding_route') for p in parents.values()]}
 if name=='private-buy-sell':
  positions=[v['securities_realization']['analysis_input']['position'] for v in snapshot['input']['facts']['rtc.financial_ledger'][0]['entries'] if 'securities_realization' in v];assert any(p['first_acquisition'] and any(o['kind']=='BUY' and o['date'].startswith(p['period']) for o in p['operations']) for p in positions)
 if name=='private-opposing':assert any(Decimal(s['accounting_gain'])>0 for s in sales) and any(Decimal(s['accounting_gain'])<0 for s in sales)
 if name=='public-prior-fair':assert any(Decimal(s['components']['deferred_fair_value_realized'])!=0 and Decimal(s['accounting_gain'])!=Decimal(s['price_minus_acquisition_cost']) for s in sales)
 if name=='public-fund':assert 'EXCLUSIVE_FUND_95' in observations['holding_routes']
 if name=='third-sale-rounding':assert any(Decimal(s['components']['rounding_bridge'])!=0 for s in sales)
 if name.startswith('shared-asset'):
  positions=report['projection']['securities_realization_binding']['aggregate_asset_positions'];assert positions['debt000']['opening']=='19950.00' and positions['debt000']['titles']==2;observations['aggregate_asset_positions']=positions
 for event,path in report['event_outputs'].items():
  v=report['event_validations'][event];assert sha(ROOT/path)==v['xml_sha256'] and v['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and len(v['errors'])==1 and 'Signature' in v['errors'][0]['message'];bodies+=1
 evidence[name]={'snapshot':sp.relative_to(ROOT).as_posix(),'snapshot_sha256':sha(sp),'monthly_report':rp.relative_to(ROOT).as_posix(),'monthly_report_sha256':sha(rp),'bundle_id':report['bundle_id'],'observations':observations,'xml_bodies_checked':len(report['event_outputs'])};del snapshot,report,base,parents,sales;gc.collect()
requirements=[('partial_and_full_disposal',['shared-asset-partial','shared-asset-complete']),('buy_and_sell_same_month',['private-buy-sell']),('acquisition_cost_differs_from_book',['public-prior-fair']),('prior_period_income',['historical-separate-assets']),('accounting_and_fiscal_gain_differ',['public-prior-fair']),('fair_value_subledger',['public-prior-fair']),('opposing_gains_and_losses',['private-opposing']),('exclusive_funds',['public-fund']),('multiple_titles_and_accounts',['historical-separate-assets','shared-asset-asymmetric']),('rounding_and_three_period_chain',['third-sale-rounding'])]
source=ROOT/'docs/fiscal/phase4b-next-scope-tvm-realization.md';save(E/'requirements-evidence-matrix.json',{'status':'HETEROGENEOUS_SCOPE28_EVIDENCE_INSPECTED_NOT_UNIFIED_ACCEPTANCE','original_scope':source.relative_to(ROOT).as_posix(),'original_scope_sha256':sha(source),'accepted_predecessor_report':prior.relative_to(ROOT).as_posix(),'accepted_predecessor_report_sha256':sha(prior),'requirements':[{'requirement':r,'cases':c,'individual_evidence_checked':True,'unified_candidate_proven':False} for r,c in requirements],'cases':evidence,'xml_bodies_checked':bodies,'pending':['Single versioned candidate covering the complete positive and negative realization corpus','Explicit current-period daily memory selection for July, August and September without substituting rate data','Classification and projection of public new produced return at disposal; cannot assume ordinary D1101 code or silently rewrite book memory','Complete scope28 acceptance audit and broader phase4B.8 requirements'],'scope28_completed':False,'phase4b_completed':False,'production_ready':False});print(json.dumps({'requirements':len(requirements),'distinct_evidence_cases':len(evidence),'xml_bodies_checked':bodies,'unified_candidate_proven':False}))
