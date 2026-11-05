# A158 — ciclo de split payment

Base Git f501885153d64f77ea8a7571fa680d5c9f43e970, remoto fiorbeltda/motorfiscal,
branch codex/fiscal-engine-bootstrap; fetch05/11 sem avanço. As12 alterações locais
A157 estão preservadas no ZIP externo SHA256
516fcadd5a040a1176e52636a3c594d815cde6b1fe2a718df142ee853deb2371.
Índice Git real vazio; nenhum processo de teste/PHPStan vivo no início.

IDs:4B.3.7 principal;4B.1.5,4B.3.10,4B.4.11 e4B.4.14 interfaces.
Estado: elegibilidade/implantação em TransitionCatalog e CollectionDecisionEngine,
sem ciclo material padrão/simplificado/parcelas na matriz44 atual. A157 é distinta.

Objetivo: implementar ciclo local de segregação/recolhimento por transação/operação,
IBS/CBS separados; consulta padrão/indisponibilidade e reconciliação de recebimentos;
procedimento simplificado com percentual explicitamente qualificado, nunca inventado;
parcelas e vínculos, responsabilidades, sobra/saldo e trace/replay. Respeitar etapas
técnicas oficiais e não converter ensaio em implantação real.

Antes de escolher fórmula/critério de parcelas, conferir LC214 arts31–35, regulamentos
CBS/IBS e documentação operacional primária atual. Lacuna/contradição jurídica não será
preenchida por escolha arbitrária de implementação. Percentual futuro/ato não comprovado
gera exigência explícita, não valor presumido. Sem pedidos de permissão para pesquisa pública.

Caminhos exclusivos A previstos:
- app/Modules/Fiscal/ConsumptionReform/SplitPayment158.php
- app/Modules/Fiscal/ConsumptionReform/SplitTransaction158.php
- app/Modules/Fiscal/ConsumptionReform/SplitLedger158.php
- app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php
- app/Console/Commands/AssessSplitPayment158Command.php
- tests/Fiscal/Unit/SplitPayment158Test.php
- tests/Fiscal/Unit/SplitTransaction158Test.php
- tests/Fiscal/Unit/SplitLedger158Test.php
- tests/Fiscal/Integration/SplitPayment158IntegrationTest.php
- scripts/agent_a/split158_fixture.php
- scripts/agent_a/verify158.py
- docs/fiscal/agent-a/20261105/task-A158-manifest.md
- docs/fiscal/agent-a/20261105/split158-source-review.md
- docs/fiscal/agent-a/20261105/split158-results.md
- docs/fiscal/agent-a/20261105/validation-A158.json
- docs/fiscal/agent-a/20261105/handoff-A158-to-B.md
- docs/fiscal/agent-a/20261105/console-proposal-A158.json
- docs/fiscal/agent-a/20261105/resume-after-A158-pause.md

Somente leitura: A157 aceito localmente e seu pacote, todos os componentes/recursos de B,
factories/infra, fontes aceitas, matriz80/monitoramento. A é escritor atribuído do motor;
nenhuma assinatura existente será alterada. Ativação só em nova versão por B. A prepara
contratos/fatos/perguntas em relatório e candidatos exclusivamente em memória.

Testes: positivos de cálculo, ausência/contradição de fonte/valor, datas de operação,
documento/parcelas, pagamento antecipado de recebível sem deslocar liquidação original,
ordem de débitos no simplificado e calendário quando qualificado; determinismo, oráculo
Decimal, CLI e compatibilidade A157/A156/B23. Nenhum grupo/produção encerra por esta task.

Evidências volumosas: D:/ProjetoSaasExports/agent-a-A158-split-payment-20261105-v1.
Sem commit/push/merge nesta retomada. Console/matriz recebem proposta a B, sem escrita concorrente.

Incremento seguinte ao corte aritmético imutável: `SplitTransaction158` qualifica
vínculo documental/parcela, retornos da plataforma por versão/processamento, janela
de correção e marco de liquidação. Testes novos exclusivos A no caminho acima.
O contrato da transação não substitui conciliação de repasse nem modelo simplificado;
ambos continuam no objetivo. Sem arquivos novos de B nem escrita no monitoramento.

Incremento de conciliação: `SplitLedger158` confere cronograma de uma operação,
informes com totais próprios e de outros documentos, repasse consolidado por PSP/arranjo/
tributo, aplicações de débito e excesso/transferências documentados. Nenhuma fração de
lote é presumida como extinção da operação. Fontes: Operações§6.2 e Tempos§6.1–6.2,
LC214 arts27/31–34; fontes/hashes já preservados. Proporcionalidade do cronograma
inicial só aceita alocações exatas em centavos neste contrato; arredondamento residual
permanece lacuna explícita, sem inventar regra de distribuição. Prazos sem calendário
qualificado permanecem não avaliados. Simplificado/revisões fiscais/integração seguem
no objetivo original, sem promoção dos44 requisitos a completos.

Refinamento após revisão do primeiro livro: a conferência estrita de proporcionalidade
não deve impedir a conciliação matemática de registros efetivamente fornecidos quando
as alocações somam ao débito, mas há frações de centavo. O próximo corte manterá os
valores fornecidos, registrará o desvio racional de cada parcela e marcará a
proporcionalidade como NÃO COMPROVADA; não distribuirá residual automaticamente.
Diferença de soma do cronograma versus débito continua contradição. Esse refinamento
não autoriza uso fiscal oficial nem fecha a pendência legal de arredondamento.

Pausa solicitada pelo usuário em05/11/2026 após validação do livro. Autorizado commit
do desenvolvimento A157/A158 preservado neste clone; esta nova autorização supera
a proibição anterior de commit para este conjunto. Nenhum push solicitado nesta ordem.
