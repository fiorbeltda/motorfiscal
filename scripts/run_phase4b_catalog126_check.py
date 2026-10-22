"""Run one explicitly named check, preserving terminal result and logs on D:."""
import argparse
import hashlib
import json
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

sys.stdout.reconfigure(encoding='utf-8')

parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, required=True)
parser.add_argument('command', nargs=argparse.REMAINDER)
args = parser.parse_args()
command = args.command[1:] if args.command[:1] == ['--'] else args.command
args.output.mkdir(parents=True, exist_ok=False)
receipt = {'command': command, 'started_at': datetime.now(timezone.utc).isoformat(), 'status': 'RUNNING'}
(args.output / 'execution.json').write_text(json.dumps(receipt, indent=2), encoding='utf-8')
with (args.output / 'stdout.log').open('wb') as out, (args.output / 'stderr.log').open('wb') as err:
    result = subprocess.run(command, cwd=Path(__file__).resolve().parents[1], stdout=out, stderr=err, check=False)
receipt.update(exit_code=result.returncode, finished_at=datetime.now(timezone.utc).isoformat(), status='TERMINAL')
for channel in ['stdout', 'stderr']:
    path = args.output / (channel + '.log')
    receipt[channel + '_sha256'] = hashlib.sha256(path.read_bytes()).hexdigest()
(args.output / 'execution.json').write_text(json.dumps(receipt, indent=2) + '\n', encoding='utf-8')
print(json.dumps(receipt))
print((args.output / 'stdout.log').read_text(encoding='utf-8', errors='replace')[-6000:])
print((args.output / 'stderr.log').read_text(encoding='utf-8', errors='replace')[-2000:])
raise SystemExit(result.returncode)
