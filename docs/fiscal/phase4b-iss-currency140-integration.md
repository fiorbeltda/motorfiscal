# 140 — moeda do preço documental do ISS separada do RTC

Aceite escopado em27/10/2026: **56 testes/154 asserções**,PHPStan global sem
erros e Pint dos seis arquivosPHP alterados aprovado. DoisCLI reais reproduziram
o resultado e dois replays com o conhecimento armazenado conferiram. O resultado
completo139 permaneceu idêntico;5.940 entradas congeladas e134 hashes de runtime
foram auditados. Não foi executada uma suíte integral140.

Recibo imutável:
`storage/app/phase4b-currency140-development-20261027/currency140-integration-scoped-validation.json`,
SHA256 `257490e0dcef384a140ec367eff33922ae14f768481977a2a69f74d951138f18`.
Matriz80 atual: `requirements80-current-matrix.json` na mesma pasta.
Logs e snapshots em
`D:/ProjetoSaasExports/phase4b-document-currency140-20261027-v1`.
Revisão da íntegra e imagens da consulta em `source-scope-review-v1.json`.

A auditoriaPython leu os números doJSON originalBCB comDecimal,calculou
5199,10 paraISS e5179,50 para a memóriaRTC e conferiu o imposto259,96 ao
percentual sintético5%,sem usar ponto flutuante nem a saídaPHP como referência
aritmética. O seletor de moeda aceita somente o catálogo/hash/versão selecionados.
O valor de regime não aplicável a prestador estrangeiro foi limitado a fornecedor
estrangeiro e serviço de origem estrangeira;contradições param antes do cálculo.

O CLI existente retorna sucesso técnico quando executa uma decisão não final.
O ensaio negativo confirmou `ACTION_REQUIRED`,pergunta de moeda e decisão vazia,
preservando o snapshot da pendência. Esse código de saída não foi confundido com
aceitação fiscal. Não foi construídoXML definitivo de importação ouNFTS.

Histórico de desenvolvimento preservado no recibo:ajustes na preparação do
manifesto/allowlist,remoção de um acesso nullsafe redundante e correção de sintaxe
do primeiro script nativo,que falhou antes de executar o motor. O primeiro processo
nativo ficou terminal comexit255;uma captura nova e exclusiva,v2,passou apóslint.
Pint terminouexit0;um erro do exibidorCP1252 ocorreu somente depois de gravar
o recibo e o log íntegros,e não foi tratado como falha nem repetição doPint.

Em desenvolvimento em27/10/2026,sobre o checkpoint139 publicado. Os132 hashes
do runtime139 foram conferidos antes das alterações. A revisão109 já havia lido
as consultas municipais e suas limitações;140 reutilizou essa análise e conferiu
novas capturas primárias,sem reapresentá-la como descoberta nova.

Foram preservados os bytes de quatro capturas em
`D:/ProjetoSaasExports/phase4b-document-currency140-20261027-v1/primary-v1`:
consultaSF/DEJUG08/2015,CTN consolidado,LeiSP13701 consolidada ePTAXUSD
de24–25/09/2026. O PDF da consulta tem o mesmo hash da captura109:
`05f655b02feaa3a2647e00a164feb3e9d44d65712ec1cf1700381cc35bd9edbe`.
As duas páginas foram conferidas integralmente,com reaproveitamento das imagens
renderizadas após verificar igualdade dos bytes. Datas de observação não foram
transformadas em publicação ou início de vigência.

A [consulta08/2015](https://drive.prefeitura.sp.gov.br/cidade/upload/SC008-2015_1456839085.pdf)
examina consultoria17.01 tomada do exterior,com evento de conclusão por entrega
de relatório no caso concreto,distinto de pagamento e emissão. Ela não é uma
política geral de exportação,software continuado ou todos os contribuintes.
O [CTN,art143](https://www.planalto.gov.br/ccivil_03/leis/l5172compilado.htm)
preserva a ressalva de disposição legal contrária. Seu texto isolado não define
toda a políticaPTAX ou o calendário de dias sem fechamento.

## Integração e histórico dos ensaios

O manifesto140 seleciona expressamente o catálogo e contrato de moedaISS.
O núcleo recebe o preço original,a moeda,o documento,as partes,o evento e sua
data,além da captura que vincula a qualificação local. O preço convertido vai
apenas para uma cópia dos fatos usada pelo calculadorISS. Os fatos originais
continuam nos motoresRTC e federal;projeção parcialDPS usa o preço calculadoISS.
Os manifestos139 e anteriores não selecionam o componente novo.

O primeiro ensaio integrado passou22 testes/66 asserções. ParaUSD1000,00 no
dia25/09,o fechamento de venda é5,19910 e o preçoISS fica5199,10. O conversorRTC
existente escolhe o fechamento anterior5,17950 nesse cenário,que produz5179,50.
São políticas e memórias distintas. O pagamento declarado em20/10 não escolhe
a data de câmbioISS. A alíquota5% do ensaio é um parâmetro municipal sintético,
explicitamente identificado no catálogo;não foi homologada como regra municipal.

O contrato inicial é um caso local pré-qualificado de importação17.01,conclusão
por relatório,municípioSP e preçoBRL/USD. Prestador estrangeiro recebe uma opção
própria de regime não aplicável,no novo dicionário de fatos,sem forçar regime
tributário brasileiro. Outros serviços,eventos continuados,moedas sem política
qualificada e dias sem fechamento exato retornam ação requerida;essa cobertura
restante continua pendente no escopo original.

O componente exige preço/documento/partes/evento e pergunta os fatos materiais
ausentes. Recusa contradições entre identidades fornecidas paraISS eRTC,cotações
de compra ou de outro dia,alterações de preço,provas revogadas/supersedidas ou
futuras,JSON numérico inadequado,paginação e fechamentos ambíguos. Não converte
um hash de fixture em autenticidade bancária,cadastral ou aprovação legal.

Validação dirigida ampliada,CLI e replays aprovados no aceite escopado acima.
O objetivo original continua com80 requisitos e0/8 grupos encerrados. Não há
alegação deXML definitivo,NFTS emitida,conclusão de importações/exportações,
aprovação jurídica geral ou liberação de produção.
