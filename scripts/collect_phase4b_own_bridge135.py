"""Preserve new complete snapshots and validate all-own-tax bridge with independent Decimal."""
import argparse
import hashlib
import json
import subprocess
from decimal import Decimal, ROUND_HALF_UP
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
args.output.mkdir(parents=True, exist_ok=False)
root = Path(__file__).resolve().parents[1]
command = [r'D:\Docker\DockerDesktop\resources\bin\docker.exe', 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'scripts/capture_phase4b_own_bridge135_native.php']
outputs = []
for index in range(2):
    result = subprocess.run(command, cwd=root, capture_output=True, check=False)
    (args.output / f'execution{index+1}.json').write_bytes(result.stdout)
    (args.output / f'execution{index+1}.stderr.log').write_bytes(result.stderr)
    assert result.returncode == 0, result.stderr.decode(errors='replace')
    outputs.append(result.stdout)
assert outputs[0] == outputs[1]
report = json.loads(outputs[0])
assert report['synthetic_contracts_and_proofs_only'] and not report['bundle135_installed']
assert len(report['cases']) == 3
snapshots = []
checks = 0
for case in report['cases']:
    assert case['source_replay_exact']
    snapshot = case['snapshot']
    body = json.dumps(snapshot, ensure_ascii=False, indent=2).encode('utf-8')
    file = case['case'] + '.snapshot.json'
    (args.output / file).write_bytes(body)
    snapshots.append({'file': file, 'sha256': hashlib.sha256(body).hexdigest(), 'runner': 'integrated'})
    if case['case'] != 'new-explicit-contract':
        assert case['result']['status'] == 'ACTION_REQUIRED'
        assert case['result']['reason'] == 'OWN_BRIDGE135_ALL_OWN_TAX_PRICE_DIFFERENCE_UNEXPLAINED'
        assert case['result']['difference_brl'] == ('-21.90' if case['case'] == 'former-iss-only-contract' else '0.01')
        continue
    bridge = case['result']
    assert bridge['status'] == 'DOCUMENT_PRICES_ALL_OWN_TAXES_RTC_BRIDGE_BOUND'
    assert bridge['document_price_total_brl'] == '1041.33' and bridge['own_exclusions_total_brl'] == '41.33'
    own = {row['role']: row for row in bridge['fresh_own_calculation']['documents']}
    for document in bridge['documents']:
        amounts = document['own_incident_amounts_brl']
        price = Decimal(document['composed_document_price_brl'])
        iss = Decimal(amounts['ISS'])
        assert format(price - sum((Decimal(v) for v in amounts.values()), Decimal('0.00')), '.2f') == document['rtc_leg_brl']
        checks += 1
        if document['role'] == 'RTC_DOMESTIC':
            assert amounts == {'ISS': '18.60', 'ICMS': '0.00', 'PIS': '4.05', 'COFINS': '18.68'}
            assert iss == (price * Decimal('.029')).quantize(Decimal('.01'), rounding=ROUND_HALF_UP)
            assert own['RTC_DOMESTIC']['own_contribution_base_brl'] == format(price - iss, '.2f')
            for tax, rate in [('PIS', '.0065'), ('COFINS', '.03')]:
                assert amounts[tax] == format(((price - iss) * Decimal(rate)).quantize(Decimal('.01'), rounding=ROUND_HALF_UP), '.2f')
                checks += 1
            checks += 2
validation = {'status': 'LOCAL_ALL_OWN_TAX_BRIDGE_VALIDATED_NOT_FULL_DPS', 'identical_runs': True, 'exact_source_replays': 6,
              'source_scenarios': 3, 'positive': 1, 'negative': 2, 'independent_decimal_checks': checks, 'snapshots': snapshots,
              'output_sha256': hashlib.sha256(outputs[0]).hexdigest(), 'bundle135_installed': False,
              'document_obligation_complete': False, 'judicial_temporal_effects_approved': False}
(args.output / 'validation.json').write_text(json.dumps(validation, indent=2) + '\n', encoding='utf-8')
print(json.dumps({k: v for k, v in validation.items() if k != 'snapshots'}))
