# A157 — reconciliação do recolhimento pelo adquirente

Base f501885153d64f77ea8a7571fa680d5c9f43e970, codex/fiscal-engine-bootstrap.
Entrega local: API, CLI e integração com gate próprio para4B.3.8, com efeitos em
4B.3.2/4B.3.10 e memória de4B.4.14. Trabalho regular retomado; nenhum commit/push nesta task.

O ciclo aplica recolhimentos somente ao débito remanescente daquela operação e tributo,
considerando extinções anteriores e baixas intermediárias comprovadas. Conserva recebimento,
aplicação, saldo, excedente e transferências ao fornecedor, por comprovante e por IBS/CBS.
Impede IDs/recibos repetidos, reuso do histórico, cruzamento de partes/documentos/transações,
moedas incompatíveis, eventos fora do corte de conhecimento, excesso de baixa e de devolução.
O replay recompõe o resultado e recusa adulteração mesmo com hash externo recalculado.

Validação terminal no código atual:

- Dirigida de14 arquivos:526 testes/1923 asserções, sem falhas/erros/skips.
- Os58 testes próprios/196 asserções são parte dos526; não somar novamente.
- Compatibilidade: A148, cálculo ordinário/material119, B23/H1–H5 e testes A156 executados
  na seleção registrada. Esta é regressão dirigida PARCIAL, não integral da versão.
- CLI real:19 casos positivos, cada um repetido com saída byte-idêntica;190 verificações
  independentes Python Decimal; uma recusa de histórico incompleto com exit1 esperado.
- Casos2026/2027/2030/2033 usam débitos e comprovantes explicitamente sintéticos, sem taxa futura inferida.
- PHPStan global nível8 sem erros; Pint6 arquivos aprovado. Console em leitura:28 checks,
  endpoint HTTP200. Monitoramento e matriz80 seguem sob escrita de B.

O gate ausente preserva a saída antiga. A ativação nova exige catálogo/fato/pergunta de B.
Provas sintéticas não viram decisão final oficial. Cash_due nominal e créditos não são
reescritos pelo relatório de recebimentos, que não autentica, lança ou movimenta valores.
Não se afirma que opção exercida em recibos parciais equivale a quitação integral.

Os quatro IDs continuam PARCIAIS quanto ao requisito inteiro: a lacuna de ciclo ordinário
foi reduzida, mas permanecem ativação/revisão integrada, calendários e perfis específicos.
A data do prazo de transferência de excedente depende de calendário oficial qualificado;
a regra legal é registrada, não calculada por calendário presumido. Split payment segue
lacuna própria de4B.3.7, e as demais pendências dos44 permanecem na revisão A156.

Contrato e interface para B: [handoff-A157-to-B.md](handoff-A157-to-B.md).
Recibo: [validation-A157.json](validation-A157.json). Proposta global, não aplicada:
[console-proposal-A157.json](console-proposal-A157.json).
Evidências: D:/ProjetoSaasExports/agent-a-A157-acquirer-collection-20261105-v1.
Fonte Câmara LC214 revalidada em05/11, mesmo SHA256 dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde.

Próximo desenvolvimento de A: ciclo de split payment e demais lacunas materiais da revisão44,
com nova task/manifesto. Nenhum grupo4B, fase ou produção é declarado concluído por A157.
