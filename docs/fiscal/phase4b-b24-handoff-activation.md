# B24: ativação dos handoffs H6–H10 do A156 (05/11/2026)

Base: `f501885` (A156, H6–H10), sobre o B23 `1fa9969`. Bundle novo:
`resources/fiscal/bundles/consumption-phase4b-2026.11.05-b24-draft1.json`, gerado por
`scripts/fork_phase4b_b24_handoff_activation_bundle.py` (idempotente; o B23 e os anteriores ficam intactos).

## Decisões do usuário nesta rodada

- O B passa a cobrir também o trabalho do A ("faz tudo sozinho, sem precisar do A").
- Escopo do produto: **tudo o que é emitido por NFS-e**. O que exige BP-e, CT-e ou NF-e fica marcado
  como fora do escopo do produto, com justificativa.

## Conferência do commit A156

- 7 arquivos de teste novos do 156: 125 testes / 627 asserções aprovados no clone B.
- 22 arquivos B07–B23 + 155 + motor/CreditDecision: 1.404 testes / 223.122 asserções aprovados, sem regressão
  pelo patch em `ConsumptionReformEngine.php`.

## O que o B24 ativa

| Handoff | Decisão B24 | Motivo |
|---|---|---|
| H6/H7 `credit_evidence_a156` | **Ativado** | Política copiada de `CreditEvidence156::POLICY` via PHP. Fatos ARRAY novos: `rtc.credit_book_entries` e `rtc.import_definitive_credit_records`, com perguntas. |
| H8/H9 `synthetic_evidence_a156` | Não ativado | Modo exclusivamente aritmético/de teste; nunca faz parte de um bundle Sandbox. |
| H10 `legal_base_reduction_a156` | Não ativado: fora do escopo | A classificação oficial da hipótese (art. 12 §8) é a cClassTrib **222001** (CST 222), cuja linha oficial no acervo (`nfe-cclasstrib-it2025-002-v170`) tem `indNFSe=0` e vale só para BP-e/BP-e TA/CT-e OS. O cIndOp oficial seria o 060101. O motor emite NFS-e e recusa código sem suporte NFS-e. O mecanismo de cálculo do A fica disponível para um futuro módulo BP-e. |

Efeito material: no B24, todo crédito INPUT e todo crédito do pool art. 51/52 exige lançamento de livro com
documento, titular e valores iguais. Sem ele, o resultado é `ACTION_REQUIRED/RTC_CREDIT_BOOK_ENTRIES_REQUIRED`.
Na importação de serviço, são exigidos também o documento definitivo e os pagamentos IBS/CBS extintos.
Records `SYNTHETIC_TEST` nunca geram decisão oficial. As declarações de entrada não autenticam a fonte, e não há posting.

## Testes

`tests/Fiscal/Integration/HandoffActivationB24Test.php`: 13 testes / 208 asserções aprovados.
- O fork muda só o catálogo de códigos, os fatos, as perguntas e os controles, e acrescenta apenas a chave nova.
- Os dois fatos são ARRAY e materiais, cada um com pergunta; o 222001 está fora do NFS-e conforme a linha oficial.
- No B23 a decisão sai VALID sem livro; no B24 o livro é pedido (no runner RTC e no integrado, sem QUESTION_UNMAPPED).
- Com livro documental, o B24 chega à mesma decisão do B23 (só muda a versão do catálogo).
- São recusados: documento, titular ou valores divergentes; hash adulterado; record sintético.
- Importação: documento definitivo + pagamentos qualificados; sem o record, `RTC_IMPORT_DEFINITIVE_RECORD_REQUIRED`.

## Pesquisa externa (05/11/2026)

- **CBS 2027:** o TCU calculou 11,3468%, mas a resolução do Senado tem prazo até 15/12/2026. Enquanto não houver
  ato publicado, nenhuma alíquota futura entra como oficial.
- **Split payment:** a 1ª etapa (B2B facultativa, 2027) tem documentação (Ato Conjunto RFB/CGIBS nº 02/2026);
  a 2ª etapa depende de ato ainda não publicado.
- **Fixtures históricas:** os 41 arquivos excluídos dependem de corpora no D: da máquina do A; não são regeneráveis.

## Próximo passo

Consolidar a matriz80 e o console com os 7 itens B. Depois, atacar as lacunas dos 44 itens do A dentro do escopo
NFS-e, por bloco funcional. Nenhum item foi declarado integral por esta rodada.
