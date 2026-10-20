# Fronteira113 - contrato proposto para baseISS por documento

Estudo em20/10/2026,enquanto a regressao112 executa com17.418 entradas
congeladas. Este documento nao instala politica ou componente,nem aceita
classificacao,base,aliquota,conversao ou imposto. Preservar os cenarios112.

## Evidencia examinada e consequencia

O bundle112 usa `iss-services-2026.09.17-1.json`. A linha
`SOFTWARE_DEVELOPMENT` identificaLC116 `01.01`/cTribNac `010101`. As duas provas
municipais do cenario inicial declaram apenas `service_list_item = 1`.
Esse campo foi contratado por97 para a familia de servicos de informatica;
nao foi contratado como classificacao fiscal granular para selecionar aliquota.
Nao promover `1` a `1.01` implicitamente,nem chama-lo de prova contraditoria.

A pesquisa113 preserva a linha historicaPJ `02660`/`1.01` naIN23/2017 e noXLS,
com anotacoes e datas. Ela nao autoriza atualizar o parametro sintetico2% para
2,9% ou associar automaticamente o codigo a todos os documentos do motor.
O codigo `02692`/`1.04` e outra linha;"software" nao torna os dois equivalentes.

106 reexecuta105/95/97 por data material e retorna resultadosISS proprios.
111 reexecuta110/107/106 e calcula preco composto,na moeda declarada. Seu
resultado desativa explicitamente baseISS,imposto,conversao e aprovacao legal.
O proximo componente deve aproveitar essas garantias,nao apagar seus limites.

## Entradas e guardas necessarias antes de implementar

1. Reexecutar111 e106 sobre os mesmos fatos correntes,provas originais,
   conhecimento e politicas exatas. Comparar os hashes frescos por documento
   que111 transporta;nao aceitar resultado106/111 previamente qualificado.
2. Vincular uma prova de classificacao por documento,contrato,unidade/linha,
   prestador,tomador,municipio e dataISS. Separar codigo nacional,subitemLC116
   e codigo municipal. Conferir fonte e cadeia temporal de cada mapeamento;
   texto descritivo ou familia1 nao substitui essa prova.
3. Vincular regime/forma do prestador e a aplicabilidade das regras de valor.
   O escopo105 atual ePJ privado,regime geral e naoSimples. Nao amplia-lo aPF
   para aplicar uma nota de tabela sem cadeia regulamentar/prova acessoria.
4. Selecionar tratamentoISS pelo resultado fresco,nao pelo papelRTC_FOREIGN
   ouRTC_DOMESTIC. Candidato local a nao incidencia nao e aprovacao juridica.
   ResultadoBR afasta somente a alegacao de exportacao examinada;nao prova
   inexistencia de imunidades,isencoes,outras exclusoes ou regras especiais.
5. Nao converter ausencia de incidencia em base0 ou imposto0. Representar
   tratamento nao aplicavel separadamente,com valor nulo e justificativa;
   prova ausente continuaACTION_REQUIRED,nao ramo positivo de nao incidencia.
6. Quando a moeda do preco nao forBRL,exigir contrato cambialISS proprio,
   fonte,direcao,unidade,data/calendario,publicacao e regra de arredondamento.
   Nao reutilizar cotacaoRTC nem multiplicar pela parcela de consumoRTC.
7. Preservar bruto,acrescimos,desconto incondicional e desconto condicionado
   separadamente. O abatimento condicionado nao reduz automaticamente preco
   tributavel. Nao subtrair novamente oISS incluido no preco nem usar o campo
   de deducao de construcao para o desconto de um servico de software.
8. Selecionar regra e parametro por vigencia e conhecimento comprovados.
   Fonte observada em20/10 nao pode ser tratada como politica conhecida em19/10.
   Hash e reenvelopamento de prova sintetica nao autenticam evidencia fiscal.

## Matriz minima da implementacao posterior

- Familia1 sem subitem/codigo municipal:pedir classificacao;nao inferir02660.
- Subitem nacional1.01 com codigo1.04/02692:recusar conflito granular,sem reparar fatos.
- Documento,linha,partes,data ou fonte trocados:recusar mesmo com novo hash coerente.
- Prova completa atual e prova revogada/supersedida:decisoes temporais distintas.
- ResultadosISS invertidos entre papeisRTC:seguir resultado fresco,nao o rotulo.
- ResultadoBR com beneficio nao examinado:nao produzir imposto por exclusao presumida.
- MoedaBRL versusUSD/EUR/AUD:nenhuma cotacao padrao,zero ou atalhoRTC.
- Desconto incondicional/condicional e acrescimo combinados:nao duplicar abatimento.
- Nao incidencia,aliquota zero,isencao e base monetaria zero:tratamentos distintos.
- Conhecimento anterior a disponibilidade da politica:recusar,sem recuar observacao.
- Fonte/parametro sintetico2%:nunca promovido a tabela municipal real.
- Todas as recusas e controles108/112 permanecem reproduziveis e imutaveis.

Essa matriz e projeto de verificacao,nao testes executados nem novo aceite.
O observador isolado `inspect_price_base_boundary113.py`,noD:da pesquisa113,
confere apenas os inputs e resultados112 capturados. Seu recibo em
`price-base-boundary-v1/price-base-boundary-observation.json` nao e reexecucao
fresca do motor nem substitui a regressao integral ou sua auditoria final.

## Pendencia real

Observacao terminal do corpus capturado:124 cenarios examinados,73 com
composicao e51 sem composicao. Os146 documentos compostos declaramBRL130,
USD12,EUR2 eAUD2. Sao moedas de preco declaradas,nao146 bases calculadas
nem prova de conversaoISS. O observador concluiu1.842 conferencias e
verificou87 hashes de execucao intactos. Nao reexecutou o motor nem
reconferiu todas as17.418 entradas;essa auditoria pertence ao coordenador112.
SHA256 do recibo: `7eb5996f7c9638bd4a920c6cf8cb5178f290e8cbfff23a27c93ae279d0c89fa0`.

Concluir cadeia normativa/aplicabilidade e prova granular antes de ativar a
baseISS. Depois implementar,exercitar casos positivos e negativos emPHP,
validar oraculos decimais,replay e integracao,com nova geracao/aceite. Os80
requisitos originais e os oito grupos4B continuam sem encerramento integral.
