"""Freeze amortization and prior-return coupon continuity, preserving every predecessor."""
import copy, hashlib, json
from datetime import datetime, timezone
from pathlib import Path
from dere_daily_memory_oracle import oracle, seal, digest
ROOT = Path(__file__).resolve().parents[1]
F = ROOT/'resources/fiscal'
V = '2026.10.06-28-draft5'
E = ROOT/'storage/app/phase4b-realization-other-releases-draft-20261006-28'
S = F/f'scenarios/tvm-other-releases-{V}'
E.mkdir(parents=True, exist_ok=True)
S.mkdir(parents=True, exist_ok=True)
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def save(p, value):
    if p.exists(): assert read(p) == value, p
    else:
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text(json.dumps(value, ensure_ascii=False, indent=2)+'\n', encoding='utf-8')
if not (E/'historical-before.json').exists():
    baseline = {}
    for folder in [F, ROOT/'storage/app']:
        for p in folder.rglob('*'):
            if p.is_file() and E not in p.parents and V not in str(p) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']:
                baseline[p.relative_to(ROOT).as_posix()] = hashlib.sha256(p.read_bytes()).hexdigest()
    save(E/'historical-before.json', baseline)
b = read(F/'bundles/consumption-phase4b-2026.10.06-28-draft4.json')
target = F/f'bundles/consumption-phase4b-{V}.json'
known = read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
c = read(F/b['resource_paths']['rtc_code_catalog'])
c['version'] = V
c['financial_operation']['base']['securities_realization'].update(published_at=known, other_release_realization_mode='PROVEN_AMORTIZATION_WITH_PRIOR_HISTORY_AND_SUBACCOUNT')
c['dere_monthly_closing_projection']['securities_realization_binding']['published_at'] = known
catalog_path = f'catalogs/rtc-codes-{V}.json'
save(F/catalog_path, c)
control = read(F/b['production_controls']['path'])
control['version'] = V
for row in control['controls']:
    if row['id'] == '4B.7.DOCUMENT': row['gap'] += ' Draft5: amortizacao com realizacao provada; cupom de rendimento anterior sem nova inclusao; escopo28 integral pendente.'
control_path = f'production/phase4b-controls-{V}.json'
save(F/control_path, control)
b.update(predecessor_bundle_id=b['bundle_id'], bundle_id='CONSUMPTION-PHASE4B-'+V, version=V, knowledge_available_at=known)
b['resource_paths']['rtc_code_catalog'] = catalog_path
b['content_hashes']['rtc_code_catalog'] = digest(c)
b['catalog_versions']['rtc_codes'] = V
b['production_controls'] = {'path':control_path, 'sha256':digest(control)}
save(target,b)
ledger = read(F/'scenarios/tvm-base-2026.10.06-28-draft/mixed-realization-financial.json')['facts']['rtc.financial_ledger'][0]
original = ledger['entries'][4]['securities_realization']['analysis_input']['position']
zero = dict.fromkeys(['prior_produced_return','current_produced_return_already_in_curve','deferred_fair_value_realized','rounding_bridge'],'0')
for name,first,sale,prior,fair,closing_prior,closing_fair,revenue in [
 ('amortization-then-sale', {'id':'amort1','date':'2026-09-10','sequence':1,'kind':'AMORTIZATION','book_amount':'2060.00','acquisition_cost_reduction':'2000'},
  {'id':'sale1','date':'2026-09-14','sequence':1,'kind':'SELL','quantity':'375','book_amount':'3090.00','sale_proceeds':'3130.00'}, '20','40','50','100','290.00'),
 ('prior-coupon-then-sale', {'id':'coupon1','date':'2026-09-10','sequence':1,'kind':'COUPON','book_amount':'50.00'},
  {'id':'sale1','date':'2026-09-14','sequence':1,'kind':'SELL','quantity':'400','book_amount':'4100.00','sale_proceeds':'4200.00'}, '50','0','30','120','330.00')]:
    p = copy.deepcopy(original)
    p['operations'] = [first, sale]
    p['appropriations'] = {'2026-09-30':'150'}
    curve, monthly, result = oracle(p,c['dere_debt_daily_memory_calculation']['series'])
    p.pop('appropriations');p['contract_curve']=curve;p['declared_monthly']=monthly;seal(p)
    sale_parts = {**zero, 'prior_produced_return':'30' if name.startswith('amort') else '20',
                  'deferred_fair_value_realized':'60' if name.startswith('amort') else '80',
                  'new_produced_return_at_sale':'40' if name.startswith('amort') else '100','new_fair_value_change_at_sale':'0'}
    inventory = {'opening':{**zero,'prior_produced_return':'100','deferred_fair_value_realized':'200'},
      'closing':{**zero,'prior_produced_return':closing_prior,'deferred_fair_value_realized':closing_fair,'current_produced_return_already_in_curve':'150'},
      'closing_fiscal_components':{'assessed_produced_return':closing_prior,'excluded_art195_return':'0','removed_pis_cofins':'0'},
      'other_releases':[{'operation_id':first['id'],'components':{**zero,'prior_produced_return':prior,'deferred_fair_value_realized':fair},
        'fiscal_components':{'assessed_produced_return':prior,'excluded_art195_return':'0','removed_pis_cofins':'0'}}]}
    save(S/(name+'.json'), {'position':p,'independent_oracle':result,'sale_components':sale_parts,'recognition_inventory':inventory,
                          'expected':{'revenue':revenue,'deductions':'0.00','net_base':'990.00' if name.startswith('amort') else '1030.00'}})
save(E/'research.json', {'legal_url':'https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html',
 'legal_source_sha256':c['financial_operation']['base']['securities_realization']['source_hash'],
 'rule':'Art192 paragraph6: actual realization requires separate evidence; receiving a coupon is not automatically realization of deferred fair value.',
 'accounting_method':'Only provided and independently proved journals; synthetic corpus; no automatic postings or normative homologation',
 'scope28_completed':False})
print('Draft5 and two independent continuity fixtures frozen.')
