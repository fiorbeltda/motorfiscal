# B14 — Plano de utilização de créditos (Agente B, 03/11/2026)

Base `87d22e7` + B01–B13 locais, sem commit. Requisito: 4B.5.2 (apropriação ponta a ponta). Continua **parcial**:
o plano é local e não está integrado ao fluxo do motor (`ConsumptionReformEngine.php`, escritor A).

## Fundamento (LC 214/2025, Planalto, 2026-11-03, `0a0d190b…673c`)

- art. 47, §1º, I: apropriação segregada; **vedada, em qualquer hipótese**, a compensação cruzada IBS×CBS.
- art. 53, I–III: ordem de compensação.
  - I: saldo vencido de períodos anteriores, não inscrito em dívida ativa;
  - II: débitos do mesmo período, em ordem cronológica;
  - III: débitos de períodos subsequentes. O ressarcimento do §1º é a alternativa a este inciso.
- art. 53, §2º: valor nominal, vedada correção monetária.
- art. 54: o direito expira após 5 anos, contados do 1º dia do período seguinte ao da apropriação.
- art. 55: vedada a transferência, salvo sucessão (fusão, cisão, incorporação), preservada a data original.

## Comportamento (`CreditUtilizationPlannerB14::plan`)

Gera as compensações com o passo e a base legal, os créditos e débitos remanescentes, os créditos expirados, os débitos
excluídos (dívida ativa ou anterior sem situação de vencido) e a reserva para ressarcimento. Também recusa correção
monetária, entradas malformadas, crédito de período futuro e transferências não sucessórias.

Consumir primeiro o crédito mais antigo é **escolha de engenharia**; a lei não ordena créditos.

Limites sempre presentes: `debts_extinguished=false`, `official_compensation_proven=false`.

## Validação

`tests/Fiscal/Unit/CreditUtilizationPlannerB14Test.php`: 15 testes / 35 asserções.
- Ordem do art. 53.
- Segregação IBS/CBS.
- Bordas do prazo de 5 anos.
- Sucessão.
- Ressarcimento.
- Exclusões.
- Recusas.
- Conservação de valor por oráculo bcmath e independência de ordem.

PHPStan e Pint aprovados. Teste dirigido; não é regressão integral.
