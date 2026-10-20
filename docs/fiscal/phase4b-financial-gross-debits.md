# Débitos financeiros brutos — candidato12

Bundle `CONSUMPTION-PHASE4B-2026.10.02-12`. `FinancialTaxEngine` recebe enquadramento e base192/193 qualificados, vincula fornecedor/família/período e calcula débitos nominais. Não concede créditos, não fecha o saldo mensal nem aprova documento. A família financeira continua incompleta.

## Alíquotas e recolhimento

Em2026, usa o catálogo de alíquotas preservado: IBS estadual0,10%, municipal0%, CBS0,90%. LC214348 III b aplica esses percentuais às bases específicas financeiras. Base800 resultaIBS0,80/CBS7,20. Cada componente é arredondado monetariamente uma vez, HALF_UP; uma base5 produzIBS0,01/CBS0,05.

Cumprimento das obrigações acessórias deve ser conhecido: true conserva nominais e dispensa recolhimento pelo348 §1; false conserva débitos brutos anteriores ao encontro de contas; desconhecido exige o fato `rtc.accessory_obligations_fulfilled`. Não declara saldo líquido a pagar nem compensa créditos automaticamente. PIS/Cofins de2026 permanece obrigação independente, art348 §2.

Para2027–2033, conserva os percentuais combinados233 e as reduções em pontos percentuais pela incidência deISS. Exige essa incidência qualificada independentemente e alíquotas por componente publicadas em atos próprios, fonte, vigência/conhecimento e âmbito financeiro. Não calcula CBS por diferença da soma legal. Linhas por incidência deISS são selecionadas separadamente; conflito de componentes ou soma divergente bloqueia o cálculo. A partir2034 conserva a referência combinada2033, art189, mas ainda exige componentes publicados vigentes.

O catálogo financeiro futuro do candidato12 permanece vazio. As alíquotas futuras usadas nos testes são explicitamente sintéticas, não são fontes oficiais e não entram no bundle. O cálculo positivo atual é apenas o teste legal2026. Base negativa requer regra própria, sem crédito/carryforward inventado. Cooperativa com proporção zero positiva exige segregação entre operações tributáveis e zero antes de calcular tributos sobre a base mista; o ajuste188 isolado não elimina essa necessidade.

## Ativação e evidências

O estágio `FINANCIAL_GROSS_DEBITS` conserva a memória após enquadramento/base. Quando resolve, a decisão final ainda retorna `ACTION_REQUIRED:RTC_FINANCIAL_CREDIT_AND_DOCUMENT_PENDING`, com decisão vazia. Isso não é cenário financeiro integrado concluído. Os cenários RTC usam livro mensal financeiro, sem classificação de software ou ISS presumido sobre juros.

Corpus6/6 RTC parcial: crédito com dispensa, sem dispensa, condição desconhecida, base negativa, cooperativa mista e factoring. Corpus3/3 geral preserva software não financeiro de fornecedor183, comISS fixture20,4A61,50 eIBS1/CBS9. Testes dirigidos39/140 (17/57 cálculo,6/30 ativação,16/53 regressão integrada11); após correção de presença da linha de alíquota,23/87 cálculo/ativação novamente passaram. PHPStan sem erros. Última suíte completa385/1554 permanece histórica do7.

| Snapshot | Tipo | Hash |
|---|---|---|
| Financeiro com dispensa | Parcial, decisão bloqueada | `2e8f68e71e58babed7dafdcad3e8de5f1ef7fb60c7d6e0152b2e311787281a87` |
| Financeiro sem dispensa | Parcial, decisão bloqueada | `85f4c07d73aba9dfdf4416c78d8f588b1be5a4251ccd83a345e258da7197fd0f` |
| Banco/software | Integrado geral | `7b4e44e2a150108ff94784f5cd123a894496b3dad514123c69f277fd866c0533` |
| Caixa/software | Integrado geral | `b836971ea7ae5b623c40b5d7c19c8ea92f704a063cb98bc85cf8e47c35dcbef7` |
| Instituição pagamento/software | Integrado geral | `aea5f847df5e06c1622ac924c403eac92c01740fa8ac9196f13841e5be0adefa` |

Cinco replays idênticos; três candidatos XML somente dos gerais, com apenas conflito oficial de série. Não foi gerada DPS financeira específica. Regressão11 novamente13/13 e seus três hashes preservados. Artefatos em `storage/app/phase4b-financial-gross-debits-*-20261002-12-*` e `phase4b-financial-tax-general-*-20261002-12-*`. Gate `phase4b-production-assessment-20261002-12.json`:20/20 fontes, elegível local, `ready=false`,119 bloqueios,12 controles parciais/abertos.

Fonte: [LC214 consolidada, arts189,233 e343–348](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html), hashHTML `dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`; catálogo de teste vincula PDF oficial preservado `0cf36f4433ced108c8072379496b119817549ea0c182b018cad309ffbe1e1442`. Não se acrescentou ato ou fonte futura. Materializador `fork_phase4b_financial_tax_bundle.py`, verificador `verify_phase4b_financial_tax_cut.py`.

Créditos específicos, documentos por perfil, demais bases182, transporte de perdas193 e segregação cooperativa mista permanecem desenvolvimento obrigatório. Nenhuma emissão, assinatura, transmissão, escrituração externa ou recolhimento.
