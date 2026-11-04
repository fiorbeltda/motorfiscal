# A154 — revisão de aceite e validação existente dos 44 requisitos de A

Base: `https://github.com/fiorbeltda/motorfiscal.git`, branch
`codex/fiscal-engine-bootstrap`, HEAD `87d22e70787b128a97e5aed4b175a39e619798af`.
A144–A153 locais preservados; nenhuma suíte viva observada no contêiner exclusivo.

Objetivo: revisar os 44 requisitos no estado atual, distinguindo implementação,
teste executado, prova ausente, parâmetro externo e integração de B. O índice
A144 é uma reconciliação inicial, com classificação parcial por construção;
não serve como mecanismo automático de aceite integral. Esta revisão não
consolida os 80 itens nem cria corpus global 4B.8.

Para cada ID, explicitar o comportamento necessário, os caminhos reais, os
testes existentes e as evidências A145–A153. Executar a união dos testes
relacionados, sem contar arquivos repetidos por requisito. Preservar falhas
e distinguir ausência de fixtures históricas de defeito de implementação.
Resultado verde não prova sozinho cobertura integral. Não reduzir os 44 IDs
ao conjunto que passar, não fechar grupos automaticamente e não criar testes
que apenas espelhem o código para preencher a matriz.

## Escrita exclusiva A

- `scripts/agent_a/audit154.py`
- `docs/fiscal/agent-a/20261103/task-A154-manifest.md`
- `docs/fiscal/agent-a/20261103/task-A154-test-plan.json`
- `docs/fiscal/agent-a/20261103/acceptance44-A154.json`
- `docs/fiscal/agent-a/20261103/review154-results.md`
- `docs/fiscal/agent-a/20261103/handoff-B.md`

Somente leitura: produção, todos os testes existentes, índice A144, recibos
A145–A153 e respectivos pacotes, checkpoint143/matriz80, `AGENTS.md`, console
e metadados globais. Nenhuma alteração de fixtures, fábrica, catálogo, crédito,
documento, infraestrutura ou política operacional. Sem publicação Git.

Plano e preflight devem preceder a suíte. Evidências em
`D:/ProjetoSaasExports/agent-a-A154-acceptance44-review-20261103-v1`:
`preflight.json`, `test-command.json`, `own44-v1.log`, `own44-v1-exit.json`,
`agent-a-A154-existing44-v1.junit.xml`, `closure-v1.json`,
`source-package-final/manifest.json` e cópias inventariadas. Preservar recibos
e logs anteriores. Não chamar essa união dirigida de regressão integral final.

Pesquisa PF: LC214 art.4 §6, art.11 §3 I a e art.59; Decreto12955 art.105/107
consultados em fonte Câmara. Referência primária CGIBS6 encontrada, mas o acesso
web ao PDF expirou; não usar conteúdo de espelho como prova de versão vigente.
Nenhuma regra nova de PF empresarial será escolhida nesta revisão. O limite
continua explícito enquanto a qualificação normativa/material não estiver pronta.

Handoff ao usuário para B: revisão própria por ID, testes efetivamente
observados e proposta de consolidação. Não declarar recebimento/aplicação de B.

## Pausa solicitada pelo usuário

Parar no ponto seguro mais próximo. A154 fica apenas com este manifesto de
revisão: script, plano JSON, relatório de aceite e suíte A154 não foram criados
ou executados. Não há alteração de produção posterior a A153. Os oito arquivos
do recibo A153 foram conferidos e continuam com os hashes aceitos; checkout
original limpo e `git diff --check` aprovado. Contêiner observado somente com
php-fpm, sem Pest ou PHPStan ativos. Nenhum commit/push/PR/merge/tag/deploy.

Ponto de retomada: A153 validado em 508 testes/1.807 asserções na regressão
dirigida acumulada, com recibo e pacote final preservados; A154 é planejamento
pendente. Não interpretar esta pausa como aceite dos 44 requisitos ou da fase.
Retomar apenas após nova solicitação do usuário.

## Retomada autorizada em 04/11/2026

HEAD conferido `6c0f81bffb9923f36d00dcfb9eec5d0fd138bd9f`, árvore limpa.
A publicação posterior de A144–A153 está concluída e não altera o registro da
pausa acima. Usuário autorizou retomar o desenvolvimento; sem nova publicação.
Docker inicialmente desligado: iniciado o Desktop existente por Win32_Process,
sem recriar contêiner, imagem ou volumes. Conferir prontidão antes da suíte.
O plano será a união deduplicada dos testes indexados e suplementos diretamente
relacionados aos contratos de A. Erros de fixture serão conservados, não pulados.
Artefato externo suplementar: `existing-tests-observed.json`, com casos reais e
mensagens extraídas do JUnit, e `console-check-v1.log`/`console-endpoint-v1.log`.
Esses arquivos fazem parte da revisão A154, sem alteração do painel global.

### Ajuste após falha terminal v1

A união v1 terminou com exit1 por esgotar 128MiB em CanonicalJson; JUnit vazio,
logo sem contagem terminal confiável. Preservar log, exit e JUnit vazio.
Reexecutar a mesma seleção/hashes com `php -d memory_limit=1G`, apenas no
processo CLI, sem mudar php.ini/configuração/infraestrutura. Novos artefatos:
`test-command-v2.json`, `own44-v2.log`, `own44-v2-exit.json`,
`agent-a-A154-existing44-v2.junit.xml`. A revisão usa só a execução terminal v2,
mantendo v1 como tentativa abortada; não somar contagens.
