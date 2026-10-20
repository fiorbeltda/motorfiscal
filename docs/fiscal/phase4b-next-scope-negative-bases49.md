# Bases negativas financeiras — preparação49

Objetivo: qualificar origem, validade e consumo mensal de bases negativas financeiras; manter IBS e CBS separados e projetar as opções do D1199 em conhecimento próprio, com snapshots, replay, XML local e regressão. O escopo permanece aberto até essa integração ser demonstrada.

A base normativa geral é a LC214, arts300/301/303, e, para CBS, o Decreto12955, arts273§3/274/278/280. O art193§3 trata de perdas específicas de factoring/securitização e não autoriza transportar indiscriminadamente despesas ou compensar resultados entre títulos. Fontes: [LC214 consolidada](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html) e [Decreto12955 consolidado](https://www2.camara.leg.br/legin/fed/decret/2026/decreto-12955-29-abril-2026-799019-normaatualizada-pe.html).

A preparação está congelada em `storage/app/phase4b-negative-base49-preparation-20261009/research-contract.json`, com SHA dos documentos oficiais, extrações identificadas por página física e estrutura real dos schemas D1199/D9199. O script `scripts/prepare_phase4b_negative_base49_research.py` reproduz os mesmos arquivos e falha se um artefato já existente divergir.

Critérios de implementação e aceitação:

1. Catalogar inicialmente as raízes financeiras1005I/C e1010I/C da Tabela12. Provar o codBC formador, o vínculo com ISS e a competência; não usar uma expressão regular como substituto da classificação fiscal.
2. Obter e qualificar o retorno de origem D9199. Conservar fornecedor, período, codBCN exato, sequência de fechamento, fonte, integridade e saldo. Uma chave sintaticamente válida não prova recepção, saldo ou autenticidade. Os testes sintéticos devem declarar essa condição.
3. Qualificar o último dia útil do período de origem e a fronteira do prazo de cinco anos. Não substituir calendário comprovado por último dia de semana. Dedução somente em períodos posteriores, sem atualização e na base da mesma natureza/tributo.
4. Separar geração da base negativa, base positiva corrente, dedução de saldos anteriores e saldo final. Provar conservação, consumo limitado à base positiva corrente, saldo restante e ausência de crédito ou dedução duplicados.
5. Implementar opção de não usar, PEPS automático e seleção manual. `gUtilizBCN` contém `infoBCN` e depois o irmão `detBCNeg`; os detalhes não ficam dentro de cada `infoBCN`. Método é obrigatório quando usar=1 e vedado quando usar=0. Cada raiz manual precisa de seus detalhes; detalhes de raízes automáticas ou desativadas são incompatíveis.
6. Em seleção manual, omitir `vUsarBCN` significa usar o disponível. Valor superior ao estoque deve ser limitado ao disponível com alerta, conforme leiaute1.3.0; não rejeitar automaticamente ou inventar o estoque. Preservar o limite solicitado no XML.
7. Reconciliar reabertura D1198 e novo fechamento com o estoque comprovado, sem repetir consumo nem inventar sequência oficial. Selecionar regras e fontes pela temporalidade própria.
8. Integrar a projeção mensal mediante política específica e bundle próprio; preservar o comportamento dos bundles antigos. Capturar positivos e negativos semânticos, snapshots/replays, XMLs/schema, regressão27/26/25/48, auditoria das fontes e gate bloqueado.

Primeira entrega implementada: `DereNegativeBaseClosingParametersValidator`, contrato `DERE-BCN-CLOSING-PARAMETERS-1.3.0-49`. Valida formato, raízes financeiras suportadas, método, duplicações, competência anterior e limites monetários no formato do schema; preserva o pedido recebido. Resultado `PARAMETERS_VALIDATED` não qualifica origem, saldo, expiração ou consumo. Está separado da rota mensal atual, que continua exigindo ausência de pedido de aproveitamento. Essa restrição só poderá mudar depois da qualificação efetiva e da integração testada.

O conhecimento comum48 mantém102 cenários/102 replays/595 XMLs e aceitação local. O candidato integral aceito permanece27. A parcela nova produzida apenas na alienação de dívida corporativa ofertada publicamente continua com classificação documental pendente; este novo escopo não encerra essa pendência nem o escopo28/Fase4B.8. Produção permanece bloqueada.
