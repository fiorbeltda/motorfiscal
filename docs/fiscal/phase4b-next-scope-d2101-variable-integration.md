# Próximo objetivo — posição variável integrada ao D2101 e ao livro

Partida: candidatos25 e26 aceitos localmente. O25 integra rendimento de posição constante e origem fiscal única. O26 reconstrói posição variável, memória diária, aquisição, saídas, amortização, cupons e curva Selic por método local explícito. A Fase4B permanece parcial; produção bloqueada.

Objetivo: ligar o resultado reproduzido da memória26 à mesma instituição/título/conta/período no qualificador art.195, à base financeira e ao D2101 mensal, com prova integral da metodologia declarada e saldos/movimentos conferidos ao D1101. A prova do emissor/oferta deve continuar independente da prova de posição. O custo inicial não deve ser confundido com o saldo contábil inicial quando houver juros apropriados de competências anteriores.

Aceitação: compras no mês e primeira aquisição, alteração de custo médio, saídas parciais/totais, amortização e cupom, ambas as convenções temporais, variação negativa, fundos exclusivos e múltiplos títulos/contas. Cada rendimento deve ter uma origem fiscal, sem duplicação D1101/D2101 ou entre lançamentos. As entradas e saídas patrimoniais devem reconciliar movimentos e saldos ao livro sem serem classificadas automaticamente como receita ou dedução. A memória tem de ser reproduzida, não apenas confiar no valor mensal declarado.

Ganho/perda de alienação deve ter regra explícita de enquadramento e origem; enquanto ausente, a integração precisa recusá-lo com motivo próprio. A convenção intradiária do26 continuará identificada como local, sem alegação de homologação nem liberação de produção. Não fabricar recibo PGCC ou retorno D9121.

Produzir bundle/controles próprios, cenários de integração positivos/negativos, snapshots/replays, conjunto mensal coerente no XSD1.3.0, testes e oráculo independente, auditoria e regressões26/25 com hashes preservados. Concluir esse recorte não encerra a4B: auxiliares adicionais, bases negativas, reabertura, demais famílias e corpus integral de transição/final continuam no objetivo maior.

## Aceitação27 e continuidade

Integração local concluída:1.082 testes/8.294 asserções,38 casos/120 XML/20 replays, regressões27/26/25 e3.114 históricos preservados. Relatório em `storage/app/phase4b-variable-yield-20261006-27/verification-report.json`; contrato em `phase4b-dere-d2101-variable-integration.md`. Fonte consolidada reconsultada/capturada por hash.

Próximo objetivo: `phase4b-next-scope-tvm-realization.md`, para resolver as diferenças de alienação, custo de aquisição e componentes já apropriados/diferidos. A4B permanece parcial e o objetivo maior continua ativo.
