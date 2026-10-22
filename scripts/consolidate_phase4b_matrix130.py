"""Append130 candidate evidence; keep all original requirements and unresolved fiscal obligations."""
import copy
import hashlib
import json
import os
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
source = ROOT / 'storage/app/phase4b-import129-development-20261022/requirements80-current-matrix.json'
receipt = ROOT / 'storage/app/phase4b-import130-development-20261022/import130-candidate-validation.json'
output = receipt.parent / 'requirements80-current-matrix.json'

def read(p):
    return json.loads(p.read_text(encoding='utf-8-sig'))

def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

assert sha(source) == 'e6b817b9e415c38abed7601696aa8c077d7d7b61d5fd596d8ee4b0ebc42624c3'
record = read(receipt)
assert record['document_obligation_complete'] is False and record['phase4b_complete'] is False
baseline = read(source)
result = copy.deepcopy(baseline)
evidence = {'package': '130', 'path': str(receipt.relative_to(ROOT)).replace('\\','/'), 'sha256': sha(receipt), 'status': record['status']}
updates = {
    '4B.7.1': '127/128 preservam projeções regulares e adquirente estrangeiro.130 acrescenta candidato XML de importação pelo tomador,com prestador estrangeiro/COMEX/NBS,a partir do snapshot não final129. Concluir obrigação documental real,campos de fatura/câmbio,ISS por documento,demais perfis/par da exportação parcial e ajustes;XSD-válido não encerra essas pendências.',
    '4B.8.10': 'Projeções127/128 preservadas.130 produz candidato local de importação determinístico/XSD-válido,sem promover ACTION_REQUIRED a decisão final. Completar documentação/obrigação integrada,demais perfis e exportação parcial;candidate_validated não é aceitação fiscal da operação.'}
for old, item in zip(baseline['requirements'], result['requirements'], strict=True):
    if item['id'] in updates:
        item['remaining_before_scoped130'] = item['current_remaining_gap']
        item['current_remaining_gap'] = updates[item['id']]
        item.setdefault('subsequent_scoped_evidence', []).append(evidence)
    else:
        assert old == item
    assert old['own_review'] == item['own_review'] and not item['formal_completion_approved']
result.setdefault('subsequent_scoped_evidence', []).append(evidence)
result.update(created_at=datetime.now(timezone.utc).isoformat(), status='ORIGINAL80_WITH_CANDIDATE130_EVIDENCE_NOT_FUNCTIONAL_CLOSURE',
    baseline_matrix={'path': str(source), 'sha256': sha(source)}, requirements_with_scoped130_updates=list(updates),
    scope_note='80 itens examinados;130 atualiza duas pendências com candidato não final. Revisões,17 correções e126–129 preservados;0/8 grupos encerrados,produção bloqueada.')
assert len(result['requirements']) == 80 and result['formal_phase4b_groups_closed'] == 0 and not result['phase4b_complete']
with output.open('x', encoding='utf-8', newline='\n') as handle:
    handle.write(json.dumps(result, ensure_ascii=False, indent=2)+'\n');handle.flush();os.fsync(handle.fileno())
print(json.dumps({'requirements':80,'updated':2,'formal_groups_closed':0,'sha256':sha(output)}))
