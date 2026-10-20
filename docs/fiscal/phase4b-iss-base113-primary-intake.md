# Pesquisa113 - identidade das fontes e codigos municipais

Corte inicial parcial de20/10/2026 sobre12 capturas primarias realizadas em19/10.
As atualizacoes abaixo ampliam o corte para13 capturas e incluem a leituraXLS;
as limitacoes iniciais foram preservadas como historico,nao como estado atual.
Nao e politica fiscal,aliquota instalada ou nova baseISS. A integracao112 esta
congelada para regressao;nenhum de seus17418 inputs foi editado por esta pesquisa.

## Evidencias e leitura

Originais,URLs,horarios,TLS eSHA256 preservados em
`D:/ProjetoSaasExports/phase4b-iss-base113-primary-research-20261019/captures-v1/`.
DoisPDFs,18 e11 paginas,foram extraidos sem alterar seus bytes. Foram
inspecionadas visualmente as paginas fisicas2 e9 do anexo antigo,e1 e11 da
tabela2026. A ultima pagina desta tabela so tem o brasao,sem corpo textual;
texto vazio nessa pagina nao representa perda da tabela. Nao houve revisao
visual integral dos29 paginas nem revisao doXLS preservado.

Auditoria de identidade/escopo:180 conferencias aprovadas e87 hashesruntime112
intactos. Recibo em `review-v1/source-scope-review.json`. A tentativa inicial
de usar `pdftotext` encontrou comando indisponivel;foi usada extracao com
`pypdf`,acompanhada das imagensPoppler. Avisos de fontes do renderizador
foram preservados;as paginas relevantes estavam legiveis.

## Limites que mudam a proxima implementacao

- O rotulo do portal referente ao anexo daIN8/2011 menciona retificacao2026,
  mas o corpo capturado ainda mostra02690/02798 comoPJ a2%. O corpo deve ser
  confrontado com a cadeia de alteracoes;rotulo ou metadata nao provam vigencia.
