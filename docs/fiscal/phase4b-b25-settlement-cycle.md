# B25: ciclo de liquidação (retirado em 06/11/2026, substituído por A157/A158)

## O que era

Em 05/11/2026 o Agente B implementou localmente o `SettlementCycleB25`. Ele tinha o gate `settlement_cycle_b25` e o fato
`rtc.settlement_records`, e cobria os itens 4B.3.7 (split payment) e 4B.3.8 (recolhimento pelo adquirente). Fonte:
LC 214/2025, arts. 27 e 31–36.

## Por que saiu

No mesmo dia, o Agente A publicou o `1f56dfa`, com duas implementações próprias desses itens:
- A157 (`AcquirerCollection157`): recolhimento pelo adquirente. Está integrado no motor, com livro de reconciliação,
  comprovantes, excedente e repasses ao fornecedor.
- A158 (`SplitPayment158`, `SplitTransaction158`, `SplitLedger158`): split payment. Os componentes estão prontos, mas
  ainda não entraram no motor.

O B25 nunca foi publicado e duplicava esse trabalho. Manter os dois criaria duas reconciliações para a mesma rota ACQUIRER
e dois critérios de rateio de parcelas. O B25 adotava uma convenção local de arredondamento, que o A158 recusa.

## O que mudou

- O componente, o teste e o script do B25 saíram do repositório. A cópia fica fora do repo, em
  `E:\dev\motorfiscal-agente-b-evidence\superseded-b25-settlement-20261106\`.
- O motor não lê mais `settlement_cycle_b25`.
- Os bundles `2026.11.05-b25-draft1` a `b32-draft1` não foram alterados, porque bundles anteriores não mudam. Eles
  continuam carregando a chave e o fato do B25, mas sem efeito, porque o motor não lê mais nenhum dos dois.
- O bundle `2026.11.06-b33-draft1` (`scripts/fork_phase4b_b33_acquirer_collection_bundle.py`, teste
  `tests/Fiscal/Integration/HandoffActivationB33Test.php`) faz duas coisas:
  - retira o gate e o fato do B25;
  - ativa a política exata do A157 e o fato `rtc.acquirer_collection_ledger`, como pede o
    `docs/fiscal/agent-a/20261105/handoff-A157-to-B.md`.

## Situação dos requisitos

- **4B.3.8:** depende do A157. Continua PARCIAL: regimes especiais, importação e calendário de repasse ainda faltam.
- **4B.3.7:** depende do A158. Continua PARCIAL até o A158 entrar no motor atrás de um gate próprio. Depois disso, o B
  ativa o gate num bundle novo.
- **4B.3.2:** sem evidência própria do B. As evidências vêm do A157.
