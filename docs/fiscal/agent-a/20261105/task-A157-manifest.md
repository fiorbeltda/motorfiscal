# A157 — recolhimento pelo adquirente, LC214 art36

Base f501885153d64f77ea8a7571fa680d5c9f43e970, branch codex/fiscal-engine-bootstrap,
origin https://github.com/fiorbeltda/motorfiscal.git. Árvore inicial limpa, fetch sem avanço,
contêiner exclusivo ativo em PHP8.5; nenhum teste/PHPStan vivo observado.

IDs:4B.3.8 principal;4B.3.2/4B.3.10 e4B.4.14 interfaces. A148 entrega elegibilidade;
A156 confirma lacuna no exercício/vínculo do recolhimento com débito/extinção.
Não encerrar o item por mais uma guarda de entrada: implementar reconciliação monetária
de recolhimentos por operação, IBS/CBS separados, histórico completo de extinções anteriores,
saldo remanescente e excedente destinado ao fornecedor, com transferências observadas separadas.
O contrato exige moeda BRL, instante da operação e corte temporal do histórico. Todos os
recolhimentos do adquirente da operação ficam na lista integral; o histórico de abertura
exclui esses recolhimentos, para não esconder excedentes anteriores. Baixas de outras
modalidades após o corte entram como eventos observados intercalados, nunca como planos de crédito.

Escrita exclusiva A neste ciclo:
- app/Modules/Fiscal/ConsumptionReform/AcquirerCollection157.php
- app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php
- app/Console/Commands/ReconcileAcquirerCollection157Command.php
- tests/Fiscal/Unit/AcquirerCollection157Test.php
- tests/Fiscal/Integration/AcquirerCollection157IntegrationTest.php
- scripts/agent_a/collection157_fixture.php
- scripts/agent_a/verify157.py
- docs/fiscal/agent-a/20261105/task-A157-manifest.md
- docs/fiscal/agent-a/20261105/validation-A157.json
- docs/fiscal/agent-a/20261105/collection157-results.md
- docs/fiscal/agent-a/20261105/handoff-A157-to-B.md
- docs/fiscal/agent-a/20261105/console-proposal-A157.json

Somente leitura: componentes B/créditos, factories, bundles/catálogos/fatos/perguntas aceitos,
matriz80, monitoramento, A144–A156 e fontes históricas. Nenhuma assinatura existente será alterada.
Interface nova proposta a B: ledger ARRAY e política exata próprios, somente em nova versão.
Valores nominais e créditos não serão reescritos pela reconciliação de recebimentos.

Fonte: LC214 consolidada Câmara, arts27 parágrafo únicoII e36, reconsultada05/11/2026.
Capturar bruto/hash no D:. Art36§1 liga exercício da opção ao recolhimento;§3 limita aplicação
ao débito da operação e destina excedente ao contribuinte. Não autenticar fontes, enviar dinheiro,
lançar baixa fiscal ou inventar data de três dias úteis sem calendário oficial qualificado.

Validação: positivos parciais/totais/excedentes; histórico e duplicidade; partes/documento/transação;
segregação IBS/CBS; datas/ordem/conhecimento; transferências; tampering/replay; CLI e Decimal independente;
gate desligado e compatibilidade A148/B23. Evidências novas em
D:/ProjetoSaasExports/agent-a-A157-acquirer-collection-20261105-v1.

O ciclo completo de split payment segue lacuna distinta de4B.3.7, não transferida a B.
Console/matriz: proposta estruturada por A, aplicação pelo escritor B. Sem commit/push nesta retomada:
a autorização anterior era específica dos handoffs H6–H10 já publicados. Trabalho regular segue local.
