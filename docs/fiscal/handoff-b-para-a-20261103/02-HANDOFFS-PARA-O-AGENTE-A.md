# Handoffs consolidados — Agente B → Agente A (03/11/2026)

O Agente A é o escritor único de `app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php` e de
`app/Modules/Fiscal/ConsumptionReform/TemporalRateCatalog.php`. O B não editou esses arquivos. Todos os gates estão desligados.
Regra comum: **ativar só em uma nova versão de catálogo/bundle**, nunca nos já aceitos (preserva replays e hashes).

Prioridade sugerida: H1 → H2 → H4 → H3.

---

## H1 — B02: causa distinta para todo resultado zero de crédito (prioridade 1)

1. **Problema / requisitos:** 4B.5.10 e 4B.5.3. Hoje, `CONDITIONAL`/`0.00` é idêntico para documento inválido e para tributo
   anterior não extinto; `DENIED` por uso pessoal vem sem motivo.
2. **Arquivos a examinar:** `ConsumptionReformEngine.php` (chamadas ao `CreditDecisionEngine`, perto das linhas 1085 e 1161 no
   HEAD); do lado B: `CreditDecisionEngine.php` e `CreditZeroSemanticsPolicyB02.php`.
3. **Comportamento atual comprovado:** saída histórica idêntica em 5.832 combinações (`CreditZeroSemanticsB02Test`).
4. **Esperado:** com o gate ativo, os componentes `RESOLVED` recebem `zero_semantics_version`, `zero_causes` e
   `amount_semantics`. Status e valores não mudam.
5. **Fundamento:** LC 214 arts. 47–50 (condições do crédito); rótulos de engenharia para condições já implementadas.
6. **Contrato:** `CreditDecisionEngine::resolve(..., bool $explainZero = false)`; gate
   `CreditZeroSemanticsPolicyB02::required($this->codes)` (chave `credit_zero_semantics_b02`).
7. **Patch sugerido:**
   ```diff
   +        $creditZeroB02 = CreditZeroSemanticsPolicyB02::required($this->codes);
   -        ...->resolve($facts, 'IMPORT', true, $inputCreditTreatment);
   +        ...->resolve($facts, 'IMPORT', true, $inputCreditTreatment, explainZero: $creditZeroB02);
   -        ...->resolve($facts, $market, treatmentDecision: $inputCreditTreatment);
   +        ...->resolve($facts, $market, treatmentDecision: $inputCreditTreatment, explainZero: $creditZeroB02);
   ```
8. **Testes que devem passar:** `CreditZeroSemanticsB02Test`, `CreditZeroSemanticsPolicyB02Test` e as suítes de crédito
   (referência sem o gate: 714 testes / 64.514 asserções, resultado idêntico).
9. **Riscos:** a saída de crédito entra no hash da decisão; ativar em catálogo antigo quebra replays.
10. **Consequência para o B:** com o patch, o B cria a versão de catálogo com o gate e valida; 4B.5.3 e 4B.5.10 deixam de depender do A.

## H2 — Q-B06-1: tributação integral declarada para NBS dos Anexos II/III (prioridade 1)

1. **Problema / requisitos:** 4B.6.6, 4B.6.9 e 4B.4.x (A). Em 40 regras, `STANDARD` é aceito para uma NBS de anexo mesmo com a
   descrição do anexo confirmada.
2. **Arquivos:** `ConsumptionReformEngine.php` (seleção de tratamento, cerca das linhas 724–790); do lado B:
   `AnnexStandardQualificationPolicyB07.php`.
3. **Atual comprovado:** 40/40 regras caracterizadas (`AnnexStandardQualificationB07Test`, conjunto "characterizes").
4. **Esperado:** a tabela de decisão de 7 situações em `docs/fiscal/phase4b-b07-annex-standard-qualification-spec.md`, com os
   motivos `RTC_ANNEX_*`. Nunca escolhe STANDARD por padrão nem presume REDUCED_60 sem prova.
5. **Fundamento:** LC 214 arts. 129 e 130; Anexos II e III (Planalto, conferidos e iguais ao catálogo).
6. **Contrato:** gate `AnnexStandardQualificationPolicyB07::required($this->codes)`; fatos novos
   `rtc.annex_noncorrespondence_justification`, `rtc.annex_noncorrespondence_evidence_id` e `rtc.annex_nbs_classification_confirmed`.
