# B09 — Invariantes de crédito (Agente B, 03/11/2026)

Base `87d22e7` + B01–B08 locais, sem commit. Corte só de testes: nenhum código de produção alterado.

## Requisitos

4B.8.11 (invariantes), 4B.5.4 (estorno), 4B.5.5 (glosa) e 4B.5.9 (vínculo documento–crédito). Nenhum encerrado: o corte
prova as propriedades aritméticas e de vínculo do `CreditDecisionEngine`, não o ciclo material completo de estorno/glosa
(evento causal, registro e consumo) nem os demais perfis.

## Propriedades provadas (`tests/Fiscal/Unit/CreditInvariantsB09Test.php`, oráculo independente bcmath)

1. Crédito `ALLOWED` = entrada − Σ(estornos + glosas do mesmo tributo), arredondado half-up em 2 casas, nunca negativo.
   Espaço de 480 combinações com valores de borda `0.004`, `0.005`, `10.005` e `999999.99`, ajustes múltiplos e mistos.
2. Ajuste acima do disponível → `ACTION_REQUIRED` / `RTC_CREDIT_ADJUSTMENT_EXCEEDS_AVAILABLE`, nunca crédito negativo.
3. Monotonicidade: ajuste maior nunca produz crédito maior (estorno e glosa).
4. Independência IBS × CBS: ajuste de CBS não altera o componente IBS.
5. Estorno e glosa têm a mesma aritmética; outros tipos, tributos desconhecidos, valores negativos e falta de evidência são recusados.
6. Vínculo: todo componente `ALLOWED` carrega o `input_document_id` exato; saída determinística.

Resultado: 9 testes / 2.714 asserções aprovados (PHP 8.5.12, `memory_limit=256M`). Pint aprovado.
Teste dirigido; não é regressão integral.

## Limites e próximos passos

- Ciclo material de estorno/glosa (evento causal/documental, período de apropriação) continua pendente para 4B.5.4/4B.5.5.
- A validação completa da B07 continua sem resultado terminal (interrompida por falta de memória).
