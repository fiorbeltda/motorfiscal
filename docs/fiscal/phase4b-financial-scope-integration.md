# Fornecedores financeiros e exceção geral184 integrada

Candidato `CONSUMPTION-PHASE4B-2026.10.02-11`, predecessor10 preservado. Ativa `FinancialRegimeQualifier`, `FinancialOperationEngine` e `FinancialBaseEngine` pelo catálogo versionado. Provas singleton `rtc.financial_scope_proof` e, quando aplicável, `rtc.financial_ledger`.

`rtc.financial_provider=true` solicita a qualificação em serviço geral; operação `FINANCIAL` exige qualificação. Prova ou livro fornecido também ativa a conferência mesmo sem indicador. Indicador explicitamente falso com evidência financeira é conflito, impedindo que o motor ignore fatos materiais. Registro da instituição não qualifica automaticamente a operação nem define a classificação documental.

## Resultado integrado materializado

Três cenários comprovam software não financeiro prestado por banco, caixa econômica ou instituição de pagamento, art184 §3. Permanecem nas regras gerais: base1000, IBS nominal1, CBS nominal9, ISS20 pela taxa municipal fixture2%, retenções4A61,50. Os cenários executam efetivamente a4A; não alteram automaticamente sua qualificação por causa do regime do fornecedor.

Provas desconhecidas, registro ausente, partes/documento/datas conflitantes e classificação de fornecedor `FINTECH` sem categoria legal são bloqueados. Declarar a operação como `FINANCIAL` e simultaneamente comprovar exceção geral exige corrigir a natureza da operação, sem conversão silenciosa.

Para operação do regime específico, o motor compõe a qualificação com o livro192/193 vinculado ao fornecedor/documento/família. O teste de ativação RTC calcula receita1000, exclui principal10000, deduz captação200 e conserva base800 na trace. Retorna `ACTION_REQUIRED:RTC_FINANCIAL_RATE_CREDIT_AND_DOCUMENT_PENDING` e decisão final vazia. Isso comprova ativação do cálculo, sem contar empréstimo como cenário integrado positivo. Não presume ISS tributável sobre juros de empréstimo, código DPS ou DeRE.

## Fontes e evidência

LC214 consolidada preservada, arts182–184,185–188,192–193; SHA256 `dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`. Não foram acrescentadas fontes, alíquotas, atos municipais ou aprovação formal. As98 regras interpretativas permanecem candidatas; os20 arquivos oficiais continuam íntegros.

Corpus13/13 (3 positivos gerais,10 negativos), teste integrado16/53 incluindo indicador conflitante e base RTC com decisão bloqueada. Testes76/169 das três camadas são medição do predecessor de implementação, sem alteração dessas classes neste corte. PHPStan sem erros; Pint e `git diff --check` sem erros. Regressão10 novamente21/21, seus quatro hashes inalterados.

Três snapshots com replay idêntico:

| Caso | Hash da decisão |
|---|---|
| Banco/software | `ea0f105c95ed97b8827df86ac666aa77f19264e80c175cb0c789acdc4a1944c6` |
| Caixa/software | `92bbbda18ce97c3343364da0c8f742849f45eb7ab446174e226f2137d006282b` |
| Instituição de pagamento/software | `6a43c748444c890166d8befcff032273147b18e78f34eba20ba6b9687fa57241` |

Três candidatos XML com apenas conflito oficial de série. Contrato DPS preservado `DPS-LOCAL-2026.10.02-1`; não são XMLs homologados. Artefatos `storage/app/phase4b-financial-scope-*-20261002-11-*`; corpus `phase4b-financial-scope-corpus-20261002-11.json`; gate `phase4b-production-assessment-20261002-11.json`: elegível local,20/20 fontes, `ready=false`,119 bloqueios,12 controles parciais/abertos. Materializador `fork_phase4b_financial_scope_bundle.py`; verificador `verify_phase4b_financial_scope_cut.py`.

## Escopo ainda obrigatório

Alíquotas/creditamento próprios, bases dos demais serviços182, perdas transportáveis193 §3, qualificação de ISS financeiro por serviço real, documentos DeRE/municipais/outros por perfil e corpus positivo completo. As exceções184 caput/§2 estão qualificadas nas camadas, mas ainda não têm classificação/documento próprios materializados em positivos integrados. A família financeira e a Fase4B continuam incompletas. Nenhuma emissão, assinatura, transmissão ou recolhimento efetivo.
