"""Record observed catalog126 milestones; never infer whole-phase completion."""
import argparse
import copy
import json
import subprocess
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--completed', type=int, required=True, choices=range(7))
parser.add_argument('--state', required=True)
parser.add_argument('--next', required=True)
parser.add_argument('--evidence', action='append', default=[])
args = parser.parse_args()
now = datetime.now(timezone.utc).isoformat()
folder = ROOT / 'resources/monitoring'
def read(name):
    return json.loads((folder / name).read_text(encoding='utf-8-sig'))
def write(name, value):
    with (folder / name).open('w', encoding='utf-8', newline='\n') as handle:
        handle.write(json.dumps(value, ensure_ascii=False, indent=2) + '\n')
task = read('task-progress.json')
previous = copy.deepcopy(task)
previous.pop('history', None)
task.setdefault('history', []).insert(0, previous)
titles = ['Conferir checkpoint122 e pendências atuais', 'Preservar fontes atuais e qualificar versões/ambientes',
          'Implementar seleção determinística e contratos', 'Integrar novo bundle preservando os históricos',
          'Validar cenários,recusas e replay', 'Conferir documentação,objetivos e console']
task.update(updated_at=now, title='Catálogos126 · seleção temporal e ambiental cIndOp', state=args.state,
            criterion=f'{args.completed}/6 marcos observados desta entrega ({round(args.completed/6*100)}%). Não é percentual da Fase4B;0/8 grupos formalmente encerrados.',
            milestones=[{'title': title, 'complete': index < args.completed} for index, title in enumerate(titles)],
            finished=titles[:args.completed][-3:], pending=titles[args.completed:args.completed+3],
            next_step=args.next, evidence=args.evidence)
task['focus'] = {key: task[key] for key in ['finished', 'pending', 'next_step']}
task['focus']['summary'] = args.state
write('task-progress.json', task)
review = read('objective-review.json')
if not any(item.get('reviewed_at') == '2026-10-21T19:40:19.700Z' for item in review['history_reviews']):
    baseline = json.loads(subprocess.check_output(['git', 'show', '473bfcc:resources/monitoring/objective-review.json'], cwd=ROOT))
    review['history_reviews'].insert(0, {key: baseline[key] for key in ['reviewed_at', 'criterion', 'current_review', 'objectives']})
review['reviewed_at'] = now
review['criterion'] = ('Retomada autorizada22/10.122 permanece último aceite integral;126 validado no escopo local.'
                       if args.completed == 6 else 'Retomada autorizada22/10.122 permanece aceite histórico;126 em desenvolvimento.') + '80 requisitos examinados,0/8 grupos formalmente encerrados;produção bloqueada.'
review['current_review'] = args.state
review['active_development_bundle'] = 'CONSUMPTION-PHASE4B-2026.10.22-126-draft1'
for obj in review['objectives']:
    obj['reviewed_at'] = now
    if obj['id'] == '4B.5':
        obj['pending'][0] = 'Concluir apropriação,estorno/glosa e ciclos materiais por perfil. Contratos estruturais/perguntas122 preservados na retomada22/10.'
    if obj['id'] == '4B.8':
        obj['pending'][0] = 'Desenvolvimento retomado22/10:fechar lacunas funcionais atuais da matriz80,com aceite próprio por corte e preservação do checkpoint122.'
    if obj['id'] == '4B.6':
        obj['reviewed_at'] = now
        obj['current_work'] = args.state
        obj['current_work_evidence'] = args.evidence
        if args.completed >= 5:
            note = '126:40 códigosRTC1.03.00 e26 do AnexoC preservados separadamente;seleção local explícita,tipos,fontes e limites temporais validados,sem ativação oficial presumida.'
            if note not in obj['finished']:
                obj['finished'].append(note)
            obj['pending'][0] = 'Qualificar vigência e seleção dos ambientes oficiais;resolver divergências entreRTC1.03.00 eAnexoC sem fundir tabelas.126 usa referência local;122 preserva1.02.00.'
write('objective-review.json', review)
roadmap = read('roadmap.json')
if not any(item.get('reviewed_at') == '2026-10-21T19:40:19.700Z' for item in roadmap['history_reviews']):
    baseline = json.loads(subprocess.check_output(['git', 'show', '473bfcc:resources/monitoring/roadmap.json'], cwd=ROOT))
    roadmap['history_reviews'].insert(0, {key: baseline[key] for key in ['reviewed_at', 'current_review', 'scope_note', 'phases']})
roadmap['reviewed_at'] = now
roadmap['current_review'] = args.state
roadmap['scope_note'] = '3/4A encerradas emSandbox;4B parcial,5–7 planejadas. Retomada22/10:catálogos126 ' + ('validados no escopo local' if args.completed == 6 else 'em desenvolvimento') + ';122 último aceite integral.0/8 grupos encerrados;produção bloqueada.'
for phase in roadmap['phases']:
    if phase['id'] == '4B':
        phase['summary'] = args.state + ' Fase parcial;0/8 grupos formalmente encerrados.'
write('roadmap.json', roadmap)
print(json.dumps({'updated_at': now, 'milestones_complete': args.completed, 'runtime_modified': False}))
