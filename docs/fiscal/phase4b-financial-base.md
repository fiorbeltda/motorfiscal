# Base financeira — implementação em andamento

Estado corrente — Candidato27 (06/10/2026): posição variável com memória diária reproduzida integrada à base financeira e ao D2101, com movimentos brutos reconciliados ao D1101 e origem fiscal única. Primeira aquisição, custo distinto do saldo contábil, compras/saídas/amortização/cupons/fundos e múltiplos títulos cobertos. Suíte1.082/8.294, PHPStan sem erros, Pint373,38 casos/120 corpos XML/20 replays; regressões27/26/25 e3.114 históricos preservados. Diferenças de alienação e resíduos exigem tratamento próprio. Gate47/47 fontes, `ready=false`,119 bloqueios; Fase4B parcial. [Aceitação e limites](phase4b-dere-d2101-variable-integration.md).

Histórico — Candidato26 (06/10/2026): memória diária de custo médio e movimentações, com compras, vendas, amortizações, cupons, curvas contábeis e benchmark Selic por convenção local explícita. Custo de aquisição separado do saldo contábil; ganho de venda registrado com tratamento tributário pendente. Suíte1.044/7.937, PHPStan sem erros, Pint372,44 casos/16 replays/seis guardas; regressões26/25 e2.849 históricos preservados. Posição variável ainda não ativada na base financeira ou XML mensal. Gate47/47 fontes, `ready=false`,119 bloqueios; Fase4B parcial. [Aceitação e limites](phase4b-dere-d2101-daily-memory.md).

Histórico — Candidato25 (06/10/2026): rendimento art.195 de custo constante qualificado por emissor/oferta/posição, integrado à base financeira e ao D2101 com origem fiscal única. Base conjunta R$798,17 no exemplo, D2101 R$98,17; gross contábil preservado e perdas sem compensação entre títulos. Arredondamento DeRE8/2 HALF_EVEN, XSD1.3.0. Suíte998/3.315, PHPStan sem erros, Pint369,39 casos/72 corpos XML/12 replays; regressões25/24/23 e2.593 históricos preservados. Gate47/47 fontes, `ready=false`,119 bloqueios; Fase4B parcial. [Aceitação e limites](phase4b-dere-d2101-yield-integration.md).

Histórico — Candidato24 (06/10/2026): cálculo D2101 por título para posição de custo constante durante todo o mês, usando21 taxas diárias BCB SGS11 reais de setembro2026 e provas de posição sintéticas. TetoR$108,17 para custoR$10.000; baseR$98,17 com variação150/PIS10. Suíte959/3.108, PHPStan sem erros, Pint366,22 casos/sete replays; regressões24/23 e2.448 históricos preservados. Integração à base financeira e XML mensal com rendimentos ainda pendente. Gate47/47 fontes, `ready=false`,119 bloqueios; Fase4B parcial. [Aceitação e limites](phase4b-dere-d2101-constant-cost.md).

`FinancialBaseEngine` calcula a base de um livro financeiro mensal previamente qualificado. Está ativado no candidato11 pela composição de enquadramento e base, conservada na trace enquanto o cálculo/documento financeiro completo permanece pendente. A Fase4B e a família financeira continuam incompletas. Ver `phase4b-financial-scope-integration.md`.

`FinancialRegimeQualifier` qualifica fornecedor183, operação182 e exceções184; `FinancialOperationEngine` compõe essa qualificação com a base e exige vínculo por fornecedor/documento/família. Validação anterior à ativação:76 testes/169 asserções;38/86 de enquadramento,6/14 de composição e32/69 de base.

## Qualificação e composição implementadas

A prova identifica fornecedor, documento, categoria do prestador, supervisão, natureza da operação, período e observação. Fornecedor supervisionado exige registro eficaz; fornecedor não supervisionado exige categoria legal e, quando aplicável, prova de atividade econômica/habitual/profissional ou registro de correspondente. Entidade de previdência exige prova de não enquadramento na exclusão do art26 §9. A autorização da operação é independente do registro do fornecedor. `FINTECH` não é categoria suficiente.

As17 categorias do art182 são encaminhadas à família de base pertinente. Isso prova o enquadramento, não a implementação de todas essas bases: só192/193 estão calculadas nesta camada. Seguro de saúde não é encaminhado automaticamente ao regime financeiro. Demais bases retornam necessidade de regra própria no calculador.

Serviço exclusivo de banco/caixa econômica remunerado por tarifa/comissão exige prova da exclusividade regulamentar e segue184 caput/§1. Manutenção/encerramento de conta pré/pós-paga por instituição de pagamento com tarifa/comissão segue184 §2. Outro serviço comprovadamente fora182, inclusive software prestado por instituição financeira, segue184 §3. Não se concede a exceção bancária à cooperativa de crédito.

