# ISS106 - composicao isolada de data material e resultado por documento

Diretorio de preparacao:
`D:/ProjetoSaasExports/phase4b-iss-date106-result-composition-20261019/`.
Componente `PartialExportIssDatedResults106.php`,fixture `dated106-cases-v2.json`
e probe nativo `probe106.php`. Nenhuma instalacao no motor104.

O106 executa105 novamente com fatos/provas atuais;nao aceita um objeto de
aprovacao105 fornecido pelo chamador. Para cada papel,confronta as partes,
operacao,transacao e referencia original com seu contexto95. supply_on deve
ser a dataISS daquele documento,nao rtc.operation_date. Em seguida executa
95 e97 inalterados,com politicas exatas,validade,conhecimento e ciclo de vida
proprios. Nao passa por96/98 nem modifica fatosRTC para satisfaze-los.

Prova nova de vinculo do mesmo evento exige contrato/unidade,data/instante,
hashes da prestacao105 e origem capturada,contexto/prova95 e prova97,partes,
papel e referencias. Conteudo capturado e hash do envelope sao confrontados;
uma prova trocada,resignada mas ligada a outra unidade ou documento,e
recusada. Isso demonstra consistencia local,nao autenticidade economica.
Modo SINGLE/CONTINUING_MONTHLY e identificador da etapa97 devem corresponder
ao evento105. PapéisRTC nao determinam resultadoISS estrangeiro/brasileiro.

## Verificacao nativa

174 casos/3.586 verificacoes:18 composicoes locais positivas. Todos os casos
de recusa105 continuam exercitados antes dos resultados. Positivos incluem
ISS anterior/posterior aoRTC,datas diferentes por papel,moedasBRL/USD,
pagamentos independentes,etapa mensal sem entrega final,fronteira civil,
offsets equivalentes e ordem dos registros. Resultado no exterior com vinculo
de software a pessoaBR conserva a excecao municipal;resultadoBR nao recebe
tributacao positiva por inferencia. Nao selecionar tratamentoISS pelo papelRTC.

Quatro casos adicionais confrontam disponibilidade106:um microssegundo antes,
no instante,offset equivalente e tentativa de retroagir a politica. Nenhuma
data de publicacao/observacao e promovida a vigencia juridica. A disponibilidade
do contrato local e um gate diferente dos efeitos das normas.

Primeira fixture170 e saidas preservadas. PHPStanV1 nao incluiu o105 preparado
nos caminhos da analise e recusou classe desconhecida;V2/V3 analisam os tres
arquivos explicitamente. PintV1 recusou estilo;fonte anterior preservada e
formatacao aplicada apenas ao106 no /tmp do conteiner e copiada ao D:.
Saida nativaV2/V3 byte-identica apos formatar. PHPStan/Pint finais aprovados.

Recibo `dated106-local-composition-validation.json` confronta os174 resultados,
hashes dos arquivos/checks,candidatos/recusas e datas de cada componente.
O runtime104 e os originais105 nao sao alterados;auditoria confere79 hashes
e11.133 entradas da regressao104.

## Limites e proximo trabalho

Preparacao106 nao e aceite de integracao ou pesquisa completa. Nao houve
fato/pergunta,bundle,testePest permanente,regressao106,novo XML ou alteracao
do calculoRTC/ISS. Campos de autenticacao,aplicabilidade,qualificacao material
legal,competenciaDPS,identidade fiscal,imposto,posting e producao sao falsos.

Permanece necessario versionar a identidade/competenciaDPS para as datas de
cada documento,com todas as validacoes103 preservadas,sem fabricar duas
copias dos fatosRTC com datas diferentes. Depois,corpus composto em cenarios
nativos,testes permanentes,integracao por politica propria e regressao.
Aplicabilidade municipal/historica e cobertura de regimes ainda precisam de
pesquisa. Cambio,base/imposto e parXML sao etapas distintas,nao resolvidas
pela composicao dos tres qualificadores. A auditoria dos80 requisitos continua
necessaria;nenhum grupo4B ou fase foi encerrado por esses checks.
