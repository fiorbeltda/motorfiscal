# B30/B31: composição da base e arredondamento/Calculadora (05/11/2026)

O Agente B, assumindo o trabalho do A, cobre os itens 4B.4.1 a 4B.4.5 (base, exclusões, descontos, acréscimos e
reembolsos) e 4B.4.11 (arredondamento).

## B30: ajustes tipados do art. 12 §§1º–3º (LC 214, redação da LC 227/2026)

Componente `BaseCompositionB30`, gate `base_composition_b30`, bundle `2026.11.05-b30-draft1`. Os ajustes usam o fato
existente `rtc.base_adjustments`, e os tipos já aceitos mantêm o significado.

| Tipo | Efeito | Dispositivo |
|---|---|---|
| `PRICE_ADJUSTMENT_INCREASE` | soma | §1º, I |
| `INTEREST_FINE_CHARGE` | soma | §1º, II |
| `CONDITIONAL_DISCOUNT` | nunca reduz; soma de volta se o bruto foi declarado já líquido | §1º, III |
| `FREIGHT_CHARGED` | soma só se o transporte é do fornecedor ou por conta dele | §1º, IV |
| `TAXES_AND_PUBLIC_PRICES` | soma; recusa IBS, CBS, IPI, ICMS, ISS, PIS, Cofins e COSIP (excluídos pelo §2º) | §1º, V |
| `OTHER_CHARGED_AMOUNT` | soma com descrição (seguros, taxas) | §1º, VI |
| `COSIP_EXCLUSION` | subtrai | §2º, VI |
| `UNCONDITIONAL_DISCOUNT` (já existia) | o programa de fidelidade só vale se for não oneroso e do próprio fornecedor | §2º, III e §3º |

Base negativa depois dos ajustes é recusada. O teste é `BaseCompositionB30Test` (17 testes).

## B31: arredondamento e Calculadora oficial

Fonte: NT009, Anexo VI, regras de validação da DPS (RN). Componente `CalculatorReconciliationB31`, gate
`calculator_reconciliation_b31`, fato `rtc.official_calculator_result`, bundle `2026.11.05-b31-draft1`.

- **Conciliação:** vIBSUF, vIBSMun, vIBSTot e vCBS do motor (HALF_UP em centavos) são conferidos com o retorno da
  Calculadora oficial, com margem de R$ 0,01. Divergência acima disso devolve o código de rejeição oficial (E1568,
  E1572, E1556 ou E1582). O retorno da Calculadora é declarado: não é buscado nem autenticado.
- **Correção:** pela RN da vBC, PIS e Cofins só podem ser deduzidos **até 2026**. De 2027 a 2032 deduz-se o ISSQN
  (ICMS mantido pela janela do art. 12 §2º V). Sem o gate, o motor continuava aceitando PIS/Cofins até 2032.

O teste é `CalculatorReconciliationB31Test` (5 testes).

**Dependência externa:** a Calculadora oficial em si; o motor só confere o resultado que for informado.

## B32: preço com IBS/CBS embutidos (art. 12 §2º, I)

Componente `TaxInclusivePriceB32`, gate `tax_inclusive_price_b32`, fato `rtc.price_includes_ibs_cbs`, bundle
`2026.11.05-b32-draft1`. Quando o preço cobrado já inclui o IBS e a CBS da própria operação, a base é o maior valor em
centavos B tal que B + IBS(B) + CBS(B) = preço, com o mesmo arredondamento HALF_UP do motor. Exemplo em 2026:
R$ 1.000,00 com 1% → base R$ 990,10 e tributos R$ 9,90.

Se nenhum centavo satisfaz a identidade (vão de arredondamento), o motor pergunta em vez de aproximar
(`RTC_TAX_INCLUSIVE_PRICE_ROUNDING_GAP`). O teste `TaxInclusivePriceB32Test` varre de R$ 100,00 a R$ 110,00,
centavo a centavo. Combinações com não onerosas e importação ficam recusadas.