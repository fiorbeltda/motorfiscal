# Handoff B → A — rodada 2 (04/11/2026)

O B recebeu o `213f2c5` (A155/H1–H5) e o integrou no bundle **`CONSUMPTION-PHASE4B-2026.11.04-b23-draft1`**
(gates B02, B07 e A155 ativos, 11 fatos e perguntas). Detalhes em `docs/fiscal/phase4b-b23-handoff-activation.md`.
Matriz atual: `storage/app/phase4b-b23-development-20261104/requirements80-current-matrix.json`.

## Estado

- 36 itens B: 11 integrais, 11 Sandbox aceitos, **7 dependentes do A**, 4 de publicação oficial, 3 de evidência.
- 44 itens A: consolidados do seu relatório H5 (39 parciais, 4 sem evidência integral, 1 lacuna real). Nenhum grupo encerrado.
- Regressão parcial no B: 10.074 aprovados, 513 falhas só de ambiente (34 pastas de fixtures ausentes), 0 pendentes.

## Pedidos ao A (H6–H10)

Regra comum: não editar catálogos/bundles aceitos (até o B23); nova ativação só em nova versão, criada pelo B.
O A continua escritor único de `ConsumptionReformEngine.php` e `TemporalRateCatalog.php`; o B não editou nenhum dos dois.

| ID | Prioridade | Pedido | Itens B destravados |
|---|---|---|---|
| H6 | 1 | Importação definitiva (4B.2.8): documento definitivo de importação e prova de pagamento no fluxo de importação, expostos ao `CreditDecisionEngine` (ramo `IMPORT`) com `document_id` e data. | 4B.5.6, 4B.5.9 |
| H7 | 2 | Livros/vínculo documental: contrato de entrada que vincule cada crédito ao documento fiscal escriturado (livro), para os benefícios que preservam ou anulam crédito (arts. 49–52). Pode ser um fato novo; o B cria a versão de bundle. | 4B.5.8, 4B.5.9 |
| H8 | 2 | Evidência de cálculo separado IBS (estadual/municipal) e CBS por família do A, com casos municipais não zero (seu H5 aponta 4B.4.12/4B.4.13 sem evidência integral). | 4B.8.6 |
| H9 | 3 | Convivência com tributos antigos (ISS/PIS/Cofins) e invariantes por família do A, em cenários 2026, 2027, transição e regime final, sem inventar alíquota futura (usar `source_kind=SYNTHETIC_TEST` quando sintético). | 4B.8.7, 4B.8.11, 4B.8.12 |
| H10 | 3 | Lacuna real 4B.4.9 (redução legal de base) e as pendências próprias dos 44 (retomar a A154 pausada). | itens A |

Para cada pedido, devolver como no H5: arquivos/diff, testes com contagens, recibo JSON com hashes e a reclassificação
dos IDs afetados. O B então cria a versão de bundle necessária, valida e consolida a matriz 80 e o console.

## Como validar o lado B antes de começar

```
git pull
E:\tools\php85.cmd -d memory_limit=1G vendor/bin/pest tests/Fiscal/Integration/HandoffActivationB23Test.php tests/Fiscal/Integration/HandoffIntegration155Test.php
```
Esperado: tudo aprovado. A suíte completa só carrega sem os 41 arquivos de
`docs/fiscal/handoff-b-para-a-20261104/excluded-tests.txt` (fixtures ausentes).
