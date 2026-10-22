"""Track only observed DPS127 work while preserving prior task/review cuts."""
import argparse
import copy
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FOLDER = ROOT / 'resources/monitoring'
parser = argparse.ArgumentParser()
parser.add_argument('--completed', required=True, type=int, choices=range(7))
parser.add_argument('--state', required=True)
parser.add_argument('--next', required=True)
parser.add_argument('--evidence', action='append', default=[])
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
titles = ['Conferir estado126 e processos ativos', 'Preservar e comparar schema oficial20/10',
          'Implementar contrato e projeção no schema observado', 'Integrar novo bundle sem reescrever os históricos',
          'Validar XML,recusas e replay nos executores', 'Auditar evidências,documentação e console']
task.update(updated_at=now, title='Documentos127 · schema restrito observado20/10', state=args.state,
            criterion=f'{args.completed}/6 marcos observados ({round(args.completed/6*100)}%) desta entrega. Fase4B parcial,0/8 grupos encerrados.',
            milestones=[{'title': title, 'complete': index < args.completed} for index, title in enumerate(titles)],
            finished=titles[:args.completed][-3:], pending=titles[args.completed:args.completed+3],
            next_step=args.next, evidence=args.evidence)
task['focus'] = {'summary': args.state, **{key: task[key] for key in ['finished', 'pending', 'next_step']}}
write('task-progress.json', task)
for name in ['objective-review.json', 'roadmap.json']:
    value = read(name)
    if not any('126' in str(item.get('current_review', '')) and item.get('reviewed_at') == '2026-10-22T11:11:46.035611+00:00' for item in value['history_reviews']):
        snapshot = {key: copy.deepcopy(item) for key, item in value.items() if key not in ['history_reviews']}
        value['history_reviews'].insert(0, snapshot)
    value['reviewed_at'] = now
    value['current_review'] = args.state
    if name == 'objective-review.json':
        value['criterion'] = ('127 validado no escopo documental local;' if args.completed == 6 else '127 em desenvolvimento;') + '126 validado no escopo local,122 último aceite integral.80 IDs examinados,0/8 grupos encerrados;produção bloqueada.'
        if args.completed >= 4:
            value['active_development_bundle'] = 'CONSUMPTION-PHASE4B-2026.10.22-127-draft1'
        for obj in value['objectives']:
            if obj['id'] in ['4B.6', '4B.7', '4B.8']:
                obj['current_work'] = args.state
                obj['current_work_evidence'] = args.evidence
                obj['reviewed_at'] = now
            if args.completed >= 5 and obj['id'] == '4B.7':
                note = '127:seisXML locais determinísticos/XSD-válidos no arquivo observado20/10,em três perfis;finNFSe,CST/cClassTrib e ordemcNBS versionados,sem assinatura/transmissão.'
                if note not in obj['finished']:
                    obj['finished'].append(note)
                obj['pending'][0] = 'Concluir demais campos/perfisNT009,ajustes,documentos separados da exportação parcial e documento definitivo da importação. As três projeções regulares127 já têm validação XSD local.'
            if args.completed >= 5 and obj['id'] == '4B.8':
                note = '127:305 testes/1310 verificações dirigidas,PHPStan0/Pint617,12 decisões/replays comparativos,seisXML válidos e12 históricos122 exatos;sem nova regressão integral.'
                if note not in obj['finished']:
                    obj['finished'].append(note)
    else:
        value['scope_note'] = '3/4A encerradas emSandbox;4B parcial,5–7 planejadas.127 documental ' + ('validado no escopo local' if args.completed == 6 else 'em desenvolvimento') + ';122 último aceite integral.0/8 grupos encerrados;produção bloqueada.'
        for phase in value['phases']:
            if phase['id'] == '4B':
                phase['summary'] = args.state + ' Fase parcial,0/8 grupos formalmente encerrados.'
    write(name, value)
print(json.dumps({'updated_at': now, 'observed_milestones': args.completed}))
