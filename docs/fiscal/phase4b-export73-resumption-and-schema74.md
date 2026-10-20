# Retomada 73 e inspeção documental 74 - 14/10/2026

Os dez arquivos de implementação, testes e recursos coincidem com os SHA-256
do checkpoint `handoff-checkpoint-validation.json` da geração 73.

Após a formatação final, o corte dirigido passou: **50 testes, 213 assertions**,
com exit code 0. Evidência nova, exclusiva:
`storage/app/phase4b-fx71-exact-quote-preparation-20261014/scope73-final-directed.json`
e log correspondente.

A suíte completa foi iniciada no contêiner PHP 8.5 pelo wrapper de captura,
com label `scope73-full-suite`. Seu resultado terminal deve ser conferido antes
de executar `scripts/audit_phase4b_export_fx73_development.py`. O auditor exige
2.266 testes aprovados e extrai a contagem real de assertions da saída. O
auditor criado ainda não constitui aceitação enquanto não produzir seu relatório.

O modo explícito `--preflight` do novo auditor passou e gravou
`export-integration-preflight-validation.json` no diretório da geração 73.
Ele conferiu o corte dirigido terminal, hashes dos logs de qualidade, os dez
hashes do checkpoint, hashes canônicos de facts/questions/catálogo/controles,
12 capturas com replays e 18 replays históricos. Os seis casos nominais foram
reconciliados com Decimal independente (conversão, alocação e tributos de 2026);
os seis casos recusados não produziram cálculo nominal doméstico.
O registro declara `full_suite_complete=false` e `full_suite=null`.
O modo padrão continua exigindo a suíte terminal e grava um relatório
distinto de aceitação do desenvolvimento, sem fechar o escopo documental.

### Resultado terminal da retomada 73

A suíte completa terminou com **2.266 testes e 45.742 assertions**, exit 0,
em 1.564,06 segundos. O log tem SHA-256
`55ad18d929685f7f89f9b301bcb20770d86d0d00246039c360c31fc89f3759b4`.
O auditor padrão passou e gravou exclusivamente
`storage/app/phase4b-partial-export-fx73-preparation-20261014/export-integration-development-validation.json`.

A conversão/alocação da geração 73 fica aceita como desenvolvimento técnico,
com hashes finais, corpus, replays, estilo e avaliação de produção vinculados.
`document_obligation_complete=false`, `scope73_complete=false`,
`phase4b_complete=false` e `production_ready=false` permanecem explícitos.
Nenhum Pest permaneceu ativo na conferência terminal. O Pint final passou em
504 arquivos; o registro de PHPStan do runtime da geração 73, sem erros,
é anterior à formatação final, como já declarado no checkpoint.

A avaliação própria da geração 73 foi capturada com label exclusivo
`scope73-production-assess`: exit 0 para `--target=local-validation`,
`eligible_for_local_validation=true`, `ready=false`, 51 fontes oficiais com
hashes conferidos e 119 bloqueios de produção. O auditor de desenvolvimento
também exige esse registro e sua vinculação aos hashes do bundle/controles.
O preflight preservado é anterior à inclusão dessa nova evidência no auditor.

## Inspeção dos artefatos oficiais preservados

`scripts/inspect_phase4b_document74_schema_closure.py` conferiu os hashes dos
dois ZIPs preservados e percorreu imports/includes/redefines a partir da DPS.
Cada pacote possui quatro membros no fechamento observado. A evidência exclusiva
é `storage/app/phase4b-document74-primary-research-20261014/schema-closure-inspection.json`.

Os fechamentos observados não contêm os elementos `nFatura`, `vFatura`,
`nContCambio`, `nDUIMP`, `nDUE` ou `vAjusteBCIBSCBSComExt` anunciados pela
NT009 v1.01. A existência de `comExt`, `mdPrestacao`, `indDest` e `dest` nesses
pacotes não prova compatibilidade com as alterações novas de comércio exterior.
A integração da NT009 exige o leiaute/anexo aplicável e o artefato correspondente.

## Série numérica em libxml

`scripts/exercise_phase4b_document74_series.php` usa o validador existente no
PHP do contêiner e o ZIP de homologação hash-bound
`6c7e0510d3ecff4454f291f4e10b742d27a4818f23aab181494f96d0ea79f3dc`.

A primeira tentativa foi preservada em `restricted-series-native-validation.json`:
a fixture de produção tinha Id sintético incompatível com o pattern do pacote
de homologação. O segundo registro `restricted-series-native-validation-v2.json`
ajusta apenas o Id da entrada sintética; nenhum XSD oficial foi alterado.

Resultados da segunda tentativa: XMLs com séries `1`, `00001` e `89999`
válidos; `90000`, `99999`, `^1$`, `123456` e `ABC` inválidos. Trata-se de
validação estrutural local de uma fixture, sem autenticação de contribuinte,
implantação remota ou qualificação temporal do leiaute novo.

