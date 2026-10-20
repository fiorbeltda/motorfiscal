# Corpus119 — preparação e falha de fidelidade

Fechamento20/10:regressão integral119 e aceite independente concluídos,
8.614 testes/133.926 verificações. Consulte
`phase4b-material119-day-closure-20261020.md`. Os relatos abaixo preservam
os pontos históricos;corpus/regressão119 não estão mais pendentes.

Atualização após término do corpus:248 decisões/replays de preços e1.071
históricos concluídos e auditados antes da correção. O escritor passou a
preservar a fração zero dos númerosJSON;28 testes/188 verificações aprovados,
PHPStan geral e Pint597 aprovados. A nova tentativa `contract-cli-v2` concluiu
90 decisões e90 replays,incluindo1.0 nos dois executores;somados aos32 casos
geográficos são370 decisões e370 replays próprios. Snapshots antigos não foram
reescritos;o snapshot falho continua divergente,como comprovação do defeito.

`material119-snapshot-fidelity-validation.json` vincula os92 hashes atuais,
o escritor anterior e a única adição de teste após o corpus congelado.
O leitor pós-correção v1 parou por não aplicar esse mapeamento temporal ao
inventário de entradas;v2 aplica somente o arquivo arquivado e a adição de
teste expressamente vinculados,sem ignorar outras diferenças. Capturas e
primeiro leitor foram preservados,sem reexecutar corpus por erro do leitor.

A auditoria120 de créditos terminou com54/80 IDs examinados,26 não revistos e
falhas materiais adicionais abertas. A regressão integral119 e seu aceite
continuam pendentes. O texto abaixo preserva a descoberta e o ponto anterior.

124 transportes sintéticos de preços119 preparados e requalificados:74 cenários
iniciais e50 fontes adicionais. Mantidos todos os fatos materiais/monetários
dos112,71 composições positivas,11 fontesUSD e dois eventos literais de ciclo
que tornam recusas quatro decisões nos dois executores. Não foram deslocadas
datas para preservar resultados positivos. Conhecimento sintético posterior
à disponibilidade119; vínculos de identidade/preço/composição recalculados.

599 testes dirigidos /9.439 verificações; auditoria3.660 conferências.
PHPStan geral sem erros e Pint596 aprovados nesse corte. Esses resultados
não constituem aceite integral119.32 decisões/replays geográficos119 concluídos.

## Falha reproduzida nos comandos nativos

`contract-cli-v1` preserva89 decisões concluídas,88 replays exatos e uma
divergência. O caso de valor monetário aninhado JSON `1.0` é corretamente
recusado por119 como número binário. Contudo, `JsonFileWriter` grava `1` no
snapshot, perdendo a distinção float/inteiro. A reavaliação desse snapshot
passa aVALID e o replay diverge doINVALID original.

Uma reprodução independente confirmou os dois tipos e os resultados sem
alterar runtime. O replay seguinte integrado do mesmo caso não foi executado:
o coletor parou corretamente na primeira divergência. Não houve90/90 passes.
Não se deve converter o valor recebido para inteiro nem editar o snapshot
falho para obter aprovação. A correção deve preservar tipos em novos arquivos,
mantendo arquivos históricos imutáveis e a recusa monetária original.

Evidências em `D:/ProjetoSaasExports/phase4b-material-input119-integration-20261020`:

- `material119-expanded-preparation-validation.json`;
- `cli-v1/material119-cli-validation.json`;
- `contract-cli-v1`, incluindo coletor arquivado e snapshot falho;
- `snapshot-fidelity-probe-v1`;
- `snapshot-fidelity-fix-v1/failure-observation.json` e `JsonFileWriter-before.php`.

O corpus de preços e1.071 replays históricos foi iniciado com entradas
congeladas antes dessa correção. Acompanhar o mesmo processo até terminar,
sem modificar runtime/fixtures durante a captura. Depois corrigir o escritor,
adicionar regressões permanentes e executar uma nova tentativa dos contratos
com resultados/snapshots e replays completos. Nenhuma regressão integral119
foi iniciada; nenhuma aceitação nova foi publicada.112 permanece último aceite.

Os dez requisitos de crédito4B.5 estão em investigação separada120, sem
modificar os arquivos congelados; uma investigação não encerra requisitos.
Os80 itens originais continuam no escopo. Produção permanece bloqueada.
