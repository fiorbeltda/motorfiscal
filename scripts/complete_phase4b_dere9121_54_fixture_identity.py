"""Preserve failed first fixture and bind successor to exact origin event id."""
from pathlib import Path
from xml.etree import ElementTree as ET
import hashlib
import json
import subprocess

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

context = json.loads((E / 'synthetic-d9121-context.json').read_text(encoding='utf-8'))
origin = ROOT / context['source_d2101_path']
assert sha(origin) == context['source_d2101_sha256']
source = ET.fromstring(origin.read_bytes())
event_id = list(source)[0].get('id')
assert isinstance(event_id, str) and len(event_id) == 42
namespace = 'http://www.dere.gov.br/schemas/evtRetornoTitPub/v0_0_2'
ET.register_namespace('', namespace)
tree = ET.fromstring((E / 'synthetic-d9121.xml').read_bytes())
tree.find('{'+namespace+'}evtRetornoTitPub').set('id', event_id)
fixture = E / 'synthetic-d9121-v2.xml'
with fixture.open('xb') as stream:
    stream.write(ET.tostring(tree, encoding='utf-8', xml_declaration=True))
driver = E / 'validate-synthetic-d9121-v2.php'
text = (E / 'validate-synthetic-d9121.php').read_text(encoding='utf-8')
assert text.count('synthetic-d9121.xml') == 1
with driver.open('x', encoding='utf-8') as stream:
    stream.write(text.replace('synthetic-d9121.xml', fixture.name))
command = ['docker', 'compose', 'exec', '-T', 'app', 'php', driver.relative_to(ROOT).as_posix()]
result = subprocess.run(command, cwd=ROOT, capture_output=True, encoding='utf-8')
with (E / 'synthetic-schema-v2-check.log').open('x', encoding='utf-8') as stream:
    stream.write(result.stdout + result.stderr)
record = {'command': command, 'exit_code': result.returncode, 'log_sha256': sha(E / 'synthetic-schema-v2-check.log'),
    'first_fixture_preserved_sha256': sha(E / 'synthetic-d9121.xml'), 'successor_fixture_sha256': sha(fixture),
    'origin_event_id': event_id, 'origin_event_id_matches': True, 'source_d2101_sha256': sha(origin),
    'reader_implemented': False, 'monthly_composition_integrated': False, 'production_ready': False}
if result.returncode == 0:
    replay = subprocess.run(command, cwd=ROOT, capture_output=True, encoding='utf-8')
    assert replay.returncode == 0 and json.loads(replay.stdout) == json.loads(result.stdout)
    record['exact_schema_replay'] = True
with (E / 'synthetic-schema-v2-check.json').open('x', encoding='utf-8') as stream:
    json.dump(record, stream, indent=2)
print(result.stdout + result.stderr)
raise SystemExit(result.returncode)
