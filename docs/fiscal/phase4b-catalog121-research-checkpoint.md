# Catálogos121 — ponto de pesquisa preservado em20/10/2026

Pesquisa isolada,sem instalação de catálogo,alteração de runtime ou encerramento
formal dos requisitos4B.6. A contagem auditada permanece54/80 requisitos
examinados e26 ainda por revisar; a pesquisa121 não equivale a dez novos aceites.

## Conferência dos arquivos oficiais preservados

Leitura dos XLSX com Artifact Tool,sem exportação ou alteração das planilhas.
Comparador independente terminal `audit_materialization_v3.py`:4.421 verificações.
As tentativas anteriores do comparador permanecem preservadas; não são aceites.

| Referência | Linhas materializadas conferidas | Versão preservada |
| --- | ---: | --- |
| cIndOp |39|1.02.00|
| cClassTrib |173|IT2025.002 v1.70|
| CST |18|planilha CST2026-09-01|
| NBS |1.208|v1.01-20260122|
| Correlação |200|AnexoVIII1.01.00|

As1.208 entradasNBS incluem101 códigos numéricos de origem representados como
chaves de texto; não houve preenchimento inventado de códigos. As200
correlações são todas as linhas com NBS,cIndOp e cClassTrib preenchidos.
Outras1.321 linhas não vazias da fonte têm campos incompletos; não foi presumida
herança de células ou autorização decisória para essas linhas.

## Fontes atuais capturadas,separadas da versão instalada

Captura do portalRTC em20/10/2026 às14:45 deSãoPaulo:

- cIndOp1.03.00:SHA256`95f30a44ee94adeea57fb92f3a0337b1e62fdc106f393e73894087889be9e5aa`;
  planilha`cIndOp Public!A1:J41`,não ativada.
- LeiauteNT0091.04.01:SHA256`103a150dd6f56ec8edcaa3a453b59a1b0e096d7cd5025d7bd06a387f141ddf39`;
  sete abas extraídas para pesquisa,não ativadas.
- PáginaRTC:SHA256`01472c05a84612245cfebbfb439cede34afce693832ac84014fb19cdeb81feed`.

O[portalRTC](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc)
distingue versões e informa que o AnexoVIII é trabalho inicial,sem regras de
negócio baseadas nele em produção restrita ou produção. Disponibilidade de
arquivo não comprova implantação nem autoriza seleção tributária automática.

Nova captura do cClassTrib no portalNF-e às14:47 confirmou os mesmos bytes
preservados em101:SHA256
`bee3e106d24c06257d31105658679e97e2544dcc3ac61467fbe0ca4f78c6e850`.
O índice oficial consultado identifica publicação01/10/2026 eIT2025.002v1.70.
A primeira tentativa falhou por redirecionamentos; a segunda,com cookiesHTTP
normais,concluiu sem desativarTLS ou substituir a fonte por tabela de terceiros.

## Sonda nativa no catálogo119

`native-probe-v2`:2.981 casos de referência reproduzidos e20 cenários com40
chamadas aos executores. Foram capturadas24 decisões e16 recusas de integridade
do executor integrado diante de alterações deliberadas no catálogo em memória.
Essas recusas não são decisões fiscais ou exceções de entrada monetária120.

A sonda1alterou uma regraSaaS não usada pelo cenário de saúde selecionado;
foi preservada e não fundamenta conclusões sobre essa regra. A sonda2atinge a
regra efetivamente selecionada:
`RTC.CLASSIFICATION.ANNEX.040101.PERSON_PRESENCE.REDUCED_60-1`.

Resultados observados na chamada direta do domínio: ausência de regra é
`ACTION_REQUIRED`; regras elegíveis duplicadas são`INVALID`; conflito de código
informado é`INVALID`; correlação não substitui`ENGINE_DECISION_RULE`.
Remover as duas estruturas de referência e mudar a versão da fonte contorna
essa guarda na chamada direta,mas o executor integrado recusa o catálogo
adulterado por hash. Isso delimita uma possível melhoria de defesa em
profundidade,não demonstra alteração aceita no bundle instalado.

## Próximo trabalho

Comparar integralmente o cIndOp1.03.00 com a versão preservada,qualificar a
seleção temporal/implantação e concluir a revisão individual dos dez requisitos
4B.6. Não atribuir64 requisitos revistos antes dessa auditoria terminal.
Permanecem também os contratos de crédito120 e as lacunasISS/documentais.

Evidências completas,arquivos oficiais e tentativas anteriores preservados em:
`D:/ProjetoSaasExports/phase4b-requirements121-catalogs-audit-20261020-v1/`.
Nenhum arquivo desta pesquisa foi ativado no motor nem usado para mudar a
regressão integral119. Produção permanece bloqueada.
