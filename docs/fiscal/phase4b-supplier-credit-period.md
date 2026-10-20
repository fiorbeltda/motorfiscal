# Créditos anteriores do fornecedor — apuração mensal

Candidato `CONSUMPTION-PHASE4B-2026.10.02-3`, ainda sujeito à revisão formal. Não encerra o escopo integral da Fase 4B.

## Contrato

`rtc.assess_supplier_credit_period=true` solicita a análise. `rtc.supplier_credit_period` recebe uma lista com exatamente um livro mensal. O livro exige fornecedor, BRL, mês encerrado, todas as saídas, provas do livro e do conjunto completo de créditos, documentos de apropriação/extinção e ajustes individualizados. O contrato é `SINGLE_PERIOD_ARRAY`. O candidato intermediário `-2` permanece preservado como tentativa com formato incompatível; não possui execução positiva homologada.

A apuração separa IBS e CBS e aplica os arts. 47 §10, 51 e 52 da LC 214 sobre tratamentos das saídas já qualificados e documentados. Saídas imunes comuns e isentas compõem o numerador da anulação; exportação, livros e radiodifusão gratuita recebem as exceções documentadas. Alíquota zero e reduções preservam créditos segundo a regra aplicável. O denominador confere o valor de todas as saídas do mês. O cálculo monetário arredonda uma única vez, preservando numerador e denominador exatos.

Ajustes anteriores reduzem cada crédito individualmente; exceder o saldo do documento invalida o livro. O prazo de utilização considera o art. 54, a partir do primeiro dia do mês seguinte à apropriação. Duplicidades, titular divergente e anulação já registrada invalidam a entrada. Mês aberto, livro incompleto, ausência de prova, faturamento zero e tratamentos especiais sem regra materializada exigem ação.

O módulo não qualifica automaticamente a elegibilidade de imunidades/isenções das saídas. Recebe um livro já qualificado com referências de evidência. Não produz a apuração consolidada de débitos/pagamentos, não executa lançamentos e não transforma o crédito preservado em saldo disponível de conta fiscal. Presumidos e regimes específicos seguem pendentes.

## Verificação

23 testes dirigidos passaram, com81 asserções; suíte completa do corte328/1.384. PHPStan sem erros. O script `scripts/verify_phase4b_supplier_credit_period_cut.py` conferiu valores independentes antes de gravar os hashes: corpus RTC20/20 e integrado3/3, três snapshots com replay idêntico. Os integrados mantêm ISS de fixture de R$50,00 e4A `NOT_REQUESTED`; operação corrente IBS R$0,40/CBS R$3,60 separada do livro anterior. A projeção XML do livro misto mantém os valores da operação; o XSD oficial rejeita somente a série conhecida. O XML permanece candidato, sem validação positiva.

Exemplos do livro: misto anula R$30/R$270 e mantém R$70/R$630; assimétrico anula R$40/R$0; ajuste prévio IBS de R$10 reduz a anulação IBS para R$27. A divisão 1/3 sobre créditos R$0,05/R$0,10 anula R$0,02/R$0,03.

Evidências em `storage/app`: `phase4b-supplier-credit-period-*-20261002-3-snapshot.json`, resultados e dois relatórios de corpus. Gate `phase4b-production-assessment-20261002-3.json`: elegível local, `ready=false`, 113 bloqueios. As fontes e os controles permanecem sujeitos à revisão; este corte não equivale a autorização de produção.
