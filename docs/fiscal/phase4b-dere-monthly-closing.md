# DeRE — fechamento mensal local D1199

Estado corrente — Candidato27 (06/10/2026): posição variável com memória diária reproduzida integrada à base financeira e ao D2101, com movimentos brutos reconciliados ao D1101 e origem fiscal única. Primeira aquisição, custo distinto do saldo contábil, compras/saídas/amortização/cupons/fundos e múltiplos títulos cobertos. Suíte1.082/8.294, PHPStan sem erros, Pint373,38 casos/120 corpos XML/20 replays; regressões27/26/25 e3.114 históricos preservados. Diferenças de alienação e resíduos exigem tratamento próprio. Gate47/47 fontes, `ready=false`,119 bloqueios; Fase4B parcial. [Aceitação e limites](phase4b-dere-d2101-variable-integration.md).

Histórico — Candidato26 (06/10/2026): memória diária de custo médio e movimentações, com compras, vendas, amortizações, cupons, curvas contábeis e benchmark Selic por convenção local explícita. Custo de aquisição separado do saldo contábil; ganho de venda registrado com tratamento tributário pendente. Suíte1.044/7.937, PHPStan sem erros, Pint372,44 casos/16 replays/seis guardas; regressões26/25 e2.849 históricos preservados. Posição variável ainda não ativada na base financeira ou XML mensal. Gate47/47 fontes, `ready=false`,119 bloqueios; Fase4B parcial. [Aceitação e limites](phase4b-dere-d2101-daily-memory.md).

Histórico — Candidato25 (06/10/2026): rendimento art.195 de custo constante qualificado por emissor/oferta/posição, integrado à base financeira e ao D2101 com origem fiscal única. Base conjunta R$798,17 no exemplo, D2101 R$98,17; gross contábil preservado e perdas sem compensação entre títulos. Arredondamento DeRE8/2 HALF_EVEN, XSD1.3.0. Suíte998/3.315, PHPStan sem erros, Pint369,39 casos/72 corpos XML/12 replays; regressões25/24/23 e2.593 históricos preservados. Gate47/47 fontes, `ready=false`,119 bloqueios; Fase4B parcial. [Aceitação e limites](phase4b-dere-d2101-yield-integration.md).

Histórico — Candidato24 (06/10/2026): cálculo D2101 por título para posição de custo constante durante todo o mês, usando21 taxas diárias BCB SGS11 reais de setembro2026 e provas de posição sintéticas. TetoR$108,17 para custoR$10.000; baseR$98,17 com variação150/PIS10. Suíte959/3.108, PHPStan sem erros, Pint366,22 casos/sete replays; regressões24/23 e2.448 históricos preservados. Integração à base financeira e XML mensal com rendimentos ainda pendente. Gate47/47 fontes, `ready=false`,119 bloqueios; Fase4B parcial. [Aceitação e limites](phase4b-dere-d2101-constant-cost.md).

Histórico — Candidato23 (06/10/2026): D2101 com aquisição, amortização, resgate e continuidade de principal por ISIN/conta, reconciliado ao D1101. Suíte935/3.024, PHPStan sem erros, Pint363,37 casos/42 XML; regressões23/22 e2.284 históricos preservados. Provas positivas sintéticas; Selic externa previamente qualificada, juros/ganhos bloqueados. Gate43/43 fontes, `ready=false`,119 bloqueios; Fase4B parcial. [Aceitação e limites](phase4b-dere-d2101-principal-movement.md).

Histórico — Candidato22 (06/10/2026): migração dos seis eventos mensais suportados ao mesmo pacote oficial XSD1.3.0; D1011v1.0.4 e D1121v0.0.2 com conta e histórico de continuação conferidos. Suíte898/2.901, PHPStan sem erros, Pint361,46 casos e41 corpos XML, regressões22/21 e2.203 históricos preservados. Provas positivas sintéticas; dedução de correspondente em conta única e D2101 sem movimento. Gate43/43 fontes, `ready=false`,119 bloqueios; Fase4B parcial. [Aceitação e limites](phase4b-dere-schema13-migration.md).

Atualização06/10/2026 — Candidato21: D2101 sem movimento para contas zeradas dos códigos110113001/110113002, com prova independente e XSD próprio1.3.0, integrado ao fechamento. Movimentos/saldos não nulos e D1106 permanecem pendentes. Os contratos e medições dos candidatos anteriores abaixo são históricos. [Contrato e aceitação21](phase4b-dere-d2101-no-movement.md).

## Candidato20: parcela D1121 reconciliada ao PGCC

