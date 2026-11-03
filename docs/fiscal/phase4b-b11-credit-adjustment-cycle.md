# B11 — Ciclo de estorno e glosa de crédito (Agente B, 03/11/2026)

Prioridade 1 definida pelo usuário em 03/11/2026. Base `87d22e7` + B01–B10 locais, sem commit.

## Requisitos

4B.5.4 (estorno), 4B.5.5 (glosa) e 4B.5.9 (vínculo documento–crédito). Nenhum encerrado: o ciclo é local e autônomo,
ainda não ligado ao fluxo integrado (`ConsumptionReformEngine.php`, escritor único A), e os acréscimos não são calculados.

## Fundamento legal (LC 214/2025, Planalto, 2026-11-03, `lc214-planalto-observed-20261103.htm`, `0a0d190b…673c`)

| Causa (`cause`) | Tipo | Base | Acréscimos |
|---|---|---|---|
| `PERISHED`, `DETERIORATED`, `ROBBERY`, `THEFT`, `LOSS` | REVERSAL | art. 47, §6º | não, se registrado no período do evento; registro tardio → `LATE_REGISTRATION_INTERPRETATION_PENDING` |
| `FIXED_ASSET_ROBBERY_OR_THEFT` | REVERSAL | art. 47, §7º (proporção "definida em regulamento") | idem; exige `regulation_proportion` e `regulation_source_id` |
| `IMPROPER_APPROPRIATION` | DISALLOWANCE | art. 45, §2º; art. 29, §§2º–4º | `LEGAL_ADDITIONS_REQUIRED` desde a apropriação, quando o crédito é de período anterior |
| `PRESUMED_CREDIT_CONDITION_UNMET` | DISALLOWANCE | art. 447, §2º; art. 465, §2º | `LEGAL_ADDITIONS_REQUIRED`; só para crédito `PRESUMED` |

- "Glosa" de crédito: a LC 214 só usa "glosa" no art. 130 (glosa médica, base de cálculo, 4B.4/A). Para crédito,
  o ciclo modela a anulação por apropriação indevida (art. 45, §2º; infração no art. 341, XV) e a perda do crédito
  presumido. **A associação tipo↔causa é interpretação de engenharia** documentada, não texto legal literal.

## Ciclo (`App\Modules\Fiscal\ConsumptionReform\CreditAdjustmentCycleB11::apply`)

1. Evento causal com `event_id` único, causa do catálogo legal, crédito existente e mesmos tributo e documento (vínculo 4B.5.9),
   evidência não vazia, data válida e não anterior à apropriação.
2. Registro no período de apuração informado (ajuste no saldo, art. 45, §1º). Evento com data posterior ao período fica
   em `deferred`, sem registro retroativo.
3. Saldo por crédito: apropriado, ajustado acumulado, registrado no período e `available_for_consumption`. Ajuste acima
   do disponível é recusado. IBS e CBS ficam separados.
4. Trace por evento com hash de cada lançamento e `result_hash` determinístico, independente da ordem de entrada.
5. Limites sempre presentes: `payment_or_extinction_proven=false`, `legal_additions_computed=false`, `official_homologation=false`.

## Dependências externas (não inventadas)

- Valor dos acréscimos: multa de 0,33% ao dia limitada a 20% e juros pela Selic mensal + 1% no mês do pagamento (art. 29, §2º)
  dependem da série Selic oficial e da data de pagamento. Corte futuro, com fonte oficial e hash.
- Proporção do ativo imobilizado (art. 47, §7º): definida em regulamento. Os testes usam a fonte sintética identificada
  `SYNTHETIC.TEST.REGULATION`.
- Registro tardio de estorno do art. 47, §6º: incidência de acréscimos é questão jurídica pendente; o ciclo não decide.

## Validação

`tests/Fiscal/Unit/CreditAdjustmentCycleB11Test.php`: 31 testes / 149 asserções.
- Positivos: as 7 causas, acumulação, independência IBS/CBS e diferimento de evento futuro.
- Determinação de acréscimos: 5 situações.
- Ativo imobilizado com e sem parâmetro de regulamento.
- 13 recusas e duplicidades.
- Determinismo sob permutação.
- Oráculo bcmath em 16 combinações.

PHPStan e Pint aprovados. Teste dirigido; não é regressão integral.
