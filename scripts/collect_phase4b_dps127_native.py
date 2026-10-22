"""Persist native127 snapshots/XMLs on D: and reject partial/error output."""
import argparse
import hashlib
import json
import os
import subprocess
from datetime import datetime, timezone
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, required=True)
parser.add_argument('--script', default='scripts/capture_phase4b_dps127_native.php')
parser.add_argument('--cases', nargs='+', default=['federal', 'education', 'presential'])
parser.add_argument('--expected-snapshots', type=int, default=12)
parser.add_argument('--expected-xmls', type=int, default=3)
parser.add_argument('--comparison-flag', default='declared_document_changes_only')
args = parser.parse_args()
args.output.mkdir(parents=True, exist_ok=False)
command = [r'D:\Docker\DockerDesktop\resources\bin\docker.exe', 'compose', 'exec', '-T', 'app', 'php', args.script]
report = {'started_at': datetime.now(timezone.utc).isoformat(), 'command': command, 'status': 'RUNNING', 'snapshots': [], 'xmls': [], 'parse_errors': []}
def write_json(path, value):
    raw = json.dumps(value, ensure_ascii=False, indent=2).encode('utf-8')
    with path.open('xb') as handle:
        handle.write(raw)
        handle.flush()
        os.fsync(handle.fileno())
    return hashlib.sha256(raw).hexdigest()
with (args.output / 'stream.ndjson').open('wb') as stream, (args.output / 'stderr.log').open('wb') as err:
    process = subprocess.Popen(command, cwd=Path(__file__).resolve().parents[1], stdout=subprocess.PIPE, stderr=err)
    for line in process.stdout:
        stream.write(line)
        stream.flush()
        try:
            item = json.loads(line)
        except ValueError:
            if line.strip():
                report['parse_errors'].append(line.decode('utf-8', errors='replace'))
            continue
        name = item['case']
        assert name in args.cases
        if item['kind'] == 'snapshot':
            filename = '-'.join([name, item['runner'], item['version'], 'snapshot.json'])
            digest = write_json(args.output / filename, item.pop('snapshot'))
            report['snapshots'].append({**item, 'file': filename, 'sha256': digest})
        elif item['kind'] == 'xml':
            for key in ['first_xml', 'second_xml']:
                xml = item.pop(key).encode('utf-8')
                filename = name + '-' + key + '.xml'
                with (args.output / filename).open('xb') as handle:
                    handle.write(xml)
                    handle.flush()
                    os.fsync(handle.fileno())
                item[key + '_file'] = filename
                item[key + '_sha256'] = hashlib.sha256(xml).hexdigest()
            filename = name + '-projection.json'
            digest = write_json(args.output / filename, item)
            report['xmls'].append({'file': filename, 'sha256': digest})
        else:
            report['parse_errors'].append('Unknown native record kind')
    exit_code = process.wait()
    stream.flush()
    os.fsync(stream.fileno())
    err.flush()
    os.fsync(err.fileno())
valid = exit_code == 0 and not report['parse_errors'] and len(report['snapshots']) == args.expected_snapshots and len(report['xmls']) == args.expected_xmls
valid = valid and all(item['replay_matches'] and item.get(args.comparison_flag) is True for item in report['snapshots'])
report.update(status='TERMINAL', exit_code=exit_code, complete=valid, finished_at=datetime.now(timezone.utc).isoformat(),
              native_decisions=len(report['snapshots']), exact_replays=sum(item['replay_matches'] for item in report['snapshots']), xml_files=2*len(report['xmls']))
write_json(args.output / 'validation.json', report)
print(json.dumps({key: value for key, value in report.items() if key not in ['snapshots', 'xmls', 'command']}))
raise SystemExit(0 if valid else 1)
