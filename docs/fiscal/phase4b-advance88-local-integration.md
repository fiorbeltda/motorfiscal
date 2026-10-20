# Antecipações88: origem anterior ao fornecimento, integração local

## Aceitação de desenvolvimento concluída

Suíte terminal em15/10/2026: **2.678 testes/48.287 verificações**, exit0,
1.668,32s nativos. Auditoria final conferiu4.692 arquivos sem alterações,
adições ou remoções; PHPStan geral e Pint534 finais aprovados;59 fontes
íntegras e119 bloqueios de produção preservados.24 cenários nos dois runners,
48 decisões/replays finais e5 replays históricos.11 arquivos de evidência
copiados por hash para `storage/app/phase4b-advance88-development-20261015/`.

Relatório: `advance88-development-validation.json`; cópias conferidas em
`evidence-copy-receipt.json`. Esta aceitação cobre origem e nominal anteriores
ao fornecimento, não registro anterior ou liquidação temporal/documental
completos, Fase4B ou produção.89/90 permanecem externos e seus43/28 casos
não são novos testes permanentes desta suíte. As seções abaixo preservam
o escopo e a cronologia dos gates direcionados.

## Escopo implementado

Bundle próprio `consumption-phase4b-2026.10.15-88-draft1.json`, predecessor86,
com dois fatos/perguntas novos: `rtc.assess_advance_origin` e
`rtc.advance_origin_contexts`. Reutiliza identidades explícitas de operação e
parcela. Fontes oficiais59, taxas e bloqueios de produção do predecessor
permanecem preservados. Não cria taxa futura, dispensa de caixa ou aprovação.

`AdvanceOnlyOrigin87`, `AdvanceOnlyNominal87` e `AdvanceContext88` qualificam
o pagamento de uma parcela antes do fornecimento, sem inventar fornecimento
concluído. Exigem prova vinculada de que o fornecimento não ocorreu até a data
de referência, identidade de operação/contrato/fornecedor, pagamento e parcela.
Ausência explícita de documento permite apenas o seletor isolado; no fluxo
integrado, sem documento fiscal vinculado, a obrigação permanece pendente.

A data do evento é o pagamento; a data da alíquota é a primeira entre a emissão
correspondente e o pagamento. A origem mantém ambas. Pagamento2027 com documento
vinculado2026 pode produzir memória nominal com taxa2026, mas não recebe dispensa
de caixa2026. Sem taxa CBS da data selecionada, recusa o cálculo sem fallback.

Após origem, o motor mantém qualificadores de incidência, destino, papéis,
classificação e base. Ajustes/reduções que exijam alocação por evento não
comprovada são recusados. Uma parcela RTC300 não altera a operação ISS1000:
a fixture usa referência sintética explícita de reconciliação; sem ela, mantém
`RTC_GROSS_OPERATION_RECONCILIATION_MISSING`. Competência ISS não é substituída.

O resultado nominal termina em `ACTION_REQUIRED` com
`RTC_ADVANCE_REGISTRATION_AND_DOCUMENT_OBLIGATION_NOT_PROVEN`. Snapshots nativos
registram trace e knowledge capturados, não débito fiscal anteriormente lançado.
Registro, extinção, apropriação, caixa e liquidação documental não são concedidos.

## Evidências direcionadas

- 238 testes/1.680 verificações, incluindo149 temporais86 anteriores,
  51 de origem/decimal,14 de política88 e24 integrados nos dois runners.
- PHPStan geral sem erros; Pint geral534 arquivos aprovado após formatação.
- Manifest de24 cenários em
  `storage/app/phase4b-advance88-preparation-20261015/advance88-case-manifest-v2.json`.
- Capturas e replays em `storage/app/phase4b-advance88-cli-20261015/`.
  A auditoria do corpus e a regressão completa têm gates próprios; testes
  direcionados não constituem aceitação integral de desenvolvimento.

Primeira tentativa integrada:13 passes/10 falhas por falta de referência de
reconciliação ISS/parcela e prova incompleta de ajuste nas fixtures. Preservada;
fixturesv2 corrigem provas, sem remover guardas. Primeira política88:9 passes/5
falhas pela ausência da flag explícita de lançamento nas recusas herdadas86;
o wrapper88 passou a declarar a negativa. Não alterou o formato dos retornos86.
O primeiro Pint reprovou seis questões de estilo; arquivos anteriores arquivados.
O primeiro avaliador CLI usou controles padrão14 não vinculados ao bundle88;
seu resultado é preservado e excluído da aceitação, que exige controles88 explícitos.

## Pendências do escopo temporal integral

Ainda faltam prova de registro anterior e consumo íntegro dos snapshots no
definitivo, conjunto completo de parcelas, uso único e estados de retificação,
cancelamento e definitivo já registrado. Integridade/replay não autenticam
remotamente documento ou pagamento. Não renomear dívida mensal vencida como
antecipação do próprio mês, nem agregar IBS estadual/municipal perdendo origem.

Continuam também a qualificação do período de apuração, liquidação documental,
demais espécies/operações temporais e a auditoria dos80 requisitos originais.
Contrato integral de continuidade: `phase4b-temporal87-advance-origin-contract.md`.
Fase4B e produção permanecem sem conclusão; nenhuma transmissão foi realizada.
