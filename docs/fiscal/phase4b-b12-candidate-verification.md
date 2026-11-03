# B12 — Verificação própria dos 5 candidatos a aceite integral (Agente B, 03/11/2026)

Base `87d22e7` + B01–B11 locais, sem commit. Teste: `tests/Fiscal/Unit/CandidateAcceptanceB12Test.php`
(6 testes / 160.891 asserções; Pint aprovado). Teste dirigido; não é regressão integral.

| Item | Prova |
|---|---|
| 4B.6.8 `OFFICIAL_CORRELATION` | Todas as correlações de todas as versões `rtc-codes-*` e da edição 1.02.00 (B01): `kind=OFFICIAL_CORRELATION`, `decision_authority=false` |
| 4B.6.7 Distinção obrigatória | Todas as regras: `kind=ENGINE_DECISION_RULE`, sem `decision_authority`; correlações sem campos de regra; varredura de `app/`: só a referência B01, sem autoridade, lê `correlations` |
| 4B.7.3 Manual municipal | `resources/fiscal/sources/official/identity104-municipal_manual-observed20261016.pdf`, SHA-256 `3b5e982b…916e`, igual ao original exigido (registro em `phase4b-requirements123-document-review.md`) |
| 4B.8.2 Bundle próprio | `CONSUMPTION-PHASE4B-2026.10.27-141-draft1` carregado nativamente e decidindo cenário conhecido (cClassTrib `200029`) |
| 4B.8.4 Catálogos versionados | 13 recursos vinculados por hash canônico; catálogos `rtc_*` com versão no nome e no conteúdo; adulteração detectada |

Efeito no modelo de aceite: os 5 itens passam a `IMPLEMENTADO_E_VALIDADO_INTEGRALMENTE` no escopo B, **sujeitos a revisão
na integração A+B**. Os grupos 4B.6, 4B.7 e 4B.8 continuam abertos, porque os demais itens seguem parciais.
