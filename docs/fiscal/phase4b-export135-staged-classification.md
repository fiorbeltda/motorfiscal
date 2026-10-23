# Exportação135 — classificação da parcela estrangeira em preparação

IntegraçãoCLI135 em23/10:comando monta doisDPS completos XSD com replay134,ponte própria,identidade,COMEX e classificação.203 testes/498 asserções,PHPStan global0/Pint673,22 históricos exatos e2 XML distintos idênticos entre execuções. Grupo próprioPIS-Cofins ainda só no relatório. Regressão integral iniciada12:03:18UTC com5896 entradas,incluindo scripts/helpers;resultado terminal pendente.0/8 grupos encerrados. Ver `phase4b-pair135-local-projection.md`.

Avanço da ponte135 em23/10:todos os tributos próprios reexecutados e preços conferidos comRTC.166 testes/426 asserções,PHPStan0/Pint5;3 cenários,6 replays e6 oráculosDecimal. Nova declaração sintética400/641,33 reconcilia1041,33−41,33=1000,00;contrato617,92 e diferença de um centavo continuam recusados. Integração/par135 e efeitos judiciais pendentes;0/8 encerrados. Ver `phase4b-own-bridge135-price-reconciliation.md`.

Avanço135V2 em23/10:cálculo próprio dePIS/Cofins por receita de software,comISS deduzido sob alegação localTema118 (mérito21/10,ata23/10).143 testes/386 asserções,PHPStan0/Pint4;2 replays134 e10 conferênciasDecimal. A ponte preservada diverge21,90 no documento nacional. Efeitos judiciais e integração pendentes;0/8 grupos encerrados. Ver `phase4b-own135-software-revenue.md`.

Retomada135 em23/10:guarda isolada de exclusões declaradas por documento,sem integração ao bundle.94 testes/316 asserções,PHPStan0/Pint3;2 replays134 exatos,8 controles. Próximo:avaliações próprias e provas materiais por tributo antes do par135.134 última regressão integral;0/8 grupos encerrados. Ver `phase4b-legacy135-declared-exclusions.md`.

AvançoCOMEX em22/10:componente isolado `DocumentComex135` vincula uma prova
por documento,com referência de contrato/unidade/linha,dataISS,partes,hash do
resultadoISS fresco,camposCOMEX e alegação de execução/presença do consumidor.
Passaram15 controles em duas execuções idênticas,com replay integral da entrada:
dois positivos e13 recusas.O segundo positivo muda a alegação de presença e
o modo coerente sem mudar o papelRTC,confirmando que o modo não é inferido
pelo rótuloRTC_FOREIGN/RTC_DOMESTIC.Recibo em `source-staging135-v1/comex-v1`.

O perfil local contempla execuçãoBR,sem movimento temporário de pessoas/bens,
partes sem vínculo,mecanismos de apoio declarados ausentes e moedaBRL com
preço sem descontos.Sem alegação material coerente,recusa divergência de modo,
moeda,preço,documento,partes,data,ciclo de vida,campo extra ou prova duplicada.
Os campos são conferidos também contra as facetas preservadas doXSD127.
`currency_temporal_validity_proven=false` e `proof_authenticated=false` continuam
explícitos.A prova sintética não é autenticação de fato comercial ou concessão
de benefício.Não suporta silenciosamente outros perfis ou campos futurosNT009.

Ensaio conjunto em `source-staging135-v1/bound-pair-comex-bridge-v1`:novo snapshot
de origem reexecutado,identidades comparadas ao envelope,alegaçõesCOMEX e ponte
aritmética requalificadas antes de montar o par.DoisXML completos passaram no
XSD e foram idênticos em duas execuções.O estrangeiro usa preço400,00 e modo1
(transfronteiriço);o nacional617,92 e modo2(consumoBR).Alterar apenas o preço
COMEX do XML para1000,00 foi recusado comexit1.A sequência usa o preço próprio
de cada documento;as opçõesCOMEX copiadas dos primeiros ensaios ficam somente
no histórico,não constituem valores aprovados para o novo par.

