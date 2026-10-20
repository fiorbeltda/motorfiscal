# Exportação 77 — diagnóstico causal antes da implementação

## Evidência observada em 15/10/2026

Inspeção sem alteração de runtime, enquanto a regressão 76 permanece ativa.
Relatório exclusivo em
`D:/ProjetoSaasExports/phase4b-export77-causal-inspection-20261015/export-causal-gap-validation.json`;
script em `D:/ProjetoSaasExports/inspect_export77_document_gap.py`.

O relatório conferiu o hash do snapshot anterior `full-export-snapshot.json`,
seu replay exato preservado e a recusa real do projetor. Não é uma execução
nativa nova do bundle 76. O snapshot pertence ao bundle 74 e tem competência
25/09/2026. Sua observação não prova cobertura de todas as exportações.

Nesse caso concreto:

- RTC: IBS/CBS `IMMUNE_EXPORT`, crédito `PRESERVED_SUBJECT_TO_PROOF`.
- ISS: `TAXABLE`, valor 20,00; não houve qualificação independente de exportação ISS.
- Projeção RTC: status `VALID`, mas `issuer_supplied` vazio.
- Comando XML: `ACTION_REQUIRED / RTC_DPS_DECISION_FIELDS_REQUIRED`, sem XML;
  faltam propósito, operação, destino, CST e cClassTrib.

## Causa no código atual

Quatro trechos completos, linhas e hashes estão no relatório:

1. `ConsumptionReformEngine.php`, ramo terminal de exportação, linhas 498–502:
   devolve imunidade RTC sem chave `decision.document`. O retorno ocorre antes
   da seleção e validação documental da rota ordinária. Não há autorização
   para copiar os códigos tributáveis dessa rota para a exportação.
2. `DpsProjection.php`, linhas 69–81: documento ausente produz array vazio
   de campos do emitente. O status `VALID` do RTC, isoladamente, não significa
   projeção documental materializada.
3. `DpsXmlProjector.php`, linhas 58–73: a ausência dos cinco campos impede o XML.
   Mesmo após preenchê-los, permanecem as guardas de identidade, papel e destino.
4. `DefaultFiscalDecisionEngine.php`, linhas 400–408: o caminho ordinário
   de incidência aceita somente `TAXABLE`. A rota institucional de imunidade
   existente é distinta; não deve ser reutilizada como exportação de ISS.

## Fonte e próximos limites

A [LC 116, art. 2º, I e parágrafo único](https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp116.htm)
exige análise própria do resultado do serviço; pagamento por residente no
exterior não basta quando o resultado se verifica no Brasil. Não transformar
o flag RTC de consumo estrangeiro em prova automática desse requisito de ISS.
O texto já preservado da Câmara está em
`resources/fiscal/sources/official/lc116-camara-2026-09-28.html`, SHA-256
`9f3ea2baf131b8f9712267d503a5a2153ec5c9616d8e8bdd002e4797c70b9a90`.
O escopo declarado desse item no manifesto é art. 8-B; esta leitura de pesquisa
do art. 2º não amplia automaticamente a política ativada no bundle.

A próxima implementação deve qualificar separadamente ISS, IBS/CBS e seus
documentos, com fonte/temporalidade/papéis e corpus próprios. Não alterar os
snapshots históricos, inventar códigos de exportação, transformar adquirente
estrangeiro em CPF/CNPJ ou usar alocação IBS/CBS como base documental de ISS.
Exportação integral e par de documentos de exportação parcial são contratos
distintos. A geração 76 de comprador estrangeiro com consumo brasileiro não
resolve essas duas rotas. Nenhuma aceitação fiscal ou de produção foi emitida.
