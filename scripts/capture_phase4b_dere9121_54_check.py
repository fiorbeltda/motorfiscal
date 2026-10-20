"""Capture successor research and prototype checks without overwriting evidence."""
from pathlib import Path
import hashlib
import json
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'
label = sys.argv[1]
assert label.replace('-', '').isalnum()
log = E / (label + '.log')
record = E / (label + '.json')
assert not log.exists() and not record.exists()
command = sys.argv[2:]
result = subprocess.run(command, cwd=ROOT, capture_output=True, encoding='utf-8')
with log.open('x', encoding='utf-8') as stream:
    stream.write(result.stdout + result.stderr)
with record.open('x', encoding='utf-8') as stream:
    json.dump({'command': command, 'exit_code': result.returncode,
        'log_sha256': hashlib.sha256(log.read_bytes()).hexdigest()}, stream, indent=2)
if label.startswith('reader-native-') and result.returncode == 0:
    capture = json.loads(result.stdout)
    expected = 16 if '-v2-' in label else 14
    assert capture['status'] == 'PLANNED_READER_EXERCISED_NOT_INSTALLED' and len(capture['cases']) == expected
    path = E / ('reader-native-v2-result.json' if expected == 16 else 'reader-native-result.json')
    if path.exists():
        assert json.loads(path.read_text(encoding='utf-8')) == capture
    else:
        with path.open('x', encoding='utf-8') as stream:
            json.dump(capture, stream, indent=2)
    print('PASS: ' + str(expected) + ' local planned-reader cases; native capture/replay checked')
else:
    print(result.stdout + result.stderr)
raise SystemExit(result.returncode)
