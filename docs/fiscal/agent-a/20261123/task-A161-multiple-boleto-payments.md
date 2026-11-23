# A161 — múltiplos pagamentos de um boleto

Próximo desenvolvimento da frente 1 após A160. Estado: especificação de implementação;
não é entrega funcional nem aceite do requisito 4B.3.7. Preservar os contratos A158–A160.

## Fontes lidas

- Manual de Operações 1.0.1, §§5.1–5.3: proporcionalidade por pagamento, truncamento final
  separado de IBS/CBS, limite em aberto por pagamento; Pix Dinâmico/Automático têm um pagamento.
- Manual de Integração 1.1.1, página 10: `indPgtoIntegral` refere-se ao total dos pagamentos
  até o momento; boleto é o arranjo do recebedor que admite múltiplos pagamentos da transação.
- Hash do PDF de Integração preservado no recibo A158:
  `d9b91bd1ac0b8f199f9e38e3437cd0d70320e4df1074fcaca59cbb9581d7db2e`.
- Operações foi recapturado por bytes em A160. Recibo externo em
  `D:/ProjetoSaasExports/front1-a160-20261123-v1/source-review.json`.

Não descontar segregações anteriores do valor em aberto por hipótese: o valor em aberto
é uma resposta da plataforma por pagamento e não prova extinção. Não distribuir centavos
residuais entre pagamentos. Não inferir o indicador de pagamento integral apenas por soma,
pois acréscimos, revisões e informações do arranjo podem ter materialidade própria.

## Entrega necessária

1. Contrato local próprio para um boleto com histórico completo de pagamentos. Um número
   de parcela e um identificador do boleto; IDs e recibos financeiros distintos por pagamento.
2. Snapshot corrente qualificado A158/A160, sem usá-lo como nova liquidação. Cada pagamento
   tem seu snapshot no congelamento e liquidação próprios, com conhecimento não posterior ao
   do livro. Revisões posteriores não reescrevem o cálculo já congelado do pagamento anterior.
3. Partes, operação, documento, moeda, boleto, PSP e parcela vinculados. Valores históricos
   preservados por pagamento; principal/débito da parcela alocados uma única vez no cronograma.
4. Prova e indicador fornecido de pagamento integral. Somente o pagamento final pode encerrar
   a janela integral; pagamentos parciais não podem encerrá-la. Nova liquidação depois do
   encerramento exige recusa. Não criar IDs de plataforma ou pagamento externo.
5. Resultado por pagamento e agregados locais separados. Reuso de recibos, IDs e eventos,
   datas contraditórias, escopo Pix e histórico incompleto precisam de recusas testadas.
6. Livro versionado novo precisa referenciar os pagamentos individualmente em informes;
   o identificador do boleto sozinho é ambíguo. Não duplicar parcelas/transações para contornar
   essa necessidade. Guardas de cronograma A159, revisões A160 e vínculos B34 preservadas.
7. A conciliação de informe, repasse e aplicação deve usar o instante e o valor daquele
   pagamento. Impedir informe anterior à sua liquidação e dupla declaração em informes distintos.
8. Ativação própria: bundle derivado A160, política exata, pergunta do livro, CLI/motor,
   replays, oráculo Decimal, negativos e compatibilidade A158/A159/A160/A157/B34/B38/B39.

## Próximos pontos do objetivo integral

Após pagamentos múltiplos, continuar modelo legal simplificado e prazos/calendários
qualificados; depois recolhimento pelo adquirente, operações combinadas, perfis restantes,
temporalidade e base/memória. Os 44 IDs permanecem na auditoria de trabalho
`requirements44-current.json`. Nenhum grupo está encerrado; produção continua separada.
