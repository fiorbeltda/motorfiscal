"""Own integrated FX scenarios with source-bound synthetic transaction proof and distinct negative mutations."""
from pathlib import Path
from decimal import Decimal,ROUND_HALF_UP
import copy,json
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'resources/fiscal'
E=ROOT/'storage/app/phase4b-import-fx68-integration-20261013'
V='2026.10.14-68-draft1'
bundle=json.loads((F/('bundles/consumption-phase4b-'+V+'.json')).read_text(encoding='utf-8'))
catalog=json.loads((F/bundle['resource_paths']['rtc_code_catalog']).read_text(encoding='utf-8'))
original=json.loads((F/'scenarios/consumption-phase4b/import-digital-foreign-currency-unconverted-2026.json').read_text(encoding='utf-8'))
directory=F/'scenarios'/('import-fx-'+V+'-v3');directory.mkdir(exist_ok=True)
records=[]
cases=['usd-direct','eur-direct','aud-direct','usd-weekend','usd-partial','brl-adjustment','missing-proof','proof-hash',
    'wrong-document','wrong-operation','foreign-adjustment','future-proof','unknown-source','non-usd-weekend']
for name in cases:
    scenario=copy.deepcopy(original)
    currency='EUR' if name in ['eur-direct','non-usd-weekend'] else 'AUD' if name=='aud-direct' else 'USD'
    date='2026-09-28' if 'weekend' in name else '2026-09-25'
    rate={'USD':'5.1991' if date=='2026-09-28' else '5.1795','EUR':'5.928' if date=='2026-09-28' else '5.8906','AUD':'3.6329'}[currency]
    gross=str((Decimal('1000')*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_UP))
    scenario.update(scenario_id='SYNTHETIC-IMPORT-FX68-'+name,competence_date=date,knowledge_at=bundle['knowledge_available_at'],
        rule_bundle_id=bundle['bundle_id'],catalog_versions=bundle['catalog_versions'])
    facts=scenario['facts'];facts.update({'rtc.operation_date':date,'rtc.gross_currency':currency,'operation.amount':gross,
        'rtc.import_fx_source_id':currency,'rtc.operation_amount_reconciliation_id':'SYNTHETIC-FX68-'+name})
    value={'supplier_id':facts['rtc.supplier_id'],'supplier_foreign_nif':facts['rtc.supplier_foreign_nif'],
        'importer_id':facts['rtc.importer_id'],'document_id':facts['rtc.document_id'],'foreign_document_id':facts['rtc.import_document_id'],
        'transaction_evidence_id':facts['rtc.import_transaction_evidence_id'],'tax_point_date':date,
        'original_amount':facts['rtc.gross_amount'],'original_currency':currency,'converted_gross_brl':gross,
        'operation_amount_brl':gross,'quote_source_id':currency,'quote_source_sha256':catalog['ordinary_import_fx']['quote_sources'][currency]['sha256']}
    proof={'qualified':True,'scope':'SYNTHETIC_COMPLETE_IMPORT_FX_TRANSACTION_NOT_REMOTE_VERIFICATION',
        'evidence_id':facts['rtc.operation_amount_reconciliation_id'],'observed_at':bundle['knowledge_available_at'],
        'valid_from':'2026-09-01','valid_to':'2026-09-30','value':value}
    expected='RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN'
    if name=='usd-partial':
        facts.update({'rtc.import_brazil_share_percent':'25','rtc.import_allocation_method':'CONTRACTUAL',
            'rtc.import_allocation_evidence_id':'SYNTHETIC-25PCT-BRAZIL'})
    if name in ['brl-adjustment','foreign-adjustment']:
        facts['rtc.base_adjustments']=[{'kind':'INCLUDED_CHARGE','amount':'10.00','currency':'BRL' if name=='brl-adjustment' else 'USD','evidence_id':'SYNTHETIC-FX68-FEE'}]
        if name=='foreign-adjustment':expected='RTC_IMPORT_FX_ADJUSTMENT_BRL_UNIT_REQUIRED'
    if name=='wrong-document':proof['value']['foreign_document_id']='other';expected='RTC_IMPORT_FX_TRANSACTION_OPERATION_OR_DOCUMENT_BINDING_CONFLICT'
    if name=='wrong-operation':facts['operation.amount']='1000.00';proof['value']['operation_amount_brl']='1000.00';expected='RTC_IMPORT_FX_TRANSACTION_OPERATION_OR_DOCUMENT_BINDING_CONFLICT'
    if name=='future-proof':proof['observed_at']='2026-12-01T00:00:00+00:00';expected='RTC_IMPORT_FX_TRANSACTION_PROOF_NOT_KNOWN'
    proof['source_hash']=digest(proof);facts['rtc.import_fx_proof']=proof
    if name=='missing-proof':facts.pop('rtc.import_fx_proof');expected='RTC_IMPORT_FX_TRANSACTION_FACTS_REQUIRED'
    if name=='proof-hash':proof['source_hash']='0'*64;expected='RTC_IMPORT_FX_TRANSACTION_PROOF_HASH_CONFLICT'
    if name=='unknown-source':facts['rtc.import_fx_source_id']='UNKNOWN';expected='RTC_IMPORT_FX_TRUSTED_QUOTE_SOURCE_REQUIRED'
    if name=='non-usd-weekend':expected='RTC_IMPORT_FX_NON_USD_PREVIOUS_DAY_FALLBACK_RULE_UNQUALIFIED'
    if 'rtc.import_fx_proof' in facts: facts['rtc.import_fx_proof']=[facts['rtc.import_fx_proof']]
    path=directory/(name+'.json')
    with path.open('x',encoding='utf-8') as f:json.dump(scenario,f,ensure_ascii=False,indent=2)
    records.append({'name':name,'path':path.relative_to(ROOT).as_posix(),'expected_reason':expected,'converted_gross_brl':gross,
        'positive_fx_path':name in ['usd-direct','eur-direct','aud-direct','usd-weekend','usd-partial','brl-adjustment']})
with (E/'scenario-manifest-v3.json').open('x',encoding='utf-8') as f:json.dump({'cases':records,'production_ready':False},f,ensure_ascii=False,indent=2)
print('PASS:14 own68 integrated FX scenarios prepared, six nominal paths and eight explicit refusals')
