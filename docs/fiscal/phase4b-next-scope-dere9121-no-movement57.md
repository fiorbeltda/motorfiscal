# D9121 com D2101 sem movimento — 57

Objetivo: concluir a leitura e a composição mensal do D9121 quando o D2101 nativo declara `semTitulos=1`, mantendo retorno explícito, prova do livro vazio para as contas públicas, política temporal, cálculo e bloqueio de produção.

A falha foi reproduzida contra três candidatos21 preservados: dívida direta, fundo exclusivo e ambos os códigos. O leitor instalado consulta `semTitulos` diretamente sob o evento; o XSD e o projetor nativo colocam esse campo em `infoDebOpOfPublic/semTitulos`. A correção preparada fora da aplicação reconciliou os três retornos sintéticos com total0,00 e recusou total1,00, com reprodução exata. A evidência está em `storage/app/phase4b-dere9121-57-no-movement-preparation-20261013/native-guard-comparison.json`.

O leiaute de Eventos, página física126, obriga `infoTotTitOfPub` para inclusão/alteração e o dispensa na exclusão. A página física128 especifica `vApurTot=SOMA(vApur)` do D2101. Portanto, o caminho local deve exigir retorno explícito com total zero, sem tratar ausência de totalizador como zero.

Critérios:

1. Depois do término da suíte55, arquivar as fontes aceitas antes de corrigir o XPath e ampliar a seleção da composição. Preservar os hashes dos relatórios anteriores.
2. Promover a correção do leitor e acrescentar testes permanentes para as três formas sem títulos, total não zero, total ausente e rejeição da exclusão.
3. Reproduzir o projetor mensal completo e selecionar apenas `debt_no_movement_projection` qualificado, além do caminho de rendimentos já existente. Derivar total zero da declaração nativa validada, mantendo o D9121 explícito e seu vínculo de evento/PGCC.
4. Vincular recepção/processamento ao conhecimento capturado e à política sucessora apropriada. O leitor não estabelece autenticação nem prova de versão ativa governamental.
5. Capturar snapshot, entrada mensal, retorno, solicitação, proposta e seis XMLs próprios; provar replays, ausência de receita pública adicionada e conservação dos estoques/débitos brutos.
6. Executar testes do novo comando e dos componentes, regressões do corpus público56 e dos candidatos27/26/25, qualidade e suíte integral antes da aceitação local.

Este avanço não encerra versões sucessoras do D2101, juros produzidos em venda isolada sem ponte documental, classificação ampla, origem negativa integrada, calendário, autenticidade, dependências e a Fase4B. Produção permanece bloqueada.
