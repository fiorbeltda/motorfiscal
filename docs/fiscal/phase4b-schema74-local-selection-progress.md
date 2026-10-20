# Seleção local do XSD de homologação - progresso 74

A geração 73 foi fechada tecnicamente antes desta alteração: suíte completa
2.266/45.742, corte dirigido 50/213, Pint 504 e auditor de desenvolvimento
aprovados. A obrigação documental da exportação parcial permanece aberta.

`DpsRestrictedSchemaSelection` introduz o contrato opt-in
`DPS-LOCAL-RESTRICTED-XSD-2026.10.14-74` no comando `fiscal:dps:project-xml`.
Ele vincula os hashes exatos do ZIP e do membro raiz observados. Exige ambiente
2, escopo exclusivamente estrutural local, instante de observação explícito,
conhecimento e emissão posteriores à observação, emissão não posterior ao
conhecimento, competência civil válida e série numérica entre 0 e 89999.
O resultado declara ausência de prova de implantação remota, obrigação fiscal
e aptidão para produção. Os três contratos antigos mantêm seu caminho anterior.

O comando foi arquivado antes de editar em
`storage/app/phase4b-local-schema74-integration-20261014/before-project-dps-command.php`.
O registro `pre-change-archive.json` vincula o arquivo anterior e a aceitação 73.

Foram acrescentados 18 casos de teste para seleção válida, ambiente de produção,
fonte ainda não observada, conhecimento insuficiente, emissão futura, datas
inválidas, offset fora do limite civil, competência conflitante, série e
substituição de hash/raiz/escopo. A execução dirigida inclui também projetor e
validador históricos; PHPStan dirigido foi iniciado. Ainda não existe resultado
terminal confirmado desses checks. Os wrappers ativos não foram reiniciados.

Nenhum bundle 74 foi criado ou ativado até este ponto. O pacote observado
continua sem campos e sem o modo misto 5 da NT009. A ativação do contrato local,
corpus CLI próprio, recusa de produção e replay dos bundles anteriores precisam
ser concluídos após os checks. A obrigação da exportação parcial requer seu
contrato separado. Esta página registra progresso, não aceitação do corte 74.

## Construtor preparado e incidente de disco

`scripts/build_phase4b_restricted_schema74_bundle.py` foi preparado, mas não
executado. Seus gates exigem os checks de seleção, estilo, PHPStan e testes
do avaliador, além da recusa explícita `RTC_DPS_RESTRICTED_SCHEMA_LOCAL_ONLY`
instalada no avaliador. Essa guarda foi instalada após recuperação e novo
arquivamento verificado do avaliador no D:. Seu teste verifica a permanência
do bloqueio mesmo quando a assinatura sintética do revisor é válida e vinculada
aos hashes exatos. A sintaxe passou no PHP local 8.2; teste e PHPStan em PHP 8.5
continuam pendentes. Não há aceitação de funcionamento desse corte.

O disco C ficou cheio durante sua tentativa de arquivamento/alteração, e uma
gravação interrompida deixou `Phase4bReadinessAssessor.php` com zero bytes.
O arquivo foi reconstruído a partir da leitura completa anterior à alteração
preservada nesta conversa e restaurado no checkout. A transferência foi
conferida por hash: 14.139 bytes, SHA-256
`71120179bfb3a1b75678d2c6f55e738ee15b8e9ea268b37dab5b5f3100a743e2`.
O hash original anterior ao incidente não estava disponível; não se declara
uma comparação binária histórica. A sintaxe passou no PHP local 8.2, sem
substituir a validação funcional obrigatória em PHP 8.5.

Cópia de recuperação e registro:
`D:/ProjetoSaasExports/phase4b-schema74-recovery-20261014/Phase4bReadinessAssessor.php`
e `recovery-record.json`. Nenhuma evidência fiscal histórica foi apagada.
Os handles dirigidos continuam pendentes de resposta do Docker, mesmo após
inicialização normal do aplicativo. A distribuição WSL responde e consta
Running; isso não comprova saúde da API Docker nem conclusão dos checks.

A cópia antes da guarda posterior está em
`D:/ProjetoSaasExports/phase4b-schema74-recovery-20261014/Phase4bReadinessAssessor-before-local-only-guard.php`.
O teste próprio é `tests/Fiscal/Unit/DpsRestrictedSchemaReadiness74Test.php`.
Os relatórios de recuperação anteriores permanecem como registros do momento
de restauração, anterior à instalação desta guarda.

## Reinício autorizado do Docker Desktop

Após autorização explícita do usuário, `docker desktop restart --timeout 45`
terminou com código 1: os processos não encerraram no prazo. Foram então
encerrados somente processos com executável sob `D:/Docker/DockerDesktop/`,
terminada a distribuição `docker-desktop` e iniciado o aplicativo novamente.
Não foram removidos volumes, imagens, distribuições ou evidências do projeto.

