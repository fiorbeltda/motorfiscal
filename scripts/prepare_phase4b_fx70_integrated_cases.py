"""Own policy-origin scenarios preserve old numerical decisions, and refuse unqualified material origins."""
from pathlib import Path
import copy,json
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'resources/fiscal'
E=ROOT/'storage/app/phase4b-fx70-origin-preparation-20261014'
bundle=json.loads((F/'bundles/consumption-phase4b-2026.10.14-70-draft1.json').read_text(encoding='utf-8'))
assert (E/'material-origin-installation.json').exists()
directory=F/'scenarios/import-fx-origin-2026.10.14-70-draft1';directory.mkdir(exist_ok=True)
records=[]
for name in ['usd-direct','usd-weekend','usd-partial','brl-adjustment','eur-direct','aud-direct','missing-proof','proof-hash','wrong-operation']:
    source=F/('scenarios/import-fx-2026.10.14-68-draft1-v3/'+name+'.json')
    scenario=json.loads(source.read_text(encoding='utf-8'))
    scenario.update(scenario_id='SYNTHETIC-FX70-ORIGIN-'+name,knowledge_at=bundle['knowledge_available_at'],
        rule_bundle_id=bundle['bundle_id'],catalog_versions=bundle['catalog_versions'])
    expected={'missing-proof':'RTC_IMPORT_FX_TRANSACTION_FACTS_REQUIRED','proof-hash':'RTC_IMPORT_FX_TRANSACTION_PROOF_HASH_CONFLICT',
        'wrong-operation':'RTC_IMPORT_FX_TRANSACTION_OPERATION_OR_DOCUMENT_BINDING_CONFLICT'}.get(name,
        'RTC_IMPORT_FX_MATERIAL_QUOTE_ORIGIN_UNQUALIFIED' if name in ['eur-direct','aud-direct'] else 'RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN')
    path=directory/(name+'.json')
    with path.open('x',encoding='utf-8') as f:json.dump(scenario,f,ensure_ascii=False,indent=2)
    records.append({'name':name,'path':path.relative_to(ROOT).as_posix(),'expected_reason':expected,
        'nominal_path':name in ['usd-direct','usd-weekend','usd-partial','brl-adjustment']})
with (E/'integrated-origin-case-manifest.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'NINE_OWN_MATERIAL_ORIGIN_SCENARIOS_PREPARED','cases':records,'production_ready':False},f,indent=2)
print('PASS:nine own70 integrated origin-policy scenarios prepared')