O ensaio agora registra identidades vinculadas,alegaçõesCOMEX localmente ligadas
e preços aritmeticamente reconciliados.Ainda não aprova exclusões legais,demais
tributos do preço,cadastro ou emissão fiscal.Componente135 e montador permanecem
isolados noD:/temporário do contêiner;nenhum arquivo congelado ou bundle foi
alterado.A suíte134 continua dependência para instalação e regressão seguinte.

Avanço aritmético em22/10:componente isolado `DocumentPriceBridge135` preparado
para confrontar preços documentais próprios e alocaçãoRTC fresca,sem editar
nenhum deles.Recibo em `source-staging135-v1/price-bridge-v1/validation.json`
noD:.Foram12 controles em duas execuções idênticas,com replay integral do
snapshot134 de origem e oráculoPythonDecimal independente.

O controle positivo é uma nova declaração contratual sintética:preço estrangeiro
400,00 e nacional617,92,o segundo contendoISS próprio17,92.Resulta uma ponte
aritmética400,00+(617,92-17,92)=1000,00,com parcelasRTC400,00/600,00.
Esses preços são entradas novas expressas,com provas107/110/111/133 reexecutadas;
não foram calculados pelo componente a partir do percentual de consumoRTC.
O caso anterior com documentos1000,00/1000,00 não foi modificado e sua diferença
continua recusada.O ensaio não altera baseISS617,92 ou imposto17,92 para fazê-los
coincidir com a parcelaRTC600,00.

A prova de ponte delimita uma alegação explícita de que o preço composto contém
somenteISS próprio como tributo legado.A conferência liga documento,contrato,
unidade,linha,data e hashes frescosISS/alocação;verifica conteúdo original da
prova,observação,cobertura e revogação/supersessão.Exclui aritmeticamente apenas
oISS transportado pelo resultado fresco,e não desconto condicionado.

Isso é reconciliação de alegações e valores,não qualificação jurídica de base.
O componente retorna `legacy_exclusion_legally_approved=false`,
`other_legacy_taxes_assessed=false` e obrigação incompleta.A ausência de outros
tributos no preço é declaração sintética expressa,não conclusão obtida porque
as retenções federais não foram solicitadas.PIS/Cofins,outras exclusões e prova
material da alegação precisam de qualificação própria antes da aceitação fiscal.

Onze controles negativos recusaram preçoISS alterado,imposto alterado,parcelaRTC
alterada,referência documental diferente,alegação incompleta sobre outros
tributos,revogação,observação futura,prova ausente,hash de vínculo antigo,
cobertura fora da data e os preços anteriores sem diferença explicada.
Os casos de contexto/prova foram reenvelopados nos testes:novo hash coerente
não eliminou a contradição material.Nenhum bundle135 foi instalado.

Avanço seguinte em22/10:as novas identidades1351/1352 e emissão19:10UTC
foram incorporadas a um cenário sintético isolado,com provas107/110/111/133
reexecutadas e integração134 corrente. O snapshot resultante foi reexecutado
integralmente antes da montagem. A guarda do montador confronta número,série,
instante,município eID da identidade qualificada com o envelopeXML. Os doisXML
continuaram XSD-válidos e idênticos em duas execuções;trocar apenas o número
nacional para9999 foi recusado com código de saída1. Recibo em
`source-staging135-v1/identity-pair-probe-v2/validation.json` noD:.

Isso supera a falta de vínculo de identidade daquele ensaio específico abaixo,
sem autenticar cadastro ou emissão.Não houve instalação no checkout congelado.
COMEX e reconciliação material dos preços documentais comRTC continuam pendentes.
O novo cenário e as provas são sintéticos,com derivação preservada em
`identity-binding-v1/derivation.json`;não reescrevem fixtures ou capturas antigas.

Em `identity-pair-probe-v1`,a guarda recusou o número alterado,mas o handler
de exceção do bootstrap mostrou a exceção sem retornar falha ao sistema
operacional.O ensaioV2 instala handler explícito local,retornaJSON de recusa
eexit1,validando novamente as duas execuções positivas e o controle negativo.
Esse ajuste limita-se ao script temporário de preparação135.

