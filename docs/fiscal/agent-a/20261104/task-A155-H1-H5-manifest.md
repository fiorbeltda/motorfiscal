# A155 — executar H1–H5 solicitados pelo usuário

Base local:6c0f81b; remoto origin/codex/fiscal-engine-bootstrap:e3a632b.
Fetch comprovou histórico linear (B74a4195, pacotee3a632b). Não há caminhos
em comum entre mudanças locais A154 e arquivos publicados por B. Preservar
A154/pausa em pacote externo antes de fast-forward local, sem commit/push.

Objetivo: H1 explicar zeros por gate B02; H2 qualificar STANDARD nos Anexos
II/III pelo gate B07; H3 integrar vedações/presumidos por operação e ciclos
B11/B14 na apuração separada; H4 impedir taxa sintética como oficial; H5
entregar relatório/JSON/patch/hash/pacote para B. Não retomar A154.

Escrita atribuída a A pelo pacote de B e autorizada por este pedido:
- `app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php`
- `app/Modules/Fiscal/ConsumptionReform/TemporalRateCatalog.php`
- `app/Modules/Fiscal/ConsumptionReform/CreditIntegration155.php`
- `app/Modules/Fiscal/ConsumptionReform/PeriodCreditAssessment155.php`
- `app/Console/Commands/AssessPeriodCredit155Command.php`
- `tests/Fiscal/Integration/HandoffIntegration155Test.php`
- `tests/Fiscal/Unit/PeriodCreditAssessment155Test.php`
- `scripts/agent_a/integrate155.py`
- `scripts/agent_a/handoff155_fixture.php`
- `docs/fiscal/agent-a/20261104/task-A155-H1-H5-manifest.md`
- `docs/fiscal/agent-a/20261104/paused-A154.md`
- `docs/fiscal/agent-a/20261104/handoffs-H1-H5-results.md`
- `docs/fiscal/agent-a/20261104/handoffs-H1-H5-receipt.json`
- `docs/fiscal/agent-a/20261104/requirements44-handoff-H5.json`
- `docs/fiscal/agent-a/20261103/handoff-B.md`

Somente leitura: componentes/testes/relatórios B, catálogos e bundles aceitos,
fábrica, definições de fatos, perguntas, documentos/XML, infraestrutura, banco,
console e matriz80. B mantém criação de versões reais de catálogos/bundles.
Contratos novos serão descritos antes da implementação nos detalhes abaixo.
Usar ativação em memória apenas em teste identificado; não ativar gate em
catálogo aceito nem alegar que B já recebeu/integrou a entrega.

Fontes: conferir trechos oficiais LC214 preservados pelo B e revalidar fonte
primária para regras fiscais de H2/H3; H1 é semântica de engenharia e H4
proveniência, sem inventar norma/taxa. Validar positivos/negativos e ausência
de gate, dois ramos de crédito, tabela B07, taxas oficiais/sintéticas,
proibições/exceções e ciclo separado, determinismo, CLI e oráculo monetário.
Rodar suítes B relevantes sem editar seus arquivos; pendentes B ficam
explicitamente separados dos testes de integração próprios de A.

Evidências e cópia de pausa em
`D:/ProjetoSaasExports/agent-a-A155-H1-H5-20261104-v1`: inventários/hash,
comandos/logs/JUnit novos, fontes brutas e recibos, patch completo, arquivos
novos, relatório e pacote final. Não alterar os recibos A144–A153.

## Contratos de integração a implementar

H1: reutilizar gate e assinatura B02 nos dois pontos existentes; sem gate a
saída permanece idêntica. H2: tabela B07 antes da seleção de tratamento,
consultando itens/NBS do anexo já capturado; política presente adulterada é
recusada. Nenhum padrão STANDARD/REDUCED é inferido.

H3: adaptador A `CreditIntegration155`, gate exato `credit_integration_a155`
(constante POLICY no código), chama B13 para INPUT com os fatos planos
sugeridos por B. `rtc.presumed_credit_claims` é lista de no máximo uma hipótese
B17, vinculada à data, documento de entrada e regime do adquirente; qualificação
aparece na decisão/trace, separada de crédito ordinário e sem apropriação ou
soma automática. Parâmetro sintético permanece não oficial.

Apuração separada `RTC-PERIOD-CREDIT-ASSESSMENT-2026.11.04-A155` recebe
assessment_id, taxpayer_id, ledger_evidence_id, period, ledger_complete,
credits, events, debts, refund_requested e transfers. Encadeia B11→B14 usando
somente o saldo ajustado, preservando data original e restrições de restituição,
sem posting/extinção. Não alterar cálculos internos de B.

H4: recusar source_kind diferente de OFFICIAL e marcador synthetic verdadeiro
ou malformado; não permitir que um campo negue o outro. Ausência dos campos
mantém bytes históricos. Metadados explícitos têm official booleano; recusa
sintética marca official=false. B16 contém caracterização histórica que deve
falhar após a correção (B é responsável por atualizá-la); registrar separadamente.

Critério H5: disponibilizar pacote completo local para encaminhamento pelo
usuário, sem alegar recebimento por Claude/B e sem publicação remota implícita.

Detalhe H3: pedido presumido permanece verdadeiro no input original. Somente
a projeção de avaliação ordinária recebe um fato DERIVED=false para que o
qualificador art49/50 não avalie a hipótese B17. O trace registra essa separação;
B17 é obrigatório para o pedido e não soma nem substitui o crédito ordinário.
Claim exige acquisition_date, document_id, acquirer_id e acquirer_regime iguais
à operação. Decisão preserva `credit_integration155` e o resultado B17.
Exceção de plano coletivo B13 exige `rtc.supplier_specific_credit_debits`:
duas linhas IBS/CBS, amount exatamente igual ao respectivo input, supplier_id,
document_id, extinguished=true e evidence_id. Não inferir esse montante de
um booleano genérico de extinção; ajustes ordinários continuam aplicados uma vez.

Apuração: `prior_utilization_complete=true` e `previously_utilized_amount`
explícito em cada crédito impedem reutilizar saldo já compensado/consumido.
O ledger cobre os eventos históricos de ajuste; o adaptador subtrai utilizações
anteriores após B11 e antes de B14. Valor ausente não vira zero implicitamente.
Inputs/saídas CLI gerados em `storage/app/agent-a-A155/` (ignorados pelo Git),
copiados com hashes para o pacote externo. Oráculo Python Decimal independente
confere consumo anterior, ajuste, compensação e saldo IBS/CBS separadamente.

## Publicação autorizada ao final

Usuário solicitou em04/11/2026: "Assim que finalizar faça um commit especificando
a finalização do hand off para o claude". Preparar commit local depois dos
resultados terminais, com Codex e H1–H5/Claude no título. Incluir o checkpoint
pausado A154 para preservar retomada. A ordem atual é commit; sem push implícito.
Conferir diff, inventário, hashes, HEAD e remoto antes de registrar o commit.
SHA final e verificação do commit no recibo externo, sem inserir SHA circular
no próprio conteúdo commitado. Não retomar o desenvolvimento regular.

Revisão final: evidências adicionais `qualified_supplier_debits` pertencem ao
envelope A155, fora do objeto B13 com `result_hash`; preservar a assinatura
original de B. Referências B07 são STRING: espaços internos significativos
são conservados, sem aceitar referência vazia ou de controle.
