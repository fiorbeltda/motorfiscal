# Desenvolvimento28 — baixa histórica com perda pública e fundo

Os drafts28 (agosto) e29 (setembro) cobrem seis históricos sem venda no mês da amortização/cupom: quatro com rendimento negativo por rota direta/fundo e dois positivos via fundo. Cada histórico alimenta realizações parcial e total de setembro. Os componentes anteriormente apurados e a perda pública não compensada são preservados individualmente, sem nova tributação do rendimento antigo ou dedução automática da perda.

A cobertura usa as políticas optativas já reconciliadas nos drafts26/27. O helper de captura passou a aceitar o valor financeiro bruto da própria fixture; o padrão50 permanece nos casos antigos. Nos novos negativos, o valor bruto250 tem natureza devedora na qualificação pública, não é receita positiva. Não foram modificados os contratos de produção, fontes oficiais ou bundles anteriores.

A fonte de julho é uma decisão efetivamente capturada com100 apurado. A rota fundo reutiliza `fund-net-negative-2026-07-snapshot.json`, com conta `debtfund000` e elegibilidade95%; a rota direta mantém sua própria conta/histórico. O verificador compara a rota e a conta ao título da fonte anterior, além de reproduzir o cálculo e a decisão. As provas institucionais continuam sintéticas.

Agosto negativo apropria−250/PIS0 antes da baixa:

- Amortização: baixa contábil1.970 e custo de aquisição liberado2.000, com consumo20 anterior/−50 corrente. Fecha quantidade1.000/custo8.000/saldo7.880; conserva80 apurado anterior e−200 de perda corrente. A abertura fiscal seguinte tem80 apurado/−200 perda, total−120.
- Cupom: recebimento30 com consumo80 anterior/−50 corrente. Fecha quantidade1.000/custo10.000/saldo9.820; conserva20 apurado e−200 de perda, total−180. O cupom não reaparece como recebimento em setembro.

Nos dois positivos via fundo, agosto apropria50/PIS5 e a baixa consome20 anterior/10 corrente. O fechamento conserva80 anterior/40 corrente, transportando116 apurado/PIS4. O saldo contábil final é8.120 para amortização ou10.120 para cupom. Valor de custo liberado não é uma declaração de principal nominal ou liquidação institucional.

Setembro apropria50/PIS5 antes da venda. Resultado igual para as duas rotas, preservando os componentes próprios:

| Tipo da baixa anterior | D2101 parcial | Base parcial | D2101 total | Base total |
|---|---:|---:|---:|---:|
| Amortização |45,00|745,00|28,12|728,12|
| Cupom |45,00|745,00|36,40|736,40|

A venda parcial retém metade do apurado anterior, PIS e perda em seus componentes respectivos. A total encerra esses componentes. A perda antiga não entra nas deduções correntes; a dedução genérica300 permanece separada. A curva atual tem origem única D2101, com `vApur=0.00` na conta D1101 correspondente. Perda mensal pública continua com base isolada zero e sem abatimento de outro título.

Validação:21 testes dirigidos/405 asserções na captura, incluindo consumo acima da disponibilidade, tentativa de tornar a perda positiva e fundo com94%. Suíte ampla1.387/12.226, PHPStan sem erros e Pint409 aprovados. Dezoito replays exatos e72 corpos XML atuais, nos seis eventos mensais, com somente assinatura ausente no XSD1.3.0. O verificador compara todos os componentes e dias do oráculo decimal independente e cada componente fiscal da baixa/fechamento. Regressões drafts27/25/23/21/18 e candidatos27/26/25 aprovadas;4.901 históricos e49 fontes conferidos.

A [LC214 consolidada na Câmara](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html), art195, §§1º e2º, foi reconfirmada em08/10: mantém a exclusão do rendimento público acima da Selic e sua aplicação à rota de fundo exclusivo com ao menos95% desses títulos. A nova captura oficial tem o mesmo SHA256 da fonte preservada (`dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`). O MOD1.3.0, páginas físicas86/87 (impressas85/86), define movimentos, continuidade e base zero nas variações não positivas, sem compensação entre títulos. Fonte/hash/interpretação: `storage/app/phase4b-realization-public-signed-release-draft-20261008-28/research.json`.

Limitações: agosto está demonstrado por decisão financeira e memória reproduzidas; os72 XMLs são dos doze cenários atuais de setembro. Não há captura mensal própria de agosto, recepção assinada, pagamento anterior, transmissão ou homologação estabelecidos. A memória aninhada e a suíte ampla usam512MB nos comandos locais, sem alteração global. Compressão transparente Windows LZX preservou bytes/caminhos de182 arquivos conferidos; um replay nativo também confirmou a leitura após a compressão.

Relatório: `storage/app/phase4b-realization-public-signed-release-draft-20261008-28/progress-report.json`. Candidato `2026.10.06-27` permanece a última integração aceita localmente. Aceitação integral28/4B e produção continuam bloqueadas. Permanecem pendentes sinais diários opostos no mesmo mês, resíduos de arredondamento dos movimentos brutos, outras combinações com compras/baixas, novo rendimento de venda sem apropriação comprovada e escala do histórico.

[Próximo recorte de movimentos brutos e arredondamento](phase4b-public-gross-rounding-next.md).
