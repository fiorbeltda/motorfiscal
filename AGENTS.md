# Regra de acompanhamento do motor fiscal

Ao finalizar qualquer tarefa neste projeto, atualizar `/console-fiscal`:

- Atualizar `resources/monitoring/task-progress.json` com data, estado,
  percentual da entrega com critério e marcos explícitos, concluídos, evidências,
  pendências e próximo passo. Preservar o histórico de tarefas nesse registro.
- Separar progresso da entrega, encerramento dos grupos4B e liberação de produção.
  Não inventar percentual global por volume de código/testes/drafts. Os80
  requisitos originais exigem auditoria individual antes da conclusão integral.
- Exibir resultados efetivamente observados; suíte ativa não é aprovação.
  Preservar aceitações históricas e não sobrescrever suas evidências.
- Executar `php scripts/check_fiscal_console.php` no contêiner, conferir
  `/console-fiscal/status` e verificar a tela quando modificar sua interface.
- Antes da resposta final, garantir que o painel descreva o que foi finalizado,
  os limites e o próximo trabalho. Regra permanente para futuras tasks do projeto.
- Manter `task_progress.focus` conciso: resumo atual, até três entregas,
  até três pendências e próximo passo. Arquivar os cortes anteriores no histórico,
  sem acumular relatos antigos na visão geral; preservar evidências completas.
- Revisar também fases, objetivos e `objective-review.json`: não atualizar apenas
  o card da task. Exibir cobertura funcional concluída e pendências por grupo;
  manter encerramento formal separado da produção e sem percentual estimado.
- Checklist obrigatório de encerramento: conferir os oito grupos4B, as fases
  encerradas/em andamento/planejadas e o histórico. Remover pendências superadas
  dos campos atuais (preservando o histórico), vincular entregas às evidências e
  confirmar no endpoint os estados, concluídos e pendências antes de responder.

Checkout real: `C:/xampp/htdocs/projeto-saas-motor`. Preservar alterações do usuário.
Nunca reiniciar suíte viva por mero timeout de observação.
