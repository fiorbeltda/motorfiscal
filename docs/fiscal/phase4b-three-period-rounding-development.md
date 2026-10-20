# Desenvolvimento28 — cadeia de arredondamento em três competências completas

Objetivo integral: capturar primeira aquisição e baixa em julho, venda com arredondamento criado em agosto e nova realização em setembro. A última venda precisa consumir separadamente arredondamento com cobertura fiscal, resíduo anteriormente criado sem essa cobertura e eventual diferença nova. A preparação de abertura34 não substitui essa cadeia mensal completa.

O draft35 usa as23 taxas diárias SGS11 de julho já preservadas e quatro fontes de primeira aquisição: apropriações com saldo negativo, positivo, zero e exato. Cada fonte compra1.000 unidades/custo10.000 e vende500 unidades em14/07. Os fechamentos conservam saldo contábil4.999,99/5.000,01/5.000,00/5.000,01, custo5.000 e curvas/arredondamentos separados, como nas fontes31 de agosto. A memória diária é recalculada com a série real de julho; não é deslocada mantendo benchmark antigo.

Os quatro testes dirigidos passaram com72 asserções na captura. Cada fonte foi capturada como snapshot financeiro, reproduzida e projetada mensalmente. Quatro replays exatos e24 corpos XML dos seis eventos suportados foram conferidos contra a memória e o balancete, incluindo IBS/CBS separados, deduções genéricas, campos do título D2101 e movimentos D1101. XSD1.3.0 aponta somente assinatura ausente. Registros em `storage/app/phase4b-realization-three-period-rounding-draft-20261008-28/july-corpus.json`.

O helper mensal `TvmFairFixtures` agora suporta julho e agosto com mapas temporais explícitos, fim do mês e competência anterior corretos. Datas das provas são transformadas e seus hashes recalculados. O mapa de agosto permanece equivalente ao anterior; as regressões32/30 com comparação exata já passaram. A qualificação34 foi recapturada com comparação idêntica nos18 casos/116 asserções. PHPStan e Pint418 arquivos aprovados; suíte ampla, candidatos27/26/25 e auditoria histórica final possuem registros próprios de execução.

Suíte ampla concluída:1.466 testes/15.527 asserções. Regressões34/33/32/30 e candidatos27/26/25 aprovadas; gate bloqueado com49 fontes oficiais válidas. A comparação de cada data e valor da série julho contra o CSV oficial confirmou as23 linhas sem adição de dias sintéticos. `july-series-row-audit.json` registra a conferência. A compressão de224 arquivos de evidência conservou bytes e caminhos, registrados em `compression-preservation.json`.

Auditoria final:5.592 artefatos históricos sem alteração. `july-progress-report.json` conserva qualidade, corpus, pesquisa e limites. O primeiro mês está validado neste recorte; `full_three_month_chain_accepted=false`, `scope28_completed=false` e `production_ready=false`. O registro de preparação inicial permanece preservado e não foi reescrito para representar a captura posterior.

O draft36 prepara dez vendas de agosto a partir das decisões de julho efetivamente capturadas. Usa competência e série oficial de agosto, alocação líquida comprovada do fechamento de julho e provas por fonte/decisão/memória. Inclui quatro históricos com vendas parcial/total e dois controles de sinais alinhados. O novo rendimento fornecido é50,00/PIS5,00 ou50,02/PIS5,00 nos controles alinhados.

`scripts/check_phase4b_three_period_august_preparation.php` reproduziu as fontes e as memórias de fechamento para os dez casos, conferiu abertura fiscal/econômica e comparou cada campo do oráculo decimal diário com a memória nativa de agosto. A base D2101 esperada é34,04 nas seis vendas parciais e18,43 nas quatro totais. Essa preparação não é captura da decisão financeira de agosto: a integração de realização, o fechamento mensal e seus XMLs continuam pendentes. Registros em `storage/app/phase4b-realization-three-period-august-draft-20261008-28/native-preparation-check.json`.

Fontes: [LC214 consolidada](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html), novamente consultada com hash igual ao preservado; MOD1.3.0, páginas físicas66/67 e86/87; série julhoSGS11 preservada com23 linhas e hash conferido. Pesquisa35 em `research.json`. Convenções locais e provas sintéticas não são homologação normativa.

Próximos trabalhos: adaptar explicitamente os helpers de prova histórica para a competência de agosto sem alterar os resultados antigos; capturar e validar as dez realizações36; transportar seis fechamentos parciais para setembro usando a alocação por componente do contrato34; integrar as12 vendas finais com cobertura fiscal antiga e resíduos econômicos separados. Concluir replays/fechamentos/XML dos três períodos, oráculos, guardas, auditoria e regressões antes de declarar a cadeia aceita.

Limites: primeira fonte de julho sem exclusão/PIS, rota direta, operações únicas nas competências seguintes e sem valor justo/ganho contábil. A cadeia completa, demais combinações do escopo28 e aceitação4B continuam abertas. Produção bloqueada; candidato27 permanece a última aceitação local integral. Nenhum dado futuro de outubro foi usado ou inventado.

