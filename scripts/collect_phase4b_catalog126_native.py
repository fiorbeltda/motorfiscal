"""Stream native PHP snapshots directly to D:, preserving every terminal result."""
import argparse
import hashlib
import json
import subprocess
from datetime import datetime, timezone
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
args.output.mkdir(parents=True, exist_ok=False)
command = [r'D:\Docker\DockerDesktop\resources\bin\docker.exe', 'compose', 'exec', '-T', 'app',
           'php', 'scripts/capture_phase4b_catalog126_native.php']
receipt = {'started_at': datetime.now(timezone.utc).isoformat(), 'command': command, 'cases': [], 'status': 'RUNNING'}
parse_errors = []
with (args.output / 'stderr.log').open('wb') as err, (args.output / 'stream.ndjson').open('wb') as stream:
    process = subprocess.Popen(command, cwd=Path(__file__).resolve().parents[1], stdout=subprocess.PIPE, stderr=err)
    for line in process.stdout:
        stream.write(line)
        stream.flush()
        try:
            record = json.loads(line)
        except ValueError:
            if line.strip():
                parse_errors.append(line.decode('utf-8', errors='replace'))
            continue
        name = '-'.join(str(record[key]) for key in ['case', 'runner', 'version']) + '.json'
        snapshot = json.dumps(record.pop('snapshot'), ensure_ascii=False, separators=(',', ':')).encode('utf-8')
        with (args.output / name).open('xb') as handle:
            handle.write(snapshot)
        receipt['cases'].append({**record, 'file': name, 'sha256': hashlib.sha256(snapshot).hexdigest()})
        (args.output / 'execution.json').write_text(json.dumps(receipt, indent=2), encoding='utf-8')
    exit_code = process.wait()
receipt.update(exit_code=exit_code, status='TERMINAL', finished_at=datetime.now(timezone.utc).isoformat())
receipt['native_decisions'] = len(receipt['cases'])
receipt['exact_replays'] = sum(record['replay_matches'] is True for record in receipt['cases'])
receipt['parse_errors'] = parse_errors
(args.output / 'execution.json').write_text(json.dumps(receipt, indent=2) + '\n', encoding='utf-8')
print(json.dumps({key: value for key, value in receipt.items() if key != 'cases'}))
raise SystemExit(exit_code if exit_code else (0 if len(receipt['cases']) == 32 and not parse_errors else 1))
