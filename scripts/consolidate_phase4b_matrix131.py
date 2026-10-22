"""Append131 schedule evidence without closing temporal or documentary requirements."""
import copy
import hashlib
import json
import os
from datetime import datetime, timezone
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
source=ROOT/'storage/app/phase4b-import130-development-20261022/requirements80-current-matrix.json'
receipt=ROOT/'storage/app/phase4b-schedule131-development-20261022/schedule131-draft2-scoped-validation.json'
output=receipt.parent/'requirements80-current-matrix.json'
def read(p):return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
assert sha(source)=='ab04865c32306f9046ff4ba849d7ef9b8ca0a835f25ce360fef9704bf9fb6436'
record=read(receipt);assert not record['phase4b_complete'] and not record['document_obligation_complete']
baseline=read(source);result=copy.deepcopy(baseline)
evidence={'package':'131-draft2','path':str(receipt.relative_to(ROOT)).replace('\\','/'),'sha256':sha(receipt),'status':record['status']}
updates={
    '4B.1.5':'131 separa a data do fato que seleciona o cronograma documental da disponibilidade de conhecimento da política;32 linhas/exceções doAto4 vinculadas,sem alterar as datas próprias dos tributos. ',
    '4B.1.6':'131 qualifica publicação/vigência doAto4 por seu texto integral e mantém os drafts anteriores;isso não encerra revogações/supersessões de todas as regras. ',
    '4B.7.1':'131 integra cronograma por perfil e regime do contribuinte brasileiro,com pergunta pelo destinatário quando ausente;cronograma resolvido não prova cumprimento ou corpo documental completo. '}
for old,item in zip(baseline['requirements'],result['requirements'],strict=True):
    if item['id'] in updates:
        item['remaining_before_scoped131']=item['current_remaining_gap']
        item['current_remaining_gap']=updates[item['id']]+item['current_remaining_gap']
        item.setdefault('subsequent_scoped_evidence',[]).append(evidence)
    else:assert item==old
    assert item['own_review']==old['own_review'] and not item['formal_completion_approved']
result.setdefault('subsequent_scoped_evidence',[]).append(evidence)
result.update(created_at=datetime.now(timezone.utc).isoformat(),status='ORIGINAL80_WITH_SCOPED131_SCHEDULE_UPDATES_NOT_CLOSURE',
    baseline_matrix={'path':str(source),'sha256':sha(source)},requirements_with_scoped131_updates=list(updates),
    scope_note='80 itens examinados;três pendências atualizadas com cronograma131. Revisões e remediações anteriores preservadas;0/8 grupos encerrados,produção bloqueada.')
assert len(result['requirements'])==80 and result['formal_phase4b_groups_closed']==0
with output.open('x',encoding='utf-8') as f:json.dump(result,f,ensure_ascii=False,indent=2);f.flush();os.fsync(f.fileno())
print(json.dumps({'requirements':80,'updated':3,'formal_groups_closed':0,'sha256':sha(output)}))
