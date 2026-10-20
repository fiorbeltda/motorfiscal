# Identidade103 - composicao e vinculo local de duas DPS

Componente preparado no D:,sem instalacao no motor ou novo bundle. As fontes
foram capturadas em16/10/2026;checks nativos executados em19/10/2026. Nao
reclassificar capturas historicas como documentacao recapturada em19/10.

## Fonte municipal original do plano

O manual municipal do K: e a captura oficial sao identicos:
SHA256 `3b5e982be9298ecc72ee254e26eb6824dc13d2e05704a83f01aa7a8fd321916e`.
Seis paginas lidas;pagina fisica4 renderizada e inspecionada com a skillPDF.
Descricao do identificadorDPS confrontada com a pesquisa102 e seus anexos/XSD.
Publicacao e vigencia material nao provadas permanecem nulas.

## Componente e limites

`PartialExportDpsIdentity103`,contrato
`DPS-PARTIAL-EXPORT-LOCAL-IDENTITY-2026.10.16-103`.
Fonte/politica e conhecimento vinculados ao instante
`2026-10-16T18:55:42.879759+00:00`,com hashes dos dois manuais,dos dois
anexos102 e do XSD de homologacao. O anexo de producao serve a comparacao,
nao habilita producao.

Escopo declarado:software digital em exportacao parcial,prestadorPJ comCNPJ
numerico,emitenteprestador,aplicativo proprio e ambiente2. Serie1-49999 e
numero1-999999999999999,campos textuais exatos. ID recomposto com45 caracteres.
Nao acrescentar ambiente ao ID oficial,pois ele e dimensao separada.

Cada papelRTC_FOREIGN/RTC_DOMESTIC preserva sua referencia original do fluxo
e recebe uma identidadeDPS mais uma prova local de mapeamento. A prova vincula
operacao,papel,referencia,transacao,prestador,municipio e hash da identidade.
Dois IDs canonicos iguais sao recusados mesmo quando a serie foi escrita
como1 em um documento e00001 no outro. Ordem de papeis nao altera o hash
semantico;hash bruto preserva a ordem fornecida.

Datas exatas e offsets ate14:00. Emissao/conhecimento/prova respeitam ordem
temporal do contrato;competencia declarada deve coincidir com a operacao e
nao superar a data civil da emissao. Isso nao qualifica a data material ISS.

Resultado positivo:`LOCAL_DPS_IDENTITY_PAIR_BOUND`,somente composicao e
mapeamento locais. Todos os seguintes gates continuam falsos:identidade fiscal
integral,digitos verificadoresCNPJ,cadastro/autorizacao municipal,unicidade
remota,autenticacao governamental,data material,conversao,imposto por documento,
obrigacao documental completa,implantacao remota,XML,lancamento e producao.

Este componente nao executa os gates de incidencia,cambio,alocacao,classificacao
ou resultadosISS95/96/97/98. Integracao deve chama-lo no fluxo atual somente
apos os gates correspondentes;nao usar seu resultado para contorna-los.

## Checks observados

128 casos/2.350 verificacoes no PHP8.5.10:11 vinculos locais,59ACTION_REQUIRED
e58INVALID. Politica/fonte,um microssegundo antes/no limite,offsets equivalentes,
datas invalidas,canal/ambiente/tipo,serie/numero,IDs adulterados,referencias
trocadas,papeis duplicados e mapeamentos adulterados/futuros cobertos.

Saidas nativasV1,V2 eV3 identicas. PHPStan do componente e Pint dos dois
arquivos finais aprovados. A primeira verificacao de tipos pediu guarda
explicita dos dois papeis;primeira falha de estilo e arquivos anteriores
preservados. A primeira formatacao em/tmp falhou por propriedade do arquivo;
o helper passou a ajustar somente os arquivos proprios do103 depois da copia.
Nenhum arquivo do motor foi alterado para corrigir permissao ou estilo.

O Docker estava desligado em19/10. Ambiente iniciado sem build ou recriacao
de volumes;PostgreSQL recuperou e ficou saudavel antes dos checks. Nao houve
suite completa em execucao ou reiniciada neste trabalho.

## Evidencias e proxima integracao

`D:/ProjetoSaasExports/phase4b-identity103-local-preparation-20261016/`:
fonte municipal/copias,texto/imagem,componente,fixture128,probe e logs exclusivos.
SHA do componente formatado:
`f5d0a7b869bb074d1e106562cfb273fc06ac302778eb6d0560bc0e9156cb0851`.

Auditoria terminalV2 aprovada:128 casos/2.350 verificacoes,9.272 entradas e78
hashes runtime101 intactos. Relatorio exclusivo:identity103-preparation-validation.json.
A primeira versao
do auditor exigia erroneamente a politica completa ate nos casos negativos
que a removem deliberadamente;correcao limitada ao auditor,preservada a versao
anterior,sem mudar o componente ou suas expectativas.

Proximo:integrar em nova versao com fatos/perguntas,
testes permanentes,corpus dos dois executores,replays historicos,regressao e
auditoria de desenvolvimento. Preservar os comprovantes e referencias95-101.
101 continua ultimo aceite fiscal local;Fase4B parcial e producao bloqueada.
