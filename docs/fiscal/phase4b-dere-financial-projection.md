# Projeção financeira local — candidato14

`CONSUMPTION-PHASE4B-2026.10.02-14` vincula32 artefatos oficiais preservados e o contrato `DERE-FINANCIAL-LOCAL-2026.10.02-1`. Fontes DeRE são observadas para desenvolvimento; metadados de publicação/efeitos ainda requerem qualificação. A Fase4B continua incompleta e `ready=false`.

## Execução e vínculo

Comando no contêiner:

```text
php artisan fiscal:dere:project-xml storage/app/phase4b-dere-loan-20261002-14-snapshot.json resources/fiscal/scenarios/dere-local-2026.10.02-14/qualified-allocation.json --candidate-output=storage/app/novo-candidato-dere.xml --report=storage/app/novo-relatorio-dere.json
```

O comando seleciona14 por padrão, exige igualdade do hash do manifesto capturado e reproduz a decisão RTC inteira antes da projeção. Aceita somente a etapa específica `RTC_FINANCIAL_DOCUMENT_PENDING`; não transforma esse snapshot parcial em decisão integrada aprovada. Rejeita corpo alterado ou seleção de outro bundle. XMLs e relatórios são imutáveis.

`DereFinancialXmlProjector` exige prova previamente qualificada de alocação completa, valores líquidos, incidência independente de ISS e partes. Confere fornecedor/documento/período, origem da alocação, observação até conhecimento, operações cronológicas, IDs únicos e chaves distintas. A soma das bases transacionais deve coincidir com a base mensal e com os débitos calculados. Valores transacionais não são deduzidos automaticamente de um total mensal. Datas, cadastro, códigos municipais e veracidade das provas continuam sujeitos à qualificação material.

## Eventos materializados

| Categoria | Sem ISS /com ISS | Evento local |
| --- | --- | --- |
| Crédito/TVM | 1010 /1005 | D-2211, `evtOperFinanc-v0_0_1.xsd` |
| Securitização | 1220 /1215 | D-2221, `evtAntecReceb-v0_0_1.xsd` |
| Factoring | 1240 /1235 | D-2221, `evtAntecReceb-v0_0_1.xsd` |

Códigos conforme tabela12 do anexoI1.2.0 preservado. O contrato atual trata adquirentes domésticos CPF/CNPJ, registro original11, ambiente local restrito2, múltiplos grupos e até999 operações por grupo. Não cobre automaticamente câmbio, outras bases, adquirentes estrangeiros, devolução/cancelamento ou FGTS.

## Arredondamento

Débitos financeiros14 usam a regra documental do anexoII: intermediário8, final2, empate para o par. Mantém o cálculo histórico13 intacto: sobre base5, IBS/CBS eram0,01/0,05 no13 e são0,00/0,04 no14. O projetor verifica os valores mensais contra o arredondamento DeRE e calcula cada detalhe separadamente; não arredonda silenciosamente um resultado mensal incompatível. A política completa de bases, gross-down, créditos, conciliação entre arredondamento mensal/transacional e demais regimes ainda precisa de desenvolvimento.

## Evidências verificadas

43 testes dirigidos/149 asserções de projetor, comando, limites de arredondamento e regressões financeiras. PHPStan sem erros após implementação do projetor/comando; Pint sem erros. Corpus15/15 RTC parcial e3/3 gerais integrados. O verificador herdado confere oito replays; o verificador DeRE executa três projeções/replays adicionais, sendo crédito o mesmo caso do corpus herdado. Não contabilizar snapshots duplicados como novas famílias positivas.

Três candidatos locais, com duas operações de bases500 e300: IBS estadual0,50+0,30, CBS4,50+2,70. SHA256 dos XMLs:

- Crédito: `62a03193fdc4d36be28b3244a81bc747c53e4cd8f25475f49c5de29d97cdf7a3`.
- Factoring: `98e5c5e0c109ed68b774a37dbc83143502c58bd76b947218751d03c02b72659d`.
- Securitização: `7c58dd2fe3c0ed59d566b1f38611a99473a8646f32352bb7cf2673494deee8d6`.

Corpos avaliados nos XSDs oficiais íntegros, com dependência W3C por hash. Único erro de cada candidato: assinatura ausente. Resultado `ACTION_REQUIRED / RTC_DERE_UNSIGNED_LOCAL_CANDIDATE`, corpo estrutural conferido; assinatura e transmissão não realizadas. Nenhum desses resultados comprova a declaração mensal ou a integração financeira ISS/4A completa.

`scripts/verify_phase4b_dere_projection_cut.py` verifica bases/tributos, chaves e DV com cálculo independente, além de replay e hashes dos arquivos. `scripts/verify_phase4b_financial_credit_cut.py` aceita versão explícita e conserva o13 como padrão histórico. Gate14:32/32 hashes, elegível local, `ready=false`,119 bloqueios,12 controles parciais/abertos.

## Trabalho restante da família

ISS e4A financeiros próprios, demais17 categorias/base, saldo negativo e transporte de perdas, dados reais e cálculo bruto Selic/DI, crédito documental, gross-down, integração contábil real, D-1001/D-1011/D-1101/D-1121/D-1199 e outros eventos mensais pertinentes; calendário/implantação/publicação completos, regras de negócio e variantes documentais. Candidatos sem assinatura atendem somente à verificação local prevista; autenticação e transmissão ficam fora do escopo.
