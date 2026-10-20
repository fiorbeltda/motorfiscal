# Avaliação dos importadores — 16/09/2026

## Catálogo mestre LC 116

Artefato oficial: `lc116-consolidada-senado-2026-09-16.html`.

- SHA-256: `da3ba7e8c199eb9293f76274767098fd804d7ec600a05094d9be71372c675cd5`.
- Itens: 40.
- Subitens: 205, sendo 200 ativos e 5 vetados.
- Vetados: `03.01`, `07.14`, `07.15`, `13.01` e `17.07`.
- Links e anotações legislativas são preservados separadamente das descrições legais.

## Catálogo mestre cTribNac

Artefato oficial: `anexo-b-servicos-nacionais-v1.01-20260122.xlsx`.

- SHA-256: `e74b0be8ad204e458932efb1716225a3fb5703a84e5475ce5c4c6789d1c61b1a`.
- Aba obrigatória: `LISTA.SERV.NAC.`.
- Códigos: 338, sendo 337 ligados à LC 116 e 1 especial sem vínculo artificial (`990101`).
- Subitens ativos da LC 116 referenciados: 200; 138 possuem um código e 62 possuem múltiplos códigos.
- O leitor OOXML não avalia fórmulas, rejeita DTD/entidades e restringe a relação selecionada a `xl/worksheets`.
- Códigos são preservados como strings e recebem zeros à esquerda a partir das dimensões oficiais.

A reconciliação dos dois catálogos mestres com o catálogo territorial confirma que `200102` existe no Anexo B e está ausente do derivado territorial. Nenhuma estratégia é inferida para preencher essa lacuna.

Os dois artefatos permanecem `CANDIDATE` enquanto a vigência jurídica não for comprovada. O trecho `20260122` no nome do XLSX não é usado como `effective_from`.

## ZIP de alíquotas municipais

Artefato: `aliquotas-municipios-20260903-extr1.zip`.

- SHA-256 do artefato: `1519370b7c31ad9814a17875d6ead90f4e5c400217ac0f59e1a7276b6de24a56`.
- Arquivos CSV processados em streaming: 26.
- Linhas lidas: 1.919.368.
- Linhas aceitas: 1.918.512.
- Linhas rejeitadas: 856, todas sinalizadas por vigência invertida (`dt_fim` anterior a `dt_ini`).
- Alíquotas vazias preservadas como desconhecidas: 18.480.
- Municípios encontrados: 5.341.
- Códigos completos de serviço encontrados: 333.
- Duplicidades normalizadas: 0.
- Hash lógico: `e3710b4f3e42890fecb35a87e2f7670d54b4e80f17dc042489cc783b30bdaf61`.

Duas execuções independentes do dry-run produziram o mesmo hash lógico. Isso confirma a leitura determinística do artefato; não afirma cobertura nacional completa nem validade jurídica de cada linha.

O código completo oficial `NN.NN.NN.NNN` é preservado. O importador também deriva separadamente o `cTribNac` de seis dígitos e o sufixo municipal, sem converter códigos para número.

## Catálogo territorial do ISS

Artefato derivado: `Anexo_I_NFSe_ISSLocationEngine.md`.

- SHA-256: `59a929abd7d1fe3d8bd815adf1250eeacfb4d910b61238a964c3e72a1cdf1107`.
- Linhas declaradas e importadas: 337.
- Referência declarada de `cTribNac`: 338.
- Estratégias: `EP` 277, `LP` 58, `ET` 1 e `NONE` 1.

O importador não silencia a diferença de cobertura. Ele gera `TERRITORIAL_CATALOG_PARTIAL_AGAINST_CTRIBNAC_REFERENCE` e registra a divergência interna nos códigos:

`14.14.01`, `14.14.02`, `14.14.03`, `14.14.04`, `20.01.01`, `20.02.01`, `20.03.01` e `11.03.01`.

Esse anexo é fonte derivada e versionada, não verdade normativa imutável. As inconsistências devem ser resolvidas contra fontes oficiais antes da promoção do bundle.

## Comportamento seguro

- Arquivos ZIP são lidos sem extrair todo o conteúdo em memória.
- `;`, BOM UTF-8 e decimais com vírgula são suportados.
- Alíquota vazia nunca é convertida em zero.
- Uma importação persistente registra artefato, SHA-256, versão, vigência, manifesto e execução.
- Reimportar o mesmo conteúdo é idempotente; sobrescrever uma versão imutável com conteúdo diferente é rejeitado.
- Cobertura, lacunas, sobreposições e diffs são relatados separadamente.
