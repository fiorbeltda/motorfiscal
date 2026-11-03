# A150 — áreas e extensões com precisão exata

BaseGit87d22e7,alteraçõesA144–A149 preservadas e nenhuma publicação.
Contrato opt-in `RTC-MEASURED-TERRITORIAL-LOCALITY-2026.11.03-A150` na CLI própria
`fiscal:rtc:qualify-service-locality`. Arquivos de produção pertencentes aA149
estendidos; nenhum catálogo,enum,schema,engine/factory central ou arquivoB editado.

## Comportamento comprovado

Para imóvel, o componente soma as áreas de suas partes por município antes
da escolha. No exemplo, duas partes30,25+30,25 emSãoPaulo superam a parcela
isolada39,50 emCampinas: o destino éSãoPaulo, com o fornecimento inteiro ali
localizado. Não há rateio60,50%/39,50% do tributo do imóvel. Empate de maior área
retorna pergunta explícita, sem escolher por ordem de entrada.

Para exploração onerosa de via, as extensões são agrupadas por município eUF.
Três trechos de1m emSãoPaulo,Campinas eRio conservam frações1/3 municipais e
SP2/3,RJ1/3 estaduais. Não se convertem frações em percentuais arredondados.
Os dois níveis se referem ao mesmo fornecimento; não são novas operações nem
devem ser somados para duplicar sua base. Nenhuma alíquota ou crédito é escolhido.

Cada levantamento exige unidade explícitaM/M2,ativo,operação,fornecimento,
partes,data,documento,lista completa de partes e total reconciliado. Provas são
vinculadas por conteúdo/hash; isso não autentica origem externa ou cadastro.
Zero,exponente,float,unidade ambígua,parte extra/duplicada,prova trocada/futura,
incompletude e divergência de soma são recusados. Medidas decimais não são
arredondadas, inclusive na fronteira1.00000000000000000001.

## Evidência terminal

-55 testes dirigidos/246 asserções, incluindo os30 testesA149 anteriores.
-309 testes/1.106 asserções na regressão dirigida acumulada, incluindo os55;
  não somar os conjuntos e não chamar essa suíte de regressão integral final.
-Três casos nativos:imóvel,via e imóvel de alta precisão; oráculos independentes
  `fractions.Fraction` conferem áreas,totais,destino e pesos municipais/estaduais.
-DuasCLI de via idênticas,CLI de imóvel e replays completos; empate retornaexit1.
-Saída aceitaA149 permanece byte a byte igual depois da extensão.
-PHPStan global sem erros; Pint dos quatroPHP próprios aprovado.

Recibo `validation-A150.json`; verificação reproduzível
`python scripts/agent_a/verify150.py`. Fonte primária:LC214art11,II/§2 eVIII;
regulamentoCBSart12§2/VIII consultado na fonte oficialCâmara. Capturas e hashes
originais preservados; não houve alteração de norma,XSD ou catálogo.
Pacote externo: `D:/ProjetoSaasExports/agent-a-A150-territorial-measures-20261103-v1`.

## Interface e pendências

`jurisdiction_allocations` e `state_allocations` contêm numerador/denominador
exatos; `measured_territories` preserva medidas eIDs. Para imóvel, `locality` é
único e a participação do destino escolhido é1/1; para via, as participações
territoriais são explícitas e `locality` única permanece nula.

B deverá consumir os destinos/participações em sua classificação/projeção,
preservando o mesmo fornecimento. A integração continuaPENDING_AGENT_B; não
selecionar cIndOp,cClassTrib,NBS ou taxa por iniciativaA. A referência geográfica
observada não prova existência jurídica histórica. Requisitos permanecem com
aceites escopados, sem grupoA formalmente encerrado e sem produção.
Próximo foco:domicílio/estabelecimento material e exceções de compra centralizada.

## Preservação dos aceites próprios

Os verificadores145–150 agora criam recibos uma única vez. Repetição com mesmo
conteúdo apenas confere; mudança de valor,tipo ou implementação exige novo
artefato/versionamento. RecibosWindowsCRLF permanecem byte a byte intactos,
sem reformatar o arquivo para conferir seu conteúdoJSON.
Controles em diretório temporário comprovaram criação,repetição,CRLF,recusa de
valor diferente e de tipo diferente; não são somados aos testesPHP. A reprodução
do oráculoA150 preservou o SHA256 original do recibo. Evidência complementar:
`evidence-preservation-A150.json`. O índice corrente44 continua atualizável,
preservando os recibos e o histórico que referencia.
