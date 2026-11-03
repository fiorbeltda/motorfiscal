# B06 — Análise individualizada das 83 regras sem correlação literal (Agente B, 03/11/2026)

Atende à orientação do usuário de 03/11/2026: analisar uma a uma as 26 regras divergentes e as 57 sem correlação
oficial do B04, com fontes oficiais, sem aprovar regras automaticamente e encaminhando só o que for controverso.
Base `87d22e7` + B01–B05 locais, sem commit. **Nenhuma regra foi alterada, aprovada ou ativada.**

## Fontes oficiais usadas (todas com hash)

| Fonte | Versão / data | Vínculo |
|---|---|---|
| Tabela cClassTrib (Portal NF-e, IT 2025.002) | v1.70, publicada em 01/10/2026 | catálogo 141 `sources.cclasstrib` (`bee3e106…c850`) |
| AnexoVII cIndOp (Portal NFS-e) | 1.03.00 (NT009) | `95f30a44…e5aa`, revalidada no B03 |
| NBS (Anexo B, serviços nacionais) | v1.01-20260122 | `e74b0be8…1c61` |
| LC 214/2025, arts. 11, 129, 130, 9º, 127, 271 e Anexos II/III | consolidada (Câmara, 01/10/2026) | `dc87a43e…2bde` |
| LC 214/2025, texto do Planalto | obtido em 2026-11-03T12:44:32Z | `resources/fiscal/sources/official/lc214-planalto-observed-20261103.htm`, `0a0d190b…673c` |
| Anexo VIII (correlação) | 1.01.00 e 1.02.00 | B01 |

**Revalidação de lei:** o texto do Planalto confirma o art. 129 (60% para educação, Anexo II) e o art. 130 (60% para
saúde, Anexo III), e os conjuntos de NBS dos Anexos II (7 códigos) e III (21 códigos) são **idênticos** aos do
catálogo. Isso inclui o item 30 do Anexo III (serviços funerários, NBS `1.2603.00.00`) e a grafia literal `1.2301.99.0` do item 29.

## Verificações aplicadas a cada regra (script `scripts/analyze_phase4b_b06_rule_documentation.py`)

- C1: cClassTrib existe na v1.70, é válido na vigência da regra e é permitido em NFS-e.
- C2: cada cIndOp existe na 1.03.00 e é permitido em NFS-e.
- C3: a base de destino da regra coincide com o texto oficial do cIndOp (art. 11, X, a, 1/2 ou art. 11, III).
- C4: os prefixos NBS existem na NBS oficial.
- C5: o fundamento da regra cita o artigo do cClassTrib (ou o art. 11, quando o cClassTrib não tem referência).
- C6: nas reduções 200028/200029, os prefixos estão no Anexo II/III da categoria correta.
- Correlação **por item LC116** (a comparação só por NBS do B04 apontava linhas de outros itens).

## Resultado

| Classificação | Regras | Tratamento técnico proposto |
|---|---:|---|
| `RESOLVIDA_DOCUMENTALMENTE` | 37 | Manter como `ENGINE_DECISION_RULE` Sandbox com fundamentos registrados; sem ativação oficial presumida |
| `RESOLVIDA_DOCUMENTALMENTE_CONDICIONADA_A_FATOS` | 6 | Manter; uso só com prova do fato subjetivo (imunidade art. 9º II/III ou cooperativa art. 271), já exigida pelos qualificadores |
| `CONTROVERSA_INTERPRETACAO_JURIDICA` | 40 | Manter pendente de validação jurídica, sem ativação automática |

- Nenhuma das 83 regras falhou em C1–C6, e nenhuma tem divergência inexplicada no próprio item LC116.
- As 26 "divergências" do B04 são variantes documentadas do mesmo item: domicílio do adquirente (art. 11, X)
  × presença física (art. 11, III); tributação integral × redução de anexo; imunidade ou cooperativa.
- As 57 "sem correlação" são itens LC116 para os quais o Anexo VIII não traz linha completa. **A ausência de
  correlação não invalida a regra**, e cada uma tem fundamento verificável pelas verificações acima.
- Uma falha inicial de C5 (`IMPORTED_STRATEGIC_CONSULTANCY141.STANDARD`) era do analisador, que não lia o campo
  `legal_basis` (art. 11, X, a, 1 e art. 64). Foi corrigida e registrada.

Artefato por regra: `storage/app/phase4b-b06-development-20261103/b06-rule-documentation-analysis.json`
(SHA-256 `71ef597b…704a`; gerador determinístico; teste `tests/Fiscal/Unit/RuleDocumentationAnalysisB06Test.php`).

## Única questão controversa (encaminhada para análise jurídica)

**Q-B06-1. Variante "tributação integral" declarada para serviço cuja NBS está nos Anexos II/III (40 regras: 20 itens
LC116 de saúde/educação × domicílio/presença).**

- Fatos: o tratamento é o fato declarado `rtc.tax_treatment`. Para `REDUCED_60`, o motor exige prova (item do anexo,
  NBS do item, descrição confirmada, consideração exclusiva, evidências). Para `STANDARD` (cClassTrib `000001`) numa
  NBS de anexo, não exige nada.
- Lei: arts. 129 e 130 reduzem as alíquotas dos serviços "relacionados no Anexo […] com a especificação das
  respectivas classificações da NBS". A lei não traz opção pela tributação integral. A descrição de cada item do anexo
  é critério, além da NBS (o qualificador já exige `annex_service_description_confirmed`).
- Ponto controverso: se o serviço concreto não corresponde à descrição do item, a tributação integral é correta. Se
  corresponde, a redução é devida, e aceitar `STANDARD` sem justificativa pode cobrar a mais.
- Alternativas:
  - **A (atual):** aceitar `STANDARD` declarado sem justificativa. Simples, mas sem proteção contra cobrança a maior.
  - **B (recomendada):** aceitar `STANDARD` para NBS de anexo só com qualificação negativa explícita (fato
    "serviço não corresponde à descrição do item X", com evidência). Sem ela, `ACTION_REQUIRED`. É simétrico à prova já
    exigida na redução e não presume a interpretação.
  - **C:** proibir `STANDARD` para NBS de anexo. Bloqueia casos legítimos em que a NBS coincide mas a descrição não.
- Implementação de B: a seleção de tratamento e a redução de alíquota pertencem a 4B.4 (Agente A) e a
  `ConsumptionReformEngine.php` (escritor único: A). B prepara a especificação após a decisão.
