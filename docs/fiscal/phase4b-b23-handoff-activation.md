# B23 — Ativação dos handoffs A155 (H1–H5) em bundle versionado (04/11/2026)

Agente B. Base: `213f2c5` (Codex, "finaliza handoffs H1-H5 para o Claude"), sobre `e3a632b`.
Sem commit nem push nesta task; publicação só por ordem do usuário.

## O que o A entregou (verificado no código)

| Handoff | Entrega do A (213f2c5) | Verificação do B |
|---|---|---|
| H1 | `ConsumptionReformEngine` chama o `CreditDecisionEngine` via `CreditIntegration155` com `explainZero` sob o gate B02 | Ativo no B23; resultado idêntico ao candidato em memória do A |
| H2 | Tabela Q-B06-1 sob o gate B07 | 320 casos B07 aprovados no bundle B23 instalado |
| H3 | `CreditIntegration155` (B13 antes da decisão, B17 separado) e `PeriodCreditAssessment155` (B11→B14) | Ativo no B23; B13/B17 conferidos no bundle instalado |
| H4 | `TemporalRateCatalog` recusa `source_kind` não oficial e `synthetic` | B19 aprovado; caracterização B16 atualizada |
| H5 | `requirements44-handoff-H5.json` + recibo | Consolidado na matriz 80 (sem aceite integral) |

## O que o B fez

1. **Bundle `CONSUMPTION-PHASE4B-2026.11.04-b23-draft1`** (`scripts/fork_phase4b_b23_handoff_activation_bundle.py`),
   derivado do 141 sem alterá-lo. Muda só o catálogo de códigos (3 blocos de política lidos das constantes PHP),
   fatos (+11), perguntas (+11) e controles de produção. Bundles até o 141 continuam sem os gates.
2. **Testes do B ajustados ao estado novo** (o A não editou arquivos do B):
   - `PeriodScenarioMatrixB16Test`: a caracterização histórica (sintético aceito como oficial) passa a provar a recusa.
   - `CreditZeroSemanticsPolicyB02Test` e `AnnexStandardQualificationB07Test`: gate ausente no acervo histórico e
     presente só no B23; os 320 casos B07 deixam de ser pendentes e rodam no bundle instalado.
   - Oráculo B07 (`b07Outcome`): uma qualificação bloqueada (ACTION_REQUIRED/INVALID) interrompe a decisão integrada,
     que passa a carregar o status e o motivo RTC; uma qualificação válida fica em `decision.consumption_reform`.
3. **`HandoffActivationB23Test`** (novo): o bundle instalado reproduz o candidato em memória do A em 14 cenários
   (H1, H2, H3) nos dois runners. Só há duas diferenças, ambas esperadas e afirmadas no teste: o rótulo da versão do
   catálogo e o `QUESTION_UNMAPPED` do candidato, que o B23 elimina com as perguntas novas.
4. **Matriz 80 (B23)**: reclassificação dos itens B que dependiam do A e consolidação do relatório H5 do A.

## Classificação dos 36 itens B (B22 → B23)

| Estado | B22 | B23 |
|---|---|---|
| Integralmente implementado e validado no escopo | 5 | 11 |
| Funcional em Sandbox, validação externa pendente | 6 | 11 |
| Parcial dependente do A | 18 | 7 |
| Dependente de publicação oficial | 4 | 4 |
| Dependente de evidência ou ambiente | 3 | 3 |

Passam a integrais: 4B.5.3, 4B.5.7, 4B.5.10, 4B.6.6, 4B.6.9, 4B.8.3.
Passam a Sandbox: 4B.5.1, 4B.5.2, 4B.5.4, 4B.5.5, 4B.8.5 (Selic, atos conjuntos anuais, alíquotas futuras).
Continuam dependentes do A: 4B.5.6, 4B.5.8, 4B.5.9, 4B.8.6, 4B.8.7, 4B.8.11, 4B.8.12.

Itens A (relatório H5): 39 parciais, 4 implementados sem evidência integral, 1 lacuna real (4B.4.9). Nenhum conta
como concluído. 0/8 grupos encerrados; Fase 4B não concluída; nada pronto para produção.
