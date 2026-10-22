"""Record an observed scoped package without promoting whole-phase acceptance."""
import argparse
import copy
import json
import hashlib
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FOLDER = ROOT / 'resources/monitoring'
parser = argparse.ArgumentParser()
parser.add_argument('--package', required=True)
parser.add_argument('--title', required=True)
parser.add_argument('--completed', required=True, type=int, choices=range(7))
parser.add_argument('--state', required=True)
parser.add_argument('--next', required=True)
parser.add_argument('--bundle')
parser.add_argument('--matrix')
parser.add_argument('--evidence', action='append', default=[])
parser.add_argument('--objective', action='append', default=['4B.7', '4B.8'])
parser.add_argument('--finished', action='append', default=[])
args = parser.parse_args()
now = datetime.now(timezone.utc).isoformat()
def read(name):
    return json.loads((FOLDER / name).read_text(encoding='utf-8-sig'))
def write(name, value):
    with (FOLDER / name).open('w', encoding='utf-8', newline='\n') as handle:
        handle.write(json.dumps(value, ensure_ascii=False, indent=2) + '\n')
task = read('task-progress.json')
previous = copy.deepcopy(task)
previous.pop('history', None)
task.setdefault('history', []).insert(0, previous)
titles = ['Conferir checkpoint/processos', 'Preservar fontes e qualificar contrato', 'Implementar comportamento delimitado',
          'Integrar nova versão preservando históricos', 'Validar casos,recusas e replays', 'Auditar evidências,documentação e console']
task.update(updated_at=now, title=args.title, scope_package=args.package, state=args.state,
            criterion=f'{args.completed}/6 marcos observados ({round(args.completed/6*100)}%) deste corte. Não é percentual funcional4B;0/8 grupos encerrados.',
            milestones=[{'title': title, 'complete': index < args.completed} for index, title in enumerate(titles)],
            finished=args.finished[-3:] or titles[:args.completed][-3:], pending=titles[args.completed:args.completed+3],
            next_step=args.next, evidence=args.evidence)
task['focus'] = {'summary': args.state, **{key: task[key] for key in ['finished', 'pending', 'next_step']}}
task.pop('analysis_review', None)  # Previous analysis remains in history after an authorized resume.
write('task-progress.json', task)
for name in ['objective-review.json', 'roadmap.json']:
    value = read(name)
    if value.get('scope_package') != args.package:
        value['history_reviews'].insert(0, {key: copy.deepcopy(item) for key, item in value.items() if key != 'history_reviews'})
    value.update(scope_package=args.package, reviewed_at=now, current_review=args.state)
    value.pop('analysis_review', None)
    if name == 'objective-review.json':
        value['criterion'] = f'{args.package}:corte local ' + ('validado' if args.completed == 6 else 'em desenvolvimento') + ';122 último aceite integral.80 IDs examinados,0/8 grupos encerrados;produção bloqueada.'
        if args.bundle:
            value['active_development_bundle'] = args.bundle
        if args.matrix:
            matrix_path = ROOT / args.matrix
            matrix = json.loads(matrix_path.read_text(encoding='utf-8-sig'))
            assert len(matrix['requirements']) == 80 and matrix['formal_phase4b_groups_closed'] == 0
            value['current_requirements_matrix'] = {'path': args.matrix, 'sha256': hashlib.sha256(matrix_path.read_bytes()).hexdigest()}
        for obj in value['objectives']:
            if obj['id'] in args.objective:
                obj.update(reviewed_at=now, current_work=args.state, current_work_evidence=args.evidence)
                if args.completed >= 5:
                    for item in args.finished:
                        if item not in obj['finished']:
                            obj['finished'].append(item)
    else:
        value['scope_note'] = f'3/4A encerradas emSandbox;4B parcial,5–7 planejadas. Corte{args.package} local ' + ('validado' if args.completed == 6 else 'em desenvolvimento') + ';122 último aceite integral.0/8 grupos encerrados;produção bloqueada.'
        for phase in value['phases']:
            if phase['id'] == '4B':
                phase['summary'] = args.state + ' Fase parcial,0/8 grupos formalmente encerrados.'
    write(name, value)
print(json.dumps({'package': args.package, 'updated_at': now, 'observed_milestones': args.completed}))
