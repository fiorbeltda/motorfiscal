# A157 — contrato de reconciliação do recolhimento pelo adquirente

Base f501885; desenvolvimento local de A, sem commit/publicação automática.
IDs A:4B.3.8,4B.3.2,4B.3.10,4B.4.14. Não modifica componentes de crédito ou recursos de B.

## Nova versão necessária, sem alterar B23/anteriores

Adicionar `AcquirerCollection157::KEY` com o conteúdo exato de `POLICY` em catálogo novo.
Fato ARRAY `rtc.acquirer_collection_ledger`: lista com exatamente um objeto do contrato
`RTC-ACQUIRER-COLLECTION-A157-V1`. Criar pergunta para esse fato nas recusas do novo fluxo;
erros de referência legal continuam usando `rtc.collection_legal_basis`.
Gates ausentes preservam os contratos antigos; política presente e malformada é INVALID.

Contrato API/CLI: `AcquirerCollection157::reconcile(array)` e
`fiscal:rtc:reconcile-acquirer-collection <ledger.json>`. Saída positiva é
`LOCAL_ACQUIRER_COLLECTION_RECONCILED`, com replay recomputado e determinístico.

## Dados materiais

- contract, source_kind (`DOCUMENTARY_INPUT` ou `SYNTHETIC_TEST`), evidence_id, source_hash SHA256;
- document_id, supplier_id, acquirer_id, payment_transaction_id, acquirer_regime=REGULAR;
- currency=BRL, instrument_split_capable=false, instrument_evidence_id;
- operation_date, operation_at RFC3339, history_as_of RFC3339, known_at RFC3339;
- history_complete, payments_complete, transfers_complete, other_extinctions_complete=true;
- history_excludes_acquirer_collections=true; history_evidence_id e prior_receipt_ids únicos;
- debits e previously_extinguished: exatamente IBS/CBS, strings BRL canônicas com duas casas;
- payments, other_extinctions, transfers: listas completas, inclusive vazias quando comprovadamente inexistentes;
- input_hash=CanonicalJson SHA256 de todo o objeto sem input_hash.

O corte de abertura contém extinções de outras modalidades e antecede o primeiro recolhimento
do adquirente. Todos os recolhimentos do adquirente da operação devem estar em payments,
inclusive os antigos: não esconder pagamentos/excedentes no saldo de abertura.
Não apresentar planos B14 de utilização de crédito como comprovantes de compensação aplicada.

Cada payment tem id, tax, amount, settled_at, settled=true, payer_id=acquirer_id,
authority_receipt_id único e evidence_id. Cada other_extinction tem id, tax, amount,
applied_at, applied=true, authority_receipt_id único, evidence_id e modality entre
COMPENSATION/SUPPLIER_PAYMENT/SPLIT_PAYMENT/RESPONSIBLE_PAYMENT. Essas baixas são consumidas
como observações qualificadas de entrada; A não calcula nem apropria crédito de B.

Todos esses registros e transfers repetem document_id, supplier_id, acquirer_id,
payment_transaction_id e currency do objeto. Instantes posteriores ao corte e até known_at.
Eventos de um mesmo tributo no mesmo instante, sem ordem material distinta, são recusados;
IBS/CBS simultâneos são independentes e recebem ordenação de saída estável.

Transfer: id, tax, amount, collection_payment_id, beneficiary_id=supplier_id, settled=true,
transferred_at, authority_transfer_receipt_id único e evidence_id. Só consome o excedente
daquele recolhimento/tributo, após ele e até known_at. Não compensa um tributo com outro.

## Efeito no motor e limites

Na rota ordinária ACQUIRER, A confere as partes, transação, instrumento, data e débitos
contra a decisão calculada, além do instante de conhecimento. Adiciona trace
ACQUIRER_COLLECTION157 e `decision.collection.reconciliation157`. Valores nominais,
créditos e campos legados cash_due permanecem como cálculo anterior à reconciliação;
os saldos após comprovantes ficam no novo objeto, sem desconto duplicado.
O status legado de elegibilidade não autentica comprovantes; consultar o status do novo objeto.

IBS/CBS têm debit/prior/other_applied/received/applied/remaining/excess/transferred e
pending_transfer_to_supplier. Receipts é uma lista, com collection_payment_id explícito
preservado como string, incluindo zeros iniciais. O exercício da opção informado depende
de recolhimento positivo, nunca só da escolha ACQUIRER. Isso não significa quitação integral.

Excedente é destinado ao fornecedor/contribuinte conforme art36§3II. A regra de três dias
úteis é exposta, mas a data/timeliness exige calendário oficial qualificado; não foi inventada.
Nenhuma transferência, autenticação, apropriação de crédito ou baixa é executada pelo agente.
Os reports são locais, official=false, posted=false e payment_performed=false.
Dados SYNTHETIC_TEST também impedem decisão final no motor integrado, como no A156.

Escopo atual: reconciliação BRL da rota ordinária, com histórico e eventos qualificados.
Não fecha os regimes especiais/importações, o calendário de transferência nem o split payment
padrão/simplificado.4B.3.7 continua trabalho de A; não foi repassado a B.

Fonte primária: [LC214 consolidada, Câmara](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html),
arts27 parágrafo únicoII e36. Snapshot05/11/2026, SHA256
dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde.
Recebimento/ativação/consolidação por B não foram presumidos.
