"""Replay consecutive public yield months before allocating separate signed history pools."""
import copy
import hashlib
import json
import subprocess
from datetime import datetime, timezone
from decimal import Decimal
from pathlib import Path
from dere_daily_memory_oracle import digest, oracle, proof, seal

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
J = '2026.10.08-28-draft19'
A = '2026.10.08-28-draft20'
V = '2026.10.08-28-draft21'
E = ROOT / 'storage/app/phase4b-realization-public-mixed-history-draft-20261008-28'
S = F / ('scenarios/tvm-public-mixed-history-' + V)
E.mkdir(parents=True, exist_ok=True)
S.mkdir(parents=True, exist_ok=True)

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def save(path, value):
    if path.exists():
        assert read(path) == value, path
    else:
        path.parent.mkdir(parents=True, exist_ok=True)
        with path.open('x', encoding='utf-8') as stream:
            stream.write(json.dumps(value, ensure_ascii=False, indent=2) + '\n')

def run(args):
    result = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php', 'artisan', *args], cwd=ROOT, capture_output=True, encoding='utf-8')
    assert result.returncode == 0, result.stdout + result.stderr
    return json.loads(result.stdout)

if not (E / 'historical-before.json').exists():
    baseline = {}
    for folder in [F, ROOT / 'storage/app']:
        for path in folder.rglob('*'):
            if path.is_file() and E not in path.parents and not any(v in str(path) for v in [J, A, V]) and path.suffix in ['.json', '.xml', '.pdf', '.zip', '.xsd']:
                baseline[path.relative_to(ROOT).as_posix()] = hashlib.sha256(path.read_bytes()).hexdigest()
    save(E / 'historical-before.json', baseline)

july = read(F / 'catalogs/tvm-public-chain-july-policy-draft-20261008.json')
source = {'id': 'BCB_SGS11_OBSERVED_20261008_202607', 'file': july['official_data_file'], 'sha256': july['official_data_sha256'],
          'url': 'https://api.bcb.gov.br/dados/serie/bcdata.sgs.11/dados?formato=csv&dataInicial=01/07/2026&dataFinal=31/07/2026',
          'retrieved_on': '2026-10-08', 'published_on': None,
          'publication_verification': 'OFFICIAL_RESPONSE_OBSERVED_NOT_A_GENERAL_FISCAL_VALIDITY_DATE',
          'proven_effective_scope': 'Observed July2026 daily SGS11 rates only; synthetic positions and historical recognition assignments do not establish taxpayer approval or prior reception.'}

def bundle(version, predecessor, policy=None, prior_manifests=None):
    manifest = read(F / ('bundles/consumption-phase4b-' + predecessor + '.json'))
    catalog = read(F / manifest['resource_paths']['rtc_code_catalog'])
    target = F / ('bundles/consumption-phase4b-' + version + '.json')
    known = read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
    catalog['version'] = version
    qualification = catalog['financial_operation']['base']['public_offer_debt']
    qualification['published_at'] = known
    if policy:
        qualification['daily_memory_calculation'] = copy.deepcopy(policy)
        catalog['dere_debt_daily_memory_calculation'] = copy.deepcopy(policy)
    if prior_manifests:
        analysis = qualification['sale_recognition_policy']['analysis']
        analysis['published_at'] = known
        history = analysis['history_policy']
        history.update(published_at=known, public_position_chain_policy='REPLAYED_CONSECUTIVE_PUBLIC_YIELD_POSITION_CHAIN')
        history['daily_memory_policies']['2026-07'] = copy.deepcopy(july)
        history['permitted_bundle_manifest_hashes'] += [digest(m) for m in prior_manifests]
        catalog['financial_operation']['base']['securities_realization'].update(published_at=known, analysis=copy.deepcopy(analysis), public_offer_debt=copy.deepcopy(qualification))
        catalog['dere_monthly_closing_projection']['securities_realization_binding']['published_at'] = known
    catalog['dere_monthly_closing_projection']['debt_yield_projection']['qualification'] = copy.deepcopy(qualification)
    catalog_path = 'catalogs/rtc-codes-' + version + '.json'
    save(F / catalog_path, catalog)
    controls = read(F / manifest['production_controls']['path'])
    controls['version'] = version
    controls_path = 'production/phase4b-controls-' + version + '.json'
    save(F / controls_path, controls)
    manifest.update(predecessor_bundle_id=manifest['bundle_id'], bundle_id='CONSUMPTION-PHASE4B-' + version, version=version, knowledge_available_at=known)
    manifest['resource_paths']['rtc_code_catalog'] = catalog_path
    manifest['content_hashes']['rtc_code_catalog'] = digest(catalog)
    manifest['catalog_versions']['rtc_codes'] = version
    manifest['production_controls'] = {'path': controls_path, 'sha256': digest(controls)}
    if source['id'] not in [r['id'] for r in manifest['official_sources']]:
        manifest['official_sources'].append(source)
    save(target, manifest)
    return manifest, catalog

