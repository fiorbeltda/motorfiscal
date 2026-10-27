# 141 — consultoria importada e conciliação monetária documental

Integrado e validado localmente em27/10/2026: **74 testes/150 asserções**,PHPStan
global0 ePint4. OCLI real foi executado duas vezes com resultado idêntico e
replay completo do snapshot;três execuções adicionais recusaram snapshot
adulterado,evidência futura e preço divergente recapturado. Auditoria conferiu
5.948 entradas congeladas,136 hashes de runtime e os80 requisitos originais.
Os134 hashes anteriores permanecem idênticos. Não houve regressão integral141.

Recibo:
`storage/app/phase4b-import141-development-20261027/import141-integration-scoped-validation.json`,
SHA256 `ca4e3050dd4f61033dffa964acb4e043e80ac876ec8742dd636dfe6809d63307`.
Matriz80 na mesma pasta. Logs,snapshots,declaração normalizada e fontes em
`D:/ProjetoSaasExports/phase4b-import-document141-20261027-v1`.

## Comportamento e limites

O catálogo141 acrescenta uma regra explícita para consultoria estratégica17.01,
NBS1.1401.11.00,importação digital ordinária,tratamentoSTANDARD e domicílio
do adquirente brasileiro em2026. As98 regras anteriores e todas as outras
seções do catálogo134 permanecem iguais. Não se transforma consultoria em
software,nem se deduz tratamento tributário da correlaçãoAnexoVIII.

A [LC214 consolidada](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html)
foi recapturada e conferida nos arts11 e64,considerando as alteraçõesLC227.
Seus bytes coincidem com a captura municipal/legal já preservada no projeto.
A [referência oficial de indicadores](https://dfe-portal.svrs.rs.gov.br/CFF/TabelaIndicadoresDosLocaisDeOperacao)
foi recapturada para conferir100301. A classificação é uma regra interpretativa
local delimitada,com revisãoSandbox;presença em tabela oficial não autoriza
benefícios,alíquotas,créditos ou aplicação jurídica geral.

O comando `fiscal:import:reconcile-document-money snapshot invoice evidence`
exige conhecimento141 imutável e replay integral. A fonte é uma **declaração
normalizada explícita**,emJSON canônico,com uma linha cobrindo a fatura inteira.
Seus campos são extraídos desses bytes e vinculados a documento,partes,NBS,
serviço,eventos,data,preço e moeda do snapshot. Não é um leitor arbitrário dePDFs
ou faturas de fornecedores,nem uma autenticação da fonte. Bytes canônicos evitam
chaves duplicadas e tipos numéricos ambíguos. Evidência possuiid,SHA256 e data
explícitos,que devem estar disponíveis no conhecimento utilizado.

Os impostos legados são valores declarados pré-qualificados,com prova por linha;
o contrato exige exatamenteISS/PIS/COFINS e conferência com os ajustesRTC.
Não calculaPIS/Cofins-importação nem cria isenções para fazer os números fecharem.
No cenário sintético,USD1000 geraISS5199,10 eRTC5179,50. A diferença−19,60,
junto das exclusõesISS259,96/PIS85,46/COFINS393,64,produz baseRTC4440,44;
nominaisIBS4,44/CBS39,96. DecimalPython calculou esses valores independentemente
com os números originaisBCB. A alíquotaISS5% e o livro de impostos do ensaio
são sintéticos,sem aprovação jurídica ou cálculo novo de tributos legados.

O motor continua `ACTION_REQUIRED/RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN`.
A conciliação local não promove essa decisão nem geraXML. Fonte,memórias e
declaração conflitantes,provas futuras e traços duplicados param com pendência.

## Leiaute e próximo trabalho

A [NT0091.01](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc/nota-tecnica-009-se-cgnfs-e-v-1-01.pdf)
foi recapturada comSHA256cc86c9402e317e84eb3a9f48bbd5e0b8e3dcd84f2645700190c84d45bf1ea58e.
As páginas16–18 foram extraídas,renderizadas e conferidas visualmente noD:.
Campos de fatura e contrato pertencem acomExt,e o ajuste cambial pertence
avalores/vAjusteBC;ele altera a baseRTC sem substituirvServ. A fórmula2026
também consideraISS/PIS/COFINS,não apenas o delta cambial.

OZIP20/10 recapturado do portal restrito tem os mesmos bytes oficiais usados
anteriormente:25fdcecc651a67a216496ab10d7566631e6ed10f721e37d62c7845dadb0ea267.
A inspeção de todos osXSD encontrou352 nomes de elementos;nenhum dos quatro
camposnFatura,vFatura,nContCambio,vAjusteBCIBSCBSComExt está declarado.
`schema-coverage-v1.json` preserva o inventário. Não se altera oXSD oficial
para fazer um candidato passar. Modelo documental versionado,facetas e
compatibilidade permanecem trabalho seguinte. A tabelaNT009 mostra identificador
de fatura numérico1–20;um identificador alfanumérico do cenário141 não deve
ser silenciosamente reescrito para emissão.

Histórico:uma recapturaPlanalto falhou por conexão e foi substituída pela fonte
primária consolidadaCâmara. Metadados iniciais herdaram predecessor139/controles140;
foram corrigidos para predecessor140/controles141/fontes vinculadas. Evidências
iniciais foram preservadas;native-v2 e testesv3 cobrem os metadados finais.

Documento completo,demais formatos de entrada,serviços,eventos,moedas e perfis
de importação/exportação continuam pendentes. Escopo original80 mantido,
0/8 grupos encerrados,produçãofalse. Desenvolvimento permanece autorizado.
