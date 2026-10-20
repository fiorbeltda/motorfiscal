# ISS105 - consultas municipais e divergencia de referencia

Pesquisa complementar em19/10/2026,sem nova regra fiscal instalada. Cinco
capturas adicionais preservadas em:
`D:/ProjetoSaasExports/phase4b-iss-date105-primary-research-20261019/municipal-supplement-20261019-v1/`.
Tres PDFs oficiais de duas paginas cada foram integralmente lidos e suas seis
paginas renderizadas foram inspecionadas visualmente:sem corte,ilegibilidade
ou perda de texto que impeça a revisao. Originais,extracoes e imagens mantidos
no D: como evidencia;nenhum PDF oficial foi corrigido ou reescrito.

## Escopos que nao podem ser fundidos

- [SC29/2025](https://prefeitura.sp.gov.br/documents/d/fazenda/sc-29-2025-pdf):
  monitoramento/rastreamento veicular,item11.05,codigo7930,contrato anual e
  pagamento antecipado. A solucao distingue a prestacao mensal da antecipacao
  financeira. O tratamento da multa descrita pertence aquele caso;nao produz
  regra automatica de multa,base ou competencia para software.
- [SC08/2017](https://drive.prefeitura.sp.gov.br/cidade/upload/sc008-2017_1512505269.pdf):
  tomador orgao federal,contratos licitados e adiantamento. A consulta trata
  documento fiscal/prestacao e nao determina os comprovantes financeiros que
  a administracao federal deve aceitar. Nao e contrato generico de software.
- [SC06/2018](https://drive.prefeitura.sp.gov.br/cidade/upload/sc006-2018_1530720423.pdf):
  distingue estacionamento proprio11.01 e intermediacao10.05. Nao transportar
  automaticamente evento,local,base ou codigo dessas atividades para item1.

Consulta individual,parecer normativo,lei e regulamento sao fontes distintas.
Sua hierarquia,destinatarios,versao e efeitos nao podem ser inferidos de uma
data de captura. Nao foi concluida a qualificacao historica desses efeitos.

## Divergencia confirmada na SC08/2017

O PDF cita Lei15.406 nos paragrafos3 e6. No texto consolidado consultado:

- O art6 proprio da Lei15.406 pertence ao PPI,nao a emissao de nota.
- O art18 da Lei15.406 altera o art6 da Lei13.701,onde esta o comando de
  emissao por prestacao. A SC06/2018 descreve essa relacao de alteracao.
- A independencia do pagamento esta no art1,paragrafo4,V da Lei13.701.

Confronto primario:
[Lei15.406,art18](https://legislacao.prefeitura.sp.gov.br/lei-15406-de-08-de-julho-de-2011/consolidado),
[Lei13.701,arts1/6](https://legislacao.prefeitura.sp.gov.br/lei-13701-de-24-de-dezembro-de-2003/consolidado).
Nao corrigir silenciosamente a citacao no PDF nem transformar artigo citado
internamente em artigo proprio da lei alteradora. Lei15.406 permanece com
estado ALTERADO no portal;publicacao e efeitos exigem campos separados.

## PN4 e data material

O [PN4/2016](https://legislacao.prefeitura.sp.gov.br/parecer-normativo-secretaria-municipal-da-fazenda-4-de-10-de-novembro-de-2016/consolidado)
trata resultado no exterior,servico continuado por etapa mensal e prova
documental. Seu proprio art4 revoga disposicoes contrarias e o PN2/2016.
O [cadastro do ato](https://legislacao.prefeitura.sp.gov.br/parecer-normativo-secretaria-municipal-da-fazenda-4-de-10-de-novembro-de-2016/detalhe)
informa assinatura09/11/2016,publicacao10/11/2016 e SEM REVOGACAO EXPRESSA.
Isso nao equivale a auditoria de todos os conflitos,precedentes e efeitos
historicos;esses metadados foram vistos no portal,nao ativados no bundle104.

Proximo contrato deve provar prestacao ou etapa efetivamente realizada por
papel/documento. Data de pagamento,assinatura,emissao,aceite comercial isolado
ou ultimo dia do mes nao sao substitutos automaticos da prova material.
Nao exigir entrega final para toda prestacao continuada,mas tambem nao gerar
etapa mensal so porque o calendario mudou.

## Diagnostico nativo e continuidade

Sete avaliacoes PHP do runtime104 foram capturadas separadamente em
`existing-date-coupling-probe-v1/native.stdout`. Os dois controles nominais
passam. O qualificador95 vincula provas sinteticas de resultado com supply_on
anterior/posterior,mas CurrentPartialExportIss96 recusa ambas no campo
supply_on;103 recusa competenciaDPS diferente da dataRTC. Nenhuma avaliacao
contem prova material de prestacao;nao sao cenarios fiscais novos aceitos.

A primeira auditoria parou ao exigir do95 um campo booleano que ele nao
publica. Saida nativa e coletorV1 foram preservados;auditoriaV2 confere o
contrato real sem interpretar ausencia de campo como true ou false.
O diagnostico obriga a projetar vinculos independentes de data,nao a relaxar
ou reescrever provas historicas. Ver o contrato proposto em
`phase4b-iss-date105-material-contract-draft.md`.

As dez capturas locais (cinco iniciais e cinco complementares),seis paginas
e79 hashes104 sao conferidos no novo recibo de revisao. Nenhuma fonte foi
instalada,nenhum componente105 ou vigencia material aprovado.4B.1.5/6,
4B.7.1 e4B.8.10 continuam incompletos. Producao bloqueada.