jm, jc = bundle(J, '2026.10.08-28-draft17', july)
am, ac = bundle(A, '2026.10.08-28-draft17')
cm, cc = bundle(V, '2026.10.08-28-draft18', prior_manifests=[jm, am])
variants = [('net-negative', 'direct', '100', '-250'), ('net-positive', 'direct', '-150', '250'),
            ('net-zero', 'direct', '100', '-100'), ('fund-net-negative', 'fund', '100', '-250')]
keys = ['assessed_produced_return', 'excluded_art195_return', 'removed_pis_cofins', 'deferred_fair_value', 'unoffset_public_title_loss']
chains = {}
for name, route, july_amount, august_amount in variants:
    chain = []
    for period, amount, manifest, catalog in [('2026-07', july_amount, jm, jc), ('2026-08', august_amount, am, ac)]:
        scenario = read(F / ('scenarios/tvm-public-negative-yield-2026.10.08-28-draft18/prior-' + route + '-financial.json'))
        end = period + '-31'
        scenario.update(scenario_id='SYNTHETIC-public-mixed-' + name + '-' + period, competence_date=end,
                        knowledge_at=manifest['knowledge_available_at'], rule_bundle_id=manifest['bundle_id'], catalog_versions=manifest['catalog_versions'])
        scenario['facts']['rtc.operation_date'] = end
        ledger = scenario['facts']['rtc.financial_ledger'][0]
        ledger.update(period_start=period + '-01', period_end=end)
        entry = ledger['entries'][0]
        detail = entry['public_offer_debt']
        position = copy.deepcopy(read(F / ('scenarios/tvm-public-chain-preparation-20261008/july-' + ('positive' if Decimal(amount) > 0 else 'negative') + '.json'))['position'])
        position.update(period=period, first_acquisition=not chain)
        position.update(idTitulo=detail['title']['idTitulo'], cCta=detail['title']['cCta'])
        if chain:
            previous = chain[-1]['memory']
            position['opening'] = {'quantity': previous['closing_quantity'], 'unit_cost': previous['closing_unit_cost'],
                                   'acquisition_cost': previous['closing_acquisition_cost'], 'book_balance': previous['monthly']['vSaldoContFin']}
            position['operations'] = []
            position['previous_position_evidence'] = proof({'supplier_id':position['supplier_id'], 'idTitulo':position['idTitulo'], 'cCta':position['cCta'], 'period':'2026-07', 'closing':position['opening']}, 'SYNTHETIC.CHAIN.PREVIOUS.' + name, '2026-07-01', '2026-07-31')
        position['method_evidence'] = proof({'contract':july['contract'], 'method':position['method']}, 'SYNTHETIC.CHAIN.METHOD.' + name, period + '-01', end)
        position['appropriations'] = {end:amount}
        policy = catalog['financial_operation']['base']['public_offer_debt']['daily_memory_calculation']
        curve, monthly, independent = oracle(position, policy['series'])
        position.pop('appropriations')
        position.update(contract_curve=curve, declared_monthly=monthly)
        position.pop('memory_evidence', None)
        position['memory_evidence'] = proof(position, 'SYNTHETIC.CHAIN.MEMORY.' + name + period, period + '-01', end)
        entry.update(recognized_on=end, amount=format(abs(Decimal(amount)), '.2f'), document_id='SYNTHETIC.mixed.' + name + '.' + period)
        detail['position'] = position
        detail['eligibility_evidence'] = proof({'supplier_id':ledger['supplier_id'], 'period_end':end, 'title':detail['title'], 'financial_entry_id':'yield1',
            'document_id':entry['document_id'], 'amount':entry['amount'], 'result_account_code':detail['result_account_code'], 'position_input_hash':digest(position), 'position_mode':'DAILY_MEMORY'}, 'SYNTHETIC.CHAIN.ELIGIBILITY.' + name, period + '-01', end)
        scenario_path = S / (name + '-' + period + '-financial.json')
        save(scenario_path, scenario)
        snapshot_path = E / (name + '-' + period + '-snapshot.json')
        args = ['fiscal:rtc:run', scenario_path.relative_to(ROOT).as_posix(), '--bundle-manifest=bundles/consumption-phase4b-' + manifest['version'] + '.json']
        if not snapshot_path.exists():
            args += ['--snapshot=' + snapshot_path.relative_to(ROOT).as_posix()]
        result = run(args)
        assert result['reason_codes'] == ['RTC_FINANCIAL_DOCUMENT_PENDING'], result.get('reason_codes')
        save(E / (name + '-' + period + '-result.json'), result)
        base = next(t['base'] for t in result['trace'] if t['stage'] == 'FINANCIAL_OPERATION_AND_BASE')
        binding = base['entries'][0]['d2101_binding']
        memory = binding['calculation']
        for key, value in independent.items():
            if key != 'daily_memory':
                assert memory[key] == value, (key, memory[key], value)
        available = dict.fromkeys(keys, '0.00000000')
        for key, field in [('assessed_produced_return', 'taxable_amount'), ('excluded_art195_return', 'art195_excess'), ('removed_pis_cofins', 'pis_deduction_applied')]:
            available[key] = format(Decimal(binding[field]), '.8f')
        if Decimal(amount) < 0:
            available['unoffset_public_title_loss'] = format(-Decimal(binding['unoffset_title_loss']), '.8f')
        assert sum(map(Decimal, available.values())) == Decimal(amount)
        assert base['net_base'] == binding['taxable_amount']
        chain.append({'period':period, 'position':position, 'memory':memory, 'entry':entry, 'snapshot':read(snapshot_path), 'manifest':manifest,
                      'policy':policy, 'available':available, 'title':detail['title']})
    chains[name] = chain

