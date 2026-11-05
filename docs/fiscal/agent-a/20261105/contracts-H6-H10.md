# Contratos para nova ativação por B (A156)

Não ativar no B23 nem alterar catálogos aceitos. Este pacote modifica somente código/testes
próprios e captura candidatos em memória. B é o escritor dos novos fatos, perguntas,
classificações, catálogos, bundle, matriz80 e console.

## H6/H7: `credit_evidence_a156`

Copiar exatamente `CreditEvidence156::POLICY` para nova versão do catálogo de códigos.
Fatos novos ARRAY: `rtc.credit_book_entries` e `rtc.import_definitive_credit_records`.
Ambos são listas; cada elemento é `{record, record_hash}`. Hash: CanonicalJson SHA256 do record.
Todo record tem `source_kind` (`DOCUMENTARY_INPUT` ou `SYNTHETIC_TEST`), `evidence_id`,
`source_hash` SHA256 do documento externo e `observed_at` RFC3339 até knowledge_at.
Referências/hashes fornecidos são declarações qualificadas de entrada; não autenticam a fonte.

Livro: `book_id`, `entry_id`, `document_id`, `taxpayer_id`, `written=true`, `document_on`,
`booked_on`, `ibs_amount`, `cbs_amount`. Valores BRL como strings canônicas com duas casas.
Cada crédito INPUT e cada entrada do credit_pool art51/52 deve ter documento único no livro,
mesmo titular e valores. Recusa ausência, duplicidade e divergência; não gera lançamento.
O fluxo de manutenção/anulação continua no componente existente, com o mesmo cálculo.
Não confundir crédito do adquirente (art49/50) com créditos anteriores do fornecedor (art51/52).

Importação: um record `contract=IMPORT-CREDIT-A156-V1`, `document_id` igual a input_document_id
e import_credit_fiscal_document_id; `foreign_invoice_id` igual a import_document_id;
`acquirer_id`, `importer_id`, adquirente BR, importador igual ao adquirente;
`definitive=true`, `document_valid=true`, `document_on`, `credit_on`, `extinction_evidence_id`.
Documento também deve estar no livro, com mesma data e escrituração até credit_on.
`payments`: dois registros, um IBS e um CBS, com `tax`, `document_id`, `payer_id`,
`extinguished=true`, `payment_id`, `evidence_id`, `paid_on`, `amount` igual ao componente pedido.
Exige document_on ≤ paid_on ≤ credit_on ≤ data de conhecimento. Os fatos legados de validade
e extinção devem concordar. Não há projeção de uma nova prova sobre fatos contraditórios.

A verificação entra antes de `CreditDecisionEngine::resolve` e os fatos completos chegam a ele
no mesmo FactBag, ramo IMPORT. A trace CREDIT_DOCUMENT_BOOK156/IMPORT_CREDIT conserva o documento,
datas de crédito/pagamento e os componentes separados. A obrigação de emitir/validar documento
de importação continua bloqueada pelo contrato documental histórico: esta entrega não declara
resolvidos os impedimentos oficiais de XSD/regras de emissão já documentados no A143/B23.

Criar perguntas para as razões RTC_CREDIT_BOOK_* e RTC_IMPORT_DEFINITIVE_RECORD_* / PAYMENT_LINK_CONFLICT,
conservando os missing_facts emitidos. Gate malformado é INVALID. Gate ausente preserva a saída histórica.
Regimes financeiros e outros créditos especiais precisam de contratos próprios de livro; não presumir
que este contrato ordinário substitui suas provas específicas.

## H8/H9: evidência sintética explícita

`TemporalRateCatalog::EVIDENCE_KEY` e `EVIDENCE_POLICY` devem coincidir no catálogo de taxas
e de códigos de um candidato de teste. Toda taxa usada nesse modo deve ter
`source_kind=SYNTHETIC_TEST`, `synthetic=true`; taxas oficiais não são selecionadas nesse modo.
Sem os dois gates exatos, o motor recusa. Sem o gate de taxas, sintéticos continuam NOT_FOUND.
Não publicar essas taxas como fonte oficial ou substituir as taxas do B23.

O motor sempre retorna ACTION_REQUIRED/RTC_SYNTHETIC_TEST_ONLY, `official=false`, decisão vazia.
Essa proteção também é aplicada automaticamente aos novos records documentais/livros/redução
marcados SYNTHETIC_TEST, quando seu gate próprio está ativo, inclusive com taxas oficiais.
Memória nominal fica somente na trace SYNTHETIC_ARITHMETIC156, em candidate_decision ou nos
estágios de cálculo dos ramos recusados. O runner integrado também bloqueia a projeção final.
Não apresentar candidate_status=VALID como uma decisão válida para produção.

Os cenários 2027/2030/2033 estendem somente a validade de um candidato em memória para isolar
aritmética; não afirmam vigência futura de tabelas/classificações/layouts. Metadata de extinção
não calcula PIS/Cofins monetários. Tributos legados, retenções e crédito IBS/CBS são grandezas
distintas. Preservar os bloqueios temporais do runner integrado e os casos históricos.

## H10: redução legal de base

Nova política `LegalBaseReduction156::KEY/POLICY`; fato ARRAY `rtc.legal_base_reduction_records`.
Hipótese delimitada LC214 art12§8: transporte internacional de passageiros, ida/volta vendidas
conjuntamente, BRL, operação onerosa OUTPUT STANDARD, sem outros ajustes ou transformações.
Não é desconto nem redução de alíquota. Base = metade do valor cobrado; HALF_UP em centavos.

Record com envelope acima, `contract=LEGAL-BASE-ART12-PAR8-A156`, `rule=LC214_ART12_PAR8`,
`service_family=INTERNATIONAL_PASSENGER_TRANSPORT`, `service_id`, `scope_confirmed=true`,
`sold_together=true`, `joint_sale_id`, `sold_on`, `currency=BRL`, `charged_amount`,
`document_id`, `supplier_id`, `acquirer_id`. Dois `legs`: id único, from_country/to_country
ISO2 distintos e invertidos entre ida/volta (perfil deste pacote com BR), departure_on ordenado,
joint_sale_id igual. Referências devem coincidir com os fatos da operação.

A regra classificatória selecionada precisa declarar
`legal_base_reduction_family=INTERNATIONAL_PASSENGER_TRANSPORT`.
Nessa família ativada, ausência do record exige informação; não há fallback silencioso à base cheia.
B deve criar classificação material própria na nova versão; uma declaração de passageiro não transforma uma regra de SaaS.
O teste positivo usa novo ID explicitamente SYNTHETIC em memória, sem inventar NBS ou cClassTrib oficial.
Combinações com regimes especiais, importação, antecipações e imunidade permanecem recusadas.
Para essas combinações, especificar regra/escopo legal e prova antes de ampliar; não inferir benefício.
