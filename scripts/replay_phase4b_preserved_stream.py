"""Replay an immutable native receipt's snapshots directly from D: via stdin."""
import argparse
import hashlib
import json
import subprocess
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--receipt', type=Path, required=True)
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
args.output.mkdir(parents=True, exist_ok=False)
receipt = json.loads(args.receipt.read_text(encoding='utf-8-sig'))
items = []
for item in receipt['snapshots']:
    path = (args.receipt.parent / item['file']).resolve()
    path.relative_to(args.receipt.parent.resolve())
    raw = path.read_bytes()
    assert hashlib.sha256(raw).hexdigest() == item['sha256']
    items.append({'file': str(path), 'runner': item['runner'], 'snapshot': json.loads(raw)})
command = [r'D:\Docker\DockerDesktop\resources\bin\docker.exe', 'compose', 'exec', '-T', 'app', 'php', 'scripts/replay_phase4b_catalog126_stream.php']
raw = b'\n'.join(json.dumps(item, ensure_ascii=False, separators=(',', ':')).encode() for item in items) + b'\n'
result = subprocess.run(command, cwd=Path(__file__).resolve().parents[1], input=raw, capture_output=True, check=False)
(args.output / 'stdout.log').write_bytes(result.stdout)
(args.output / 'stderr.log').write_bytes(result.stderr)
replays = [json.loads(line) for line in result.stdout.splitlines()]
report = {'exit_code': result.returncode, 'source_receipt': str(args.receipt),
    'source_receipt_sha256': hashlib.sha256(args.receipt.read_bytes()).hexdigest(),
    'checked_original_hashes': len(items), 'exact_replays': sum(item['replay_matches'] is True for item in replays), 'replays': replays}
with (args.output / 'validation.json').open('x', encoding='utf-8') as handle:
    handle.write(json.dumps(report, indent=2) + '\n')
assert result.returncode == 0 and len(replays) == len(items) and report['exact_replays'] == len(items)
print(json.dumps({key: value for key, value in report.items() if key != 'replays'}))