Avanço posterior em22/10:ensaio estrutural do par completo montou doisXML
com valores134 próprios e classificações estrangeira410027/nacional000001.
Ambos passaram noXSD127 intacto e foram idênticos em duas execuções.
Artefatos e recibo em `source-staging135-v1/full-pair-probe-v2` noD:.
O replay integral do snapshot134 de origem foi conferido antes da montagem.
Não foi instalada nenhuma alteração nos arquivos congelados.

Esse ensaio explicita o que ainda falta para integração fiscal:novos números
e instante de emissão não estão vinculados às provas107 anteriores;as opções
COMEX são declarações sintéticas;os preços documentais não foram reconciliados
com as parcelasRTC. Por exemplo,o preço próprioBRL1000,00 do documento nacional
continua diferente da base nominalRTC600,00,sem inferir igualdade por rateio.
O relatório registra essas três qualificações como falsas. A montagem comprova
viabilidade estrutural do par,sem aprovar a obrigação documental ou uso fiscal.

A primeira execução estrutural falhou porque `verAplic` tinha27 caracteres,
acima do limite20. O erroXSD foi preservado em `full-pair-probe-v1`;corrigido
somente o identificador técnico no ensaio,com nova execução exclusivaV2.
Nenhuma faceta do schema foi alterada para fazê-lo passar.

Em22/10/2026, durante a suíte integral134, foi preparado fora dos arquivos
congelados o componente `PartialExportForeignClassification135`. Código,
entrada de referência, sete controles e recibo estão em
`D:/ProjetoSaasExports/phase4b-iss134-20261022-v1/source-staging135-v1`.
Nada foi instalado em `app`, `tests` ou `resources/fiscal`; não há bundle135.

O componente seleciona localmente CST410/cClassTrib410027 para a parcela
estrangeira de uma exportação parcial digital onerosa já qualificada pelo
motor, confrontando fatos e alocaçãoRTC fresca. Reexecuta a referência100 das
tabelas oficiais preservadas e confere o vínculo do código ao art80II e o
indicador `ind_gIBSCBS=0` doCST410. Não usa a tabela como decisão tributária
isolada: exige fornecedorBR, adquirente/destinatário estrangeiros, consumo
estrangeiro, provas de alocação, documentos distintos e as parcelas materiais
recalculadas. Não seleciona tratamentoISS pelo papelRTC.

O AnexoVI1.04.01, capturado em22/10 às19:02:09UTC, traz as regrasE0859/E0860
em `RN DPS_NFS-e!D697:Q698`:a ocorrência de `cIndOp` depende do indicador do
grupoIBS/CBS. OXSD127 preservado permite sua omissão. Por isso, o componente
preparado retorna `cindop=null` e proibição de informar esse campo quando o
indicador é zero, sem copiar100302 da parcela nacional. A observação nova não
autoriza afirmar implantação remota ou vigência jurídica do leiaute.

Sete controles executados emPHP8.5 no diretório temporário do contêiner:
positivo com parcela estrangeira400,00 e classificação410027; documentos
conflitantes; alocação ausente; valor estrangeiro alterado; referência fiscal
alterada; conhecimento anterior à disponibilidade; política adulterada.
As seis recusas tiveram os motivos esperados. A execução usa o snapshot
integrado134 preservado como entrada do ensaio, sem afirmar que um snapshot
recebido substitui a avaliação fresca na futura integração.

O endpoint de calculadora citado pelo anexo não resolveu porDNS. A falha foi
preservada em `layout-followup-v1/calculator-v1/capture-failed.json`, sem resposta
remota inventada. O ensaio usa as tabelas oficiais já capturadas, com limites
locais explícitos. Recibo: `source-staging135-v1/validation.json` noD:.

Pendências:instalar após término e auditoria134, integrar ao estágio corrente,
ampliar testes nos dois runners, qualificar as regras de ocorrência completas
e montar o parDPS com dados próprios. Sete controles isolados não são uma
regressão135, decisão fiscal integrada ouDPS completa.0/8 grupos4B encerrados.
