# Referência geográfica116 — integração direcionada

O bundle `CONSUMPTION-PHASE4B-2026.10.20-116-draft2` integra a referência
observada115 como checagem auxiliar de consistência. Não determina domicílio,
local de incidência, validade territorial histórica ou natureza jurídica de
município. Não seleciona alíquota nem autoriza lançamento ou produção.

## Entrega e limites

- Guarda nos caminhos ordinário/importação, doação nominal e parcela doméstica
  da exportação parcial, sem opt-in fornecido pelo chamador.
- UF desconhecida e prefixo município/UF conflitante recusados como INVALID;
  código ausente do retrato observado pede revisão como ACTION_REQUIRED.
  Ausência no retrato não prova inexistência histórica.
- Política, observação, três fontes e mapas canônicos vinculados. O catálogo116
  recusa inclusive a ausência simultânea da referência e da política. Os
  catálogos históricos sem esses campos preservam seu comportamento anterior.
- Operação de25/09 com conhecimento posterior à observação de20/10 recebe
  somente uma checagem referencial explícita, não aprovação retroativa. Nenhuma
  vigência foi inventada. Os nove indicadores de aprovação material permanecem
  falsos na trilha, inclusive quando a referência é consistente.
- Mapas completos:27UFs e5.571 registros territoriais. Boa Esperança do Norte,
  Brasília e Fernando de Noronha preservados sem inferir sua natureza jurídica.

## Verificações observadas

Execução final após o reforço de vinculação: **96 testes/416 verificações**,
dos quais80 testes permanentes116 e16 da correção temporal114. Inclui ambos
os executores, quatro caminhos, positivos, três recusas geográficas, cinco
mutações de referência/política e controles de conhecimento histórico.
PHPStan geral sem erros; Pint geral aprovado em584 arquivos.

CLI-v2:16 cenários,32 decisões nativas e32 replays exatos. A avaliação de
produção conferiu97/97 fontes e manteve126 bloqueios, `ready=false` e
`eligible_for_local_validation=true`. A conferência de entradas antes/depois
do coletor foi aprovada. CLI-v1 é o corte anterior ao reforço de vinculação;
não somar as duas execuções como cenários distintos.

O importador da fixture recebeu três fatos ISS sintéticos explícitos para
alcançar a guardaRTC, sem alterar as guardas ISS existentes. A fixture de
exportação parcial desliga a avaliação de preços112 neste corpus geográfico.
Portanto, estes32 resultados não substituem a expansão dos124 cenários112,
as composições de preço/câmbio capturadas ou seus replays históricos.

Os positivos preservam o conteúdo fiscal anterior: a comparação admite apenas
as novas identidades/hashes vinculados de bundle e catálogo e a nova etapa
auxiliar na trilha. Não elimina genericamente versões, valores ou provas.

## Tentativas preservadas

O primeiro manifest116 utilizou hash bruto para os controles, cujo contrato
exige JSON canônico; foi preservado e substituído pelo draft2, sem reescrita.
A saída da primeira execução de testes não ficou disponível no contexto;
nenhum aceite foi atribuído a ela. A execução v2 teve70 testes aprovados e
duas divergências de metadados esperados. Os testes foram corrigidos para
conferir explicitamente a nova identidade; v4 passou88/384 após formatação.
O reforço contra remoção simultânea dos campos acrescentou oito casos,
levando a v5 final96/416. Os logs e fontes anteriores estão preservados.

## Evidências e continuidade

Diretório: `D:/ProjetoSaasExports/phase4b-geographic116-integration-20261020`.

- `before-edit.json` e `ConsumptionReformEngine-before116.php`.
- `resource-build-v1/resource-build.json` e
  `resource-build-v1/control-hash-binding-correction.json`.
- `directed-v5.log`, `phpstan-v2.log` e `pint-final-v2.log`.
- `cli-v2/geographic116-cli-validation.json`, snapshots, saídas e execuções.
- Verificador independente: `scripts/verify_phase4b_geographic116_evidence.py`.
- `geographic116-targeted-validation.json`:16.817 conferências independentes,
 88 hashes atuais, fontes/linhas oficiais, entradas do corpus e evidências
 direcionadas conferidas. Não é aceite integrado de desenvolvimento116.

Último aceite integral:112,7.738 testes/116.599 verificações. Depois dele,
duas classes anteriores mudaram de forma autorizada: TransitionCatalog114 e
ConsumptionReformEngine116; há também o novo componente geográfico. Não
reescrever os hashes do aceite112 para fazer o runtime atual parecer aceito.

Restam expansão/revinculação de corpus, replays históricos, regressão completa
pós114/116 e auditoria independente de aceite integrado. A entrega delimitada
está em5/6 marcos; isso não é percentual funcional global da4B. Os20 IDs
examinados nos cortes114/115 não são20 requisitos aprovados;60 ainda não foram
revistos nesses cortes e nenhum dos oito grupos está formalmente encerrado.
Base/câmbioISS, documentos e demais famílias continuam no escopo original.
Fases3/4A encerradas emSandbox;4B parcial;5–7 planejadas; produção bloqueada.
