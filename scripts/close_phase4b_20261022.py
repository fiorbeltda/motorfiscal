"""Record the user-requested safe stop after terminal suite134; no fiscal changes."""
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
E = Path('D:/ProjetoSaasExports/phase4b-iss134-20261022-v1')
now = datetime.now(timezone.utc).astimezone().isoformat()
suite = json.loads((E / 'full-suite-v1/validation.json').read_text(encoding='utf-8'))
assert suite['complete'] and suite['tests'] == 9406 and not suite['changed_inputs'] and not suite['added_inputs']
receipt = 'storage/app/phase4b-iss134-development-20261022/iss134-scoped-validation.json'
matrix = 'storage/app/phase4b-iss134-development-20261022/requirements80-current-matrix.json'
summary = 'Parada segura solicitada pelo usuário:134 validado;9406 testes/145560 asserções,415 conferências.135 somente em ensaio externo;4B parcial,0/8 grupos encerrados,produção bloqueada.'
pending = ['Integrar135 após qualificar exclusões legais e demais tributos do preço.', 'Concluir câmbioISS,importação e demais mínimos dos80 requisitos.', 'Retomar somente por solicitação do usuário;nenhum processo de desenvolvimento ativo.']
for name in ['task-progress.json', 'objective-review.json', 'roadmap.json']:
    path = ROOT / 'resources/monitoring' / name
    data = json.loads(path.read_text(encoding='utf-8'))
    data['updated_at'] = now
    if name == 'task-progress.json':
        data['state'] = summary
        data['title'] = 'Checkpoint seguro134 — desenvolvimento interrompido a pedido do usuário'
        data['criterion'] = '6/6 marcos do corte134 verificados;nenhum percentual global4B;0/8 grupos encerrados.'
        data['milestones'] = [dict(item, complete=True) for item in data['milestones']]
        data['finished'] = ['Regressão integral134:9406 testes/145560 asserções;5179 entradas intactas.', 'Auditoria134:415 conferências,114 hashes;matriz80 preservada.', 'Ensaios135 externos preservados com hashes;nenhuma instalação135.']
        data['pending'] = pending
        data['next_step'] = pending[0] + ' Aguardar nova solicitação de retomada.'
        data['focus'] = {'summary': summary, **{k: data[k] for k in ['finished', 'pending', 'next_step']}}
        data['evidence'] = [receipt, matrix, 'docs/fiscal/phase4b-day-closure-20261022.md']
    else:
        data['reviewed_at'] = now
        data['current_review'] = summary
        if name == 'objective-review.json':
            data['criterion'] = '134 validado com regressão integral;122 aceite histórico preservado;80 revisões próprias,0/8 grupos encerrados;produção bloqueada.'
            data['current_requirements_matrix'] = {'path': matrix, 'sha256': hashlib.sha256((ROOT / matrix).read_bytes()).hexdigest()}
            for obj in data['objectives']:
                obj['reviewed_at'] = now
                if obj['id'] in ['4B.4', '4B.7', '4B.8']:
                    obj['current_work'] = summary
                    obj['current_work_evidence'] = [receipt, matrix]
        else:
            data['scope_note'] = '3/4A encerradas emSandbox;4B parcial,0/8 grupos encerrados;5–7 planejadas.134 regressão integral aprovada;desenvolvimento interrompido a pedido do usuário.'
            for phase in data['phases']:
                if phase['id'] == '4B':
                    phase['summary'] = summary
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')

prefix = f'Checkpoint em {now}: parada segura solicitada pelo usuário. Regressão134 encerrada:9.406 testes/145.560 asserções,5.179 entradas intactas;auditoria415/114 hashes. Corte134 validado;4B parcial,0/8 encerrados. Ensaios135 externos não integrados. Ver `phase4b-day-closure-20261022.md`.\n\n'
for name in ['implementation-status.md', 'phase4b-status.md', 'phase4b-original80-current-matrix.md', 'phase4b-iss134-versioned-document-values.md']:
    path = ROOT / 'docs/fiscal' / name
    text = path.read_text(encoding='utf-8')
    first, rest = text.split('\n', 1)
    text = first + '\n\n' + prefix + rest.lstrip('\n')
    text = text.replace('A regressão integral está\nem execução com inventário congelado;sem novo aceite integral antecipado.', 'Regressão integral134 aprovada:9406 testes/145560 asserções;inventário intacto.')
    text = text.replace('Regressão integral em execução;112 fontes conferidas,produção bloqueada.', 'Regressão integral134 aprovada:9406 testes/145560 asserções;112 fontes conferidas,produção bloqueada.')
    old = 'Enquanto não houver resultado terminal, não há aprovação dessa regressão ou\nrecibo final134. O aceite integral122 permanece histórico e separado desta\nentrega. Fontes, testes e recursos fiscais não devem ser editados durante a suíte.'
    text = text.replace(old, 'Encerrada em22/10 às19:40:16UTC com código0:9406 testes/145560 asserções;5179 entradas sem alteração ou adição. Auditoria415 conferências/114 hashes aprovada. Recibo `storage/app/phase4b-iss134-development-20261022/iss134-scoped-validation.json`. O aceite122 permanece histórico;esta regressão não encerra4B.')
    path.write_text(text, encoding='utf-8')

inventory = []
for base in [E / 'source-staging135-v1', E / 'full-suite-v1']:
    for path in sorted(base.rglob('*')):
        if path.is_file():
            inventory.append({'path': str(path), 'bytes': path.stat().st_size, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest()})
out = ROOT / 'storage/app/phase4b-iss134-development-20261022/checkpoint-external-inventory.json'
out.write_text(json.dumps({'created_at': now, 'external_files_preserved': inventory, 'staged135_installed': False, 'phase4b_complete': False}, indent=2) + '\n', encoding='utf-8')
print(json.dumps({'timestamp': now, 'files_preserved': len(inventory), 'receipt': receipt}))
