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

# Arquivos volumosos e rastreabilidade entre C: e D:

Priorizar sempre o D: para arquivos pesados,corpora,snapshots,XML,logs extensos,
renderizações e novas evidências volumosas. Manter no C: os caminhos lógicos
necessários ao projeto e índices/recibos compactos que apontem para o D:.

O usuário autorizou transferir evidências históricas volumosas para o D:,
preservando integralmente o conteúdo e a rastreabilidade. Usar junções quando
necessário para que os caminhos antigos no C: continuem funcionando. Registrar
origem,destino,data,inventário e SHA256;verificar cópia e acesso pelos dois
caminhos antes de considerar a transferência concluída. Não apagar histórico.

Não mover arquivos utilizados por capturas,suítes ou replays ativos. Confirmar
estado terminal primeiro;validar os alvos absolutos e manter recuperação segura.

No DockerDesktop,junçõesNTFS para outro disco não são suficientes:um teste
sintético confirmou que funcionam noWindows mas não pelo bind do checkout.
Para evidências migradas,manter também binds explícitos doD: nos mesmos
caminhos lógicos do contêiner,preferencialmente somente leitura. Conferir hashes
no host e no contêiner antes de remover a cópia antiga. Não recriar contêiner
vivo;preservar antes sua camada gravável/temporários relevantes noD: e manter
volumes e banco intactos. Configuração preparada não significa mount aplicado.

Em21/10/2026,o DockerDesktop foi relançado oculto porWin32_Process.Create,
com paiWmiPrvSE.exe,fora da árvore transitória do executor. Recibo:
`D:/ProjetoSaasExports/docker122-detached-relaunch-20261021-v1/readiness-validation.json`.
Se precisar relançá-lo,preferir esse método com logs/PID noD:,sem tarefas
agendadas adicionais. Não reiniciar se já estiver vivo nem interromper coletas.
Validar também reinício com junções existentes;apenas o teste antes da migração
não comprova a conectividade posterior. O índice compacto das duas primeiras
pastas migradas está em`storage/app/evidence-storage-index-20261021.json`.
