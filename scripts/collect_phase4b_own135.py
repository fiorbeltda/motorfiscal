"""Capture complete134 replay plus actual own contribution calculations on two documents."""
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
source = Path('D:/ProjetoSaasExports/phase4b-iss134-20261022-v1/source-staging135-v1/price-bridge-v1/snapshot.json')
raw = source.read_bytes()
assert hashlib.sha256(raw).hexdigest() == 'b0a122fbc8e5757a40cd81d0ace8311808d17da316cdb5af12aeb4c5942995a5'
command = [r'D:\Docker\DockerDesktop\resources\bin\docker.exe', 'compose', 'exec', '-T', 'app', 'php', 'scripts/capture_phase4b_own135_native.php']
outputs = []
for index in range(2):
    result = subprocess.run(command, input=raw, capture_output=True, cwd=root, check=False)
    (args.output / f'execution{index+1}.json').write_bytes(result.stdout)
    (args.output / f'execution{index+1}.stderr.log').write_bytes(result.stderr)
    assert result.returncode == 0, result.stderr.decode(errors='replace')
    outputs.append(result.stdout)
assert outputs[0] == outputs[1]
report = json.loads(outputs[0])
assert report['source_replay_exact'] and report['synthetic_proofs_only'] and not report['bundle135_installed']
assert len(report['cases']) == 6
checks = 0
for case in report['cases']:
    result = case['result']
    if case['case'] not in ['captured-own', 'foreign-ordinary']:
        assert result['status'] == 'ACTION_REQUIRED'
        continue
    assert result['status'] == 'LOCAL_SOFTWARE_OWN_REVENUE_LINES_CALCULATED'
    for document in result['documents']:
        amounts = document['own_incident_amounts_brl']
        if document['own_contribution_base_brl'] is not None:
            base = Decimal(document['own_contribution_base_brl'])
            for tax, rate in [('PIS', '0.0065'), ('COFINS', '0.03')]:
                expected = (base * Decimal(rate)).quantize(Decimal('0.01'), rounding=ROUND_HALF_UP)
                assert format(expected, '.2f') == amounts[tax]
                checks += 1
        total = sum((Decimal(value) for value in amounts.values()), Decimal('0.00'))
        window = document['legacy_reconciliation']
        assert format(total, '.2f') == window['declared_legacy_exclusion_brl']
        checks += 1
bridge = {row['role']: row for row in report['bridge']}
assert bridge['RTC_FOREIGN']['bridge_matches']
assert bridge['RTC_DOMESTIC']['own_net_candidate_brl'] == '578.10'
assert bridge['RTC_DOMESTIC']['difference_brl'] == '-21.90' and not bridge['RTC_DOMESTIC']['bridge_matches']
validation = {'status': 'OWN_SOFTWARE_LINES_LOCALLY_CALCULATED_OLD_BRIDGE_INCOMPLETE', 'exact_source_replays': 2,
              'cases': 6, 'positive': 2, 'negative': 4, 'independent_decimal_checks': checks, 'identical_output': True,
              'source_snapshot_sha256': hashlib.sha256(raw).hexdigest(), 'output_sha256': hashlib.sha256(outputs[0]).hexdigest(),
              'domestic_gap_brl': '-21.90', 'bundle135_installed': False, 'production_ready': False,
              'judicial_temporal_effects_approved': False, 'final_judgment_verified': False}
(args.output / 'validation.json').write_text(json.dumps(validation, indent=2) + '\n', encoding='utf-8')
print(json.dumps(validation))
