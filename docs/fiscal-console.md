# Console de acompanhamento local

Acesso: http://localhost:8080/console-fiscal, com o Compose já iniciado.
Status JSON: `/console-fiscal/status`. Atualiza a cada 30 segundos enquanto
a aba está visível; falhas preservam a última leitura na tela.

A tela está em `resources/views/fiscal-console.blade.php`, diretório de views
deste Laravel. O observador está em `resources/monitoring`, carregado apenas
pelas rotas do console. Não há serviço global, migrations, dependência de banco,
execução do motor, comandos, testes, publicação ou chamadas externas na coleta.
A rota `/` original foi preservada. A desativação consiste em remover o
`require` de `routes/fiscal-console.php` em `routes/web.php`.

## Fontes e significado dos indicadores

Revisão15/10: fases3 e4A encerradas funcionalmente para Sandbox.4B parcial,
sem percentual funcional global auditado. O painel mostra0/8 encerramentos
formais como contagem, não como0% de implementação. Os oito grupos possuem
resumo de entregas locais, pendências e documentos em `objective-review.json`,
vinculado ao bundle aceito.5–7 são planejadas; componentes antecipados de
perfis/governança e regressões locais não equivalem ao encerramento dessas fases.
O contador de fases encerradas não inclui fundação sem documento de fechamento.

- Entrega atual e histórico: `resources/monitoring/task-progress.json`.
  Percentual calculado como marcos concluídos / marcos totais. É avanço da
  entrega delimitada, não percentual geral da Fase4B. Mostra concluídos,
  pendências, próximo passo e data da atualização.
- Regra permanente de encerramento: `AGENTS.md`. Toda task deste projeto deve
  atualizar o registro do console antes da resposta final e conferir seus dados.

- Roadmap: `resources/monitoring/roadmap.json`, com fases 3–7 e fundação,
  transcritas do plano original. Novas fases e termos de encerramento devem
  ser registrados nessa configuração; o observador não interpreta prosa para
  descobrir fases futuras automaticamente.
- Fases 3/4A: 100% significa fechamento funcional declarado no documento
  indicado, para Sandbox; não corresponde a homologação de produção.
- Fase 4B: grupos 4B.1–4B.8. Um grupo encerra apenas quando todos os controles
  vinculados são APPROVED. Percentual = grupos encerrados / oito. Parcial não
  recebe peso estimado. Zero grupos encerrados não significa zero código
  implementado. Os 80 requisitos ainda dependem de auditoria individual.
- Sem evidência: percentual desconhecido, nunca inferido pelo volume de código,
  testes, número de drafts ou data dos arquivos. Não há percentual geral
  misturando fases com tamanhos e escopos diferentes.
- Aceitação: relatório `storage/app/*/*development-validation.json` com
  `full_suite_complete=true` e `full_suite.exit_code=0`, escolhido por data do
  registro. Bundle exato obtido do comando registrado e conferido por SHA-256.
- Objetivos: avaliação de produção capturada no log vinculado por hash ao
  relatório. Não executa o avaliador e não troca por uma matriz mais recente
  sem aceitação. Pendências são históricas daquele corte.
- Integridade: somente arquivos de `runtime_hashes` do relatório. Detecta
  diferenças; não certifica o inventário inteiro nem revalida o código atual.
- Notas: oito arquivos de `docs/fiscal` mais recentemente modificados,
  incluindo preparações não integradas. Modificação não significa aceitação.
- Conexão: disponibilidade da página/status. Não representa heartbeat,
  processamento em andamento, saúde do PostgreSQL ou resultado de nova suíte.

Relatórios de versões futuras com outro formato ficam sem aferição até que
o adaptador seja atualizado. A página não substitui o processo de aceitação.
Os endpoints acompanham a configuração de rede existente do servidor local.

## Verificação isolada

## Organização da interface (19/10/2026)

Três áreas independentes: **Visão geral**, **Fases e objetivos** e
**Histórico e evidências**. A primeira usa `task_progress.focus` para exibir
somente o resumo atual, entregas do corte, pendências e próximo passo.
O percentual continua calculado pelos marcos, não pelo texto do resumo.
Sem `focus`, há compatibilidade com registros anteriores.

Critérios, marcos, entregas acumuladas, controles e documentação ficam em
seções recolhidas. O histórico completo é pesquisável; seu conteúdo não foi
apagado. A área selecionada, detalhes abertos e filtro sobrevivem ao refresh.
As notas antigas de revisão nos JSONs foram agrupadas em `history_reviews`;
`current_review` identifica a revisão atual. As oito coberturas só aparecem
quando `baseline_bundle` coincide com o bundle efetivamente aceito.

Antes de encerrar cada tarefa, atualizar os três registros e o resumo `focus`,
retirar pendências superadas dos campos atuais e guardar os cortes anteriores.
Não transformar 100% de uma entrega em 100% da Fase4B ou produção liberada.

## Execução do check

```powershell
& 'D:\Docker\DockerDesktop\resources\bin\docker.exe' compose exec -T app php scripts/check_fiscal_console.php
```

O check usa arquivos temporários próprios e verifica ausência de evidências,
JSON incompleto, suíte falhada, percentual sem peso arbitrário, vínculo por hash,
alterações após aceitação e ausência de escrita nas fontes observadas.
