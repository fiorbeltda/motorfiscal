# Especificação de integração B02 → Agente A (03/11/2026)

Decisão do usuário (03/11/2026): **o Agente A é o único escritor de
`app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php`**. B especifica e depois valida; não edita esse arquivo.
O modo B02 só será habilitado após implementação e testes satisfatórios.

## 1. O que já existe no lado B (clone `E:\dev\motorfiscal-agente-b`, base `87d22e7`, local, sem commit)

| Arquivo | Papel | SHA-256 |
|---|---|---|
| `app/Modules/Fiscal/ConsumptionReform/CreditDecisionEngine.php` | `resolve(..., bool $explainZero = false)`; padrão byte-idêntico ao HEAD | `393add0e…3ff5` |
| `app/Modules/Fiscal/ConsumptionReform/CreditZeroSemanticsPolicyB02.php` | gate `required($codes)`: só `true` com a chave `credit_zero_semantics_b02` exata | ver recibo |
| `tests/Fiscal/Unit/CreditZeroSemanticsB02Test.php` | 5.832 combinações iguais ao HEAD; 11 causas | `63de1e36…f5a` |
| `tests/Fiscal/Unit/CreditZeroSemanticsPolicyB02Test.php` | gate desligado em todos os catálogos `rtc-codes-*` | ver recibo |

A deve receber esses arquivos por pacote/patch intermediado pelo usuário (não pelo GitHub antes da autorização).

## 2. Contrato

- Entrada: inalterada, mais o parâmetro nomeado opcional `explainZero` (bool, padrão `false`).
- Saída com `explainZero=false`: idêntica à histórica.
- Saída com `explainZero=true`: cada componente `ibs`/`cbs` de um resultado `RESOLVED` ganha exatamente
  `zero_semantics_version` (`"B02"`), `zero_causes` (lista) e `amount_semantics`. Status, valores e demais chaves
  não mudam. Resultados `ACTION_REQUIRED` não mudam.
- Efeito: a saída de crédito entra em `decision.ibs.credit`/`decision.cbs.credit` e, portanto, no hash da decisão.
  Por isso a ativação só pode ocorrer em uma **nova versão de catálogo**, nunca em catálogos já aceitos.

## 3. Alteração pedida ao A em `ConsumptionReformEngine.php` (patch sugerido)

```diff
@@ (perto da linha 154, junto aos demais gates)
         $creditInput122 = CreditInputPolicy122::required($this->codes);
+        $creditZeroB02 = CreditZeroSemanticsPolicyB02::required($this->codes);
@@ (linha 1085 no HEAD)
-                $importCredit = (new CreditDecisionEngine)->resolve($facts, 'IMPORT', true, $inputCreditTreatment);
+                $importCredit = (new CreditDecisionEngine)->resolve($facts, 'IMPORT', true, $inputCreditTreatment, explainZero: $creditZeroB02);
@@ (linha 1161 no HEAD)
-        $credit = (new CreditDecisionEngine)->resolve($facts, $market, treatmentDecision: $inputCreditTreatment);
+        $credit = (new CreditDecisionEngine)->resolve($facts, $market, treatmentDecision: $inputCreditTreatment, explainZero: $creditZeroB02);
```

Se `$creditZeroB02` precisar estar no escopo da linha 1085/1161, A decide onde declarar, preservando o estilo do arquivo.
Nenhuma outra linha precisa mudar.

## 4. Testes que A deve executar após o patch (com `E:\tools\php85.cmd` ou contêiner)

1. `tests/Fiscal/Unit/CreditZeroSemanticsB02Test.php` e `tests/Fiscal/Unit/CreditZeroSemanticsPolicyB02Test.php`.
2. Suítes de crédito integradas: `ConsumptionReformEngineTest`, `InputCreditTreatmentTest`, `ImportCreditQualificationTest`,
   `CreditInput122Test`, `CreditInputPolicy122Test`, `CreditInput122IntegrationTest`, `InputCreditIntegratedTest`,
   `Credit122DocumentPricesTest`, `MaterialInput119Test`, `AcquiredPersonalUseTest`. Resultado de referência no lado B
   (sem o patch): 714 testes / 64.514 asserções aprovados. **Com o patch e os catálogos atuais, o resultado deve ser idêntico**,
   porque o gate fica desligado.
3. Suítes de A que tocam o fluxo integrado (importação 129–143, 4A/ISS), comparando antes/depois.

## 5. Habilitação (após o patch do A estar validado)

1. B prepara uma nova versão de catálogo/bundle com o bloco `credit_zero_semantics_b02` igual a `CreditZeroSemanticsPolicyB02::POLICY`.
   Os catálogos e bundles anteriores não são alterados.
2. B valida: decisões integradas com a nova versão mostram as anotações; decisões e replays com versões antigas continuam iguais.
3. Só então o modo é declarado habilitado no console/matriz.

## 6. Riscos

- Se A editar outras linhas de crédito no mesmo ciclo, informe a B para revalidar o espaço combinatório.
- Não ativar o modo em catálogo já aceito (quebraria replays e hashes históricos).
