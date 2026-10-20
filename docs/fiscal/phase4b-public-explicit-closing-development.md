# Desenvolvimento28 — fechamento explícito com curva e arredondamento

Os drafts31 (agosto) e32 (setembro) acrescentam uma alocação explícita do fechamento fiscal para títulos públicos com apropriações de sinais opostos e baixa parcial anterior. As fontes são decisões efetivamente capturadas e reproduzidas, com fechamento mensal de ambos os períodos. Não se usa a razão curva remanescente/variação líquida para inferir a alocação.

O contrato optativo `REPLAYED_PUBLIC_FIRST_ACQUISITION_NET_CLOSING_WITH_CARRYING_ROUNDING` exige uma única fonte pública do mês imediatamente anterior, primeira aquisição com compra e venda, ausência de valor justo, ganho contábil, PIS/Cofins e exclusão art195 na fonte, além de quantidade remanescente positiva. A política é distinta dos contratos históricos anteriores. Não estabelece um rateio normativo universal ou homologação.

A fonte reproduzida conserva o rendimento fiscal mensal conhecido entre parcela líquida realizada e fechamento retido. A parcela realizada deriva da baixa contábil menos custo de aquisição liberado, enquanto curva e arredondamento retidos continuam identificados separadamente. Prova independente vincula fornecedor, título/conta, competências, snapshot, decisão, análise, memória, componentes mensais e retidos e origem D2101. Retenção adicional sobre um fechamento já retido é recusada.

As quatro fontes possuem compra inicial de1.000 unidades/custo10.000 e venda de500 unidades. O fechamento anterior é:

| Fonte | Saldo contábil | Curva retida | Arredondamento retido | Saldo fiscal retido |
|---|---:|---:|---:|---:|
| +1,005 / −1,015 |4.999,99|−0,005|−0,005|−0,01 de perda pública não compensada|
| +1,015 / −1,005 |5.000,01|+0,005|+0,005|+0,01 já apurado|
| +1,005 / −1,005 |5.000,00|0|0|0|
| +1,00 / −0,98 |5.000,01|+0,01|0|+0,01 já apurado|

O custo remanescente é5.000 em todos os casos. No controle exato, o rendimento mensal0,02 se divide em0,01 realizado e0,01 retido. Nos pares de um centavo, a baixa contábil arredondada tem diferença líquida zero frente ao custo liberado; o centavo permanece integralmente no fechamento. Sua cobertura fiscal é comprovada sobre o saldo líquido, sem reclassificar o arredondamento como renda fiscal nova.

Setembro cobre venda parcial de250 unidades e venda total de500 unidades. Apropria0,01 nos históricos positivo, negativo e exato, ou0,02 no controle de saldo zero. Os valores fornecidos para essas baixas são exatamente representáveis em centavos, isolando o consumo dos componentes anteriores. D2101 é0,01 (base700,01) nos três primeiros grupos e0,02 (base700,02) no saldo zero, em ambas as modalidades. O rendimento anterior não é tributado novamente e a perda pública anterior não é deduzida da base atual.

A memória econômica mantém `prior_produced_return` e `rounding_bridge` separados. O saldo fiscal reproduzido cobre o total líquido desses dois componentes, conservando renda apurada, exclusão, PIS e perda em seus campos próprios. A realização exige prova do arredondamento anteriormente reconhecido e a conservação de cada componente disponível, além da igualdade do total. O fechamento fiscal remanescente é conferido separadamente do saldo contábil e da curva atual.

Os testes dirigidos recusam alocação ausente, hash adulterado, componente de arredondamento trocado, origem D1101 indevida, alegação de homologação, retenção duplicada, snapshot alterado, política ausente, consumo fiscal indisponível e abertura economicamente redistribuída sem conservar seus componentes próprios. Os helpers de captura passaram a suportar agosto com competência, datas de operação e provas próprias; as saídas anteriores continuam sob comparação exata.

Validação:22 testes dirigidos/282 asserções na captura; suíte ampla1.429/15.022, PHPStan sem erros e Pint413 aprovados. Doze replays exatos e72 corpos XML:24 das quatro fontes mensais de agosto e48 das oito realizações de setembro. Todos os campos do oráculo diário disponível, componentes econômicos/fiscais, IBS/CBS, movimentos D1101 e dados D2101 foram comparados. XSD1.3.0 aponta somente ausência de assinatura. Regressões drafts30/29/27/25/23/21/18 e candidatos27/26/25 aprovadas. Bundle, controles e inputs foram novamente reproduzidos com comparação exata. Auditoria5.218 históricos preservados e49 fontes conferidas; gate bloqueado. Relatório: `storage/app/phase4b-realization-public-explicit-closing-draft-20261008-28/progress-report.json`.

Fontes: [LC214 consolidada na Câmara](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html), art195; MOD1.3.0, páginas físicas66/67 e86/87. A nova consulta da lei mantém o SHA256 `dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`; manual `b7756c7b0cec5cff46d77e1b1ea5d657a4e2476fa6033f8192561c17dd70ab2d`. Pesquisa e interpretação local em `storage/app/phase4b-realization-public-explicit-closing-draft-20261008-28/research.json`.

Uma primeira preparação tinha a data da operação financeira ainda em setembro, embora a competência e o ledger fossem de agosto. O motor recusou a inconsistência. Os quatro inputs iniciais e o snapshot recusado foram preservados antes da correção, com seus hashes em `initial-operation-date-preservation.json`. Não representam evidência positiva de aceitação.

Limitações: esta matriz usa rota direta e fonte inicial sem exclusão/PIS. Cadeias mais amplas, fontes com histórico próprio, valor justo, bases fiscais com componentes adicionais e arredondamento novo criado pela venda posterior permanecem sem aceitação específica. Líquido mensal zero com parcela realizada/retida líquida não nula continua recusado nesta política, sem inventar renda ou perda fiscal para suprir a falta de prova. Os XMLs locais não têm assinatura; recepção, transmissão, pagamento e homologação continuam não estabelecidos. Escopo28 integral, Fase4B e produção permanecem abertos/bloqueados.

Reprodução: `scripts/build_phase4b_tvm_public_explicit_closing_draft.py prepare/current` e `scripts/verify_phase4b_tvm_public_explicit_closing_draft.py`. Capturas extensas e suíte geral usam512MB nos comandos locais, sem mudança global.

[Próximo objetivo de arredondamento na venda posterior](phase4b-historical-sale-rounding-next.md).
