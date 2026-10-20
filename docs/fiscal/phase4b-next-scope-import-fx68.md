# Conversão cambial na importação integrada — 68

Objetivo: substituir o bloqueio incondicional de importações de serviços digitais em moeda estrangeira por uma conversão temporalmente qualificada, integrada à base IBS/CBS, alocação brasileira, papéis, crédito quando aplicável, trace e projeção documental local. Preservar o comportamento dos bundles anteriores e não atribuir a um número informado autenticidade de cotação oficial.

A pesquisa67 preservou LC214 e regulamentos de CBS/IBS, documentação da API PTAX, registro de dez moedas e respostas brutas para USD/EUR/AUD de20–28/09/2026. Cada consulta trouxe30 boletins, dos quais seis de fechamento, sem paginação pendente. Os18 fechamentos foram normalizados como valores decimais e vinculados aos hashes brutos. A pesquisa não ativou taxas nem alterou a aplicação.

O art.13, §4º, dos regulamentos distingue USD/PTAX, outras moedas com taxa de venda divulgada pelo BCB e moedas sem cotação no país, convertidas primeiro para USD. O incisoII refere expressamente a última taxa disponível ao incisoI; não extrapolar essa referência silenciosamente para moedas não USD. Qualificar a regra e a evidência que autorizam qualquer fallback. A regra aduaneira de bens materiais, art.81/CBS e art.70/LC214, tem fundamento próprio e não deve ser aplicada automaticamente à importação de serviços digitais.

Critérios de conclusão do desenvolvimento:

1. Verificar fontes primárias, hashes, regras e vigências. Distinguir data legal do fato gerador, data da cotação, publicação, observação e conhecimento; nunca usar cotação atual retroativamente.
2. Implementar qualificador de cotação e conversor determinístico. Exigir venda e boletim apropriado; validar unidade BRL por moeda, contexto de moeda e completude da consulta. Qualificar os caminhos de USD, moeda com cotação BCB e moeda sem cotação com conversão documentada em USD, mantendo recusas para prova insuficiente.
3. Preservar valor/moeda originais, taxa(s), fonte(s), datas, identificadores, hashes e memória. Definir precisão intermediária e final compatível com o caminho RTC. Não confundir paridade com cotação BRL por unidade; não usar valores binários de ponto flutuante para o cálculo monetário.
4. Vincular prova ao fornecedor, importador, operação e documento. Integrar valor convertido antes de ajustes e alocação brasileira; explicitar a moeda/unidade de ajustes, reembolsos e reconciliação com o valor da operação, evitando misturar USD com BRL.
5. Ativar por política e bundle próprios, com facts/perguntas próprios. Conservar as entradas históricas sem política FX e os resultados/replays anteriores. Preservar ISS/Fase4A e bloqueios documentais/operacionais que continuem aplicáveis.
6. Cobrir cotações diretas, datas sem boletim, informação futura, compra em vez de venda, boletim intermediário, moeda/taxa/hash/unidade divergentes, alocação parcial, valor da operação inconciliado, prova cambial ausente e caminhos não cotados sem fonte suficiente. Conferir nominal IBS estadual/municipal e CBS separadamente.
7. Capturar cenários, snapshots, trace, memória e propostas documentais próprias; reproduzir integralmente. Executar testes, análise estática, formatação e regressões pertinentes; executar suíte completa quando houver alteração de aplicação antes da aceitação local.

A implementação geral continua limitada às rotas que forem efetivamente pesquisadas e qualificadas, mantendo as demais em `ACTION_REQUIRED` com fatos/perguntas específicos. Uma captura PTAX não comprova cumprimento documental, crédito apropriado, autenticidade de documento fiscal ou recolhimento. Não concluir a Fase4B apenas com esse escopo. Produção, assinatura e transmissão permanecem bloqueadas.
