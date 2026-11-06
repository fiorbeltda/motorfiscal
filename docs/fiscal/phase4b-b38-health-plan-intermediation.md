# B38: intermediação de planos de assistência à saúde (06/11/2026)

Item 4B.4.10 (regimes específicos). Escopo: a intermediação de planos de saúde, que é a parte do regime de planos de
saúde tributada **por documento** em NFS-e.

## Fonte

LC 214/2025, arts. 237 e 240, no texto compilado do Planalto observado em 03/11/2026 (SHA256 `0a0d190b…4673c`).

| Regra | Artigo | Como o motor trata |
|---|---|---|
| IBS e CBS sobre o valor da operação, pela mesma alíquota do plano de saúde | 240 | Base = `rtc.gross_amount`, cClassTrib oficial **011003** (CST 011, indNFSe = 1) |
| Alíquota do plano = alíquota de referência de cada esfera reduzida em 60%, uniforme nacional | 237 | Em 2026 as alíquotas de teste são nacionais, então coincidem com as de referência e a redução de 60% fica exata. De 2027 em diante o motor recusa (`RTC_UNIFORM_NATIONAL_REFERENCE_RATE_ROUTE_NOT_MATERIALIZED`), porque usaria a alíquota do destino, que não é a de referência |
| Intermediário optante pelo Simples fica no Simples, salvo opção pelo regular | 240, par. único | O fornecedor precisa ser `REGULAR` |

## Implementação

- `HealthPlanIntermediationB38.php`, atrás do gate `health_plan_intermediation_b38`.
- Bundle `2026.11.06-b38-draft1`, com uma regra de classificação (`SANDBOX_REVIEW_REQUIRED`):
  - cTribNac 100104;
  - NBS 1.0906.12 e 1.0910.20;
  - destino no domicílio do adquirente (cIndOp 100301).

## Fora desta etapa

O regime das operadoras (arts. 234–239) e o dos concursos de prognósticos (arts. 244–248) são apurados por período e em
regime de caixa:
- **operadoras:** prêmios recebidos, mais as receitas financeiras das reservas, menos indenizações, cancelamentos,
  intermediação e administração;
- **prognósticos:** arrecadação, menos prêmios pagos e destinações legais.

Nos dois casos, o documento por operação é informativo (cClassTrib 820001, 820003 e 820004; CST 820). Isso exige um
componente de apuração periódica e a rota de alíquotas de referência uniformes, que é a próxima etapa.

## Testes

`tests/Fiscal/Integration/HealthPlanIntermediationB38Test.php`:
- fonte literal;
- cálculo de 2026: base de 1.000,00, com CBS 3,60 e IBS 0,40;
- recusa de 2027 em diante;
- recusa do Simples;
- recusa por falta de prova;
- NBS de outros seguros fica fora.
