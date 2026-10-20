# DPS107 - identidade e competencia por dataISS,preparacao isolada

Diretorio:
`D:/ProjetoSaasExports/phase4b-identity107-dated-preparation-20261019/`.
Componente `PartialExportDatedDpsIdentity107.php`,manifesto
`identity107-derivation.json`,fixture `identity107-cases-v2.json` e recibo
`identity107-local-dated-validation.json`. Nenhuma instalacao no runtime104.

## Derivacao e comportamento

O107 e uma classe nova derivada do103 com24 transformacoes explicitas.
O original103 foi copiado byte a byte antes da derivacao;manifesto registra
fonte/hash e cada substituicao exata. Politica de fontes de identidade103
permanece vinculada,com disponibilidade do novo contrato107 separada da
observacao das fontes e sem promover datas a efeitos juridicos.

A composicao106 e recalculada com fatos e provas atuais,antes da identidade;
nao recebe objeto aprovado do chamador. Nao altera rtc.operation_date nem
cria duas copias de fatosRTC para satisfazer o103. Cada competenciaDPS deve
corresponder a dataISS daquele papel/documento no106. Emissao e confrontada
separadamente com a data civil America/Sao_Paulo e com o instante material:
mesmo dia nao permite emissao anterior a prestacao/etapa comprovada.

Continuam presentes as guardas103 de escopo,referencias originais,dois papeis,
CNPJ numerico/municipio,modeloDPS,ambiente2,canal proprio,tpEmit1,tipo2,
serie1..49999,numero1..15digitos sem zero inicial,ID canonico45caracteres,
dois IDs distintos e instantes estritos. A disponibilidade103 segue sendo
o piso das fontes historicas para emissao;disponibilidade107 e piso do novo
contrato/vinculo,nao motivo para reescrever uma emissao historica.

Nova prova107 inclui hashes do par106 e do documento datado inteiro,alem de
identidade,partes,transacao,papel e referencias. Observacao do mapeamento deve
ser conhecida,nao anteceder emissao,disponibilidade107 ou observacoes das
provas materiais,nacionais e municipais vinculadas. Evidencias de mapeamento
duplicadas entre papeis sao recusadas. Hash local nao autentica governo.

## Evidencias nativas e qualidade

255 casos novos107 e128 controles103 inalterados,5.708 verificacoes totais.
107:26 pares locais positivos,106ACTION_REQUIRED e123INVALID. Positivos
incluem competencias diferentes da dataRTC,diferentes por papel,series
normalizadas/limites,numero maximo,offsets equivalentes e disponibilidade.
As seis extensoesV2 conferem emissao no evento,um microssegundo antes,
fronteira civil e mapeamento anterior a observacao nacional/municipal.

Os128 controles usam as mesmas entradas/politicas historicas103,sem migrar
expectativas para107. Nao somar esses controles aos255 como se fossem todos
casos novos. Seed249 e fixture/logV1 preservados;V2 acrescenta seis casos.
Fixture final e gerada emPHP antes da execucao do probe;provas107 novas sao
sinteticas e sua preparacao nao altera o resultado esperado durante o teste.

PHPStan de cinco arquivos (105,106,107,builder eprobe) ePint de tres arquivos
novos aprovados. Primeiro Pint recusou estilo;fonte anterior e log preservados.
Formatacao apenas no107,salva no D:,manteve saida nativaV2/V3 byte-identica.
Auditoria confronta a derivacao,objetos completos,hashes,competencias,emissao,
provas,128 controles e integridade das11.133 entradas/79 hashes104.

## Limites e continuidade

dated_identity_pair_locally_bound sinaliza somente composicao/vinculo local.
dps_competence_legally_approved,legal_applicability_approved,identidade fiscal
integral,autenticacao,autorizacao,unicidade remota,data material juridicamente
qualificada,cambio/imposto por documento,XML,posting eproducao ficam falsos.
Nao ha novo bundle,fato/pergunta,testePest permanente ou regressao107.

Pesquisa de aplicabilidade municipal/historica,cobertura de regimes e ciclo
de cancelamento/substituicao nao foram concluidos.107 e preparacao para a
integracao temporal/documental,nunca fechamento de toda a obrigacao.
Proximos:concluir essa pesquisa,exercitar corpus nativo do motor preservando
calculos/recusas a montante,integrar sob politica propria e rodar regressao.
Conversao,base/imposto e parXML ainda exigem fontes/contratos/corpus proprios.
Fase4B conserva os80 requisitos e oito grupos parciais;producao bloqueada.
