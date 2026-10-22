"""Check the final observation console and durably archive the scoped132 checkpoint."""
import hashlib
import json
import os
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MONITORING = ROOT / 'resources/monitoring'
OUTPUT = Path('D:/ProjetoSaasExports/phase4b-values132-20261022-v1/final-monitoring-v1')
now = datetime.now(timezone.utc).isoformat()


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def write(path, value):
    with path.open('w', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)
        stream.flush()
        os.fsync(stream.fileno())


assert not OUTPUT.exists()
task = read(MONITORING / 'task-progress.json')
assert task['scope_package'] == '132' and all(m['complete'] for m in task['milestones'])
task['pending'] = [
    'Qualificar base legalISS por documento,classificação municipal granular e composição de preço.',
    'Concluir contrato cambialISS próprio e demais moedas/perfis,sem presumir câmbioRTC.',
    'Concluir obrigação documental integrada da importação e demais mínimos dos80 requisitos4B.',
]
task['focus']['pending'] = task['pending']
write(MONITORING / 'task-progress.json', task)
review = read(MONITORING / 'objective-review.json')
assert len(review['objectives']) == 8
for objective in review['objectives']:
    assert objective['pending']
    objective['reviewed_at'] = now
    objective['current_package_review'] = ('132 atualiza evidências documentais/aceitação local,sem encerramento.'
                                           if objective['id'] in ['4B.7', '4B.8'] else
                                           '132 não altera o comportamento fiscal deste grupo;conquistas e pendências anteriores preservadas.')
    if objective['id'] in ['4B.7', '4B.8']:
        objective['current_work'] = task['state']
        objective['current_work_evidence'] = task['evidence']
write(MONITORING / 'objective-review.json', review)
roadmap = read(MONITORING / 'roadmap.json')
assert all(p['state'] == 'PLANNED' for p in roadmap['phases'] if p['id'] in ['5', '6', '7'])
receipt_path = ROOT / 'storage/app/phase4b-values132-development-20261022/values132-scoped-validation.json'
receipt = read(receipt_path)
assert all(hashlib.sha256((ROOT / name).read_bytes()).hexdigest() == digest for name, digest in receipt['runtime_hashes'].items())
endpoint_bytes = urllib.request.urlopen('http://localhost:8080/console-fiscal/status', timeout=30).read()
endpoint = json.loads(endpoint_bytes)
assert endpoint['task_progress']['scope_package'] == '132' and endpoint['task_progress']['completed'] == 6
assert endpoint['task_progress']['pending'] == task['pending']
assert endpoint['task_progress']['focus']['finished'] == task['finished']
states = {p['id']: p['state'] for p in endpoint['phases']}
assert all(states[k] == v for k, v in {'3': 'CLOSED', '4A': 'CLOSED', '4B': 'IN_PROGRESS', '5': 'PLANNED', '6': 'PLANNED', '7': 'PLANNED'}.items())
assert endpoint['acceptance']['tests'] == 9061 and not endpoint['acceptance']['production_ready']
OUTPUT.mkdir()
files = ['resources/monitoring/' + name for name in ['task-progress.json', 'objective-review.json', 'roadmap.json']]
files += ['storage/app/phase4b-values132-development-20261022/' + name for name in ['values132-scoped-validation.json', 'requirements80-current-matrix.json']]
files += ['docs/fiscal/' + name for name in ['phase4b-import132-document-values.md', 'implementation-status.md', 'phase4b-status.md', 'phase4b-original80-current-matrix.md']]
inventory = []
for name in files:
    raw = (ROOT / name).read_bytes()
    destination = OUTPUT / Path(name).name
    with destination.open('xb') as stream:
        stream.write(raw)
        stream.flush()
        os.fsync(stream.fileno())
    digest = hashlib.sha256(raw).hexdigest()
    assert hashlib.sha256(destination.read_bytes()).hexdigest() == digest
    inventory.append({'source': name, 'saved': destination.name, 'sha256': digest})
with (OUTPUT / 'console-status.json').open('xb') as stream:
    stream.write(endpoint_bytes)
    stream.flush()
    os.fsync(stream.fileno())
write(OUTPUT / 'validation.json', {'checked_at': now, 'complete': True, 'files': inventory,
                                  'console_status_sha256': hashlib.sha256(endpoint_bytes).hexdigest(),
                                  'runtime_hashes_verified': len(receipt['runtime_hashes']), 'groups_reviewed': 8,
                                  'formal_groups_closed': 0, 'phases': states, 'full_acceptance_tests': 9061})
print(json.dumps({'complete': True, 'saved_files': len(files), 'runtime_hashes_verified': len(receipt['runtime_hashes']),
                  'groups_reviewed': 8, 'formal_groups_closed': 0, 'phases': states}))
