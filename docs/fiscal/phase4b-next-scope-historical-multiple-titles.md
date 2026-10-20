# Próximo objetivo28 — múltiplos títulos com histórico reproduzido

Ampliar a condição original de múltiplos títulos além da primeira aquisição privada já validada no draft6. A cobertura pública de fundos e históricos individuais já existe nos drafts12/15/18/21/29; não repetir essa cobertura como se estivesse ausente. O próximo recorte deve qualificar duas posições públicas com fontes financeiras próprias, fornecedor/competência comuns e fechamento mensal único.

Objetivo: demonstrar que a união de duas cadeias históricas conserva os saldos, a cobertura fiscal e a identidade por título/conta, sem duplicar receita genérica, dedução genérica, caixa, histórico ou apropriação. Incluir apropriação positiva de um título e negativa do outro, com zero de base do título negativo sem compensação cruzada. Projetar dois detTitulo D2101 e os movimentos D1101 por conta, com IBS/CBS calculados sobre a base efetivamente qualificada.

Primeiro produzir fontes anteriores efetivamente capturadas para os dois títulos, com ISINs sintéticos válidos e contas patrimoniais distintas. Não renomear snapshots aceitos nem transformar uma fonte em outra alterando provas. Qualificar as posições atuais e os diários próprios contra o livro comum. Conferir caixa compartilhado, contas de resultado distintas e, depois, contas de resultado compartilhadas quando o contrato e as evidências sustentarem a agregação. Uma limitação existente da conta compartilhada deve ser resolvida explicitamente ou documentada como pendência; não substituir esse requisito apenas por contas distintas.

Aceitação: fontes e decisões atuais reproduzidas; vendas parciais/totais; oráculo decimal independente para cada posição; conservação de cada parcela fiscal/econômica; dois títulos no D2101; livro agregado reconciliado e seis XMLs por fechamento; recusa de identidade trocada, duplicação de fonte/entrada, referência temporal inválida e compensação de perda entre títulos. Bundle/controles próprios, replays, fontes oficiais, hashes históricos e regressões dos contratos anteriores. Investigar antes de ampliar qualquer convenção local para resultado compartilhado. Produção permanece bloqueada; este recorte não equivale à aceitação integral28/Fase4B.

Partida prevista: integração38 da terceira venda com origens de arredondamento separadas. Sua aceitação deve ser comprovada pelo relatório próprio, sem usar esta nota de objetivo como evidência de conclusão.

Partida confirmada: o relatório38 validou a cadeia sintética de três períodos com26 replays,156 XMLs e5.871 históricos preservados. Inspeção inicial do `DereDebtYieldXmlProjector` mostrou agregação por conta de resultado e conferência exata das entradas de origem, movimentos brutos e ajustes de exclusão. Isso indica um caminho para o corpus compartilhado, mas não constitui prova de duas realizações históricas simultâneas; a validação própria continua necessária.

## Preparação39 capturada

O bundle próprio `2026.10.08-28-draft39` produziu duas novas fontes públicas de julho, com identidades distintas: BRFIXTDEB019/debt000 e BRFIXTDEB027/debt2000. Os ISINs são sintéticos e apenas cumprem o checksum; não se declara atribuição oficial. Os snapshots anteriores de outros corpora permaneceram intactos, e as novas fontes foram executadas e reproduzidas pelo motor.

A fonte positiva apropriou100,00, produziu D2101/base100,00 e fechou com saldo contábil10100,00. A negativa apropriou−150,00, produziu base0,00 e manteve perda não compensada150,00, com saldo9850,00. Ambas mantêm quantidade1000 e custo10000. Cada campo de memória diária foi comparado ao oráculo decimal independente. A reprodução do builder preservou os inputs e os snapshots; a auditoria conferiu6.062 artefatos anteriores sem alteração e49 fontes oficiais.

Evidências em `storage/app/phase4b-realization-historical-multiple-titles-draft-20261008-28/source-preparation-audit.json`. Builder: `scripts/build_phase4b_tvm_historical_multiple_sources_draft.py`. Este resultado valida somente as fontes individuais. A venda atual dos dois títulos, o livro comum, a conta de resultado compartilhada e o fechamento mensal conjunto permanecem pendentes. O próximo bundle deve qualificar a competência atual e a vinculação às duas fontes39 antes de executar o corpus conjunto. Escopo28/Fase4B abertos e produção bloqueada.

## Fechamento conjunto41 aceito localmente

O relatório41 concluiu o objetivo de duas contas patrimoniais distintas: oito replays,48 XMLs, resultado compartilhado/distinto e conservação das parcelas individuais. Confira [validação e limites](phase4b-historical-multiple-development.md). A conta patrimonial compartilhada é o [objetivo seguinte](phase4b-next-scope-shared-asset-account.md).