7. **Patch:** especificação completa no documento acima (A implementa no seu estilo).
8. **Testes:** os 320 casos B07 (40 regras × 8 situações) passam de pendentes a aprovados; o caso "fora do anexo" deve ficar igual ao histórico.
9. **Riscos:** regressão nas suítes de anexo e educação/saúde (referência: `AnnexServiceIntegratedTest`).
10. **Consequência para o B:** o B cria o bundle com o gate e os 3 fatos e executa os 320 casos.

## H3 — Integração dos componentes de crédito B11/B13/B14/B17 (prioridade 3)

1. **Problema / requisitos:** 4B.5.1, 4B.5.2, 4B.5.4, 4B.5.5, 4B.5.7 e 4B.5.9. Os componentes estão validados localmente, mas fora do fluxo.
2. **Arquivos:** `ConsumptionReformEngine.php`; componentes do B: `AcquirerCreditProhibitionCatalogB13`,
   `PresumedCreditCatalogB17`, `CreditAdjustmentCycleB11` e `CreditUtilizationPlannerB14`.
3. **Atual comprovado:** B13 31/136, B17 28/97, B11 31/149, B14 15 testes, B18 (ciclo completo com conservação de valor).
4. **Esperado:**
   - B13 antes do `CreditDecisionEngine` (`CREDIT_PROHIBITED` → `DENIED` com base legal);
   - B17 alimentando créditos presumidos (`official_decision=false` para parâmetro sintético);
   - B11 e B14 na apuração periódica (art. 45), fora da decisão por operação.
5. **Fundamento:** LC 214 arts. 45, 47, 49–55, 57, 168–171, 180, 199, 211, 226, 238, 243, 247, 276, 283, 285, 293, 303, 444, 447,
   449, 452, 465 e 468 (trechos vigentes conferidos).
6. **Contrato:** `E:\dev\motorfiscal-agente-b-evidence\handoff\B11-B13-B14-especificacao-integracao-para-A.md` (cópia em `evidencias/handoff/`).
7. **Patch:** chamada no fluxo atrás de um gate novo, no estilo B02.
8. **Testes:** os 4 conjuntos acima + regressões de crédito.
9. **Riscos:** dupla negação de crédito com o tratamento 122 (comparar `InputCreditTreatmentQualifier`).
10. **Consequência para o B:** o B cria a versão de catálogo e os fatos (`rtc.acquired_regime` etc.) e valida.

## H4 — Parâmetro sintético tratado como oficial no seletor de alíquotas (prioridade 2)

1. **Problema / requisitos:** 4B.8.5 e 4B.8.12 (e 4B.1.x, do A). `TemporalRateCatalog::resolve` seleciona uma linha `ACTIVE`
   sintética como oficial.
2. **Arquivo:** `app/Modules/Fiscal/ConsumptionReform/TemporalRateCatalog.php`.
3. **Atual comprovado:** `PeriodScenarioMatrixB16Test` (caracterização) e `SyntheticRateAcceptanceB19Test` (pendente).
4. **Esperado:** linhas `source_kind=SYNTHETIC_TEST` nunca resolvidas como oficiais (recusa, ou `official=false` na resposta);
   oficiais seguem resolvidas.
5. **Fundamento:** orientação do usuário (casos sintéticos identificados, nunca decisão oficial).
6. **Contrato:** campo `source_kind` (`OFFICIAL` | `SYNTHETIC_TEST`) nas linhas; resposta com `official` booleano.
7. **Patch sugerido:** no laço de `resolve`, `if (($row['source_kind'] ?? 'OFFICIAL') !== 'OFFICIAL') { continue; }`, salvo em
   contexto de teste explícito; ou incluir `'official' => false` no retorno.
8. **Testes:** `SyntheticRateAcceptanceB19Test` deve passar (deixar de ficar pendente); `PeriodScenarioMatrixB16Test` deve ser
   atualizado pelo B (a caracterização muda).
9. **Riscos:** catálogos atuais não têm `source_kind`, então o padrão `OFFICIAL` preserva o comportamento.
10. **Consequência para o B:** o B atualiza o teste B16 e reclassifica 4B.8.5.

## H5 — Relatório de integração do A (prioridade 1)

Pedido completo em `pedido-relatorio-integracao-A.md` (nesta pasta): base, diffs, requisitos 4B.1–4B.4, contratos com o B,
testes, hashes e pendências. Sem esse relatório, o B mantém os 44 itens do A como `PENDENTE_DE_INTEGRACAO_A`.
