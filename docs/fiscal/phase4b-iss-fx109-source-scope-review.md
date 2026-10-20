# ISS109 - preco e cambio por documento: pesquisa parcial

Em19/10/2026 foram preservadas11 capturas primarias no D:,sem alterar o
runtime108 ou qualquer bundle. Os quatro PDFs municipais foram lidos por
inteiro,extraidos e renderizados;suas oito paginas foram inspecionadas
visualmente com a skillPDF. Originais e continuacoes estao legiveis,sem
cortes relevantes. Nao houve reescrita de PDF ou remocao de divergencias.

Evidencia:
`D:/ProjetoSaasExports/phase4b-iss-fx109-primary-research-20261019/iss-fx109-source-scope-review.json`.
SHA256:`289d7f5b174ec8f49791f7a1e21e709f507ede7be9e89c38d1b12e5625c960ca`.
Capturas,URLs,hashes,observacao,extracoes e imagens em `captures-v1/`.
Perguntas e respostas da Pesquisa31 foram lidas integralmente e copiadas
com hashes iguais ao K:. O arquivo de respostas tem2490 linhas;seu nome
original contem `Respotas`. Pesquisa interna nao e norma oficial.

## Fontes e alcance observado

O [CTN,arts114/116/143/144](https://www.planalto.gov.br/ccivil_03/leis/l5172compilado.htm)
exige tratar evento e legislacao aplicavel separadamente. O art143 nao
seleciona sozinho seriePTAX,compra/venda,calendario ou agregacao mensal.
Sua ressalva de lei em contrario impede transformar uma captura do artigo
em politica cambial completa para toda operacao.

A [LC116,arts1/2/7](https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp116.htm),
a [Lei13701,arts1/6/14](https://legislacao.prefeitura.sp.gov.br/lei-13701-de-24-de-dezembro-de-2003/consolidado)
e o [Decreto53151,arts17-19](https://legislacao.prefeitura.sp.gov.br/decreto-53151-de-17-de-maio-de-2012/consolidado)
separam fato,documentacao,preco/base,aliquota e regime. Consolidacoes exibem
redacoes historicas e alteracoes;extracao textual nao qualifica sua vigencia.
O [FAQ municipal de calculo](https://prefeitura.sp.gov.br/fazenda/w/servicos/iss/2478)
e orientacao,nao fonte autonoma de efeitos. Data de atualizacao da pagina
nao e effective_from. Nenhuma aliquota foi instalada desta pesquisa.

| Consulta | Contexto efetivamente examinado | Limite para o proximo corte |
| --- | --- | --- |
| [SC05/2010](https://www.prefeitura.sp.gov.br/cidade/secretarias/upload/arquivos/secretarias/financas/consultas/SC005-2010.pdf) | Importacao de suporte tecnico1.07 continuado;fechamento para vendaBCB no ultimo dia util do mes da prestacao | Nao autoriza regra geral de exportacao/software |
| [SC51/2012](https://www.prefeitura.sp.gov.br/cidade/secretarias/upload/arquivos/secretarias/financas/consultas/SC051-2012.pdf) | Importacao continuada de engenharia7.03/7.17,consorcio com empresa italiana,valor emEUR | Nao transportar codigo,aliquota ou evento para software |
| [SC24/2014](https://www.prefeitura.sp.gov.br/cidade/secretarias/upload/arquivos/secretarias/financas/consultas/SC024-2014.pdf) | Importacao continuada;cambio mensal e emissaoNFTS distintos do fato;cabecalho1.07 e item4 do corpo17.01 | Divergencia preservada e confirmada visualmente;nao escolher classificacao silenciosamente |
| [SC08/2015](https://www.prefeitura.sp.gov.br/cidade/upload/SC008-2015_1456839085.pdf) | Importacao de consultoria17.01 com conclusao por relatorio final no caso concreto;fechamentoBCB para venda na conclusao | Nao impor entrega final universal nem adaptar automaticamente para etapa continuada de software |

As quatro consultas tratam de servicos tomados do exterior. Seu alcance
subjetivo,historico e eventual supersessao precisam de prova propria.
Esta leitura nao demonstra ausencia de norma de exportacao,mas tambem nao
autoriza aplicar automaticamente as consultas a exportacao parcial108.

## Consequencias para a implementacao ainda pendente

1. Vincular preco juridicoISS a documento,papel,partes,contrato e unidade
economica. A alocacao de consumoRTC40/60 nao prova rateio de precoISS.
2. Qualificar fonte cambial aplicavel a cada dataISS,serie,direcao,fechamento,
calendario e disponibilidade. Nao copiar a cotacaoRTC para outro evento.
3. Separar valor original,preco/baseISS,valor documental e memoriaRTC;
descontos,acrescimos,retencoes e variacao financeira nao sao equivalentes.
4. Exigir origem/conteudo/hash,lifecycle e conhecimento das provas;hash
autoassinado nao autentica preco,contribuinte ou governo.
5. Testar moedas,arredondamento,fronteiras temporais e conflitos antes da
integracao;projetar o parXML somente com schema/ambiente/data qualificados.

Nenhum componente109 foi implementado ou instalado;nenhum testePest novo.
83 hashes do runtime108 permaneceram iguais na auditoria desta pesquisa.
Publicacao,efeitos,vigencia,aplicabilidade integral e politica cambial para
exportacao nao foram aprovados. Fontes desta pesquisa nao foram acrescidas
as76 fontes do bundle108. Producao permanece bloqueada;os oito grupos4B
continuam parciais e os80 requisitos exigem auditoria individual.