Candidato20: D1121 obrigatório411011105 reconciliado ao PGCC, lançamento e documento, com dedução financeira integral separada da parcela declarada. Inclui original, continuação, competência sem apropriação e captação isolada. Provas positivas sintéticas. Suíte860/2.752, PHPStan sem erros, Pint358, corpus19/19, quatro novos replays e18 corpos XML aprovados; regressões20/19/18/17 e1.925 históricos preservados. Gate42/42 fontes, elegível local, `ready=false`,119 bloqueios. Fase4B parcial e defaults preservados. [Aceitação e limites](phase4b-dere-d1121-pgcc-binding.md). Conta obrigatória sem apropriação documental gera indInexistDedu=1; captação isolada não gera D1121.

## Histórico — Candidato19: PGCC obrigatório no recorte oficial

O19 acrescenta D1011 e qualificação PGCC obrigatórios, catálogos vinculados por hash e provas independentes datadas. Auxiliares são derivados da RN1.3.0; na captação411011201 o comando preserva a reconciliação da dedução e publica D1001/D1011/D1101/D1199, sem D1121 obrigatório. A política legado17/18 e o default17 permanecem preservados. Corpus12/12, suíte830/2.700, PHPStan sem erros, Pint356 e regressões19/18/17 aprovados. Gate42/42 fontes, `ready=false`,119 bloqueios. Sem homologação mensal integral1.3.0. [Escopo, temporalidade e aceitação](phase4b-dere-pgcc-activation.md).

## Histórico — Candidato18: cadastro financeiro com domínio oficial

`CONSUMPTION-PHASE4B-2026.10.05-18` acrescenta D1001 financeiro obrigatório ao recorte mensal, com Tabela21 oficial1.3.0, cinco provas externas datadas, hash do catálogo e conferência de atividade/operação. Exige vigência cadastral cobrindo o mês inteiro. Provas positivas do cadastro/calendário continuam sintéticas. D1101/D1121/D1199 mantêm contratos, pacoteXSD1.2.0 e hashes do candidato17; pacote mensal integral1.3.0 ainda não demonstrado. PGCC/D1011 não ativados; default do comando permanece17. Suíte800/2.658, corpus8/8 e regressões18/17 aprovados,1.660 históricos preservados. Gate41/41 fontes, `ready=false`,119 bloqueios. [Escopo e aceitação](phase4b-dere-contributor-activation.md). As medições seguintes são históricas.

## Atualização05/10/2026

Integração opcional D1001 acrescentada: requalifica perfil, exige identidade/período/hash coerentes e confronta validade/provas do D1011 quando também projetado. O comando inclui D1001 na validação individual dos XSDs e na conferência de destinos antes da gravação. Sem ativação no candidato17. Contrato/limites em `phase4b-dere-contributor.md`.

Tratamento D1011 histórico confirmado: contas inativas conservadas na declaração, referências e códigos com cobertura do intervalo histórico e obrigações mensais derivadas apenas de contas ativas. Testes conferem o conjunto com cinco eventos nos XSDs preservados, somente assinatura ausente. Validação final dirigida116 testes/236 asserções inclui cadastro, PGCC, fechamento e comando. Suíte ampla anterior ao último caso do comando768/2.514; PHPStan completo sem erros; Pint353 arquivos e último caso do comando aprovados. Logs em `storage/app/phase4b-resumption-20261005-1/`.

Verificador17 reproduziu D1101/D1121/D1199 com hashes idênticos, corpus15/15 RTC e3/3 geral e oito replays. Comparados1.642 artefatos históricos sem mudança. Gate17 com32/32 fontes, `ready=false`,119 bloqueios. As contagens e estados anteriores abaixo são históricos; não comprovam implementação integral.

## Componente em desenvolvimento

`DereMonthlyClosingXmlProjector`, contrato `DERE-MONTHLY-CLOSING-LOCAL-2026.10.02-1`, reconstrói o candidato de balancete D1101 e, havendo deduções financeiras, o candidato D1121 antes de projetar inclusão original do D1199. Fornecedor, documento, período e hash do PGCC precisam ser coerentes. O resultado permanece `period_closed=false`: a projeção não representa recepção governamental ou fechamento efetivo.

Exige qualificação explícita do cadastro do contribuinte e das obrigações auxiliares, com hashes das evidências e escopo do período. Os indicadores de necessidade de D1106/D2101/D1121 não podem ser inferidos por ausência de dados. D1106 ou D2101 necessários continuam pendentes até implementação do respectivo evento. Fechamento anterior ativo ou solicitação de uso de base negativa não são aceitos neste contrato inicial.

`indInexistDedu=1` é emitido somente com dedução calculada igual a zero e obrigatoriedade D1121 comprovada. Com deduções, D1121 é reconstruído e o indicador é omitido. Sem obrigatoriedade, o indicador também é omitido. Bases negativas calculadas continuam exigindo contabilidade própria, sem aproveitamento automático.

