# B17–B19 — Créditos presumidos, ciclo completo e aceite da alíquota sintética (Agente B, 03/11/2026)

Base `87d22e7` + B01–B16 locais, sem commit. Fonte: LC 214/2025, texto compilado do Planalto
(`resources/fiscal/sources/official/lc214-planalto-observed-20261103.htm`, `0a0d190b…673c`), com trechos conferidos como
únicos e não substituídos.

## B17 — Catálogo de créditos presumidos (4B.5.1)

| Hipótese | Base | Percentual | Situação no motor |
|---|---|---|---|
| Bem industrializado nacional com alíquota zero (ZFM) | art. 447; uso: art. 452 | 7,5% (S/SE exceto ES) ou 13,5% (N/NE/CO e ES) | calcula, com habilitação e prova |
| Idem nas Áreas de Livre Comércio | art. 465; uso: art. 468 | 7,5% / 13,5% | calcula, com habilitação e prova |
| Resíduos sólidos de coletor incentivado | art. 170 (exclusões do §3º; óleo lubrificante do §4º) | IBS: 1,3/2,6/3,9/5,2% em 2029–2032, 13% a partir de 2033; CBS: 7% | calcula. Antes de 2027 → pergunta (produção de efeitos não qualificada); IBS antes de 2029 → sem percentual |
| Produtor rural não contribuinte | art. 168 | ato conjunto anual (§4º) | exige parâmetro com fonte |
| Transportador autônomo de carga pessoa física | art. 169 | ato conjunto anual | exige parâmetro com fonte |
| Bem móvel usado de pessoa física para revenda | art. 171 | alíquotas de referência | exige parâmetro com fonte |
| Bem intermediário para indústria incentivada da ZFM | art. 449 (§2º: só o valor agregado na encomenda) | 7,5% | calcula, com habilitação e prova |
| Importação para revenda presencial na ZFM (regular ou Simples) | art. 444 | 50% da alíquota de IBS na importação | exige parâmetro com fonte |
| Importação para revenda presencial nas ALC | art. 462 | 50% da alíquota de IBS na importação | exige parâmetro com fonte |
| Indústria habilitada das ALC (CBS) | art. 467 (exclusões do §2º) | 6% | calcula |
| Indústria incentivada da ZFM | art. 450, redação da LC 227 (exclusões do §3º) | IBS 55/75/90,25/100% do saldo devedor; CBS 6% (art. 454) ou 2% | calcula; venda para a União (§5º) → pergunta |
| Regime automotivo (Lei 9.440, art. 11-C) | arts. 309–311 | 11,60/10/8,70% por mês de fruição; redução de 20% ao ano em 2029–2032 (leitura de engenharia); extinto em 2033 | calcula, com habilitação e elegibilidade |
| Regime automotivo (Lei 9.826) | art. 312 | produto de fatores da legislação externa | exige fatores |
| Estoque em 1º/01/2027 | art. 381 (§2º: ato do Poder Executivo) | — | exige ato |
| Créditos de PIS/Cofins por depreciação | art. 380 | — | exige dado legado |

Parâmetro `SYNTHETIC_TEST` calcula com `status=PRESUMED_CREDIT_SYNTHETIC_TEST_ONLY` e `official_decision=false`.
O catálogo nunca apropria crédito (`credit_appropriated=false`). Todas as hipóteses de crédito presumido da LC 214 identificadas no texto vigente estão catalogadas.
Teste: 44 testes / 141 asserções.

## B18 — Ciclo completo do crédito (4B.5.2, 4B.8.11)

O ciclo apropriação → estorno (B11) → crédito presumido (B17) → utilização (B14) conserva valor exatamente.
Ajuste no B14: crédito com `refund_prohibited` (arts. 452 e 468) não entra no ressarcimento e continua só compensável.
Teste: 3 testes, mais a regressão do B14 (18 testes / 51 asserções no total).

## B19 — Critério de aceite da correção do seletor de alíquotas (frente A)

`tests/Fiscal/Unit/SyntheticRateAcceptanceB19Test.php`. Enquanto o `TemporalRateCatalog` tratar a linha `SYNTHETIC_TEST`
como oficial, o teste fica **pendente** (skip), com a mensagem `PENDENTE AGENTE A`. Depois da correção, precisa passar:
uma alíquota sintética nunca é resolvida como oficial, e as alíquotas oficiais de 2026 continuam válidas.
