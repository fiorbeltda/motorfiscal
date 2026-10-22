"""Verify and durably preserve the scoped133 console without promoting phase acceptance."""
import hashlib
import json
import os
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
M = ROOT / 'resources/monitoring'
OUT = Path('D:/ProjetoSaasExports/phase4b-iss133-20261022-v1/final-monitoring-v1')
assert not OUT.exists()
now = datetime.now(timezone.utc).isoformat()


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def save(path, raw, mode='xb'):
    with path.open(mode) as stream:
        stream.write(raw)
        stream.flush()
        os.fsync(stream.fileno())


def update(path, value):
    save(path, (json.dumps(value, ensure_ascii=False, indent=2) + '\n').encode(), 'wb')


task = read(M / 'task-progress.json')
assert task['scope_package'] == '133' and all(item['complete'] for item in task['milestones'])
task['pending'] = ['Integrar cálculoISS133 ao fluxo versionado e àDPS com valores próprios por documento.',
                   'Concluir câmbioISS próprio,importação e demais perfis de base/classificação.',
                   'Concluir obrigação documental e demais mínimos dos80 requisitos originais4B.']
task['focus']['pending'] = task['pending']
update(M / 'task-progress.json', task)
review = read(M / 'objective-review.json')
assert len(review['objectives']) == 8 and review['active_development_bundle'] == 'CONSUMPTION-PHASE4B-2026.10.22-131-draft2'
for objective in review['objectives']:
    assert objective['pending']
    objective['reviewed_at'] = now
    relevant = objective['id'] in ['4B.4', '4B.7', '4B.8']
    objective['current_package_review'] = ('133 acrescenta cálculo localISS por documento;evidência não encerra o grupo.' if relevant
                                          else '133 preserva comportamento e pendências anteriores deste grupo,sem encerramento.')
    if relevant:
        objective['current_work'] = task['state']
        objective['current_work_evidence'] = task['evidence']
update(M / 'objective-review.json', review)
receipt_path = ROOT / 'storage/app/phase4b-iss133-development-20261022/iss133-scoped-validation.json'
receipt = read(receipt_path)
assert len(receipt['runtime_hashes']) == 110 and not receipt['phase4b_complete']
assert all(hashlib.sha256((ROOT / name).read_bytes()).hexdigest() == digest for name, digest in receipt['runtime_hashes'].items())
raw_status = urllib.request.urlopen('http://localhost:8080/console-fiscal/status', timeout=30).read()
status = json.loads(raw_status)
assert status['task_progress']['scope_package'] == '133' and status['task_progress']['completed'] == 6
assert status['task_progress']['focus']['pending'] == task['pending'] and status['task_progress']['finished'] == task['finished']
assert status['acceptance']['tests'] == 9061 and not status['acceptance']['production_ready']
states = {p['id']: p['state'] for p in status['phases']}
assert all(states[k] == v for k, v in {'3': 'CLOSED', '4A': 'CLOSED', '4B': 'IN_PROGRESS', '5': 'PLANNED', '6': 'PLANNED', '7': 'PLANNED'}.items())
OUT.mkdir()
files = ['resources/monitoring/' + name for name in ['task-progress.json', 'objective-review.json', 'roadmap.json']]
files += ['storage/app/phase4b-iss133-development-20261022/' + name for name in ['iss133-scoped-validation.json', 'requirements80-current-matrix.json']]
files += ['docs/fiscal/' + name for name in ['phase4b-iss133-local-document-base.md', 'phase4b-iss-base113-boundary-contract.md',
                                         'implementation-status.md', 'phase4b-status.md', 'phase4b-original80-current-matrix.md']]
inventory = []
for name in files:
    raw = (ROOT / name).read_bytes()
    destination = OUT / Path(name).name
    save(destination, raw)
    digest = hashlib.sha256(raw).hexdigest()
    assert hashlib.sha256(destination.read_bytes()).hexdigest() == digest
    inventory.append({'source': name, 'saved': destination.name, 'sha256': digest})
save(OUT / 'console-status.json', raw_status)
result = {'checked_at': now, 'complete': True, 'files': inventory, 'runtime_hashes_verified': 110,
          'console_status_sha256': hashlib.sha256(raw_status).hexdigest(), 'groups_reviewed': 8,
          'formal_groups_closed': 0, 'phases': states, 'full_acceptance_tests': 9061}
save(OUT / 'validation.json', json.dumps(result, indent=2).encode())
print(json.dumps({k: v for k, v in result.items() if k not in ['files', 'console_status_sha256']}))