zero = dict.fromkeys(['prior_produced_return', 'current_produced_return_already_in_curve', 'deferred_fair_value_realized', 'rounding_bridge'], '0')
for case, name, quantity in [('negative-partial','net-negative',400), ('negative-complete','net-negative',1000),
                             ('positive-partial','net-positive',400), ('zero-partial','net-zero',400), ('fund-partial','fund-net-negative',400)]:
    chain = chains[name]
    last = chain[-1]
    position = copy.deepcopy(last['position'])
    position.update(period='2026-09', first_acquisition=False, vPisCofins='5.00')
    position['opening'] = {'quantity':last['memory']['closing_quantity'], 'unit_cost':last['memory']['closing_unit_cost'],
                           'acquisition_cost':last['memory']['closing_acquisition_cost'], 'book_balance':last['memory']['monthly']['vSaldoContFin']}
    position['previous_position_evidence'] = proof({'supplier_id':position['supplier_id'], 'idTitulo':position['idTitulo'], 'cCta':position['cCta'], 'period':'2026-08', 'closing':position['opening']}, 'SYNTHETIC.MIXED.CURRENT.PREVIOUS', '2026-08-01', '2026-08-31')
    position['method_evidence'] = proof({'contract':july['contract'], 'method':position['method']}, 'SYNTHETIC.MIXED.CURRENT.METHOD')
    book = (Decimal(position['opening']['book_balance']) + 50) * Decimal(quantity) / 1000
    position['operations'] = [{'id':'sale1','date':'2026-09-14','sequence':1,'kind':'SELL','quantity':str(quantity),'book_amount':format(book,'.2f'),'sale_proceeds':format(book,'.2f')}]
    position['appropriations'] = {'2026-09-10':'50'}
    curve, monthly, independent = oracle(position, cc['dere_debt_daily_memory_calculation']['series'])
    position.pop('appropriations')
    position.update(contract_curve=curve, declared_monthly=monthly)
    seal(position)
    scope = {'supplier_id':position['supplier_id'],'period':'2026-09','title':last['title'],'opening':position['opening']}
    closing = {'contract':'TVM-RECOGNITION-CLOSING-MEMORY-1','position':last['position'],'result':last['memory'], 'known_at':last['manifest']['knowledge_available_at'],'policy_hash':digest(last['policy'])}
    pool = {key:format(sum(Decimal(r['available'][key]) for r in chain),'.8f') for key in keys}
    sources, references = [], []
    for record in chain:
        assignment = proof({'supplier_id':position['supplier_id'],'current_period':'2026-09','title':last['title'],'snapshot_hash':digest(record['snapshot']),'financial_entry_id':'yield1','retained_share':'1'}, 'SYNTHETIC.MIXED.ASSIGN.' + record['period'], record['period'] + '-01', record['period'] + '-31')
        sources.append({'snapshot':record['snapshot'],'financial_entry_id':'yield1','retained_share':'1','assignment_evidence':assignment})
        references.append({'source_key':digest({'supplier_id':position['supplier_id'],'period':record['period'],'document_id':record['entry']['document_id'],'financial_entry_id':'yield1'}),
            'snapshot_hash':digest(record['snapshot']),'decision_hash':record['snapshot']['result']['decision_hash'],'snapshot_status':record['snapshot']['result']['status'],
            'period':record['period'],'entry_id':'yield1','retained_share':'1','available':record['available'],'retained':record['available'],'assignment_evidence_hash':assignment['source_hash']})
    references.sort(key=lambda r:r['source_key'])
    history = {'closing_memory':closing,'fiscal_sources':sources,'complete_evidence':proof({'scope':scope,'sources':references,'closing_memory_hash':digest(closing),'opening_fiscal_components':pool}, 'SYNTHETIC.MIXED.COMPLETE','2026-08-01','2026-08-31')}
    fraction = Decimal(quantity) / 1000
    remaining = 1 - fraction
    prior = sum(Decimal(pool[key]) for key in keys)
    save(S / (case + '-current.json'), {'position':position,'title':last['title'],'recognition_history':history,'independent_oracle':independent,
        'components':{'sale1':{**zero,'prior_produced_return':format(prior*fraction,'.8f'),'current_produced_return_already_in_curve':str(Decimal(50)*fraction),'new_produced_return_at_sale':'0','new_fair_value_change_at_sale':'0'}},
        'recognition_inventory':{'opening':{**zero,'prior_produced_return':format(prior,'.8f')},'closing':{**zero,'prior_produced_return':format(prior*remaining,'.8f'),'current_produced_return_already_in_curve':str(Decimal(50)*remaining)},'other_releases':[],
            'closing_fiscal_components':{key:format(Decimal(pool[key])*remaining,'.8f') for key in keys if key!='deferred_fair_value'}},
        'expected':{'revenue':'0.00','deductions':'0.00','base':format(Decimal(700)+Decimal(monthly['vApur']),'.2f'),'auxiliary_base':monthly['vApur'],'fair_revenue':'0.00','fair_deductions':'0.00','opening_fiscal_components':pool}})
print('Eight prior decisions and five current mixed-history fixtures preserved.')