## Evidência dirigida

`tests/Fiscal/Unit/DereMonthlyClosingXmlProjectorTest.php`:15 testes/42 asserções; PHPStan passou e Pint aplicado. Três casos positivos: deduções, ausência de deduções com obrigação auxiliar, ausência de deduções sem essa obrigação. Os testes executam o motor RTC com o candidato16; os casos sem deduções removem efetivamente o lançamento do cenário antes do cálculo. D1101, D1121 quando presente e D1199 são conferidos nos XSDs preservados. Somente assinatura ausente em cada candidato. Caso de base negativa usa cálculo efetivo com receita1000.00 e dedução1200.00, recebendo pendência específica.

Fontes preservadas: leiaute1.2.0 SHA-256 `7adbc82a55f0d60dfc2a63771a4c65c67d479e6a50a7821079e73f38b21873c7`, AnexoII SHA-256 `b52cb2cf96b176d303d64aeea0bab16e7d35bcc739478f6fc8a26b0512415feb`, pacoteXSD SHA-256 `c750f7d522400e5bd93b7ddd1d1d0d8108cd033eb250c1f10011ac57cc732818`. Regras consideradas: `EVENTOS_OBRIGATORIOS_PERIODO`, `CONSISTIR_PGCC_EVENTOS_PERIODO` e semântica de `indInexistDedu` no leiaute. O hash local do PGCC não substitui recibo de recepção oficial.

## Candidato17: comando, captura e replay

`CONSUMPTION-PHASE4B-2026.10.02-17` vincula a política `dere_monthly_closing_projection`, mantendo as políticas D1101/D1121 herdadas. `fiscal:dere:project-monthly-closing {snapshot} {month}` reproduz o resultado integral do snapshot, confere o bundle selecionado e reconstrói os três candidatos locais. Todos os corpos precisam passar pela conferência dos XSDs oficiais com somente assinatura ausente antes de gravar XMLs.

Com `--candidate-output=caminho.xml`, D1199 é salvo no caminho solicitado e os auxiliares em `caminho.xml.D1101.xml` e `caminho.xml.D1121.xml`, quando presentes. Os destinos existentes são conferidos antes da gravação e preservados. Relatório vincula snapshot, entrada mensal, decisão, políticas e validação individual dos eventos por hashes. Falha de escrita após o início ainda pode deixar arquivos parciais; não há promessa de transação de filesystem.

Materializador: `scripts/fork_phase4b_dere_closing_bundle.py`. Verificador: `scripts/verify_phase4b_dere_closing_cut.py`. Artefatos em `storage/app/phase4b-dere-monthly-closing-20261002-17-*`. SHA-256 D1101: `ae99d818eafb9ec05afd6a9ec008b625ade236e1a4ee8a6bb293b5223b96b0db`; D1121: `e76f7ae1a1b2521f976f09344b65e062ed230f3db7e9acc6f9bbc4e234d2de25`; D1199: `c3399dd2d66681d8ddedf9651f0792b39c888542584f2a50f46bf962ffe232cf`.

Testes do comando5/19 e projetor15/42:20 testes/61 asserções dirigidos, PHPStan passou, Pint aplicado. Regressão17:15/15 RTC parciais e3/3 gerais integrados,8 replays herdados;3 candidatos DPS gerais permanecem com conflito oficial de série. Gate17:32/32 hashes de fontes íntegros, elegível local, `ready=false`,119 bloqueios. A reprodução deste conjunto documental específico não conclui as demais famílias financeiras ou a Fase4B.

## Pendências de conclusão

Avanço posterior: integração opcional obrigatória do qualificador PGCC implementada no componente, com comparação de contas, frequência, completude analítica e obrigações auxiliares.23 testes/53 asserções do projetor passaram; replay do candidato17 preservado. A política ainda não foi ativada no17, pois faltam catálogos oficiais datados e qualificados. Detalhes em `phase4b-dere-pgcc.md`.

Cadastro, PGCC e tabela de códigos que determina obrigatoriedade são entradas previamente qualificadas nas fixtures; falta sua qualificação integral a partir dos documentos reais. Falta exigir/verificar validade do cadastro e a tabela referencial vigente, D1001/D1011 completos, D1106/D2101 positivos, bases negativas e opções de aproveitamento, D1198/reabertura/retificações e homologação técnica do conjunto mensal completo. Eventos financeiros transacionais e ISS/4A das famílias específicas continuam sujeitos às pendências já registradas. Não houve assinatura, transmissão ou recolhimento; o gate de produção permanece false.
