# Precos110 - componente isolado de vinculo contratual

Em19/10/2026 foi implementado e verificado no D: o componente
`PartialExportContractPrices110`,sem instalar codigo,facts,perguntas,bundle
ou novos testesPest no repositorio. A regressao108 permanece independente,
com13.395 entradas congeladas;nenhuma delas foi alterada por este corte.
Os83 hashesruntime108 foram novamente conferidos. A conferencia integral
das entradas congeladas continua reservada ao termino da regressao.

Diretorio:
`D:/ProjetoSaasExports/phase4b-price110-local-preparation-20261019/`.
Relatorio:`price110-local-component-validation.json`.
Componente SHA256:
`86cbf874f1e99f6d79b7d278b3763a821f3bdabc2726c0a3613c70ee0321ccf0`.
Contrato:`LOCAL-PARTIAL-EXPORT-CONTRACT-PRICES-2026.10.19-110`.

## O que foi implementado

Qualificacao pura de dois precos contratuais declarados,por papel e referencia
original. Reexecuta107 e,por ele,106/105/95/97,antes de vincular precos a
documento,identidade,contrato,unidade economica,prestador,adquirente e operacao.
Exige contextoONEROUS explicito;nao infere preco juridico a partir de REGULAR,
do rateio de consumoRTC ou da aparencia de um identificadorDPS.

Cada documento recebe linha contratual distinta,moeda declarada e preco
positivo em string decimal canonica. DecimalValue soma exatamente os dois
precos,sem float ou arredondamento oculto. O total contratual deve coincidir
com o total da prova,mas nao recebe igualdade automatica ao brutoRTC.
A relacao com o precoRTC permanece explicitamente nao reconciliada.

Uma prova de par independente vincula valores,contexto atual e hash da
qualificacao107 fresca. Sao conferidos conteudoJSON capturado,hash do
conteudo,envelope completo,observacao,cobertura por cada dataISS e ciclo
de revogacao/superacao. Observacao nao pode anteceder as provas ligadas de
regime,prestacao,resultado ou identidade. Reuso dos identificadores dessas
provas nao substitui evidencia contratual propria. Hash local nao autentica
o contribuinte,contrato ou governo.

Escopo local de entrada monetaria:BRL/USD/EUR/AUD,duas casas e ate15 digitos
inteiros. Moedas mistas,demais moedas ou precisao fora desse dominio exigem
outro contrato;nao recebem conversao ou cotacao padrao. Aceitar uma stringEUR
ouAUD como preco declarado nao qualifica sua origem cambial no motorRTC.

## Evidencia executada

- 160 casos/3.296 checks nativos:24 pares vinculados localmente,101INVALID e
35ACTION_REQUIRED;repeticao deterministica e imutabilidade de entradas.
- 72 controles independentes com PythonDecimal:23 positivos com proporcao
de preco diferente do percentualRTC e quatro com total diferente do brutoRTC.
Inclui valores acima da precisao inteira segura de float,sem alterar fatosRTC.
- 12 variacoes positivas107 preservam datasISS anteriores/posteriores/distintas,
pagamentos independentes,etapa mensal sem entrega final e offsets civis.
- PHPStan escopado ao componente e Pint nos tres arquivosPHP aprovados.
Native/cases-v3 e quality-v3 sao os checks finais desta preparacao.

Os154 primeiros casos e resultados foram preservados exatamente na bateria
final160. A revisao acrescentou exigencia explicita de onerosidade e seis
recusas,sem mudar expectativas anteriores. Fixtures usam conhecimento futuro
sintetico18:00UTC e rederivam hashes106/107/prova de mapeamento;isso nao e
captura real futura. Datas,identidades,partes,provas materiais/resultados e
fatos comerciais originais permanecem iguais nos positivos.

Primeira falhaPint de estilo em dois arquivos e fontes anteriores preservadas.
Primeira tentativa de formatacao falhou porque docker-cp criou tres arquivos
temporarios com dono root. Foi alterado somente o dono desses tres arquivos
em `/tmp/phase4b-price110-v1`,sem alterar permissoes do projeto. Formatacao
escopada posterior aprovada;resultados nativos iguais apos formatacao.

## Limites e continuidade

Pesquisa109 e seu recibo sao fundamentos de escopo,nao aprovacao de politica
fiscal. Availability110 e piso tecnico do prototipo,nao vigencia legal.
Este componente nao calcula baseISS,tributo,conversao,vServ ou outro campoXML;
nao reconcilia precoISS comRTC e nao implementa todos os descontos/acrescimos,
operacoes combinadas,cancelamento ou substituicao. Preco contratual ligado
localmente nao equivale a preco juridicoISS qualificado.

Permanecem falsos:iss_legal_price_qualified,iss_base_calculated,
currency_code_materially_qualified,documentary_currency_conversion_qualified,
per_document_tax_amount_calculated,legal_applicability_approved,
government_evidence_authenticated,xml_generated,document_obligation_complete,
posting_authorized eproduction_ready.

Proximo trabalho:concluir aplicabilidade/preco juridico por documento e fonte
cambial apropriada;compor conversao,base/imposto e parXML sem copiar a cotacao
ou rateioRTC aoISS. Integracao110 somente em versao propria apos aceite108,
com fatos/perguntas,trace,corpus e verificacao dos contratos de transporte.
Os oito grupos4B continuam parciais;nao substitui auditoria dos80 requisitos,
regressao108 ou aprovacao de producao.
