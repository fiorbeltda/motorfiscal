# ISS105 - componente isolado verificado,sem integracao

Em19/10/2026,o contrato proposto foi implementado isoladamente no D:.
`D:/ProjetoSaasExports/phase4b-iss-date105-local-preparation-20261019/`:
`PartialExportIssMaterialDate105.php`,`date105-cases.json`,`probe105.php`,
`date105-local-component-validation.json` e recibos/logs exclusivos.

115 casos/2.007 verificacoes nativas:13 vinculos locais,58ACTION_REQUIRED,
44INVALID. PHPStan e Pint de dois arquivos aprovados apos formatacao.
Primeiro Pint recusou estilo;fonte anterior/log preservados. A formatacao
nao mudou a saida nativa byte a byte. Nao sao novos testesPest permanentes
nem execucao da suite105.

Provas de regimeISS explicito e prestacao/etapa vinculam operacao,transacao,
partes,papel,documento,contrato,unidade e datas. O conteudoJSON capturado e
seu hash sao confrontados com todo o envelope,periodo,conhecimento e ciclo
de vida. Representacao canonica recusa chavesJSON duplicadas e espacamento
nao canonico;nao significa assinatura ou autenticacao. Origens sao sinteticas.

Positivos incluem dataISS anterior/posterior aoRTC,duas unidades com datas
distintas por papel,mesma unidade com offsets equivalentes,fronteira civil,
etapa mensal realizada sem entrega final,BRL/USD e pagamentos independentes.
Mesma unidade economica exige evento consistente nos dois papeis;diferencas
nao sao aceitas apenas porque as provas foram re-hashadas. Prova de resultado,
competenciaDPS e montantes continuam separados e nao sao requalificados105.

Negativos cobrem modo somente pagamento/contrato/emissao,prova insuficiente,
regime especial/nao demonstrado,periodos,datas/offsets,conhecimento,revogacao,
troca de partes/referencias,conteudo alterado,resignado ou duplicado,e
cancelamento/substituicao sem cadeia. Esta ultima cadeia permanece recusada,
nao implementada;nao concluir toda a cobertura de ciclo de vida.

O componente publica material_date_evidence_locally_bound somente em sucesso.
iss_supply_date_materially_qualified,legal_applicability_approved e todas as
capacidades de autenticacao,imposto,XML,posting/producao permanecem falsas.
E um vinculo local de evidencia,nao prova legal da realidade economica.

As cinco fontes normativas e recibo de metadados foram vinculados por hash,
sem inferir vigencia de publicacao/assinatura. Pesquisa de aplicabilidade
municipal/historica permanece parcial;consultas setoriais nao viraram regras.
Contrato e limites: `phase4b-iss-date105-material-contract-draft.md`.

Auditoria confirmou79 hashes do corte104 aceito intactos. Nenhum arquivo
runtime,recurso fiscal,bundle,fato/pergunta ou teste permanente105 instalado.
Proximos:aplicabilidade/regimes,adaptadores versionados95/97/103 com datas
independentes,corpus composto e integracao completa. Nao anexar o105 aos
adaptadores96/103 mantendo a exigencia de igualdade com dataRTC.

Fase4B continua parcial;nao encerrar4B.1.5/6,4B.7.1 ou4B.8.10 por esses
checks isolados. Cambio,base,imposto e parXML ainda requerem suas evidencias.
