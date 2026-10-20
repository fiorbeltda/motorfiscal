# Datados108 - integracao local instalada,aceite ainda pendente

Em19/10/2026,105/106/107 foram instalados em namespace do motor e ligados
por opt-in a um bundle108 proprio.104 e seus predecessores permanecem
aceitacoes historicas imutaveis. Antes das mudancas,11.133 entradas/79 hashes
104 foram conferidos e engine/assessor/monitoramento arquivados no D:.

Evidencias:
`D:/ProjetoSaasExports/phase4b-dated108-integration-20261019/`.
`before-integration-archive.json`,`dated108-bundle-installation.json`,
`dated108-directed-validation.json`,recibos/logs de checks e falhas preservadas.
Bundle atual:
`resources/fiscal/bundles/consumption-phase4b-2026.10.19-108-draft3.json`.
Drafts1/2 sao tentativas de transporte preservadas,nao aceites.

## Fluxo e contratos

Novo opt-in `rtc.assess_export_dated_documents` e registros materiais105,
resultados106 e identidades107. RegimeISS exige declaracao explicita e lista
`provider.iss_regime_proof_records` com exatamente uma prova. Seis novos
fatos/perguntas. Nao inferir regimeISS de REGULAR RTC ou LUCRO_REAL.

O novo caminho nao mistura os opt-ins antigos96/98/104:contexto misto e
recusado. Nao elimina validacoes:nacional95,municipal97 e material105 sao
reexecutados no106/107 por dataISS de cada documento,sem a igualdade a
rtc.operation_date imposta pelos wrappers antigos. Cotacao,alocacao,
classificacao,destino e calculo nominalRTC anteriores sao avaliados antes
de PARTIAL_EXPORT_DATED_DOCUMENTS. Sucesso local continua ACTION_REQUIRED /
RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN,sem decisao tributaria/XML.

O novo transporte preserva FactDefinition::ARRAY como lista;105 recebe o
objeto da unica prova apenas na projecao de qualificacao. Nao foram ampliados
tipos do kernel ou schema historico. Schema novo permite especificamente
essa lista. Dois builders falhos foram preservados:objeto direto rejeitado
pelo schema e envelope com objeto rejeitado pelo tipoARRAY do FactBag.
Os qualificadores105/106 continuam byte-identicos apos trocar namespace;
107 teve ainda ajustePint de import redundante do proprio namespace/estilo,
com fonte anterior preservada e255 casos permanentes de paridade.

## Hashes sem ciclo e sem mutacao de negocios

A prova107 carrega o hash do106;seu proprio fato transportador fica fora
desse hash para impedir ciclo. Todas as outras entradas fornecidas continuam
vinculadas. O caminho integrado acrescenta fatos derivados de apresentacao
ISS/federal. Sao excluidas da projecao apenas estas cinco chaves e somente
quando sourceType==DERIVED:service.lc116_code,service.ctribnac_code,
resolved.iss.location_strategy,payment.gross_amount,invoice.gross_amount.
Uma chave igual fornecida pelo usuario continua vinculada;fato derivado
desconhecido ou de negocio nao ganha isencao geral. Datas,partes,referencias,
moeda,provas e valoresRTC nao sao reescritos. Trace registra hashes do contexto
completo e fornecido,lista das exclusoes e normalizacao do transporte.

Primeira bateria:801 passaram/20 falharam por hashes diferentes no fluxo
integrado. Fonte/expectativas/log preservados. A correcao foi a projecao
explicita acima,nao remover casos ou mudar expectativas fiscais.11 testes
adicionais comprovam exclusao limitada,preservacao de fatos fornecidos e
vinculo de dados derivados desconhecidos.

## Checks efetivamente concluidos

904 testes direcionados/12.595 verificacoes,exit0,incluindo controles103/104.
671 testes novos:115 de105,174 de106,255 de107,11 da projecao,44 avaliacoes
de22 cenarios e72 avaliacoes de36 casos a montante nos dois runners.
Cada cenario compara nominalRTC com bundle104
sem novos fatos;datas diferentes permanecem positivos obrigatorios.
PHPStan geral ePint572 arquivos aprovados.76 fontes validas e124 bloqueios
no assessor;eligible_for_local_validation=true,ready=false.

