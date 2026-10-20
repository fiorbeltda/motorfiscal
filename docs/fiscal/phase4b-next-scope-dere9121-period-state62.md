# Estado contextual da competência — preparação62

Objetivo: conferir o intervalo aberto em que cada evento público foi recebido e comprovar, dentro do histórico sintético completo, que a competência continua aberta no instante de conhecimento do cálculo. A declaração isolada `existing_closing=false` não basta para representar ciclos anteriores de fechamento e reabertura.

O MOD1.3.0, páginas físicas148–149, descreve o D9198 como desbloqueio técnico, sem modificar bases ou créditos. O D1198 aceita somente inclusão, referencia o último recibo D1199 ativo e exige novo fechamento depois das retificações. O contador de reabertura começa00 e prossegue01…99 independentemente das versões do fechamento e do evento público. Os leiautes, páginas físicas29–30, exigem correspondência de contribuinte, competência e recibo1199. Os trechos e quatro XSDs foram preservados com hashes em `period-research-sources.json`.

O protótipo62 exige contexto sintético explícito e histórico com hash que abranja exatamente o instante de conhecimento. Cada fechamento/reabertura contém os bytes de origem e retorno, ambos conferidos nos esquemas. A reconciliação verifica hash real da origem, identificador, recibo, sequência por família, sucesso sem ocorrência de erro, contribuinte, competência e cronologia com sete casas fracionárias. Reabertura precisa apontar o último fechamento ativo. Os eventos públicos são qualificados pelo seletor60 e precisam estar inteiramente dentro do histórico, em intervalo aberto, sem sobreposição com transição de competência. A cadeia pode terminar aberta depois da reabertura; o componente49 de estoque continua exigindo fechamento sucessor para seu objetivo próprio.

A captura revisada contém22 casos: três positivos e dezenove recusas. Inclui duas reaberturas, competência fechada antes ou depois do evento público, recibo antigo, duplicação de fechamento, sequência reiniciada, origem ausente, hash/identificador/contribuinte divergentes, erro no retorno, operação vedada, cronologia inválida, sobreposição no sétimo dígito fracionário e cobertura histórica incompleta. O primeiro conjunto20 foi preservado antes de reforçar a cobertura do instante de conhecimento. Também foram preservados a tentativa inicial de extração com nome incorreto de XSD e os arquivos anteriores à formatação e à correção da ordem dos imports do driver.

Limites: o contexto inicial aberto e a completude do histórico são testemunhos sintéticos. O protótipo não autentica o estado governamental, não reconcilia economicamente o fechamento antigo com a memória financeira e não autoriza contabilização. As origens/retornos de fechamento do fixture servem à prova do ciclo de estado; seus valores não representam o fechamento econômico61. Essa diferença é explícita em `closing_origin_economics_reconciled=false`.

Critérios seguintes:

1. Promover o qualificador e testes permanentes depois da aceitação local61, preservando os hashes da geração anterior.
2. Vincular o testemunho de histórico à entrada mensal reconstruída e ao instante de conhecimento do bundle, incluindo prova do contexto inicial e da completude em fonte efetiva quando disponível.
3. Integrar por política e bundle próprios o contexto qualificado antes de admitir a versão pública ativa no cálculo e no comando. Guardar recibos e sequências de fechamento/reabertura separadamente do codBCN e da sequência D2101.
4. Capturar alteração após reabertura e recusas antes de qualquer escrita; repetir replays, regressões históricas e qualidade completa da nova geração.
5. Qualificar D1021 antes de admitir exclusão por processo. A aceitação desses componentes não substitui a aceitação dos demais perfis do escopo54 nem da Fase4B.

Nenhuma política ou implementação de aplicação é alterada pela preparação62. Produção permanece bloqueada.

Referência pública confirmada em13/10/2026: [comunicado oficial RFB1.3.0](https://www.gov.br/receitafederal/pt-br/assuntos/noticias/2026/outubro/receita-federal-publica-versao-1-3-0-da-documentacao-tecnica-da-dere/). As regras utilizadas vêm dos PDFs e XSDs preservados, e não apenas desse comunicado.
