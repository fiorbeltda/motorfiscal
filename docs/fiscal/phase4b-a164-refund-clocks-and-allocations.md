# A164 — devoluções padrão/fallback e pelo adquirente

Entrega local da frente 1, sobre A159–A163; bundle `2026.11.23-a164-draft1`, contrato `RTC-REFUND-DEADLINES-A164-V1`. Abrange os requisitos 4B.3.7 e 4B.3.8 de forma incremental. Não encerra os 44 requisitos da frente ou libera produção.

## Regras e valores

O cálculo aceita um relatório de recolhimento já reconciliado A157 ou um livro split A158–A161 e verifica seu replay integral. Cada pedido exige aviso documental atribuído à autoridade do tributo, pagamento, excedente, valor alocado, calendário e alocações dos comprovantes de transferência. O motor exige que esse contexto corresponda exatamente ao relatório de recolhimento produzido na mesma decisão; não basta um relatório válido de outra operação ou data de conhecimento.

As fontes recapturadas são o [Decreto 12.955, arts.29 e 36](https://www2.camara.leg.br/legin/fed/decret/2026/decreto-12955-29-abril-2026-799019-normaatualizada-pe.html) para CBS e a [Resolução CGIBS 6, arts.29 e 36](https://www.cgibs.gov.br/upload/arquivos/202604/30084927-res-cgibs-n-6-30-abr-2026-regulamenta-o-ibs.pdf) para IBS. O prazo é de três dias úteis, com marcos distintos:

| Procedimento | Marco inicial documentado |
|---|---|
| Padrão/fallback, art.29 §5 II b | Mais recente entre liquidação financeira e vinculação correta |
| Padrão/fallback, atraso comprovado, art.29 §6 | Mais recente entre recebimento da informação pela autoridade e vinculação correta |
| Adquirente, art.36 §3 II | Pagamento do recolhimento |
| Adquirente, atraso comprovado, art.36 §4 | Recebimento da informação pela autoridade |

A demora não é inferida de uma diferença entre timestamps: exige declaração explícita e prova própria. No fallback, exige-se ainda o registro da impossibilidade da consulta. A confirmação de vinculação correta pela autoridade é distinta do vínculo originalmente informado ao arranjo; pode ocorrer após a liquidação. Não se reescrevem os contratos A158–A161 nem se desloca o repasse operacional D+1 da A163.

Nos repasses consolidados, a atribuição do excedente a um pagamento é fornecida explicitamente. O total atribuído não pode exceder o excedente documentado nem a segregação do pagamento para o tributo. Transferências alocadas não podem ultrapassar o recibo ou a parcela de devolução; a mesma transferência pode ser dividida entre parcelas com conservação, sem ser contabilizada duas vezes. Boletos A161 usam o recibo do pagamento individual vinculado ao informe e ao repasse corretos.

`FULL_EXCESSES` exige cobertura exata de todos os excedentes positivos e de todas as transferências existentes no livro fornecido. `SELECTED_EXCESSES` mantém a cobertura parcial explícita: informa o valor ainda sem alocação de transferência, mas não declara saldo pendente ou atraso financeiro integral dessa seleção. Datas têm precisão de dia, sem horário de expediente inventado. Resultados não autenticam a autoridade, enviam dinheiro, apropriam crédito ou julgam responsabilidade jurídica.

## Ativação e evidência

Fato e pergunta próprios: `rtc.refund_deadline_record`, um livro A164. Política, fontes e hashes estão no bundle; o catálogo de calendários A163 é preservado. Comando: `fiscal:rtc:assess-refund-deadlines {input}`. O consumidor recebe `collection.refund_deadlines164` e o trace `REFUND_DEADLINES164`. Contratos anteriores continuam compatíveis.

Validação observada: **557 testes / 1.689 asserções** dirigidos cumulativos; 43 testes / 120 asserções próprios A164. PHPStan global serial sem erros; Pint aprovado; 12 modos CLI repetidos por bytes, replay e 12 recusas de adulteração. Os oráculos independentes verificam 32 resultados Decimal e sete contagens de datas. Não é regressão integral final: execução local PHP8.5 e Docker indisponível.

Reprodução: `python scripts/agent_a/validate_split160.py --increment A164 --php C:/tools/php85/php.exe --output D:/ProjetoSaasExports/novo-diretorio`.

Recibo terminal: `D:/ProjetoSaasExports/front1-a164-20261123-final-v1/validation-a164.json`. Fontes, inventário SHA256, análise estática e páginas IBS 26/29 renderizadas e conferidas: `D:/ProjetoSaasExports/front1-a164-20261123-source-v1`. A Resolução CGIBS13 foi conferida: altera o art.617, não os dispositivos de contagem usados aqui; sua vigência não substitui prova de implantação do split.

## Dependências e continuação

Nenhum calendário oficial de expediente de devolução foi qualificado nesta entrega. Fontes bancárias, pontos facultativos gerais e calendário de contencioso não são automaticamente aplicáveis a esse ato. Isso não afirma inexistência de calendário oficial. A dependência de catálogo e sua integração estão explicitadas em `agent-a/20261123/integration-dependencies-A164.json`; as demonstrações positivas usam dados sintéticos e permanecem retidas como `RTC_SYNTHETIC_TEST_ONLY`.

Conforme o roteiro da pessoa1, o desenvolvimento independente segue para operações combinadas: importação, exportação, eventos, reconciliação de valores e decisão por fornecimento. Permanecem a qualificação externa, exclusões ANS e a auditoria individual dos demais requisitos. Fases3/4A encerradas;4B em andamento;0/8 grupos encerrados;5–7 planejadas.
