# B02 — Resultados zero de crédito com causa distinta (Agente B, 03/11/2026)

Base `87d22e7` + B01 local, clone exclusivo do Agente B, sem commit.

## Requisitos

- 4B.5.10 (nunca tratar todo resultado zero como equivalente) e 4B.5.3 (condicionantes).
- Nenhum requisito encerrado: a ativação no fluxo integrado depende de um chamador versionado.

## Defeito observado no HEAD

`CreditDecisionEngine::resolve` devolve exatamente a mesma saída
`CONDITIONAL` / `amount=0.00`, sem motivo, para causas distintas:
documento de entrada inválido e tributo anterior não extinto. Do mesmo modo, `DENIED` por uso
pessoal não traz motivo. Um consumidor que olhe só o valor zero não distingue negação final,
crédito contingente, valor de entrada zero, estorno integral e arredondamento.

## Entrega

- `app/Modules/Fiscal/ConsumptionReform/CreditDecisionEngine.php`: parâmetro opcional
  `explainZero` (padrão `false`). Com `true`, cada componente IBS/CBS `RESOLVED` recebe
  `zero_semantics_version=B02`, `zero_causes` e `amount_semantics`, sem alterar status, valor
  ou demais chaves:
  - `NOT_ASSESSED_ON_OUTPUT`: `OUTPUT_CONTEXT_NO_INPUT_CREDIT` / `NOT_ASSESSED_NO_AMOUNT`;
  - `DENIED`: motivo existente, ou `PERSONAL_USE_OR_CONSUMPTION` / `FINAL_DENIAL_ZERO`;
  - `CONDITIONAL`: `INPUT_DOCUMENT_NOT_VALID`, `PRIOR_TAX_NOT_EXTINGUISHED` e/ou
    `INPUT_TREATMENT_CONDITIONAL` / `CONTINGENT_NOT_YET_APPROPRIABLE`;
  - `ALLOWED` zero: `INPUT_TAX_AMOUNT_ZERO`, `FULLY_REVERSED_OR_DISALLOWED` ou
    `ROUNDED_TO_ZERO` / `ALLOWED_ZERO_AMOUNT`; positivo: `ALLOWED_AMOUNT`.
- Os rótulos descrevem condições já implementadas; nenhuma regra legal nova foi criada.

## Validação (PHP 8.5.12 NTS Windows)

- `tests/Fiscal/Unit/CreditZeroSemanticsB02Test.php`: 12 testes. A implementação do HEAD fica
  congelada em `tests/Fiscal/Support/b02/CreditDecisionEngine.base-87d22e7.php.txt`
  (SHA-256 `da1f00e2…725a`) e serve de oráculo:
  - 5.832 combinações de contexto, mercado, regime, documento, extinção, valores, ajustes,
    tratamento e país: saída padrão byte a byte igual ao HEAD;
  - `explainZero` só acrescenta as três chaves;
  - nenhum zero fica sem causa, nenhuma causa `_UNSPECIFIED` aparece no espaço, as 11 causas
    são observadas e as duas causas condicionais históricas passam a ser distintas.
- Junto com as suítes de crédito existentes (ConsumptionReformEngine, InputCreditTreatment,
  ImportCredit, CreditInput122, CreditInputPolicy122, CreditInput122Integration,
  InputCreditIntegrated, Credit122DocumentPrices, MaterialInput119, AcquiredPersonalUse):
  714 testes/64.514 asserções aprovados. Pint e PHPStan do arquivo alterado aprovados.
- Teste dirigido; não é regressão integral.

## Interface para ativação (pendente)

O único chamador é `ConsumptionReformEngine.php` (linhas 1085 e 1161 no HEAD), arquivo transversal.
A ativação exige um escritor único definido para esse arquivo e uma versão nova de catálogo/bundle,
porque a saída de crédito entra no hash da decisão e nos replays aceitos. Sem essa ativação, o
comportamento integrado continua o histórico.
