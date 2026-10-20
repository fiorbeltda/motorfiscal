# Próximo objetivo28 — dois títulos históricos na mesma conta patrimonial

Ampliar o corpus de múltiplos títulos históricos para uma única conta patrimonial analítica. O MOD1.3.0, página física86/impressa85, identifica o D2101 pela chave composta ISIN/conta e admite múltiplos ISINs na mesma conta. A validação41 usa duas contas patrimoniais distintas, com caixa comum e contas de resultado distintas/compartilhadas; não constitui prova de estoque agregado por conta patrimonial.

Objetivo: preservar cada memória histórica e cada base D2101 por título enquanto o D1101 informa os saldos e movimentos agregados da conta patrimonial comum. O título com variação negativa continua com base zero e não reduz a base do outro. Conferir abertura agregada, apropriações positivas/negativas, saídas parciais/totais, saldo final, caixa e vínculos financeiros; repetir combinações com conta de resultado compartilhada e distinta.

Produzir fontes anteriores novas e efetivamente capturadas para os dois títulos na conta comum. Não renomear snapshots antigos para simular a conta nova. Reproduzir as duas fontes, qualificar as posições atuais e os diários próprios e agregar somente o livro fornecido. A abertura da conta patrimonial será a soma dos saldos dos títulos; a abertura do caixa comum será fornecida uma única vez. Os ajustes fiscais e as disponibilidades históricas permanecem separados por título, sem inferir transferência de cobertura entre eles.

Aceitação: dois detTitulo com a mesma cCta e ISINs distintos, D1101 agregado coerente com os dois cálculos independentes, venda parcial/total e combinações assimétricas, replays e seis XMLs por fechamento; guardas para saldo agregado errado, título omitido, fonte reutilizada, vínculo financeiro incompleto e compensação cruzada. Bundle/controles próprios, oráculos decimais, fontes oficiais e regressões41/38/29/21 e candidatos27/26/25. Escopo28/Fase4B permanecem abertos e produção bloqueada até aceitação integral.

A implementação deve partir do relatório final41 aprovado. Qualquer preparação anterior à aprovação final deve ser identificada como preparação, sem aceitação de fechamento conjunto.

Preparação42: duas fontes novas de julho, com BRFIXTDEB019 e BRFIXTDEB027 em debt000, foram capturadas e reproduzidas contra memórias independentes. Saldos10100,00/9850,00, abertura agregada19950,00; bases100,00/0,00 e perda150,00 preservadas por título. Auditoria6.296 históricos sem alteração e49 fontes. Evidência: `storage/app/phase4b-realization-shared-asset-sources-draft-20261009-28/source-preparation-audit.json`. Draft43 prepara quatro realizações correntes e regra optativa de agregação contábil; o fechamento conjunto atual ainda exige sua validação integral.

## Aceitação local43

O relatório43 concluiu o objetivo: oito replays,48 XMLs,24 testes específicos/687 asserções, suíte1.559/17.855,6.310 históricos preservados e49 fontes. Confira [implementação e limites](phase4b-shared-asset-historical-development.md). A consolidação integral28 continua pendente e constitui o [objetivo seguinte](phase4b-next-scope-realization-consolidation.md).
