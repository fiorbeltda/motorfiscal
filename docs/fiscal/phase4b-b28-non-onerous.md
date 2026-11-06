# B28: espécies não onerosas restantes no escopo NFS-e (05/11/2026)

O Agente B, assumindo o trabalho do A, cobre o item 4B.2.2 (operações não onerosas previstas).

## Fonte oficial

- LC 214/2025, redação da LC 227/2026: art. 5º I a IV e §§1º–9º; art. 6º; art. 12 §§3º–4º.
- NT009, Anexo VI (`anexovi-leiautesrn_rtc_ibscbs-v1-04-00-2013-nt009.xlsx`), campo `indDoacao`: a doação com
  contraprestação vai em NFS-e emitida a valor de mercado, com tributação regular.
- Tabela cClassTrib do acervo: 410001 (art. 5º §1º, I), 410003 e 410026 (doações), 000001 (tributação integral).

## Cobertura por espécie

| Espécie | Situação |
|---|---|
| Art. 5º I a 1–4, I b 1–2 (uso pessoal: sócio, empregado, parentes) | Já existia, com cálculo pelo valor de mercado. **Novo:** documento candidato |
| Art. 5º II, brindes e bonificações | **Novo:** tributado pelo valor de mercado (art. 12 §4º I/II); exige a evidência da campanha |
| Art. 5º §1º I, bonificação no documento sem evento posterior | **Novo:** recusada como operação separada (410001: é desconto incondicional do documento principal) |
| Art. 5º IV, parte relacionada (§§2º–6º) | **Novo:** hipótese da relação + evidência; não oneroso ou abaixo do mercado; base = valor de mercado (art. 12 §4º IV); preço ≥ mercado é recusado como art. 5º IV |
| Art. 5º III, transmissão de bens a sócio | Fora do escopo NFS-e (bens/NF-e) |
| Art. 6º VIII, doações | Já existia (DonationQualifier) |

O valor de mercado precisa vir de operações comparáveis entre partes não relacionadas
(`rtc.market_value_method=COMPARABLE_UNRELATED_OPERATIONS`), com evidência. Empregados, sócios e parentes
são encaminhados para a rota específica do art. 5º I; não podem passar por II ou IV.

## Documento: candidato, não regra aprovada

Antes, todas as espécies tributadas paravam em `RTC_NON_ONEROUS_DOCUMENT_NOT_MATERIALIZED`. Com o gate,
o motor monta a NFS-e a valor de mercado com cClassTrib 000001 e sem `indDoacao`, e registra
`decision.non_onerous_b28` com `review_status=SANDBOX_REVIEW_REQUIRED`.

A NT009 só descreve esse padrão para a doação com contraprestação. Para as demais espécies é uma
interpretação por analogia, que precisa de revisão. Não é aprovação automática.

## Artefatos

- Componente: `app/Modules/Fiscal/ConsumptionReform/NonOnerousSpeciesB28.php`, integrado ao motor atrás do gate
  `non_onerous_species_b28`.
- Bundle `2026.11.05-b28-draft1` (`scripts/fork_phase4b_b28_non_onerous_bundle.py`), com 5 fatos e perguntas novos.
- Teste: `tests/Fiscal/Integration/NonOnerousSpeciesB28Test.php`, 11 testes.
