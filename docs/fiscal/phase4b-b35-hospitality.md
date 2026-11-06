# B35: regime específico de hotelaria, parques de diversão e parques temáticos (06/11/2026)

Item 4B.4.10 (regimes específicos). Escopo: o que é emitido por NFS-e.

## Fonte

LC 214/2025, arts. 277–283, no texto compilado do Planalto observado em 03/11/2026:
`resources/fiscal/sources/official/lc214-planalto-observed-20261103.htm`, SHA256 `0a0d190b…4673c`. O teste confere,
no arquivo com hash, que cada trecho usado aparece uma única vez.

| Regra | Artigo | Como o motor trata |
|---|---|---|
| Hotelaria = alojamento temporário em unidades exclusivas dos hóspedes ou em imóvel residencial mobiliado | 278 | `rtc.hotel_temporary_lodging` precisa ser verdadeiro e `rtc.hotel_unit_kind` precisa ser uma das duas hipóteses |
| Parque de diversão: atividade essencial é disponibilizar atrações fruídas presencialmente. Parque temático: tem tema histórico, cultural, etnográfico, lúdico ou ambiental | 279 | `rtc.park_presential_attractions_essential` e `rtc.theme_park_theme` |
| Base = valor da operação | 280 | Base geral do motor, sem ajuste próprio |
| Alimentação e bebidas seguem o regime de bares e restaurantes | 280, par. único (LC 227/2026) | O motor recusa (`RTC_HOSPITALITY_FOOD_FOLLOWS_RESTAURANT_REGIME`) quando o preço inclui alimentação ou bebidas: elas nunca entram nesta base. Bares e restaurantes saem por NFC-e, fora do escopo NFS-e |
| Alíquotas de IBS e CBS reduzidas em 40% | 281 | `rate_reduction_percent = 40.00`, cClassTrib oficial **200048** (CST 200, indNFSe = 1; a tabela oficial também traz 40%/40%) |
| Fornecedor mantém o crédito pelos arts. 47–56 | 282 | `supplier_credit = ARTS_47_56` |
| Crédito vedado ao adquirente | 283 | `acquirer_credit = PROHIBITED_ART283`, ligado à entrada `HOTEL_AMUSEMENT_PARK` do catálogo B13 |

## Implementação

- `HospitalityRegimeB35.php`:
  - fica atrás do gate `hospitality_regime_b35`, cuja política é fixa e cujo `published_at` é o instante de conhecimento
    do bundle;
  - o motor só o aciona com `rtc.tax_treatment = SPECIFIC_REGIME` e `rtc.specific_regime = HOSPITALITY`.
- `ConsumptionReformEngine.php`:
  - o regime entra na mesma cadeia de redução de alíquota que a redução profissional, a do anexo e a da cooperativa;
  - as regras de classificação podem exigir `specific_regime`.
- O bundle `2026.11.06-b35-draft1` (`scripts/fork_phase4b_b35_hospitality_bundle.py`) traz:
  - a política do regime;
  - cinco regras de classificação, todas com `SANDBOX_REVIEW_REQUIRED`:

    | cTribNac | Serviço | NBS |
    |---|---|---|
    | 090101–090104 | hospedagem | 1.0303 |
    | 120501 | parques | 1.2507 |

    Nas cinco, o destino é dado pela presença (art. 11, III; cIndOp 030101/030102);
  - os oito fatos novos e as perguntas correspondentes.

## Limites e pontos para revisão

1. **cIndOp da hospedagem:** usei o art. 11, III (serviço fruído presencialmente por pessoa física; cIndOp 030101/030102),
   como no padrão já usado para educação presencial. Revisão jurídica: confirmar que não se aplica o inciso II
   (serviço sobre bem imóvel).
2. **NBS 1.0304 fica de fora:** são estudantes e trabalhadores em hospedagem não turística, e o motor recusa o código. O
   alcance de "alojamento temporário" nesses casos precisa de revisão antes de incluir.
3. **ISS no fluxo integrado:** para a hospedagem, o fluxo integrado para em `MUNICIPAL_PARAMETERS_NOT_COVERED`, porque
   não há parâmetro municipal de ISS para o 090101 no catálogo Sandbox. Nada foi inventado. O resultado de IBS/CBS sai
   pelo motor RTC.
4. **Alíquotas de 2026:** são as alíquotas de teste (CBS 0,9% e IBS 0,1%), reduzidas para 0,54% e 0,06%. As alíquotas
   de 2027 em diante continuam dependendo dos atos oficiais.

## Testes

`tests/Fiscal/Integration/HospitalityRegimeB35Test.php` tem 15 testes:
- fonte literal;
- cálculo da hotelaria;
- parque de diversão e parque temático;
- recusa da alimentação;
- seis recusas de definição ou prova;
- b34 sem conhecimento do regime;
- política alterada;
- fork mínimo;
- parada do ISS no fluxo integrado.
