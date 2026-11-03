# Resumo executivo — Agente B (4B.5–4B.8), 03/11/2026

**DESENVOLVIMENTO INDEPENDENTE DO AGENTE B ESGOTADO — INTEGRAÇÃO E DEPENDÊNCIAS EXTERNAS PENDENTES.**
Na B22 o catálogo de créditos presumidos foi completado com todas as hipóteses da LC 214 (44 testes / 141 asserções).

## O que o B implementou e validou (cortes B01–B21, todos locais)

- **Catálogos e regras (4B.6):**
  - Anexo VIII 1.02.00 como referência sem autoridade (B01);
  - relatório das 99 regras do motor × correlação oficial (B04);
  - análise documental das 83 regras (B06);
  - especificação da Q-B06-1 com 360 testes (B07);
  - oráculo independente de cClassTrib v1.70 e NBS v1.01 (B20).
- **Créditos (4B.5):**
  - causa distinta para crédito zero (B02);
  - invariantes (B09);
  - ciclo de estorno e glosa (B11);
  - 14 vedações legais (B13);
  - utilização dos créditos pelos arts. 53–55 (B14);
  - revalidação dos arts. 49–52 (B15);
  - créditos presumidos, catálogo completo (B17/B22);
  - ciclo completo (B18).
- **Documentos (4B.7):** fontes oficiais revalidadas byte a byte (B03, B05). O XSD de Produção Restrita é igual ao usado no 143
  e segue sem os campos da NT009.
- **Corpus e aceitação (4B.8):**
  - matriz de aceite em dois eixos (B08, B10, B21);
  - verificação dos candidatos (B12);
  - matriz de períodos 2026–2033 (B16);
  - aceite da correção do parâmetro sintético (B19);
  - inventário das evidências ausentes (B05).

## Situação dos 36 requisitos do B (detalhe em `06-ESTADO-DOS-36-REQUISITOS.md`)

| Estado | Itens |
|---|---:|
| Integralmente implementado e validado | 5 (4B.6.7, 4B.6.8, 4B.7.3, 4B.8.2, 4B.8.4) |
| Sandbox aceito; validação externa pendente | 6 (4B.6.3, 4B.6.4, 4B.6.5, 4B.6.10, 4B.7.2, 4B.7.4) |
| Parcial, com trabalho próprio do B possível | 0 |
| Parcial, dependente do A | 18 (inclui 4B.5.1) |
| Dependente de publicação oficial | 4 (4B.6.1, 4B.6.2, 4B.7.1, 4B.8.10) |
| Dependente de evidência ou ambiente | 3 (4B.8.1, 4B.8.8, 4B.8.9) |

Itens do A (44): pendentes de integração. Grupos encerrados: 0/8. A Fase 4B **não** está concluída.

## Testes

- **Regressão parcial** em blocos (41 arquivos excluídos por evidência ausente): 9.478 casos únicos, 8.644 aprovados,
  **513 falhas, todas de ambiente, 0 de código**, e 321 pendentes do A.
- **Pacote reproduzido** num checkout limpo: 261 testes do B aprovados e 1 pendente.
- **Regressão integral:** não executada; precisa das 34 pastas da máquina antiga.
