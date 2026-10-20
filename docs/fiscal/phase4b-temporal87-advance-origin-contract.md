# Temporal87: origem da antecipação antes do fornecimento e vínculo ao definitivo

Contrato de continuidade elaborado enquanto a suíte86 está congelada, sem
alterar runtime, testes, recursos ou entradas da regressão.

## Evidência atual e lacuna concreta

86 qualifica uma data relevante e reconcilia aritmeticamente antecipações no
fornecimento final. Ainda exige prova de débito anterior. O resolvedor83 separa
previsão futura de evento realizado; por isso o fluxo86 atual não calcula uma
antecipação já paga quando apenas a previsão futura do fornecimento é conhecida.
Esse caminho precisa ser implementado, e não preenchido com um recibo inventado
ou com uma avaliação de parcela que finja fornecimento ordinário já concluído.

`MonthlyAssessmentEngine` contém `debits`, `prior_debts` e `extinctions`, mas não
são intercambiáveis: `prior_debts` exige dívida vencida anterior ao mês;
antecipação anterior pode estar no próprio mês e ainda não vencida. O ledger
mensal agrega IBS; a reconciliação86 separa IBS estadual/municipal. Não adaptar
essa categoria por renomeação nem perder os componentes para preencher a guarda.

## Sequência funcional necessária

1. Seleção explícita `ADVANCE_ONLY`: pagamento conhecido e vinculado à parcela,
   sem exigir que o fornecimento futuro já tenha ocorrido. Guardar previsão
   como previsão e registrar que o definitivo permanece aberto.
2. Separar data da ocorrência (pagamento), data da alíquota (primeira emissão
   documental correspondente/pagamento) e período de apuração. Usar a data
   da alíquota no catálogo não autoriza copiar a dispensa de caixa de2026 para
   uma ocorrência de2027. Nenhuma tolerância genérica de cinco dias.
3. Vincular incidência/local/sujeitos/base à operação e à parcela. Manter todos
   os qualificadores do motor; não chamar antecipação de operação integral nem
   deslocar a competência ISS silenciosamente. Base provisória é a parcela paga.
4. Produzir decisão nativa/snapshot de antecipação local com identidade de
   operação/parcela/documento, fornecedor, componente, data de pagamento,
   data da alíquota, base, percentual, valor e fontes/knowledge capturados.
5. No definitivo, receber os snapshots íntegros e sua prova de registro anterior,
   conferir hash/replay/vínculos e conjunto completo de parcelas. Não confundir
   integridade do snapshot com autenticação remota ou pagamento/extinção.
6. Calcular o definitivo sobre o total fornecido e conciliar as antecipações
   qualificadas uma única vez. Diferença negativa segue procedimento próprio,
   sem gerar crédito apropriado automaticamente. Retificações, cancelamento e
   definitivo já registrado exigem estado/evidência próprios.

## Invariantes e corpus obrigatório

- Pagamento passado/fornecimento futuro; ausência de previsão não significa
  prova de fornecimento concluído quando o contexto é de antecipação.
- Documento anterior/posterior ao pagamento, vínculo errado, duplicidade e
  inversão da ordem das parcelas; IDs numéricos textuais preservados.
- Virada2026/2027 com emissão2026/pagamento2027 e cálculo distinto de dispensa;
  taxa futura ausente não recebe fallback sem hipótese documental comprovada.
- Snapshot adulterado, de outra operação/fornecedor/parcela, faltante, extra,
  revogado/retificado ou já utilizado; valor anterior não é reconstituído só
  a partir da alíquota que o catálogo conhece hoje.
- Sem registro anterior provado, manter a guarda86. Sem extinção provada,
  não conceder crédito por considerá-lo pago. Sem leiaute/prova documental,
  manter as respectivas guardas, separadas da aritmética.
- Dois runners, snapshots/replays novos e históricos, versão própria de
  bundle/fatos/perguntas, gates gerais e regressão completa.

Fontes temporais já preservadas81/82 sustentam a distinção de datas; a ativação
do novo caminho requer confronto do contrato com os atos efetivos. Este documento
não é implementação87 nem aceitação de4B.1 ou conclusão da Fase4B.
