"""Preserve a daily policy derived from the exact official July SGS11 CSV."""
import copy
import csv
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path
from decimal import Decimal
from dere_daily_memory_oracle import digest, oracle, proof

ROOT = Path(__file__).resolve().parents[1]
FISCAL = ROOT / 'resources/fiscal'
SOURCE = FISCAL / 'sources/official/bcb-sgs11-observed-20261008-202607.csv'
TARGET = FISCAL / 'catalogs/tvm-public-chain-july-policy-draft-20261008.json'
raw = SOURCE.read_bytes()
assert hashlib.sha256(raw).hexdigest() == '3503209c607a75b0b206caeb808f2805d673d1945a5b2a47b368b09f1889d22e'
parsed = list(csv.DictReader(raw.decode('utf-8-sig').splitlines(), delimiter=';'))
rows = [{'date': datetime.strptime(r['data'], '%d/%m/%Y').strftime('%Y-%m-%d'),
         'daily_percent': r['valor'].replace(',', '.')} for r in parsed]
assert len(rows) == len({r['date'] for r in rows}) == 23
assert rows == sorted(rows, key=lambda r: r['date'])
assert all(r['date'].startswith('2026-07-') and Decimal(r['daily_percent']) >= 0 for r in rows)
catalog = json.loads((FISCAL / 'catalogs/rtc-codes-2026.10.08-28-draft18.json').read_text(encoding='utf-8'))
policy = copy.deepcopy(catalog['financial_operation']['base']['securities_realization']['analysis']['history_policy']['daily_memory_policies']['2026-08'])
observed = datetime.fromtimestamp(SOURCE.stat().st_mtime, timezone.utc).replace(microsecond=0).isoformat()
policy['series'] = {'series_code': 11, 'unit': 'PERCENT_PER_BUSINESS_DAY', 'period': '2026-07',
                    'observed_at': observed, 'official_data_sha256': hashlib.sha256(raw).hexdigest(), 'rows': rows}
policy['series']['source_hash'] = digest(policy['series'])
policy.update(published_at=observed, official_data_file=SOURCE.name,
              official_data_sha256=hashlib.sha256(raw).hexdigest(), series_hash=policy['series']['source_hash'])
if TARGET.exists():
    assert json.loads(TARGET.read_text(encoding='utf-8')) == policy
else:
    with TARGET.open('x', encoding='utf-8') as stream:
        stream.write(json.dumps(policy, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({'status': 'DRAFT_NOT_BUNDLE_ACTIVATED', 'rows': len(rows), 'series_hash': policy['series_hash']}))
fixture = json.loads((FISCAL / 'scenarios/tvm-public-negative-yield-2026.10.08-28-draft18/partial-current.json').read_text(encoding='utf-8'))
for name, amount in [('positive', '100'), ('negative', '-150')]:
    position = copy.deepcopy(fixture['recognition_history']['closing_memory']['position'])
    position['period'] = '2026-07'
    for operation in position['operations']:
        operation['date'] = '2026-07-01'
    position['method_evidence'] = proof({'contract': policy['contract'], 'method': position['method']},
                                        'SYNTHETIC.PUBLIC.CHAIN.JULY.METHOD', '2026-07-01', '2026-07-31')
    position['appropriations'] = {'2026-07-31': amount}
    curve, monthly, independent = oracle(position, policy['series'])
    position.pop('appropriations')
    position['contract_curve'] = curve
    position['declared_monthly'] = monthly
    position.pop('memory_evidence', None)
    position['memory_evidence'] = proof(position, 'SYNTHETIC.PUBLIC.CHAIN.JULY.' + name.upper(), '2026-07-01', '2026-07-31')
    target = FISCAL / ('scenarios/tvm-public-chain-preparation-20261008/july-' + name + '.json')
    data = {'position': position, 'independent_oracle': independent, 'bundle_activated': False}
    if target.exists():
        assert json.loads(target.read_text(encoding='utf-8')) == data
    else:
        target.parent.mkdir(parents=True, exist_ok=True)
        with target.open('x', encoding='utf-8') as stream:
            stream.write(json.dumps(data, ensure_ascii=False, indent=2) + '\n')
    print(json.dumps({'case': name, 'vSelic': monthly['vSelic'], 'vApur': monthly['vApur']}))
