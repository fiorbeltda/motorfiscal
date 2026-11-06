# B36: regime específico das agências de turismo (06/11/2026)

Item 4B.4.10 (regimes específicos). Escopo: intermediação de serviços turísticos emitida por NFS-e.

## Fonte

LC 214/2025, arts. 288–291, no texto compilado do Planalto observado em 03/11/2026 (SHA256 `0a0d190b…4673c`). O teste
confere, no arquivo com hash, que cada trecho usado aparece uma única vez.

| Regra | Artigo | Como o motor trata |
|---|---|---|
| Base = valor da operação menos os valores repassados aos fornecedores intermediados, com base no documento que subsidia o agenciamento | 289, I | Cada repasse exige `supplier_id`, `supporting_document_id`, `evidence_id` e valor exato |
| Valor da operação = total cobrado do usuário, com bens e serviços usufruídos, margem e acréscimos | 289, §1º | `total_charged_to_user` tem de ser igual a `rtc.gross_amount` |
| Comissões e incentivos pagos por terceiros integram a base | 289, §2º | Linhas `third_party_commissions` (PLUS) |
| Mesma alíquota da hotelaria (redução de 40%) | 289, II c/c 281 | `rate_reduction_percent = 40.00`, cClassTrib oficial **200051** (CST 200, indNFSe = 1, redução de 40/40 na tabela oficial) |
| O adquirente pode tomar crédito da intermediação | 290 | `acquirer_credit = ALLOWED_ART290_ARTS_47_56` |
| A agência toma crédito nas suas aquisições, exceto sobre os valores deduzidos | 291 | Cada repasse sai com `credit_by_agency = PROHIBITED_ART291` |

## Implementação

- `TravelAgencyRegimeB36.php` tem duas funções:
  - `resolve()`: qualificação e redução de alíquota, na mesma cadeia do B35;
  - `settle()`: base própria do art. 289. Recusa base negativa, linhas sem prova, identificadores duplicados e valores
    monetários inexatos.
- No motor, a base da agência não se combina com outros ajustes de base, nem com a redução legal de base, o mercado
  externo ou um câmbio. Qualquer combinação desse tipo é recusada.
- O bundle `2026.11.06-b36-draft1` (`scripts/fork_phase4b_b36_travel_agency_bundle.py`) traz:
  - a política do regime;
  - uma regra de classificação, com `SANDBOX_REVIEW_REQUIRED`:
    - cTribNac 090201 (agenciamento e intermediação);
    - NBS de reservas 1.1805.1, 1.1805.2 e 1.1805.3;
    - destino no domicílio do adquirente (art. 11, X, a; cIndOp 100301);
  - `rtc.specific_regime` ampliado com `TRAVEL_AGENCY`;
  - três fatos novos.

## Limites e pontos para revisão

1. **Operadoras, guias e promoção turística:** operadoras de turismo (NBS 1.1805.40), guias (1.1805.50) e promoção
   turística (1.1805.6) ficam fora. O art. 289 trata da intermediação; operar um pacote próprio não é intermediação.
   É preciso revisar antes de incluir.
2. **Fornecimento próprio da agência:** quando a agência vende serviço próprio, que não é intermediação, o motor recusa
   com `RTC_TRAVEL_AGENCY_ONLY_INTERMEDIATION_MATERIALIZED`.
3. **Atendimento presencial no balcão:** a regra cobre a entrega remota ou digital. A venda presencial no balcão fica
   para revisão, porque o destino da intermediação segue a regra geral do art. 11, X.
4. **Alíquotas de 2026:** são as alíquotas de teste, reduzidas. As alíquotas de 2027 em diante dependem de atos oficiais.

## Testes

`tests/Fiscal/Integration/TravelAgencyRegimeB36Test.php`:
- fonte literal;
- cálculo da base: 1.000,00 + 50,00 − 700,00 = 350,00, que resulta em CBS 1,89 e IBS 0,21;
- seis recusas de apuração;
- só a intermediação é materializada;
- combinação com outros ajustes de base é recusada;
- operadora fora da regra;
- fork mínimo.
