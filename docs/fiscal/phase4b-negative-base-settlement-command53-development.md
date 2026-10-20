# Integração local da proposta de apuração com BCN no comando mensal — 53

O comando `fiscal:dere:project-monthly-closing` passou a aceitar `--negative-base-settlement-request`. A política é selecionada do conhecimento capturado no snapshot próprio52; o comando reproduz a avaliação financeira e a projeção mensal original antes de verificar a solicitação. Snapshot, entrada mensal, projeção original, operação e débitos brutos permanecem vinculados por hashes.

Quando apenas a solicitação de apuração é apresentada, ela também fornece os parâmetros e a cadeia anterior para o D1199. Quando as duas solicitações são apresentadas, os parâmetros e os eventos anteriores devem ser idênticos. Divergências são rejeitadas antes de escrever relatórios e candidatos XML.

A proposta integra o relatório mensal com bases após gross-down, deduções por raiz, estoques remanescentes e impostos nominais separados. O estágio financeiro original permanece preservado. A integração não autentica recibos governamentais nem habilita contabilização ou produção.

## Evidências disponíveis

- A suíte anterior à integração53 terminou com 1.974 testes e 44.446 asserções; o suplemento52 registra os resultados e preserva o relatório histórico do protótipo.
- Os testes de integração50/53 passaram: 19 testes, 128 asserções. Cobrem seleção derivada e explícita, ausência da nova opção, cinco hashes alterados, solicitação real não qualificada, lacuna de período anterior, divergência entre solicitações e bundle sem política.
- Duas execuções nativas53 foram capturadas e reproduzidas exatamente. Dez hashes XML coincidem com os candidatos anteriores de declaração; IBS nominal 0,84 e CBS nominal 7,93 coincidem com a proposta preparada.
- A análise estática geral passou depois de corrigir os tipos das leituras no teste. As falhas iniciais de análise estática e formatação permanecem registradas.
- A formatação geral passou para 470 arquivos. A compatibilidade posterior confirmou as três execuções mensais preservadas e 15 hashes XML.
- As regressões passaram: candidato27 com 27 casos mensais/11 do motor, 120 XMLs, 20 replays e 3.114 artefatos históricos; candidato26 com 44 casos, 16 replays, seis guardas e 2.849 artefatos; candidato25 com 24 casos mensais/15 do motor, 72 XMLs, 12 replays e 2.593 artefatos.
- A fonte aceita50 foi arquivada antes da alteração. A primeira proposta, a proposta revista, a fonte anterior à formatação e as versões anteriores do teste foram preservadas.

Os arquivos estão em `storage/app/phase4b-negative-base53-command-integration-20261009`. A captura nativa ocorreu antes de uma correção exclusivamente de indentação e ordenação de imports; a suíte completa53 e a verificação posterior de compatibilidade foram iniciadas depois da formatação. O resultado da suíte ainda precisa ser inspecionado antes da aceitação do escopo53.

## Pendências de aceitação

Concluir a suíte completa53 e executar a auditoria final preparada em `scripts/audit_phase4b_negative_base53_monthly_settlement.py`. O relatório final deve verificar a fonte arquivada50 e a migração explícita, sem modificar hashes de evidências anteriores.

Permanecem abertas a classificação financeira ampla, as receitas de transição e o vínculo D9121, a geração integrada de novas origens negativas, a autenticação de retornos, a qualificação do calendário de expiração, o reprocessamento de períodos dependentes e as demais condições dos escopos28/49/51 e da Fase4B. `production_ready=false`.
