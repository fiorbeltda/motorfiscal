# Desenvolvimento28 — amortização, cupom e compra adicional históricos

Os drafts24 (agosto) e25 (setembro) cobrem três históricos com movimentos antes de uma venda e seis realizações subsequentes parcial/total. O contrato usa o fechamento fiscal efetivamente reproduzido e a curva do próprio mês ainda retida; os componentes antigos não geram nova receita ou dedução. Não altera os bundles anteriores nem conclui a aceitação integral28.

O MOD1.3.0, página impressa85 (física86), distingue compras/entradas, principal amortizado/saídas, recebimentos de juros/cupons e variação contábil. A abertura seguinte conserva a mesma chave título/conta e coincide com o fechamento anterior. Fonte/hash e interpretação estão em `storage/app/phase4b-realization-public-other-movements-draft-20261008-28/research.json`.

Julho reconheceu100 apurado. Agosto apropriou50/PIS5 antes dos movimentos e da venda de14/08:

- Amortização: baixa contábil2.030 e principal2.000 em12/08, seguida de venda500 por4.060/custo4.000. Fecha quantidade500, custo4.000 e saldo4.060. Conserva40 do apurado anterior e20 da curva de agosto; setembro abre58 apurado/PIS2.
- Cupom: recebimento30 em12/08 reduz o saldo contábil, preservando o principal. Venda500 por5.060/custo5.000; fecha quantidade500/custo5.000/saldo5.060. A abertura fiscal de setembro também é58/PIS2. O recebimento30 pertence somente a agosto; setembro registra `vJurosRec=0.00`.
- Compra adicional: compra1.000 por12.000 em05/08 altera o custo médio para11; venda1.000 por11.075/custo11.000. Fecha quantidade1.000/custo11.000/saldo11.075. Conserva50 do apurado anterior e25 da curva de agosto; setembro abre72,50 apurado/PIS2,50.

Setembro apropria50/PIS5 antes de vender metade ou toda a posição. Resultado do D2101 e base financeira (incluindo base genérica700):

| Histórico | D2101 parcial | Base parcial | D2101 total | Base total |
|---|---:|---:|---:|---:|
| Amortização |24,91|724,91|11,56|711,56|
| Cupom |32,39|732,39|15,70|715,70|
| Compra adicional |45,00|745,00|40,54|740,54|

As vendas totais encerram os componentes históricos; as parciais conservam exatamente metade. O rendimento corrente tem origem única no D2101, com `vApur=0.00` na conta correspondente do D1101. Os movimentos brutos e todos os campos do título são comparados com os XMLs projetados.

Validação:12 testes dirigidos/189 asserções, incluindo falta de prova da amortização, alocação fiscal divergente e custo médio antigo após compra adicional. Suíte completa1.352/11.663 com512MB local, PHPStan sem erros e Pint407 aprovados. Nove decisões reproduzidas,36 corpos XML dos seis eventos mensais; validação XSD1.3.0 com somente assinatura ausente. O verificador também compara todos os dias e componentes do oráculo decimal independente. Evidências exclusivas, sem sobrescrever tentativas anteriores; três entradas iniciais com quantidade excessivamente precisa foram preservadas separadamente antes da correção.

Relatório final/auditoria: `storage/app/phase4b-realization-public-other-movements-draft-20261008-28/progress-report.json`. Produção continua bloqueada; o candidato27 permanece a última integração aceita localmente. Os históricos de agosto são decisões financeiras reproduzidas, sem prova de recepção mensal assinada ou pagamento anterior. Provas institucionais e contábeis são sintéticas.

Pendências: amortização/cupom sem venda no mês histórico; fundos e perdas históricas combinados com movimentos; sinais diários opostos e resíduos; novo rendimento na venda sem apropriação comprovada; escala/memória do histórico e demais requisitos integrais28/4B. Captura/projeção/replay aninhados exigem512MB nos comandos locais, sem mudança da configuração global. [Próximo recorte sem venda](phase4b-public-release-without-sale-next.md).