Nenhum schema foi ativado por esta inspeção. A obrigação documental da
exportação parcial, a aceitação integral da Fase 4B e produção permanecem
pendentes. As evidências 71 e as tentativas anteriores foram preservadas.

## Anexos oficiais atuais e condições de comércio exterior

A nova pesquisa `storage/app/phase4b-document74-annex-research-20261014`
preserva Anexo VI 1.04.01 e Anexo VII 1.03.00, obtidos dos links do portal RTC.
SHA-256 respectivamente:
`103a150dd6f56ec8edcaa3a453b59a1b0e096d7cd5025d7bd06a387f141ddf39`
e `95f30a44ee94adeea57fb92f3a0337b1e62fdc106f393e73894087889be9e5aa`.
O manifesto registra URLs, instante da observação, tamanho e ausência de
comprovação de vigência/implantação desses novos contratos.

A NT009 foi extraída integralmente para leitura. As páginas físicas 15 a 18
foram renderizadas; 16 e 18 foram inspecionadas visualmente nesta retomada.
A extração das planilhas preserva células e fórmulas. Nenhuma planilha oficial
foi editada ou recalculada.

O relatório `document-annex-contract-validation.json` registra:

- Anexo VI, `LEIAUTE DPS_NFS-e - RT!I236`: consumo misto exportado usa a parcela
  estrangeira no vServ e exige NFS-e regular para a parcela brasileira.
- `I238`: o código de moeda precisa estar válido na data da competência.
- `I239`: nFatura está condicionado à exigência de documento para importação,
  cujo escopo exato aguarda ato conjunto. A descrição não comprova exigência
  universal de nFatura em exportação.
- `RN DPS_NFS-e!D601`: o ajuste de comércio exterior não pode ser informado
  quando tpEmit=1 e tribISSQN diferente de 3. A célula não está tachada;
  o código de rejeição em H601 ainda é EXXX no artefato observado.

A diferença entre a aprovação de anexos, o cronograma anunciado, os ZIPs
publicados e a execução remota deve continuar explícita. Os anexos atuais
comprovam os contratos descritos, mas não fornecem os novos elementos nos
fechamentos XSD já capturados. A série numérica resolve uma parte estrutural
do pacote de homologação preservado, sem concluir a migração NT009.

## Prova integrada do projetor existente no XSD de homologação

O diretório exclusivo `storage/app/phase4b-document74-integrated-projection-20261014`
contém `integrated-projection-validation.json` e 12 XMLs sintéticos.
Foram reproduzidos exatamente três snapshots históricos: DPS com retenções
federais, educação e educação presencial integrada. Cada decisão foi projetada
duas vezes para comprovar determinismo, usando a identidade histórica explícita
e mudando apenas a série da prova.

Os nove XMLs com séries `1`, `00001` e `89999` validaram no ZIP de homologação
preservado; os três com `90000` foram recusados. Os snapshots originais mantêm
seus hashes. A evidência registra hashes do projetor e do validador atuais.
O script da prova passou por sintaxe PHP e Pint direcionado, junto com o
script de inspeção da série (dois arquivos).

Essa reprodução comprova compatibilidade estrutural local do projetor existente
com o pacote preservado para os três perfis examinados. Nenhum conhecimento
histórico foi trocado e nenhum bundle foi ativado. A aplicação operacional exige
um novo contrato com seleção explícita de ambiente, artefato, conhecimento e
datas. A prova não fornece os novos campos de comércio exterior da NT009 nem
equivale à homologação remota, à veracidade de dados ou à autorização de produção.

## Incompatibilidade adicional do domínio comExt

`schema-types-inspection.json` preserva as facetas oficiais e a sequência do
grupo comExt dos dois pacotes. Ambos limitam TSModoPrestacao aos valores 0-4,
com `3 = Presença Comercial no Exterior` e `4 = Movimento Temporário de
Pessoas Físicas`. Na NT009, esses dois valores possuem outros significados e
existe `5 = Consumo concomitante no território nacional e exterior`.

A prova nativa `comext-native-validation.json` confirmou em libxml: modo
transfronteiriço 1 e modo antigo 4 estruturalmente válidos; modo misto 5 e
elemento nFatura inválidos no pacote de homologação preservado. O teste usa
entradas sintéticas de estrutura, sem qualificar uma operação internacional
ou confirmar o código de moeda pela tabela BCB. O script passou por sintaxe
PHP e Pint direcionado.

Após todos os scripts de diagnóstico desta retomada, o Pint geral final
(`scope73-final-pint-all`) passou em **504 arquivos**, exit 0. O auditor
extrai essa contagem da saída terminal e preserva o log histórico de 501
arquivos como evidência do checkpoint anterior.

A seleção de um pacote novo não pode reaproveitar as descrições antigas dos
modos nem usar o sucesso da série numérica como aceitação NT009. A Pesquisa 01
foi relida integralmente nesta retomada e mantém a necessidade de versionar
ambiente, mapper, schema, regras de negócio e datas de forma independente.
