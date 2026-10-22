"""Append129 evidence to current subject gaps without rewriting original reviews or claiming closure."""
import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / 'storage/app/phase4b-foreign128-development-20261022/requirements80-current-matrix.json'
RECEIPT = ROOT / 'storage/app/phase4b-import129-development-20261022/import129-scoped-validation.json'
OUTPUT = RECEIPT.parent / 'requirements80-current-matrix.json'

def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

assert sha(SOURCE) == 'ce9fbb59fc2d3063493e20f9f446601fc11c91c4f642f27cb29852387bb6fc6a'
receipt = read(RECEIPT)
assert receipt['status'] == 'SCOPED129_VALIDATED_NOT_FULL_PHASE_ACCEPTANCE' and not receipt['phase4b_complete']
baseline = read(SOURCE)
result = copy.deepcopy(baseline)
evidence = {'package': '129', 'path': str(RECEIPT.relative_to(ROOT)).replace('\\', '/'),
    'sha256': sha(RECEIPT), 'status': receipt['status']}
updates = {
    '4B.3.4': '129 vincula o contribuinte adquirenteBR ou destinatárioBR ao importador e valida referências específicas art64§5ºV/VI. Restam cadastro/condições materiais,documentos completos,crédito e perfis/bases/alíquotas exigidos;sem inventar parâmetros indisponíveis.',
    '4B.3.6': '129 separa contribuinte e responsabilidade solidária condicional do fornecedor estrangeiro,com plataforma previamente qualificada preservada. Concluir as demais hipóteses/condições,efeitos documentais e de pagamento;identificação e fundamento não provam extinção.',
    '4B.3.10': '129 integra papéis separados na importação digital qualificada,com recusa de contribuinte declarado conflitante e fundamento incompatível. Completar documentos,condições materiais e consistência de papéis nos demais perfis;rota declarada não é pagamento efetivo.'}
for item in result['requirements']:
    if item['id'] in updates:
        item['remaining_before_scoped129'] = item['current_remaining_gap']
        item['current_remaining_gap'] = updates[item['id']]
        item.setdefault('subsequent_scoped_evidence', []).append(evidence)
        assert item['formal_completion_approved'] is False
result.setdefault('subsequent_scoped_evidence', []).append(evidence)
result.update(created_at=datetime.now(timezone.utc).isoformat(),
    status='ORIGINAL80_WITH_SCOPED129_SUBJECT_UPDATES_NOT_FUNCTIONAL_CLOSURE',
    baseline_matrix={'path': str(SOURCE), 'sha256': sha(SOURCE)}, requirements_with_scoped129_updates=list(updates),
    scope_note='80 itens examinados,não aprovados.129 atualiza três pendências de sujeitos;revisões originais,17 correções e atualizações126–128 preservadas.0/8 grupos encerrados;produção bloqueada.')
assert len(result['requirements']) == 80 and result['formal_phase4b_groups_closed'] == 0
for old, new in zip(baseline['requirements'], result['requirements'], strict=True):
    assert old['id'] == new['id'] and old['requirement'] == new['requirement'] and old['own_review'] == new['own_review']
    if old['id'] not in updates:
        assert old == new
assert not result['phase4b_complete'] and not result['production_ready']
with OUTPUT.open('x', encoding='utf-8', newline='\n') as handle:
    handle.write(json.dumps(result, ensure_ascii=False, indent=2)+'\n')
print(json.dumps({'requirements': 80, 'updated': len(updates), 'formal_groups_closed': 0, 'sha256': sha(OUTPUT)}))
