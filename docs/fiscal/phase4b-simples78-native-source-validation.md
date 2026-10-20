# Simples 78 — fontes nativas oficiais preservadas

## Resultado da pesquisa

Em 15/10/2026 foram capturadas, sem autenticação, as respostas JSON oficiais
usadas pela consulta pública externa da Receita. Isso supera a limitação de
captura registrada no documento preparatório anterior: a fonte agora não é
somente transcrição manual, snippet de busca ou shell de aplicação JavaScript.
Nenhuma regra, catálogo ou bundle foi alterado por esta pesquisa.

Diretório exclusivo:
`D:/ProjetoSaasExports/phase4b-simples78-native-acts-20261015/`.

| Ato | Resposta oficial | SHA-256 |
| --- | --- | --- |
| CGSN 194 | [consulta externa vigente 153801](https://normasinternet2.receita.fazenda.gov.br/api/consulta-externa/ato/153801/visao/vigente) | `038e3259963c9cf492e3dec1f4ddbff50cbf83ccabd3a94ea191b0f56fe82ef7` |
| CGSN 186 consolidada | [consulta externa vigente 150653](https://normasinternet2.receita.fazenda.gov.br/api/consulta-externa/ato/150653/visao/vigente) | `31dc79137f5e4d3f67289bd0cd05f7ef72992037bf28dde216fd1085f8453f1d` |

As respostas HTTP 200, `application/json`, foram preservadas byte a byte.
Identidade do ato, visão vigente, publicação, vigência e flags públicas foram
conferidas. A íntegra dos segmentos atuais foi extraída e lida, incluindo
assinaturas. A transcrição anterior da 194 contém todos os segmentos de corpo
conferidos contra a resposta nativa. Ementas e demais metadados permanecem
íntegros no JSON bruto; os arquivos de texto são explicitamente extrações do corpo.

## Seleção correta das versões

O primeiro inspetor supôs que a visão vigente eliminaria versões antigas do
JSON. Sua assertiva falhou na CGSN 186; a tentativa e o texto parcial da 194
permanecem preservados. Não houve relatório de aceitação dessa tentativa.

O JSON da 186 contém oito versões antigas com `omitir=true` e respectivas
substitutas visíveis. O segundo inspetor seleciona somente `omitir=false`,
exige versão compilada e não tachada, unicidade de ID entre as versões
selecionadas e ordem. Para cada versão omitida, exige substituta do mesmo ID
com versão maior e âncora para a CGSN 194 com vigência em 28/09/2026. Também
confere que o prazo antigo de 30/09 não vazou para o corpo vigente.

Auditoria bem-sucedida:
`native-act-source-validation-v2.json`.
Histórico de falha: `inspection-attempt1-failure.json`.
Fontes e scripts originais não foram sobrescritos.

## Proveniência da rota pública

A rota não foi adivinhada por enumeração de IDs. Os IDs foram localizados na
interface oficial. A base `/api` e o serviço GET
`/consulta-externa/ato/{id}/visao/{visao}` foram identificados nos próprios
arquivos públicos da aplicação. Esses arquivos, seus hashes e trechos de
descoberta estão em
`D:/ProjetoSaasExports/phase4b-simples78-public-document-assets-20261015/`.
Foram executadas apenas leituras GET da consulta externa; nenhum endpoint de
autenticação, edição, anotação ou gestão foi chamado. O código capturado foi
inspecionado como texto, não executado.

## O que ainda exige implementação

A qualificação do Simples no engine ainda usa o catálogo histórico com aviso
de supersessão e continua bloqueada pela razão correspondente após a observação
do aviso. A obtenção do ato não muda esse comportamento automaticamente.

O futuro contrato deve preservar as duas opções distintas, a inscrição CNPJ
separada de início de atividade, as exceções do art. 3º, a exclusão SIMEI,
estado/protocolo da eleição, conhecimento/publicação/efeitos e replays antigos.
O corpus precisa cobrir também os limites das janelas e as hipóteses de opção
no momento da inscrição. Não basta substituir duas datas numa linha antiga.

A suíte completa 76 permanece independente desta pesquisa. Fase 4B e aprovação
de produção continuam incompletas, mesmo com as duas fontes agora disponíveis.
