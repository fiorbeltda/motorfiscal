"""A156 immutable command receipts; no historical evidence or B resources are rewritten."""
import hashlib
import json
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path
from xml.etree import ElementTree as ET

from evidence_io import preserve_bytes, preserve_json

ROOT = Path(__file__).resolve().parents[2]
OUT = Path('D:/ProjetoSaasExports/agent-a-A156-H6-H10-20261105-v1')
DOCKER = 'D:/Docker/DockerDesktop/resources/bin/docker.exe'
CONTAINER = 'motorfiscal-agente-a-20261103'


def sha(p):
    return hashlib.sha256(Path(p).read_bytes()).hexdigest()


def run(name, args):
    command = [DOCKER, 'exec', CONTAINER, 'php', '-d', 'memory_limit=1G', *args]
    hashes = {p.as_posix().removeprefix(ROOT.as_posix()+'/'): sha(p)
              for base in ['app/Modules/Fiscal/ConsumptionReform', 'tests/Fiscal', 'scripts/agent_a']
              for p in (ROOT/base).rglob('*') if p.is_file() and p.suffix in ['.php', '.py']}
    preserve_json(OUT/(name+'-command.json'), {'command': command, 'started_at': datetime.now(timezone.utc).isoformat(),
        'head': subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT).decode().strip(), 'hashes': hashes})
    with (OUT/(name+'.log')).open('xb') as f:
        result = subprocess.run(command, cwd=ROOT, stdout=f, stderr=subprocess.STDOUT)
    preserve_json(OUT/(name+'-exit.json'), {'exit_code': result.returncode, 'log_sha256': sha(OUT/(name+'.log')),
        'finished_at': datetime.now(timezone.utc).isoformat()})
    print(json.dumps({'name': name, 'exit': result.returncode}))


def collect(name, junit):
    p = ROOT/junit
    preserve_bytes(OUT/(name+'.junit.xml'), p.read_bytes())
    tree = ET.parse(p)
    cases = list(tree.iter('testcase'))
    result = {'name': name, 'tests': len(cases), 'assertions': sum(int(c.get('assertions', 0)) for c in cases),
        'failures': sum(c.find('failure') is not None for c in cases),
        'errors': sum(c.find('error') is not None for c in cases),
        'skipped': sum(c.find('skipped') is not None for c in cases), 'sha256': sha(p),
        'files': sorted(set(c.get('file', '') for c in cases)),
        'issues': [{'name': c.get('name'), 'details': ET.tostring(c, encoding='unicode')}
                   for c in cases if any(c.find(k) is not None for k in ['failure', 'error', 'skipped'])]}
    preserve_json(OUT/(name+'-result.json'), result)
    print(json.dumps({k:v for k,v in result.items() if k not in ['files', 'issues']}))


if __name__ == '__main__':
    if sys.argv[1] == 'run':
        run(sys.argv[2], sys.argv[3:])
    elif sys.argv[1] == 'collect':
        collect(sys.argv[2], sys.argv[3])
