# B13 — Vedações legais de crédito do adquirente (Agente B, 03/11/2026)

Prioridade 2 (demais perfis de crédito e regimes). Base `87d22e7` + B01–B12 locais, sem commit.

## Requisito

4B.5.7 (operações sem crédito: exclusões legais com fonte e testes). Continua **parcial**:
- o catálogo cobre as vedações por regime da LC 214, mas não foi ligado ao fluxo integrado (`ConsumptionReformEngine.php`, escritor A);
- os benefícios que anulam crédito (art. 51, imunidade/isenção) pertencem a 4B.5.8.

## Fonte e proveniência

Texto compilado do Planalto, `resources/fiscal/sources/official/lc214-planalto-observed-20261103.htm` (`0a0d190b…673c`).
Cada entrada guarda o **trecho literal vigente**. O teste confirma, de forma independente:
- que o trecho aparece uma única vez no texto oficial;
- que não há redação posterior que o substitua (LC 227/2026).

A checagem endureceu durante o corte. A primeira versão da exceção do art. 238 remetia ao "§ 2º do art. 57" e foi
substituída pela LC 227 ("§ 3º do art. 57"); o controle negativo confirmou que a checagem marca as redações antigas.

| Regime | Base | Escopo |
|---|---|---|
| Uso ou consumo pessoal | art. 57, §5º | adquirente |
| Combustível monofásico para distribuição, comercialização ou revenda | art. 180 | adquirente |
| Serviços financeiros não permitidos nos arts. 194–198 | art. 199 | adquirente |
| Serviços prestados ao investidor (fora do fundo) | art. 211 | adquirente |
| Previdência complementar | art. 226 | adquirente |
| Planos de assistência à saúde | art. 238 (exceção: art. 57, §3º, IV, "f") | adquirente |
| Planos de saúde de animais domésticos | art. 243 | adquirente |
| Apostadores de concursos de prognósticos | art. 247 | adquirente |
| Alimentação e bebidas de bares e restaurantes | art. 276 | adquirente |
| Hotelaria e parques | art. 283 | adquirente |
| Transporte coletivo urbano/metropolitano | art. 285, III (adquirente) e II (insumos do fornecedor) | conforme o papel |
| Bens e serviços da SAF (exceto direitos desportivos) | art. 293, §6º | adquirente |
| Valores deduzidos da base em regimes específicos | art. 303 | adquirente |

## Comportamento (`AcquirerCreditProhibitionCatalogB13::qualify`)

- `CREDIT_PROHIBITED`: vedação aplicável, com base legal e hash do trecho.
- `CREDIT_LIMITED_TO_SUPPLIER_EXTINGUISHED_DEBITS`: plano de saúde coletivo de empregados (acordo ou convenção), adquirente no
  regime regular e evidência.
- `NOT_PROHIBITED_BY_STATUTORY_CATALOGUE`: sem vedação no catálogo. **Não concede crédito**; as condições dos arts. 47–56 continuam.
- `ACTION_REQUIRED`: falta destino do combustível, evidência de exceção ou artigo de permissão financeira válido.
- `INVALID`: regime fora do catálogo.

Todo resultado leva `credit_granted=false` e `other_conditions_still_apply=true`.

## Validação

`tests/Fiscal/Unit/AcquirerCreditProhibitionCatalogB13Test.php`: 31 testes / 136 asserções.
- Proveniência dos 16 trechos.
- As 14 vedações.
- 15 casos de exceção e recusa.
- Determinismo.

PHPStan e Pint aprovados. Teste dirigido; não é regressão integral.

## Limites

- O art. 180 traz parágrafos com hipóteses de crédito para combustível que não é revendido. Aqui só se marca "não vedado
  pelo art. 180"; os parágrafos não foram modelados.
- Os arts. 485–487 (regimes imobiliários opcionais) são vedações do próprio contribuinte optante; ficam para um corte de regimes.
- A ligação ao fluxo integrado depende de especificação para o A.
