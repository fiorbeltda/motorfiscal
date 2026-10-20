"""Bind historical openings to an actual realized closing; no incomplete-month tax calculation."""
import copy
import json
from pathlib import Path
from dere_daily_memory_oracle import digest, proof

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = ROOT / 'storage/app/phase4b-realization-public-mixed-history-draft-20261008-28'
OUT = F / 'scenarios/tvm-public-realized-closing-preparation-20261008'
OUT.mkdir(parents=True, exist_ok=True)

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def save(path, value):
    if path.exists():
        assert read(path) == value, path
    else:
        with path.open('x', encoding='utf-8') as stream:
            stream.write(json.dumps(value, ensure_ascii=False, indent=2) + '\n')

catalog = read(F / 'catalogs/rtc-codes-2026.10.08-28-draft21.json')
policy = copy.deepcopy(catalog['financial_operation']['base']['securities_realization']['analysis']['history_policy'])
policy.pop('public_position_chain_policy')
policy['public_realized_closing_policy'] = 'REPLAYED_PUBLIC_REALIZATION_CLOSING_AND_RETAINED_CURRENT_CURVE'
policy['daily_memory_policies']['2026-09'] = copy.deepcopy(catalog['dere_debt_daily_memory_calculation'])
manifest = read(F / 'bundles/consumption-phase4b-2026.10.08-28-draft21.json')
policy['permitted_bundle_manifest_hashes'].append(digest(manifest))
save(F / 'catalogs/tvm-public-realized-closing-policy-draft-20261008.json', policy)

expected = {
    'negative-partial': ['87.00000000', '0.00000000', '3.00000000', '0.00000000', '-150.00000000'],
    'negative-complete': ['0.00000000', '0.00000000', '0.00000000', '0.00000000', '0.00000000'],
    'positive-partial': ['92.58600000', '84.41400000', '3.00000000', '0.00000000', '-90.00000000'],
    'zero-partial': ['87.00000000', '0.00000000', '3.00000000', '0.00000000', '-60.00000000'],
    'fund-partial': ['87.00000000', '0.00000000', '3.00000000', '0.00000000', '-150.00000000'],
}
keys = ['assessed_produced_return', 'excluded_art195_return', 'removed_pis_cofins', 'deferred_fair_value', 'unoffset_public_title_loss']
for case, amounts in expected.items():
    snapshot = read(E / (case + '-snapshot.json'))
    base = next(t['base'] for t in snapshot['result']['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE')
    entry = next(e for e in base['entries'] if e['id']=='yield1')
    position = entry['public_offer_debt']['position']
    memory = entry['d2101_binding']['calculation']
    title = entry['public_offer_debt']['title']
    opening = {'quantity':memory['closing_quantity'],'unit_cost':memory['closing_unit_cost'],
               'acquisition_cost':memory['closing_acquisition_cost'],'book_balance':memory['monthly']['vSaldoContFin']}
    scope = {'supplier_id':position['supplier_id'],'period':'2026-10','title':title,'opening':opening}
    closing = {'contract':'TVM-RECOGNITION-CLOSING-MEMORY-1','position':position,'result':memory,
               'known_at':snapshot['input']['knowledge_at'],'policy_hash':digest(policy['daily_memory_policies']['2026-09'])}
    pool = dict(zip(keys, amounts))
    assignment = proof({'supplier_id':position['supplier_id'],'current_period':'2026-10','title':title,'snapshot_hash':digest(snapshot),
                        'financial_entry_id':'yield1','retained_share':'1'}, 'SYNTHETIC.REALIZED.CLOSING.ASSIGN.'+case)
    reference = {'source_key':digest({'supplier_id':position['supplier_id'],'period':'2026-09','document_id':entry['document_id'],'financial_entry_id':'yield1'}),
                 'snapshot_hash':digest(snapshot),'decision_hash':snapshot['result']['decision_hash'],'snapshot_status':snapshot['result']['status'],
                 'period':'2026-09','entry_id':'yield1','retained_share':'1','available':pool,'retained':pool,'assignment_evidence_hash':assignment['source_hash']}
    history = {'closing_memory':closing,'fiscal_sources':[{'snapshot':snapshot,'financial_entry_id':'yield1','retained_share':'1','assignment_evidence':assignment}],
               'complete_evidence':proof({'scope':scope,'sources':[reference],'closing_memory_hash':digest(closing),'opening_fiscal_components':pool}, 'SYNTHETIC.REALIZED.CLOSING.COMPLETE.'+case)}
    save(OUT / (case + '.json'), {'scope':scope,'recognition_history':history,'expected_components':pool,'bundle_activated':False,'current_month_tax_calculated':False})
print('Five actual September realized closing positions prepared as historical openings only.')
