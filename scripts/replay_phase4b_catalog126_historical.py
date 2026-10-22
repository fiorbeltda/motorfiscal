"""Replay preserved122 snapshots without copying them into C: or changing bytes."""
import argparse
import hashlib
import json
import subprocess
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
args.output.mkdir(parents=True, exist_ok=False)
root = Path(r'D:\ProjetoSaasExports\phase4b-credit122-preparation-20261021\credit-cli-v2')
corpus = json.loads((root / 'credit122-cli-validation.json').read_text(encoding='utf-8-sig'))
items = []
for case in corpus['cases'][:6]:
    for runner in ['rtc', 'integrated']:
        source = root / case[runner]['snapshot']
        raw = source.read_bytes()
        assert hashlib.sha256(raw).hexdigest() == case[runner]['snapshot_sha256']
        items.append({'file': str(source), 'runner': runner, 'snapshot': json.loads(raw)})
command = [r'D:\Docker\DockerDesktop\resources\bin\docker.exe', 'compose', 'exec', '-T', 'app',
           'php', 'scripts/replay_phase4b_catalog126_stream.php']
raw_input = b'\n'.join(json.dumps(item, ensure_ascii=False, separators=(',', ':')).encode() for item in items) + b'\n'
result = subprocess.run(command, cwd=Path(__file__).resolve().parents[1], input=raw_input, capture_output=True, check=False)
(args.output / 'stdout.log').write_bytes(result.stdout)
(args.output / 'stderr.log').write_bytes(result.stderr)
replays = [json.loads(line) for line in result.stdout.splitlines()]
report = {'exit_code': result.returncode, 'replays': replays, 'checked_snapshot_hashes': len(items),
          'exact_replays': sum(item['replay_matches'] is True for item in replays),
          'scope': 'SIX_PRESERVED122_SCENARIOS_BOTH_RUNNERS_NOT_ALL1441_HISTORICAL'}
(args.output / 'validation.json').write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
assert result.returncode == 0 and len(replays) == 12 and report['exact_replays'] == 12, report
print(json.dumps({key: value for key, value in report.items() if key != 'replays'}))