Na composição, serviço geral não aceita livro da base específica. Serviço específico exige livro da família correspondente, do mesmo fornecedor e vinculado ao documento mensal avaliado. Um livro de outra entidade, documento ou família é rejeitado antes do cálculo. A composição retorna qualificação e base; ainda não gera decisão IBS/CBS/ISS/4A, crédito ou documento homologado.

## Contrato implementado

Famílias `CREDIT_FX_SECURITIES_ART192` e `SECURITIZATION_FACTORING_ART193`. Exige livro completo em BRL, fornecedor, provas do enquadramento, reconhecimento temporal e valores líquidos de IBS/CBS/ISS. Período mensal completo encerrado, datas reconhecidas dentro do mês e observação até o conhecimento. IDs e documento/tipo duplicados são rejeitados; cada documento/tipo deve estar consolidado em uma entrada.

Para o art192, exclui principal de crédito e de captação. Alienação de títulos computa venda menos custo. Receita de recuperação/reversão somente entra quando a provisão/baixa anterior foi deduzida. Encargo de dívida estornado é adicionado quando previamente deduzido. Valor justo excedente ao rendimento produzido e não realizado exige subconta e fica separado; valor realizado e derivativos têm entradas próprias.

Despesas dedutíveis são limitadas aos tipos legais, operação autorizada, limites operacionais e prova. Despesa administrativa é rejeitada. Assessores/consultores/correspondentes exigem categoria/registro e ausência de vínculo como empregado/administrador. Encargos de instrumento patrimonial são rejeitados. Perdas de crédito exigem valor de mercado e, no art192, elegibilidade da entidade e dedutibilidade do IR previamente qualificadas. Não calcula automaticamente os requisitos de IR ou regulamentação bancária.

Para o art193, usa deságio de liquidação antecipada como receita, custos de securitização enumerados e prestados por terceiros, captação e perdas qualificadas. Receita financeira genérica não substitui o deságio. Saldo negativo é preservado na memória; compensação futura de perdas do art193 §3 ainda precisa de contrato específico, sem concessão automática de carryforward.

No art192 §8, crédito cooperativa/associado com recursos próprios ou públicos/direcionados/equalizados/oficiais comprovados exclui a receita e veda a dedução da captação correspondente. É uma exclusão de operação própria, independente da opção271. Para opção271 provada, art188 reverte as deduções elegíveis pelo valor das operações zero sobre o valor de todas as operações da cooperativa. Conserva numerador/denominador e arredonda o valor monetário uma vez;200×1/3 resulta66,67.

Retorna receitas, deduções, exclusões, valores diferidos, reversão188, base líquida assinada e memória por entrada. Não presume alíquota, crédito do tomador, pagamento, transporte de saldo ou escrituração externa. `posting_performed=false`.

## Fonte e validação

Fonte primária: [LC214 consolidada, arts185–188 e192–193](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html), arquivo preservado `lc214-camara-observed-20261001.html`, SHA256 `dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`.

Testes dirigidos `FinancialBaseEngineTest.php`:32 casos/69 asserções; incluem duplicidade, titular, datas, ausência de qualificação, categorias, efeito188, exclusão192 §8, base negativa e fonte futura/revogada. Pint dois arquivos, PHPStan e `git diff --check` sem erros na implementação final deste recorte. A suíte completa não foi repetida.

## Trabalho seguinte obrigatório

1. Completar os demais perfis183/182/184 na integração e sua classificação/documentação; a ativação do enquadramento/base no11 não fecha os tributos específicos.
2. Conectar o livro à decisão integrada e selecionar alíquotas por fonte/vigência. Soma233 não autoriza inventar o componente IBS ou CBS.
3. Créditos específicos190/194–198, operações públicas195 e transporte de perdas193 §3; demais bases financeiras próprias continuam pendentes.
4. ISS conforme serviço efetivo e documento municipal; DeRE versus outros documentos conforme perfil e atos/layouts oficiais completos. Não converter automaticamente livro mensal em DPS por operação.
5. Corpus integrado positivo/negativo, snapshots/replay e homologação local documental da família.

A [página oficial da DeRE](https://www.gov.br/sped/pt-br/assuntos/documentos-fiscais/dere) foi consultada nesta continuidade: ela descreve apuração por margens e deduções próprias. Resultados de busca sobre cronogramas e versões não foram ativados como atos. A obtenção do esclarecimento do CGIBS sobre prazos expirou; nenhuma data de entrega foi presumida. É necessário obter e conferir a documentação integral antes de vincular novos hashes.
