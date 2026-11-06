# B39: matriz de resultados esperados 2026–2033 por família (06/11/2026)

Itens 4B.1.1 (fases de 2026 a 2033) e 4B.8.12 (cenários de 2026, 2027, transição e regime final).

## Método

- **Teste:** `tests/Fiscal/Integration/TemporalMatrixB39Test.php`.
- **Fixture:** `scripts/agent_b/temporal_matrix_b39_fixture.php`.
- **Evidência gravada:** `storage/app/phase4b-b39-development-20261106/temporal-matrix-b39.json`.

São cinco famílias, todas avaliadas no bundle mais recente (`2026.11.06-b38-draft1`):
- SaaS no regime geral;
- hotelaria (B35);
- agência de turismo (B36);
- locação de imóvel (B37);
- intermediação de plano de saúde (B38).

Só mudam a data da operação e a do pagamento. Cada ano é avaliado 30 dias depois da própria operação, como seria na
prática, e o relatório de domicílio é refeito com evidências observadas depois de cada operação.

## Resultado observado (sem estimativa)

| Família | 2026 | 2027–2033 |
|---|---|---|
| SaaS, regime geral | Calcula: base 1.000,00; CBS 9,00; IBS 1,00 | Para em `RTC_CINDOP126_OUTSIDE_LOCAL_COVERAGE` |
| Hotelaria (B35) | Calcula: CBS 5,40; IBS 0,60 | Idem |
| Agência de turismo (B36) | Calcula: base 350,00; CBS 1,89; IBS 0,21 | Idem |
| Locação (B37) | Calcula: base 700,00; CBS 1,89; IBS 0,21 | Idem |
| Intermediação de plano de saúde (B38) | Calcula: CBS 3,60; IBS 0,40 | Para em `RTC_UNIFORM_NATIONAL_REFERENCE_RATE_ROUTE_NOT_MATERIALIZED` |

A tabela cIndOp local (RTC 1.03.00) cobre só 2026. A tabela que vale de 2027 em diante ainda não está comprovada.

## Próximo bloqueio

Para identificar o próximo bloqueio, uma segunda passada remove, só em memória, a referência cIndOp local de 2026
(marcada como sintética e sem efeito legal):

| Anos | Bloqueio seguinte | Dependência |
|---|---|---|
| 2027–2028 | `RTC_RATE_NOT_PROVEN:CBS` | Alíquota da CBS de 2027 (resolução do Senado prevista até 15/12/2026) |
| 2029–2033 | `RTC_RATE_NOT_PROVEN:IBS_STATE` | Alíquotas do IBS na transição 2029–2032 e no regime final de 2033 |
| Planos de saúde, 2027+ | Rota de alíquota de referência uniforme | Alíquotas de referência publicadas |

## Conclusão

- A matriz prova que o motor calcula 2026 em todas as famílias.
- Também prova que, de 2027 em diante, ele recusa com o motivo exato em vez de estimar.
- O cálculo positivo de 2027+ fica liberado quando forem publicados:
  - a tabela cIndOp vigente;
  - as alíquotas da CBS e do IBS de cada ano.

  Para isso basta um bundle novo com esses parâmetros, sem mudar código.
