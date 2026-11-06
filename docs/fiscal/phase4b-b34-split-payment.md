# B34: split payment do A158 integrado ao motor (06/11/2026)

## Decisão

Em 06/11/2026 o usuário decidiu que o Agente B faz a integração do A158 ao motor, sem esperar o Agente A
("Faz você agora"). O A158 (`SplitPayment158`, `SplitTransaction158`, `SplitLedger158`, commit `1f56dfa`) não foi
alterado. O B só liga o livro de conciliação à decisão do motor. **Agente A: não refazer esta integração.** Se o A158
mudar de contrato, basta um bundle novo com a política nova.

## O que entrou

- `app/Modules/Fiscal/ConsumptionReform/SplitPaymentIntegrationB34.php`
  - Gate `split_payment_a158`, cuja política fixa os contratos e os hashes dos manuais do A158.
  - Fato `rtc.split_payment_ledger`, com um único livro no contrato `RTC-SPLIT-LEDGER-A158-V1`.
  - Vínculos exigidos antes de reconciliar:
    - documento, fornecedor e adquirente iguais aos fatos;
    - `rtc.payment_transaction_id` presente no cronograma;
    - todas as transações na data da operação (horário de Brasília);
    - débitos de IBS/CBS iguais aos calculados pelo motor;
    - `known_at` igual ao instante de conhecimento.
  - Recusas: `RTC_SPLIT_LEDGER_REQUIRED`, `..._FACT_CONFLICT`, `..._TRANSACTION_CONFLICT`, `..._OPERATION_DATE_CONFLICT`,
    `..._DEBIT_CONFLICT` e `..._KNOWLEDGE_CONFLICT`, além das recusas `A158_*` do próprio livro.
- `ConsumptionReformEngine.php`, em três pontos, todos dentro do gate:
  - um livro sintético retém a decisão final (regra A156);
  - estágio `SPLIT_LEDGER158` no trace;
  - `decision.collection.split_ledger158` com o relatório completo, que pode ser repetido com `SplitLedger158::replay`.
- Bundle `2026.11.06-b34-draft1` (`scripts/fork_phase4b_b34_split_payment_bundle.py`):
  - fork do b33;
  - muda só o catálogo de códigos, os fatos, as perguntas e os controles de produção;
  - o catálogo de transição **não** muda.

## O que não foi inventado

- **Implantação oficial do split:** `split_deployments` continua vazio. No bundle instalado, a rota SPLIT_PAYMENT para em
  `RTC_SPLIT_OFFICIAL_DEPLOYMENT_NOT_PROVEN` antes do livro. Uma linha de implantação só aparece nos testes, em memória e
  marcada como `SYNTHETIC_TEST`.
- **Procedimento simplificado:** o A158 recusa esse procedimento (`A158_PHASE1_DOES_NOT_IMPLEMENT_SIMPLIFIED`), porque
  não existe percentual oficial.
- **Calendário e prazos:** o relatório devolve `deadline_status = QUALIFIED_CALENDAR_AND_REPORTING_WINDOWS_REQUIRED`.
- **Lançamentos e autenticação:** nada é lançado, autenticado ou pago (`posted`, `authenticated`, `deployment_proven` e
  `payment_performed` são sempre `false`).

## Testes

- `tests/Fiscal/Integration/SplitPaymentIntegrationB34Test.php`, candidato em memória:
  - reconciliação na decisão candidata, no caminho direto e no integrado;
  - rota histórica preservada sem o gate;
  - bloqueio sem implantação;
  - seis recusas de vínculo;
  - política alterada;
  - determinismo e replay.
- `tests/Fiscal/Integration/HandoffActivationB34Test.php`, bundle instalado:
  - fork mínimo;
  - política exata e demais códigos idênticos;
  - posição do fato e da pergunta;
  - bloqueio por implantação não comprovada;
  - mesma reconciliação do candidato em memória;
  - o b33 recusa o fato novo.
- Fixture `scripts/agent_b/split158_engine_fixture.php`: livro de uma parcela, todo sintético, com débitos de IBS 1,00 e
  CBS 9,00 do cenário SaaS regular.

## Situação dos requisitos

- **4B.3.7:** continua PARCIAL. O ciclo padrão local está integrado e testado, mas ainda há trabalho próprio pendente,
  da lista de retomada do A158:
  - revisão dos dados fiscais e reset de retornos;
  - vários pagamentos do mesmo boleto;
  - cronograma com parcelas futuras ainda não iniciadas.

  Também há dependências externas:
  - a implantação oficial (ato RFB/CGIBS);
  - o percentual oficial do procedimento simplificado;
  - o calendário qualificado de repasses e prazos.

  O item só passa a *Sandbox aceito; validação externa pendente* depois que o trabalho próprio estiver resolvido.
- **4B.1.5, 4B.3.10, 4B.4.11 e 4B.4.14:** o trace e o replay do split passam a existir no motor. Isso não muda a
  classificação desses itens.
