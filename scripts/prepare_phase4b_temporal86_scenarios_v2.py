"""Build immutable native integration scenarios; every fixture is synthetic and names its expected gate."""
from pathlib import Path
from copy import deepcopy
import json

ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'resources/fiscal'
E=ROOT/'storage/app/phase4b-temporal86-integration-preparation-20261015'
S=E/'scenarios-v2'
S.mkdir(exist_ok=False)
def read(path):return json.loads(path.read_text(encoding='utf-8'))
bundle=read(F/'bundles/consumption-phase4b-2026.10.15-86-draft1.json')
base=read(F/'scenarios/consumption-phase4b/regular-saas-2026.json')
base.update(knowledge_at='2026-10-15T23:00:00+00:00',competence_date='2026-10-12',rule_bundle_id=bundle['bundle_id'],catalog_versions=bundle['catalog_versions'])
base['facts'].update({'rtc.operation_date':'2026-10-12','service.canonical_id':'SOFTWARE_DEVELOPMENT','service.ctribnac_code':'010101',
    'rtc.assess_tax_point_events':True,'rtc.tax_point_operation_id':'OP86'})
op={'id':'OP86','document_id':'dps-1','mode':'ORDINARY','supply_kind':'INTANGIBLE_AVAILABILITY',
    'public_purchase':False,'cancelled':False,'total_amount':'1000.00','installments':[],
    'actual_supply':None,'fiscal_document':{'operation_id':'OP86','document_id':'dps-1','kind':'FISCAL_DOCUMENT_ISSUED',
        'date':'2026-10-05','observed_on':'2026-10-06','evidence_id':'FIXTURE.DOC86','local_integrity_checked':True,'expected_delivery_on':'2026-10-12'}}
base['facts']['rtc.tax_point_contexts']=[op]
cases=[]
def add(name,scenario=None,reason=None,status='VALID'):
    scenario=deepcopy(scenario if scenario is not None else base)
    scenario['scenario_id']='SYNTHETIC-TEMPORAL86-'+name
    with (S/(name+'.json')).open('x',encoding='utf-8') as stream:json.dump(scenario,stream,ensure_ascii=False,indent=2)
    cases.append({'case':name,'path':(S/(name+'.json')).relative_to(ROOT).as_posix(),'status':status,'reason':reason})
add('expected-date')
p=deepcopy(base);p['facts']['rtc.tax_point_contexts'][0]['fiscal_document']['expected_delivery_on']=None;p['facts']['rtc.operation_date']='2026-10-05'
add('issue-date-with-explicit-absence',p)
p=deepcopy(base);p['facts']['rtc.tax_point_contexts'][0]['actual_supply']={'operation_id':'OP86','document_id':'dps-1',
    'kind':'INTANGIBLE_AVAILABILITY','date':'2026-10-10','observed_on':'2026-10-11','evidence_id':'FIXTURE.ACTUAL86','rebuts_document_id':'dps-1'}
p['facts']['rtc.operation_date']='2026-10-10';add('contrary-material-proof',p)
p=deepcopy(base);p['facts']['rtc.operation_date']='2026-10-11';add('declared-date-conflict',p,'RTC_TAX_POINT_DECLARED_DATE_CONFLICT','ACTION_REQUIRED')
p=deepcopy(base);p['facts']['rtc.tax_point_contexts'][0]['fiscal_document']['expected_delivery_on']='2026-10-20'
add('future-presumption',p,'RTC_TAX_POINT_PRESUMED_SUPPLY_DATE_NOT_YET_REACHED','ACTION_REQUIRED')
p=deepcopy(base);p['facts']['rtc.tax_point_contexts'][0]['fiscal_document'].pop('expected_delivery_on')
add('unknown-is-not-absent',p,'RTC_TAX_POINT_EXPECTED_DELIVERY_FIELD_STATE_UNKNOWN','ACTION_REQUIRED')
p=deepcopy(base);p['facts']['rtc.tax_point_contexts'][0]['document_id']='OTHER'
add('wrong-operation-document',p,'RTC_TAX_POINT_OPERATION_DOCUMENT_CONFLICT','ACTION_REQUIRED')
p=deepcopy(base);p['facts']['rtc.tax_point_contexts'][0]['supply_kind']='SERVICE_COMPLETION'
add('wrong-supply-kind',p,'RTC_TAX_POINT_SUPPLY_KIND_CONFLICT','ACTION_REQUIRED')
advance=deepcopy(base)
advance['facts']['rtc.tax_point_contexts'][0]['installments']=[{'id':'1','amount':'300.00',
    'payment':{'operation_id':'OP86','installment_id':'1','kind':'PAYMENT','date':'2026-10-08','observed_on':'2026-10-09','evidence_id':'FIXTURE.PAY86','amount':'300.00'},
    'payment_document':{'operation_id':'OP86','installment_id':'1','document_id':'ADV86','date':'2026-10-07','observed_on':'2026-10-08','evidence_id':'FIXTURE.ADV86'}}]
add('advance-candidate-not-booked',advance,'RTC_TEMPORAL_PRIOR_ADVANCE_DEBITS_NOT_PROVEN','ACTION_REQUIRED')
p=deepcopy(advance);p['facts']['rtc.tax_point_contexts'][0]['installments'][0]['payment']['amount']='301.00'
add('payment-amount-conflict',p,'RTC_TAX_POINT_PAYMENT_KIND_OR_AMOUNT_CONFLICT','ACTION_REQUIRED')
p=deepcopy(advance);p['facts']['rtc.operation_date']='2027-01-02'
p['facts']['rtc.tax_point_contexts'][0]['fiscal_document']['expected_delivery_on']='2027-01-02'
p['knowledge_at']='2027-01-10T23:00:00+00:00';p['competence_date']='2027-01-02'
add('advance-no-future-cbs-default',p,'RTC_RATE_NOT_PROVEN:CBS','ACTION_REQUIRED')
continuous=deepcopy(base)
context=continuous['facts']['rtc.tax_point_contexts'][0];context.update(mode='CONTINUOUS')
context['installments']=[{'id':'MONTH1','amount':'1000.00','due':{'operation_id':'OP86','installment_id':'MONTH1',
    'kind':'INVOICE_CONSTITUTING_RIGHT','date':'2026-10-07','observed_on':'2026-10-08','evidence_id':'FIXTURE.RIGHT86'}}]
continuous['facts'].update({'rtc.tax_point_installment_id':'MONTH1','rtc.operation_date':'2026-10-07'})
add('continuous-right-constituted',continuous)
p=deepcopy(continuous);p['facts']['rtc.tax_point_installment_id']='OTHER'
add('continuous-missing-selected-parcel',p,'RTC_TAX_POINT_SELECTED_INSTALLMENT_GROSS_CONFLICT','ACTION_REQUIRED')
p=deepcopy(base)
for key in ['rtc.assess_tax_point_events','rtc.tax_point_contexts','rtc.tax_point_operation_id']:p['facts'].pop(key)
add('opt-in-absent-unchanged',p)
with (E/'temporal86-case-manifest-v2.json').open('x',encoding='utf-8') as stream:json.dump({'bundle':'bundles/consumption-phase4b-2026.10.15-86-draft1.json','cases':cases,'predecessor_fixture_note':'First fixtures used a canonical service absent from the eligible SaaS decision rule;corrected before any native run;both preserved.'},stream,indent=2)
print('PASS:14 exclusive synthetic scenarios with binding/refusal/continuous/advance/current-rate cases')
