# ISS105 - contrato material proposto,nao implementado

Este desenho responde aos requisitos originais4B.1.5/6,4B.7.1 e4B.8.10 e a
limite reproduzido no runtime104. Nao e aceite de pesquisa completa,de
componente ou de integracao. Nenhum novo fato/pergunta foi instalado.
Nao reduzir o objetivo integral da4B a exportacao parcial ou aos casos abaixo.

Atualizacao posterior:qualificador isolado implementado no D: e verificado
em115 casos/2.007 checks;ver `phase4b-iss-date105-local-component.md`.
Este desenho preserva o contrato de origem;as etapas de aplicabilidade,
composicao com resultado/identidade e integracao permanecem pendentes.

## Dimensoes independentes

Manter data relevanteRTC ja avaliada,data materialISS de cada prestacao,
competencia declaradaDPS,data/instante de emissao,pagamentos e conhecimento
separados. Nenhuma delas e default para outra. Se duas datas coincidirem,
exigir a prova correspondente,a coincidencia nao a substitui.

O diagnostico105 mostrou que96 compara supply_on com onRTC e103 compara
competence_date com rtc.operation_date. Portanto uma nova versao nao podera
apenas anexar um campo a cadeia96/98/103 e afirmar independencia temporal.
Precisa qualificar as datas antes desses vinculos ou introduzir vinculos
versionados que consumam datas por documento. Manter predecessores intactos.

## Contrato de entrada a implementar

- Escopo explicito:municipio do estabelecimento,perfil/regimeISS e prova do
  enquadramento com periodo e ciclo de vida. REGULAR RTC ou LUCRO_REAL nao
  demonstram por si regimeISS ad valorem. Regime especial,sociedade profissional,
  Simples,imunidade ou isencao exigem tratamento proprio,nao falso candidato.
- Dois registros identificados pelos papeis RTC_FOREIGN/RTC_DOMESTIC e pelas
  referencias originais,com operacao,transacao,prestador,adquirente,destinatario,
  contrato de servico e unidade/etapa economica. Nao renomear documentos para
  fazer provas antigas corresponderem a novas datas.
- Modalidade explicita de prestacao:evento pontual comprovado ou etapa mensal
  continuada. Registrar intervalo realizado,inicio/fim,instante material e
  fuso civil aplicavel. Uma etapa incompleta nao recebe competencia automatica;
  periodos que cruzem meses exigem segmentacao comprovada,sem deslocar tudo
  para o ultimo dia. Entrega final e cumprimento da etapa sao eventos distintos.
- Prova material independente:tipo proprio,evidence_id,referencia do conteudo
  de origem,hash do conteudo capturado,periodo de cobertura,observed_at e
  estado de revogacao/superacao;valor vinculado aos identificadores e ao evento
  efetivamente realizado. Uma declaracao booleana ou hash autoassinado nao
  autentica realidade economica ou governo.
- Datas estritas ISO,instantes com offset valido ate14h e microssegundos
  preservados. Fuso America/Sao_Paulo para as fronteiras municipais;instante
  anterior/posterior deve ser comparado como instante,nao por ordenacao textual
  de offsets. Data futura,inexistente ou prova observada depois do conhecimento
  impedem qualificacao. Nao inventar uma meia-noite para uma data sem horario.
- CompetenciaDPS explicitamente confrontada com a data materialISS qualificada
  pela politica documental apropriada;emissao deve ser confrontada separadamente.
  Pagamento antecipado ou posterior pode coexistir,mas nao qualifica prestacao.
- Cancelamento/substituicao e revogacao/superacao conhecidos alteram a validade
  da prova. Eventos futuros declarados nao sao presumidos ocorridos no instante
  de conhecimento. Exigir cadeia de substituicao antes de reaproveitar prova;
  nao apagar o documento anterior ou aceitar simultaneamente as duas origens.

## Rota de integracao versionada

1. Concluir aplicabilidade municipal/historica e politica de fontes com
   observacao/publicacao/efeitos separados. Consultas setoriais sao comparacoes,
   nao regras gerais de software ou cambio.
2. Implementar e testar fora do corte104 um qualificador material que produza
   datasISS por papel e hash de vinculo;apenas leitura de fatos e provas,sem
   inferencia de taxa,preco,resultado estrangeiro ou obrigacao integral.
3. Vincular resultados95 e condicoes97 diretamente as datasISS de cada papel
   em novos adaptadores,nunca mudando rtc.operation_date para satisfazer96.
   Rejeitar prova de resultado/municipio datada para outro evento,mesmo com
   hashes validos. ResultadoBR continua apenas afastando aquela exportacao.
4. Criar vinculo de identidade versionado que confronte competenciaDPS com a
   dataISS qualificada por papel,sem renomear referencias nem enfraquecer
   serie,numero,emissor,ambiente,unicidade local ou conhecimento do103.
5. Integrar com opt-in,facts/perguntas,bundle,trace e bloqueios proprios somente
   apos aceite104. Reexecutar fluxoRTC nominal antes da qualificacaoISS para
   preservar moeda,alocacao,classificacao e calculos ja comprovados. A dataRTC
   continua pertencendo ao seu fluxo;nao recalcular tributoRTC com a dataISS.
6. Converter/basear/calcular cada documento e projetar XML em etapas seguintes,
   com fontes e schemas qualificados. Nao copiar cotacaoRTC aoISS por conveniencia.

## Corpus minimo a executar,nao testes ja aprovados

Positivos:prestacao pontual com datas coincidentes;ISS anterior e posterior
aoRTC;datas diferentes entre os dois papeis;pagamento anterior/posterior sem
mudar prestacao;etapa mensal efetivamente cumprida sem entrega final;BRL/USD;
fronteira de dia por offset e precisao de microssegundos;variantes dos fluxos
101/104 que preservem recusas a montante. Cada positivo exige prova material
e as provas de resultado/municipio/identidade consistentes com sua data.

Negativos:so pagamento/assinatura/emissao;prova booleana;modalidade ausente;
etapa incompleta,intervalo invertido/multimensal nao segmentado;data futura;
31/02,offset14:01,timezone ausente;conhecimento anterior a fonte/prova;mesma
prova em papeis/documentos diferentes;conteudo re-hashado mas ligado a outra
operacao;regime nao comprovado ou especial;municipio errado;fato/data alterado
sem nova evidencia;cancelamento/substituicao sem cadeia;revogacao atual;
contradicao entre prova material,resultados e competencia;tentativa de usar
SC29/2025 como autorizacao de multa generica;policy escalada para producao.

Auditoria deve confrontar objetos e saldos nominais anteriores,normais e
recusados,em ambos os runners e replays exatos. Contagem isolada nao prova
requisito. Diferencas temporais sao casos positivos obrigatorios,nao excecoes
a remover do corpus para preservar o adaptador atual.

## Saida e limites

Somente candidato de data material localmente vinculado,com datas por papel,
origens,hashes e razoes de recusa especificas. Autenticacao governamental,
cadastro/autorizacao municipal,unicidade remota,legal_applicability_approved,
conversao/base/imposto por documento,XML,posting e production_ready continuam
falsos ate suas respectivas evidencias. Nao promover um candidato local a
obrigacao documental completa ou encerrar um grupo4B.