Reprodução: `scripts/build_phase4b_tvm_three_period_rounding_draft.py`, `scripts/verify_phase4b_tvm_three_period_rounding_draft.py` e `scripts/build_phase4b_tvm_three_period_august_draft.py`. Fixtures e snapshots volumosos são comprimidos com conservação de bytes e hashes; não se removem evidências históricas.

## Integração de agosto — draft36

As dez vendas de agosto foram integradas à decisão financeira e ao fechamento mensal. O helper de histórico passou a derivar competência atual e anterior, datas de reconhecimento/provas e saldo anterior pela posição fornecida; os caminhos de setembro continuam sendo comparados com as capturas anteriores. O caso financeiro declara31/08 tanto na competência quanto na data da operação.

Os15 testes dirigidos passaram com355 asserções na captura. Conferem cinco campos do arredondamento nativo, cobertura antiga consumida, conservação econômica/fiscal, ausência de receita/dedução derivada dos resíduos, base financeira e D2101. Prova/política ausentes, parcelas adulteradas com hash recalculado e total líquido usado como cobertura antiga são recusados. Suíte completa1.481/15.862, PHPStan sem erros e Pint420 aprovados; demais regressões e auditoria final ficam registradas no relatório próprio36.

Dez snapshots e replays exatos, dez fechamentos mensais e60 corpos XML foram comparados. Cada campo do oráculo diário, saldo de abertura/fechamento, IBS/CBS separados, movimentos D1101 e dados D2101 foi conferido. XSD1.3.0 aponta somente assinatura ausente. D2101 é34,04/base734,04 nas vendas parciais e18,43/base718,43 nas totais. Os24 XMLs e quatro replays anteriores de julho continuam nos caminhos35 originais, sob regressão própria, totalizando14 decisões e84 XMLs dos dois primeiros meses; esses números não incluem setembro.

Os30 arquivos volumosos da evidência corrente foram comprimidos com hashes idênticos. `storage/app/phase4b-realization-three-period-august-draft-20261008-28` conserva corpus, testes, logs e auditorias. A preparação inicial36 permanece preservada, sem reescrita para representar captura posteriormente realizada.

Validação final36 concluída: regressões35/34/33/32/30/29/27/25/23/21/18 e candidatos27/26/25 aprovadas;5.664 históricos preservados e49 fontes oficiais conferidas. `august-progress-report.json` registra o conjunto completo e conserva `full_three_month_chain_accepted=false`, `scope28_completed=false` e `production_ready=false`. Julho e agosto estão validados neste recorte; a venda final de setembro permanece pendente.

## Preparação da terceira venda — draft37

Seis fechamentos parciais36 alimentam seis aberturas de setembro com o contrato de alocação corrente independente34. Fontes e memórias anteriores foram reproduzidas; os componentes fiscais e econômicos coincidiram. O arredondamento criado na venda de agosto permanece explicitamente fora da cobertura fiscal antiga. A preparação não altera os contratos anteriores nem produz uma decisão de venda aceita em setembro.

Doze fixtures de venda parcial/total após nova apropriação50,00/PIS5,00 foram geradas e reproduzidas com comparação exata. O oráculo decimal mantém consumo do arredondamento coberto, consumo do resíduo criado anteriormente e diferença nova da terceira baixa separados. A memória nativa com as21 taxas oficiais completas de setembro coincidiu com todos os campos do oráculo nos12 casos. A base D2101 preparatória é13,70 nas vendas parciais e5,35 nas totais; permanece dependente da integração de realização, decisão financeira e fechamento mensal.

Um primeiro conjunto de seis inputs de abertura usava a competência atual na chave de referência da fonte. Os inputs e o builder originais foram preservados com hashes; o builder corrigido deriva a competência da posição anterior. Os seis inputs corrigidos passaram pela qualificação nativa. A evidência inicial não representa aceitação.

Registros37 em `storage/app/phase4b-realization-third-sale-rounding-draft-20261008-28`. Scripts: `build_phase4b_tvm_third_sale_rounding_draft.py`, `prepare_phase4b_tvm_third_sale_fixtures.py`, `check_phase4b_third_sale_openings.php` e `check_phase4b_third_sale_memory.php`. [Objetivo de integração final](phase4b-third-sale-rounding-integration-next.md). A cadeia integral, escopo28, Fase4B e produção continuam abertos/bloqueados.

## Integração38 validada

Atualização08/10 — draft38 integrou as12 vendas de setembro, com12 replays e72 XMLs. A cadeia sintética julho/agosto/setembro foi validada com26 decisões e156 XMLs, sem misturar arredondamento coberto, resíduo anterior e diferença nova. Suíte1.499/16.312,18 testes específicos/450 asserções,11 verificações adicionais de provas, PHPStan/Pint e regressões aprovadas. Auditoria5.871 históricos preservados e49 fontes oficiais. O recorte de três períodos foi aceito localmente; escopo28 integral/Fase4B continuam abertos e produção bloqueada. [Contrato, evidências e limites](phase4b-third-sale-integrated-development.md). [Próximo objetivo](phase4b-next-scope-historical-multiple-titles.md).

