# B10 — Modelo de aceite em dois eixos (decisão do usuário, 03/11/2026)

Aplicado pelo Agente B, escritor local da matriz 80. Base `87d22e7` + B01–B09 locais, sem commit.

## Decisão registrada

- Os 80 requisitos originais **não** são reduzidos.
- Cada item passa a ter dois eixos independentes, além dos campos históricos (preservados):
  `implementation_state` e `external_dependency_state`, mais `sandbox_acceptance`.
- Estados de implementação: `IMPLEMENTADO_E_VALIDADO_INTEGRALMENTE`, `FUNCIONAL_SANDBOX_COM_DEPENDENCIA_EXTERNA`
  (rótulo obrigatório "Sandbox aceito; validação externa pendente"), `CANDIDATO_ACEITE_INTEGRAL_REQUER_VERIFICACAO`,
  `PARCIAL`, `BLOQUEADO`, `NAO_INICIADO_OU_NAO_COMPROVADO` e `PENDENTE_DE_INTEGRACAO_A`.
- Regras:
  - recusa segura isolada não aprova requisito que exige implementação ou prova positiva;
  - lacuna local nunca vira dependência externa;
  - o aceite Sandbox exige teste positivo (oficial e/ou sintético identificado), recusa segura e dependência documentada
    com fonte, versão, data e impacto;
  - XML local nunca é declarado válido num XSD sem os campos, nem aceito pelo governo;
  - regressão com testes obrigatórios excluídos é parcial.

## Situação aplicada (matriz `storage/app/phase4b-b10-development-20261103/requirements80-current-matrix.json`)

| Grupo | Estados |
|---|---|
| 4B.1–4B.4 (A) | 44 `PENDENTE_DE_INTEGRACAO_A` (estado histórico preservado) |
| 4B.5 | 10 `PARCIAL` |
| 4B.6 | 8 `PARCIAL`, 2 `CANDIDATO_ACEITE_INTEGRAL_REQUER_VERIFICACAO` (4B.6.7, 4B.6.8) |
| 4B.7 | 3 `PARCIAL`, 1 candidato (4B.7.3) |
| 4B.8 | 10 `PARCIAL`, 2 candidatos (4B.8.2, 4B.8.4) |

Nenhum item com `sandbox_acceptance=true`. Os 5 candidatos precisam de verificação própria antes de contar.
