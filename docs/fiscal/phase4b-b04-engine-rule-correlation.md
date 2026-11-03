# B04 — Regras do motor × correlação oficial (Agente B, 03/11/2026)

Base `87d22e7` + B01–B03 locais, sem commit. Relatório de revisão: nenhuma regra ou correlação foi alterada.

## Requisitos

4B.6.6 (regra do motor quando não há correlação oficial), 4B.6.7 (distinguir obrigatoriamente) e
4B.6.9 (`ENGINE_DECISION_RULE`). Nenhum encerrado.

## Método

`EngineRuleCorrelationReconcilerB04` compara cada uma das 99 `ENGINE_DECISION_RULE` do catálogo ativo
`rtc-codes-2026.10.27-141-draft1.json` com as linhas literais `OFFICIAL_CORRELATION` que compartilham um
prefixo NBS da regra, nas edições 1.01.00 (ativa) e 1.02.00 (B01). Uma linha converge quando cClassTrib e
cIndOp (ou um dos cIndOp por presença) coincidem. Recusa conjuntos com regras de outro tipo ou com
correlação que alegue autoridade decisória.

## Resultado (igual nas duas edições)

| Status | Regras |
|---|---:|
| `CONVERGENT_LITERAL_ROW_EXISTS` | 4 |
| `DIVERGENT_NO_LITERAL_MATCH` | 26 |
| `NO_OFFICIAL_ROW_IN_PREFIX` | 57 |
| `NOT_COMPARABLE_NO_NBS_PREFIX` | 12 |

- Hash canônico do relatório: `293055a2…be45`; artefato
  `storage/app/phase4b-b04-development-20261103/b04-reconciliation-report.json`.
- As 26 divergências se concentram em variantes que a correlação oficial não carrega: redução de 60%
  (`200028`/`200029`), cooperativa (`200020`), imunidades (`410006`/`410007`) e cIndOp por presença
  (`030101`/`030102`) nas famílias do Anexo (04.01.01, 08.01.01, 08.01.02, 08.02.01, 25.01.01, 25.02.02).
- **Divergência não invalida regra e convergência não confirma regra.** A correlação é provisória e sem
  regras de negócio no portal. As 26 regras divergentes e as 57 sem linha oficial seguem `SANDBOX_REVIEW_REQUIRED`
  e são a lista objetiva para a revisão jurídica de 4B.6.6.
- A edição 1.02.00 não mudou nenhum status frente à 1.01.00 para as regras atuais.

## Validação

- `tests/Fiscal/Unit/EngineRuleCorrelationReconcilerB04Test.php`: 7 testes (totais e hash fixados, entradas não
  mutadas, determinismo sob permutação, variantes, três recusas, artefato igual ao recomputado).
- Oráculo independente em Python (`E:/dev/motorfiscal-agente-b-evidence/tools/oracle_b04.py`): 198 pares
  regra×edição idênticos.
- Pint e PHPStan aprovados. Teste dirigido; não é regressão integral.
