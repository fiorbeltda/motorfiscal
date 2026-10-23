"""Replay source134 twice, exercise local135, independently check declared amounts."""
import argparse
import hashlib
import json
import subprocess
from decimal import Decimal
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
root = Path(__file__).resolve().parents[1]
args.output.mkdir(parents=True, exist_ok=False)
source = Path('D:/ProjetoSaasExports/phase4b-iss134-20261022-v1/source-staging135-v1/price-bridge-v1/snapshot.json')
raw = source.read_bytes()
assert hashlib.sha256(raw).hexdigest() == 'b0a122fbc8e5757a40cd81d0ace8311808d17da316cdb5af12aeb4c5942995a5'
command = [r'D:\Docker\DockerDesktop\resources\bin\docker.exe', 'compose', 'exec', '-T', 'app', 'php', 'scripts/capture_phase4b_legacy135_native.php']
runs = []
for index in range(2):
    result = subprocess.run(command, input=raw, capture_output=True, cwd=root, check=False)
    (args.output / f'execution{index+1}.json').write_bytes(result.stdout)
    (args.output / f'execution{index+1}.stderr.log').write_bytes(result.stderr)
    assert result.returncode == 0, result.stderr.decode(errors='replace')
    runs.append(json.loads(result.stdout))
assert runs[0] == runs[1]
report = runs[0]
assert report['source_replay_exact'] and report['source_federal_withholdings_skipped']
assert report['synthetic_assessments_only'] and not report['integrated135']
assert len(report['checks']) == 8
positive = negative = 0
for case in report['checks']:
    actual = case['result']
    if case['case'] in ['missing-own', 'withholding-not-requested']:
        assert actual['status'] == 'ACTION_REQUIRED'
        negative += 1
        continue
    positive += 1
    assert actual['status'] == 'ARTICLE12_PAR2_V_DECLARED_INCIDENT_AMOUNTS_RECONCILED'
    total = sum((Decimal(row['amount_brl']) for row in case['assessments']), Decimal('0.00'))
    net = Decimal(case['context']['composed_price_brl']) - total
    assert actual['declared_legacy_exclusion_brl'] == format(total, '.2f')
    assert actual['price_after_declared_legacy_exclusions_brl'] == format(net, '.2f')
    assert not actual['own_tax_assessments_reexecuted'] and not actual['legacy_exclusion_legally_approved']
validation = {'status': 'LOCAL_DECLARATIONS_ONLY_NOT_FISCAL_ACCEPTANCE', 'source_snapshot_sha256': hashlib.sha256(raw).hexdigest(),
              'exact_source_replays': 2, 'identical_runs': True, 'cases': 8, 'positive': positive, 'negative': negative,
              'independent_decimal_checks': positive, 'integrated135': False,
              'output_sha256': hashlib.sha256((args.output / 'execution1.json').read_bytes()).hexdigest()}
(args.output / 'validation.json').write_text(json.dumps(validation, indent=2) + '\n', encoding='utf-8')
print(json.dumps(validation))
