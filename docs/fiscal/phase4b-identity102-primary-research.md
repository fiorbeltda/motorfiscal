# Identidade102 - fontes para os dois documentos da exportacao parcial

Pesquisa iniciada16/10/2026 apos o aceite101. Nenhum componente de identidade102
foi instalado no motor. Aceitacao101 permanece preservada e nao implica que as
referencias livres dos documentos historicos sejam IDs fiscais qualificados.

## Fontes capturadas

Diretorio exclusivo:
`D:/ProjetoSaasExports/phase4b-identity102-primary-preparation-20261016`.

Capturas com validacaoTLS do Windows habilitada:manualAPI,portais de producao e
homologacao,ZIP XSD de homologacao e os dois AnexosI oficiais. URLs,instantes,
hashes e ambiente sao preservados. Publicacao/vigencia material nao demonstradas
continuam nulas. Capturar documentacao nao prova implantacao remota.

O manual recapturado tem SHA
`ac2f36e34ff565cc36d67c5d67415c33cc09f27b2dea006e8da9bcd2ddce5581`,
igual ao preservado. Seis paginas extraidas/lidas;pagina fisica4 renderizada
e inspecionada. O nome do arquivo indica v1.2/out2025,enquanto o historico
interno informa1.0/17-03-2025. Preservar ambos,nunca corrigir a fonte.

O ZIP homologacao conserva SHA
`6c7e0510d3ecff4454f291f4e10b742d27a4818f23aab181494f96d0ea79f3dc`.
Tipos extraidos de membros exatos,com hashes dos membros,sem editar os XSD.

AnexoI homologacao:
`2ae2ac9f91efa9b64f0c9ed97acaf18bb7513b090fae39ba5bda59187e64d9e9`.
AnexoI producao:
`de5bc492959eadc8bfa7540e16939995924f2188f743648eaf84d3b31e9eeb7c`.
Os arquivos sao distintos. Importacao somente leitura pelo ArtifactTool dos
valores de todas as cinco abas em cada arquivo;sem exportacao ou alteracao.

## Regras observadas,nao uma autorizacao fiscal

ManualAPI,pagina4:identificador usa municipio7,tipo de inscricao1,inscricao14,
serie5 e numero15. O AnexoI,aba `LEIAUTE DPS_NFS-e `,linha102,e o tipo
`TSIdDPS` incluem literalDPS,formando45 caracteres. Tipo1 corresponde aoCPF,
tipo2 aoCNPJ. IDs de DPS nao sao chaves de acesso da NFS-e.

AnexoI,linha106:series00001-49999 para aplicativo proprio;50000-69999 para
movel;70000-79999 paraWeb;80000-89999 para transcricao manualWeb. Linha107:
numero1-999999999999999. Linha109 distingue prestador,tomador e intermediario.

XSD homologacao:`TSSerieDPS` permite1-5 digitos ate89999,mas nao seleciona a
faixa correta do canal. A regraE0010,aba `RN DPS_NFS-e`,linha147,recusa serie
fora da faixa do emissor. Portanto,validacao estrutural anterior nao constitui
prova de serie de aplicativo proprio. Nao modificar guardas historicas sem
contrato novo e corpus;nao retroagir a mudanca a snapshots antigos.

E0004,linha142,exige ID recomposto dos campos correspondentes. E0014,linha148,
verifica se o conjunto ja foi usado para uma NFS-e anterior. Duas series
escritas como1 e00001 podem gerar o mesmo componente doID;comparar strings
brutas nao demonstra que os documentos irmaos sao distintos.

O ambiente nao faz parte dessa concatenacao. Nao adicionar digito de ambiente
ao ID oficial. A selecao do ambiente continua uma dimensao propria do contrato.

E0015,linha150,relaciona competencia e emissao. Linhas151-156 condicionam datas
a convenio,cadastro e autorizacao. O manual tambem explicita dependencia de
parametros municipais,CNC,CNPJ/CPF eSimples. Formato correto nao prova cadastro,
convenio ativo,autorizacao ou inexistencia de documento anterior.

O XSD observado admite caracteresA-Z no componenteCNPJ doID,enquanto o texto
doAnexo preservado descreve posicoes numericas. O novo escopo deve declarar
expressamente o suporte efetivamente qualificado;nao inferir suporte integral
aCNPJ alfanumerico ouCPF a partir do pattern doID isolado.

## Contrato seguinte necessario

Preparar qualificador de composicao e vinculo local dos dois IDs,com fonte,
instante,ambiente,canal,tipo de emitente,municipio,inscricao,serie e numero
explicitos. Para a rota atual,prestadorPJ brasileiro e aplicativo proprio
podem constituir escopo inicial declarado,sem descartar exigencias restantes.

Manter separadas a referencia original do documento na operacao e sua futura
identidadeDPS. Exigir prova local de mapeamento que vincule operacao,papel,
referencia original,prestador e componentesDPS. Nao renomear silenciosamente
as referencias dentro de provas95/96/97/98 ou snapshots aceitos.

Recusar papeis ausentes/duplicados,troca entre irmaos,IDs canonicos iguais,
prestador/municipio divergentes,canal/ambiente nao qualificados,serie fora
da faixa,numero invalido,composicao adulterada e prova futura. A ordem dos
papeis pode ser irrelevante para o vinculo semantico,sem perder o hash bruto.

Composicao/vinculo local nao pode retornar autenticacao cadastral,autorizacao
de emissao,unicidade remota,obrigacao documental completa,conversao,base,
imposto por documento,XML,assinatura,transmissao ou producao. Esses gates
continuam obrigatorios no objetivo integral.

AuditoriaV2 concluida:seis capturas,58 linhas cruzadas por segunda biblioteca,
9.272 entradas e78 hashes101 intactos. Relatorio:
`D:/ProjetoSaasExports/phase4b-identity102-primary-preparation-20261016/identity102-research-validation-v2.json`.
Primeira tentativa preservada:colunaA de numeracao usaROW;comparacao inicial
opunha formula ao valor101. A leitura corrigida compara valores em ambas as
bibliotecas,sem alterar ou recalcular a fonte. Metadados de linhas ocultas sao
lidos independentemente. Nao e nova execucao fiscal nem novo testePest.

Pesquisa de identidade concluida neste alcance. Proximo:confrontar tambem o
manual municipal original exigido pelo plano e preparar o contrato/componente
de vinculo dos dois documentos com corpus nativo,antes da integracao versionada.
Nao contabilizar prototipo,casos nativos,integracao ou aceitacao fiscal102
antes de sua existencia e verificacao. Campos de data material,conversao,
base eXML continuam pendentes no objetivo integral.
