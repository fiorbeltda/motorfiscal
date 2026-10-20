# Exportação96 — resultado ISS por documento no fluxo atual

## Estado em16/10/2026

Aceito em desenvolvimento local após regressão terminal:2.962 testes/51.784 verificações,exit0;6.121 entradas intactas,75 hashes runtime e duas cópias pequenas de evidência conferidos. PHPStan/Pint549 finais aprovados.181 testes/2.390 verificações dirigidas,incluindo os12 casos anteriores73.169 testes novos permanentes:116 do qualificador95,11 do vínculo atual96 e42 execuções de integração nos dois runners. A aceitação94 permanece histórica e preservada.

Bundle próprio `CONSUMPTION-PHASE4B-2026.10.16-96-draft1`, com três fatos/perguntas próprios,60 fontes e taxas anteriores preservadas. A avaliação de produção acrescenta `ISS_EXPORT_RESULT_LOCAL_CANDIDATE_ONLY` aos119 bloqueios anteriores:120 no96. Nenhum grupo4B foi encerrado.

## Integração efetiva

O `ConsumptionReformEngine` possui opt-in `rtc.assess_export_iss_results`. A referência da operação vem de `rtc.export_operation_reference`; os dois registros independentes vêm de `rtc.export_iss_result_contexts`, como `RTC_FOREIGN` e `RTC_DOMESTIC`.

O engine primeiro exige a rota de exportação parcial digital ordinária, sem combinar neste contrato os ramos de antecipação/eventos temporais. Seus gates existentes de câmbio, origem, alocação, classificação e taxas permanecem anteriores à qualificação ISS. Só depois do cálculo nominal chama `CurrentPartialExportIss96`, que usa `IssExportResultQualifier95` para cada documento e acrescenta o trace `PARTIAL_EXPORT_ISS_RESULTS`.

O modo95 de snapshot arquivado permanece como preparação de referência. Não foi inserido dentro do engine: isso criaria dependência artificial de cenários antigos ou replay recursivo da operação corrente. O96 vincula provas aos fatos da avaliação corrente e **não afirma ter replayado um snapshot**. Os replays reais são feitos pelos runners e capturados separadamente no corpus.

Com a opção ausente/desativada, nenhuma etapa96 é adicionada. Quando ativada sem política96, referência ou duas provas válidas, exige ação. Não ignora silenciosamente pedido de qualificação em rota doméstica, exportação integral ou combinação temporal ainda não suportada.

Mesmo após vínculo local positivo, o resultado fiscal continua `ACTION_REQUIRED / RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN`. Não reescreve o ISS integral, não emite XML e não calcula imposto por documento. Datas ISS, identidade econômica/fiscal, conversão documental, bases próprias, aplicabilidade legal e obrigação permanecem não qualificados.

## Fontes e critérios independentes

A captura LC116 de16/10 é incluída no inventário por hash, sem presumir vigência histórica/municipal ou publicação do texto compilado; esses campos continuam nulos/não provados. A fonte serve ao contrato candidato de resultado do serviço, não a uma aprovação de produção.

Resultado ISS no Brasil não recebe automaticamente código tributável1; afasta esta alegação de exportação. Resultado estrangeiro só gera candidato de não incidência. Os testes mantêm a independência entre resultado ISS e consumo RTC, sem usar um critério como substituto do outro. Hash semântico do vínculo ignora a ordem dos papéis; o hash de fatos brutos preserva a ordem efetivamente fornecida como evidência.

## Verificação e tentativas preservadas

Evidências em `D:/ProjetoSaasExports/phase4b-export-iss96-integration-20261016/`:
`before-integration-archive.json`, `export-iss96-bundle-installation.json`, manifestos e logs dos gates. Antes da alteração,5.360 entradas94 e72 hashes runtime foram conferidos; engine/monitoramento anteriores arquivados. O assessor de produção anterior também foi copiado antes da alteração.

O primeiro dirigido teve duas expectativas incorretas para2027: a classificação doméstica é recusada antes de resolver CBS. Preservados o log e a expectativa original; o manifesto v2 registra o motivo observado. Não foi estendida a classificação para alcançar uma taxa futura. A indisponibilidade de CBS2027 é verificada separadamente pelo catálogo real. Um ajuste de estilo foi preservado e corrigido antes dos gates finais dirigidos.

O corpus próprio possui21 cenários: seis controles nominais, provas ausentes/trocadas/adulteradas, pagamento em vez de resultado, resultado misto, critérios ISS/RTC distintos, opção desativada e recusas de rota/período. São42 execuções nos dois runners com replays próprios. Os113 replays históricos são verificados separadamente; sua conclusão e a regressão só podem ser afirmadas após os relatórios terminais.

Corpus terminal aprovado:42 decisões/42 replays próprios e113 históricos exatos,60 fontes válidas e120 bloqueios. Relatório:`D:/ProjetoSaasExports/phase4b-export-iss96-integration-20261016/cli/export-iss96-cli-validation.json`.
Regressão completa terminal,com evidências em `D:/ProjetoSaasExports/phase4b-export-iss96-full-suite-20261016/`:2.962 testes/51.784 verificações. Duração nativa1644,04s;host1673,37s. Sem inputs alterados,adicionados ou removidos. Gates PHPStan/Pint549 e avaliação de produção foram repetidos após a suíte:60 fontes válidas e120 bloqueios preservados. A auditoria independente final conferiu também corpus,históricos,arquivos e fontes.

Aceitação: `storage/app/phase4b-export-iss96-development-20261016/export-iss96-development-validation.json`.
SHA256:`5b44a4cd995dc8ae3e8c8a08f03a4361ae40c671aa151f79234286b4974f3453`.
As duas cópias no C: (relatório e avaliação de produção) foram verificadas;logs e inventário volumosos ficaram no D:. A regressão terminou;não reiniciá-la nem sobrescrever sua evidência.

## Próximo trabalho

Qualificar conversão e base ISS por documento,sua identidade e vínculo material;reconciliar as bases ISS/RTC e materializar o par sob contrato de schema/ambiente/data comprovado. Não assinar/transmitir. Fases3/4A encerradas funcionalmente em Sandbox;4B parcial;5–7 planejadas e produção bloqueada. O aceite96 cobre somente vínculo local dos resultados,sem conversão,imposto por documento,XML ou liberação de produção.
