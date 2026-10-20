# Auditoria115 - incidencia/local e referencia geografica

Corte20/10/2026 as09:00. Foram examinados individualmente os12 itens4B.2.1-12.
Somados aos8 itens temporais do corte114,sao20 IDs distintos revisados,nao20
requisitos aprovados.60 IDs ainda nao revisados nesses dois cortes;revisoes
anteriores de escopo diferente permanecem preservadas. Nenhum grupo4B encerrado.

## Prova nativa e diferencas relevantes

31 casos sinteticos em memoria:30 carregam e executam emRTC e no fluxo integrado,
total60 avaliacoes;um codigo malformado e recusado no carregamento tipado.
87 hashes de execucao do estado pos114 intactos e fontes dos cenarios conferidas.
Nao sao31 testes permanentes novos,nem uma suite integral.

- SaaS oneroso/B2C,adquirente estrangeiro com destinatarioBR,educacao presencial
  e remota e domicilio nao cadastrado corroborado produzem resultados positivos.
- Consumo estrangeiro nao provado,destinatarioBR em exportacao,destino ausente,
  corroboracao insuficiente/conflitante e B2B/PF contraditorios sao distinguidos.
- Importacao com contribuinte errado eINVALID noRTC,mas o fluxo integrado
  encontra antes uma pendenciaISS. Nao forcar igualdade de razoes entre ramos.
- Antigasfixtures de uso pessoal e exportacao parcial nao trazem todas as
  provas exigidas depois:suas recusas atuais nao demonstram automaticamente
  uma lacuna do novo ramo. Doacao permanece com obrigacao documental pendente.
- Plataforma:contrato e quatro testes existentes lidos;nenhum corpus especifico
  de plataforma foi reexecutado neste corte. Nao confundir leitura com execucao.

## Lacuna comprovada e preparacao corretiva

Os dois executores aceitamCampinas3509502 comUF_RS,UF_XX e3599999 como destino
numerico. O ultimo nao consta do retratoIBGE capturado;isso nao prova que nunca
existiu na historia. A tipagem exige forma7digitos/2letras,mas nao coerencia
referencial. Provas presenciais conferem igualdade das declaracoes entre si,
nao a existencia oficial do codigo ou suaUF. O problema deve ser tratado nos
caminhos ordinario,importacao,doacao e parcela domestica da exportacao,nao
somente no bloco principal de destino.

Tres capturas oficiais bem-sucedidas:API deUFs,API de municipios e documentacao
daAPI. A pagina explicativa de codigos recusou a captura por protecao web;
falha/stdout/stderr preservados,sem bypass ou contagem como fonte valida.

A normalizacao preserva27UFs e5.571 registros territoriais.5.570 registros
tiveram as duas hierarquias comparadas;o registro5101837 temmicrorregiao nula
e foi mantido pela hierarquia de regiao imediata/intermediaria da propriaAPI.
Brasilia eFernando deNoronha permanecem como registros daAPI:presenca na lista
nao determina natureza juridica municipal ou competencia tributaria.

Fonte observada mais tarde:20/10/2026,11:46:20.034562UTC. Nao foi criada
vigencia efetiva a partir desse horario,nome do arquivo,numero de linhas ouURL.
Hash brutoUF `7ca1368dea3af83cba1af84ae8a7e88f1173c97586831d086cc8b3c1ba9c6596`;
hash bruto territorios `86ecdccdf97d72e7e5e46f0854cfcea8bacc28154cc1bc4ed0e6110e3a6c9c02`.

## Componente isolado,nao instalado

`ObservedGeographicReference115` vincula politica,URLs,hashes brutos,observacoes
exatas e mapas completos;recusa codigo/UF contraditorios,fonte alterada e
conhecimento anterior a observacao. Ausencia do retrato retornaACTION_REQUIRED,
nao uma afirmacao de inexistencia historica. Nao normaliza zeros/caixa/espacos.
Retorno positivo significa somente consistencia com referencia observada.
Todos os flags de destino fiscal,domicilio,estabelecimento,natureza municipal,
vigencia historica,aliquota,aprovacao eproducao continuam falsos.

47 casos isolados/423 conferencias de limites falsos aprovados,maisPint e
PHPStan do componente.27UFs,Campinas/SP,novo registroMT,DF/PE,hash refeito apos
troca de mapa,registro removido,fonte alterada,aprovacao injetada e fronteira
de1microssegundo foram verificados. A primeira recusa de estilo foi preservada;
v2 formata somente copia temporaria propria e reexecuta os checks. Nenhuma
classe/recurso/bundle do checkout alterado por115;nao ha aceite integrado115.

## Requisitos e proximo desenvolvimento

O relatorio individual associa a cada4B.2.1-12 evidencia positiva,limites e
proximo trabalho.4B.2.4/5 possuem contraexemplos reais de coerencia referencial;
os demais continuam parciais em familias/localidades,prova material e documentos.
Nao basta instalar a lista como decisao fiscal automatica.

Proximo corte:politica versionada que usa a referencia como validacao auxiliar,
define o tratamento explicito da data da operacao e da prova historica e a liga
a todos os ramos consumidores de destino. Preservar os replays dos bundles
anteriores;nao usar um opt-in omitido para declarar a lacuna atual resolvida.
Depois integrar e testar negativos/positivos,corpus/replay e regressao no
fechamento coerente. Continuam base/cambioISS,documentos e restante dos80 itens.

Evidencias sob `D:/ProjetoSaasExports/phase4b-requirements115-incidence-audit-20261020-v1/`:

- `native-probe-v1/observations.json` e `execution.json`.
- `geography-captures-v1/primary-source-observation.json`.
- `geography-normalization-v1/normalization-validation.json`.
- `geographic-checks-v2/geographic-reference-validation.json`.
- `requirements-review-v1/requirements115-incidence-review.json`.

112 continua o ultimo aceite integral. A unica mudanca de execucao posterior
a esse aceite e a correcao temporal114 ja documentada;115 ainda e preparacao.
Fases3/4A encerradas emSandbox,4B parcial,5-7 planejadas eproducao bloqueada.
