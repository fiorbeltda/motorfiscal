# Temporal86: integração opt-in aceita em desenvolvimento local

## Resultado terminal

Suíte completa2.589 testes/47.492 assertions, exit0;PHPStan geral e Pint527
reexecutados com sucesso,59/59 fontes válidas e119 bloqueios mantidos.
Auditoria final e11 arquivos de evidência copiados por hash para
`storage/app/phase4b-temporal86-development-20261015/`.
Aceitação: `temporal86-development-validation.json`. A aceitação cobre data local
e candidato aritmético, não liquidação temporal completa nem Fase4B/produção.
As seções abaixo preservam a cronologia anterior à aceitação.

## Estado em15/10/2026

Bundle próprio `consumption-phase4b-2026.10.15-86-draft1.json`, predecessor78.
Componentes83/84/85 instalados por transformação mecânica de namespace/imports,
mais qualificador `TaxPointContext86`. Antes dos edits foram arquivadas sete
entradas aceitas78 e preservados os componentes/corpora externos por hash em
`storage/app/phase4b-temporal86-integration-preparation-20261015/`.

A aceitação78 permanece histórica; os hashes do engine78 não representam o
engine atual após a integração deliberada86. Não reescrever a aceitação anterior.
Não há ainda aceitação integral de desenvolvimento86.

## Caminho funcional

Quatro fatos/perguntas: `rtc.assess_tax_point_events`,
`rtc.tax_point_operation_id`, `rtc.tax_point_contexts` (lista de uma operação)
e `rtc.tax_point_installment_id` para identificar a parcela de execução continuada.
Sem o booleano opt-in, o comportamento anterior permanece sem trace novo.
Bundles sem política recusam a solicitação do novo caminho.

A política vincula quatro fontes oficiais brutas, contratos, intervalo e
observação máxima15/10/2026 14:11:36.200607Z. Não permite retrospectivamente
usá-la como conhecimento anterior à observação. Publicação, disponibilidade,
revogação/supersessão e intervalo de aplicação são tratados separadamente.
O intervalo de pesquisa local não comprova alíquotas futuras nem dispensa
revisão de atos posteriores para novas datas de conhecimento.

O qualificador confronta operação/documento/valor, espécie de fornecimento,
data declarada e data derivada da prova. Não altera silenciosamente a data ISS
ou competência. Contexto ordinário usa83 e85; o continuado exige parcela
selecionada e igualdade com o bruto desta decisão. A política integrada atual
é de operação doméstica onerosa regular, tributação padrão, saída e fornecedor
como recolhedor. Demais tratamentos, público/cancelamento e espécies especiais
não entram por esse caminho para contornar suas qualificações próprias.

Com antecipações, após qualificar incidência/classificação/base,84 produz
memória nominal segregada com datas de alíquota próprias. Transformações de
base/taxa que ainda exigem alocação por evento são recusadas. O exemplo300/1000
em2026 mostra CBS antecipada2,70, definitiva9,00, diferença6,30, mas termina
`ACTION_REQUIRED / RTC_TEMPORAL_PRIOR_ADVANCE_DEBITS_NOT_PROVEN`.
Não converte antecipação calculada em débito anteriormente provado ou extinto.
Sem CBS2027 comprovada, mantém `RTC_RATE_NOT_PROVEN:CBS`.

## Provas terminadas

- 149 testes dirigidos/885 assertions após formatação final.
- PHPStan geral sem erros e Pint geral527 arquivos.
- 14 cenários,28 decisões nativas RTC/ISS,28 replays exatos e3 históricos
  (Simples78, comprador estrangeiro76 e exportação73).
- 59/59 fontes oficiais por hash, produção falsa, elegibilidade apenas local.
- O avaliador adiciona exclusivamente
  `CONTROL_NOT_APPROVED:4B.1.TEMPORAL_EVENT_SETTLEMENT` aos118 bloqueios78;
  nenhum bloqueio anterior foi removido.

Corpus: `storage/app/phase4b-temporal86-cli-20261015/temporal86-cli-corpus-validation.json`.
Pré-auditoria: `temporal86-integration-preflight.json`, congelando runtime,
evidências, snapshots, fontes e gates. O corpus contém documentos sintéticos
e não autentica contribuinte, recibo ou transmissão governamental.

O primeiro corte integrado preservado teve144 passes/5 falhas por expectativa
de `VALID` no runner completo; este corretamente retorna `VALID_WITH_WARNINGS`
pelos avisos de bundle não aprovado/parâmetros municipais sintéticos. A correção
confere ambos os avisos nominalmente e preserva ISS20,00. Não removeu guardas.
Fixtures iniciais com canonical_id incompatível com a regra SaaS foram
preservadas; a v2 corrigiu o vínculo antes de qualquer execução CLI.

## Regressão completa ativa

Wrapper `D:/ProjetoSaasExports/capture_temporal86_full_suite.py`.
Evidência `D:/ProjetoSaasExports/phase4b-temporal86-full-suite-20261015/`.
Handle89267, cliente Docker PID2292. Pest ativo confirmado por `compose top`.
Não tratar esse PID ou o exibido por compose como PID interno intercambiável.

Esperado aritmeticamente:2589 testes (2440+149); a contagem/assertions reais só
serão aceitas na saída terminal. O wrapper confere inventário/hashes antes e
depois, incluindo corpora utilizados pelos testes, e registra log no D:.
Não editar app/tests/resources nem evidências de entrada durante a execução.
Timeout de observação não autoriza reiniciar. Após término, rodar auditor final,
gates gerais e avaliação de produção novamente, com novos outputs exclusivos.
Auditor preparado, executar somente após `full-suite.json` terminal:
`D:/ProjetoSaasExports/finish_temporal86_development.py`.

## Pendências do objetivo integral

Prova de débitos anteriores, documentos de liquidação, período de apuração,
cancelamento/pagamento a maior, contextos não cobertos pela política atual,
moedas e exportação parcial/documentação, parâmetros futuros e matriz original
dos80 requisitos. Não declarar4B.1 ou Fase4B completos a partir desta integração.

[Contrato87: origem da antecipação antes do fornecimento](phase4b-temporal87-advance-origin-contract.md)
explicita a próxima implementação e por que dívida vencida do ledger mensal não
substitui prova de antecipação anterior.
