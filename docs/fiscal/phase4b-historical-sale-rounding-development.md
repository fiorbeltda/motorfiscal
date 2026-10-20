# Desenvolvimento28 — arredondamento criado na venda com histórico

O draft33 distingue o arredondamento antigo consumido pela venda da diferença criada entre a baixa média em oito casas e o valor contábil fornecido em centavos. Usa quatro fontes sintéticas de agosto do draft31, efetivamente capturadas no corpus31/32, e dez vendas de setembro. A decisão anterior é efetivamente reproduzida; os componentes econômicos e fiscais continuam separados.

A política optativa `PROVED_HISTORICAL_AVERAGE_RELEASE_WITH_SEPARATE_CREATED_ROUNDING_8_2` exige histórico com fechamento líquido explícito, uma única venda, ausência de operações adicionais, valor justo, rendimento novo produzido na alienação e ganho contábil. A memória nativa determina quantidade e valor contábil anteriores, média liberada, baixa arredondada e parcela proporcional do arredondamento antigo. Prova independente vincula fornecedor, competência, título, operação, memória e conjunto de fontes históricas às cinco parcelas calculadas. O motor confere o valor fornecido e não realiza lançamentos.

O histórico abre com500 unidades/custo5.000. Os casos normais apropriam50,00/PIS5,00 antes da venda de250 ou500 unidades. Os dois controles de sinais alinhados apropriam50,02/PIS5,00 antes da venda parcial. As fontes anteriores conservam seus saldos fiscais efetivamente apurados ou de perda não compensada; a nova diferença de arredondamento não os amplia.

| Venda parcial | Média liberada | Baixa contábil | Antigo consumido | Criado na venda | Total liberado |
|---|---:|---:|---:|---:|---:|
| Histórico negativo |2.524,995|2.525,00|−0,0025|+0,005|+0,0025|
| Histórico positivo |2.525,005|2.525,00|+0,0025|−0,005|−0,0025|
| Histórico zero |2.525,000|2.525,00|0|0|0|
| Histórico exato |2.525,005|2.525,00|0|−0,005|−0,005|
| Positivo alinhado |2.525,015|2.525,02|+0,0025|+0,005|+0,0075|
| Negativo alinhado |2.525,005|2.525,00|−0,0025|−0,005|−0,0075|

As quatro vendas totais não criam diferença adicional e encerram os componentes. Na venda parcial, o fechamento conserva o restante do arredondamento antigo e o inverso da diferença criada na baixa. `rounding_inventory` expõe abertura, consumo antigo, criação atual e ambos os saldos finais. O limite de meio centavo se aplica à diferença nova calculada, enquanto o consumo antigo é limitado ao saldo efetivamente reproduzido; a soma das duas parcelas pode superar meio centavo.

A cobertura fiscal anterior usa apenas o arredondamento antigo consumido. Curva corrente, PIS/Cofins, rendimento anteriormente apurado e perda pública anterior continuam em componentes próprios. Não se cria receita ou dedução a partir da diferença contábil. A base D2101 atual é conferida com oráculo decimal diário e Selic oficial; não é presumida como45,00 em todos os casos, pois a venda altera a posição que permanece aplicada durante o mês.

Os testes recusam prova ausente, política ausente, parcelas antigas/novas adulteradas mesmo com hash recalculado e uso do total líquido como cobertura antiga. Os casos alinhados verificam que conservar o total não substitui a prova de cada parcela. O corpus confere IBS/CBS separados, deduções genéricas, fechamento econômico/fiscal, memória diária, movimentos D1101 e todos os campos do título D2101.

Evidências próprias: `storage/app/phase4b-realization-historical-sale-rounding-draft-20261008-28`. As quatro fontes mensais de agosto são reutilizadas dos caminhos originais e reproduzidas, sem fabricar novas decisões anteriores. Dez capturas atuais completam14 replays e84 corpos XML:24 anteriores e60 atuais. Os seis eventos suportados permanecem candidatos locais sem assinatura e com produção bloqueada.

Fontes e interpretação: MOD1.3.0, páginas físicas66/67 e86/87; [LC214 consolidada na Câmara](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html), art195. `research.json` registra hashes e a distinção entre conservação econômica e cobertura fiscal. [Publicação oficial da documentação1.3.0](https://www.gov.br/fazenda/pt-br/assuntos/noticias/2026/outubro/receita-federal-publica-versao-1-3-0-da-documentacao-tecnica-da-dere/) novamente consultada; a captura preservada e seu hash constam em `publication-confirmation.json`. A convenção local não constitui homologação normativa.

Limites: fonte inicial sem PIS/exclusão anterior, rota direta, uma venda por competência e sem valor justo/ganho contábil. O transporte do fechamento criado agora para outro período, fontes com componentes fiscais adicionais, fundos e combinações de aquisições/baixas exigem cobertura própria. Recepção, transmissão, pagamento e homologação não foram estabelecidos. Escopo28 integral e Fase4B permanecem abertos; candidato27 continua sendo a última aceitação local.

Validação concluída:15 testes dirigidos/355 asserções na captura; suíte final1.444/15.357, PHPStan sem erros e Pint414 arquivos aprovado. Regressões drafts32/30/29/27/25/23/21/18 e candidatos27/26/25 aprovadas. Bundle, controles e fixtures reproduzidos com comparação exata. Auditoria final5.396 artefatos históricos preservados e49 fontes oficiais conferidas; gate bloqueado. Relatório: `storage/app/phase4b-realization-historical-sale-rounding-draft-20261008-28/progress-report.json`. A suíte final foi executada depois da correção da tipagem nullable do histórico; os registros iniciais de PHPStan e da suíte anterior foram preservados em `initial-validation-preservation.json`.

Reprodução: `scripts/build_phase4b_tvm_historical_sale_rounding_draft.py` e `scripts/verify_phase4b_tvm_historical_sale_rounding_draft.py`. Verificação ampla e capturas usam512MB nos comandos locais. Compressão NTFS conserva os bytes e caminhos; registros de hashes anteriores/posteriores permitem conferir os arquivos comprimidos novamente.

[Próximo objetivo de transporte do fechamento](phase4b-created-rounding-history-next.md).