As tentativas `scope74-selection-directed` e `scope74-selection-type`
terminaram com código 1 por indisponibilidade da conexão Docker, não por
resultado dos testes ou análise. Seus logs e JSON originais foram preservados.
Ambos os logs têm SHA-256
`82529e9921f8f8787a288e663e10f86522d61d9c654fa495d66f5df3d9e68758`.
Uma repetição deverá usar rótulos novos e atualizar explicitamente os gates
do construtor, sem sobrescrever esses registros.

O aplicativo abriu com novos processos, mas a API ainda retornou HTTP 500 na
consulta `compose ps`; o log do backend registra timeout do controle de
inicialização e ausência de resposta do motor. Isso não comprova recuperação
dos contêineres. O C: continua com menos de 300 MB livres. Os checks PHP 8.5
e a instalação do bundle 74 permanecem pendentes; produção continua bloqueada.

## Recuperação WSL e checks dirigidos — tentativa v3

Com nova autorização do usuário, tentou-se reiniciar WslService; o Windows
recusou acesso administrativo. `wsl --shutdown` concluiu com código 0 e o
Docker Desktop foi iniciado novamente. A API respondeu 29.8.0; app, web e
PostgreSQL foram iniciados com `compose up -d --no-build --pull never`.
PostgreSQL ficou saudável e o runtime confirmou PHP 8.5.10.

A tentativa v2 de testes passou 38/108. PHPStan v2 apontou coalescência
redundante de um grupo regex e Pint v2 apontou formatação do comando.
As duas correções foram aplicadas após cópias verificadas no D:; os primeiros
resultados foram preservados. Os registros finais, todos exit 0, são:

- `scope74-selection-directed-v3`: 38 testes, 108 assertions;
- `scope74-selection-type-v3`: PHPStan nos três arquivos de aplicação, sem erros;
- `scope74-selection-style-v3`: Pint nos cinco arquivos do corte, aprovado.

Esses labels ficam no diretório histórico de checks 71, sem sobrescrever v1/v2.
O corte inclui teste da guarda de produção com assinatura sintética válida.
Ainda não foi executado o construtor 74 nem atualizados seus gates antigos;
o corpus CLI do novo bundle e a regressão completa 74 continuam pendentes.

Foram movidos apenas temporários antigos e caches regeneráveis para
`D:/ProjetoSaasExports/temporary-cleanup-20261014`, com registro
`README-recovery.md`; não houve remoção definitiva. Evidências, anexos,
volumes e código foram preservados. Apesar de aproximadamente 1,12 GB
retirados, o espaço livre oscilou e terminou em cerca de 223 MB; o arquivo
de paginação foi observado crescendo nesse intervalo. Sua configuração não
foi alterada. Não iniciar uma regressão longa sem margem de disco estável.

## Integração dirigida 74 comprovada

O construtor foi arquivado no D: antes de substituir os labels por v3.
Os gates novos conferem também os argumentos dos comandos e sua cobertura:
o teste do avaliador e sua análise de tipos estão incluídos nos cortes v3.
Não foram removidos gates funcionais para contornar as primeiras falhas.
O construtor exclusivo terminou e criou o bundle
`consumption-phase4b-2026.10.14-74-draft1.json`, seu catálogo com 245 campos,
controles próprios e cópia exata do ZIP observado. SHA-256 do bundle:
`8e5c83d873742a45a6d7344af396f0253955ad58dad3891af33e390136f05ae6`.

O corpus próprio usa competências históricas de 01/10, conhecimento do novo
bundle e emissão posterior à observação da fonte. Não altera fatos de negócio
nem snapshots anteriores. As três rotas são federal integrada, educação e
educação presencial integrada. Resultado nativo: nove XMLs válidos (séries
1/00001/89999), doze recusas (ambiente 1, série 90000, emissão antiga e futura),
três replays históricos exatos e três replays novos exatos. Repetições do
projetor também conferiram determinismo. A primeira tentativa ficou preservada:
o auditor buscava xml_sha256 em projection em vez de schema_validation;
o runtime já havia validado o primeiro XML. A tentativa v2 corrigiu só essa
leitura do relatório e concluiu 40 comandos nativos.

Seis entradas sintéticas foram promovidas por hash ao diretório próprio
`resources/fiscal/scenarios/dps-local-2026.10.14-74-draft1`.
`DpsRestrictedSchemaCommand74Test.php` acrescentou 21 testes permanentes.
Com os seis testes históricos do comando, o corte passou 27/178;
Pint do novo teste passou. A regressão dirigida do avaliador passou 14/75.
Não somar esses números aos 38/108 como se fossem uma suíte sem sobreposição.

O avaliador do novo bundle conferiu 52/52 fontes, elegibilidade local true,
ready false e 119 bloqueios, incluindo a guarda de schema exclusivamente local.
O auditor `audit_phase4b_schema74_directed_integration.py` conferiu logs/hashes,
manifesto/catálogo/controles, XMLs, recusas, fontes e replays. Relatório exclusivo:
`storage/app/phase4b-schema74-cli-corpus-v2-20261014/directed-integration-validation.json`.
Ele declara explicitamente aceitação completa pendente. Não houve transmissão.

