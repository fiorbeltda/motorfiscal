# Próximo objetivo28 — novo arredondamento na venda posterior

Recorte concluído no draft33, sem aceitação integral28/Fase4B. [Contrato e validação](phase4b-historical-sale-rounding-development.md). [Próximo objetivo de transporte do fechamento](phase4b-created-rounding-history-next.md). O plano original abaixo fica preservado.

Objetivo: reconciliar a baixa parcial de uma posição cujo histórico reproduzido já contém curva e arredondamento, quando a venda atual cria uma nova diferença entre o valor médio em oito casas e a baixa contábil em centavos. Conservar o arredondamento antigo consumido, a diferença criada na venda e o saldo restante separadamente da cobertura fiscal anterior.

Partida: os drafts31/32 comprovam fechamento histórico explícito e consumo do arredondamento anterior. Suas baixas atuais não criam novo resíduo. O contrato de primeira aquisição do draft30 não deve ser reutilizado automaticamente para posições com histórico.

Usar as quatro fontes de agosto efetivamente reproduzidas e rendimentos atuais de maior magnitude antes da venda parcial/total de setembro. Cobrir arredondamento novo positivo, negativo e zero, com PIS/Cofins atual quando qualificado. A diferença fiscal já apurada anteriormente deve ser consumida somente na parcela comprovada de curva/arredondamento antigo; a diferença criada agora não constitui automaticamente rendimento anterior, receita nova ou dedução.

Nova prova optativa deve vincular memória nativa, operação, valor médio não arredondado, baixa contábil fornecida, arredondamento antigo consumido e diferença criada. A memória econômica precisa conservar ambos os componentes; o saldo fiscal anterior deve permanecer limitado à disponibilidade da decisão fonte. Não permitir que a parcela nova seja usada para satisfazer artificialmente a soma fiscal anterior.

Aceitação: bundle/controles próprios, oráculo decimal independente, corpus positivo/negativo, provas ausentes/trocadas/excessivas recusadas, snapshots/replays, fechamento mensal e XMLs dos dois períodos, fontes/históricos auditados e regressões drafts32/30 e demais escopos. Transporte adicional do novo fechamento a um terceiro período, fontes com exclusão/PIS anteriores, fundos e cadeia histórica ampla exigem cobertura própria; não declarar aceitação integral28/Fase4B ou produção por esta validação.