A auditoria dirigida confere11.131 entradas104 intactas e apenas duas
mudancas autorizadas (engine/assessor),com originais arquivados. Runtime atual
tem83 hashes:79 anteriores e quatro classes novas. Alíquotas,transicao,
classificacoes,correlacoes,cIndOp e catalogos anteriores nao foram alterados.
O recibo de metadados105 e pesquisa local,nao foi rotulado como norma oficial.
Cinco novas copias oficiais trazem observacao e hashes,sem vigencia inventada.

## Pendencias e criterio

5/7 marcos tecnicos,71% somente desta integracao:prototipos105-107,arquivo,
instalacao,checks/auditoria dirigidos e corpusCLI ampliado concluidos.
Regressao completa/qualidade final e aceite independente pendentes.
Os22 cenarios iniciais foram ampliados com36 casos:30 rotas104 e seis recusas
cambiais73. Auditoria nova confere56 contextos nacionais,56 corpos de provas
municipais e60 identidades originais preservados,sem deduplicar negativos.
Expectativas foram declaradas no seed,nao extraidas da resposta do motor.
Recibo:`dated108-upstream-validation.json`;checks:`directed-v4`,`phpstan-v3`,
`pint-v4`. O corpusCLI ja percorreu58 casos nos dois runners e seus replays
proprios;corpus terminal em19/10 as14:16:45:116 decisoes/116 replays proprios
e427 historicos exatos,92 comparacoes nominais capturadas104 e72 checks
decimais independentes.76 fontes validas/124 bloqueios mantidos.
Recibo:`cli-v1/dated108-cli-validation.json`.

Correcao da revisao anterior:os30 nomes originais104 ja continham prefixo
upstream-export-. Nao havia omissao no lookup do coletor. O verificador por
original_path e uma verificacao independente redundante,nao correcao do motor
ou dos resultados. A inferencia equivocada em dated108-upstream-validation.json
foi preservada,com errata explicita fora das entradas108 congelaveis:
`D:/ProjetoSaasExports/phase4b-dated108-review-correction-20261019/collector-lookup-correction.json`.

Preflight concluido e regressao iniciada uma unica vez as14:20:12 de19/10,
com13.395 arquivos congelados e83 hashesruntime. Verificacao independente
por caminho confirmou52 comparacoes a montante como subconjunto/redundancia
das92 comparacoes do coletor;nao somar as contagens como novas memorias.
Evidencias em `D:/ProjetoSaasExports/phase4b-dated108-full-suite-20261019/`:
`preflight.json`,`process-start.json` e log continuo `full-suite.log`.
7.200 testes sao a contagem esperada,nao resultado aprovado.
Nao editar codigo/testes/recursos/scripts/evidencias108 apos congelamento.
Os replays e a analise de aplicabilidade municipal/historica/regimes continuam
necessarios.
O paragrafo acima registra o corte anterior ao termino,nao o estado atual.

## Encerramento tecnico local —19/10/2026,15:00

Regressao unica concluida:7.200 testes/107.350 verificacoes,exit0.
As13.395 entradas permaneceram inalteradas,sem adicoes/remocoes;83 hashesruntime
conferidos. PHPStan geral,Pint572 e avaliacao final76 fontes/124 bloqueios
aprovados. Auditoria independente concluiu e publicou duas copias verificadas.
Aceite local: `storage/app/phase4b-dated108-development-20261019/dated108-development-validation.json`.
SHA256: `5d7585b577d2b974e2df09062eb8a271c16cb8870ae5952b11d132556a96c53e`.
Originais grandes no D:,aceite104 preservado.108 e o corte local aceito atual.
58 cenarios,116 replays proprios e427 historicos;92 controles nominais104
iguais e72 verificacoes decimais independentes. As52 comparacoes por caminho
sao subconjunto redundante dessas92,nao novas memorias.

Conversao,base/imposto por documento,parXML,ciclo documental completo e
auditoria individual dos80 requisitos continuam pendentes. Integracao local
de evidencias nao autentica contribuinte/governo ou autoriza emissao/credito.
Nenhum dos oito grupos4B esta encerrado;3/4A fechadas em Sandbox,5-7 planejadas,
producao bloqueada.104 e o ultimo aceite de desenvolvimento anterior e seu
hash nao descreve as duas mudancas atuais;nao reescrever a evidencia104.
O aceite108 agora cobre essas mudancas no escopo local delimitado,sem
aprovar aplicabilidade juridica,emissao ou producao.
