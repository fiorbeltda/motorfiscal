# Desenvolvimento28 — amortização e cupom sem venda histórica

Os drafts26 (agosto) e27 (setembro) acrescentam baixa pública histórica sem venda no mês. A política optativa `REPLAYED_PUBLIC_AMORTIZATION_OR_COUPON_WITHOUT_SALE` conserva o contrato anterior: sem ativação explícita, uma lista vazia de vendas continua recusada. O novo caminho exige título público elegível, histórico fiscal efetivamente reproduzido e lista não vazia cobrindo exatamente todas as operações de amortização/cupom da memória diária. Compra isolada ou ausência de baixa não satisfazem o escopo. Valor justo diferido e ponte de arredondamento são zero neste recorte, nas bordas e nas baixas.

O inventário independente comprova cada consumo e saldo diário; a declaração completa vincula posição, baixas e inventário. A análise só emite `public_other_release_without_sale_reconciled=true` depois dessa reconciliação. O qualificador público repete a análise com a mesma política e memória, usando o marcador calculado. O fechamento histórico é transportado pela política já existente, a partir da fonte financeira reproduzida e da curva corrente ainda retida. O novo marcador não aparece nas decisões antigas que contêm vendas.

Julho reconheceu100 apurado. Agosto apropriou50/PIS5 antes da baixa de12/08. A amortização baixa2.030 de valor contábil e2.000 de custo de aquisição, conservando quantidade1.000/custo8.000/saldo8.120. O cupom recebe30, conserva quantidade1.000/custo10.000 e fecha saldo10.120. Em ambos, a baixa consome20 do rendimento anterior e10 da curva corrente; o fechamento mantém80 anterior e40 da curva de agosto. Esta última corresponde a36 apurado/PIS4 da decisão própria. Setembro abre116 apurado/PIS4, total120, com nova curva50/PIS5 antes da venda.

| Origem histórica | D2101 parcial | Base parcial | D2101 total | Base total |
|---|---:|---:|---:|---:|
| Amortização sem venda |45,00|745,00|28,12|728,12|
| Cupom sem venda |45,00|745,00|36,40|736,40|

A venda parcial conserva58 apurado/PIS2 anteriores e25 da curva de setembro; a total zera os componentes históricos. A curva corrente tem origem fiscal única D2101 e conta D1101 com `vApur=0.00`; o saldo antigo não produz receita ou dedução atual. O cupom30 aparece somente no recebimento de agosto, com recebimento zero em setembro.

Quatorze testes dirigidos/198 asserções na captura cobrem dois históricos e quatro realizações, política ausente, baixa ausente/duplicada, alocação fiscal divergente, valor justo não suportado, diferença contábil da baixa incompatível, compra isolada e mês sem baixa. Os dois últimos controles têm memória diária válida antes da recusa de escopo. Seis replays e24 corpos XML conferidos nos seis eventos mensais, com somente assinatura ausente no XSD1.3.0. Todos os componentes e dias do oráculo decimal independente são comparados ao cálculo nativo.

Fonte atual: MOD1.3.0, página impressa85/física86, SHA256 `b7756c7b0cec5cff46d77e1b1ea5d657a4e2476fa6033f8192561c17dd70ab2d`. O manual distingue compras, amortizações do principal, recebimentos de juros/cupons e variação contábil, com abertura igual ao fechamento anterior por título/conta e fórmula mensal reconciliada. O valor2.000 neste cenário identifica custo de aquisição liberado; a baixa contábil é2.030. Os números e as provas são sintéticos, sem confirmação institucional, liquidação real ou pagamento fiscal anterior.

Validação ampla: suíte1.366 testes/11.851 asserções, PHPStan sem erros, Pint408 e regressões draft25/23/21/18 e candidatos27/26/25 aprovadas. Auditoria4.819 históricos e49 fontes oficiais. Os24 corpos XML são dos quatro cenários de setembro; agosto está demonstrado por decisão financeira e memória diária reproduzidas, sem captura mensal própria ou recepção assinada provada.

Relatório final: `storage/app/phase4b-realization-public-release-only-draft-20261008-28/progress-report.json`. Fontes anteriores e evidências capturadas são preservadas; o candidato `2026.10.06-27` permanece a última integração aceita localmente. A aceitação integral28/4B e a produção continuam bloqueadas. Captura/projeção/replay aninhados e suíte ampla usam512MB somente nos comandos locais.

Pendências: combinações de fundos/perdas históricas com movimentos; sinais diários opostos/resíduos; novo rendimento na venda sem apropriação comprovada; escala do histórico e demais requisitos da fase. Este recorte resolve baixa sem venda com histórico positivo, mantendo esses limites explícitos.

[Próximo recorte de históricos negativos e fundos](phase4b-public-signed-release-history-next.md).