Pendências: regressão completa 74 e auditor de desenvolvimento, contrato
documental de exportação parcial com fontes/temporalidade próprias, migração
NT009 e matriz integral dos 80 requisitos. O pacote observado continua sem
campos novos e modo misto 5. Não usar esta seleção estrutural como prova de
obrigação fiscal ou implantação remota. Produção e conclusão 4B permanecem
bloqueadas. O C: terminou com aproximadamente 177 MB livres; a suíte longa
não foi iniciada. Os scripts de criação já executados são exclusivos e não
devem ser reexecutados sobre estes mesmos destinos.

## Suíte completa em execução com log no D:

Reavaliou-se a restrição de disco: os temporários principais da suíte usam
o filesystem do contêiner, com ampla margem; capturas condicionais de corpus
não estão habilitadas. O checkout no C: continua sendo o real. Não foram
movidos evidências ou recursos necessários aos testes. Uma cópia temporária
inativa do atualizador Docker foi movida por hash para o arquivo recuperável
no D:, sem instalação/alteração de versão. O C: continua baixo; não se declara
que essa retirada trouxe ganho líquido constante.

Foi criado `D:/ProjetoSaasExports/capture_schema74_full_suite.py`, que grava
diretamente no D: sem acumular stdout em memória. O script registra antes e
depois os hashes de app/tests/recursos JSON/XML/XSD e arquivos de configuração,
sem editar o código sob teste. O comando usa PHP 8.5 com memory_limit=512M,
suíte Pest completa, cores desativadas, sem cache de resultados e diretório
de cache no /tmp do contêiner. O resultado ainda não existe neste checkpoint.

Evidência exclusiva:
`D:/ProjetoSaasExports/phase4b-schema74-full-suite-20261014/`.
`preflight.json` e `process-start.json` foram gravados; `full-suite.log` cresce
com resultados nativos. Handle de observação 80854, wrapper Python PID 22108,
cliente Docker PID 24380. O `compose top app` confirmou processo Pest ativo
(PID exibido 7431; não usar esse número como PID interno sem resolução).
Uma observação lenta não autoriza reinício. Não editar código/testes/recursos
durante este corte. Aguardar terminal e conferir `full-suite.json`, hashes,
contagens reais e estado do disco antes de declarar aceitação técnica 74.

## Fechamento técnico local 74 — terminal conferido

A suíte terminou em 14/10/2026 às 16:51 (America/Sao_Paulo): 2.306 testes,
45.937 assertions, exit 0, duração nativa 1.570,25 s. SHA-256 do log:
`e83643ffe821e791cbb93f07e8ca2639af9d027e400875f36dd82066cca325cb`.
O wrapper conferiu hashes antes/depois sem alterações; o auditor final
também comparou a lista de arquivos para detectar inclusões/remoções.
Foram conferidos 3.761 arquivos de entrada. Os handles 80854 (suíte) e
92640 (fechamento) terminaram; compose top confirmou apenas PHP-FPM.

Após a regressão: PHPStan geral sem erros, Pint geral em 508 arquivos,
avaliação final com 52/52 fontes, elegibilidade local true, ready false,
119 bloqueios e guarda RTC_DPS_RESTRICTED_SCHEMA_LOCAL_ONLY preservada.
Todos os comandos terminaram com exit 0 (o alvo do avaliador foi validação
local, não produção). O auditor final permaneceu somente leitura.

O relatório exclusivo é
`D:/ProjetoSaasExports/phase4b-schema74-full-suite-20261014/schema74-development-validation.json`.
Após recuperação da margem no C: (cerca de 919 MB), os logs e registros foram
copiados por hash, sem alterar o original D:, para
`storage/app/phase4b-schema74-development-20261014/`.
Não reexecutar os scripts exclusivos sobre os mesmos destinos.

Esta aceitação corresponde somente à integração estrutural local opt-in
do ZIP restrito observado. Não conclui a migração NT009/comExt modo 5,
obrigação documental da exportação parcial, emissão real, assinatura,
transmissão ou Fase 4B. Os corpora históricos e primeiros erros continuam
preservados. Os três contratos antigos permanecem no caminho anterior.

Durante a suíte, foram produzidas somente pesquisas/notas novas no D:.
As Pesquisas 30 e 32 foram lidas integralmente; a Pesquisa 30 possui correção
posterior de uma frase antiga sobre país do resultado, que não deve virar
exigência universal de schema. A fonte oficial de códigos de moeda BCB foi
capturada com hash em
`D:/ProjetoSaasExports/phase4b-document75-bcb-currency-research-20261014/`.
Sua observação atual não comprova vigência histórica na competência nem
origem material da cotação; nenhuma moeda nova foi ativada.

Preparação 75 no D: comprovou conservação de seis alocações arquivais,
mas o controle BRL não contém os mesmos identificadores documentais dos
cinco controles USD. O projetor atual exige adquirente BR/CPF/CNPJ e não
monta identidade estrangeira/COMEX: os nove XMLs 74 não provam o futuro par
de exportação. A próxima implementação precisa de tratamento ISS independente,
identidades internacionais, reconciliação documental/IBS/CBS, seleção de
schema e obrigação temporal própria, com corpus e revisão de fontes primárias.
