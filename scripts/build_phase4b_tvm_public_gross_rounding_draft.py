"""Reproduce draft30 from frozen first-acquisition inputs and independent decimal oracles."""
import copy,json
from pathlib import Path
from decimal import Decimal,ROUND_HALF_EVEN
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.08-28-draft30'
E=ROOT/'storage/app/phase4b-realization-public-gross-rounding-draft-20261008-28';S=F/('scenarios/tvm-public-gross-rounding-'+V)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:
  with p.open('x',encoding='utf-8') as out:out.write(json.dumps(v,ensure_ascii=False,indent=2)+'\n')
b=read(F/'bundles/consumption-phase4b-2026.10.08-28-draft29.json');c=read(F/b['resource_paths']['rtc_code_catalog'])
known=read(F/('bundles/consumption-phase4b-'+V+'.json'))['knowledge_available_at']
c['version']=V;q=c['financial_operation']['base']['public_offer_debt'];q['published_at']=known
q['gross_rounding_reconciliation_policy']='PROVIDED_PUBLIC_GROSS_ROUNDING_JOURNAL_8_2'
a=q['sale_recognition_policy']['analysis'];a['published_at']=known;a['history_policy']['published_at']=known
a['sale_book_rounding_policy']='PROVED_AVERAGE_CARRYING_RELEASE_HALF_EVEN_8_2';q['sale_recognition_policy']['published_at']=known
c['financial_operation']['base']['securities_realization'].update(published_at=known,analysis=copy.deepcopy(a),public_offer_debt=copy.deepcopy(q))
binding=c['dere_monthly_closing_projection']['securities_realization_binding'];binding['published_at']=known;binding['gross_rounding_journal_mode']=q['gross_rounding_reconciliation_policy']
c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(q)
cp='catalogs/rtc-codes-'+V+'.json';save(F/cp,c)
ctrl=read(F/b['production_controls']['path']);ctrl['version']=V;ct='production/phase4b-controls-'+V+'.json';save(F/ct,ctrl)
b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,knowledge_available_at=known)
b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V;b['production_controls']={'path':ct,'sha256':digest(ctrl)}
save(F/('bundles/consumption-phase4b-'+V+'.json'),b)
for name,fixture in read(E/'initial-prepared-fixtures.json').items():
 f=copy.deepcopy(fixture);days=f['independent_oracle']['daily_memory'];curve=f['position']['contract_curve'];credit=sum((max(Decimal(d['appropriation']),0) for d in curve),Decimal(0));debit=sum((max(-Decimal(d['appropriation']),0) for d in curve),Decimal(0))
 rc=credit.quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN);rd=debit.quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
 delta=Decimal(f['independent_oracle']['monthly']['vVarMensal'])-(rc-rd)
 f['gross_rounding_oracle']={'raw_gross_credit':format(credit,'.8f'),'raw_gross_debit':format(debit,'.8f'),'rounded_gross_credit':format(rc,'.2f'),'rounded_gross_debit':format(rd,'.2f'),'signed_adjustment':format(delta,'.2f')}
 sale=next(d for d in f['position']['operations'] if d['kind']=='SELL');after=next(d for d in days if d['date']==sale['date'])
 qty=Decimal(sale['quantity'])+Decimal(after['quantity']);before=Decimal(sale['book_amount'])+Decimal(after['book_balance'])
 average=(before*Decimal(sale['quantity'])/qty).quantize(Decimal('.00000001'),rounding=ROUND_HALF_EVEN)
 actual=average.quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN);adjustment=actual-average
 if adjustment:f['sale_rounding_oracle']={'unrounded_average_release':format(average,'.8f'),'actual_book_release':format(actual,'.2f'),'signed_adjustment':format(adjustment,'.8f')}
 save(S/name,f)
print('Draft30 bundle, controls and eight frozen independent fixtures reproduced.')