- A [IN23/2017](https://legislacao.prefeitura.sp.gov.br/instrucao-normativa-secretaria-municipal-da-fazenda-sf-23-de-22-de-dezembro-de-2017/consolidado)
  apresenta02692/02800 para1.04/1.05 emPJ a2,9%,encerra02690/02798 eexplicita
  efeitos proprios dos codigos em13/02/2018. Nao inferir mapeamento atual apenas
  da coincidencia de descricao ou do catalogo nacional.
- A [IN3/2026](https://legislacao.prefeitura.sp.gov.br/instrucao-normativa-secretaria-municipal-da-fazenda-sf-surem-3-de-27-de-marco-de-2026)
  encerra codigos anteriores dePF e remete a nova tabela. Nela,02694/02695 sao
  PF,nao substituicoes automaticas dos codigosPJ. A clausula de efeitos doart5
  foi alterada pelaIN7/2026;publicacao,conhecimento eefeitos nao sao a mesma data.
- As linhasPF remetem aNota6. No anexo antigo,essa nota contem condicoes de
  inscricao/estabelecimento e uma linha6b visualmente incompleta,terminando em
  "deste". Nao completar o trecho por inferencia nem aplicar a aliquota nominal
  ignorando a nota. Uma fonte completa e sua cadeia atual ainda sao necessarias.
- O Decreto53151 capturado apresenta aliquotas antigas;Lei13701art16 e suas
  alteracoes devem ser selecionadas por versao,efeitos eaplicabilidade. A
  fixture2% do motor foi preservada;nao foi promovida a parametro municipal real.

## Fronteira da proxima implementacao

Atualizacao08:17:dez corpos primarios adicionais preservados noD:,total23.
Sete paginas normativas posteriores,um ato correlato2022 e doisPDFs foram
capturados. A tabela2026 retornouPDF,apesar do nome local inicialHTML:seus
bytes/log originais foram preservados apos falha de decodificacao,sem recaptura
ou normalizacao silenciosa.22 paginas novas extraidas;as duas da tabela2026
e as paginas1/20 do anexo2021 foram vistas. Nao houve revisao visual integral
do anexo2021 nem aceite de aplicabilidade atual.

O novo recibo `code-history-review-v1/code-history-review.json` registra196
conferencias e87 hashes de execucao112 intactos. A tabela efetiva daIN8/2026
tem25 linhas retificadas,sem02660/02692;isso nao prova ausencia de alteracao em
toda a legislacao. O rotulo de um check dizia24,mas sua lista esperada ja
continha25 codigos;correcao de rotulo preservada em
`code-history-review-v1/row-count-label-correction.json`.

A IN4/2018 apresenta fevereiro no titulo do portal e marco no corpo republicado.
Seu texto distingue corte cadastral12/02/2018 e efeitos13/02/2018,e exige
atualizacaoCCM se a conversao nao corresponder a atividade real. A IN9/2020
referencia02660/1.01 em programa especifico daZona Leste:isso nao concede
beneficio universal. A IN19/2021 remete a anexo e retira o campoLivro Fiscal
com efeitos proprios;nao tomar a tabela2017 inteira como layout atual.

O catalogo vinculado ao112 identifica `SOFTWARE_DEVELOPMENT` comoLC116 `01.01`
e cTribNac `010101`. As provas municipais106 declaram apenas a familia `1`.
Isso nao e contradicao,mas nao prova subitem nem codigo municipal especifico.
Nao inferir `1.04`/`02692` a partir de "software". A pesquisa historica liga
`02660` a `1.01`,sem aprovar sua aplicabilidade atual por documento.
O contrato proposto e a matriz de recusas estao em
`phase4b-iss-base113-boundary-contract.md`;nao ha componente instalado.

## Pendencias

Concluir historia aplicavel de codigos/classificacao;qualificar aNota6 atual;
vincular fontes/efeitos/conhecimento a provas
frescas por documento. Depois compor base/aliquota/imposto em escopo explicito,
sem usar rateio ou cotacaoRTC como prova de preco/cambioISS. Nao ha componente113
implementado,novo testePest,XML ou aceite113. Os80 requisitos originais continuam
exigindo auditoria individual;os oito grupos4B permanecem parciais.

## Atualizacao20/10 as07:56 - planilha historica inspecionada

O originalXLS foi lido com `xlrd2.0.2` isolado noD:,pois o runtime disponivel
nao tinha leitorBIFF8. Nenhuma dependencia do projeto ou do runtime empacotado
foi modificada. As quatro abas,celulas preenchidas,formatos eareas mescladas
foram preservadas na extracaoJSON;nao houve exportacao,recalculo ou edicao doXLS.

A aba `PJ + PF` tem560 linhas e11 colunas;as tres abas restantes estao vazias.
O filtro inicial por identificador exato encontrou apenas dois codigos porque
os demais trazem anotacoes na propria celula. A extracao completa ja continha
todos os valores. A revisao posterior separa codigo/anotacao para consulta,
preservando ambos e sem reescrever a fonte.

As celulasA118/E118 eA121/E121 mostram02692/02800 comNota23 eproporcao nominal
0.029;A545 explicita o inicio de codigos em13/02/18. As linhas antigasA112/E112
eA114/E114 conservam02690/02798 a0.02,masK112/K114 remetem aNota27,eA557
declara seu encerramento. As oito linhasPJ foram confrontadas com o texto da
IN23/2017. Nao tratar todos os registros da planilha como simultaneamente vigentes.

O trecho incompleto daNota6b noPDF esta completo na celulaA495 doXLS,terminando
em "deste Anexo.". O PDF nao foi corrigido nem seu texto foi substituido.
Esse achado resolve a leitura historica,nao a aplicabilidade atual.

Foi preservada em20/10 uma13a captura primaria,a
[Lei14864](https://legislacao.prefeitura.sp.gov.br/lei-14864-de-23-de-dezembro-de-2008),
SHA256 `266306f3df628ebf8d7d007724dfead1d7799443812d4e5eb2b968e156ab5a01`.
O texto observado exige natureza/cadastro proprios,exclui formas eatividades
especificas econdiciona o beneficio a obrigacoes acessorias. Ainda falta
qualificar a cadeia regulamentar e as provas materiais;nao basta um booleanoCCM.

Recibos adicionais noD:: `legacy-inspection-v1/legacy-source-inspection.json`,
`pf-supplement-v1/primary-source-observation.json` e
`legacy-review-v1/legacy-source-review.json`. O ultimo registra141 conferencias
novas aprovadas e87 hashesruntime112 intactos. O corte anterior de180 checks
permanece preservado. Nenhuma nova base,aliquota,isenção ou regra foi ativada.
