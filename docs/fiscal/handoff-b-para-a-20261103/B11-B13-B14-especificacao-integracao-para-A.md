# Especificação de integração B11/B13/B14 → Agente A (03/11/2026)

O Agente A é o escritor único de `ConsumptionReformEngine.php`. Os componentes abaixo são do B, locais e autônomos.
Nenhum deve ser ativado em catálogo ou bundle já aceito (os replays e hashes históricos precisam ser preservados).

| Componente (B) | Contrato | Onde entraria no fluxo |
|---|---|---|
| `AcquirerCreditProhibitionCatalogB13::qualify(operation)` | `CREDIT_PROHIBITED` / `CREDIT_LIMITED_TO_SUPPLIER_EXTINGUISHED_DEBITS` / `NOT_PROHIBITED_BY_STATUTORY_CATALOGUE` / `ACTION_REQUIRED` / `INVALID`; nunca concede crédito | Antes do `CreditDecisionEngine`, para crédito de entrada: `CREDIT_PROHIBITED` → componente `DENIED` com a base legal do B13 |
| `CreditAdjustmentCycleB11::apply(credits, events, period)` | Registros de estorno/glosa com causa legal, saldo `available_for_consumption` e acréscimos determinados (não calculados) | Apuração periódica (art. 45), fora da decisão por operação |
| `CreditUtilizationPlannerB14::plan(credits, debts, period, refund, transfers)` | Compensações pela ordem do art. 53, segregação IBS/CBS, expiração do art. 54; não extingue débitos | Apuração periódica, após o B11 |

Fatos novos sugeridos para o B13, a serem criados por B na nova versão do bundle depois do acordo:
- `rtc.acquired_regime`
- `rtc.fuel_destination`
- `rtc.credit_exception_evidence_id`
- `rtc.employee_collective_agreement_plan`
- `rtc.saf_sports_rights_acquisition`
- `rtc.financial_permission_article`

Gate: uma política no estilo `CreditZeroSemanticsPolicyB02`, presente só em uma nova versão de catálogo.

Testes do lado B prontos:
- `CreditAdjustmentCycleB11Test` (31)
- `AcquirerCreditProhibitionCatalogB13Test` (31)
- `CreditUtilizationPlannerB14Test` (15)

Após o patch do A, B valida o fluxo com os novos fatos e confirma que as suítes históricas continuam iguais.

## Achado B16 (03/11/2026): parâmetro sintético no seletor de alíquotas

`TemporalRateCatalog::resolve` (arquivo da frente A) seleciona como oficial uma linha `ACTIVE` sintética, por exemplo uma
CBS 2027 de teste. Pela orientação do usuário, casos sintéticos devem ser identificados e nunca tratados como decisão
oficial. Sugestão:
- campo `source_kind` (`OFFICIAL` | `SYNTHETIC_TEST`);
- recusa de linhas sintéticas fora de contexto explícito de teste;
- marcação no trace e na decisão.

O teste de caracterização está em `tests/Fiscal/Unit/PeriodScenarioMatrixB16Test.php`.
