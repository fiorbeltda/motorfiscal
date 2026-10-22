"""Preserve original80 reviews and append scoped126-128 evidence without closure."""
import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / 'storage/app/phase4b-credit122-development-20261021/requirements80-current-matrix.json'
OUTPUT = ROOT / 'storage/app/phase4b-foreign128-development-20261022/requirements80-current-matrix.json'
def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))
def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()
baseline = read(SOURCE)
result = copy.deepcopy(baseline)
evidence = []
for label, path in [
    ('126', 'storage/app/phase4b-catalog126-development-20261022/catalog126-scoped-validation.json'),
    ('127', 'storage/app/phase4b-dps127-development-20261022/dps127-scoped-validation.json'),
    ('128', 'storage/app/phase4b-foreign128-development-20261022/foreign128-scoped-validation.json')]:
    record = read(ROOT / path)
    assert record['phase4b_complete'] is False and record['full_suite_executed'] is False
    evidence.append({'package': label, 'path': path, 'sha256': sha(ROOT / path), 'status': record['status']})
updates = {
    '4B.6.1': 'Concluir qualificação oficial por vigência/ambiente e todos os mapeamentos obrigatórios.126–128 materializam a referência local1.03.00 e separam oAnexoC;isso não ativa ambientes oficiais.',
    '4B.6.2': 'Referência localRTC1.03.00 instalada em126–128,com40 linhas e fonte vinculada;122 preserva1.02.00. Restam vigências oficiais,seleção por ambiente,conciliação doAnexoC e classificação aplicável às famílias restantes.',
    '4B.7.1': '127 migrou os perfis regulares federal/educação/presencial para oXSD observado20/10;128 migrou adquirente estrangeiro/destinatárioBR comCOMEX. Restam documento definitivo de importação,par da exportação parcial,notas de ajuste e demais campos/perfis obrigatórios.',
    '4B.7.4': '127–128 preservam novos bytes de fonte,root/closure hashes,facetas e contratos sem alterar as versões históricas. Continuar versionando novas mudanças;essa prova estrutural não encerra aplicabilidade documental de todos os perfis.',
    '4B.8.10': 'Há projeção corrente127/128 efetivamente XSD-válida e determinística,com fatos originais preservados e replay. Concluir importação,exportação parcial e demais rotas documentais;não usar comprador estrangeiro como prova de exportação.'}
for item in result['requirements']:
    if item['id'] not in updates:
        continue
    item['remaining_before_scoped126_128'] = item['current_remaining_gap']
    item['current_remaining_gap'] = updates[item['id']]
    item.setdefault('subsequent_scoped_evidence', []).extend(evidence)
    assert item['formal_completion_approved'] is False
result.update(created_at=datetime.now(timezone.utc).isoformat(),
    status='ORIGINAL80_WITH_SCOPED126_128_UPDATES_NOT_FUNCTIONAL_CLOSURE',
    baseline_matrix={'path': str(SOURCE), 'sha256': sha(SOURCE)},
    subsequent_scoped_evidence=evidence, requirements_with_scoped126_128_updates=list(updates),
    scope_note='80 itens examinados,não aprovados. Cinco pendências atualizadas com126–128;demais revisões e17 correções históricas preservadas.0/8 grupos encerrados;produção bloqueada.')
assert len(result['requirements']) == 80
assert [(i['id'], i['requirement']) for i in result['requirements']] == [(i['id'], i['requirement']) for i in baseline['requirements']]
assert result['formal_phase4b_groups_closed'] == 0 and result['phase4b_complete'] is False and result['production_ready'] is False
with OUTPUT.open('x', encoding='utf-8', newline='\n') as handle:
    handle.write(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({'requirements': 80, 'updated_remaining_gaps': len(updates), 'sha256': sha(OUTPUT), 'formal_groups_closed': 0}))
