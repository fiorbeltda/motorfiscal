# Versões públicas D2101/D9121 — 59

Objetivo: selecionar o evento público ativo entre inclusão, alteração, exclusão e reinclusão e vinculá-lo à composição mensal efetivamente reconstruída, sem somar versões substituídas nem inferir recepção governamental.

As fontes primárias preservadas confirmam que alteração substitui integralmente o evento anterior, a exclusão não reinicia a sequência, alteração/exclusão exigem o último recibo ativo e exclusão exige motivo. Motivo1 também exige processo válido no D1021. O MOD, página física88, bloqueia inclusão/alteração ordinária em competência encerrada e exige coerência com PGCC ativo e balancete. Essas condições precisam permanecer como guardas.

O protótipo preparado reconcilia quatro prefixos de uma cadeia sintética00→01→02→03 e recusa oito casos inválidos: reinício da sequência, inclusão duplicada, lacuna, período fechado, modo real, hash divergente, recibo ativo incorreto e falta de motivo de exclusão. A exclusão deixa o evento ativo ausente; a reinclusão recupera apenas a nova versão. O protótipo permanece fora da aplicação e sua saída não é elegível para composição ou contabilização.

Critérios de conclusão:

1. Preservar fontes, esquemas e implementações aceitas58; ativar a nova seleção por política/bundle próprio, mantendo os comportamentos anteriores.
2. Promover o seletor e testes permanentes para transições, recibos ativos, sequência contínua, datas de conhecimento, contexto do contribuinte/competência e campos obrigatórios/vedados por operação. Processo D1021 deve ser qualificado antes de admitir motivo1; falhas não podem virar autorização implícita.
3. Conferir a semântica de competência aberta/encerrada e reabertura antes de admitir alterações. Não concluir esse estado a partir de um sinalizador sem prova contextual; a preparação atual usa apenas declaração sintética explícita de período aberto.
4. Reconstruir o livro, PGCC, memória financeira e D2101 correspondente à versão ativa. Somar vApur e conferir a fórmula, saldos e fontes de cada título. O recibo PGCC do retorno precisa corresponder ao mapeamento qualificado da competência, além de satisfazer o XSD.
5. Integrar o total ativo ao fechamento mensal, preservar IBS/CBS e estoque BCN por origem e manter as versões antigas como histórico. Exclusão sem reinclusão deve bloquear a apuração que dependa do evento. Não usar seqEvento do D2101 para formar codBCN de fechamento.
6. Capturar cenários/retornos/solicitações/relatórios próprios, replays exatos, testes negativos, regressões dos corpora56/58 e candidatos27/26/25, qualidade e suíte completa antes da aceitação local.

O primeiro fixture do protótipo tinha apenas coerência agregada; foi preservado e substituído por um fixture que conserva vPisCofins e a igualdade aritmética vApur=min(vSelic,vVarMensal)−vPisCofins. Isso não prova a curva ou o livro subjacentes. A reconciliação econômica nativa da versão ativa e a autenticação governamental continuam pendentes, assim como o escopo54 amplo e a Fase4B. Produção permanece bloqueada.
