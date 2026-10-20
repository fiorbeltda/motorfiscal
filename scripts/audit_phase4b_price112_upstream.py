"""Independently audit the expanded112 transport; not CLI/full-suite acceptance."""
from pathlib import Path
from datetime import datetime, timezone
from decimal import Decimal
from collections import Counter
import hashlib
import json
import re

R = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-price112-integration-20261019')
C = E.parent/'phase4b-dated108-integration-20261019/cli-v1'

def read(p): return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb') as f: return hashlib.file_digest(f, 'sha256').hexdigest()
def canonical(v): return hashlib.sha256(json.dumps(v, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()

checks = 0
def check(ok, label):
    global checks
    assert ok, label
    checks += 1

target = E/'price112-expanded-directed-validation.json'
assert not target.exists()
initial_path = E/'price112-initial-directed-validation.json'
check(sha(initial_path) == 'd991e06414407b5fcda7806252ab9ccad5db15bf884ab5853485cbff4dd9ef52', 'immutable initial audit')
initial = read(initial_path)
runtime = initial['runtime_hashes']
for p, h in runtime.items(): check(sha(R/p) == h, 'runtime:'+p)
for label in ['upstream-engine-v1', 'combined-directed-v4', 'phpstan-v3', 'pint-v4']:
    receipt = read(E/(label+'.json'))
    check(receipt['exit_code'] == 0 and not receipt['runtime_changed_during_check'], 'terminal stable:'+label)
    check(receipt['runtime_hashes'] == runtime, 'same runtime:'+label)
    for channel in ['stdout', 'stderr']:
        check(sha(E/(label+'.'+channel)) == receipt[channel+'_sha256'], 'receipt bytes:'+label+channel)
combined = (E/'combined-directed-v4.stdout').read_text(encoding='utf-8')
m = re.search(r'Tests:\s+1442 passed \((\d+) assertions\)', combined)
check(m is not None, '1442 directed tests terminal')
assertions = int(m[1])
check('248 passed (6818 assertions)' in (E/'upstream-engine-v1.stdout').read_text(encoding='utf-8'), '248 expanded runner tests')
check('[OK] No errors' in (E/'phpstan-v3.stdout').read_text(encoding='utf-8'), 'general static analysis')
pint = (E/'pint-v4.stdout').read_text(encoding='utf-8')
check(re.search(r'PASS.*\d+ files', pint) is not None, 'general style')
first = read(R/'tests/Fixtures/price112-scenario-expectations-v2.json')
new_path = R/'tests/Fixtures/price112-upstream-expectations.json'
new = read(new_path)
check(sha(new_path) == '74173f9bb038c3d5b171de5cc033802bad4c919e14ab2f314e89ddfeee4f1143', 'exclusive50 manifest')
seed = E/'upstream112-source-seed-v1.json'
check(sha(seed) == new['source_seed_sha256'] == 'aaf5e8d8df2c0f5fbf6a630638d0829e0b611423fe8260d467aff9ae84e1c5d2', 'seed exact')
original_inventory = initial['complete_source108_inventory']
expected_paths = {r['path'] for r in original_inventory}
actual_paths = [r['upstream108_path'] for r in first['cases']+new['cases'] if 'upstream108_path' in r]
check(len(actual_paths) == 58 and set(actual_paths) == expected_paths and set(Counter(actual_paths).values()) == {1}, 'all58sources once')
check(len(first['cases']) == 74 and len(new['cases']) == 50, '124 total scenarios')
captured = {r['path']:r for r in read(C/'dated108-cli-validation.json')['cases']}
positive = usd = bad_hashes = decimal_checks = 0
new_fields = ['rtc.assess_export_document_prices', 'rtc.export_contract_price_records',
              'rtc.export_price_composition_records', 'rtc.export_contract_price_proof_records',
              'rtc.export_price_composition_proof_records']
allowed = {'dated_pair_binding_hash', 'dated_document_hash', 'observed_at', 'source_hash'}
for row in new['cases']:
    label = row['name']
    check(sha(R/row['path']) == row['scenario_sha256'], 'new scenario:'+label)
    check(sha(R/row['upstream108_path']) == row['upstream108_sha256'], 'old scenario:'+label)
    current = read(R/row['path']); original = read(R/row['upstream108_path'])
    facts = current['facts']; old = original['facts']; restored = dict(facts)
    for key in new_fields: restored.pop(key)
    restored['rtc.assess_export_dated_documents'] = old['rtc.assess_export_dated_documents']
    restored['rtc.export_dated_dps_identity_records'] = old['rtc.export_dated_dps_identity_records']
    check(restored == old, 'every original business fact/RTC/FX/material/result preserved:'+label)
    old_snapshot = captured[row['upstream108_path']]
    snap = C/old_snapshot['rtc_snapshot']
    check(str(snap) == row['source_rtc_snapshot_path'], 'source snapshot lookup:'+label)
    check(sha(snap) == row['source_rtc_snapshot_sha256'] == old_snapshot['rtc_snapshot_sha256'], 'captured bytes:'+label)
    stages = {s['stage']:s for s in read(snap)['result']['trace']}
    for stage, key in [('PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION','captured108_nominal_stage'), ('PARTIAL_EXPORT_FX_TRANSACTION','captured108_fx_stage')]:
        check(stages.get(stage) == row[key], 'full captured stage oracle:'+label+stage)
    for before, after in zip(old['rtc.export_dated_dps_identity_records'], facts['rtc.export_dated_dps_identity_records'], strict=True):
        b = dict(before); a = dict(after); bp = b.pop('proof'); ap = a.pop('proof')
        check(a == b, 'identity record outside proof unchanged:'+label)
        check({k:v for k,v in bp.items() if k not in allowed} == {k:v for k,v in ap.items() if k not in allowed}, 'proof fields outside rebinding unchanged:'+label)
        valid = bp['source_hash'] == canonical({k:v for k,v in bp.items() if k != 'source_hash'})
        if not valid:
            bad_hashes += 1
            check(ap['source_hash'] == bp['source_hash'] and ap['source_hash'] != canonical({k:v for k,v in ap.items() if k != 'source_hash'}), 'corruption preserved:'+label)
        else:
            check(ap['source_hash'] == canonical({k:v for k,v in ap.items() if k != 'source_hash'}), 'valid reseal:'+label)
        if bp.get('observed_at') != original['knowledge_at']:
            check(ap.get('observed_at') == bp.get('observed_at'), 'nonstandard observation not repaired:'+label)
        else:
            check(ap.get('observed_at') in [bp['observed_at'], current['knowledge_at']], 'synthetic observation only:'+label)
    if row['composition_status'] == 'LOCAL_ISS_DOCUMENT_PRICES_COMPOSED':
        positive += 1
        usd += row['rtc_gross_currency'] == 'USD'
        check(row['source_dated_status'] == 'LOCAL_DATED_DPS_IDENTITY_PAIR_BOUND', 'fresh successful predecessor:'+label)
        amounts = [Decimal(p['service_price']) for p in facts['rtc.export_contract_price_records']]
        check(amounts == [Decimal('350'), Decimal('650')] and sum(amounts) == Decimal('1000'), 'independent new price claims:'+label)
        for i, comp in enumerate(facts['rtc.export_price_composition_records']):
            price = Decimal(comp['principal']) + sum((Decimal(a['amount']) for a in comp['service_additions']), Decimal(0)) - sum((Decimal(a['amount']) for a in comp['unconditional_discounts']), Decimal(0))
            candidate = price - sum((Decimal(a['amount']) for a in comp['conditional_discounts']), Decimal(0))
            check(price == Decimal(row['expected_iss_prices'][i]) and candidate == Decimal(row['expected_payment_candidates'][i]), 'decimal composition:'+label+str(i))
            decimal_checks += 2
        check(facts['rtc.export_contract_price_proof_records'][0]['value']['contract_service_price_total'] == '1000.00', 'new declaration total:'+label)
    else:
        check(row['source_dated_status'] != 'LOCAL_DATED_DPS_IDENTITY_PAIR_BOUND', 'original refusal not upgraded:'+label)
check((positive, usd, decimal_checks) == (23, 11, 92), '23new positive,11actualUSD,92decimal checks')
for p,h in runtime.items(): check(sha(R/p) == h, 'stable final runtime:'+p)
report = {'status':'PRICE112_EXPANDED_DIRECTED_VERIFIED_CLI_FULL_REGRESSION_PENDING',
    'created_at':datetime.now(timezone.utc).isoformat(), 'audit_checks':checks,
    'bundle':first['bundle'], 'bundle_sha256':initial['bundle_sha256'], 'runtime_hashes':runtime,
    'manifest_sha256':sha(new_path), 'engine_scenarios':124, 'original108_scenarios_transported':58,
    'new_positive_compositions':positive, 'new_successful_actual_rtc_usd':usd, 'new_decimal_checks':decimal_checks,
    'corrupted_identity_proof_hashes_preserved':bad_hashes,
    'new_permanent_tests':538, 'directed_tests':1442, 'directed_assertions':assertions,
    'quality_receipts':{label:sha(E/(label+'.json')) for label in ['combined-directed-v4','phpstan-v3','pint-v4']},
    'initial_audit_sha256':sha(initial_path), 'auditor_sha256':sha(Path(__file__)),
    'full_suite_expected_not_executed':7738, 'full_suite_complete':False, 'corpus_cli_complete':False,
    'acceptance_published':False, 'production_ready':False}
with target.open('x', encoding='utf-8') as f: json.dump(report, f, indent=2, ensure_ascii=False)
print(json.dumps({k:report[k] for k in ['status','audit_checks','directed_tests','directed_assertions','new_successful_actual_rtc_usd']}), flush=True)
