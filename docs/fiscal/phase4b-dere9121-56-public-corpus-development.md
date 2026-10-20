# Corpus público de composição e BCN — 56

Vinte cenários públicos positivos do candidato27 foram recapturados sob o bundle54, com snapshots financeiros próprios, novos testemunhos sintéticos explícitos para o PGCC sucessor, retorno D9121 vinculado ao D2101 nativo e solicitação mensal de BCN. Foram conferidos120 hashes XML,20 replays de apuração e20 replays do comando mensal.

A auditoria independente em Decimal verificou a composição sem duplicação D1101/D2101, gross-down com oito casas e HalfEven, bases e deduções por raiz, componentes IBS/CBS e conservação dos estoques. Os XMLs D1001/D1101/D1121/D2101 permanecem byte a byte iguais às fontes anteriores de cada cenário. O PGCC acrescenta exatamente um campo `indTribISS=0`; o D1199 acrescenta as opções BCN e seu contêiner, sem alterar os demais campos. Todas as fontes históricas foram conferidas por SHA256.

Os cenários cobrem vendas completas e parciais, amortizações, cupons, compras em dias não úteis, custos distintos, quantidades fracionárias, vários títulos e operações ordenadas no mesmo dia. Valor de apuração público zero com títulos declarados não equivale ao caminho `semTitulos=1`, que continua pendente no escopo57.

A primeira auditoria não considerava que o projetor cria `infoFechamento` ao acrescentar opções a um D1199 que não tinha esse grupo. A comparação foi corrigida para conferir explicitamente a ausência do grupo no original e retirar somente o contêiner novo esvaziado pela remoção das opções. O diagnóstico e a fonte inicial do auditor foram preservados.

O relatório autoritativo é `storage/app/phase4b-dere9121-56-corpus-preparation-20261013/corpus-development-validation.json`. O corpus local está validado; a suíte geral após55 ainda estava em execução durante esta auditoria. A cadeia de versões públicas, ausência de movimento, autenticação governamental, geração negativa integrada, calendário e demais exigências continuam abertas. Produção permanece bloqueada.
